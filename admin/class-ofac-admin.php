<?php
/**
 * Admin functionality
 *
 * @package Ocade_Fusion_AnythingLLM_Chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Admin
 * 
 * Handles all admin functionality
 */
class OFAC_Admin {

    /**
     * Instance
     *
     * @var OFAC_Admin
     */
    private static $instance = null;

    /**
     * Settings page
     *
     * @var OFAC_Admin_Settings
     */
    private $settings_page;

    /**
     * Logs page
     *
     * @var OFAC_Admin_Logs
     */
    private $logs_page;

    /**
     * Stats page
     *
     * @var OFAC_Admin_Stats
     */
    private $stats_page;

    /**
     * GDPR page
     *
     * @var OFAC_Admin_GDPR
     */
    private $gdpr_page;

    /**
     * Callbacks page
     *
     * @var OFAC_Admin_Callbacks
     */
    private $callbacks_page;

    /**
     * Get instance
     *
     * @return OFAC_Admin
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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load dependencies
     */
    private function load_dependencies() {
        require_once OFAC_PLUGIN_DIR . 'admin/class-ofac-admin-settings.php';
        require_once OFAC_PLUGIN_DIR . 'admin/class-ofac-admin-logs.php';
        require_once OFAC_PLUGIN_DIR . 'admin/class-ofac-admin-stats.php';
        require_once OFAC_PLUGIN_DIR . 'admin/class-ofac-admin-gdpr.php';
        require_once OFAC_PLUGIN_DIR . 'admin/class-ofac-admin-callbacks.php';

        $this->settings_page = new OFAC_Admin_Settings();
        $this->logs_page = new OFAC_Admin_Logs();
        $this->stats_page = new OFAC_Admin_Stats();
        $this->gdpr_page = new OFAC_Admin_GDPR();
        $this->callbacks_page = new OFAC_Admin_Callbacks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_filter( 'plugin_action_links_' . OFAC_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Determine the default page for the current user
        $default_page = OFAC_Capabilities::get_default_page();
        $default_callback = $this->get_page_callback( $default_page );

        // Main menu — visible to anyone with at least one OFAC capability
        add_menu_page(
            __( 'AnythingLLM Chatbot', 'anythingllm-chatbot' ),
            __( 'AnythingLLM', 'anythingllm-chatbot' ),
            OFAC_Capabilities::get_menu_capability(),
            $default_page,
            $default_callback,
            'dashicons-format-chat',
            80
        );

        // Settings submenu (admin only)
        if ( current_user_can( 'manage_ofac_settings' ) ) {
            add_submenu_page(
                $default_page,
                __( 'Réglages', 'anythingllm-chatbot' ),
                __( 'Réglages', 'anythingllm-chatbot' ),
                'manage_ofac_settings',
                'ofac-settings',
                array( $this->settings_page, 'render' )
            );
        }

        // Logs submenu
        add_submenu_page(
            $default_page,
            __( 'Journaux', 'anythingllm-chatbot' ),
            __( 'Journaux', 'anythingllm-chatbot' ),
            'manage_ofac_logs',
            'ofac-logs',
            array( $this->logs_page, 'render' )
        );

        // Stats submenu
        add_submenu_page(
            $default_page,
            __( 'Statistiques', 'anythingllm-chatbot' ),
            __( 'Statistiques', 'anythingllm-chatbot' ),
            'manage_ofac_stats',
            'ofac-stats',
            array( $this->stats_page, 'render' )
        );

        // Callbacks submenu
        $pending_count = $this->get_pending_callbacks_count();
        $callbacks_label = __( 'Demandes', 'anythingllm-chatbot' );
        if ( $pending_count > 0 ) {
            $callbacks_label .= ' <span class="awaiting-mod count-' . $pending_count . '"><span class="pending-count">' . $pending_count . '</span></span>';
        }
        add_submenu_page(
            $default_page,
            __( 'Demandes de rappel', 'anythingllm-chatbot' ),
            $callbacks_label,
            'manage_ofac_callbacks',
            'ofac-callbacks',
            array( $this->callbacks_page, 'render' )
        );

        // GDPR submenu (admin only)
        if ( current_user_can( 'manage_ofac_gdpr' ) ) {
            add_submenu_page(
                $default_page,
                __( 'RGPD', 'anythingllm-chatbot' ),
                __( 'RGPD', 'anythingllm-chatbot' ),
                'manage_ofac_gdpr',
                'ofac-gdpr',
                array( $this->gdpr_page, 'render' )
            );
        }
    }

    /**
     * Get page render callback by slug
     *
     * @param string $slug Page slug.
     * @return callable
     */
    private function get_page_callback( $slug ) {
        $map = array(
            'ofac-settings'  => array( $this->settings_page, 'render' ),
            'ofac-logs'      => array( $this->logs_page, 'render' ),
            'ofac-stats'     => array( $this->stats_page, 'render' ),
            'ofac-callbacks' => array( $this->callbacks_page, 'render' ),
            'ofac-gdpr'      => array( $this->gdpr_page, 'render' ),
        );

        return isset( $map[ $slug ] ) ? $map[ $slug ] : array( $this->settings_page, 'render' );
    }

    /**
     * Enqueue admin styles
     *
     * @param string $hook Current page hook.
     */
    public function enqueue_styles( $hook ) {
        if ( ! $this->is_plugin_page( $hook ) ) {
            return;
        }

        wp_enqueue_style(
            'ofac-admin',
            OFAC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            OFAC_VERSION
        );

        // Color picker
        wp_enqueue_style( 'wp-color-picker' );
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current page hook.
     */
    public function enqueue_scripts( $hook ) {
        if ( ! $this->is_plugin_page( $hook ) ) {
            return;
        }

        // Media uploader
        wp_enqueue_media();

        // Color picker
        wp_enqueue_script( 'wp-color-picker' );

        // Chart.js for stats
        if ( strpos( $hook, 'ofac-stats' ) !== false ) {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
                array(),
                '4.4.1',
                true
            );
        }

        wp_enqueue_script(
            'ofac-admin',
            OFAC_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'wp-color-picker' ),
            OFAC_VERSION,
            true
        );

        wp_localize_script( 'ofac-admin', 'ofacAdmin', array(
            'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'ofac_admin_nonce' ),
            'strings'     => array(
                'confirmDelete'     => __( 'Êtes-vous sûr de vouloir supprimer ?', 'anythingllm-chatbot' ),
                'confirmReset'      => __( 'Êtes-vous sûr de vouloir réinitialiser les réglages ?', 'anythingllm-chatbot' ),
                'testingConnection' => __( 'Test en cours...', 'anythingllm-chatbot' ),
                'connectionSuccess' => __( 'Connexion réussie !', 'anythingllm-chatbot' ),
                'connectionError'   => __( 'Erreur de connexion', 'anythingllm-chatbot' ),
                'saved'             => __( 'Enregistré', 'anythingllm-chatbot' ),
                'error'             => __( 'Erreur', 'anythingllm-chatbot' ),
                'selectImage'       => __( 'Sélectionner une image', 'anythingllm-chatbot' ),
                'useImage'          => __( 'Utiliser cette image', 'anythingllm-chatbot' ),
                'exporting'         => __( 'Export en cours...', 'anythingllm-chatbot' ),
                'importing'         => __( 'Import en cours...', 'anythingllm-chatbot' ),
            ),
            'previewSettings' => OFAC_Settings::get_instance()->get_public_settings(),
        ) );
    }

