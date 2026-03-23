<?php
/**
 * GitHub Updater
 *
 * Handles automatic plugin updates from GitHub releases.
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_GitHub_Updater
 *
 * Checks GitHub releases for new versions and integrates
 * with the WordPress plugin update system.
 */
class OFAC_GitHub_Updater {

    /**
     * GitHub repository (owner/repo)
     */
    const GITHUB_REPO = 'ocade-graciet-system/ocade-chatbot-anything-llm-n8n';

    /**
     * Cache key for GitHub API response
     */
    const CACHE_KEY = 'ofac_github_updater';

    /**
     * Cache duration in seconds (12 hours)
     */
    const CACHE_EXPIRATION = 43200;

    /**
     * Single instance
     *
     * @var OFAC_GitHub_Updater|null
     */
    private static $instance = null;

    /**
     * Plugin basename (e.g. folder-name/main-file.php)
     *
     * @var string
     */
    private $plugin_basename;

    /**
     * Current plugin directory name
     *
     * @var string
     */
    private $plugin_dir_name;

    /**
     * Plugin main file path
     *
     * @var string
     */
    private $plugin_file;

    /**
     * Get single instance
     *
     * @return OFAC_GitHub_Updater
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
        $this->plugin_basename = OFAC_PLUGIN_BASENAME;
        $this->plugin_dir_name = dirname( OFAC_PLUGIN_BASENAME );
        $this->plugin_file     = WP_PLUGIN_DIR . '/' . OFAC_PLUGIN_BASENAME;

        // Check for updates
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );

        // Provide plugin info for the update details popup
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );

        // Fix directory name after extraction
        add_filter( 'upgrader_source_selection', array( $this, 'fix_directory_name' ), 10, 4 );

        // Clear cache after update
        add_action( 'upgrader_process_complete', array( $this, 'after_update' ), 10, 2 );
    }

    /**
     * Check GitHub for the latest release and inject into WordPress update transient
     *
     * @param object $transient The update_plugins transient.
     * @return object Modified transient.
     */
    public function check_for_updates( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $transient;
        }

        $current_version = $this->get_current_version();
        $remote_version  = ltrim( $release['tag_name'], 'v' );

        if ( version_compare( $remote_version, $current_version, '>' ) ) {
            $download_url = $this->get_zip_url( $release );

            if ( $download_url ) {
                $transient->response[ $this->plugin_basename ] = (object) array(
                    'slug'        => $this->plugin_dir_name,
                    'plugin'      => $this->plugin_basename,
                    'new_version' => $remote_version,
                    'url'         => 'https://github.com/' . self::GITHUB_REPO,
                    'package'     => $download_url,
                    'icons'       => array(),
                    'banners'     => array(),
                    'tested'      => '',
                    'requires'    => OFAC_MIN_WP_VERSION,
                    'requires_php' => OFAC_MIN_PHP_VERSION,
                );
            }
        }

