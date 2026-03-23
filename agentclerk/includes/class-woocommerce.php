<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AgentClerk_WooCommerce {

    public function __construct() {
        add_action( 'woocommerce_init', [ $this, 'handle_checkout_link' ] );
        add_action( 'woocommerce_order_status_completed', [ $this, 'handle_order_completed' ] );
    }

    public function handle_checkout_link() {
        $token = get_query_var( 'agentclerk_checkout' );
        if ( empty( $token ) ) {
            return;
        }

        $token = sanitize_text_field( $token );

        global $wpdb;
        $table = $wpdb->prefix . 'agentclerk_quote_links';
        $quote = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %s AND status = 'pending' AND expires_at > %s",
                $token,
                current_time( 'mysql' )
            )
        );

        if ( ! $quote ) {
            wp_die(
                esc_html__( 'This checkout link is invalid or has expired.', 'agentclerk' ),
                esc_html__( 'Invalid Link', 'agentclerk' ),
                [ 'response' => 404 ]
            );
        }

        WC()->cart->empty_cart();
        WC()->cart->add_to_cart( $quote->product_id, 1 );

        wp_safe_redirect( wc_get_checkout_url() );
        exit;
    }

    public function handle_order_completed( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'agentclerk_quote_links';

        foreach ( $order->get_items() as $item ) {
            $product_id = $item->get_product_id();

            $quote = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE product_id = %d AND status = 'pending' ORDER BY created_at DESC LIMIT 1",
                    $product_id
                )
            );

            if ( ! $quote ) {
                continue;
            }

            $wpdb->update( $table, [
                'wc_order_id' => $order_id,
                'status'      => 'completed',
            ], [ 'id' => $quote->id ] );

            $sale_amount = (float) $order->get_total();
            $fee         = AgentClerk_Billing::calculate_fee( $sale_amount );

            $conversations_table = $wpdb->prefix . 'agentclerk_conversations';
            $wpdb->update( $conversations_table, [
                'outcome'     => 'purchased',
                'sale_amount' => $sale_amount,
                'acclerk_fee' => $fee,
                'updated_at'  => current_time( 'mysql' ),
            ], [ 'id' => $quote->conversation_id ] );

            $accrued = (float) get_option( 'agentclerk_accrued_fees', 0 );
            update_option( 'agentclerk_accrued_fees', number_format( $accrued + $fee, 2, '.', '' ) );

            if ( $fee > 0 ) {
                AgentClerk::backend_request( '/billing/record-sale', [
                    'method' => 'POST',
                    'body'   => [
                        'orderId'    => $order_id,
                        'saleAmount' => $sale_amount,
                        'fee'        => $fee,
                        'productId'  => $product_id,
                    ],
                ] );
            }

            delete_transient( 'agentclerk_manifest_cache' );
        }
    }
}
