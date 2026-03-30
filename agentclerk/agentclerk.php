<?php
/**
 * Plugin Name: AgentClerk
 * Plugin URI: https://agentclerk.io
 * Description: AI seller agent for WooCommerce. Answers buyers, closes sales, handles support automatically.
 * Version: 1.2.1
 * Author: AgentClerk
 * Author URI: https://agentclerk.io
 * License: GPL-2.0+
 * Text Domain: agentclerk
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AGENTCLERK_VERSION', '1.2.1' );
define( 'AGENTCLERK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AGENTCLERK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AGENTCLERK_BACKEND_URL', 'https://app.agentclerk.io/api' );

/**
 * Main AgentClerk plugin class.
 *
 * @since 1.0.0
 */
final class AgentClerk {

	/**
	 * Singleton instance.
	 *
	 * @var AgentClerk|null
	 */
	private static $instance = null;

	/**
	 * Return the singleton instance.
	 *
	 * @return AgentClerk
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Loads includes and registers hooks.
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Load required files.
	 */
	private function includes() {
		require_once AGENTCLERK_PLUGIN_DIR . 'includes/class-activator.php';
		require_once AGENTCLERK_PLUGIN_DIR . 'includes/class-scanner.php';
		require_once AGENTCLERK_PLUGIN_DIR . 'includes/class-agent.php';
		require_once AGENTCLERK_PLUGIN_DIR . 'includes/class-manifest.php';
		require_once AGENTCLERK_PLUGIN_DIR . 'includes/class-billing.php';
		require_once AGENTCLERK_PLUGIN_DIR . 'includes/class-woocommerce.php';
		require_once AGENTCLERK_PLUGIN_DIR . 'includes/class-conversations.php';
		require_once AGENTCLERK_PLUGIN_DIR . 'includes/class-support.php';
		require_once AGENTCLERK_PLUGIN_DIR . 'includes/class-a2a.php';

		if ( is_admin() ) {
			require_once AGENTCLERK_PLUGIN_DIR . 'admin/class-admin.php';
		}

		require_once AGENTCLERK_PLUGIN_DIR . 'public/class-widget.php';
	}

