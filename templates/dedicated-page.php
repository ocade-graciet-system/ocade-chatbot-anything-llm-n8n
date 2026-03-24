<?php
/**
 * Minimal template for dedicated chatbot page
 *
 * Renders only the chatbot without theme header/footer/sidebar.
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            height: 100%;
            overflow: hidden;
        }
        .ofac-dedicated-topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 999999;
            height: 36px;
            background: #1e293b;
            display: flex;
            align-items: center;
            padding: 0 16px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .ofac-dedicated-topbar__link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #cbd5e1;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.2s;
        }
        .ofac-dedicated-topbar__link:hover {
            color: #fff;
        }
        .ofac-dedicated-topbar__link svg {
            width: 14px;
            height: 14px;
            fill: currentColor;
        }
        .ofac-dedicated-body .ofac-shortcode-wrapper {
            width: 100vw;
            height: calc(100vh - 36px);
            margin-top: 36px;
        }
        .ofac-dedicated-body:has(.ofac-access-denied) {
            overflow: auto;
        }
        .ofac-dedicated-body .ofac-access-denied {
            min-height: calc(100vh - 36px);
            margin-top: 36px;
        }
        .ofac-dedicated-body .ofac-inline .ofac-modal,
        .ofac-dedicated-body .ofac-inline #ofac-modal,
        .ofac-dedicated-body .ofac-modal,
        .ofac-dedicated-body .ofac-modal--fullscreen {
            position: fixed !important;
            top: 36px !important;
            left: 0 !important;
            right: 0 !important;
            bottom: 0 !important;
            width: 100% !important;
            height: calc(100% - 36px) !important;
            max-width: 100% !important;
            max-height: calc(100% - 36px) !important;
            border-radius: 0 !important;
        }
    </style>
</head>
<body class="ofac-dedicated-body">
<div class="ofac-dedicated-topbar">
    <a href="<?php echo esc_url( home_url() ); ?>" class="ofac-dedicated-topbar__link">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
        <?php esc_html_e( 'Retour au site', 'anythingllm-chatbot' ); ?>
    </a>
</div>
<?php
while ( have_posts() ) {
    the_post();
    the_content();
}
wp_footer();
?>
</body>
</html>
