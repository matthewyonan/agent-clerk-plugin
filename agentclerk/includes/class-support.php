<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AgentClerk_Support {

    public function __construct() {
        add_action( 'wp_ajax_agentclerk_escalate', [ $this, 'handle_escalation' ] );
        add_action( 'wp_ajax_nopriv_agentclerk_escalate', [ $this, 'handle_escalation' ] );
        add_action( 'wp_ajax_agentclerk_get_escalations', [ $this, 'get_escalations' ] );
        add_action( 'wp_ajax_agentclerk_toggle_read', [ $this, 'toggle_read' ] );
        add_action( 'wp_ajax_agentclerk_support_chat', [ $this, 'handle_support_chat' ] );
        add_filter( 'the_content', [ $this, 'maybe_append_support_chat' ] );
    }

    public function handle_escalation() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );

        $session_id = isset( $_COOKIE['agentclerk_session'] ) ? sanitize_text_field( $_COOKIE['agentclerk_session'] ) : '';
        $email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

        if ( empty( $session_id ) || empty( $email ) ) {
            wp_send_json_error( [ 'message' => 'Session and email are required.' ] );
        }

        global $wpdb;
        $table        = $wpdb->prefix . 'agentclerk_conversations';
        $conversation = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE session_id = %s", $session_id )
        );

        if ( ! $conversation ) {
            wp_send_json_error( [ 'message' => 'Conversation not found.' ] );
        }

        $wpdb->update( $table, [
            'outcome'    => 'escalated',
            'updated_at' => current_time( 'mysql' ),
        ], [ 'id' => $conversation->id ] );

        $config      = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
        $seller_email = $config['escalation_email'] ?? get_option( 'admin_email' );

        $messages_table = $wpdb->prefix . 'agentclerk_messages';
        $first_message  = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT content FROM {$messages_table} WHERE conversation_id = %d AND role = 'user' ORDER BY created_at ASC LIMIT 1",
                $conversation->id
            )
        );

        wp_mail(
            $seller_email,
            'AgentClerk: Customer Escalation',
            sprintf(
                "A customer has requested human support.\n\nEmail: %s\nOpening message: %s\n\nView in your WordPress admin under AgentClerk → Support.",
                $email,
                $first_message ?: '(no message)'
            )
        );

        $admin_users = get_users( [ 'role' => 'administrator', 'fields' => 'ID' ] );
        foreach ( $admin_users as $user_id ) {
            $notif_key = 'agentclerk_escalation_' . $conversation->id;
            update_user_meta( $user_id, $notif_key, [
                'conversation_id' => $conversation->id,
                'email'           => $email,
                'message'         => $first_message,
                'read'            => false,
                'created_at'      => current_time( 'mysql' ),
            ] );
        }

        $escalation_message = $config['escalation_message'] ?? 'A support agent will follow up via email within 24 hours.';
        wp_send_json_success( [ 'message' => $escalation_message ] );
    }

    public function get_escalations() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        global $wpdb;
        $table = $wpdb->prefix . 'agentclerk_conversations';
        $rows  = $wpdb->get_results(
            "SELECT * FROM {$table} WHERE outcome = 'escalated' ORDER BY updated_at DESC LIMIT 50"
        );

        $escalations = [];
        foreach ( $rows as $row ) {
            $messages_table = $wpdb->prefix . 'agentclerk_messages';
            $first_message  = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT content FROM {$messages_table} WHERE conversation_id = %d AND role = 'user' ORDER BY created_at ASC LIMIT 1",
                    $row->id
                )
            );

            $notif_key = 'agentclerk_escalation_' . $row->id;
            $notif     = get_user_meta( get_current_user_id(), $notif_key, true );

            $escalations[] = [
                'id'            => $row->id,
                'session_id'    => $row->session_id,
                'first_message' => $first_message ?: '',
                'email'         => $notif['email'] ?? '',
                'read'          => $notif['read'] ?? false,
                'created_at'    => $row->updated_at,
            ];
        }

        wp_send_json_success( [ 'escalations' => $escalations ] );
    }

    public function toggle_read() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $conversation_id = (int) ( $_POST['conversation_id'] ?? 0 );
        $notif_key       = 'agentclerk_escalation_' . $conversation_id;
        $notif           = get_user_meta( get_current_user_id(), $notif_key, true );

        if ( $notif ) {
            $notif['read'] = ! $notif['read'];
            update_user_meta( get_current_user_id(), $notif_key, $notif );
        }

        wp_send_json_success();
    }

    public function handle_support_chat() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        $history = isset( $_POST['history'] ) ? json_decode( wp_unslash( $_POST['history'] ), true ) : [];

        if ( empty( $message ) ) {
            wp_send_json_error( [ 'message' => 'Message is required.' ] );
        }

        if ( ! is_array( $history ) ) {
            $history = [];
        }

        $history[] = [ 'role' => 'user', 'content' => $message ];

        $system = "You are the AgentClerk plugin support assistant. Help the seller with questions about configuring and using the AgentClerk WordPress plugin. Do not answer questions about the seller's own products or customers — only about AgentClerk plugin functionality.";

        $response = AgentClerk::backend_request( '/agent/chat', [
            'method' => 'POST',
            'body'   => [
                'system'   => $system,
                'messages' => $history,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $text = $body['content'][0]['text'] ?? $body['message'] ?? '';

        wp_send_json_success( [ 'message' => $text ] );
    }

    public function maybe_append_support_chat( $content ) {
        if ( ! is_singular( 'page' ) ) {
            return $content;
        }

        $config       = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
        $support_page = $config['support_page_id'] ?? 0;

        if ( ! $support_page || get_the_ID() !== (int) $support_page ) {
            return $content;
        }

        $nonce = wp_create_nonce( 'agentclerk_nonce' );
        $ajax  = admin_url( 'admin-ajax.php' );

        $embed = '<div id="agentclerk-support-embed" class="agentclerk-support-chat">';
        $embed .= '<div class="agentclerk-messages" id="agentclerk-support-messages"></div>';
        $embed .= '<div class="agentclerk-input-wrap">';
        $embed .= '<input type="text" id="agentclerk-support-input" placeholder="Ask a question..." />';
        $embed .= '<button id="agentclerk-support-send">Send</button>';
        $embed .= '</div>';
        $embed .= '<div id="agentclerk-escalation-panel" style="display:none;">';
        $embed .= '<p>Would you like to speak with a human? Enter your email and we\'ll follow up.</p>';
        $embed .= '<input type="email" id="agentclerk-escalation-email" placeholder="your@email.com" />';
        $embed .= '<button id="agentclerk-escalation-confirm">Confirm</button>';
        $embed .= '</div>';
        $embed .= '</div>';
        $embed .= '<script>var agentclerkSupport={ajaxUrl:"' . esc_js( $ajax ) . '",nonce:"' . esc_js( $nonce ) . '"};</script>';

        return $content . $embed;
    }
}
