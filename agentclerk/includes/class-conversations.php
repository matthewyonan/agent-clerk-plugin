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
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE outcome = %s ORDER BY updated_at DESC LIMIT %d OFFSET %d",
					$table,
					$filter,
					$limit,
					$offset
				)
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE outcome = %s", $table, $filter )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i ORDER BY updated_at DESC LIMIT %d OFFSET %d",
					$table,
					$limit,
					$offset
				)
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM %i", $table )
			);
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$messages = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content, created_at FROM %i WHERE conversation_id = %d ORDER BY created_at ASC",
				$messages_table,
				$conversation_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$conversation = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $convo_table, $conversation_id )
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

		$cache_key = 'agentclerk_stats';
		$cached    = wp_cache_get( $cache_key, 'agentclerk' );

		if ( false !== $cached ) {
			wp_send_json_success( $cached );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM %i", $table )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$today_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM %i WHERE DATE(started_at) = %s",
				$table,
				$today
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$setup = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE outcome = 'setup'", $table )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$support = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE outcome = 'support'", $table )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$in_cart = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE outcome = 'quote'", $table )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$escalated = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE outcome = 'escalated'", $table )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$purchased = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE outcome = 'purchased'", $table )
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sales_today = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(sale_amount), 0) FROM %i WHERE outcome = 'purchased' AND DATE(updated_at) = %s",
				$table,
				$today
			)
		);

		$stats = array(
			'total'       => $total,
			'today'       => $today_count,
			'setup'       => $setup,
			'support'     => $support,
			'in_cart'     => $in_cart,
			'escalated'   => $escalated,
			'purchased'   => $purchased,
			'sales_today' => $sales_today,
		);

		wp_cache_set( $cache_key, $stats, 'agentclerk', 60 );

		wp_send_json_success( $stats );
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$gross = (float) $wpdb->get_var(
			"SELECT COALESCE(SUM(sale_amount), 0) FROM {$table} WHERE {$where}" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic WHERE built via prepare().
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$fees = (float) $wpdb->get_var(
			"SELECT COALESCE(SUM(acclerk_fee), 0) FROM {$table} WHERE {$where}" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic WHERE built via prepare().
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE {$where}" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic WHERE built via prepare().
		);
		$avg = $count > 0 ? round( $gross / $count, 2 ) : 0;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$transactions = $wpdb->get_results(
			"SELECT id, session_id, product_name, sale_amount, acclerk_fee, buyer_type, updated_at FROM {$table} WHERE {$where} ORDER BY updated_at DESC LIMIT 50" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic WHERE built via prepare().
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET outcome = 'abandoned', updated_at = %s WHERE outcome = 'browsing' AND updated_at < %s",
				$table,
				current_time( 'mysql' ),
				$threshold
			)
		);

		// Also expire old pending quote links.
		$quote_table = $wpdb->prefix . 'agentclerk_quote_links';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'expired' WHERE status = 'pending' AND expires_at < %s",
				$quote_table,
				current_time( 'mysql' )
			)
		);
	}
}
