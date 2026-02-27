<?php
/**
 * Capabilities management class
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Capabilities
 * Handles custom capabilities, roles and permission sync
 */
class OFAC_Capabilities {

    /**
     * Single instance
     *
     * @var OFAC_Capabilities|null
     */
    private static $instance = null;

    /**
     * Custom role slug
     */
    const SUPPORT_ROLE = 'support_chatbot';

    /**
     * All custom capabilities
     */
    const CAPABILITIES = array(
        'manage_ofac_settings',
        'manage_ofac_logs',
        'manage_ofac_callbacks',
        'manage_ofac_stats',
        'manage_ofac_gdpr',
    );

    /**
     * Mapping: capability → setting key for configurable roles
     */
    const CAP_SETTINGS_MAP = array(
        'manage_ofac_logs'      => 'ofac_logs_roles',
        'manage_ofac_callbacks' => 'ofac_callbacks_roles',
        'manage_ofac_stats'     => 'ofac_stats_roles',
    );

    /**
     * Get single instance
     *
     * @return OFAC_Capabilities
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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Sync capabilities when settings are saved
        add_action( 'update_option_ofac_logs_roles', array( $this, 'sync_capabilities' ) );
        add_action( 'update_option_ofac_callbacks_roles', array( $this, 'sync_capabilities' ) );
        add_action( 'update_option_ofac_stats_roles', array( $this, 'sync_capabilities' ) );

        // Ensure capabilities exist on every admin load (handles upgrades)
        $this->maybe_init_capabilities();
    }

    /**
     * Check if capabilities need to be initialized (for upgrades or first install)
     * Runs on every admin load but only does work when needed
     */
    private function maybe_init_capabilities() {
        $cap_version = get_option( 'ofac_cap_version', '0' );
        if ( version_compare( $cap_version, '1.1.0', '<' ) ) {
            self::activate();
            $this->sync_capabilities();
            update_option( 'ofac_cap_version', '1.1.0' );
        }
    }

    /**
     * Run on plugin activation: create role and assign capabilities
     */
    public static function activate() {
        // Add all capabilities to administrator
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            foreach ( self::CAPABILITIES as $cap ) {
                $admin_role->add_cap( $cap );
            }
        }

        // Create Support Chat Bot role
        $existing = get_role( self::SUPPORT_ROLE );
        if ( $existing ) {
            remove_role( self::SUPPORT_ROLE );
        }

        add_role(
            self::SUPPORT_ROLE,
            __( 'Support Chat Bot', 'anythingllm-chatbot' ),
            array(
                'read'                  => true,
                'manage_ofac_logs'      => true,
                'manage_ofac_callbacks' => true,
                'manage_ofac_stats'     => true,
            )
        );

        // Set default role configuration
        $defaults = array(
            'ofac_logs_roles'      => array( 'administrator', self::SUPPORT_ROLE ),
            'ofac_callbacks_roles' => array( 'administrator', self::SUPPORT_ROLE ),
            'ofac_stats_roles'     => array( 'administrator', self::SUPPORT_ROLE ),
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }

    /**
     * Run on plugin deactivation: remove role and capabilities
     */
    public static function deactivate() {
        // Remove custom role
        remove_role( self::SUPPORT_ROLE );

        // Remove custom capabilities from all roles
        global $wp_roles;
        if ( ! isset( $wp_roles ) ) {
            $wp_roles = new WP_Roles();
        }

        foreach ( $wp_roles->roles as $role_slug => $role_data ) {
            $role = get_role( $role_slug );
            if ( $role ) {
                foreach ( self::CAPABILITIES as $cap ) {
                    $role->remove_cap( $cap );
                }
            }
        }
    }

    /**
     * Sync capabilities based on settings configuration
     * Called when permission settings are updated
     */
    public function sync_capabilities() {
        global $wp_roles;
        if ( ! isset( $wp_roles ) ) {
            $wp_roles = new WP_Roles();
        }

        // For each configurable capability
        foreach ( self::CAP_SETTINGS_MAP as $cap => $setting_key ) {
            $allowed_roles = get_option( $setting_key, array() );
            if ( ! is_array( $allowed_roles ) ) {
                $allowed_roles = array();
            }

            // Always include administrator
            if ( ! in_array( 'administrator', $allowed_roles, true ) ) {
                $allowed_roles[] = 'administrator';
            }

            // Update each role
            foreach ( $wp_roles->roles as $role_slug => $role_data ) {
                $role = get_role( $role_slug );
                if ( ! $role ) {
                    continue;
                }

                if ( in_array( $role_slug, $allowed_roles, true ) ) {
                    $role->add_cap( $cap );
                } else {
                    $role->remove_cap( $cap );
                }
            }
        }

        // Non-configurable capabilities: settings and gdpr are always admin-only
        foreach ( $wp_roles->roles as $role_slug => $role_data ) {
            $role = get_role( $role_slug );
            if ( ! $role ) {
                continue;
            }

            if ( $role_slug === 'administrator' ) {
                $role->add_cap( 'manage_ofac_settings' );
                $role->add_cap( 'manage_ofac_gdpr' );
            } else {
                $role->remove_cap( 'manage_ofac_settings' );
                $role->remove_cap( 'manage_ofac_gdpr' );
            }
        }
    }

    /**
     * Check if current user has any OFAC capability (for menu visibility)
     *
     * @return bool
     */
    public static function current_user_has_any_cap() {
        foreach ( self::CAPABILITIES as $cap ) {
            if ( current_user_can( $cap ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get the first accessible page slug for current user
     *
     * @return string
     */
    public static function get_default_page() {
        $pages = array(
            'manage_ofac_settings'  => 'ofac-settings',
            'manage_ofac_logs'      => 'ofac-logs',
            'manage_ofac_stats'     => 'ofac-stats',
            'manage_ofac_callbacks' => 'ofac-callbacks',
            'manage_ofac_gdpr'      => 'ofac-gdpr',
        );

        foreach ( $pages as $cap => $slug ) {
            if ( current_user_can( $cap ) ) {
                return $slug;
            }
        }

        return 'ofac-settings';
    }

    /**
     * Get the minimum capability for main menu visibility
     * Returns the first capability the current user has
     *
     * @return string
     */
    public static function get_menu_capability() {
        foreach ( self::CAPABILITIES as $cap ) {
            if ( current_user_can( $cap ) ) {
                return $cap;
            }
        }
        return 'manage_ofac_settings';
    }
}
