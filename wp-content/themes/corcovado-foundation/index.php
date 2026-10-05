<?php
/**
 * Fallback template (search results, archives).
 *
 * @package CorcovadoFoundation
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="section-pad section-white">
<div class="container shell">
<div class="section-heading">
<h1><?php echo esc_html( is_search() ? sprintf( /* translators: %s: search terms */ __( 'Search results for "%s"', 'corcovado-foundation' ), get_search_query() ) : wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
</div>
<?php if ( have_posts() ) : ?>
<div class="story-grid">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
<article class="story-card">
<h2><?php the_title(); ?></h2>
<p><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
<a class="card-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read more', 'corcovado-foundation' ); ?></a>
</article>
	<?php endwhile; ?>
</div>
	<?php the_posts_pagination(); ?>
<?php else : ?>
<p><?php esc_html_e( 'Nothing found.', 'corcovado-foundation' ); ?></p>
<?php endif; ?>
</div>
</section>
<?php
get_footer();
