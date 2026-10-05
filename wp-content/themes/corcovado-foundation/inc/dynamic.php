<?php
/**
 * Dynamic regions of the page sections: the markup of each card is the original markup of the
 * static site; the data comes from the plugin's content types.
 *
 * @package CorcovadoFoundation
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'cf_render_dynamic', 'cf_render_dynamic', 10, 5 );

/**
 * Dispatcher.
 *
 * @param string $html    HTML.
 * @param string $type    Region type.
 * @param array  $o       Locked options.
 * @param array  $f       Editable labels.
 * @param array  $context Context.
 */
function cf_render_dynamic( $html, $type, $o, $f, $context ) {
	$lang = $context['lang'] ?? cf_current_lang();
	switch ( $type ) {
		case 'news':
			return cf_dyn_news( $o, $f, $lang );
		case 'events':
			return cf_dyn_events( $o, $f, $lang );
		case 'resources':
			return cf_dyn_resources( $o, $f, $lang );
		case 'partners':
			return cf_dyn_partners( $o, $f );
		case 'sponsor_members':
			return cf_dyn_sponsor_members( $o, $f );
		case 'team':
			return cf_dyn_team( $o, $f, $lang );
	}
	return $html;
}

/**
 * Reveal-delay class used by the original grids (cards 2 and 3 of each row of 3).
 *
 * @param int  $n      Index.
 * @param bool $delays Use delays.
 */
function cf_reveal_class( $n, $delays ) {
	if ( ! $delays || 0 === $n % 3 ) {
		return 'reveal';
	}
	return 'reveal reveal-delay-' . ( $n % 3 );
}

/**
 * News cards.
 *
 * @param array  $o    Options.
 * @param array  $f    Labels.
 * @param string $lang Language.
 */
function cf_dyn_news( $o, $f, $lang ) {
	$count = (int) ( $o['count'] ?? 0 );
	$tag   = in_array( $o['heading'] ?? 'h3', array( 'h2', 'h3', 'h4' ), true ) ? $o['heading'] : 'h3';
	$posts = cf_get_items(
		'cf_news',
		$lang,
		array(
			'posts_per_page' => $count > 0 ? $count : -1,
			'orderby'        => array(
				'date' => 'DESC',
				'ID'   => 'DESC',
			),
		)
	);
	$out = '';
	foreach ( $posts as $n => $post ) {
		$short = ! empty( $o['short_titles'] ) ? (string) cf_meta( $post->ID, 'short_title' ) : '';
		$text  = ! empty( $o['short_titles'] ) ? (string) cf_meta( $post->ID, 'short_excerpt' ) : '';
		$out  .= sprintf(
			"<article class=\"story-card %1\$s\">\n<%2\$s>%3\$s</%2\$s>\n<p>%4\$s</p>\n<a class=\"card-link\" href=\"%5\$s\">%6\$s</a>\n</article>\n",
			esc_attr( cf_reveal_class( $n, ! empty( $o['delays'] ) ) ),
			$tag,
			esc_html( '' !== $short ? $short : get_the_title( $post ) ),
			esc_html( '' !== $text ? $text : wp_strip_all_tags( get_the_excerpt( $post ) ) ),
			esc_url( get_permalink( $post ) ),
			esc_html( (string) ( $f['link_label'] ?? '' ) )
		);
	}
	return $out;
}

/**
 * Date label of an event when no explicit label was entered.
 *
 * @param int    $id   Event.
 * @param string $lang Language.
 */
function cf_event_date_label( $id, $lang ) {
	$label = (string) cf_meta( $id, 'date_label' );
	if ( '' !== $label ) {
		return $label;
	}
	$start = (string) cf_meta( $id, 'start_date' );
	$end   = (string) cf_meta( $id, 'end_date' );
	if ( '' === $start ) {
		return '';
	}
	$fmt = 'es' === $lang ? 'j \d\e F, Y' : 'F j, Y';
	$out = date_i18n( $fmt, strtotime( $start ) );
	if ( '' !== $end && $end !== $start ) {
		$out .= ' - ' . date_i18n( $fmt, strtotime( $end ) );
	}
	return $out;
}

