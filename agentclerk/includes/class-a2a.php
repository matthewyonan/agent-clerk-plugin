<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A2A (Agent-to-Agent) Protocol handler for AgentClerk.
 *
 * Implements the A2A v1.0 specification (https://github.com/a2aproject/A2A)
 * so external AI agents can discover, message, and transact with the store's
 * AgentClerk agent via standardised endpoints.
 *
 * Endpoints:
 *   GET  /.well-known/agent-card.json   — Agent Card (discovery)
 *   POST /a2a/message:send              — Send message (blocking)
 *   POST /a2a/message:stream            — Send message (SSE streaming)
 *   GET  /a2a/tasks/{id}                — Get task status
 *   GET  /a2a/tasks                     — List tasks
 *   POST /a2a/tasks/{id}:cancel         — Cancel task
 *   POST /a2a/tasks/{id}:subscribe      — Subscribe to task (SSE)
 *   POST /a2a/tasks/{id}/pushNotificationConfigs — Register webhook
 *   GET  /a2a/tasks/{id}/pushNotificationConfigs — List webhooks
 *   DELETE /a2a/tasks/{id}/pushNotificationConfigs/{cid} — Remove webhook
 *
 * @since 1.1.1
 */
class AgentClerk_A2A {

	private static $instance = null;

	const A2A_VERSION  = '1.0';
	const CONTENT_TYPE = 'application/a2a+json';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'template_redirect', array( $this, 'handle_request' ) );
		// Serve .well-known/agent-card.json early — rewrite rules don't
		// work for dotfile paths on most hosts (Apache/Nginx block them).
		add_action( 'init', array( $this, 'handle_well_known' ) );
	}

	/**
	 * Serve /.well-known/agent-card.json via early init hook.
	 * This bypasses WordPress rewrite rules which fail for dotfile paths.
	 */
	public function handle_well_known() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}
		$uri  = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$path = wp_parse_url( $uri, PHP_URL_PATH );

		// Agent Card — dotfile paths are blocked by most server configs.
		if ( '/.well-known/agent-card.json' === $path ) {
			$this->serve_agent_card();
		}

		// A2A message endpoints — colons in URLs can fail on some hosts.
		if ( '/a2a/message:send' === $path ) {
			$this->handle_send_message( true );
		}
		if ( '/a2a/message:stream' === $path ) {
			$this->handle_send_message( false );
		}
	}

	/* =====================================================================
	 * Request Routing
	 * ================================================================== */

	public function handle_request() {
		// Agent Card (GET).
		if ( get_query_var( 'agentclerk_a2a_card' ) ) {
			$this->serve_agent_card();
		}

		// Message endpoints (POST).
		$send = get_query_var( 'agentclerk_a2a_send' );
		if ( $send ) {
			$this->handle_send_message( 'send' === $send );
		}

		// Task endpoints.
		$task_id = get_query_var( 'agentclerk_a2a_task' );
		if ( $task_id ) {
			$this->route_task_request( sanitize_text_field( $task_id ) );
		}

		// Task list.
		if ( get_query_var( 'agentclerk_a2a_tasks' ) ) {
			$this->handle_list_tasks();
		}

		// Push notification config.
		$push_task = get_query_var( 'agentclerk_a2a_push' );
		if ( $push_task ) {
			$this->route_push_config( sanitize_text_field( $push_task ) );
		}
	}

	/* =====================================================================
	 * Agent Card
	 * ================================================================== */

	private function serve_agent_card() {
		$config   = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		$site_url = get_site_url();

		$card = array(
			'name'                => $config['agent_name'] ?? 'AgentClerk',
			'description'         => ( $config['business_desc'] ?? '' )
				? $config['business_desc']
				: 'AI sales and support agent for ' . get_bloginfo( 'name' ),
			'version'             => AGENTCLERK_VERSION,
			'provider'            => array(
				'organization' => get_bloginfo( 'name' ),
				'url'          => $site_url,
			),
			'documentationUrl'    => 'https://agentclerk.io/docs',
			'iconUrl'             => $site_url . '/wp-content/plugins/agentclerk/public/icon.png',
			'supportedInterfaces' => array(
				array(
					'url'             => $site_url . '/a2a',
					'protocolBinding' => 'HTTP+JSON',
					'protocolVersion' => self::A2A_VERSION,
				),
			),
			'capabilities'        => array(
				'streaming'         => true,
				'pushNotifications' => true,
				'extendedAgentCard' => false,
			),
			'defaultInputModes'   => array( 'text/plain', 'application/json' ),
			'defaultOutputModes'  => array( 'text/plain', 'application/json' ),
			'skills'              => $this->build_skills(),
			'securitySchemes'     => array(
				'apiKey' => array(
					'type'     => 'apiKey',
					'in'       => 'header',
					'name'     => 'X-AgentClerk-Key',
				),
			),
			'securityRequirements' => array(),
		);

		$this->send_json( $card );
	}

	private function build_skills() {
		$skills = array(
			array(
				'id'          => 'product_inquiry',
				'name'        => 'Product Inquiry',
				'description' => 'Answer questions about products, pricing, availability, and features.',
				'tags'        => array( 'shopping', 'products', 'pricing', 'catalog' ),
				'examples'    => array(
					'What products do you sell?',
					'How much does your software cost?',
					'Is this product available?',
				),
			),
			array(
				'id'          => 'purchase',
				'name'        => 'Purchase',
				'description' => 'Generate checkout links for products the buyer wants to purchase.',
				'tags'        => array( 'shopping', 'checkout', 'purchase', 'buy' ),
				'examples'    => array(
					'I want to buy the Pro plan.',
					'Add this to my cart.',
				),
			),
			array(
				'id'          => 'support',
				'name'        => 'Customer Support',
				'description' => 'Answer support questions using the store knowledge base and policies.',
				'tags'        => array( 'support', 'help', 'refund', 'policy' ),
				'examples'    => array(
					'What is your refund policy?',
					'How do I install the plugin?',
					'I need help with my order.',
				),
			),
		);

		// Add product-specific skills from WooCommerce.
		if ( function_exists( 'wc_get_products' ) ) {
			$products = wc_get_products( array( 'status' => 'publish', 'limit' => 10 ) );
			foreach ( $products as $product ) {
				$skills[] = array(
					'id'          => 'product_' . $product->get_id(),
					'name'        => 'Buy ' . $product->get_name(),
					'description' => 'Purchase ' . $product->get_name() . ' ($' . $product->get_price() . ')',
					'tags'        => array( 'product', 'purchase', sanitize_title( $product->get_name() ) ),
				);
			}
		}

		return $skills;
	}

	/* =====================================================================
	 * Send Message (Blocking + Streaming)
	 * ================================================================== */

	private function handle_send_message( $blocking = true ) {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		if ( 'POST' !== $method ) {
			$this->send_error( 'UnsupportedOperationError', 'Method not allowed.', 405 );
		}

		$this->validate_version_header();

		$body = json_decode( file_get_contents( 'php://input' ), true );
		if ( empty( $body['message'] ) ) {
			$this->send_error( 'InvalidAgentResponseError', 'Missing message in request body.', 400 );
		}

		$msg = $body['message'];

		// Extract text from message parts.
		$text = $this->extract_text_from_parts( $msg['parts'] ?? array() );
		if ( empty( $text ) ) {
			$this->send_error( 'ContentTypeNotSupportedError', 'No text content found in message parts.', 415 );
		}

		// Map A2A contextId to session_id. Create one if not provided.
		$context_id = sanitize_text_field( $msg['contextId'] ?? '' );
		$session_id = $context_id ? 'a2a_' . $context_id : 'a2a_' . bin2hex( random_bytes( 16 ) );
		if ( empty( $context_id ) ) {
			$context_id = substr( $session_id, 4 );
		}

		$existing_task_id = sanitize_text_field( $msg['taskId'] ?? '' );
		$config           = $body['configuration'] ?? array();
		$return_immediate = ! empty( $config['returnImmediately'] );

		// Process through the shared agent.
		$agent  = AgentClerk_Agent::instance();
		$result = $agent->process_chat( $text, $session_id, 'agent', false );

		if ( is_wp_error( $result ) ) {
			// Create failed task.
			$task_id = $this->create_task( $context_id, $session_id, 'TASK_STATE_FAILED' );
			$this->update_task_error( $task_id, $result->get_error_message() );
			$this->send_json( array(
				'task' => $this->get_task_response( $task_id ),
			) );
		}

		// Determine task state based on response.
		$has_question = $this->response_is_question( $result['message'] ?? '' );
		$state        = $has_question ? 'TASK_STATE_INPUT_REQUIRED' : 'TASK_STATE_COMPLETED';

		// Create or update task.
		$task_id = $existing_task_id
			? $existing_task_id
			: $this->create_task( $context_id, $session_id, $state );

		if ( $existing_task_id ) {
			$this->update_task_state( $task_id, $state );
		}

		// Store messages on task.
		$this->store_task_message( $task_id, 'ROLE_USER', $text, $msg['messageId'] ?? null );
		$agent_msg_id = $this->store_task_message( $task_id, 'ROLE_AGENT', $result['message'] ?? '' );

		// Create artifact for quote links.
		if ( ! empty( $result['quote_link'] ) ) {
			$this->store_task_artifact( $task_id, 'checkout_link', 'Checkout Link', array(
				array(
					'data' => array(
						'checkout_url' => $result['quote_link'],
						'type'         => 'checkout_link',
					),
					'metadata' => array( 'mediaType' => 'application/json' ),
				),
			) );
		}

		// Build response.
		if ( ! $blocking ) {
			// SSE streaming response.
			$this->stream_task_response( $task_id, $result );
			return;
		}

		$this->send_json( array(
			'task' => $this->get_task_response( $task_id ),
		) );
	}

	/* =====================================================================
	 * SSE Streaming
	 * ================================================================== */

	private function stream_task_response( $task_id, $result ) {
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' );

		// Send status update: working.
		$this->send_sse_event( array(
			'statusUpdate' => array(
				'taskId'    => $task_id,
				'contextId' => $this->get_task_context( $task_id ),
				'status'    => array(
					'state'   => 'TASK_STATE_WORKING',
					'message' => array(
						'role'  => 'ROLE_AGENT',
						'parts' => array( array( 'text' => 'Processing your request...' ) ),
					),
				),
			),
		) );

		if ( function_exists( 'ob_flush' ) ) {
			ob_flush();
		}
		flush();

		// Send the agent message.
		$this->send_sse_event( array(
			'message' => array(
				'messageId' => wp_generate_uuid4(),
				'role'      => 'ROLE_AGENT',
				'parts'     => array( array( 'text' => $result['message'] ?? '' ) ),
				'contextId' => $this->get_task_context( $task_id ),
				'taskId'    => $task_id,
			),
		) );

		// Send artifact if quote link exists.
		if ( ! empty( $result['quote_link'] ) ) {
			$this->send_sse_event( array(
				'artifactUpdate' => array(
					'taskId'    => $task_id,
					'contextId' => $this->get_task_context( $task_id ),
					'artifact'  => array(
						'artifactId' => 'checkout_' . $task_id,
						'name'       => 'Checkout Link',
						'parts'      => array( array(
							'data' => array(
								'checkout_url' => $result['quote_link'],
								'type'         => 'checkout_link',
							),
						) ),
					),
					'lastChunk' => true,
				),
			) );
		}

		// Send final status.
		$has_question = $this->response_is_question( $result['message'] ?? '' );
		$final_state  = $has_question ? 'TASK_STATE_INPUT_REQUIRED' : 'TASK_STATE_COMPLETED';

		$this->send_sse_event( array(
			'statusUpdate' => array(
				'taskId'    => $task_id,
				'contextId' => $this->get_task_context( $task_id ),
				'status'    => array( 'state' => $final_state ),
			),
		) );

		if ( function_exists( 'ob_flush' ) ) {
			ob_flush();
		}
		flush();
		exit;
	}

	private function send_sse_event( $data ) {
		echo 'data: ' . wp_json_encode( $data ) . "\n\n";
	}

	/* =====================================================================
	 * Task Endpoints
	 * ================================================================== */

	private function route_task_request( $task_id ) {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		$this->validate_version_header();

		// POST /a2a/tasks/{id}:cancel
		if ( 'POST' === $method && false !== strpos( $uri, ':cancel' ) ) {
			$this->handle_cancel_task( $task_id );
			return;
		}

		// POST /a2a/tasks/{id}:subscribe
		if ( 'POST' === $method && false !== strpos( $uri, ':subscribe' ) ) {
			$this->handle_subscribe_task( $task_id );
			return;
		}

		// Push notification config CRUD.
		if ( false !== strpos( $uri, 'pushNotificationConfigs' ) ) {
			$this->route_push_config( $task_id );
			return;
		}

		// GET /a2a/tasks/{id}
		if ( 'GET' === $method ) {
			$this->handle_get_task( $task_id );
			return;
		}

		$this->send_error( 'UnsupportedOperationError', 'Unsupported method.', 405 );
	}

	private function handle_get_task( $task_id ) {
		$task = $this->get_task_response( $task_id );
		if ( ! $task ) {
			$this->send_error( 'TaskNotFoundError', 'Task not found.', 404 );
		}
		$this->send_json( array( 'task' => $task ) );
	}

	private function handle_list_tasks() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		if ( 'GET' !== $method ) {
			$this->send_error( 'UnsupportedOperationError', 'Method not allowed.', 405 );
		}

		$this->validate_version_header();

		$context_id = isset( $_GET['contextId'] ) ? sanitize_text_field( wp_unslash( $_GET['contextId'] ) ) : '';
		$limit      = isset( $_GET['limit'] ) ? min( absint( wp_unslash( $_GET['limit'] ) ), 100 ) : 20;
		$offset     = isset( $_GET['offset'] ) ? absint( wp_unslash( $_GET['offset'] ) ) : 0;

		global $wpdb;
		$table = $wpdb->prefix . 'agentclerk_a2a_tasks';

		$where = '1=1';
		$args  = array();

		if ( $context_id ) {
			$where .= ' AND context_id = %s';
			$args[] = $context_id;
		}

		$args[] = $limit;
		$args[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT task_id FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- dynamic WHERE built via prepare().
				...$args
			)
		);

		$tasks = array();
		foreach ( $rows as $row ) {
			$tasks[] = $this->get_task_response( $row->task_id );
		}

		$this->send_json( array( 'tasks' => $tasks ) );
	}

	private function handle_cancel_task( $task_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'agentclerk_a2a_tasks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$task = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE task_id = %s",
			$table,
			$task_id
		) );

		if ( ! $task ) {
			$this->send_error( 'TaskNotFoundError', 'Task not found.', 404 );
		}

		$terminal = array( 'TASK_STATE_COMPLETED', 'TASK_STATE_FAILED', 'TASK_STATE_CANCELED', 'TASK_STATE_REJECTED' );
		if ( in_array( $task->status, $terminal, true ) ) {
			$this->send_error( 'TaskNotCancelableError', 'Task is already in a terminal state.', 409 );
		}

		$this->update_task_state( $task_id, 'TASK_STATE_CANCELED' );

		$this->send_json( array( 'task' => $this->get_task_response( $task_id ) ) );
	}

	private function handle_subscribe_task( $task_id ) {
		$task = $this->get_task_row( $task_id );
		if ( ! $task ) {
			$this->send_error( 'TaskNotFoundError', 'Task not found.', 404 );
		}

		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' );

		// Send current state.
		$this->send_sse_event( array(
			'statusUpdate' => array(
				'taskId'    => $task_id,
				'contextId' => $task->context_id,
				'status'    => array( 'state' => $task->status ),
			),
		) );

		if ( function_exists( 'ob_flush' ) ) {
			ob_flush();
		}
		flush();
		exit;
	}

	/* =====================================================================
	 * Push Notification Config
	 * ================================================================== */

	private function route_push_config( $task_id ) {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';
		$this->validate_version_header();

		global $wpdb;
		$table = $wpdb->prefix . 'agentclerk_a2a_tasks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$task = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM %i WHERE task_id = %s",
			$table,
			$task_id
		) );

		if ( ! $task ) {
			$this->send_error( 'TaskNotFoundError', 'Task not found.', 404 );
		}

		if ( 'POST' === $method ) {
			$body       = json_decode( file_get_contents( 'php://input' ), true );
			$webhook_url = sanitize_url( $body['url'] ?? '' );
			$token       = sanitize_text_field( $body['token'] ?? '' );

			if ( empty( $webhook_url ) ) {
				$this->send_error( 'PushNotificationNotSupportedError', 'Webhook URL required.', 400 );
			}

			$config_id = wp_generate_uuid4();
			$push_meta = get_option( 'agentclerk_a2a_push_configs', array() );
			$push_meta[ $task_id ]                = $push_meta[ $task_id ] ?? array();
			$push_meta[ $task_id ][ $config_id ] = array(
				'url'   => $webhook_url,
				'token' => $token,
			);
			update_option( 'agentclerk_a2a_push_configs', $push_meta );

			$this->send_json( array(
				'id'  => $config_id,
				'url' => $webhook_url,
			), 201 );
		}

		if ( 'GET' === $method ) {
			$push_meta = get_option( 'agentclerk_a2a_push_configs', array() );
			$configs   = $push_meta[ $task_id ] ?? array();
			$result    = array();
			foreach ( $configs as $cid => $cfg ) {
				$result[] = array( 'id' => $cid, 'url' => $cfg['url'] );
			}
			$this->send_json( array( 'pushNotificationConfigs' => $result ) );
		}

		if ( 'DELETE' === $method ) {
			$uri       = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			$config_id = basename( $uri );
			$push_meta = get_option( 'agentclerk_a2a_push_configs', array() );
			unset( $push_meta[ $task_id ][ $config_id ] );
			update_option( 'agentclerk_a2a_push_configs', $push_meta );
			$this->send_json( array( 'status' => 'deleted' ) );
		}
	}

	/**
	 * Fire push notifications for a task state change.
	 */
	private function fire_push_notifications( $task_id, $event_data ) {
		$push_meta = get_option( 'agentclerk_a2a_push_configs', array() );
		$configs   = $push_meta[ $task_id ] ?? array();

		foreach ( $configs as $cfg ) {
			$headers = array( 'Content-Type' => self::CONTENT_TYPE );
			if ( ! empty( $cfg['token'] ) ) {
				$headers['Authorization'] = 'Bearer ' . $cfg['token'];
			}

			wp_remote_post( $cfg['url'], array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => $headers,
				'body'     => wp_json_encode( $event_data ),
			) );
		}
	}

	/* =====================================================================
	 * Task DB Operations
	 * ================================================================== */

	private function create_task( $context_id, $session_id, $status ) {
		global $wpdb;
		$table   = $wpdb->prefix . 'agentclerk_a2a_tasks';
		$task_id = wp_generate_uuid4();
		$now     = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( $table, array(
			'task_id'    => $task_id,
			'context_id' => $context_id,
			'session_id' => $session_id,
			'status'     => $status,
			'created_at' => $now,
			'updated_at' => $now,
		), array( '%s', '%s', '%s', '%s', '%s', '%s' ) );

		return $task_id;
	}

	private function update_task_state( $task_id, $state ) {
		global $wpdb;
		$table = $wpdb->prefix . 'agentclerk_a2a_tasks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table,
			array( 'status' => $state, 'updated_at' => current_time( 'mysql' ) ),
			array( 'task_id' => $task_id ),
			array( '%s', '%s' ),
			array( '%s' )
		);

		// Invalidate task caches.
		wp_cache_delete( 'agentclerk_task_row_' . $task_id, 'agentclerk' );
		wp_cache_delete( 'agentclerk_task_resp_' . $task_id, 'agentclerk' );

		// Fire push notifications.
		$this->fire_push_notifications( $task_id, array(
			'statusUpdate' => array(
				'taskId'    => $task_id,
				'contextId' => $this->get_task_context( $task_id ),
				'status'    => array( 'state' => $state ),
			),
		) );
	}

	private function update_task_error( $task_id, $error_message ) {
		global $wpdb;
		$table = $wpdb->prefix . 'agentclerk_a2a_tasks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $table,
			array(
				'status'     => 'TASK_STATE_FAILED',
				'error_msg'  => $error_message,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'task_id' => $task_id ),
			array( '%s', '%s', '%s' ),
			array( '%s' )
		);

		// Invalidate task caches.
		wp_cache_delete( 'agentclerk_task_row_' . $task_id, 'agentclerk' );
		wp_cache_delete( 'agentclerk_task_resp_' . $task_id, 'agentclerk' );
	}

	private function get_task_row( $task_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'agentclerk_a2a_tasks';

		$cache_key = 'agentclerk_task_row_' . $task_id;
		$result    = wp_cache_get( $cache_key, 'agentclerk' );

		if ( false === $result ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM %i WHERE task_id = %s",
				$table,
				$task_id
			) );

			if ( $result ) {
				wp_cache_set( $cache_key, $result, 'agentclerk', 300 );
			}
		}

		return $result;
	}

	private function get_task_context( $task_id ) {
		$row = $this->get_task_row( $task_id );
		return $row ? $row->context_id : '';
	}

	private function store_task_message( $task_id, $role, $text, $msg_id = null ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'agentclerk_a2a_task_messages';
		$msg_id = $msg_id ?? wp_generate_uuid4();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( $table, array(
			'task_id'    => $task_id,
			'message_id' => $msg_id,
			'role'       => $role,
			'content'    => $text,
			'created_at' => current_time( 'mysql' ),
		), array( '%s', '%s', '%s', '%s', '%s' ) );

		// Invalidate task response cache.
		wp_cache_delete( 'agentclerk_task_resp_' . $task_id, 'agentclerk' );

		return $msg_id;
	}

	private function store_task_artifact( $task_id, $artifact_id, $name, $parts ) {
		global $wpdb;
		$table = $wpdb->prefix . 'agentclerk_a2a_task_artifacts';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert( $table, array(
			'task_id'     => $task_id,
			'artifact_id' => $artifact_id,
			'name'        => $name,
			'parts_json'  => wp_json_encode( $parts ),
			'created_at'  => current_time( 'mysql' ),
		), array( '%s', '%s', '%s', '%s', '%s' ) );

		// Invalidate task response cache.
		wp_cache_delete( 'agentclerk_task_resp_' . $task_id, 'agentclerk' );
	}

	private function get_task_response( $task_id ) {
		$cache_key = 'agentclerk_task_resp_' . $task_id;
		$cached    = wp_cache_get( $cache_key, 'agentclerk' );

		if ( false !== $cached ) {
			return $cached;
		}

		$task = $this->get_task_row( $task_id );
		if ( ! $task ) {
			return null;
		}

		global $wpdb;

		// Get messages.
		$msgs_table = $wpdb->prefix . 'agentclerk_a2a_task_messages';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$messages   = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM %i WHERE task_id = %s ORDER BY created_at ASC",
			$msgs_table,
			$task_id
		) );

		$history = array();
		foreach ( $messages as $m ) {
			$history[] = array(
				'messageId' => $m->message_id,
				'role'      => $m->role,
				'parts'     => array( array( 'text' => $m->content ) ),
			);
		}

		// Get artifacts.
		$art_table = $wpdb->prefix . 'agentclerk_a2a_task_artifacts';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$artifacts_rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM %i WHERE task_id = %s ORDER BY created_at ASC",
			$art_table,
			$task_id
		) );

		$artifacts = array();
		foreach ( $artifacts_rows as $a ) {
			$artifacts[] = array(
				'artifactId' => $a->artifact_id,
				'name'       => $a->name,
				'parts'      => json_decode( $a->parts_json, true ),
			);
		}

		$response = array(
			'id'        => $task->task_id,
			'contextId' => $task->context_id,
			'status'    => array(
				'state' => $task->status,
			),
			'history'   => $history,
			'artifacts' => $artifacts,
		);

		if ( ! empty( $task->error_msg ) ) {
			$response['status']['message'] = array(
				'role'  => 'ROLE_AGENT',
				'parts' => array( array( 'text' => $task->error_msg ) ),
			);
		}

		wp_cache_set( $cache_key, $response, 'agentclerk', 300 );

		return $response;
	}

	/* =====================================================================
	 * Helpers
	 * ================================================================== */

	private function extract_text_from_parts( $parts ) {
		$text = '';
		foreach ( $parts as $part ) {
			if ( isset( $part['text'] ) ) {
				$text .= $part['text'] . ' ';
			} elseif ( isset( $part['data'] ) ) {
				// Structured data — convert to text for the AI.
				$text .= wp_json_encode( $part['data'] ) . ' ';
			}
		}
		return trim( $text );
	}

	private function response_is_question( $text ) {
		return false !== strpos( $text, '?' );
	}

	private function validate_version_header() {
		$version = isset( $_SERVER['HTTP_A2A_VERSION'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_A2A_VERSION'] ) )
			: '';

		if ( $version && version_compare( $version, '1.0', '>' ) ) {
			$this->send_error( 'VersionNotSupportedError', 'Only A2A version 1.0 is supported.', 400 );
		}
	}

	private function send_json( $data, $status = 200 ) {
		status_header( $status );
		header( 'Content-Type: ' . self::CONTENT_TYPE );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		exit;
	}

	private function send_error( $type, $message, $status = 400 ) {
		status_header( $status );
		header( 'Content-Type: ' . self::CONTENT_TYPE );
		echo wp_json_encode( array(
			'error' => array(
				'type'    => $type,
				'message' => $message,
			),
		) );
		exit;
	}
}
