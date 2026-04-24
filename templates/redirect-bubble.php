<?php
/**
 * Redirect bubble template (dedicated mode shortcut).
 *
 * Renders a floating bubble that redirects to the dedicated chatbot page
 * when `ofac_display_mode=dedicated` and `ofac_dedicated_show_bubble=true`.
 *
 * Variables: $dedicated_url, $position, $style, $popup_enabled, $popup_text.
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="ofac-redirect-bubble"
     class="ofac-chatbot ofac-chatbot--redirect ofac-position-<?php echo esc_attr( $position ); ?>"
     style="<?php echo esc_attr( $style ); ?>">

    <?php if ( $popup_enabled ) : ?>
        <?php include OFAC_PLUGIN_DIR . 'templates/parts/bubble-popup.php'; ?>
    <?php endif; ?>

    <a id="ofac-trigger"
       href="<?php echo esc_url( $dedicated_url ); ?>"
       class="ofac-trigger ofac-trigger--<?php echo esc_attr( $position ); ?>"
       aria-label="<?php esc_attr_e( 'Ouvrir le chatbot', 'anythingllm-chatbot' ); ?>">
        <span class="ofac-trigger__icon ofac-trigger__icon--chat" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor">
                <path d="M12 3c5.5 0 10 3.58 10 8s-4.5 8-10 8c-1.24 0-2.43-.18-3.53-.5C5.55 21 2 21 2 21c2.33-2.33 2.7-3.9 2.75-4.5C3.05 15.07 2 13.13 2 11c0-4.42 4.5-8 10-8z"/>
            </svg>
        </span>
    </a>
</div>
