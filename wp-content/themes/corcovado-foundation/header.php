<?php
/**
 * Header: same markup as the static site.
 *
 * @package CorcovadoFoundation
 */

defined( 'ABSPATH' ) || exit;
$cf_lang     = cf_current_lang();
$cf_settings = cf_view_settings();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main"><?php echo esc_html( cf_mod( 'skip_label' ) ); ?></a>
<header class="site-header" id="siteHeader"><div class="container shell header-shell"><a class="brand" href="<?php echo esc_url( cf_lang_home() ); ?>"><img alt="<?php echo esc_attr( cf_mod( 'brand_alt' ) ); ?>" src="<?php echo esc_url( cf_logo_url( $cf_settings['header_logo'], 'header' ) ); ?>"/></a><button aria-controls="siteNav" aria-expanded="false" aria-label="<?php echo esc_attr( cf_mod( 'menu_open_label' ) ); ?>" class="nav-toggle"><span></span><span></span><span></span></button><nav aria-label="<?php echo esc_attr( cf_mod( 'nav_label' ) ); ?>" class="site-nav" id="siteNav"><div class="nav-top"><div aria-label="<?php echo esc_attr( cf_mod( 'lang_label' ) ); ?>" class="lang-switch" role="group"><?php cf_lang_switch(); ?></div><a class="btn btn-primary donate-btn" href="<?php echo esc_url( cf_path_url( cf_mod( 'donate_url' ) ) ); ?>"><?php echo esc_html( cf_mod( 'donate_label' ) ); ?></a></div><?php cf_menu_links( 'primary_' . $cf_lang, 'nav-link', true ); ?></nav></div></header>
<main id="main">
