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
<?php
while ( have_posts() ) {
    the_post();
    the_content();
}
wp_footer();
?>
</body>
</html>
