<?php
/**
 * Contact and volunteer forms: server-side handling that replaces the static site's mailto:
 * links.
 *
 * Protection layers: WordPress nonce (refreshable for cached pages), signed time token
 * (rejects instant bot posts and stale replays), honeypot field, per-visitor rate limit
 * (hashed IP in a transient, nothing else stored), strict validation and length limits,
 * header-injection-safe wp_mail(). Messages are emailed and never stored in the database.
 *
 * @package CorcovadoFoundationCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Forms.
 */
class CF_Forms {

	const NS        = 'cf/v1';
	const NONCE     = 'cf_form';
	const MIN_DELAY = 3;          // Seconds a human needs at least to fill a form.
	const MAX_AGE   = DAY_IN_SECONDS;

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	/**
	 * Field definitions: name => [required, max length, type].
	 *
	 * @param string $form Form.
	 */
	public static function fields( $form ) {
		if ( 'volunteer' === $form ) {
			return array(
				'full_name'    => array( true, 120, 'text' ),
				'email'        => array( true, 190, 'email' ),
				'phone'        => array( false, 40, 'text' ),
				'country'      => array( true, 80, 'text' ),
				'program'      => array( true, 120, 'text' ),
				'dates'        => array( true, 120, 'text' ),
				'experience'   => array( false, 80, 'text' ),
				'availability' => array( false, 80, 'text' ),
				'message'      => array( true, 5000, 'textarea' ),
			);
		}
		return array(
			'name'    => array( true, 120, 'text' ),
			'email'   => array( true, 190, 'email' ),
			'phone'   => array( false, 40, 'text' ),
			'subject' => array( false, 120, 'text' ),
			'message' => array( true, 5000, 'textarea' ),
		);
	}

	/**
	 * REST routes.
	 */
	public static function routes() {
		register_rest_route(
			self::NS,
			'/form-token',
			array(
				'methods'             => 'GET',
				'permission_callback' => '__return_true',
				'callback'            => static function () {
					$r = rest_ensure_response( self::client_token() );
					$r->header( 'Cache-Control', 'no-store, private' );
					return $r;
				},
			)
		);
		foreach ( array( 'contact', 'volunteer' ) as $form ) {
			register_rest_route(
				self::NS,
				'/' . $form,
				array(
					'methods'             => 'POST',
					'permission_callback' => '__return_true',
					'callback'            => static fn( WP_REST_Request $request ) => self::handle( $form, $request ),
				)
			);
		}
	}

	/**
	 * Values the browser needs: nonce + signed timestamp.
	 */
	public static function client_token() {
		$ts = time();
		return array(
			'nonce' => wp_create_nonce( self::NONCE ),
			'token' => $ts . '.' . self::sign( (string) $ts ),
		);
	}

	/**
	 * HMAC with a site secret (never sent to the browser).
	 *
	 * @param string $data Data.
	 */
	private static function sign( $data ) {
		return substr( hash_hmac( 'sha256', $data, wp_salt( 'nonce' ) ), 0, 32 );
	}

