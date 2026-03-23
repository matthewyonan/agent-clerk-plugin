<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AgentClerk_Billing {

    public function __construct() {
        add_action( 'agentclerk_poll_billing_status', [ $this, 'poll_status' ] );
        add_action( 'admin_notices', [ $this, 'show_billing_notices' ] );
        add_action( 'wp_ajax_agentclerk_get_billing_portal', [ $this, 'get_billing_portal' ] );
        add_action( 'wp_ajax_agentclerk_get_license_checkout', [ $this, 'get_license_checkout' ] );
    }

    public function poll_status() {
        $response = AgentClerk::backend_request( '/billing/status', [ 'method' => 'GET' ] );

        if ( is_wp_error( $response ) ) {
            return;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! $body ) {
            return;
        }

        $status = $body['billingStatus'] ?? 'active';
        update_option( 'agentclerk_billing_status', $status );

        if ( isset( $body['accruedFees'] ) ) {
            update_option( 'agentclerk_accrued_fees', $body['accruedFees'] );
        }
        if ( isset( $body['cardLast4'] ) ) {
            update_option( 'agentclerk_billing_card_last4', $body['cardLast4'] );
        }

        if ( $status === 'suspended' ) {
            update_option( 'agentclerk_plugin_status', 'suspended' );
        } elseif ( get_option( 'agentclerk_plugin_status' ) === 'suspended' && $status === 'active' ) {
            update_option( 'agentclerk_plugin_status', 'active' );
        }
    }

    public function show_billing_notices() {
        $status = get_option( 'agentclerk_billing_status', 'active' );

        if ( $status === 'grace_period' ) {
            $response = AgentClerk::backend_request( '/billing/status', [ 'method' => 'GET' ] );
            $days     = 7;
            if ( ! is_wp_error( $response ) ) {
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                $days = $body['graceDaysRemaining'] ?? 7;
            }
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>AgentClerk:</strong> Your payment method needs updating. ';
            echo esc_html( $days ) . ' days remaining before service suspension. ';
            echo '<a href="' . esc_url( admin_url( 'admin.php?page=agentclerk-settings' ) ) . '">Update payment</a></p>';
            echo '</div>';
        }
    }

    public function get_billing_portal() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $response = AgentClerk::backend_request( '/billing/portal', [
            'method' => 'POST',
            'body'   => [ 'returnUrl' => admin_url( 'admin.php?page=agentclerk-settings' ) ],
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        wp_send_json_success( [ 'url' => $body['url'] ?? '' ] );
    }

    public function get_license_checkout() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $response = AgentClerk::backend_request( '/billing/license-checkout', [
            'method' => 'POST',
            'body'   => [
                'successUrl' => admin_url( 'admin.php?page=agentclerk-sales&license=activated' ),
                'cancelUrl'  => admin_url( 'admin.php?page=agentclerk-sales' ),
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        wp_send_json_success( [ 'url' => $body['url'] ?? '' ] );
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
