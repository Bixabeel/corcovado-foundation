<?php
/**
 * Footer: same markup as the static site (two variants exist in the original pages, see
 * docs/VISUAL-DIFFERENCES.md; the page option "Footer style" selects one).
 *
 * @package CorcovadoFoundation
 */

defined( 'ABSPATH' ) || exit;
$cf_lang     = cf_current_lang();
$cf_settings = cf_view_settings();
$cf_icons    = 'icons' === $cf_settings['footer_style'];
$cf_logo     = cf_logo_url( $cf_settings['footer_logo'], 'footer' );
?>
</main>
<footer class="site-footer">
<?php if ( $cf_icons ) : ?>
<div class="container shell">
<div class="footer-grid">
<?php else : ?>
<div class="container shell footer-grid">
<?php endif; ?>
<div class="footer-brand">
<img alt="<?php echo esc_attr( cf_mod( 'footer_alt' ) ); ?>" src="<?php echo esc_url( $cf_logo ); ?>"/>
<p class="footer-text"><?php echo esc_html( cf_mod( 'footer_text' ) ); ?></p>
<div class="footer-social">
<?php cf_social_links( $cf_icons ); ?>
</div>
</div>
<?php for ( $cf_col = 1; $cf_col <= 3; $cf_col++ ) : ?>
<div<?php echo $cf_icons ? '' : ' class="footer-col"'; ?>>
<h3><?php echo esc_html( cf_mod( 'footer_col_' . $cf_col ) ); ?></h3>
<?php cf_menu_links( 'footer_' . $cf_col . '_' . $cf_lang, 'footer-link' ); ?>
</div>
<?php endfor; ?>
</div>
<?php if ( $cf_icons ) : ?>
<div class="footer-bottom">
<span><?php echo esc_html( cf_mod( 'copyright' ) ); ?></span><a class="footer-credit" href="<?php echo esc_url( cf_mod( 'credit_url' ) ); ?>" rel="noopener" target="_blank"><?php echo esc_html( cf_mod( 'credit_label' ) ); ?></a>
<a class="footer-top-link" href="#siteHeader"><?php echo esc_html( cf_mod( 'top_label' ) ); ?></a>
</div>
</div>
<?php else : ?>
<div class="container shell footer-bottom">
<p><?php echo esc_html( cf_mod( 'copyright' ) ); ?></p><a class="footer-credit" href="<?php echo esc_url( cf_mod( 'credit_url' ) ); ?>" rel="noopener" target="_blank"><?php echo esc_html( cf_mod( 'credit_label' ) ); ?></a>
<a class="footer-top-link" href="#siteHeader"><?php echo esc_html( cf_mod( 'top_label' ) ); ?></a>
</div>
<?php endif; ?>
</footer>
<?php wp_footer(); ?>
</body>
</html>
