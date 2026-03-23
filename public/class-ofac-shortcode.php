<?php
/**
 * Shortcode functionality
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Shortcode
 */
class OFAC_Shortcode {

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode( 'ofac_chatbot', array( $this, 'render' ) );
    }

    /**
     * Render shortcode
     *
     * @param array  $atts    Shortcode attributes.
     * @param string $content Shortcode content.
     * @return string
     */
    public function render( $atts = array(), $content = '' ) {
        $settings = OFAC_Settings::get_instance();

        // Check plugin is enabled and API configured (without access control)
        if ( ! $settings->get( 'ofac_enabled', false ) ) {
            return '';
        }
        $api = new OFAC_API();
        if ( ! $api->is_configured() ) {
            return '';
        }

        // Access control with user-facing messages
        $require_login = $settings->get( 'ofac_require_login', false );
        if ( $require_login && ! is_user_logged_in() ) {
            return $this->render_access_denied( true );
        }

        $allowed_roles = $settings->get( 'ofac_allowed_roles', array() );
        if ( ! empty( $allowed_roles ) ) {
            if ( ! is_user_logged_in() ) {
                return $this->render_access_denied( true );
            }
            $user  = wp_get_current_user();
            $match = array_intersect( $user->roles, $allowed_roles );
            if ( empty( $match ) ) {
                return $this->render_access_denied( false );
            }
        }

        // Parse attributes
        $atts = shortcode_atts( array(
            'position'   => '',
            'width'      => '',
            'height'     => '',
            'class'      => '',
            'fullscreen' => 'false',
        ), $atts, 'ofac_chatbot' );

        // Enqueue assets
        $this->enqueue_assets();

        // Build wrapper attributes
        $wrapper_class = 'ofac-shortcode-wrapper';
        if ( ! empty( $atts['class'] ) ) {
            $wrapper_class .= ' ' . sanitize_html_class( $atts['class'] );
        }
        if ( 'true' === $atts['fullscreen'] ) {
            $wrapper_class .= ' ofac-shortcode--fullscreen';
        }

        $wrapper_style = '';
        if ( ! empty( $atts['width'] ) ) {
            $wrapper_style .= 'width: ' . esc_attr( $atts['width'] ) . ';';
        }
        if ( ! empty( $atts['height'] ) ) {
            $wrapper_style .= 'height: ' . esc_attr( $atts['height'] ) . ';';
        }

        // Start output buffering
        ob_start();

        echo '<div class="' . esc_attr( $wrapper_class ) . '"';
        if ( $wrapper_style ) {
            echo ' style="' . esc_attr( $wrapper_style ) . '"';
        }
        echo '>';

        // Render chatbot
        OFAC_Public::get_instance()->render_chatbot_html( true );

        echo '</div>';

        return ob_get_clean();
    }

    /**
     * Render access denied card
     *
     * @param bool $show_login Whether to show the login button.
     * @return string
     */
    private function render_access_denied( $show_login = true ) {
        wp_enqueue_style( 'ofac-chatbot' );

        $settings  = OFAC_Settings::get_instance();
        $bot_name  = esc_html( $settings->get( 'ofac_bot_name', 'Service Client' ) );
        $bot_avatar_id = $settings->get( 'ofac_bot_avatar', '' );

        // Avatar: image or SVG fallback
        $avatar_url = $bot_avatar_id ? wp_get_attachment_url( $bot_avatar_id ) : false;
        if ( $avatar_url ) {
            $avatar_html = '<img src="' . esc_url( $avatar_url ) . '" alt="' . esc_attr( $settings->get( 'ofac_bot_name', 'Service Client' ) ) . '">';
        } else {
            $avatar_html = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H6l-2 2V4h16v12z"/></svg>';
        }

        // Message
        if ( $show_login ) {
            $message = esc_html( $settings->get( 'ofac_login_page_message', 'Connectez-vous pour accéder à votre assistant.' ) );
        } else {
            $message = esc_html__( 'Vous n\'avez pas les droits pour accéder au chatbot.', 'anythingllm-chatbot' );
        }

        $html = '<div class="ofac-access-denied">';
        $html .= '<div class="ofac-access-denied__card">';
        $html .= '<div class="ofac-access-denied__avatar">' . $avatar_html . '</div>';
        $html .= '<h2 class="ofac-access-denied__title">' . $bot_name . '</h2>';
        $html .= '<p class="ofac-access-denied__message">' . $message . '</p>';

        if ( $show_login ) {
            $login_url = wp_login_url( get_permalink() );
            $html .= '<a href="' . esc_url( $login_url ) . '" class="ofac-access-denied__login-btn">' . esc_html__( 'Se connecter', 'anythingllm-chatbot' ) . '</a>';
        }

        $html .= '<a href="' . esc_url( home_url() ) . '" class="ofac-access-denied__home-link">' . esc_html__( '← Retour à l\'accueil', 'anythingllm-chatbot' ) . '</a>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Enqueue assets for shortcode
     */
    private function enqueue_assets() {
        $settings = OFAC_Settings::get_instance();

        // Always load assets when shortcode is used
        wp_enqueue_style( 'ofac-chatbot' );
        wp_enqueue_style( 'ofac-prism' );
        wp_enqueue_script( 'ofac-marked' );
        wp_enqueue_script( 'ofac-prism' );
        wp_enqueue_script( 'ofac-chatbot' );

        // Localize config if not already done (needed in dedicated mode)
        if ( ! wp_script_is( 'ofac-chatbot', 'done' ) ) {
            wp_localize_script(
                'ofac-chatbot',
                'ofacConfig',
                OFAC_Public::get_instance()->get_frontend_config()
            );
        }
    }
}
