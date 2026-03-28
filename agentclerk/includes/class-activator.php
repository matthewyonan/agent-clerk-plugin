<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin activation and deactivation handler.
 *
 * Creates database tables, sets default options, creates the /clerk page,
 * registers rewrite rules, and schedules cron events.
 *
 * @since 1.0.0
 */
class AgentClerk_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate() {
		self::create_tables();
		self::set_defaults();
		self::create_clerk_page();
		self::add_rewrite_rules();
		self::schedule_crons();
		flush_rewrite_rules();
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'agentclerk_poll_billing_status' );
		wp_clear_scheduled_hook( 'agentclerk_expire_sessions' );
		flush_rewrite_rules();
	}

	/**
	 * Create the three plugin database tables.
	 */
	private static function create_tables() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$conversations = $wpdb->prefix . 'agentclerk_conversations';
		$messages      = $wpdb->prefix . 'agentclerk_messages';
		$quote_links   = $wpdb->prefix . 'agentclerk_quote_links';

		$sql = "CREATE TABLE {$conversations} (
			id BIGINT NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(64) NOT NULL,
			buyer_type ENUM('human','agent') NOT NULL DEFAULT 'human',
			first_message TEXT,
			product_name VARCHAR(255) DEFAULT NULL,
			product_ids LONGTEXT,
			outcome ENUM('browsing','quote','purchased','setup','support','abandoned','escalated') NOT NULL DEFAULT 'browsing',
			quote_link_id VARCHAR(64) DEFAULT NULL,
			sale_amount DECIMAL(10,2) DEFAULT NULL,
			acclerk_fee DECIMAL(10,2) DEFAULT NULL,
			started_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY session_id (session_id)
		) {$charset};

		CREATE TABLE {$messages} (
			id BIGINT NOT NULL AUTO_INCREMENT,
			conversation_id BIGINT NOT NULL,
			role ENUM('user','assistant') NOT NULL,
			content LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY conversation_id (conversation_id)
		) {$charset};

		CREATE TABLE {$quote_links} (
			id VARCHAR(64) NOT NULL,
			conversation_id BIGINT NOT NULL,
			product_id BIGINT NOT NULL,
			product_name VARCHAR(255) DEFAULT NULL,
			amount DECIMAL(10,2) NOT NULL,
			wc_order_id BIGINT DEFAULT NULL,
			status ENUM('pending','completed','expired') NOT NULL DEFAULT 'pending',
			expires_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY conversation_id (conversation_id)
		) {$charset};";

		// A2A protocol tables.
		$a2a_tasks     = $wpdb->prefix . 'agentclerk_a2a_tasks';
		$a2a_messages  = $wpdb->prefix . 'agentclerk_a2a_task_messages';
		$a2a_artifacts = $wpdb->prefix . 'agentclerk_a2a_task_artifacts';

		$sql .= "CREATE TABLE {$a2a_tasks} (
			task_id VARCHAR(64) NOT NULL,
			context_id VARCHAR(128) DEFAULT NULL,
			session_id VARCHAR(128) NOT NULL,
			status VARCHAR(40) NOT NULL DEFAULT 'TASK_STATE_SUBMITTED',
			error_msg TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (task_id),
			KEY context_id (context_id)
		) {$charset};

		CREATE TABLE {$a2a_messages} (
			id BIGINT NOT NULL AUTO_INCREMENT,
			task_id VARCHAR(64) NOT NULL,
			message_id VARCHAR(64) NOT NULL,
			role VARCHAR(20) NOT NULL,
			content LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY task_id (task_id)
		) {$charset};

		CREATE TABLE {$a2a_artifacts} (
			id BIGINT NOT NULL AUTO_INCREMENT,
			task_id VARCHAR(64) NOT NULL,
			artifact_id VARCHAR(128) NOT NULL,
			name VARCHAR(255) DEFAULT NULL,
			parts_json LONGTEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY task_id (task_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Set all default plugin options.
	 */
	private static function set_defaults() {
		add_option( 'agentclerk_plugin_status', 'onboarding' );
		add_option( 'agentclerk_onboarding_step', 1 );
		add_option( 'agentclerk_tier', '' );
		add_option( 'agentclerk_api_key', '' );
		add_option( 'agentclerk_install_secret', '' );
		add_option( 'agentclerk_billing_status', 'active' );
		add_option( 'agentclerk_license_status', 'none' );
		add_option( 'agentclerk_license_key', '' );
		add_option( 'agentclerk_accrued_fees', '0.00' );
		add_option( 'agentclerk_stripe_publishable_key', '' );
		add_option( 'agentclerk_stripe_customer_id', '' );
		add_option( 'agentclerk_billing_card_last4', '' );
		add_option( 'agentclerk_grace_days_remaining', 0 );

		add_option( 'agentclerk_agent_config', wp_json_encode( array(
			'agent_name'         => 'AgentClerk',
			'business_name'      => get_bloginfo( 'name' ),
			'business_desc'      => get_bloginfo( 'description' ),
			'support_file'       => '',
			'escalation_topics'  => array(),
			'escalation_email'   => get_option( 'admin_email' ),
			'escalation_message' => 'A support agent will follow up via email within 24 hours.',
			'escalation_method'  => 'both',
			'product_visibility' => array(),
			'policies'           => array(
				'refund'   => '',
				'license'  => '',
				'delivery' => '',
			),
		) ) );

		add_option( 'agentclerk_placement', wp_json_encode( array(
			'widget'       => true,
			'product_page' => true,
			'clerk_page'   => true,
			'button_label' => 'Get Help',
			'agent_name'   => 'AgentClerk',
			'position'     => 'bottom-right',
		) ) );

		add_option( 'agentclerk_scan_cache', wp_json_encode( array() ) );
	}

	/**
	 * Create the /clerk page if it does not already exist.
	 */
	private static function create_clerk_page() {
		$existing = get_option( 'agentclerk_clerk_page_id' );
		if ( $existing && get_post( $existing ) ) {
			return;
		}

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

	/**
	 * Register rewrite rules for the manifest endpoint.
	 */
	private static function add_rewrite_rules() {
		add_rewrite_rule(
			'^ai-manifest\.json$',
			'index.php?agentclerk_manifest=1',
			'top'
		);
		add_rewrite_rule(
			'^clerk-checkout/([a-zA-Z0-9]+)/?$',
			'index.php?agentclerk_checkout=$matches[1]',
			'top'
		);
	}

	/**
	 * Schedule cron events for billing polling and session expiry.
	 */
	private static function schedule_crons() {
		if ( ! wp_next_scheduled( 'agentclerk_poll_billing_status' ) ) {
			wp_schedule_event( time(), 'hourly', 'agentclerk_poll_billing_status' );
		}

		if ( ! wp_next_scheduled( 'agentclerk_expire_sessions' ) ) {
			wp_schedule_event( time(), 'hourly', 'agentclerk_expire_sessions' );
		}
	}
}
