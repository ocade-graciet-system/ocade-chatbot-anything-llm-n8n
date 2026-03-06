<?php
/**
 * Rate limiter class
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class OFAC_Rate_Limiter
 * Handles request rate limiting
 */
class OFAC_Rate_Limiter {

    /**
     * Transient prefix
     *
     * @var string
     */
    const TRANSIENT_PREFIX = 'ofac_rate_';

    /**
     * Transient prefix for hourly limits
     *
     * @var string
     */
    const HOURLY_TRANSIENT_PREFIX = 'ofac_rate_h_';

    /**
     * Rate limit (requests per minute)
     *
     * @var int
     */
    private $limit;

    /**
     * Rate limit (requests per hour)
     *
     * @var int
     */
    private $hourly_limit;

    /**
     * Window duration in seconds (per minute)
     *
     * @var int
     */
    private $window = 60;

    /**
     * Hourly window duration in seconds
     *
     * @var int
     */
    private $hourly_window = 3600;

    /**
     * Constructor
     */
    public function __construct() {
        $settings           = OFAC_Settings::get_instance();
        $this->limit        = $settings->get( 'ofac_rate_limit', 30 );
        $this->hourly_limit = $settings->get( 'ofac_rate_limit_hourly', 100 );
    }

    /**
     * Check if request is allowed (per minute AND per hour)
     *
     * @return bool
     */
    public function check() {
        // Verifier la limite par minute
        $key   = $this->get_key();
        $count = $this->get_count( $key );

        if ( $count >= $this->limit ) {
            /** @since 1.0.0 */
            do_action( 'ofac_rate_limit_exceeded', $key, $count, $this->limit );
            return false;
        }

        // Verifier la limite par heure
        $hourly_key   = $this->get_hourly_key();
        $hourly_count = $this->get_count( $hourly_key );

        if ( $hourly_count >= $this->hourly_limit ) {
            /** @since 1.0.8 */
            do_action( 'ofac_hourly_rate_limit_exceeded', $hourly_key, $hourly_count, $this->hourly_limit );
            return false;
        }

        $this->increment( $key, $this->window );
        $this->increment( $hourly_key, $this->hourly_window );
        return true;
    }

    /**
     * Get rate limit key (per minute) for current request
     *
     * @return string
     */
    private function get_key() {
        $ip = $this->get_client_ip();
        return self::TRANSIENT_PREFIX . md5( $ip );
    }

    /**
     * Get hourly rate limit key for current request
     *
     * @return string
     */
    private function get_hourly_key() {
        $ip = $this->get_client_ip();
        return self::HOURLY_TRANSIENT_PREFIX . md5( $ip );
    }

    /**
     * Get current count for key
     *
     * @param string $key Rate limit key
     * @return int
     */
    private function get_count( $key ) {
        $data = get_transient( $key );

        if ( $data === false ) {
            return 0;
        }

        return (int) $data['count'];
    }

    /**
     * Increment count for key
     *
     * @param string $key    Rate limit key
     * @param int    $window Window duration in seconds
     */
    private function increment( $key, $window = 60 ) {
        $data = get_transient( $key );

        if ( $data === false ) {
            $data = array(
                'count'   => 1,
                'started' => time(),
            );
        } else {
            $data['count']++;
        }

        set_transient( $key, $data, $window );
    }

    /**
     * Get remaining requests (per minute)
     *
     * @return int
     */
    public function get_remaining() {
        $key   = $this->get_key();
        $count = $this->get_count( $key );

        return max( 0, $this->limit - $count );
    }

    /**
     * Get remaining requests (per hour)
     *
     * @return int
     */
    public function get_remaining_hourly() {
        $key   = $this->get_hourly_key();
        $count = $this->get_count( $key );

        return max( 0, $this->hourly_limit - $count );
    }

    /**
     * Get time until reset (per minute)
     *
     * @return int Seconds until reset
     */
    public function get_reset_time() {
        $key  = $this->get_key();
        $data = get_transient( $key );

        if ( $data === false ) {
            return 0;
        }

        $elapsed = time() - $data['started'];
        return max( 0, $this->window - $elapsed );
    }

    /**
     * Get time until hourly reset
     *
     * @return int Seconds until reset
     */
    public function get_hourly_reset_time() {
        $key  = $this->get_hourly_key();
        $data = get_transient( $key );

        if ( $data === false ) {
            return 0;
        }

        $elapsed = time() - $data['started'];
        return max( 0, $this->hourly_window - $elapsed );
    }

    /**
     * Reset rate limit for current IP
     */
    public function reset() {
        delete_transient( $this->get_key() );
        delete_transient( $this->get_hourly_key() );
    }

    /**
     * Get client IP
     *
     * @return string
     */
    private function get_client_ip() {
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        // Handle comma-separated IPs
        if ( strpos( $ip, ',' ) !== false ) {
            $ips = explode( ',', $ip );
            $ip  = trim( $ips[0] );
        }

        return $ip;
    }

    /**
     * Get rate limit headers for response
     *
     * @return array
     */
    public function get_headers() {
        return array(
            'X-RateLimit-Limit'          => $this->limit,
            'X-RateLimit-Remaining'      => $this->get_remaining(),
            'X-RateLimit-Reset'          => time() + $this->get_reset_time(),
            'X-RateLimit-Hourly-Limit'     => $this->hourly_limit,
            'X-RateLimit-Hourly-Remaining' => $this->get_remaining_hourly(),
            'X-RateLimit-Hourly-Reset'     => time() + $this->get_hourly_reset_time(),
        );
    }
}
