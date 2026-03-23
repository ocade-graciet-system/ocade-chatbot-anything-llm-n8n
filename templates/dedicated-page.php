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
        .ofac-dedicated-body .ofac-shortcode-wrapper {
            width: 100vw;
            height: 100vh;
        }
        .ofac-dedicated-body:has(.ofac-access-denied) {
            overflow: auto;
        }
        .ofac-dedicated-home-link {
            position: fixed;
            top: 12px;
            left: 12px;
            z-index: 999999;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(8px);
            color: #fff;
            text-decoration: none;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 13px;
            font-weight: 500;
            border-radius: 20px;
            transition: background 0.2s;
        }
        .ofac-dedicated-home-link:hover {
            background: rgba(0, 0, 0, 0.7);
            color: #fff;
        }
        .ofac-dedicated-home-link svg {
            width: 14px;
            height: 14px;
            fill: currentColor;
        }
        .ofac-dedicated-body .ofac-inline .ofac-modal,
        .ofac-dedicated-body .ofac-inline #ofac-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            width: 100%;
            height: 100%;
            max-width: 100%;
            max-height: 100%;
            border-radius: 0;
        }
    </style>
</head>
<body class="ofac-dedicated-body">
<a href="<?php echo esc_url( home_url() ); ?>" class="ofac-dedicated-home-link">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M10 20v-6h4v6h5v-8h3L12 3 2 12h3v8z"/></svg>
    <?php esc_html_e( 'Accueil', 'anythingllm-chatbot' ); ?>
</a>
<?php
while ( have_posts() ) {
    the_post();
    the_content();
}
wp_footer();
?>
</body>
</html>
