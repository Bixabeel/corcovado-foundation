<?php
/**
 * A news story. The static site had no story pages, so this template only combines existing
 * components (breadcrumb, section, heading, card link) of the approved design.
 *
 * @package CorcovadoFoundation
 */

defined( 'ABSPATH' ) || exit;

get_header();
while ( have_posts() ) :
	the_post();
	$cf_lang = cf_current_lang();
	?>
<div class="container shell">
<nav aria-label="<?php esc_attr_e( 'Breadcrumb', 'corcovado-foundation' ); ?>" class="breadcrumb">
<a href="<?php echo esc_url( cf_lang_home() ); ?>"><?php esc_html_e( 'Home', 'corcovado-foundation' ); ?></a> <span class="sep">/</span> <a href="<?php echo esc_url( cf_news_page_url( $cf_lang ) ); ?>"><?php esc_html_e( 'News', 'corcovado-foundation' ); ?></a> <span class="sep">/</span> <?php the_title(); ?>
</nav>
</div>
<section class="section-pad section-white">
<div class="container shell">
<article <?php post_class( 'cf-news-article' ); ?>>
<div class="section-heading">
	<?php if ( ! get_post_meta( get_the_ID(), '_cf_hide_date', true ) ) : ?>
<span class="eyebrow"><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time></span>
	<?php endif; ?>
<h1><?php the_title(); ?></h1>
</div>
	<?php if ( has_post_thumbnail() ) : ?>
<figure class="cf-news-image"><?php the_post_thumbnail( 'large' ); ?></figure>
	<?php endif; ?>
<div class="cf-entry-content">
	<?php the_content(); ?>
</div>
<p><a class="card-link" href="<?php echo esc_url( cf_news_page_url( $cf_lang ) ); ?>"><?php esc_html_e( 'Back to news', 'corcovado-foundation' ); ?></a></p>
</article>
</div>
</section>
	<?php
endwhile;
get_footer();
