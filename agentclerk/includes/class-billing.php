<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AgentClerk_Billing {

    public function __construct() {
        add_action( 'agentclerk_poll_billing_status', [ $this, 'poll_status' ] );
        add_action( 'admin_notices', [ $this, 'show_billing_notices' ] );
        add_action( 'wp_ajax_agentclerk_lifetime_checkout', [ $this, 'lifetime_checkout' ] );
        add_action( 'wp_ajax_agentclerk_card_update', [ $this, 'card_update' ] );
        add_action( 'admin_init', [ $this, 'handle_license_return' ] );
    }

    public function poll_status() {
        $response = wp_remote_get( AGENTCLERK_BACKEND_URL . '/billing/status', [
            'headers' => [
                'X-AgentClerk-Secret' => get_option( 'agentclerk_install_secret' ),
                'X-AgentClerk-Site'   => home_url(),
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! $data ) {
            return;
        }

        $status = $data['billingStatus'] ?? 'active';
        update_option( 'agentclerk_billing_status', $status );

        if ( isset( $data['accruedFees'] ) ) {
            update_option( 'agentclerk_accrued_fees', $data['accruedFees'] );
        }
        if ( isset( $data['cardLast4'] ) ) {
            update_option( 'agentclerk_billing_card_last4', $data['cardLast4'] );
        }

        if ( $status === 'grace_period' ) {
            $started        = strtotime( $data['gracePeriodStarted'] ?? '' );
            $days_remaining = $started ? 3 - (int) floor( ( time() - $started ) / 86400 ) : 3;
            update_option( 'agentclerk_grace_days_remaining', max( 0, $days_remaining ) );
        }

        if ( $status === 'suspended' ) {
            update_option( 'agentclerk_plugin_status', 'suspended' );
        }

        if ( $status === 'active' && get_option( 'agentclerk_plugin_status' ) === 'suspended' ) {
            update_option( 'agentclerk_plugin_status', 'active' );
        }
    }

    public function show_billing_notices() {
        $status = get_option( 'agentclerk_billing_status', 'active' );

        if ( $status === 'grace_period' ) {
            $days = get_option( 'agentclerk_grace_days_remaining', 3 );
            echo '<div class="notice notice-error"><p>';
            echo '<strong>AgentClerk payment failed.</strong> ';
            echo 'Update your payment card within ' . esc_html( $days ) . ' day(s) to avoid suspension. ';
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=agentclerk-sales' ) ) . '">Update card &rarr;</a>';
            echo '</p></div>';
        }
    }

    public function lifetime_checkout() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $response = wp_remote_post( AGENTCLERK_BACKEND_URL . '/license/checkout', [
            'headers' => [
                'X-AgentClerk-Secret' => get_option( 'agentclerk_install_secret' ),
                'X-AgentClerk-Site'   => home_url(),
                'Content-Type'        => 'application/json',
            ],
            'body' => wp_json_encode( [
                'successUrl' => admin_url( 'admin.php?page=agentclerk-sales&license_success=1&nonce=' . wp_create_nonce( 'agentclerk_license' ) ),
                'cancelUrl'  => admin_url( 'admin.php?page=agentclerk-sales' ),
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        wp_send_json_success( [ 'checkoutUrl' => $data['checkoutUrl'] ?? '' ] );
    }

    public function card_update() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $response = wp_remote_post( AGENTCLERK_BACKEND_URL . '/billing/card-update', [
            'headers' => [
                'X-AgentClerk-Secret' => get_option( 'agentclerk_install_secret' ),
                'X-AgentClerk-Site'   => home_url(),
                'Content-Type'        => 'application/json',
            ],
            'body' => wp_json_encode( [
                'returnUrl' => admin_url( 'admin.php?page=agentclerk-sales' ),
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        wp_send_json_success( [ 'portalUrl' => $data['portalUrl'] ?? '' ] );
    }

    public function handle_license_return() {
        if ( ! isset( $_GET['license_success'] ) || $_GET['license_success'] !== '1' ) {
            return;
        }

        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        if ( $page !== 'agentclerk-sales' ) {
            return;
        }

        if ( ! isset( $_GET['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'agentclerk_license' ) ) {
            return;
        }

        $this->activate_license();
    }

    private function activate_license() {
        $response = wp_remote_post( AGENTCLERK_BACKEND_URL . '/license/activate', [
            'headers' => [
                'X-AgentClerk-Secret' => get_option( 'agentclerk_install_secret' ),
                'X-AgentClerk-Site'   => home_url(),
                'Content-Type'        => 'application/json',
            ],
            'body' => wp_json_encode( [] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! empty( $data['licenseKey'] ) ) {
            update_option( 'agentclerk_license_status', 'active' );
            update_option( 'agentclerk_license_key', $data['licenseKey'] );
            update_option( 'agentclerk_accrued_fees', 0 );

            add_action( 'admin_notices', function () {
                echo '<div class="notice notice-success"><p>Lifetime license activated. No more transaction fees.</p></div>';
            } );
        }
    }

    public static function calculate_fee( $sale_amount ) {
        if ( get_option( 'agentclerk_license_status' ) === 'active' ) {
            return 0;
        }

        $tier = get_option( 'agentclerk_tier', 'byok' );
        if ( $tier === 'byok' ) {
            return max( $sale_amount * 0.01, 1.00 );
        }

        return max( $sale_amount * 0.015, 1.99 );
    }
}
