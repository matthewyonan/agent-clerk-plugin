<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AgentClerk_WooCommerce {

    public function __construct() {
        add_action( 'woocommerce_init', [ $this, 'handle_checkout_link' ] );
        add_action( 'woocommerce_order_status_completed', [ $this, 'handle_completed_order' ] );
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

    public function handle_completed_order( $order_id ) {
        $quote_link_id = $this->get_quote_link_for_order( $order_id );
        if ( ! $quote_link_id ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $sale_amount = (float) $order->get_total();

        // Skip fee if lifetime license is active
        if ( get_option( 'agentclerk_license_status' ) === 'active' ) {
            return;
        }

        // Calculate fee
        $tier = get_option( 'agentclerk_tier', 'byok' );
        $fee  = $tier === 'turnkey'
            ? max( $sale_amount * 0.015, 1.99 )
            : max( $sale_amount * 0.01, 1.00 );

        // Get product name
        $items      = $order->get_items();
        $first_item = reset( $items );
        $product_name = $first_item ? $first_item->get_name() : 'Unknown';
        $product_id   = $first_item ? $first_item->get_product_id() : 0;

        // Mark quote link completed
        global $wpdb;
        $table = $wpdb->prefix . 'agentclerk_quote_links';
        $quote = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s", $quote_link_id )
        );

        $wpdb->update( $table, [
            'wc_order_id' => $order_id,
            'status'      => 'completed',
        ], [ 'id' => $quote_link_id ] );

        // Update conversation record
        if ( $quote ) {
            $conversations_table = $wpdb->prefix . 'agentclerk_conversations';
            $wpdb->update( $conversations_table, [
                'outcome'     => 'purchased',
                'sale_amount' => $sale_amount,
                'acclerk_fee' => $fee,
                'updated_at'  => current_time( 'mysql' ),
            ], [ 'id' => $quote->conversation_id ] );
        }

        // POST fee to backend
        wp_remote_post( AGENTCLERK_BACKEND_URL . '/fees', [
            'headers' => [
                'X-AgentClerk-Secret' => get_option( 'agentclerk_install_secret' ),
                'X-AgentClerk-Site'   => home_url(),
                'Content-Type'        => 'application/json',
            ],
            'body' => wp_json_encode( [
                'wcOrderId'   => $order_id,
                'productName' => $product_name,
                'saleAmount'  => $sale_amount,
                'feeAmount'   => $fee,
                'buyerType'   => $this->get_buyer_type_for_order( $order_id ),
            ] ),
        ] );

        // Update local accrued fees
        $current = (float) get_option( 'agentclerk_accrued_fees', 0 );
        update_option( 'agentclerk_accrued_fees', $current + $fee );

        delete_transient( 'agentclerk_manifest_cache' );
    }

    /**
     * Find a pending quote link that matches an order's products.
     */
    private function get_quote_link_for_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return null;
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

            if ( $quote ) {
                return $quote->id;
            }
        }

        return null;
    }

    /**
     * Get the buyer type for a conversation linked to an order's quote link.
     */
    private function get_buyer_type_for_order( $order_id ) {
        global $wpdb;
        $quote_table = $wpdb->prefix . 'agentclerk_quote_links';
        $convo_table = $wpdb->prefix . 'agentclerk_conversations';

        $buyer_type = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT c.buyer_type FROM {$convo_table} c
                 INNER JOIN {$quote_table} q ON q.conversation_id = c.id
                 WHERE q.wc_order_id = %d
                 LIMIT 1",
                $order_id
            )
        );

        return $buyer_type ?: 'human';
    }
}
