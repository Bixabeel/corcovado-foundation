<?php
/**
 * 404: shows the sections of the (private) "Page not found" page of the current language.
 *
 * @package CorcovadoFoundation
 */

defined( 'ABSPATH' ) || exit;

get_header();
$cf_404 = cf_core_active() ? (int) cf_setting( 'page_404_' . cf_current_lang(), 0 ) : 0;
if ( $cf_404 && cf_get_layout( $cf_404 ) ) {
	echo CF_Layout::render( $cf_404 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by CF_Layout.
} else {
	?>
<section class="section-pad section-white">
<div class="container shell">
<div class="section-heading">
<h1><?php esc_html_e( 'Page not found', 'corcovado-foundation' ); ?></h1>
<p><a class="btn btn-primary" href="<?php echo esc_url( cf_lang_home() ); ?>"><?php esc_html_e( 'Back to home', 'corcovado-foundation' ); ?></a></p>
</div>
</div>
</section>
	<?php
}
get_footer();
