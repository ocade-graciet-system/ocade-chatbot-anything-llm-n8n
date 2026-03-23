<?php
/**
 * Chatbot template
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Variables disponibles :
// $container_class, $style, $has_consent, $bot_name, $welcome_message, $placeholder
// $bot_avatar_url, $user_avatar_url, $primary_color, $inline
?>
<div id="ofac-chatbot" 
     class="<?php echo esc_attr( $container_class ); ?>" 
     style="<?php echo esc_attr( $style ); ?>"
     data-consent="<?php echo $has_consent ? 'true' : 'false'; ?>"
     data-inline="<?php echo $inline ? 'true' : 'false'; ?>">

    <!-- Toggle Button (only for floating mode) -->
    <?php if ( ! $inline ) : ?>
    <button type="button" 
            id="ofac-trigger"
            class="ofac-trigger ofac-trigger--<?php echo esc_attr( $position ); ?>"
            aria-label="<?php echo esc_attr( $accessibility->get_label( 'open_chat' ) ); ?>"
            aria-expanded="false"
            aria-controls="ofac-modal">
        <span class="ofac-trigger__icon ofac-trigger__icon--chat" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor">
                <path d="M12 3c5.5 0 10 3.58 10 8s-4.5 8-10 8c-1.24 0-2.43-.18-3.53-.5C5.55 21 2 21 2 21c2.33-2.33 2.7-3.9 2.75-4.5C3.05 15.07 2 13.13 2 11c0-4.42 4.5-8 10-8z"/>
            </svg>
        </span>
        <span class="ofac-trigger__icon ofac-trigger__icon--close" aria-hidden="true" style="display:none;">
            <svg viewBox="0 0 24 24" width="28" height="28" fill="currentColor">
                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
            </svg>
        </span>
        <span class="ofac-trigger__badge" aria-hidden="true" style="display:none;">0</span>
    </button>
    <?php endif; ?>

    <!-- Chat Window -->
    <div id="ofac-modal" 
         class="ofac-chat-window ofac-modal <?php echo $inline ? 'ofac-visible ofac-modal--open' : ''; ?> <?php echo ! $has_consent ? 'ofac-consent-active' : ''; ?> ofac-modal--<?php echo esc_attr( $position ); ?>"
         role="dialog"
         aria-modal="true"
         aria-labelledby="ofac-chat-title"
         aria-describedby="ofac-chat-description"
         <?php echo ! $inline ? 'aria-hidden="true"' : ''; ?>>

        <!-- Header -->
        <header class="ofac-chat-header">
            <div class="ofac-header-info">
                <?php if ( $bot_avatar_url ) : ?>
                <img src="<?php echo esc_url( $bot_avatar_url ); ?>" 
                     alt="" 
                     class="ofac-header-avatar"
                     aria-hidden="true"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="ofac-header-avatar ofac-avatar-default" aria-hidden="true" style="display:none;">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                    </svg>
                </div>
                <?php else : ?>
                <div class="ofac-header-avatar ofac-avatar-default" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                    </svg>
                </div>
                <?php endif; ?>
                <div class="ofac-header-text">
                    <h2 id="ofac-chat-title" class="ofac-header-title">
                        <?php echo esc_html( $bot_name ); ?>
                    </h2>
                    <span id="ofac-status" class="ofac-header-status" aria-live="polite">
                        <?php esc_html_e( 'En ligne', 'anythingllm-chatbot' ); ?>
                    </span>
                </div>
            </div>
            <div class="ofac-header-actions">
                <?php if ( $enable_callback_btn ) : ?>
                <button type="button"
                        id="ofac-callback-btn"
                        class="ofac-header-btn ofac-btn-callback"
                        aria-label="<?php echo esc_attr( $callback_btn_label ); ?>"
                        title="<?php echo esc_attr( $callback_btn_label ); ?>">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                        <path d="M20 15.5c-1.25 0-2.45-.2-3.57-.57a1.02 1.02 0 00-1.02.24l-2.2 2.2a15.045 15.045 0 01-6.59-6.59l2.2-2.21a.96.96 0 00.25-1A11.36 11.36 0 018.5 4c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1 0 9.39 7.61 17 17 17 .55 0 1-.45 1-1v-3.5c0-.55-.45-1-1-1zM19 12h2a9 9 0 00-9-9v2c3.87 0 7 3.13 7 7zm-4 0h2c0-2.76-2.24-5-5-5v2c1.66 0 3 1.34 3 3z"/>
                    </svg>
                </button>
                <?php endif; ?>
                <?php if ( $enable_contact_btn ) : ?>
                <button type="button"
                        id="ofac-contact-support"
                        class="ofac-header-btn ofac-btn-contact"
                        aria-label="<?php echo esc_attr( $contact_btn_label ); ?>"
                        title="<?php echo esc_attr( $contact_btn_label ); ?>">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                        <path d="M11.5 2C6.81 2 3 5.81 3 10.5S6.81 19 11.5 19h.5v3c4.86-2.34 8-7 8-11.5C20 5.81 16.19 2 11.5 2zm1 14.5h-2v-2h2v2zm0-3.5h-2c0-3.25 3-3 3-5 0-1.1-.9-2-2-2s-2 .9-2 2h-2c0-2.21 1.79-4 4-4s4 1.79 4 4c0 2.5-3 2.75-3 5z"/>
                    </svg>
                </button>
                <?php endif; ?>
                <button type="button"
                        id="ofac-reset"
                        class="ofac-header-btn ofac-btn-reset"
                        aria-label="<?php echo esc_attr( $accessibility->get_label( 'reset_chat' ) ); ?>"
                        title="<?php esc_attr_e( 'Réinitialiser', 'anythingllm-chatbot' ); ?>">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                        <path d="M17.65 6.35A7.958 7.958 0 0012 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0112 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>
                    </svg>
                </button>
                <button type="button"
                        id="ofac-fullscreen"
                        class="ofac-header-btn ofac-btn-fullscreen"
                        aria-label="<?php esc_attr_e( 'Plein écran', 'anythingllm-chatbot' ); ?>"
                        aria-pressed="false"
                        title="<?php esc_attr_e( 'Plein écran', 'anythingllm-chatbot' ); ?>">
                    <svg class="ofac-fullscreen-expand" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                        <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
                    </svg>
                    <svg class="ofac-fullscreen-compress" viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                        <path d="M5 16h3v3h2v-5H5v2zm3-8H5v2h5V5H8v3zm6 11h2v-3h3v-2h-5v5zm2-11V5h-2v5h5V8h-3z"/>
                    </svg>
                </button>
                <?php if ( ! $inline ) : ?>
                <button type="button" 
                        id="ofac-close"
                        class="ofac-header-btn ofac-btn-close"
                        aria-label="<?php echo esc_attr( $accessibility->get_label( 'close_chat' ) ); ?>">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
                <?php endif; ?>
            </div>
        </header>

        <!-- Hidden description for screen readers -->
        <p id="ofac-chat-description" class="ofac-sr-only">
            <?php esc_html_e( 'Fenêtre de conversation avec un assistant IA. Utilisez Tab pour naviguer, Entrée pour envoyer un message.', 'anythingllm-chatbot' ); ?>
        </p>

        <!-- Consent Screen (toujours rendu, caché par défaut si consentement déjà donné) -->
        <div id="ofac-consent-screen"
             class="ofac-consent-screen <?php echo $has_consent ? '' : 'ofac-consent--visible'; ?>"
             aria-hidden="<?php echo $has_consent ? 'true' : 'false'; ?>">
            <div class="ofac-consent-content">
                <h3 class="ofac-consent-title">
                    <?php esc_html_e( 'Consentement requis', 'anythingllm-chatbot' ); ?>
                </h3>
                <div class="ofac-consent-text">
                    <?php echo wp_kses_post( $consent->get_consent_text() ); ?>
                </div>
                <?php
                $privacy_url = $consent->get_privacy_policy_url();
                if ( $privacy_url ) :
                ?>
                <p class="ofac-consent-privacy">
                    <a href="<?php echo esc_url( $privacy_url ); ?>" target="_blank" rel="noopener">
                        <?php esc_html_e( 'Politique de confidentialité', 'anythingllm-chatbot' ); ?>
                        <span class="ofac-sr-only"><?php esc_html_e( '(s\'ouvre dans un nouvel onglet)', 'anythingllm-chatbot' ); ?></span>
                    </a>
                </p>
                <?php endif; ?>
                <div class="ofac-consent-actions">
                    <button type="button"
                            id="ofac-consent-accept"
                            class="ofac-consent-btn">
                        <?php esc_html_e( 'Accepter', 'anythingllm-chatbot' ); ?>
                    </button>
                    <button type="button"
                            id="ofac-consent-decline"
                            class="ofac-consent-btn">
                        <?php esc_html_e( 'Refuser', 'anythingllm-chatbot' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Contact Support Overlay -->
        <?php if ( $enable_contact_btn ) : ?>
        <div id="ofac-contact-overlay" class="ofac-overlay" aria-hidden="true" role="dialog" aria-labelledby="ofac-contact-title">
            <div class="ofac-overlay-content">
                <h3 id="ofac-contact-title" class="ofac-overlay-title">
                    <?php echo esc_html( $contact_btn_label ); ?>
                </h3>
                <div class="ofac-contact-info">
                    <?php if ( ! empty( $contact_email ) ) : ?>
                    <div class="ofac-contact-item">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
                            <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                        </svg>
                        <a href="mailto:<?php echo esc_attr( $contact_email ); ?>" class="ofac-contact-link">
                            <?php echo esc_html( $contact_email ); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if ( ! empty( $contact_phone ) ) : ?>
                    <div class="ofac-contact-item">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
                            <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                        </svg>
                        <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $contact_phone ) ); ?>" class="ofac-contact-link">
                            <?php echo esc_html( $contact_phone ); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <button type="button" id="ofac-contact-close" class="ofac-overlay-close-btn">
                    <?php esc_html_e( 'Fermer', 'anythingllm-chatbot' ); ?>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Callback Request Overlay -->
        <?php if ( $enable_callback_btn ) : ?>
        <div id="ofac-callback-overlay" class="ofac-overlay" aria-hidden="true" role="dialog" aria-labelledby="ofac-callback-title">
            <div class="ofac-overlay-content">
                <h3 id="ofac-callback-title" class="ofac-overlay-title">
                    <?php echo esc_html( $callback_btn_label ); ?>
                </h3>
                <form id="ofac-callback-form" class="ofac-callback-form">
                    <div class="ofac-form-group">
                        <label for="ofac-callback-phone" class="ofac-form-label">
                            <?php esc_html_e( 'Téléphone', 'anythingllm-chatbot' ); ?> <span aria-hidden="true">*</span>
                        </label>
                        <input type="tel"
                               id="ofac-callback-phone"
                               name="callback_phone"
                               class="ofac-form-input"
                               required
                               aria-required="true"
                               placeholder="06 12 34 56 78">
                    </div>
                    <div class="ofac-form-group">
                        <label for="ofac-callback-message" class="ofac-form-label">
                            <?php esc_html_e( 'Message', 'anythingllm-chatbot' ); ?> <span aria-hidden="true">*</span>
                        </label>
                        <textarea id="ofac-callback-message"
                                  name="callback_message"
                                  class="ofac-form-input ofac-form-textarea"
                                  rows="3"
                                  required
                                  aria-required="true"
                                  placeholder="<?php esc_attr_e( 'Décrivez votre demande ou question...', 'anythingllm-chatbot' ); ?>"></textarea>
                    </div>
                    <div class="ofac-form-actions">
                        <button type="submit" class="ofac-overlay-submit-btn" id="ofac-callback-submit">
                            <?php esc_html_e( 'Envoyer ma demande', 'anythingllm-chatbot' ); ?>
                        </button>
                        <button type="button" class="ofac-overlay-close-btn" id="ofac-callback-cancel">
                            <?php esc_html_e( 'Annuler', 'anythingllm-chatbot' ); ?>
                        </button>
                    </div>
                </form>
                <div id="ofac-callback-error" class="ofac-callback-error" style="display:none;" role="alert" aria-live="assertive"></div>
                <div id="ofac-callback-success" class="ofac-callback-success" style="display:none;">
                    <svg viewBox="0 0 24 24" width="48" height="48" fill="currentColor" aria-hidden="true">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <p><?php esc_html_e( 'Votre demande a été envoyée ! Nous vous recontacterons rapidement.', 'anythingllm-chatbot' ); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Messages Container -->
        <div id="ofac-messages"
             class="ofac-messages" 
             role="log" 
             aria-live="polite"
             aria-label="<?php echo esc_attr( $accessibility->get_label( 'messages' ) ); ?>"
             tabindex="0">
            
            <!-- Welcome Message -->
            <div class="ofac-message ofac-message--assistant ofac-welcome-message" data-message-id="welcome">
                <?php if ( $bot_avatar_url ) : ?>
                <img src="<?php echo esc_url( $bot_avatar_url ); ?>" 
                     alt="" 
                     class="ofac-message-avatar"
                     aria-hidden="true"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                <div class="ofac-message-avatar ofac-avatar-default" aria-hidden="true" style="display:none;">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                    </svg>
                </div>
                <?php else : ?>
                <div class="ofac-message-avatar ofac-avatar-default" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                    </svg>
                </div>
                <?php endif; ?>
                <div class="ofac-message-content">
                    <div class="ofac-message-bubble">
                        <?php echo wp_kses_post( $welcome_message ); ?>
                    </div>
                </div>
            </div>

            <!-- Messages will be appended here -->
        </div>

        <!-- Typing Indicator -->
        <div id="ofac-typing" class="ofac-typing" aria-hidden="true">
            <div class="ofac-message-avatar ofac-avatar-default" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
                </svg>
            </div>
            <div class="ofac-typing__bubble">
                <div class="ofac-typing__dots">
                    <span class="ofac-typing__dot"></span>
                    <span class="ofac-typing__dot"></span>
                    <span class="ofac-typing__dot"></span>
                </div>
            </div>
            <span class="ofac-sr-only"><?php echo esc_html( $accessibility->get_label( 'typing' ) ); ?></span>
        </div>

        <!-- Quick Replies -->
        <div id="ofac-quick-replies" class="ofac-quick-replies" aria-label="<?php esc_attr_e( 'Suggestions', 'anythingllm-chatbot' ); ?>">
            <!-- Quick reply buttons will be inserted here -->
        </div>

        <!-- Conversation Feedback Bar -->
        <div id="ofac-conversation-feedback" class="ofac-conversation-feedback" style="display:none;">
            <span class="ofac-feedback-label"><?php esc_html_e( 'Votre avis ?', 'anythingllm-chatbot' ); ?></span>
            <div class="ofac-feedback-buttons">
                <button type="button" class="ofac-feedback-btn ofac-feedback-up" data-rating="1"
                        aria-label="<?php esc_attr_e( 'Pouce en haut', 'anythingllm-chatbot' ); ?>">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
                        <path d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2z"/>
                    </svg>
                </button>
                <button type="button" class="ofac-feedback-btn ofac-feedback-down" data-rating="-1"
                        aria-label="<?php esc_attr_e( 'Pouce en bas', 'anythingllm-chatbot' ); ?>">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
                        <path d="M15 3H6c-.83 0-1.54.5-1.84 1.22l-3.02 7.05c-.09.23-.14.47-.14.73v2c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L9.83 23l6.59-6.59c.36-.36.58-.86.58-1.41V5c0-1.1-.9-2-2-2zm4 0v12h4V3h-4z"/>
                    </svg>
                </button>
            </div>
            <div class="ofac-feedback-thanks" style="display:none;">
                <?php esc_html_e( 'Merci pour votre avis !', 'anythingllm-chatbot' ); ?>
            </div>
            <div class="ofac-feedback-note-overlay" style="display:none;">
                <textarea class="ofac-feedback-note-input" rows="2"
                          placeholder="<?php esc_attr_e( 'Un commentaire ? (facultatif)', 'anythingllm-chatbot' ); ?>"
                          maxlength="500"></textarea>
                <div class="ofac-feedback-note-actions">
                    <button type="button" class="ofac-feedback-note-submit">
                        <?php esc_html_e( 'Envoyer', 'anythingllm-chatbot' ); ?>
                    </button>
                    <button type="button" class="ofac-feedback-note-skip">
                        <?php esc_html_e( 'Passer', 'anythingllm-chatbot' ); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Input Form -->
        <form id="ofac-input-form" class="ofac-input-form" aria-label="<?php echo esc_attr( $accessibility->get_label( 'compose' ) ); ?>">
            <!-- Honeypot field -->
            <input type="text" 
                   name="ofac_hp_field" 
                   id="ofac_hp_field" 
                   class="ofac-hp-field" 
                   tabindex="-1" 
                   autocomplete="off"
                   aria-hidden="true">

            <div class="ofac-input-wrapper">
                <?php 
                $settings_instance = OFAC_Settings::get_instance();
                if ( $settings_instance->get( 'ofac_enable_file_upload', false ) ) : 
                ?>
                <button type="button" 
                        id="ofac-file-btn"
                        class="ofac-input-btn ofac-upload-btn"
                        aria-label="<?php echo esc_attr( $accessibility->get_label( 'upload' ) ); ?>">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
                        <path d="M16.5 6v11.5c0 2.21-1.79 4-4 4s-4-1.79-4-4V5c0-1.38 1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5v10.5c0 .55-.45 1-1 1s-1-.45-1-1V6H10v9.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V5c0-2.21-1.79-4-4-4S7 2.79 7 5v12.5c0 3.04 2.46 5.5 5.5 5.5s5.5-2.46 5.5-5.5V6h-1.5z"/>
                    </svg>
                </button>
                <input type="file" 
                       id="ofac-file-input" 
                       class="ofac-file-input"
                       accept="<?php echo esc_attr( $settings_instance->get( 'ofac_allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,txt' ) ); ?>"
                       aria-hidden="true">
                <?php endif; ?>

                <textarea id="ofac-input"
                          name="message"
                          class="ofac-message-input"
                          placeholder="<?php echo esc_attr( $placeholder ); ?>"
                          aria-label="<?php echo esc_attr( $accessibility->get_label( 'message_input' ) ); ?>"
                          maxlength="<?php echo esc_attr( $settings_instance->get( 'ofac_max_message_length', 2000 ) ); ?>"
                          rows="1"
                          required></textarea>

                <button type="submit" 
                        id="ofac-send"
                        class="ofac-input-btn ofac-send-btn"
                        aria-label="<?php echo esc_attr( $accessibility->get_label( 'send_message' ) ); ?>">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" aria-hidden="true">
                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                    </svg>
                </button>
            </div>

            <!-- Character counter -->
            <div id="ofac-char-counter" class="ofac-char-counter" aria-live="polite" aria-atomic="true">
                <span id="ofac-char-current">0</span>/<span id="ofac-char-max"><?php echo esc_html( $settings_instance->get( 'ofac_max_message_length', 2000 ) ); ?></span>
            </div>
        </form>
    </div>
</div>

<!-- Message Template -->
<template id="ofac-message-template">
    <div class="ofac-message" data-message-id="">
        <div class="ofac-message-avatar"></div>
        <div class="ofac-message-content">
            <div class="ofac-message-bubble"></div>
            <div class="ofac-message-actions">
                <button type="button" class="ofac-action-btn ofac-copy-btn" aria-label="<?php echo esc_attr( $accessibility->get_label( 'copy' ) ); ?>">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
                        <path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/>
                    </svg>
                </button>
            </div>
            <time class="ofac-message-time"></time>
        </div>
    </div>
</template>

<!-- Quick Reply Template -->
<template id="ofac-quick-reply-template">
    <button type="button" class="ofac-quick-reply-btn"></button>
</template>

<!-- Source Template -->
<template id="ofac-source-template">
    <div class="ofac-sources">
        <button type="button" class="ofac-sources-toggle" aria-expanded="false">
            <span class="ofac-sources-label"><?php esc_html_e( 'Sources', 'anythingllm-chatbot' ); ?></span>
            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true">
                <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/>
            </svg>
        </button>
        <ul class="ofac-sources-list" hidden></ul>
    </div>
</template>