/**
 * Event cards.
 *
 * @param array  $o    Options.
 * @param array  $f    Labels.
 * @param string $lang Language.
 */
function cf_dyn_events( $o, $f, $lang ) {
	$out = '';
	foreach ( cf_get_items( 'cf_event', $lang ) as $post ) {
		$id    = $post->ID;
		$link  = (string) cf_meta( $id, 'link_url' );
		$label = (string) cf_meta( $id, 'link_label' );
		$out  .= "<div class=\"vol-card reveal\">\n";
		if ( has_post_thumbnail( $id ) ) {
			$out .= get_the_post_thumbnail( $id, 'large', array( 'class' => 'cf-event-image' ) ) . "\n";
		}
		$pill = cf_event_date_label( $id, $lang );
		if ( '' !== $pill ) {
			$out .= '<span class="vol-pill">' . esc_html( $pill ) . "</span>\n";
		}
		$out .= '<h2>' . esc_html( get_the_title( $post ) ) . "</h2>\n";
		$out .= '<p>' . esc_html( (string) cf_meta( $id, 'description' ) ) . "</p>\n";
		$extra = array_filter( array( (string) cf_meta( $id, 'time' ), (string) cf_meta( $id, 'location' ) ) );
		if ( $extra ) {
			$out .= '<p class="cf-event-meta">' . esc_html( implode( ' · ', $extra ) ) . "</p>\n";
		}
		if ( '' !== $link && '' !== $label ) {
			$out .= '<a class="card-link" href="' . esc_url( cf_url( $link ) ) . '">' . esc_html( $label ) . "</a>\n";
		}
		$out .= "</div>\n";
	}
	return $out;
}

/**
 * Library cards.
 *
 * @param array  $o    Options.
 * @param array  $f    Labels.
 * @param string $lang Language.
 */
function cf_dyn_resources( $o, $f, $lang ) {
	$featured = ! empty( $o['featured'] );
	$args     = array(
		'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			'relation' => 'AND',
			CF_Languages::meta_query( $lang ),
			$featured
				? array(
					'key'   => '_cf_featured',
					'value' => '1',
				)
				: array(
					'relation' => 'OR',
					array(
						'key'     => '_cf_featured',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_cf_featured',
						'value'   => '1',
						'compare' => '!=',
					),
				),
		),
	);
	$out = '';
	foreach ( cf_get_items( 'cf_resource', $lang, $args ) as $post ) {
		$id    = $post->ID;
		$terms = get_the_terms( $id, 'cf_resource_category' );
		$cat   = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
		$thumb = has_post_thumbnail( $id ) ? wp_get_attachment_url( get_post_thumbnail_id( $id ) ) : get_template_directory_uri() . '/assets/img/brand/resource-placeholder.svg';
		$alt   = (string) cf_meta( $id, 'thumb_alt' );
		$alt   = '' !== $alt ? $alt : get_the_title( $post );
		$date  = (string) cf_meta( $id, 'date_label' );
		if ( '' === $date && cf_meta( $id, 'pub_date' ) ) {
			$date = date_i18n( 'F Y', strtotime( (string) cf_meta( $id, 'pub_date' ) ) );
		}
		$file = cf_url( (string) cf_meta( $id, 'file' ) );
		$out .= "<article class=\"resource-card reveal\">\n<div class=\"resource-thumbnail\">\n";
		$out .= '<img alt="' . esc_attr( $alt ) . '" src="' . esc_url( (string) $thumb ) . "\"/>\n</div>\n<div class=\"resource-card-body\">\n";
		$out .= '<h3>' . esc_html( get_the_title( $post ) ) . "</h3>\n";
		$out .= '<p>' . esc_html( (string) cf_meta( $id, 'description' ) ) . "</p>\n<div class=\"resource-meta\">\n";
		$out .= '<span class="resource-category">' . esc_html( $cat ) . "</span>\n";
		$out .= '<span class="resource-file-info">' . esc_html( (string) cf_meta( $id, 'file_info' ) ) . "</span>\n";
		$out .= '<span class="resource-date">' . esc_html( trim( ( $f['published_label'] ?? '' ) . ' ' . $date ) ) . "</span>\n</div>\n";
		if ( '' !== $file ) {
			$out .= '<a class="card-link" download="" href="' . esc_url( $file ) . '">' . esc_html( (string) ( $f['download_label'] ?? '' ) ) . "</a>\n";
		}
		$out .= "</div>\n</article>\n";
	}
	return $out;
}

