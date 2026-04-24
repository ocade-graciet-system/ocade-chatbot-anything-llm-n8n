<?php
/**
 * Bubble popup partial — message above the floating trigger.
 *
 * Included by both templates/chatbot.php (floating mode) and
 * templates/redirect-bubble.php (dedicated-mode shortcut).
 *
 * Variables expected: $position, $popup_text.
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="ofac-bubble-popup"
     class="ofac-bubble-popup ofac-bubble-popup--<?php echo esc_attr( $position ); ?>"
     role="status"
     aria-live="polite"
     hidden>
    <span class="ofac-bubble-popup__text"><?php echo esc_html( $popup_text ); ?></span>
    <button type="button"
            class="ofac-bubble-popup__close"
            aria-label="<?php esc_attr_e( 'Fermer le message', 'anythingllm-chatbot' ); ?>">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true">
            <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
        </svg>
    </button>
</div>
