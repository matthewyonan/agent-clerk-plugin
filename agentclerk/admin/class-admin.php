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

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'wp_ajax_agentclerk_save_settings', [ $this, 'save_settings' ] );
        add_action( 'wp_ajax_agentclerk_register_install', [ $this, 'register_install' ] );
        add_action( 'wp_ajax_agentclerk_validate_api_key', [ $this, 'validate_api_key' ] );
        add_action( 'wp_ajax_agentclerk_start_scan', [ $this, 'start_scan' ] );
        add_action( 'wp_ajax_agentclerk_scan_progress', [ $this, 'scan_progress' ] );
        add_action( 'wp_ajax_agentclerk_save_onboarding_step', [ $this, 'save_onboarding_step' ] );
        add_action( 'wp_ajax_agentclerk_save_agent_config', [ $this, 'save_agent_config' ] );
        add_action( 'wp_ajax_agentclerk_save_placement', [ $this, 'save_placement' ] );
        add_action( 'wp_ajax_agentclerk_go_live', [ $this, 'go_live' ] );
        add_action( 'wp_ajax_agentclerk_save_catalog', [ $this, 'save_catalog' ] );
        add_action( 'wp_ajax_agentclerk_add_product', [ $this, 'add_product' ] );
    }

    public function register_menus() {
        $status = get_option( 'agentclerk_plugin_status', 'onboarding' );

        add_menu_page(
            'AgentClerk',
            'AgentClerk',
            'manage_options',
            'agentclerk',
            [ $this, 'render_dashboard' ],
            'dashicons-format-chat',
            56
        );

        add_submenu_page(
            'agentclerk',
            'Setup',
            'Setup',
            'manage_options',
            'agentclerk-onboarding',
            [ $this, 'render_onboarding' ]
        );

        if ( $status === 'suspended' ) {
            remove_submenu_page( 'agentclerk', 'agentclerk' );
            add_submenu_page(
                'agentclerk',
                'Suspended',
                'Account Suspended',
                'manage_options',
                'agentclerk',
                [ $this, 'render_suspended' ]
            );
            return;
        }

        if ( $status === 'active' ) {
            add_submenu_page( 'agentclerk', 'Dashboard', 'Dashboard', 'manage_options', 'agentclerk' );
            add_submenu_page( 'agentclerk', 'Conversations', 'Conversations', 'manage_options', 'agentclerk-conversations', [ $this, 'render_conversations' ] );
            add_submenu_page( 'agentclerk', 'Sales', 'Sales', 'manage_options', 'agentclerk-sales', [ $this, 'render_sales' ] );
            add_submenu_page( 'agentclerk', 'Support', 'Support', 'manage_options', 'agentclerk-support', [ $this, 'render_support' ] );
            add_submenu_page( 'agentclerk', 'Settings', 'Settings', 'manage_options', 'agentclerk-settings', [ $this, 'render_settings' ] );
        }
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'agentclerk' ) === false ) {
            return;
        }

        wp_enqueue_style( 'agentclerk-fonts', 'https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Syne:wght@400;500;600;700&family=DM+Sans:wght@300;400;500&display=swap', [], null );
        wp_enqueue_style( 'agentclerk-admin', AGENTCLERK_PLUGIN_URL . 'admin/css/admin.css', [], AGENTCLERK_VERSION );
        wp_enqueue_script( 'agentclerk-admin', AGENTCLERK_PLUGIN_URL . 'admin/js/admin.js', [ 'jquery' ], AGENTCLERK_VERSION, true );

        wp_localize_script( 'agentclerk-admin', 'agentclerk', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'agentclerk_nonce' ),
            'siteUrl' => get_site_url(),
        ] );

        $stripe_key = get_option( 'agentclerk_stripe_publishable_key', '' );
        if ( $stripe_key ) {
            wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', [], null, true );
            wp_localize_script( 'agentclerk-admin', 'agentclerkStripe', [
                'publishableKey' => $stripe_key,
            ] );
        }
    }

    public function render_dashboard() {
        $status = get_option( 'agentclerk_plugin_status', 'onboarding' );
        if ( $status === 'suspended' ) {
            include AGENTCLERK_PLUGIN_DIR . 'admin/views/suspended.php';
            return;
        }
        if ( $status === 'onboarding' ) {
            $this->render_onboarding();
            return;
        }
        include AGENTCLERK_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public function render_onboarding() {
        $step = (int) get_option( 'agentclerk_onboarding_step', 1 );
        $step = max( 1, min( 6, $step ) );
        include AGENTCLERK_PLUGIN_DIR . 'admin/views/onboarding/step-' . $step . '.php';
    }

    public function render_conversations() {
        include AGENTCLERK_PLUGIN_DIR . 'admin/views/conversations.php';
    }

    public function render_sales() {
        include AGENTCLERK_PLUGIN_DIR . 'admin/views/sales.php';
    }

    public function render_support() {
        include AGENTCLERK_PLUGIN_DIR . 'admin/views/support.php';
    }

    public function render_settings() {
        include AGENTCLERK_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public function render_suspended() {
        include AGENTCLERK_PLUGIN_DIR . 'admin/views/suspended.php';
    }

    public function validate_api_key() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
        if ( empty( $api_key ) ) {
            wp_send_json_error( [ 'message' => 'API key is required.' ] );
        }

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 15,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body' => wp_json_encode( [
                'model'      => 'claude-sonnet-4-20250514',
                'max_tokens' => 10,
                'messages'   => [ [ 'role' => 'user', 'content' => 'Hi' ] ],
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => 'Connection failed: ' . $response->get_error_message() ] );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 200 ) {
            wp_send_json_success( [ 'message' => 'API key is valid.' ] );
        } else {
            wp_send_json_error( [ 'message' => 'Invalid API key. Status: ' . $code ] );
        }
    }

    public function register_install() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $tier       = isset( $_POST['tier'] ) ? sanitize_text_field( wp_unslash( $_POST['tier'] ) ) : '';
        $payment_id = isset( $_POST['stripe_payment_method_id'] ) ? sanitize_text_field( wp_unslash( $_POST['stripe_payment_method_id'] ) ) : '';
        $api_key    = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';

        if ( ! in_array( $tier, [ 'byok', 'turnkey' ], true ) ) {
            wp_send_json_error( [ 'message' => 'Invalid tier.' ] );
        }

        if ( $tier === 'byok' && ! empty( $api_key ) ) {
            update_option( 'agentclerk_api_key', AgentClerk::encrypt( $api_key ) );
        }

        $body = [
            'siteUrl'                => get_site_url(),
            'tier'                   => $tier,
            'stripePaymentMethodId'  => $payment_id,
        ];

        $response = AgentClerk::backend_request( '/installs/register', [
            'method' => 'POST',
            'body'   => $body,
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        $result = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $result['installSecret'] ) ) {
            wp_send_json_error( [ 'message' => 'Registration failed. No install secret returned.' ] );
        }

        update_option( 'agentclerk_install_secret', $result['installSecret'] );
        update_option( 'agentclerk_tier', $tier );

        if ( ! empty( $result['stripePublishableKey'] ) ) {
            update_option( 'agentclerk_stripe_publishable_key', $result['stripePublishableKey'] );
        }
        if ( ! empty( $result['stripeCustomerId'] ) ) {
            update_option( 'agentclerk_stripe_customer_id', $result['stripeCustomerId'] );
        }
        if ( ! empty( $result['cardLast4'] ) ) {
            update_option( 'agentclerk_billing_card_last4', $result['cardLast4'] );
        }

        if ( $tier === 'turnkey' ) {
            $checkout = wp_remote_post( AGENTCLERK_BACKEND_URL . '/billing/turnkey-checkout', [
                'headers' => [
                    'X-AgentClerk-Secret' => $result['installSecret'],
                    'X-AgentClerk-Site'   => home_url(),
                    'Content-Type'        => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'successUrl' => admin_url( 'admin.php?page=agentclerk&step=2&turnkey_success=1' ),
                    'cancelUrl'  => admin_url( 'admin.php?page=agentclerk&step=1&turnkey_cancelled=1' ),
                ] ),
            ] );

            if ( ! is_wp_error( $checkout ) ) {
                $checkout_body = json_decode( wp_remote_retrieve_body( $checkout ), true );
                if ( ! empty( $checkout_body['checkoutUrl'] ) ) {
                    wp_send_json_success( [ 'redirect' => $checkout_body['checkoutUrl'] ] );
                    return;
                }
            }
        }

        update_option( 'agentclerk_onboarding_step', 2 );
        wp_send_json_success( [ 'step' => 2 ] );
    }

    public function start_scan() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $results = AgentClerk_Scanner::start_scan();
        update_option( 'agentclerk_onboarding_step', 3 );
        wp_send_json_success( $results );
    }

    public function scan_progress() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        $progress = get_transient( 'agentclerk_scan_progress' );
        wp_send_json_success( $progress ?: [ 'status' => 'idle' ] );
    }

    public function save_onboarding_step() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $step = (int) ( $_POST['step'] ?? 0 );
        if ( $step >= 1 && $step <= 6 ) {
            update_option( 'agentclerk_onboarding_step', $step );
            wp_send_json_success();
        }

        wp_send_json_error( [ 'message' => 'Invalid step.' ] );
    }

    public function save_agent_config() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $config = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );

        $fields = [ 'agent_name', 'business_name', 'business_desc', 'support_file', 'escalation_email', 'escalation_message' ];
        foreach ( $fields as $field ) {
            if ( isset( $_POST[ $field ] ) ) {
                $config[ $field ] = sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) );
            }
        }

        if ( isset( $_POST['escalation_topics'] ) ) {
            $topics = wp_unslash( $_POST['escalation_topics'] );
            if ( is_string( $topics ) ) {
                $topics = json_decode( $topics, true );
            }
            $config['escalation_topics'] = is_array( $topics ) ? array_map( 'sanitize_text_field', $topics ) : [];
        }

        if ( isset( $_POST['policies'] ) ) {
            $policies = wp_unslash( $_POST['policies'] );
            if ( is_string( $policies ) ) {
                $policies = json_decode( $policies, true );
            }
            if ( is_array( $policies ) ) {
                $config['policies'] = [
                    'refund'   => sanitize_textarea_field( $policies['refund'] ?? '' ),
                    'license'  => sanitize_textarea_field( $policies['license'] ?? '' ),
                    'delivery' => sanitize_textarea_field( $policies['delivery'] ?? '' ),
                ];
            }
        }

        if ( isset( $_POST['support_page_id'] ) ) {
            $config['support_page_id'] = (int) $_POST['support_page_id'];
        }

        update_option( 'agentclerk_agent_config', wp_json_encode( $config ) );
        delete_transient( 'agentclerk_manifest_cache' );

        wp_send_json_success();
    }

    public function save_catalog() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $visibility = isset( $_POST['visibility'] ) ? json_decode( wp_unslash( $_POST['visibility'] ), true ) : [];
        if ( ! is_array( $visibility ) ) {
            $visibility = [];
        }

        $config = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
        $config['product_visibility'] = array_map( function ( $v ) {
            return (bool) $v;
        }, $visibility );

        update_option( 'agentclerk_agent_config', wp_json_encode( $config ) );
        delete_transient( 'agentclerk_manifest_cache' );

        wp_send_json_success();
    }

    public function add_product() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        if ( ! function_exists( 'wc_get_product' ) ) {
            wp_send_json_error( [ 'message' => 'WooCommerce is not active.' ] );
        }

        $name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
        $price = isset( $_POST['price'] ) ? floatval( $_POST['price'] ) : 0;
        $type  = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : 'simple';
        $desc  = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';

        if ( empty( $name ) ) {
            wp_send_json_error( [ 'message' => 'Product name is required.' ] );
        }

        $product = new WC_Product_Simple();
        $product->set_name( $name );
        $product->set_regular_price( $price );
        $product->set_short_description( $desc );
        $product->set_status( 'publish' );
        $product->save();

        wp_send_json_success( [ 'product_id' => $product->get_id() ] );
    }

    public function save_placement() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $placement = [
            'widget'       => ! empty( $_POST['widget'] ),
            'product_page' => ! empty( $_POST['product_page'] ),
            'clerk_page'   => ! empty( $_POST['clerk_page'] ),
            'button_label' => isset( $_POST['button_label'] ) ? sanitize_text_field( wp_unslash( $_POST['button_label'] ) ) : 'Get Help',
            'agent_name'   => isset( $_POST['agent_name'] ) ? sanitize_text_field( wp_unslash( $_POST['agent_name'] ) ) : 'AgentClerk',
            'position'     => isset( $_POST['position'] ) ? sanitize_text_field( wp_unslash( $_POST['position'] ) ) : 'bottom-right',
        ];

        update_option( 'agentclerk_placement', wp_json_encode( $placement ) );

        $existing = get_option( 'agentclerk_clerk_page_id' );
        if ( $placement['clerk_page'] && ( ! $existing || ! get_post( $existing ) ) ) {
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

        flush_rewrite_rules();
        wp_send_json_success();
    }

    public function go_live() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        update_option( 'agentclerk_plugin_status', 'active' );
        update_option( 'agentclerk_onboarding_step', 6 );

        $config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
        $placement  = json_decode( get_option( 'agentclerk_placement', '{}' ), true );
        $scan_cache = json_decode( get_option( 'agentclerk_scan_cache', '{}' ), true );

        AgentClerk::backend_request( '/installs', [
            'method' => 'POST',
            'body'   => [
                'config'    => $config,
                'placement' => $placement,
                'products'  => $scan_cache['products'] ?? [],
                'tier'      => get_option( 'agentclerk_tier' ),
            ],
        ] );

        if ( ! wp_next_scheduled( 'agentclerk_poll_billing_status' ) ) {
            wp_schedule_event( time(), 'hourly', 'agentclerk_poll_billing_status' );
        }

        wp_send_json_success( [ 'redirect' => admin_url( 'admin.php?page=agentclerk' ) ] );
    }

    public function save_settings() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
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
                if ( $api_key ) {
                    update_option( 'agentclerk_api_key', AgentClerk::encrypt( $api_key ) );
                }
                wp_send_json_success();
                break;
            default:
                $this->save_agent_config();
        }
    }
}