/**
 * One partner logo link.
 *
 * @param WP_Post $p   Partner.
 * @param string  $cls Class.
 */
function cf_partner_link( $p, $cls ) {
	$url = (string) cf_meta( $p->ID, 'url' );
	$alt = (string) cf_meta( $p->ID, 'logo_alt' );
	$alt = '' !== $alt ? $alt : get_the_title( $p );
	$img = has_post_thumbnail( $p->ID ) ? (string) wp_get_attachment_url( get_post_thumbnail_id( $p->ID ) ) : '';
	$a   = '' !== $url ? ' href="' . esc_url( $url ) . '" rel="noopener" target="_blank"' : '';
	return '<a class="' . esc_attr( $cls ) . '"' . $a . '><img alt="' . esc_attr( $alt ) . '" src="' . esc_url( $img ) . '"/></a>';
}

/**
 * Partner logos (moving strip or grid).
 *
 * @param array $o Options.
 * @param array $f Labels.
 */
function cf_dyn_partners( $o, $f ) {
	$ids      = ( empty( $f['all'] ) && ! empty( $o['ids'] ) ) ? array_map( 'intval', (array) $o['ids'] ) : array();
	$partners = cf_get_partners( $ids );
	$marquee  = 'marquee' === ( $o['style'] ?? '' );
	$cls      = $marquee ? 'logo-slide' : 'logo-grid-item reveal';
	$once     = '';
	foreach ( $partners as $p ) {
		$once .= cf_partner_link( $p, $cls );
	}
	$repeat = $marquee ? max( 1, (int) ( $o['repeat'] ?? 1 ) ) : 1;
	return str_repeat( $once, $repeat );
}

/**
 * Members of a sponsor category.
 *
 * @param array $o Options.
 * @param array $f Labels.
 */
function cf_dyn_sponsor_members( $o, $f ) {
	$key = sanitize_key( (string) ( $o['key'] ?? '' ) );
	$out = '';
	if ( '' !== $key ) {
		$members = get_posts(
			array(
				'post_type'      => 'cf_partner',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => array(
					'menu_order' => 'ASC',
					'title'      => 'ASC',
				),
				'meta_key'       => '_cf_sponsor_category', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $key, // phpcs:ignore WordPress.DB.SlowDBQuery
				'no_found_rows'  => true,
			)
		);
		foreach ( $members as $m ) {
			$url   = (string) cf_meta( $m->ID, 'url' );
			$name  = esc_html( get_the_title( $m ) );
			$out  .= '<li class="sponsor-category__member">' . ( '' !== $url ? '<a href="' . esc_url( $url ) . '" rel="noopener" target="_blank">' . $name . '</a>' : $name ) . "</li>\n";
		}
	}
	if ( '' === $out ) {
		$out = '<li class="sponsor-category__pending">' . esc_html( (string) ( $f['pending'] ?? '' ) ) . '</li>';
	}
	return $out;
}

/**
 * Team groups, cards and the data used by the profile window (team.js).
 *
 * @param array  $o    Options.
 * @param array  $f    Labels.
 * @param string $lang Language.
 */
