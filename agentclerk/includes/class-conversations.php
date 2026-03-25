<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Conversations CRUD and statistics for AgentClerk.
 *
 * Provides AJAX endpoints for listing conversations, viewing messages,
 * fetching stats (total, today, setup, support, in_cart, escalated, purchased),
 * sales data (gross, fees, count, avg, transactions), and session expiry
 * (cron hook -- NOT scheduled here; scheduled in the activator).
 *
 * @since 1.0.0
 */
class AgentClerk_Conversations {

	/**
	 * Singleton instance.
	 *
	 * @var AgentClerk_Conversations|null
	 */
	private static $instance = null;

	/**
	 * Return the singleton instance.
	 *
	 * @return AgentClerk_Conversations
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Register AJAX and cron hooks.
	 * NOTE: Cron scheduling is in the activator, NOT here.
	 */
	private function __construct() {
		add_action( 'wp_ajax_agentclerk_get_conversations', array( $this, 'get_conversations' ) );
		add_action( 'wp_ajax_agentclerk_get_conversation_messages', array( $this, 'get_messages' ) );
		add_action( 'wp_ajax_agentclerk_get_stats', array( $this, 'get_stats' ) );
		add_action( 'wp_ajax_agentclerk_get_sales', array( $this, 'get_sales' ) );
		add_action( 'agentclerk_expire_sessions', array( $this, 'expire_abandoned' ) );
	}

	/**
	 * AJAX: Get paginated conversation list with optional outcome filter.
	 */
	public function get_conversations() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		global $wpdb;
		$table  = $wpdb->prefix . 'agentclerk_conversations';
		$filter = isset( $_GET['outcome'] ) ? sanitize_text_field( wp_unslash( $_GET['outcome'] ) ) : '';
		$page   = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 );
		$limit  = 20;
		$offset = ( $page - 1 ) * $limit;

		if ( $filter ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE outcome = %s ORDER BY updated_at DESC LIMIT %d OFFSET %d",
					$filter,
					$limit,
					$offset
				)
			);
			$total = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE outcome = %s", $filter )
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} ORDER BY updated_at DESC LIMIT %d OFFSET %d",
					$limit,
					$offset
				)
			);
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		wp_send_json_success( array(
			'conversations' => $rows,
			'total'         => $total,
			'page'          => $page,
			'pages'         => ceil( $total / $limit ),
		) );
	}

	/**
	 * AJAX: Get messages for a specific conversation.
	 */
	public function get_messages() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$conversation_id = isset( $_GET['conversation_id'] ) ? absint( $_GET['conversation_id'] ) : 0;
		if ( ! $conversation_id ) {
			wp_send_json_error( array( 'message' => 'Missing conversation_id.' ) );
		}

		global $wpdb;
		$messages_table = $wpdb->prefix . 'agentclerk_messages';
		$convo_table    = $wpdb->prefix . 'agentclerk_conversations';

		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content, created_at FROM {$messages_table} WHERE conversation_id = %d ORDER BY created_at ASC",
				$conversation_id
			)
		);

		$conversation = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$convo_table} WHERE id = %d", $conversation_id )
		);

		wp_send_json_success( array(
			'messages'     => $messages,
			'conversation' => $conversation,
		) );
	}

	/**
	 * AJAX: Get conversation statistics.
	 *
	 * Returns: total, today, setup, support, in_cart (quote), escalated, purchased.
	 */
	public function get_stats() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'agentclerk_conversations';
		$today = current_time( 'Y-m-d' );

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		$today_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE DATE(started_at) = %s",
				$today
			)
		);

		$setup = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE outcome = 'setup'"
		);

		$support = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE outcome = 'support'"
		);

		$in_cart = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE outcome = 'quote'"
		);

		$escalated = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE outcome = 'escalated'"
		);

		$purchased = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE outcome = 'purchased'"
		);

		$sales_today = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(sale_amount), 0) FROM {$table} WHERE outcome = 'purchased' AND DATE(updated_at) = %s",
				$today
			)
		);

		wp_send_json_success( array(
			'total'       => $total,
			'today'       => $today_count,
			'setup'       => $setup,
			'support'     => $support,
			'in_cart'     => $in_cart,
			'escalated'   => $escalated,
			'purchased'   => $purchased,
			'sales_today' => $sales_today,
		) );
	}

	/**
	 * AJAX: Get sales data with optional period filter.
	 *
	 * Returns: gross, fees, count, average, accrued_fees, transactions.
	 */
	public function get_sales() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		global $wpdb;
		$table  = $wpdb->prefix . 'agentclerk_conversations';
		$period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : 'all';

		$where = "outcome = 'purchased'";

		if ( 'month' === $period ) {
			$month_start = gmdate( 'Y-m-01 00:00:00' );
			$where      .= $wpdb->prepare( ' AND updated_at >= %s', $month_start );
		} elseif ( 'week' === $period ) {
			$week_start = gmdate( 'Y-m-d 00:00:00', strtotime( 'monday this week' ) );
			$where     .= $wpdb->prepare( ' AND updated_at >= %s', $week_start );
		} elseif ( 'today' === $period ) {
			$where .= $wpdb->prepare( ' AND DATE(updated_at) = %s', current_time( 'Y-m-d' ) );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built with prepare() above.
		$gross = (float) $wpdb->get_var(
			"SELECT COALESCE(SUM(sale_amount), 0) FROM {$table} WHERE {$where}"
		);
		$fees = (float) $wpdb->get_var(
			"SELECT COALESCE(SUM(acclerk_fee), 0) FROM {$table} WHERE {$where}"
		);
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE {$where}"
		);
		// phpcs:enable

		$avg = $count > 0 ? round( $gross / $count, 2 ) : 0;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$transactions = $wpdb->get_results(
			"SELECT id, session_id, product_name, sale_amount, acclerk_fee, buyer_type, updated_at FROM {$table} WHERE {$where} ORDER BY updated_at DESC LIMIT 50"
		);

		wp_send_json_success( array(
			'gross'        => $gross,
			'fees'         => $fees,
			'count'        => $count,
			'average'      => $avg,
			'accrued_fees' => (float) get_option( 'agentclerk_accrued_fees', 0 ),
			'license'      => get_option( 'agentclerk_license_status', 'none' ),
			'transactions' => $transactions,
		) );
	}

	/**
	 * Expire abandoned conversations (cron hook).
	 *
	 * Marks conversations with outcome 'browsing' as 'abandoned'
	 * if they have not been updated in over 2 hours.
	 */
	public function expire_abandoned() {
		global $wpdb;
		$table     = $wpdb->prefix . 'agentclerk_conversations';
		$threshold = gmdate( 'Y-m-d H:i:s', time() - 7200 );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET outcome = 'abandoned', updated_at = %s WHERE outcome = 'browsing' AND updated_at < %s",
				current_time( 'mysql' ),
				$threshold
			)
		);

		// Also expire old pending quote links.
		$quote_table = $wpdb->prefix . 'agentclerk_quote_links';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$quote_table} SET status = 'expired' WHERE status = 'pending' AND expires_at < %s",
				current_time( 'mysql' )
			)
		);
	}
}
