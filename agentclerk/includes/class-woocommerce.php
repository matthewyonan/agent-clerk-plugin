<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WooCommerce integration for AgentClerk.
 *
 * Handles quote link checkout (add to cart + redirect to WC checkout),
 * order completion (calculate fee, update conversation, POST to backend,
 * accrue fees), and proper quote-to-order matching.
 *
 * @since 1.0.0
 */
class AgentClerk_WooCommerce {

	/**
	 * Singleton instance.
	 *
	 * @var AgentClerk_WooCommerce|null
	 */
	private static $instance = null;

	/**
	 * Return the singleton instance.
	 *
	 * @return AgentClerk_WooCommerce
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Register hooks.
	 */
	private function __construct() {
		add_action( 'template_redirect', array( $this, 'handle_checkout_link' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'handle_completed_order' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'handle_completed_order' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'render_activation_on_thankyou' ), 5 );
	}

	/**
	 * Handle a quote link checkout via the agentclerk_checkout query var.
	 *
	 * Validates the quote token, empties the cart, adds the product,
	 * stores the quote link ID in the WC session, and redirects to WC checkout.
	 */
	public function handle_checkout_link() {
		$token = get_query_var( 'agentclerk_checkout' );
		if ( empty( $token ) ) {
			return;
		}

		$token = sanitize_text_field( $token );

		global $wpdb;
		$table = $wpdb->prefix . 'agentclerk_quote_links';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$quote = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %s AND status = 'pending' AND expires_at > %s",
				$table,
				$token,
				current_time( 'mysql' )
			)
		);

		if ( ! $quote ) {
			wp_die(
				esc_html__( 'This checkout link is invalid or has expired.', 'agentclerk' ),
				esc_html__( 'Invalid Link', 'agentclerk' ),
				array( 'response' => 404 )
			);
		}

		// Ensure WooCommerce is available.
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			wp_die(
				esc_html__( 'WooCommerce is not available.', 'agentclerk' ),
				esc_html__( 'Error', 'agentclerk' ),
				array( 'response' => 500 )
			);
		}

		WC()->cart->empty_cart();
		$added = WC()->cart->add_to_cart( $quote->product_id, 1 );

		if ( ! $added ) {
			wp_die(
				esc_html__( 'Unable to add the product to your cart. It may be out of stock.', 'agentclerk' ),
				esc_html__( 'Cart Error', 'agentclerk' ),
				array( 'response' => 400 )
			);
		}

		// Store the quote link ID in the WC session for later matching.
		if ( WC()->session ) {
			WC()->session->set( 'agentclerk_quote_link_id', $quote->id );
		}

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Handle a completed WooCommerce order.
	 *
	 * Calculates the AgentClerk fee, updates the conversation record,
	 * marks the quote link as completed, POSTs the fee to the backend,
	 * and accrues the fee locally.
	 *
	 * @param int $order_id WooCommerce order ID.
	 */
	public function handle_completed_order( $order_id ) {
		// Prevent duplicate processing.
		$already_processed = get_post_meta( $order_id, '_agentclerk_fee_processed', true );
		if ( $already_processed ) {
			return;
		}

		$quote_link_id = $this->get_quote_link_for_order( $order_id );
		if ( ! $quote_link_id ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$sale_amount = (float) $order->get_total();

		// Calculate the fee using the billing class.
		$fee = AgentClerk_Billing::calculate_fee( $sale_amount );

		// Get product name from the order.
		$items        = $order->get_items();
		$first_item   = reset( $items );
		$product_name = $first_item ? $first_item->get_name() : 'Unknown';
		$product_id   = $first_item ? $first_item->get_product_id() : 0;

		// Mark quote link as completed.
		global $wpdb;
		$quote_table = $wpdb->prefix . 'agentclerk_quote_links';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$quote       = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM %i WHERE id = %s", $quote_table, $quote_link_id )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$quote_table,
			array(
				'wc_order_id' => $order_id,
				'status'      => 'completed',
			),
			array( 'id' => $quote_link_id ),
			array( '%d', '%s' ),
			array( '%s' )
		);

		// Update the conversation record.
		if ( $quote ) {
			$conversations_table = $wpdb->prefix . 'agentclerk_conversations';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$conversations_table,
				array(
					'outcome'      => 'purchased',
					'sale_amount'  => $sale_amount,
					'acclerk_fee'  => $fee,
					'product_name' => $product_name,
					'updated_at'   => current_time( 'mysql' ),
				),
				array( 'id' => $quote->conversation_id ),
				array( '%s', '%f', '%f', '%s', '%s' ),
				array( '%d' )
			);
		}

		// POST the fee to the backend.
		$buyer_type = $this->get_buyer_type_for_order( $order_id );

		AgentClerk::backend_request( '/fees', array(
			'method' => 'POST',
			'body'   => array(
				'wcOrderId'   => $order_id,
				'productName' => $product_name,
				'productId'   => $product_id,
				'saleAmount'  => $sale_amount,
				'feeAmount'   => $fee,
				'buyerType'   => $buyer_type,
			),
		) );

		// Update local accrued fees.
		$current = (float) get_option( 'agentclerk_accrued_fees', 0 );
		update_option( 'agentclerk_accrued_fees', $current + $fee );

		// Mark as processed to prevent double-counting.
		update_post_meta( $order_id, '_agentclerk_fee_processed', '1' );
		update_post_meta( $order_id, '_agentclerk_fee_amount', $fee );
		update_post_meta( $order_id, '_agentclerk_quote_link_id', $quote_link_id );

		// Generate activation code for agent-assisted purchasing.
		$activation_code = 'AC-' . strtoupper( bin2hex( random_bytes( 4 ) ) ) . '-' . strtoupper( bin2hex( random_bytes( 4 ) ) );
		update_post_meta( $order_id, '_agentclerk_activation_code', $activation_code );

		// Bust the manifest cache.
		delete_transient( 'agentclerk_manifest_cache' );
	}

	/**
	 * Render activation handoff block on WooCommerce thank-you page.
	 *
	 * @param int $order_id Order ID.
	 */
	public function render_activation_on_thankyou( $order_id ) {
		$quote_link_id = get_post_meta( $order_id, '_agentclerk_quote_link_id', true );
		if ( ! $quote_link_id ) {
			return; // Not an AgentClerk order.
		}

		$activation_code = get_post_meta( $order_id, '_agentclerk_activation_code', true );
		if ( ! $activation_code ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$items        = $order->get_items();
		$first_item   = reset( $items );
		$product_name = $first_item ? $first_item->get_name() : 'Product';

		$clerk_url = get_permalink( get_option( 'agentclerk_clerk_page_id', 0 ) );
		if ( ! $clerk_url ) {
			$clerk_url = get_site_url() . '/clerk/';
		}
		$activation_url = $clerk_url . '?intent=activation&code=' . rawurlencode( $activation_code );

		echo '<div class="agentclerk-ssr-activation" style="margin:20px 0;padding:20px;border:2px solid #00E5C8;border-radius:8px;background:#f0fdf9;">';
		echo '<h3 style="margin:0 0 10px;font-size:16px;">Order Confirmed &mdash; ' . esc_html( $product_name ) . '</h3>';
		echo '<p style="margin:0 0 8px;font-size:13px;">Your activation code:</p>';
		echo '<p style="margin:0 0 12px;font-family:monospace;font-size:18px;font-weight:700;color:#1C2333;letter-spacing:1px;">' . esc_html( $activation_code ) . '</p>';

		echo '<p style="margin:0 0 8px;font-size:13px;"><strong>If an AI agent helped you purchase this:</strong></p>';
		echo '<p style="margin:0 0 8px;font-size:13px;">Give this code to your agent, or share this link:</p>';
		echo '<p style="margin:0 0 12px;"><a href="' . esc_url( $activation_url ) . '" style="font-family:monospace;font-size:12px;word-break:break-all;">' . esc_url( $activation_url ) . '</a></p>';
		echo '<p style="margin:0;font-size:12px;color:#64748b;">The agent can use this code to retrieve credentials, setup instructions, or activation details.</p>';
		echo '</div>';
	}

	/**
	 * Find the quote link ID for a given order.
	 *
	 * First checks the WC session, then falls back to matching by product ID.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return string|null Quote link ID or null.
	 */
	private function get_quote_link_for_order( $order_id ) {
		// Check if quote link ID was stored in post meta (from session).
		$stored = get_post_meta( $order_id, '_agentclerk_quote_link_id', true );
		if ( $stored ) {
			return $stored;
		}

		// Check WC session for the quote link.
		if ( function_exists( 'WC' ) && WC()->session ) {
			$session_quote = WC()->session->get( 'agentclerk_quote_link_id' );
			if ( $session_quote ) {
				WC()->session->set( 'agentclerk_quote_link_id', '' );
				update_post_meta( $order_id, '_agentclerk_quote_link_id', $session_quote );
				return $session_quote;
			}
		}

		// Fallback: match by product ID.
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'agentclerk_quote_links';

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$quote = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id FROM %i WHERE product_id = %d AND status = 'pending' ORDER BY created_at DESC LIMIT 1",
					$table,
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
	 * Get the buyer type for the conversation linked to an order's quote link.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return string 'human' or 'agent'.
	 */
	private function get_buyer_type_for_order( $order_id ) {
		global $wpdb;
		$quote_table = $wpdb->prefix . 'agentclerk_quote_links';
		$convo_table = $wpdb->prefix . 'agentclerk_conversations';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$buyer_type = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT c.buyer_type FROM %i c
				 INNER JOIN %i q ON q.conversation_id = c.id
				 WHERE q.wc_order_id = %d
				 LIMIT 1",
				$convo_table,
				$quote_table,
				$order_id
			)
		);

		return $buyer_type ? $buyer_type : 'human';
	}
}