        return $transient;
    }

    /**
     * Provide plugin information for the "View Details" popup
     *
     * @param false|object|array $result The result.
     * @param string             $action The API action.
     * @param object             $args   The plugin args.
     * @return false|object
     */
    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $result;
        }

        if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_dir_name ) {
            return $result;
        }

        $release = $this->get_latest_release();
        if ( ! $release ) {
            return $result;
        }

        $remote_version = ltrim( $release['tag_name'], 'v' );
        $download_url   = $this->get_zip_url( $release );

        $plugin_data = get_plugin_data( $this->plugin_file );

        $info = (object) array(
            'name'            => $plugin_data['Name'],
            'slug'            => $this->plugin_dir_name,
            'version'         => $remote_version,
            'author'          => $plugin_data['Author'],
            'author_profile'  => $plugin_data['AuthorURI'],
            'homepage'        => $plugin_data['PluginURI'],
            'requires'        => OFAC_MIN_WP_VERSION,
            'requires_php'    => OFAC_MIN_PHP_VERSION,
            'download_link'   => $download_url,
            'trunk'           => $download_url,
            'last_updated'    => $release['published_at'] ?? '',
            'sections'        => array(
                'description' => $plugin_data['Description'],
                'changelog'   => $this->format_changelog( $release ),
            ),
        );

        return $info;
    }

    /**
     * Fix the extracted directory name to match the current plugin directory
     *
     * WordPress extracts the zip into a temp folder. If the folder name inside
     * the zip doesn't match our plugin directory, WordPress loses track of the plugin.
     *
     * @param string      $source        Path to the extracted source.
     * @param string      $remote_source Path to the remote source.
     * @param WP_Upgrader $upgrader      Upgrader instance.
     * @param array       $hook_extra    Extra hook data.
     * @return string|WP_Error
     */
    public function fix_directory_name( $source, $remote_source, $upgrader, $hook_extra ) {
        // Only process our plugin
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
            return $source;
        }

        global $wp_filesystem;

        $expected_dir = trailingslashit( $remote_source ) . trailingslashit( $this->plugin_dir_name );

        if ( $source === $expected_dir ) {
            return $source;
        }

        // Rename the extracted directory to match the current plugin directory
        $result = $wp_filesystem->move( $source, $expected_dir, true );

        if ( ! $result ) {
            return new WP_Error(
                'ofac_rename_failed',
                __( 'Failed to rename the plugin directory during update.', 'anythingllm-chatbot' )
            );
        }

        return $expected_dir;
    }

    /**
     * Clear update cache after a successful update
     *
     * @param WP_Upgrader $upgrader Upgrader instance.
     * @param array       $options  Update options.
     */
    public function after_update( $upgrader, $options ) {
        if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
            if ( isset( $options['plugins'] ) && in_array( $this->plugin_basename, $options['plugins'], true ) ) {
                delete_transient( self::CACHE_KEY );
            }
        }
    }

    /**
     * Get the latest release from GitHub (cached)
     *
     * @return array|false Release data or false on failure.
     */
    private function get_latest_release() {
        $cached = get_transient( self::CACHE_KEY );
        if ( false !== $cached ) {
            return $cached;
        }

        // Use /releases (not /releases/latest) to include pre-releases
        $url = sprintf( 'https://api.github.com/repos/%s/releases?per_page=1', self::GITHUB_REPO );

        $response = wp_remote_get( $url, array(
            'timeout' => 10,
            'headers' => array(
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[OFAC Updater] GitHub API error: ' . $response->get_error_message() );
            }
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[OFAC Updater] GitHub API returned HTTP ' . $code );
            }
            if ( 403 === $code ) {
                set_transient( self::CACHE_KEY, false, 900 );
            }
            return false;
        }

        $releases = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $releases ) || empty( $releases ) ) {
            return false;
        }

        // Take the first (most recent) release
        $body = $releases[0];
        if ( empty( $body['tag_name'] ) ) {
            return false;
        }

        set_transient( self::CACHE_KEY, $body, self::CACHE_EXPIRATION );

        return $body;
    }

    /**
     * Get the current installed plugin version from the file header
     *
     * @return string
     */
    private function get_current_version() {
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data( $this->plugin_file );

        return $plugin_data['Version'] ?? '0.0.0';
    }

    /**
     * Extract the zip download URL from a release
     *
     * @param array $release GitHub release data.
     * @return string|false
     */
    private function get_zip_url( $release ) {
        if ( empty( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
            return false;
        }

        foreach ( $release['assets'] as $asset ) {
            if ( ! empty( $asset['name'] ) && str_ends_with( $asset['name'], '.zip' ) ) {
                return $asset['browser_download_url'] ?? false;
            }
        }

        return false;
    }

    /**
     * Format the release body as a changelog section
     *
     * @param array $release GitHub release data.
     * @return string
     */
    private function format_changelog( $release ) {
        $body = $release['body'] ?? '';

        if ( empty( $body ) ) {
            return '<p>' . esc_html__( 'No changelog available for this release.', 'anythingllm-chatbot' ) . '</p>';
        }

        // Convert markdown-style lists to HTML
        $lines = explode( "\n", $body );
        $html  = '<ul>';
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( empty( $line ) ) {
                continue;
            }
            // Strip markdown list markers
            $line = preg_replace( '/^[-*]\s+/', '', $line );
            $html .= '<li>' . esc_html( $line ) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    /**
     * Manually check for updates (useful for admin action)
     *
     * @return array|false
     */
    public function force_check() {
        delete_transient( self::CACHE_KEY );
        return $this->get_latest_release();
    }
}
