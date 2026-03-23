<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AgentClerk_Conversations {

    public function __construct() {
        add_action( 'wp_ajax_agentclerk_get_conversations', [ $this, 'get_conversations' ] );
        add_action( 'wp_ajax_agentclerk_get_conversation_messages', [ $this, 'get_messages' ] );
        add_action( 'wp_ajax_agentclerk_get_stats', [ $this, 'get_stats' ] );
        add_action( 'wp_ajax_agentclerk_get_sales', [ $this, 'get_sales' ] );
        add_action( 'agentclerk_expire_sessions', [ $this, 'expire_abandoned' ] );

        if ( ! wp_next_scheduled( 'agentclerk_expire_sessions' ) ) {
            wp_schedule_event( time(), 'hourly', 'agentclerk_expire_sessions' );
        }
    }

    public function get_conversations() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        global $wpdb;
        $table  = $wpdb->prefix . 'agentclerk_conversations';
        $filter = isset( $_GET['outcome'] ) ? sanitize_text_field( wp_unslash( $_GET['outcome'] ) ) : '';
        $page   = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $limit  = 20;
        $offset = ( $page - 1 ) * $limit;

        $where = '1=1';
        $args  = [];
        if ( $filter ) {
            $where .= ' AND outcome = %s';
            $args[] = $filter;
        }

        $args[] = $limit;
        $args[] = $offset;

        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where} ORDER BY updated_at DESC LIMIT %d OFFSET %d",
                ...$args
            )
        );
        $total = $wpdb->get_var(
            $args ? $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where}", $filter ) :
                    "SELECT COUNT(*) FROM {$table}"
        );

        wp_send_json_success( [ 'conversations' => $rows, 'total' => (int) $total ] );
    }

    public function get_messages() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $conversation_id = (int) ( $_GET['conversation_id'] ?? 0 );
        if ( ! $conversation_id ) {
            wp_send_json_error( [ 'message' => 'Missing conversation_id.' ] );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'agentclerk_messages';
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT role, content, created_at FROM {$table} WHERE conversation_id = %d ORDER BY created_at ASC",
                $conversation_id
            )
        );

        wp_send_json_success( [ 'messages' => $rows ] );
    }

    public function get_stats() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'agentclerk_conversations';
        $today = current_time( 'Y-m-d' );

        $total      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
        $today_ct   = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE DATE(started_at) = %s", $today
        ) );
        $setup      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE outcome = 'setup'" );
        $support    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE outcome = 'support'" );
        $quote      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE outcome = 'quote'" );
        $escalated  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE outcome = 'escalated'" );
        $purchased  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE outcome = 'purchased'" );
        $sales_today = $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(sale_amount), 0) FROM {$table} WHERE outcome = 'purchased' AND DATE(updated_at) = %s",
            $today
        ) );

        wp_send_json_success( [
            'total'       => $total,
            'today'       => $today_ct,
            'setup'       => $setup,
            'support'     => $support,
            'in_cart'     => $quote,
            'escalated'   => $escalated,
            'purchased'   => $purchased,
            'sales_today' => (float) $sales_today,
        ] );
    }

    public function get_sales() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        global $wpdb;
        $table  = $wpdb->prefix . 'agentclerk_conversations';
        $period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : 'all';

        $where = "outcome = 'purchased'";
        if ( $period === 'month' ) {
            $where .= $wpdb->prepare(
                " AND updated_at >= %s",
                gmdate( 'Y-m-01 00:00:00' )
            );
        }

        $gross = (float) $wpdb->get_var( "SELECT COALESCE(SUM(sale_amount), 0) FROM {$table} WHERE {$where}" );
        $fees  = (float) $wpdb->get_var( "SELECT COALESCE(SUM(acclerk_fee), 0) FROM {$table} WHERE {$where}" );
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
        $avg   = $count > 0 ? $gross / $count : 0;

        $transactions = $wpdb->get_results(
            "SELECT id, session_id, sale_amount, acclerk_fee, updated_at FROM {$table} WHERE {$where} ORDER BY updated_at DESC LIMIT 50"
        );

        wp_send_json_success( [
            'gross'        => $gross,
            'fees'         => $fees,
            'count'        => $count,
            'average'      => round( $avg, 2 ),
            'accrued_fees' => (float) get_option( 'agentclerk_accrued_fees', 0 ),
            'transactions' => $transactions,
        ] );
    }

    public function expire_abandoned() {
        global $wpdb;
        $table     = $wpdb->prefix . 'agentclerk_conversations';
        $threshold = gmdate( 'Y-m-d H:i:s', time() - 7200 );

        $wpdb->query( $wpdb->prepare(
            "UPDATE {$table} SET outcome = 'abandoned' WHERE outcome = 'browsing' AND updated_at < %s",
            $threshold
        ) );
    }
}