	/**
	 * Register hooks and initialize components.
	 */
	private function init_hooks() {
		AgentClerk_Activator::maybe_update_db();

		add_action( 'init', array( $this, 'register_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_action( 'template_redirect', array( $this, 'handle_redirects' ) );

		// Initialize components.
		AgentClerk_Scanner::instance();
		AgentClerk_Agent::instance();
		AgentClerk_Manifest::instance();
		AgentClerk_Billing::instance();
		AgentClerk_WooCommerce::instance();
		AgentClerk_Conversations::instance();
		AgentClerk_Support::instance();

		if ( is_admin() ) {
			AgentClerk_Admin::instance();
		}

		AgentClerk_Widget::instance();
		AgentClerk_A2A::instance();
	}

	/**
	 * Register rewrite rules for manifest and checkout endpoints.
	 */
	public function register_rewrite_rules() {
		add_rewrite_rule( '^ai-manifest\.json$', 'index.php?agentclerk_manifest=1', 'top' );
		add_rewrite_rule( '^clerk-checkout/([a-zA-Z0-9]+)/?$', 'index.php?agentclerk_checkout=$matches[1]', 'top' );

		// A2A protocol endpoints.
		add_rewrite_rule( '^\.well-known/agent-card\.json$', 'index.php?agentclerk_a2a_card=1', 'top' );
		add_rewrite_rule( '^a2a/message:send$', 'index.php?agentclerk_a2a_send=send', 'top' );
		add_rewrite_rule( '^a2a/message:stream$', 'index.php?agentclerk_a2a_send=stream', 'top' );
		add_rewrite_rule( '^a2a/tasks/?$', 'index.php?agentclerk_a2a_tasks=1', 'top' );
		add_rewrite_rule( '^a2a/tasks/([a-zA-Z0-9-]+)(/pushNotificationConfigs.*)?$', 'index.php?agentclerk_a2a_task=$matches[1]', 'top' );
		add_rewrite_rule( '^a2a/tasks/([a-zA-Z0-9-]+):cancel$', 'index.php?agentclerk_a2a_task=$matches[1]', 'top' );
		add_rewrite_rule( '^a2a/tasks/([a-zA-Z0-9-]+):subscribe$', 'index.php?agentclerk_a2a_task=$matches[1]', 'top' );
	}

	/**
	 * Register custom query vars.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'agentclerk_manifest';
		$vars[] = 'agentclerk_checkout';
		$vars[] = 'agentclerk_a2a_card';
		$vars[] = 'agentclerk_a2a_send';
		$vars[] = 'agentclerk_a2a_task';
		$vars[] = 'agentclerk_a2a_tasks';
		$vars[] = 'agentclerk_a2a_push';
		return $vars;
	}

	/**
	 * Handle redirects (e.g. onboarding enforcement).
	 */
	public function handle_redirects() {
		if ( is_admin() ) {
			$status = get_option( 'agentclerk_plugin_status', 'onboarding' );
			$screen = get_current_screen();
			if ( $screen && 'onboarding' === $status
				&& strpos( $screen->id, 'agentclerk' ) !== false
				&& 'toplevel_page_agentclerk' !== $screen->id
			) {
				wp_safe_redirect( admin_url( 'admin.php?page=agentclerk' ) );
				exit;
			}
		}
	}

	/**
	 * Make an authenticated request to the AgentClerk backend.
	 *
	 * Uses X-AgentClerk-Secret and X-AgentClerk-Site headers for authentication.
	 * Returns the raw wp_remote_request() response (WP_Error or response array).
	 *
	 * @param string $endpoint API endpoint (relative).
	 * @param array  $args     wp_remote_request() args (method, body, etc.).
	 * @return array|WP_Error Raw wp_remote_request() response.
	 */
	public static function backend_request( $endpoint, $args = array() ) {
		$install_secret = get_option( 'agentclerk_install_secret', '' );

		$args = wp_parse_args( $args, array(
			'method'  => 'POST',
			'headers' => array(),
			'timeout' => 30,
		) );

		$args['headers'] = array_merge( $args['headers'], array(
			'Content-Type'        => 'application/json',
			'X-AgentClerk-Secret' => $install_secret,
			'X-AgentClerk-Site'   => get_site_url(),
		) );

		if ( isset( $args['body'] ) && is_array( $args['body'] ) ) {
			$args['body'] = wp_json_encode( $args['body'] );
		}

		return wp_remote_request( AGENTCLERK_BACKEND_URL . '/' . ltrim( $endpoint, '/' ), $args );
	}

	/**
	 * AES-256 encrypt a value using the WordPress auth salt.
	 *
	 * @param string $value Plaintext value.
	 * @return string Base64-encoded encrypted value.
	 */
	public static function encrypt( $value ) {
		$key    = wp_salt( 'auth' );
		$iv     = openssl_random_pseudo_bytes( 16 );
		$cipher = openssl_encrypt( $value, 'aes-256-cbc', $key, 0, $iv );
		return base64_encode( $iv . '::' . $cipher );
	}

	/**
	 * AES-256 decrypt a value.
	 *
	 * @param string $value Base64-encoded encrypted value.
	 * @return string|false Decrypted value or false on failure.
	 */
	public static function decrypt( $value ) {
		$key   = wp_salt( 'auth' );
		$parts = explode( '::', base64_decode( $value ), 2 );
		if ( count( $parts ) !== 2 ) {
			return false;
		}
		return openssl_decrypt( $parts[1], 'aes-256-cbc', $key, 0, $parts[0] );
	}
}

// Load activator early so it's available for activation hook.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-activator.php';

register_activation_hook( __FILE__, array( 'AgentClerk_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AgentClerk_Activator', 'deactivate' ) );

/**
 * Return the main AgentClerk instance.
 *
 * @return AgentClerk
 */
function agentclerk() {
	return AgentClerk::instance();
}
add_action( 'plugins_loaded', 'agentclerk' );
