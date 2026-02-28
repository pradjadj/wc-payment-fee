<?php
/**
 * GitHub Plugin Updater Class
 * Handles automatic plugin updates from GitHub releases
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Payment_Fee_GitHub_Updater {
    
    private $github_repo = 'pradjadj/wc-payment-fee';
    private $github_api_url = 'https://api.github.com/repos/';
    private $plugin_file = 'wc-payment-fee/wc-payment-fee.php';
    private $cache_key = 'wc_payment_fee_github_release';
    private $cache_time = 86400; // 24 hours

    public function __construct() {
        // Hook into WordPress update checking
        add_filter( 'transient_update_plugins', array( $this, 'check_for_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_api_call' ), 10, 3 );
        add_action( 'upgrader_process_complete', array( $this, 'purge_cache' ), 10, 2 );
    }

    /**
     * Check for updates from GitHub
     */
    public function check_for_update( $transient ) {
        // Check if auto-updates are enabled
        if ( ! get_option( 'wc_payment_fee_auto_updates_enabled', true ) ) {
            return $transient;
        }

        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        // Get current plugin version
        $plugin_data = get_plugin_data( WP_PLUGIN_DIR . '/' . $this->plugin_file );
        $current_version = $plugin_data['Version'];

        // Get latest release from GitHub
        $release = $this->get_github_release();

        if ( ! $release ) {
            return $transient;
        }

        // Compare versions
        if ( version_compare( $release['version'], $current_version, '>' ) ) {
            $transient->response[ $this->plugin_file ] = (object) array(
                'id'          => $this->plugin_file,
                'slug'        => 'wc-payment-fee',
                'plugin'      => $this->plugin_file,
                'new_version' => $release['version'],
                'url'         => $release['url'],
                'package'     => $release['download_url'],
                'tested'      => get_bloginfo( 'version' ),
                'requires'    => '5.0',
                'requires_php'=> '7.2',
                'icons'       => array(),
            );
        }

        return $transient;
    }

    /**
     * Provide plugin API information for WordPress
     */
    public function plugin_api_call( $result, $action, $args ) {
        // Check if auto-updates are enabled
        if ( ! get_option( 'wc_payment_fee_auto_updates_enabled', true ) ) {
            return $result;
        }

        if ( $action !== 'plugin_information' ) {
            return $result;
        }

        if ( isset( $args->slug ) && $args->slug !== 'wc-payment-fee' ) {
            return $result;
        }

        $release = $this->get_github_release();

        if ( ! $release ) {
            return $result;
        }

        $result = new \stdClass();
        $result->name = 'WC Payment Fee';
        $result->slug = 'wc-payment-fee';
        $result->version = $release['version'];
        $result->tested = get_bloginfo( 'version' );
        $result->requires = '5.0';
        $result->requires_php = '7.2';
        $result->download_link = $release['download_url'];
        $result->trunk = $release['download_url'];
        $result->last_updated = $release['updated'];
        $result->sections = array(
            'description' => '<p>WooCommerce plugin untuk menambahkan biaya pembayaran (payment fee) yang dapat dikustomisasi per metode pembayaran.</p>',
            'changelog'   => sprintf( '<p><strong>Version %s</strong></p><p>Update dari GitHub. Lihat <a href="%s">release notes</a> untuk detail lengkap.</p>', 
                esc_html( $release['version'] ),
                esc_url( $release['url'] )
            ),
        );

        return $result;
    }

    /**
     * Get latest release from GitHub
     */
    private function get_github_release() {
        // Check cache
        $cached = get_transient( $this->cache_key );
        if ( $cached ) {
            return $cached;
        }

        // Fetch from GitHub API
        $api_url = $this->github_api_url . $this->github_repo . '/releases/latest';
        
        $response = wp_remote_get(
            $api_url,
            array(
                'timeout'   => 10,
                'sslverify' => true,
            )
        );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $release = json_decode( $body, true );

        if ( ! isset( $release['tag_name'] ) ) {
            return false;
        }

        // Extract version from tag (remove 'v' prefix if exists)
        $version = ltrim( $release['tag_name'], 'v' );

        // Get download URL for zip file
        $download_url = false;
        if ( ! empty( $release['zipball_url'] ) ) {
            $download_url = $release['zipball_url'];
        }

        if ( ! $download_url ) {
            return false;
        }

        $result = array(
            'version'      => $version,
            'url'          => $release['html_url'],
            'download_url' => $download_url,
            'updated'      => $release['published_at'],
        );

        // Cache for 24 hours
        set_transient( $this->cache_key, $result, $this->cache_time );

        return $result;
    }

    /**
     * Purge cache after plugin update
     */
    public function purge_cache( $upgrader_object, $options ) {
        if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
            delete_transient( $this->cache_key );
        }
    }
}

// Initialize the updater
new WC_Payment_Fee_GitHub_Updater();
