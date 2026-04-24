<?php
/**
 * Public-facing functionality
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Public
 */
class OFAC_Public {

    /**
     * Instance
     *
     * @var OFAC_Public
     */
    private static $instance = null;

    /**
     * Settings
     *
     * @var OFAC_Settings
     */
    private $settings;

    /**
     * Shortcode instance
     *
     * @var OFAC_Shortcode
     */
    private $shortcode;

    /**
     * Block instance
     *
     * @var OFAC_Block
     */
    private $block;

    /**
     * Get instance
     *
     * @return OFAC_Public
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->settings = OFAC_Settings::get_instance();
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load dependencies
     */
    private function load_dependencies() {
        require_once OFAC_PLUGIN_DIR . 'public/class-ofac-shortcode.php';
        require_once OFAC_PLUGIN_DIR . 'public/class-ofac-block.php';

        $this->shortcode = new OFAC_Shortcode();
        $this->block = new OFAC_Block();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_chatbot' ) );
        add_action( 'wp_body_open', array( $this, 'add_skip_link' ) );

        // Dedicated page: ensure page exists + noindex + hide admin bar + custom template
        if ( 'dedicated' === $this->settings->get( 'ofac_display_mode', 'floating' ) ) {
            add_action( 'admin_init', array( $this, 'ensure_dedicated_page' ) );
            add_action( 'wp_head', array( $this, 'maybe_noindex_dedicated_page' ) );
            add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar_on_dedicated_page' ) );
            add_filter( 'template_include', array( $this, 'dedicated_page_template' ) );
        }
    }

    /**
     * Ensure dedicated chatbot page exists
     */
    public function ensure_dedicated_page() {
        $page_id = (int) $this->settings->get( 'ofac_dedicated_page_id', 0 );

        // Check if page still exists and is not trashed
        if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
            return;
        }

        $bot_name  = $this->settings->get( 'ofac_bot_name', 'Chatbot' );
        $page_data = array(
            'post_title'   => $bot_name . ' - Chatbot',
            'post_content' => '[ofac_chatbot fullscreen="true"]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_name'    => 'chatbot',
        );

        $new_page_id = wp_insert_post( $page_data );