	/**
	 * Visitor IP (REMOTE_ADDR only; proxies can be trusted through the "cf_client_ip" filter).
	 */
	private static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		/**
		 * Filters the visitor IP used for rate limiting (e.g. to read CF-Connecting-IP when the
		 * site is behind Cloudflare and the origin only accepts Cloudflare traffic).
		 *
		 * @param string $ip IP address.
		 */
		return (string) apply_filters( 'cf_client_ip', $ip );
	}

	/**
	 * Error response.
	 *
	 * @param string $code   Code.
	 * @param int    $status HTTP status.
	 * @param array  $extra  Extra data.
	 */
	private static function error( $code, $status, $extra = array() ) {
		$r = new WP_REST_Response( array_merge( array( 'ok' => false, 'code' => $code ), $extra ), $status );
		$r->header( 'Cache-Control', 'no-store, private' );
		return $r;
	}

	/**
	 * Handle a submission.
	 *
	 * @param string          $form    Form name.
	 * @param WP_REST_Request $request Request.
	 */
	public static function handle( $form, WP_REST_Request $request ) {
		$params = $request->get_body_params();
		if ( empty( $params ) ) {
			$params = (array) $request->get_json_params();
		}

		// 1. Honeypot: silently accept (bots get no signal), send nothing.
		if ( ! empty( $params['website'] ) ) {
			return rest_ensure_response( array( 'ok' => true ) );
		}

		// 2. Nonce (expired nonces on cached pages: the browser asks for a fresh one and retries).
		$nonce = isset( $params['_cf_nonce'] ) ? sanitize_text_field( (string) $params['_cf_nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			return self::error( 'invalid_nonce', 403 );
		}

		// 3. Signed time token.
		$token = isset( $params['_cf_token'] ) ? (string) $params['_cf_token'] : '';
		$parts = explode( '.', $token, 2 );
		if ( 2 !== count( $parts ) || ! ctype_digit( $parts[0] ) || ! hash_equals( self::sign( $parts[0] ), $parts[1] ) ) {
			return self::error( 'invalid_nonce', 403 );
		}
		$age = time() - (int) $parts[0];
		if ( $age < self::MIN_DELAY ) {
			return self::error( 'too_fast', 429 );
		}
		if ( $age > self::MAX_AGE ) {
			return self::error( 'invalid_nonce', 403 );
		}

		// 4. Validation.
		$clean   = array();
		$missing = array();
		foreach ( self::fields( $form ) as $name => $def ) {
			list( $required, $max, $type ) = $def;
			$raw = isset( $params[ $name ] ) && is_scalar( $params[ $name ] ) ? (string) $params[ $name ] : '';
			$val = 'textarea' === $type ? sanitize_textarea_field( $raw ) : sanitize_text_field( $raw );
			$val = trim( $val );
			if ( 'email' === $type ) {
				$val = sanitize_email( $val );
				if ( '' !== $val && ! is_email( $val ) ) {
					$val = '';
				}
			}
			if ( function_exists( 'mb_substr' ) ) {
				$val = mb_substr( $val, 0, $max );
			} else {
				$val = substr( $val, 0, $max );
			}
			if ( $required && '' === $val ) {
				$missing[] = $name;
			}
			$clean[ $name ] = $val;
		}
		if ( $missing ) {
			return self::error( 'invalid', 400, array( 'fields' => $missing ) );
		}

		// 5. Rate limit (counted only for valid submissions).
		$key   = 'cf_rl_' . substr( hash_hmac( 'sha256', self::client_ip(), wp_salt( 'auth' ) ), 0, 40 );
		$count = (int) get_transient( $key );
		$limit = (int) CF_Settings::get( 'form_rate_limit', 5 );
		if ( $count >= $limit ) {
			return self::error( 'rate_limited', 429 );
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		// 6. Email.
		$lang = isset( $params['lang'] ) && 'es' === $params['lang'] ? 'es' : 'en';
		$mail = 'volunteer' === $form ? self::volunteer_mail( $clean ) : self::contact_mail( $clean, $lang );
		$to   = (string) CF_Settings::get( 'form_recipient', '' );
		if ( ! is_email( $to ) ) {
			return self::error( 'mail_failed', 500 );
		}
		$reply_name = trim( preg_replace( '/[\r\n"<>,;:]+/', ' ', 'volunteer' === $form ? $clean['full_name'] : $clean['name'] ) );
		$headers    = array(
			'Content-Type: text/plain; charset=UTF-8',
			'Reply-To: ' . ( '' !== $reply_name ? $reply_name . ' ' : '' ) . '<' . $clean['email'] . '>',
		);
		$subject = trim( preg_replace( '/[\r\n]+/', ' ', $mail['subject'] ) );
		$sent    = wp_mail( $to, $subject, $mail['body'], $headers );
		if ( ! $sent ) {
			return self::error( 'mail_failed', 500 );
		}
		$r = rest_ensure_response( array( 'ok' => true ) );
		$r->header( 'Cache-Control', 'no-store, private' );
		return $r;
	}

	/**
	 * Contact message (same wording as the static site's mailto body).
	 *
	 * @param array  $v    Values.
	 * @param string $lang Language.
	 */
	private static function contact_mail( $v, $lang ) {
		$es    = 'es' === $lang;
		$topic = '' !== $v['subject'] ? $v['subject'] : ( $es ? 'Consulta general' : 'General inquiry' );
		$body  = array(
			$es ? 'Mensaje desde el sitio web de Fundación Corcovado' : 'Message from the Corcovado Foundation website',
			( $es ? 'Nombre' : 'Name' ) . ': ' . $v['name'],
			( $es ? 'Correo' : 'Email' ) . ': ' . $v['email'],
			( $es ? 'Teléfono' : 'Phone' ) . ': ' . ( '' !== $v['phone'] ? $v['phone'] : ( $es ? 'No indicado' : 'Not provided' ) ),
			( $es ? 'Tema' : 'Subject' ) . ': ' . $topic,
			'',
			$es ? 'Mensaje:' : 'Message:',
			$v['message'],
		);
		return array(
			'subject' => ( $es ? 'Contacto web' : 'Website contact' ) . ' — ' . $topic,
			'body'    => implode( "\n", $body ),
		);
	}

	/**
	 * Volunteer application (same wording as the static site's mailto body).
	 *
	 * @param array $v Values.
	 */
	private static function volunteer_mail( $v ) {
		$np   = 'Not provided';
		$body = array(
			'Volunteer application from Corcovado Foundation website',
			'Full name: ' . $v['full_name'],
			'Email: ' . $v['email'],
			'Phone: ' . ( '' !== $v['phone'] ? $v['phone'] : $np ),
			'Country: ' . $v['country'],
			'Program of interest: ' . $v['program'],
			'Preferred dates: ' . $v['dates'],
			'Experience: ' . ( '' !== $v['experience'] ? $v['experience'] : $np ),
			'Availability: ' . ( '' !== $v['availability'] ? $v['availability'] : $np ),
			'',
			'Message:',
			$v['message'],
		);
		return array(
			'subject' => 'Volunteer application — ' . $v['program'],
			'body'    => implode( "\n", $body ),
		);
	}
}
