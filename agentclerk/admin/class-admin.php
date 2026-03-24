<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AgentClerk Admin
 *
 * Central admin class that registers menus, enqueues assets,
 * renders views, and handles every wp_ajax_ action for the plugin.
 *
 * @package AgentClerk
 * @since   1.0.0
 */
class AgentClerk_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var AgentClerk_Admin|null
	 */
	private static $instance = null;

	/**
	 * Return the singleton instance.
	 *
	 * @return AgentClerk_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — registers all hooks.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX handlers.
		$ajax_actions = array(
			'register_install',
			'validate_api_key',
			'start_scan',
			'scan_progress',
			'save_onboarding_step',
			'save_agent_config',
			'save_catalog',
			'save_placement',
			'add_product',
			'go_live',
			'save_settings',
			'rescan',
			'get_conversations',
			'get_conversation_messages',
			'get_conversation_stats',
			'get_sales_data',
			'toggle_escalation_read',
			'send_plugin_support',
			'purchase_lifetime',
			'update_card',
			'activate_lifetime_license',
		);

		foreach ( $ajax_actions as $action ) {
			add_action( 'wp_ajax_agentclerk_' . $action, array( $this, $action ) );
		}
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 *
	 * @throws \Exception Always.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton.' );
	}

	/* ───────────────────────────────────────────────
	 *  Menus
	 * ─────────────────────────────────────────────── */

	/**
	 * Register admin menus.
	 *
	 * During onboarding only the main page and hidden setup page are shown.
	 * Once active the full set of submenus appears.
	 * If suspended the dashboard is replaced with the suspended view.
	 */
	public function register_menus() {
		$status = get_option( 'agentclerk_plugin_status', 'onboarding' );

		add_menu_page(
			'AgentClerk',
			'AgentClerk',
			'manage_options',
			'agentclerk',
			array( $this, 'render_dashboard' ),
			'dashicons-format-chat',
			56
		);

		// Onboarding — only the setup submenu.
		if ( 'onboarding' === $status ) {
			add_submenu_page(
				'agentclerk',
				'Setup',
				'Setup',
				'manage_options',
				'agentclerk-onboarding',
				array( $this, 'render_onboarding' )
			);
			return;
		}

		// Suspended — replace everything with the suspended view.
		if ( 'suspended' === $status ) {
			remove_submenu_page( 'agentclerk', 'agentclerk' );
			add_submenu_page(
				'agentclerk',
				'Suspended',
				'Account Suspended',
				'manage_options',
				'agentclerk',
				array( $this, 'render_suspended' )
			);
			return;
		}

		// Active — full navigation.
		if ( 'active' === $status ) {
			add_submenu_page( 'agentclerk', 'Dashboard', 'Dashboard', 'manage_options', 'agentclerk' );
			add_submenu_page( 'agentclerk', 'Conversations', 'Conversations', 'manage_options', 'agentclerk-conversations', array( $this, 'render_conversations' ) );
			add_submenu_page( 'agentclerk', 'Settings', 'Settings', 'manage_options', 'agentclerk-settings', array( $this, 'render_settings' ) );
			add_submenu_page( 'agentclerk', 'Sales', 'Sales', 'manage_options', 'agentclerk-sales', array( $this, 'render_sales' ) );
			add_submenu_page( 'agentclerk', 'AgentClerk Help', 'Support', 'manage_options', 'agentclerk-support', array( $this, 'render_support' ) );
		}
	}

	/* ───────────────────────────────────────────────
	 *  Assets
	 * ─────────────────────────────────────────────── */

	/**
	 * Enqueue styles and scripts on AgentClerk admin pages only.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'agentclerk' ) === false ) {
			return;
		}

		// Google Fonts: Syne, DM Sans, DM Mono.
		wp_enqueue_style(
			'agentclerk-fonts',
			'https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Syne:wght@400;500;600;700&family=DM+Sans:wght@300;400;500&display=swap',
			array(),
			null
		);

		// Admin CSS.
		wp_enqueue_style(
			'agentclerk-admin',
			AGENTCLERK_PLUGIN_URL . 'admin/css/admin.css',
			array( 'agentclerk-fonts' ),
			AGENTCLERK_VERSION
		);

		// Admin JS.
		wp_enqueue_script(
			'agentclerk-admin',
			AGENTCLERK_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			AGENTCLERK_VERSION,
			true
		);

		// Localized data.
		$status    = get_option( 'agentclerk_plugin_status', 'onboarding' );
		$tier      = get_option( 'agentclerk_tier', '' );
		$stripe_pk = get_option( 'agentclerk_stripe_publishable_key', '' );

		wp_localize_script( 'agentclerk-admin', 'agentclerk', array(
			'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
			'nonce'                => wp_create_nonce( 'agentclerk_nonce' ),
			'siteUrl'              => get_site_url(),
			'scanCache'            => json_decode( get_option( 'agentclerk_scan_cache', '{}' ), true ),
			'agentConfig'          => json_decode( get_option( 'agentclerk_agent_config', '{}' ), true ),
			'placement'            => json_decode( get_option( 'agentclerk_placement', '{}' ), true ),
			'pluginStatus'         => $status,
			'onboardingStep'       => (int) get_option( 'agentclerk_onboarding_step', 1 ),
			'tier'                 => $tier,
			'stripePublishableKey' => $stripe_pk,
			'billingStatus'        => get_option( 'agentclerk_billing_status', 'active' ),
			'licenseStatus'        => get_option( 'agentclerk_license_status', 'none' ),
			'accruedFees'          => (float) get_option( 'agentclerk_accrued_fees', 0 ),
			'billingCardLast4'     => get_option( 'agentclerk_billing_card_last4', '' ),
		) );

		// Stripe JS (when a publishable key is available).
		if ( $stripe_pk ) {
			wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', array(), null, true );
		}
	}

	/* ───────────────────────────────────────────────
	 *  View Renderers
	 * ─────────────────────────────────────────────── */

	/**
	 * Render the main dashboard.
	 */
	public function render_dashboard() {
		$status = get_option( 'agentclerk_plugin_status', 'onboarding' );

		if ( 'suspended' === $status ) {
			include AGENTCLERK_PLUGIN_DIR . 'admin/views/suspended.php';
			return;
		}

		if ( 'onboarding' === $status ) {
			$this->render_onboarding();
			return;
		}

		include AGENTCLERK_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render the onboarding wizard step.
	 */
	public function render_onboarding() {
		$step = (int) get_option( 'agentclerk_onboarding_step', 1 );
		$step = max( 1, min( 6, $step ) );
		include AGENTCLERK_PLUGIN_DIR . 'admin/views/onboarding/step-' . $step . '.php';
	}

	/**
	 * Render the conversations page.
	 */
	public function render_conversations() {
		include AGENTCLERK_PLUGIN_DIR . 'admin/views/conversations.php';
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings() {
		include AGENTCLERK_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Render the sales page.
	 */
	public function render_sales() {
		include AGENTCLERK_PLUGIN_DIR . 'admin/views/sales.php';
	}

	/**
	 * Render the support page.
	 */
	public function render_support() {
		include AGENTCLERK_PLUGIN_DIR . 'admin/views/support.php';
	}

	/**
	 * Render the suspended account page.
	 */
	public function render_suspended() {
		include AGENTCLERK_PLUGIN_DIR . 'admin/views/suspended.php';
	}

	/* ───────────────────────────────────────────────
	 *  AJAX Handlers
	 * ─────────────────────────────────────────────── */

	/**
	 * 1. Register a new install with the AgentClerk backend.
	 *
	 * Sends site_url, admin_email, tier, wp_version, wc_version, php_version.
	 * Saves install_secret and stripe_publishable_key from the response.
	 */
	public function register_install() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$tier       = isset( $_POST['tier'] ) ? sanitize_text_field( wp_unslash( $_POST['tier'] ) ) : '';
		$payment_id = isset( $_POST['stripe_payment_method_id'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_payment_method_id'] ) ) : '';
		$api_key    = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

		if ( ! in_array( $tier, array( 'byok', 'turnkey' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid tier.' ) );
		}

		// Encrypt and store the API key for BYOK tier.
		if ( 'byok' === $tier && ! empty( $api_key ) ) {
			update_option( 'agentclerk_api_key', AgentClerk::encrypt( $api_key ) );
		}

		$wc_version = defined( 'WC_VERSION' ) ? WC_VERSION : '';

		$body = array(
			'site_url'                 => get_site_url(),
			'admin_email'              => sanitize_email( get_option( 'admin_email' ) ),
			'tier'                     => $tier,
			'wp_version'               => get_bloginfo( 'version' ),
			'wc_version'               => $wc_version,
			'php_version'              => phpversion(),
			'stripe_payment_method_id' => $payment_id,
		);

		$response = AgentClerk::backend_request( '/installs', $body, 'POST' );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code   = $response['code'] ?? 0;
		$result = $response['body'] ?? array();

		if ( $code < 200 || $code >= 300 ) {
			wp_send_json_error( array( 'message' => 'Backend registration failed. Status: ' . $code ) );
		}

		if ( empty( $result['install_secret'] ) ) {
			wp_send_json_error( array( 'message' => 'Registration failed. No install secret returned.' ) );
		}

		update_option( 'agentclerk_install_secret', sanitize_text_field( $result['install_secret'] ) );
		update_option( 'agentclerk_tier', $tier );

		if ( ! empty( $result['stripe_publishable_key'] ) ) {
			update_option( 'agentclerk_stripe_publishable_key', sanitize_text_field( $result['stripe_publishable_key'] ) );
		}
		if ( ! empty( $result['stripe_customer_id'] ) ) {
			update_option( 'agentclerk_stripe_customer_id', sanitize_text_field( $result['stripe_customer_id'] ) );
		}
		if ( ! empty( $result['card_last4'] ) ) {
			update_option( 'agentclerk_billing_card_last4', sanitize_text_field( $result['card_last4'] ) );
		}

		// Turnkey tier — redirect to Stripe checkout.
		if ( 'turnkey' === $tier ) {
			$checkout_data = array(
				'successUrl' => admin_url( 'admin.php?page=agentclerk&step=2&turnkey_success=1' ),
				'cancelUrl'  => admin_url( 'admin.php?page=agentclerk&step=1&turnkey_cancelled=1' ),
			);

			$checkout = AgentClerk::backend_request( '/billing/turnkey-checkout', $checkout_data, 'POST' );

			if ( ! is_wp_error( $checkout ) ) {
				$checkout_body = $checkout['body'] ?? array();
				if ( ! empty( $checkout_body['checkoutUrl'] ) ) {
					wp_send_json_success( array( 'redirect' => $checkout_body['checkoutUrl'] ) );
					return;
				}
			}
		}

		update_option( 'agentclerk_onboarding_step', 2 );
		wp_send_json_success( array( 'step' => 2 ) );
	}

	/**
	 * 2. Validate an Anthropic API key by sending a lightweight test request.
	 *
	 * On success encrypts and saves the key.
	 */
	public function validate_api_key() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => 'API key is required.' ) );
		}

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'timeout' => 15,
			'headers' => array(
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			),
			'body' => wp_json_encode( array(
				'model'      => 'claude-sonnet-4-20250514',
				'max_tokens' => 10,
				'messages'   => array( array( 'role' => 'user', 'content' => 'Hi' ) ),
			) ),
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => 'Connection failed: ' . $response->get_error_message() ) );
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $code ) {
			update_option( 'agentclerk_api_key', AgentClerk::encrypt( $api_key ) );
			wp_send_json_success( array( 'message' => 'API key is valid.' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Invalid API key. Status: ' . $code ) );
		}
	}

	/**
	 * 3. Trigger a site scan via AgentClerk_Scanner.
	 */
	public function start_scan() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$results = AgentClerk_Scanner::start_scan();
		update_option( 'agentclerk_onboarding_step', 3 );
		wp_send_json_success( $results );
	}

	/**
	 * 4. Return current scan progress from transient.
	 */
	public function scan_progress() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$progress = get_transient( 'agentclerk_scan_progress' );
		wp_send_json_success( $progress ? $progress : array( 'status' => 'idle' ) );
	}

	/**
	 * 5. Save current onboarding step data and advance the step number.
	 */
	public function save_onboarding_step() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$step = isset( $_POST['step'] ) ? (int) $_POST['step'] : 0;

		if ( $step < 1 || $step > 6 ) {
			wp_send_json_error( array( 'message' => 'Invalid step.' ) );
		}

		// Persist any step-specific data payload.
		if ( isset( $_POST['data'] ) ) {
			$raw = wp_unslash( $_POST['data'] );
			if ( is_string( $raw ) ) {
				$raw = json_decode( $raw, true );
			}
			if ( is_array( $raw ) ) {
				$config = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
				if ( ! is_array( $config ) ) {
					$config = array();
				}
				foreach ( $raw as $key => $value ) {
					$safe_key = sanitize_text_field( $key );
					if ( is_array( $value ) ) {
						$config[ $safe_key ] = array_map( 'sanitize_text_field', $value );
					} else {
						$config[ $safe_key ] = sanitize_textarea_field( $value );
					}
				}
				update_option( 'agentclerk_agent_config', wp_json_encode( $config ) );
			}
		}

		update_option( 'agentclerk_onboarding_step', $step );
		wp_send_json_success( array( 'step' => $step ) );
	}

	/**
	 * 6. Save agent configuration (sanitize all fields).
	 */
	public function save_agent_config() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$config = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		if ( ! is_array( $config ) ) {
			$config = array();
		}

		// Text / textarea fields.
		$text_fields = array( 'agent_name', 'business_name', 'business_desc', 'support_file', 'escalation_message' );
		foreach ( $text_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				$config[ $field ] = sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) );
			}
		}

		// Escalation email.
		if ( isset( $_POST['escalation_email'] ) ) {
			$config['escalation_email'] = sanitize_email( wp_unslash( $_POST['escalation_email'] ) );
		}

		// Escalation topics (JSON array).
		if ( isset( $_POST['escalation_topics'] ) ) {
			$topics = wp_unslash( $_POST['escalation_topics'] );
			if ( is_string( $topics ) ) {
				$topics = json_decode( $topics, true );
			}
			$config['escalation_topics'] = is_array( $topics ) ? array_map( 'sanitize_text_field', $topics ) : array();
		}

		// Policies (nested object).
		if ( isset( $_POST['policies'] ) ) {
			$policies = wp_unslash( $_POST['policies'] );
			if ( is_string( $policies ) ) {
				$policies = json_decode( $policies, true );
			}
			if ( is_array( $policies ) ) {
				$config['policies'] = array(
					'refund'   => sanitize_textarea_field( $policies['refund'] ?? '' ),
					'license'  => sanitize_textarea_field( $policies['license'] ?? '' ),
					'delivery' => sanitize_textarea_field( $policies['delivery'] ?? '' ),
				);
			}
		}

		// Support page ID.
		if ( isset( $_POST['support_page_id'] ) ) {
			$config['support_page_id'] = absint( $_POST['support_page_id'] );
		}

		update_option( 'agentclerk_agent_config', wp_json_encode( $config ) );
		delete_transient( 'agentclerk_manifest_cache' );

		wp_send_json_success( array( 'message' => 'Agent configuration saved.' ) );
	}

	/**
	 * 7. Save product visibility settings in agent_config.
	 */
	public function save_catalog() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$visibility = isset( $_POST['visibility'] ) ? json_decode( wp_unslash( $_POST['visibility'] ), true ) : array();
		if ( ! is_array( $visibility ) ) {
			$visibility = array();
		}

		$config = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		if ( ! is_array( $config ) ) {
			$config = array();
		}

		$config['product_visibility'] = array_map( function ( $v ) {
			return (bool) $v;
		}, $visibility );

		update_option( 'agentclerk_agent_config', wp_json_encode( $config ) );
		delete_transient( 'agentclerk_manifest_cache' );

		wp_send_json_success( array( 'message' => 'Catalog saved.' ) );
	}

	/**
	 * 8. Save placement option.
	 */
	public function save_placement() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$placement = array(
			'widget'       => ! empty( $_POST['widget'] ),
			'product_page' => ! empty( $_POST['product_page'] ),
			'clerk_page'   => ! empty( $_POST['clerk_page'] ),
			'button_label' => isset( $_POST['button_label'] ) ? sanitize_text_field( wp_unslash( $_POST['button_label'] ) ) : 'Get Help',
			'agent_name'   => isset( $_POST['agent_name'] ) ? sanitize_text_field( wp_unslash( $_POST['agent_name'] ) ) : 'AgentClerk',
			'position'     => isset( $_POST['position'] ) ? sanitize_text_field( wp_unslash( $_POST['position'] ) ) : 'bottom-right',
		);

		update_option( 'agentclerk_placement', wp_json_encode( $placement ) );

		// Auto-create the /clerk page when the option is toggled on.
		$existing = get_option( 'agentclerk_clerk_page_id' );
		if ( $placement['clerk_page'] && ( ! $existing || ! get_post( $existing ) ) ) {
			$page_id = wp_insert_post( array(
				'post_title'   => 'Clerk',
				'post_name'    => 'clerk',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => '<!-- AgentClerk full-page chat -->',
			) );
			if ( $page_id && ! is_wp_error( $page_id ) ) {
				update_option( 'agentclerk_clerk_page_id', $page_id );
			}
		}

		flush_rewrite_rules();
		wp_send_json_success( array( 'message' => 'Placement saved.' ) );
	}

	/**
	 * 9. Add a custom product to the WooCommerce catalog.
	 */
	public function add_product() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		if ( ! function_exists( 'wc_get_product' ) ) {
			wp_send_json_error( array( 'message' => 'WooCommerce is not active.' ) );
		}

		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$price = isset( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0;
		$type  = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'simple';
		$desc  = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

		if ( empty( $name ) ) {
			wp_send_json_error( array( 'message' => 'Product name is required.' ) );
		}

		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_regular_price( $price );
		$product->set_short_description( $desc );
		$product->set_status( 'publish' );
		$product->save();

		wp_send_json_success( array( 'product_id' => $product->get_id() ) );
	}

	/**
	 * 10. Go live — set plugin status to active and send install summary to backend.
	 *
	 * Builds quality scores (context, catalog, policy, support_file completeness),
	 * friction observations, and POSTs the full configuration summary.
	 */
	public function go_live() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		update_option( 'agentclerk_plugin_status', 'active' );
		update_option( 'agentclerk_onboarding_step', 6 );

		$config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		$placement  = json_decode( get_option( 'agentclerk_placement', '{}' ), true );
		$scan_cache = json_decode( get_option( 'agentclerk_scan_cache', '{}' ), true );

		if ( ! is_array( $config ) ) {
			$config = array();
		}
		if ( ! is_array( $placement ) ) {
			$placement = array();
		}
		if ( ! is_array( $scan_cache ) ) {
			$scan_cache = array();
		}

		// Compute quality scores.
		$context_completeness      = $this->compute_context_completeness( $config );
		$catalog_completeness      = $this->compute_catalog_completeness( $config, $scan_cache );
		$policy_completeness       = $this->compute_policy_completeness( $config );
		$support_file_completeness = $this->compute_support_file_completeness( $config );

		// Build friction observations.
		$friction_observations = $this->build_friction_observations( $config, $scan_cache );

		// Business overview.
		$business_overview = array(
			'business_name' => $config['business_name'] ?? '',
			'business_desc' => $config['business_desc'] ?? '',
			'agent_name'    => $config['agent_name'] ?? '',
		);

		// Product catalog.
		$products = $scan_cache['products'] ?? array();

		// Post summary to backend.
		AgentClerk::backend_request( '/installs/summary', array(
			'business_overview'     => $business_overview,
			'product_catalog'       => $products,
			'config'                => $config,
			'placement'             => $placement,
			'quality_scores'        => array(
				'context_completeness'      => $context_completeness,
				'catalog_completeness'      => $catalog_completeness,
				'policy_completeness'       => $policy_completeness,
				'support_file_completeness' => $support_file_completeness,
			),
			'friction_observations' => $friction_observations,
			'tier'                  => get_option( 'agentclerk_tier', '' ),
		), 'POST' );

		// Schedule billing status poll.
		if ( ! wp_next_scheduled( 'agentclerk_poll_billing_status' ) ) {
			wp_schedule_event( time(), 'hourly', 'agentclerk_poll_billing_status' );
		}

		wp_send_json_success( array( 'redirect' => admin_url( 'admin.php?page=agentclerk' ) ) );
	}

	/**
	 * 11. Save settings for any tab.
	 *
	 * Delegates to the appropriate handler based on the `tab` parameter.
	 */
	public function save_settings() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$tab = isset( $_POST['tab'] ) ? sanitize_text_field( wp_unslash( $_POST['tab'] ) ) : '';

		switch ( $tab ) {
			case 'business':
				$this->save_agent_config();
				break;

			case 'catalog':
				$this->save_catalog();
				break;

			case 'placement':
				$this->save_placement();
				break;

			case 'api_key':
				$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
				if ( ! empty( $api_key ) ) {
					update_option( 'agentclerk_api_key', AgentClerk::encrypt( $api_key ) );
				}
				wp_send_json_success( array( 'message' => 'API key updated.' ) );
				break;

			case 'support':
				$this->save_agent_config();
				break;

			default:
				$this->save_agent_config();
				break;
		}
	}

	/**
	 * 12. Trigger a new site scan (rescan).
	 */
	public function rescan() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$results = AgentClerk_Scanner::start_scan();
		wp_send_json_success( $results );
	}

	/**
	 * 13. Return paginated conversations with optional filters.
	 */
	public function get_conversations() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		global $wpdb;
		$table  = $wpdb->prefix . 'agentclerk_conversations';
		$filter = isset( $_GET['outcome'] ) ? sanitize_text_field( wp_unslash( $_GET['outcome'] ) ) : '';
		$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$page   = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 );
		$limit  = 20;
		$offset = ( $page - 1 ) * $limit;

		$where = '1=1';
		$args  = array();

		if ( $filter ) {
			$where .= ' AND outcome = %s';
			$args[] = $filter;
		}

		if ( $search ) {
			$messages_table = $wpdb->prefix . 'agentclerk_messages';
			$where         .= " AND id IN ( SELECT conversation_id FROM {$messages_table} WHERE content LIKE %s )";
			$args[]         = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$count_args = $args;

		$args[] = $limit;
		$args[] = $offset;

		$query = "SELECT * FROM {$table} WHERE {$where} ORDER BY updated_at DESC LIMIT %d OFFSET %d";
		if ( ! empty( $args ) ) {
			$rows = $wpdb->get_results( $wpdb->prepare( $query, ...$args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$rows = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$count_query = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		if ( ! empty( $count_args ) ) {
			$total = (int) $wpdb->get_var( $wpdb->prepare( $count_query, ...$count_args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$total = (int) $wpdb->get_var( $count_query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		wp_send_json_success( array(
			'conversations' => $rows,
			'total'         => $total,
			'page'          => $page,
			'pages'         => (int) ceil( $total / $limit ),
		) );
	}

	/**
	 * 14. Return messages for a conversation.
	 */
	public function get_conversation_messages() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$conversation_id = isset( $_GET['conversation_id'] ) ? absint( $_GET['conversation_id'] ) : 0;
		if ( ! $conversation_id ) {
			wp_send_json_error( array( 'message' => 'Missing conversation_id.' ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'agentclerk_messages';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content, created_at FROM {$table} WHERE conversation_id = %d ORDER BY created_at ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$conversation_id
			)
		);

		wp_send_json_success( array( 'messages' => $rows ) );
	}

	/**
	 * 15. Return conversation statistics.
	 */
	public function get_conversation_stats() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'agentclerk_conversations';
		$today = current_time( 'Y-m-d' );

		$total     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$today_ct  = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE DATE(started_at) = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$today
		) );
		$setup     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE outcome = 'setup'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$support   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE outcome = 'support'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$quote     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE outcome = 'quote'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$escalated = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE outcome = 'escalated'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$purchased = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE outcome = 'purchased'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$sales_today = $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(sale_amount), 0) FROM {$table} WHERE outcome = 'purchased' AND DATE(updated_at) = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$today
		) );

		wp_send_json_success( array(
			'total'       => $total,
			'today'       => $today_ct,
			'setup'       => $setup,
			'support'     => $support,
			'in_cart'     => $quote,
			'escalated'   => $escalated,
			'purchased'   => $purchased,
			'sales_today' => (float) $sales_today,
		) );
	}

	/**
	 * 16. Return sales data for a given period.
	 */
	public function get_sales_data() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		global $wpdb;
		$table  = $wpdb->prefix . 'agentclerk_conversations';
		$period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : 'all';

		$where = "outcome = 'purchased'";
		if ( 'month' === $period ) {
			$where .= $wpdb->prepare(
				' AND updated_at >= %s',
				gmdate( 'Y-m-01 00:00:00' )
			);
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built safely above.
		$gross = (float) $wpdb->get_var( "SELECT COALESCE(SUM(sale_amount), 0) FROM {$table} WHERE {$where}" );
		$fees  = (float) $wpdb->get_var( "SELECT COALESCE(SUM(acclerk_fee), 0) FROM {$table} WHERE {$where}" );
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
		$avg   = $count > 0 ? $gross / $count : 0;

		$transactions = $wpdb->get_results(
			"SELECT id, session_id, sale_amount, acclerk_fee, updated_at FROM {$table} WHERE {$where} ORDER BY updated_at DESC LIMIT 50"
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		wp_send_json_success( array(
			'gross'        => $gross,
			'fees'         => $fees,
			'count'        => $count,
			'average'      => round( $avg, 2 ),
			'accrued_fees' => (float) get_option( 'agentclerk_accrued_fees', 0 ),
			'transactions' => $transactions,
		) );
	}

	/**
	 * 17. Toggle read/unread on an escalation notification.
	 */
	public function toggle_escalation_read() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;
		if ( ! $conversation_id ) {
			wp_send_json_error( array( 'message' => 'Missing conversation_id.' ) );
		}

		$notif_key = 'agentclerk_escalation_' . $conversation_id;
		$notif     = get_user_meta( get_current_user_id(), $notif_key, true );

		if ( $notif && is_array( $notif ) ) {
			$notif['read'] = ! $notif['read'];
			update_user_meta( get_current_user_id(), $notif_key, $notif );
			wp_send_json_success( array( 'read' => $notif['read'] ) );
		}

		wp_send_json_success();
	}

	/**
	 * 18. Send a message to plugin support chat via the backend.
	 */
	public function send_plugin_support() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$history = isset( $_POST['history'] ) ? json_decode( wp_unslash( $_POST['history'] ), true ) : array();

		if ( empty( $message ) ) {
			wp_send_json_error( array( 'message' => 'Message is required.' ) );
		}

		if ( ! is_array( $history ) ) {
			$history = array();
		}

		// Sanitize history entries.
		$history = array_map( function ( $msg ) {
			return array(
				'role'    => sanitize_text_field( $msg['role'] ?? 'user' ),
				'content' => sanitize_textarea_field( $msg['content'] ?? '' ),
			);
		}, $history );

		$history[] = array( 'role' => 'user', 'content' => $message );

		$system = 'You are the AgentClerk plugin support assistant. Help the seller with questions about configuring and using the AgentClerk WordPress plugin. Do not answer questions about the seller\'s own products or customers — only about AgentClerk plugin functionality.';

		$response = AgentClerk::backend_request( '/agent/chat', array(
			'system'   => $system,
			'messages' => $history,
		), 'POST' );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code = $response['code'] ?? 0;
		if ( $code < 200 || $code >= 300 ) {
			wp_send_json_error( array( 'message' => 'Support service unavailable. Status: ' . $code ) );
		}

		$body = $response['body'] ?? array();
		$text = $body['content'][0]['text'] ?? $body['message'] ?? '';

		wp_send_json_success( array( 'message' => $text ) );
	}

	/**
	 * 19. Initiate Stripe checkout for lifetime license purchase.
	 */
	public function purchase_lifetime() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$response = AgentClerk::backend_request( '/license/checkout', array(
			'successUrl' => admin_url( 'admin.php?page=agentclerk-sales&license_success=1&nonce=' . wp_create_nonce( 'agentclerk_license' ) ),
			'cancelUrl'  => admin_url( 'admin.php?page=agentclerk-sales' ),
		), 'POST' );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code = $response['code'] ?? 0;
		if ( $code < 200 || $code >= 300 ) {
			wp_send_json_error( array( 'message' => 'Could not create checkout session. Status: ' . $code ) );
		}

		$data = $response['body'] ?? array();

		if ( empty( $data['checkoutUrl'] ) ) {
			wp_send_json_error( array( 'message' => 'No checkout URL returned.' ) );
		}

		wp_send_json_success( array( 'checkoutUrl' => $data['checkoutUrl'] ) );
	}

	/**
	 * 20. Initiate Stripe card update flow.
	 */
	public function update_card() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$response = AgentClerk::backend_request( '/billing/card-update', array(
			'returnUrl' => admin_url( 'admin.php?page=agentclerk-sales' ),
		), 'POST' );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code = $response['code'] ?? 0;
		if ( $code < 200 || $code >= 300 ) {
			wp_send_json_error( array( 'message' => 'Could not initiate card update. Status: ' . $code ) );
		}

		$data = $response['body'] ?? array();

		if ( empty( $data['portalUrl'] ) ) {
			wp_send_json_error( array( 'message' => 'No portal URL returned.' ) );
		}

		wp_send_json_success( array( 'portalUrl' => $data['portalUrl'] ) );
	}

	/**
	 * 21. Activate a lifetime license after Stripe payment.
	 */
	public function activate_lifetime_license() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$response = AgentClerk::backend_request( '/license/activate', array(), 'POST' );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code = $response['code'] ?? 0;
		if ( $code < 200 || $code >= 300 ) {
			wp_send_json_error( array( 'message' => 'License activation failed. Status: ' . $code ) );
		}

		$data = $response['body'] ?? array();

		if ( ! empty( $data['licenseKey'] ) ) {
			update_option( 'agentclerk_license_status', 'active' );
			update_option( 'agentclerk_license_key', sanitize_text_field( $data['licenseKey'] ) );
			update_option( 'agentclerk_accrued_fees', 0 );

			wp_send_json_success( array(
				'message'       => 'Lifetime license activated. No more transaction fees.',
				'licenseStatus' => 'active',
			) );
		}

		wp_send_json_error( array( 'message' => 'License activation failed. No license key returned.' ) );
	}

	/* ───────────────────────────────────────────────
	 *  Quality Score Computation (for go_live)
	 * ─────────────────────────────────────────────── */

	/**
	 * Context completeness: how much business context is filled in.
	 *
	 * Checks: business_name, business_desc, agent_name, escalation_email,
	 *         escalation_message, escalation_topics.
	 *
	 * @param array $config Agent configuration.
	 * @return float 0-100.
	 */
	private function compute_context_completeness( $config ) {
		$fields = array( 'business_name', 'business_desc', 'agent_name', 'escalation_email', 'escalation_message' );
		$filled = 0;
		$total  = count( $fields ) + 1; // +1 for escalation_topics.

		foreach ( $fields as $field ) {
			if ( ! empty( $config[ $field ] ) ) {
				$filled++;
			}
		}

		$topics = $config['escalation_topics'] ?? array();
		if ( ! empty( $topics ) && is_array( $topics ) && count( $topics ) > 0 ) {
			$filled++;
		}

		return round( ( $filled / $total ) * 100, 1 );
	}

	/**
	 * Catalog completeness: fraction of visible products with name, price, and description.
	 *
	 * @param array $config     Agent configuration.
	 * @param array $scan_cache Scan cache.
	 * @return float 0-100.
	 */
	private function compute_catalog_completeness( $config, $scan_cache ) {
		$products = $scan_cache['products'] ?? array();

		if ( empty( $products ) ) {
			return 0;
		}

		$visibility    = $config['product_visibility'] ?? array();
		$complete      = 0;
		$visible_count = 0;

		foreach ( $products as $product ) {
			$pid     = $product['id'] ?? 0;
			$visible = isset( $visibility[ $pid ] ) ? (bool) $visibility[ $pid ] : true;

			if ( ! $visible ) {
				continue;
			}

			$visible_count++;

			$has_name  = ! empty( $product['name'] );
			$has_price = ! empty( $product['price'] ) && (float) $product['price'] > 0;
			$has_desc  = ! empty( $product['description'] );

			if ( $has_name && $has_price && $has_desc ) {
				$complete++;
			}
		}

		if ( 0 === $visible_count ) {
			return 0;
		}

		return round( ( $complete / $visible_count ) * 100, 1 );
	}

	/**
	 * Policy completeness: fraction of policy fields (refund, license, delivery) that are populated.
	 *
	 * @param array $config Agent configuration.
	 * @return float 0-100.
	 */
	private function compute_policy_completeness( $config ) {
		$policies = $config['policies'] ?? array();
		$fields   = array( 'refund', 'license', 'delivery' );
		$filled   = 0;

		foreach ( $fields as $field ) {
			if ( ! empty( $policies[ $field ] ) ) {
				$filled++;
			}
		}

		return round( ( $filled / count( $fields ) ) * 100, 1 );
	}

	/**
	 * Support file completeness: whether a support knowledge base is provided and its depth.
	 *
	 * @param array $config Agent configuration.
	 * @return float 0, 50, or 100.
	 */
	private function compute_support_file_completeness( $config ) {
		$support_file = $config['support_file'] ?? '';

		if ( empty( trim( $support_file ) ) ) {
			return 0;
		}

		// Partial credit for short files, full credit for substantial content.
		if ( mb_strlen( $support_file ) < 200 ) {
			return 50.0;
		}

		return 100.0;
	}

	/**
	 * Build friction observations from config gaps and scan gaps.
	 *
	 * @param array $config     Agent configuration.
	 * @param array $scan_cache Scan cache.
	 * @return array List of friction observation strings.
	 */
	private function build_friction_observations( $config, $scan_cache ) {
		$observations = array();

		// Include scan gaps.
		$gaps = $scan_cache['gaps'] ?? array();
		foreach ( $gaps as $gap ) {
			$observations[] = sanitize_text_field( $gap );
		}

		// Missing business description.
		if ( empty( $config['business_desc'] ) ) {
			$observations[] = 'No business description provided.';
		}

		// Missing escalation email.
		if ( empty( $config['escalation_email'] ) ) {
			$observations[] = 'No escalation email configured.';
		}

		// Missing support file.
		if ( empty( $config['support_file'] ) ) {
			$observations[] = 'No custom support knowledge base provided.';
		}

		// Empty product catalog.
		$products = $scan_cache['products'] ?? array();
		if ( empty( $products ) ) {
			$observations[] = 'No products found in WooCommerce catalog.';
		}

		// Products missing descriptions.
		$no_desc_count = 0;
		foreach ( $products as $product ) {
			if ( empty( $product['description'] ) ) {
				$no_desc_count++;
			}
		}
		if ( $no_desc_count > 0 ) {
			$observations[] = $no_desc_count . ' product(s) missing a description.';
		}

		return $observations;
	}
}
