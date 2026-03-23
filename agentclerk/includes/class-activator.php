<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AgentClerk_Activator {

    public static function activate() {
        self::create_tables();
        self::set_defaults();
        self::create_clerk_page();
        self::add_rewrite_rules();
        flush_rewrite_rules();
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'agentclerk_poll_billing_status' );
        flush_rewrite_rules();
    }

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
            product_ids JSON,
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
            amount DECIMAL(10,2) NOT NULL,
            wc_order_id BIGINT DEFAULT NULL,
            status ENUM('pending','completed','expired') NOT NULL DEFAULT 'pending',
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY conversation_id (conversation_id)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    private static function set_defaults() {
        add_option( 'agentclerk_plugin_status', 'onboarding' );
        add_option( 'agentclerk_onboarding_step', 1 );
        add_option( 'agentclerk_billing_status', 'active' );
        add_option( 'agentclerk_license_status', 'none' );
        add_option( 'agentclerk_accrued_fees', '0.00' );
        add_option( 'agentclerk_agent_config', wp_json_encode( [
            'agent_name'         => 'AgentClerk',
            'business_name'      => get_bloginfo( 'name' ),
            'business_desc'      => get_bloginfo( 'description' ),
            'support_file'       => '',
            'escalation_topics'  => [],
            'escalation_email'   => get_option( 'admin_email' ),
            'escalation_message' => 'A support agent will follow up via email within 24 hours.',
            'product_visibility' => [],
            'policies'           => [
                'refund'   => '',
                'license'  => '',
                'delivery' => '',
            ],
        ] ) );
        add_option( 'agentclerk_placement', wp_json_encode( [
            'widget'       => true,
            'product_page' => true,
            'clerk_page'   => true,
            'button_label' => 'Get Help',
            'agent_name'   => 'AgentClerk',
            'position'     => 'bottom-right',
        ] ) );
    }

    private static function create_clerk_page() {
        $existing = get_option( 'agentclerk_clerk_page_id' );
        if ( $existing && get_post( $existing ) ) {
            return;
        }
        $page_id = wp_insert_post( [
            'post_title'   => 'Clerk',
            'post_name'    => 'clerk',
            'post_status'  => 'publish',
            'post_type'    => 'page',
            'post_content' => '<!-- AgentClerk full-page chat -->',
        ] );
        if ( $page_id && ! is_wp_error( $page_id ) ) {
            update_option( 'agentclerk_clerk_page_id', $page_id );
        }
    }

    private static function add_rewrite_rules() {
        add_rewrite_rule(
            '^ai-manifest\.json$',
            'index.php?agentclerk_manifest=1',
            'top'
        );
    }
}