        if ( ! is_wp_error( $new_page_id ) ) {
            update_option( 'ofac_dedicated_page_id', $new_page_id );

            // Yoast noindex if available
            if ( defined( 'WPSEO_VERSION' ) ) {
                update_post_meta( $new_page_id, '_yoast_wpseo_meta-robots-noindex', '1' );
            }
        }
    }

    /**
     * Add noindex meta for dedicated page (fallback when Yoast is not active)
     */
    public function maybe_noindex_dedicated_page() {
        if ( defined( 'WPSEO_VERSION' ) ) {
            return; // Yoast handles it
        }

        $page_id = (int) $this->settings->get( 'ofac_dedicated_page_id', 0 );
        if ( $page_id && is_page( $page_id ) ) {
            echo '<meta name="robots" content="noindex, nofollow">' . "\n";
        }
    }

    /**
     * Use minimal template for dedicated chatbot page
     *
     * @param string $template Template path.
     * @return string
     */
    public function dedicated_page_template( $template ) {
        $page_id = (int) $this->settings->get( 'ofac_dedicated_page_id', 0 );
        if ( $page_id && is_page( $page_id ) ) {
            $plugin_template = OFAC_PLUGIN_DIR . 'templates/dedicated-page.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }
        return $template;
    }

    /**
     * Hide admin bar on dedicated chatbot page
     *
     * @param bool $show Whether to show the admin bar.
     * @return bool
     */
    public function hide_admin_bar_on_dedicated_page( $show ) {
        $page_id = (int) $this->settings->get( 'ofac_dedicated_page_id', 0 );
        if ( $page_id && is_page( $page_id ) ) {
            return false;
        }
        return $show;
    }

    /**
     * Register and enqueue assets
     */
    public function register_assets() {
        // Always register assets first
        wp_register_style(
            'ofac-chatbot',
            OFAC_PLUGIN_URL . 'assets/css/chatbot.css',
            array(),
            OFAC_VERSION
        );

        wp_register_script(
            'ofac-chatbot',
            OFAC_PLUGIN_URL . 'assets/js/chatbot.js',
            array( 'jquery' ),
            OFAC_VERSION,
            true
        );

        wp_register_script(
            'ofac-bubble-popup',
            OFAC_PLUGIN_URL . 'assets/js/bubble-popup.js',
            array(),
            OFAC_VERSION,
            true
        );

        $chatbot        = OFAC_Chatbot::get_instance();
        $display_chat   = $chatbot->is_enabled() && $chatbot->should_display();
        $display_bubble = $chatbot->should_display_redirect_bubble();

        if ( ! $display_chat && ! $display_bubble ) {
            return;
        }

        wp_enqueue_style( 'ofac-chatbot' );

        if ( $display_chat ) {
            wp_enqueue_script( 'ofac-chatbot' );
            wp_localize_script(
                'ofac-chatbot',
                'ofacConfig',
                $this->get_frontend_config()
            );
        }

        // Bubble popup: enqueue when enabled, for both floating and redirect-bubble modes
        if ( (bool) $this->settings->get( 'ofac_bubble_popup_enabled', false )
            && ( $display_chat || $display_bubble ) ) {
            wp_enqueue_script( 'ofac-bubble-popup' );
            wp_localize_script(
                'ofac-bubble-popup',
                'ofacBubblePopup',
                array(
                    'delay'      => (int) $this->settings->get( 'ofac_bubble_popup_delay', 5 ),
                    'storageKey' => 'ofac_bubble_popup_closed',
                )
            );
        }
    }

    /**
     * Get frontend configuration
     *
     * @return array
     */
    public function get_frontend_config() {
        $accessibility = OFAC_Accessibility::get_instance();

        $config = array(
            'ajax_url'       => admin_url( 'admin-ajax.php' ),
            'nonce'          => wp_create_nonce( 'ofac_chat_nonce' ),
            'stream_enabled' => (bool) $this->settings->get( 'ofac_enable_streaming', false ),
            'settings'       => $this->settings->get_public_settings(),
            'accessibility'  => $accessibility->get_settings(),
            'labels'         => $this->get_frontend_labels(),
            'commands'       => array(
                'reset'  => '/reset',
                'help'   => '/help',
                'export' => '/export',
            ),
        );

        // Ajouter l'email de l'utilisateur connecte pour le pre-remplissage
        if ( is_user_logged_in() ) {
            $current_user = wp_get_current_user();
            $config['user_email'] = $current_user->user_email;
            $config['user_display_name'] = $current_user->display_name;
        }

        return $config;
    }

    /**
     * Get frontend labels
     *
     * @return array
     */
    private function get_frontend_labels() {
        $accessibility = OFAC_Accessibility::get_instance();

        return array(
            'openChat'       => $accessibility->get_label( 'open_chat' ),
            'closeChat'      => $accessibility->get_label( 'close_chat' ),
            'sendMessage'    => $accessibility->get_label( 'send_message' ),
            'typing'         => $accessibility->get_label( 'typing' ),
            'newMessage'     => $accessibility->get_label( 'new_message' ),
            'copySuccess'    => __( 'Copié !', 'anythingllm-chatbot' ),
            'copyError'      => __( 'Erreur de copie', 'anythingllm-chatbot' ),
            'exportSuccess'  => __( 'Conversation exportée', 'anythingllm-chatbot' ),
            'resetSuccess'   => __( 'Conversation réinitialisée', 'anythingllm-chatbot' ),
            'errorMessage'   => $this->settings->get( 'error_message' ),
            'networkError'   => __( 'Erreur réseau. Veuillez réessayer.', 'anythingllm-chatbot' ),
            'rateLimited'    => __( 'Trop de messages. Veuillez patienter.', 'anythingllm-chatbot' ),
            'consentTitle'   => __( 'Consentement requis', 'anythingllm-chatbot' ),
            'consentAccept'  => __( 'Accepter', 'anythingllm-chatbot' ),
            'consentDecline' => __( 'Refuser', 'anythingllm-chatbot' ),
            'helpTitle'      => __( 'Aide', 'anythingllm-chatbot' ),
            'helpCommands'   => __( 'Commandes disponibles :', 'anythingllm-chatbot' ),
            'helpReset'      => __( '/reset - Réinitialiser la conversation', 'anythingllm-chatbot' ),
            'helpExport'     => __( '/export - Exporter la conversation', 'anythingllm-chatbot' ),
            'helpHelp'       => __( '/help - Afficher cette aide', 'anythingllm-chatbot' ),
            'uploadFile'     => __( 'Joindre un fichier', 'anythingllm-chatbot' ),
            'fileTooLarge'   => __( 'Fichier trop volumineux', 'anythingllm-chatbot' ),
            'fileTypeError'  => __( 'Type de fichier non autorisé', 'anythingllm-chatbot' ),
            'uploading'      => __( 'Envoi en cours...', 'anythingllm-chatbot' ),
            'feedbackThanks' => __( 'Merci pour votre retour !', 'anythingllm-chatbot' ),
        );
    }

    /**
     * Render chatbot HTML
     */
    public function render_chatbot() {
        $chatbot = OFAC_Chatbot::get_instance();

        // Dedicated-mode redirect bubble (replaces full chatbot on non-dedicated pages)
        if ( $chatbot->should_display_redirect_bubble() ) {
            $this->render_redirect_bubble_html( $chatbot );
            return;
        }

        if ( ! $chatbot->is_enabled() || ! $chatbot->should_display() ) {
            return;
        }

        // Don't render if using shortcode or block
        if ( $this->is_rendered_by_shortcode() ) {
            return;
        }

        $this->render_chatbot_html();
    }

    /**
     * Render the redirect bubble HTML (dedicated mode shortcut)
     *
     * @param OFAC_Chatbot $chatbot Chatbot instance.
     */
    public function render_redirect_bubble_html( $chatbot ) {
        $dedicated_url = $chatbot->get_dedicated_page_url();
        $position      = $this->settings->get( 'ofac_position', 'bottom-right' );
        $primary_color = $this->settings->get( 'ofac_primary_color', '#2563eb' );
        $text_color    = $this->settings->get( 'ofac_text_color', '#ffffff' );
        $popup_enabled = (bool) $this->settings->get( 'ofac_bubble_popup_enabled', false );
        $popup_text    = $this->settings->get( 'ofac_bubble_popup_text', __( 'Besoin d\'aide ?', 'anythingllm-chatbot' ) );

        $style = sprintf(
            '--ofac-primary: %s; --ofac-primary-hover: %s; --ofac-text-inverse: %s;',
            esc_attr( $primary_color ),
            esc_attr( $this->adjust_brightness( $primary_color, -20 ) ),
            esc_attr( $text_color )
        );

        include OFAC_PLUGIN_DIR . 'templates/redirect-bubble.php';
    }

    /**
     * Check if chatbot is rendered by shortcode
     *
     * @return bool
     */
    private function is_rendered_by_shortcode() {
        global $post;

        if ( ! $post ) {
            return false;
        }

        // Check for shortcode
        if ( has_shortcode( $post->post_content, 'ofac_chatbot' ) ) {
            return true;
        }

        // Check for block
        if ( function_exists( 'has_block' ) && has_block( 'ofac/chatbot', $post ) ) {
            return true;
        }

        return false;
    }

    /**
     * Render chatbot HTML structure
     *
     * @param bool $inline Whether this is an inline (shortcode/block) render.
     */
    public function render_chatbot_html( $inline = false ) {
        $consent = OFAC_Consent::get_instance();
        $accessibility = OFAC_Accessibility::get_instance();
        $has_consent = $consent->has_consent();

        $position = $this->settings->get( 'ofac_position', 'bottom-right' );
        $bot_name = $this->settings->get( 'ofac_bot_name', 'Service Client' );
        $welcome_message = $this->settings->get( 'ofac_welcome_message', '' );
        $placeholder = $this->settings->get( 'ofac_placeholder_text', 'Tapez votre message...' );
        $bot_avatar = $this->settings->get( 'ofac_bot_avatar', '' );
        $user_avatar = $this->settings->get( 'ofac_user_avatar', '' );
        $primary_color = $this->settings->get( 'ofac_primary_color', '#2563eb' );
        $text_color = $this->settings->get( 'ofac_text_color', '#ffffff' );
        $width_desktop = $this->settings->get( 'ofac_width_desktop', 400 );
        $height_desktop = $this->settings->get( 'ofac_height_desktop', 600 );

        $header_avatar_size = intval( $this->settings->get( 'ofac_header_avatar_size', 48 ) );

        $bot_avatar_url = $bot_avatar ? wp_get_attachment_url( $bot_avatar ) : '';
        $user_avatar_url = $user_avatar ? wp_get_attachment_url( $user_avatar ) : '';

        // Support settings
        $enable_contact_btn  = (bool) $this->settings->get( 'ofac_enable_contact_btn', false );
        $contact_btn_label   = $this->settings->get( 'ofac_contact_btn_label', __( 'Contacter le support', 'anythingllm-chatbot' ) );
        $contact_email       = $this->settings->get( 'ofac_contact_email', '' );
        $contact_phone       = $this->settings->get( 'ofac_contact_phone', '' );
        $enable_callback_btn = (bool) $this->settings->get( 'ofac_enable_callback_btn', false );
        $callback_btn_label  = $this->settings->get( 'ofac_callback_btn_label', __( 'Être recontacté', 'anythingllm-chatbot' ) );

        // Bubble popup (above floating trigger)
        $popup_enabled = (bool) $this->settings->get( 'ofac_bubble_popup_enabled', false );
        $popup_text    = $this->settings->get( 'ofac_bubble_popup_text', __( 'Besoin d\'aide ?', 'anythingllm-chatbot' ) );

        $container_class = 'ofac-chatbot';
        if ( $inline ) {
            $container_class .= ' ofac-inline';
        }
        $container_class .= ' ofac-position-' . esc_attr( $position );

        // Apply custom CSS variables
        $style = sprintf(
            '--ofac-primary: %s; --ofac-primary-hover: %s; --ofac-text-inverse: %s; --ofac-modal-width: %dpx; --ofac-modal-height: %dpx; --ofac-bg-message-user: %s; --ofac-border-focus: %s; --ofac-text-link: %s; --ofac-avatar-size: %dpx;',
            esc_attr( $primary_color ),
            esc_attr( $this->adjust_brightness( $primary_color, -20 ) ),
            esc_attr( $text_color ),
            intval( $width_desktop ),
            intval( $height_desktop ),
            esc_attr( $primary_color ),
            esc_attr( $primary_color ),
            esc_attr( $primary_color ),
            $header_avatar_size
        );

        include OFAC_PLUGIN_DIR . 'templates/chatbot.php';
    }

    /**
     * Add skip link for accessibility
     */
    public function add_skip_link() {
        $chatbot = OFAC_Chatbot::get_instance();

        if ( ! $chatbot->is_enabled() || ! $chatbot->should_display() ) {
            return;
        }

        if ( ! $this->settings->get( 'show_skip_link' ) ) {
            return;
        }

        $accessibility = OFAC_Accessibility::get_instance();
        echo $accessibility->get_skip_link_html();
    }

    /**
     * Adjust color brightness
     *
     * @param string $hex    Hex color.
     * @param int    $steps  Steps (-255 to 255).
     * @return string
     */
    private function adjust_brightness( $hex, $steps ) {
        $hex = str_replace( '#', '', $hex );

        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );

        $r = max( 0, min( 255, $r + $steps ) );
        $g = max( 0, min( 255, $g + $steps ) );
        $b = max( 0, min( 255, $b + $steps ) );

        return '#' . sprintf( '%02x%02x%02x', $r, $g, $b );
    }

    /**
     * Get shortcode instance
     *
     * @return OFAC_Shortcode
     */
    public function get_shortcode() {
        return $this->shortcode;
    }

    /**
     * Get block instance
     *
     * @return OFAC_Block
     */
    public function get_block() {
        return $this->block;
    }
}
