<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Support and escalation handler for AgentClerk.
 *
 * Manages escalation (respects escalation_method: both/email/wp),
 * read/unread toggle via user_meta, plugin support chat (routed through
 * backend), and buyer support page embed via the_content filter.
 *
 * @since 1.0.0
 */
class AgentClerk_Support {

	/**
	 * Singleton instance.
	 *
	 * @var AgentClerk_Support|null
	 */
	private static $instance = null;

	/**
	 * Return the singleton instance.
	 *
	 * @return AgentClerk_Support
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
		add_action( 'wp_ajax_agentclerk_escalate', array( $this, 'handle_escalation' ) );
		add_action( 'wp_ajax_nopriv_agentclerk_escalate', array( $this, 'handle_escalation' ) );
		add_action( 'wp_ajax_agentclerk_get_escalations', array( $this, 'get_escalations' ) );
		add_action( 'wp_ajax_agentclerk_toggle_read', array( $this, 'toggle_read' ) );
		add_action( 'wp_ajax_agentclerk_support_chat', array( $this, 'handle_support_chat' ) );
		add_filter( 'the_content', array( $this, 'maybe_append_support_chat' ) );
	}

	/**
	 * Handle buyer escalation request.
	 *
	 * Respects the escalation_method setting:
	 *   'both'  - Send email AND create WP admin notification (default).
	 *   'email' - Send email only.
	 *   'wp'    - Create WP admin notification only.
	 */
	public function handle_escalation() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );

		$session_id = isset( $_COOKIE['agentclerk_session'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['agentclerk_session'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

		if ( empty( $session_id ) || empty( $email ) ) {
			wp_send_json_error( array( 'message' => 'Session and email are required.' ) );
		}

		global $wpdb;
		$table        = $wpdb->prefix . 'agentclerk_conversations';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$conversation = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM %i WHERE session_id = %s", $table, $session_id )
		);

		if ( ! $conversation ) {
			wp_send_json_error( array( 'message' => 'Conversation not found.' ) );
		}

		// Mark conversation as escalated.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$table,
			array(
				'outcome'    => 'escalated',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $conversation->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		$config            = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		$escalation_method = isset( $config['escalation_method'] ) ? $config['escalation_method'] : 'both';
		$seller_email      = isset( $config['escalation_email'] ) ? $config['escalation_email'] : get_option( 'admin_email' );

		// Get the first user message for context.
		$messages_table = $wpdb->prefix . 'agentclerk_messages';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$first_message  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT content FROM %i WHERE conversation_id = %d AND role = 'user' ORDER BY created_at ASC LIMIT 1",
				$messages_table,
				$conversation->id
			)
		);

		// Determine product context.
		$product_name = '';
		if ( ! empty( $conversation->product_name ) ) {
			$product_name = $conversation->product_name;
		}

		// Send email notification.
		if ( 'both' === $escalation_method || 'email' === $escalation_method ) {
			$subject = sprintf(
				/* translators: %s: product name or empty */
				__( 'AgentClerk: Customer Escalation%s', 'agentclerk' ),
				$product_name ? ' - ' . $product_name : ''
			);

			$body = sprintf(
				"A customer has requested human support.\n\nCustomer email: %s\nOpening message: %s\n%s\nView in your WordPress admin under AgentClerk > Support.\n%s",
				$email,
				$first_message ? $first_message : '(no message)',
				$product_name ? "Product: {$product_name}\n" : '',
				admin_url( 'admin.php?page=agentclerk-support' )
			);

			wp_mail( $seller_email, $subject, $body );
		}

		// Create WP admin notification via user_meta.
		if ( 'both' === $escalation_method || 'wp' === $escalation_method ) {
			$admin_users = get_users( array( 'role' => 'administrator', 'fields' => 'ID' ) );

			foreach ( $admin_users as $user_id ) {
				$notif_key = 'agentclerk_escalation_' . $conversation->id;
				update_user_meta( $user_id, $notif_key, array(
					'conversation_id' => $conversation->id,
					'email'           => $email,
					'message'         => $first_message ? $first_message : '',
					'product_name'    => $product_name,
					'read'            => false,
					'created_at'      => current_time( 'mysql' ),
				) );
			}
		}

		$escalation_message = isset( $config['escalation_message'] )
			? $config['escalation_message']
			: 'A support agent will follow up via email within 24 hours.';

		wp_send_json_success( array( 'message' => $escalation_message ) );
	}

	/**
	 * AJAX: Get list of escalated conversations.
	 */
	public function get_escalations() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'agentclerk_conversations';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM %i WHERE outcome = 'escalated' ORDER BY updated_at DESC LIMIT 50", $table )
		);

		$escalations = array();
		$current_user_id = get_current_user_id();

		foreach ( $rows as $row ) {
			$messages_table = $wpdb->prefix . 'agentclerk_messages';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$first_message  = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT content FROM %i WHERE conversation_id = %d AND role = 'user' ORDER BY created_at ASC LIMIT 1",
					$messages_table,
					$row->id
				)
			);

			$notif_key = 'agentclerk_escalation_' . $row->id;
			$notif     = get_user_meta( $current_user_id, $notif_key, true );

			$escalations[] = array(
				'id'            => $row->id,
				'session_id'    => $row->session_id,
				'first_message' => ! empty( $row->first_message ) ? $row->first_message : ( $first_message ? $first_message : '' ),
				'product_name'  => $row->product_name ? $row->product_name : '',
				'email'         => isset( $notif['email'] ) ? $notif['email'] : '',
				'read'          => isset( $notif['read'] ) ? (bool) $notif['read'] : false,
				'created_at'    => $row->updated_at,
			);
		}

		wp_send_json_success( array( 'escalations' => $escalations ) );
	}

	/**
	 * AJAX: Toggle read/unread status for an escalation.
	 */
	public function toggle_read() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$conversation_id = isset( $_POST['conversation_id'] ) ? absint( $_POST['conversation_id'] ) : 0;
		if ( ! $conversation_id ) {
			wp_send_json_error( array( 'message' => 'Missing conversation_id.' ) );
		}

		$notif_key = 'agentclerk_escalation_' . $conversation_id;
		$notif     = get_user_meta( get_current_user_id(), $notif_key, true );

		if ( $notif ) {
			$notif['read'] = ! $notif['read'];
			update_user_meta( get_current_user_id(), $notif_key, $notif );
			wp_send_json_success( array( 'read' => $notif['read'] ) );
		}

		wp_send_json_error( array( 'message' => 'Notification not found.' ) );
	}

	/**
	 * AJAX: Handle plugin support chat (admin only, routed through backend).
	 */
	public function handle_support_chat() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$history = isset( $_POST['history'] ) ? json_decode( sanitize_text_field( wp_unslash( $_POST['history'] ) ), true ) : array();

		if ( empty( $message ) ) {
			wp_send_json_error( array( 'message' => 'Message is required.' ) );
		}

		if ( ! is_array( $history ) ) {
			$history = array();
		}

		// Sanitize history.
		$history = array_map( function ( $msg ) {
			return array(
				'role'    => sanitize_text_field( $msg['role'] ?? 'user' ),
				'content' => sanitize_textarea_field( $msg['content'] ?? '' ),
			);
		}, $history );

		$history[] = array( 'role' => 'user', 'content' => $message );

		$system = "You are the AgentClerk plugin support assistant. Help the seller with questions about configuring and using the AgentClerk WordPress plugin. " .
			"Topics you can help with: plugin setup, onboarding, agent configuration, billing, API keys, product visibility, escalation settings, and troubleshooting. " .
			"Do not answer questions about the seller's own products or customers -- only about AgentClerk plugin functionality.";

		$response = AgentClerk::backend_request( '/support/chat', array(
			'method' => 'POST',
			'body'   => array(
				'system'   => $system,
				'messages' => $history,
			),
		) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$text = '';

		// Handle standard Anthropic response format.
		if ( isset( $body['content'] ) && is_array( $body['content'] ) ) {
			foreach ( $body['content'] as $block ) {
				if ( 'text' === ( $block['type'] ?? '' ) ) {
					$text .= $block['text'];
				}
			}
		}

		// Fallback.
		if ( empty( $text ) && isset( $body['message'] ) ) {
			$text = $body['message'];
		}

		wp_send_json_success( array( 'message' => $text ) );
	}

	/**
	 * Append buyer support chat embed to the designated support page.
	 *
	 * @param string $content Post content.
	 * @return string Modified content.
	 */
	public function maybe_append_support_chat( $content ) {
		if ( ! is_singular( 'page' ) ) {
			return $content;
		}

		$config       = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		$support_page = isset( $config['support_page_id'] ) ? (int) $config['support_page_id'] : 0;

		if ( ! $support_page || get_the_ID() !== $support_page ) {
			return $content;
		}

		if ( 'active' !== get_option( 'agentclerk_plugin_status' ) ) {
			return $content;
		}

		$nonce      = wp_create_nonce( 'agentclerk_nonce' );
		$ajax_url   = admin_url( 'admin-ajax.php' );
		$agent_name = isset( $config['agent_name'] ) ? esc_attr( $config['agent_name'] ) : 'AgentClerk';
		$escalation = isset( $config['escalation_message'] ) ? esc_attr( $config['escalation_message'] ) : '';

		$embed  = '<div id="agentclerk-support-embed" class="agentclerk-support-chat">';
		$embed .= '<div class="agentclerk-support-header">';
		$embed .= '<h3>' . esc_html( $agent_name ) . ' ' . esc_html__( 'Support', 'agentclerk' ) . '</h3>';
		$embed .= '</div>';
		$embed .= '<div class="agentclerk-messages" id="agentclerk-support-messages"></div>';
		$embed .= '<div class="agentclerk-input-wrap">';
		$embed .= '<input type="text" id="agentclerk-support-input" placeholder="' . esc_attr__( 'Ask a question...', 'agentclerk' ) . '" />';
		$embed .= '<button id="agentclerk-support-send">' . esc_html__( 'Send', 'agentclerk' ) . '</button>';
		$embed .= '</div>';
		$embed .= '<div id="agentclerk-escalation-panel" style="display:none;">';
		$embed .= '<p>' . esc_html__( 'Would you like to speak with a human? Enter your email and we\'ll follow up.', 'agentclerk' ) . '</p>';
		$embed .= '<input type="email" id="agentclerk-escalation-email" placeholder="' . esc_attr__( 'your@email.com', 'agentclerk' ) . '" />';
		$embed .= '<button id="agentclerk-escalation-confirm">' . esc_html__( 'Request Human Support', 'agentclerk' ) . '</button>';
		$embed .= '</div>';
		$embed .= '</div>';

		$embed .= '<script>';
		$embed .= 'var agentclerkSupport=' . wp_json_encode( array(
			'ajaxUrl'            => $ajax_url,
			'nonce'              => $nonce,
			'agentName'          => $agent_name,
			'escalationMessage'  => $escalation,
		) ) . ';';
		$embed .= '</script>';

		return $content . $embed;
	}
}
