<?php
/**
 * Pages. Pages migrated from the static site render their locked sections; new pages created
 * in WordPress render their editor content inside the site's standard section.
 *
 * @package CorcovadoFoundation
 */

defined( 'ABSPATH' ) || exit;

get_header();
while ( have_posts() ) :
	the_post();
	if ( cf_core_active() && cf_get_layout( get_the_ID() ) ) {
		echo CF_Layout::render( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every slot is escaped by CF_Layout.
	} else {
		get_template_part( 'template-parts/content', 'page' );
	}
endwhile;
get_footer();
