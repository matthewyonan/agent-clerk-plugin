<?php
/**
 * Plugin Name: AgentClerk
 * Plugin URI: https://agentclerk.io
 * Description: AI-powered sales and support agent for WooCommerce stores.
 * Version: 1.0.0
 * Author: AgentClerk
 * Author URI: https://agentclerk.io
 * License: GPL-2.0+
 * Text Domain: agentclerk
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AGENTCLERK_VERSION', '1.0.0' );
define( 'AGENTCLERK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AGENTCLERK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AGENTCLERK_BACKEND_URL', 'https://app.agentclerk.io/api' );

require_once AGENTCLERK_PLUGIN_DIR . 'includes/class-activator.php';
require_once AGENTCLERK_PLUGIN_DIR . 'includes/class-agent.php';
require_once AGENTCLERK_PLUGIN_DIR . 'includes/class-scanner.php';
require_once AGENTCLERK_PLUGIN_DIR . 'includes/class-manifest.php';
require_once AGENTCLERK_PLUGIN_DIR . 'includes/class-billing.php';
require_once AGENTCLERK_PLUGIN_DIR . 'includes/class-woocommerce.php';
require_once AGENTCLERK_PLUGIN_DIR . 'includes/class-conversations.php';
require_once AGENTCLERK_PLUGIN_DIR . 'includes/class-support.php';
require_once AGENTCLERK_PLUGIN_DIR . 'admin/class-admin.php';
require_once AGENTCLERK_PLUGIN_DIR . 'public/class-widget.php';

register_activation_hook( __FILE__, [ 'AgentClerk_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'AgentClerk_Activator', 'deactivate' ] );

/**
 * Main plugin class.
 */
final class AgentClerk {

    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'init', [ $this, 'init' ] );
        add_action( 'admin_init', [ $this, 'maybe_redirect_onboarding' ] );

        new AgentClerk_Agent();
        new AgentClerk_Manifest();
        new AgentClerk_Billing();
        new AgentClerk_WooCommerce();
        new AgentClerk_Conversations();
        new AgentClerk_Support();

        if ( is_admin() ) {
            new AgentClerk_Admin();
        } else {
            new AgentClerk_Widget();
        }
    }

    public function init() {
        add_rewrite_rule(
            '^ai-manifest\.json$',
            'index.php?agentclerk_manifest=1',
            'top'
        );
        add_filter( 'query_vars', function ( $vars ) {
            $vars[] = 'agentclerk_manifest';
            $vars[] = 'agentclerk_checkout';
            return $vars;
        } );
    }

    public function maybe_redirect_onboarding() {
        $status = get_option( 'agentclerk_plugin_status', 'onboarding' );
        $step   = (int) get_option( 'agentclerk_onboarding_step', 1 );

        if ( 'onboarding' === $status && $step < 6 ) {
            $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
            if ( $page && strpos( $page, 'agentclerk' ) !== false && $page !== 'agentclerk-onboarding' ) {
                wp_safe_redirect( admin_url( 'admin.php?page=agentclerk-onboarding' ) );
                exit;
            }
        }
    }

    /**
     * Helper: make authenticated request to AgentClerk backend.
     */
    public static function backend_request( $endpoint, $args = [] ) {
        $url    = AGENTCLERK_BACKEND_URL . $endpoint;
        $secret = get_option( 'agentclerk_install_secret', '' );

        $defaults = [
            'timeout' => 30,
            'headers' => [
                'Content-Type'        => 'application/json',
                'X-AgentClerk-Secret' => $secret,
                'X-AgentClerk-Site'   => get_site_url(),
            ],
        ];

        $args = wp_parse_args( $args, $defaults );
        if ( isset( $args['body'] ) && is_array( $args['body'] ) ) {
            $args['body'] = wp_json_encode( $args['body'] );
        }

        return wp_remote_request( $url, $args );
    }

    /**
     * AES-256 encrypt a value.
     */
    public static function encrypt( $value ) {
        $key    = wp_salt( 'auth' );
        $iv     = openssl_random_pseudo_bytes( 16 );
        $cipher = openssl_encrypt( $value, 'aes-256-cbc', $key, 0, $iv );
        return base64_encode( $iv . '::' . $cipher );
    }

    /**
     * AES-256 decrypt a value.
     */
    public static function decrypt( $value ) {
        $key  = wp_salt( 'auth' );
        $data = base64_decode( $value );
        $parts = explode( '::', $data, 2 );
        if ( count( $parts ) !== 2 ) {
            return false;
        }
        return openssl_decrypt( $parts[1], 'aes-256-cbc', $key, 0, $parts[0] );
    }
}

add_action( 'plugins_loaded', [ 'AgentClerk', 'instance' ] );