function cf_dyn_team( $o, $f, $lang ) {
	$placeholder = get_template_directory_uri() . '/assets/img/brand/team-placeholder-generic.svg';
	$data        = array();
	$out         = '';
	foreach ( CF_Post_Types::terms( 'cf_team_group', $lang ) as $term ) {
		$members = cf_get_items(
			'cf_team',
			$lang,
			array(
				'tax_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'taxonomy' => 'cf_team_group',
						'terms'    => $term->term_id,
					),
				),
			)
		);
		if ( ! $members ) {
			continue;
		}
		$out .= "<div class=\"team-category\">\n<h3 class=\"team-category__title\">" . esc_html( $term->name ) . "</h3>\n<div class=\"team-grid\">\n";
		foreach ( $members as $m ) {
			$id    = $m->ID;
			$name  = get_the_title( $m );
			$mid   = (string) get_post_meta( $id, '_cf_member_id', true );
			$mid   = '' !== $mid ? $mid : $m->post_name;
			$photo = has_post_thumbnail( $id ) ? (string) wp_get_attachment_url( get_post_thumbnail_id( $id ) ) : $placeholder;
			$alt   = (string) cf_meta( $id, 'photo_alt' );
			$alt   = '' !== $alt ? $alt : $name;
			$note  = (string) cf_meta( $id, 'name_note' );
			$role  = (string) cf_meta( $id, 'role' );
			$aria  = str_replace( '%s', $name, (string) ( $f['aria'] ?? '%s' ) );
			$err   = cf_meta( $id, 'photo_fallback' ) ? ' onerror="' . esc_attr( "this.onerror=null;this.src='" . esc_url( $placeholder ) . "';" ) . '"' : '';

			$out .= '<article aria-haspopup="dialog" aria-label="' . esc_attr( $aria ) . '" class="team-card" data-member-id="' . esc_attr( $mid ) . "\" role=\"button\" tabindex=\"0\">\n";
			$out .= '<div class="team-card__media"><img alt="' . esc_attr( $alt ) . '" loading="lazy"' . $err . ' src="' . esc_url( $photo ) . "\"/></div>\n";
			$out .= '<div class="team-card__body"><span class="team-card__role">' . esc_html( $role ) . '</span><h4 class="team-card__name">' . esc_html( $name );
			if ( '' !== $note ) {
				$out .= ' <span style="font-size: 0.85rem; color: var(--team-muted);">' . esc_html( $note ) . '</span>';
			}
			$out .= '</h4><p class="team-card__summary">' . esc_html( (string) cf_meta( $id, 'summary' ) ) . '</p><span aria-hidden="true" class="team-card__cta">' . esc_html( (string) ( $f['cta'] ?? '' ) ) . "</span></div>\n</article>\n";

			$modal_img = (int) get_post_meta( $id, '_cf_modal_image', true );
			$modal_url = $modal_img ? (string) wp_get_attachment_url( $modal_img ) : $photo;
			$modal_nm  = (string) cf_meta( $id, 'modal_name' );
			$modal_rl  = (string) cf_meta( $id, 'modal_role' );
			$data[ $mid ] = array(
				'name'  => '' !== $modal_nm ? $modal_nm : $name,
				'role'  => '' !== $modal_rl ? $modal_rl : $role,
				'image' => $modal_url,
				'bio'   => (string) cf_meta( $id, 'bio' ),
			);
		}
		$out .= "</div>\n</div>\n";
	}
	if ( wp_script_is( 'cf-team', 'enqueued' ) ) {
		wp_add_inline_script( 'cf-team', 'const TEAM_MEMBERS = ' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . ';' . "\nwindow.CF_TEAM_PLACEHOLDER = " . wp_json_encode( $placeholder, JSON_UNESCAPED_SLASHES ) . ';', 'before' );
	}
	return $out;
}