    /**
     * Check if current page is a plugin page
     *
     * @param string $hook Current page hook.
     * @return bool
     */
    private function is_plugin_page( $hook ) {
        return strpos( $hook, 'ofac-' ) !== false;
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'ofac_settings',
            'ofac_settings',
            array(
                'sanitize_callback' => array( OFAC_Settings::get_instance(), 'sanitize_settings' ),
            )
        );
    }

    /**
     * Add action links to plugins page
     *
     * @param array $links Existing links.
     * @return array
     */
    public function add_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=ofac-settings' ),
            __( 'Réglages', 'anythingllm-chatbot' )
        );

        array_unshift( $links, $settings_link );

        return $links;
    }

    /**
     * Get settings page instance
     *
     * @return OFAC_Admin_Settings
     */
    public function get_settings_page() {
        return $this->settings_page;
    }

    /**
     * Get logs page instance
     *
     * @return OFAC_Admin_Logs
     */
    public function get_logs_page() {
        return $this->logs_page;
    }

    /**
     * Get stats page instance
     *
     * @return OFAC_Admin_Stats
     */
    public function get_stats_page() {
        return $this->stats_page;
    }

    /**
     * Get GDPR page instance
     *
     * @return OFAC_Admin_GDPR
     */
    public function get_gdpr_page() {
        return $this->gdpr_page;
    }

    /**
     * Get callbacks page instance
     *
     * @return OFAC_Admin_Callbacks
     */
    public function get_callbacks_page() {
        return $this->callbacks_page;
    }

    /**
     * Get pending callbacks count for menu badge
     *
     * @return int
     */
    private function get_pending_callbacks_count() {
        global $wpdb;
        $table = $wpdb->prefix . 'ofac_callback_requests';

        // Verifier que la table existe
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return 0;
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'pending'" );
    }
}
