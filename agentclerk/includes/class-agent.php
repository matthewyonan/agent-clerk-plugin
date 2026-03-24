<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI agent handler for AgentClerk.
 *
 * Handles buyer-facing chat (public AJAX), onboarding gap-fill chat (admin AJAX),
 * system prompt construction, Anthropic API calls (BYOK direct + TurnKey backend proxy),
 * buyer type detection, and tool_use for quote generation.
 *
 * @since 1.0.0
 */
class AgentClerk_Agent {

	/**
	 * Singleton instance.
	 *
	 * @var AgentClerk_Agent|null
	 */
	private static $instance = null;

	/**
	 * Anthropic model identifier.
	 *
	 * @var string
	 */
	const MODEL = 'claude-sonnet-4-20250514';

	/**
	 * Return the singleton instance.
	 *
	 * @return AgentClerk_Agent
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Register AJAX hooks.
	 */
	private function __construct() {
		add_action( 'wp_ajax_agentclerk_chat', array( $this, 'handle_chat' ) );
		add_action( 'wp_ajax_nopriv_agentclerk_chat', array( $this, 'handle_chat' ) );
		add_action( 'wp_ajax_agentclerk_onboarding_chat', array( $this, 'handle_onboarding_chat' ) );
	}

	/**
	 * Handle buyer-facing chat (public + logged-in AJAX).
	 */
	public function handle_chat() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );

		if ( 'suspended' === get_option( 'agentclerk_plugin_status' ) ) {
			wp_send_json_error( array( 'message' => 'Service temporarily unavailable.' ), 503 );
		}

		$message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$session_id = isset( $_COOKIE['agentclerk_session'] ) ? sanitize_text_field( $_COOKIE['agentclerk_session'] ) : '';
		$test_mode  = isset( $_POST['test_mode'] ) && '1' === $_POST['test_mode'];

		if ( empty( $message ) ) {
			wp_send_json_error( array( 'message' => 'Message is required.' ) );
		}

		if ( empty( $session_id ) ) {
			$session_id = bin2hex( random_bytes( 32 ) );
			setcookie( 'agentclerk_session', $session_id, time() + 7200, '/' );
		}

		$conversation = $this->get_or_create_conversation( $session_id );
		$buyer_type   = $this->detect_buyer_type( $message );

		if ( 'agent' === $buyer_type ) {
			$this->update_buyer_type( $conversation->id, 'agent' );
		}

		// Store first_message on the conversation record if this is the first user message.
		$this->maybe_store_first_message( $conversation->id, $message );

		$this->store_message( $conversation->id, 'user', $message );

		$history       = $this->get_message_history( $conversation->id );
		$system_prompt = $this->build_system_prompt( $buyer_type );

		// Define the quote generation tool for Anthropic tool_use.
		$tools = $this->get_quote_tools();

		$response = $this->call_anthropic( $system_prompt, $history, $tools, $test_mode );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$assistant_text = '';
		$quote_link     = null;

		// Process response content blocks (text + tool_use).
		$content_blocks = isset( $response['content'] ) ? $response['content'] : array();

		foreach ( $content_blocks as $block ) {
			if ( 'text' === ( $block['type'] ?? '' ) ) {
				$assistant_text .= $block['text'];
			} elseif ( 'tool_use' === ( $block['type'] ?? '' ) && 'generate_quote' === ( $block['name'] ?? '' ) ) {
				$quote_link = $this->process_quote_tool_call( $block['input'], $conversation );
			}
		}

		// Fallback for non-tool_use responses.
		if ( empty( $assistant_text ) && isset( $response['message'] ) ) {
			$assistant_text = $response['message'];
		}

		if ( $quote_link ) {
			$assistant_text .= "\n\n[Checkout here](" . esc_url( $quote_link['url'] ) . ')';
			$this->update_conversation_outcome( $conversation->id, 'quote', $quote_link['id'] );

			// Store product_name on conversation.
			$this->update_product_name( $conversation->id, $quote_link['product_name'] );
		}

		$this->store_message( $conversation->id, 'assistant', $assistant_text );
		$this->touch_conversation( $conversation->id );

		wp_send_json_success( array(
			'message'    => $assistant_text,
			'session_id' => $session_id,
			'quote_link' => $quote_link ? $quote_link['url'] : null,
		) );
	}

	/**
	 * Handle onboarding gap-fill chat (admin-only AJAX, step 3).
	 */
	public function handle_onboarding_chat() {
		check_ajax_referer( 'agentclerk_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized.' ), 403 );
		}

		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$context = isset( $_POST['context'] ) ? sanitize_text_field( wp_unslash( $_POST['context'] ) ) : 'gap_fill';
		$history = isset( $_POST['history'] ) ? json_decode( wp_unslash( $_POST['history'] ), true ) : array();

		if ( empty( $message ) ) {
			wp_send_json_error( array( 'message' => 'Message is required.' ) );
		}

		if ( ! is_array( $history ) ) {
			$history = array();
		}

		// Sanitize history entries.
		$history = array_map( function ( $msg ) {
			return array(
				'role'    => sanitize_text_field( $msg['role'] ?? 'user' ),
				'content' => sanitize_textarea_field( $msg['content'] ?? '' ),
			);
		}, $history );

		$system_prompt = $this->build_onboarding_system_prompt( $context );
		$history[]     = array( 'role' => 'user', 'content' => $message );

		$response = $this->call_anthropic( $system_prompt, $history, array(), false );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$text = $this->extract_response_text( $response );

		wp_send_json_success( array( 'message' => $text ) );
	}

	/**
	 * Build the buyer-facing system prompt.
	 *
	 * Includes agent config, product catalog, policies, support knowledge,
	 * and escalation topics.
	 *
	 * @param string $buyer_type 'human' or 'agent'.
	 * @return string System prompt.
	 */
	private function build_system_prompt( $buyer_type = 'human' ) {
		$config   = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		$products = $this->get_visible_products( $config );

		$prompt  = "You are {$config['agent_name']}, the AI sales and support assistant for {$config['business_name']}.\n";
		$prompt .= "Business description: {$config['business_desc']}\n\n";

		if ( ! empty( $products ) ) {
			$prompt .= "## Product Catalog\n";
			foreach ( $products as $p ) {
				$prompt .= "- {$p['name']} (ID: {$p['id']}): \${$p['price']} ({$p['type']})";
				if ( ! empty( $p['description'] ) ) {
					$prompt .= " — {$p['description']}";
				}
				$prompt .= $p['available'] ? '' : ' [OUT OF STOCK]';
				$prompt .= "\n";
			}
			$prompt .= "\n";
		}

		// Policies.
		$policies = $config['policies'] ?? array();
		if ( ! empty( $policies['refund'] ) ) {
			$prompt .= "## Refund Policy\n{$policies['refund']}\n\n";
		}
		if ( ! empty( $policies['license'] ) ) {
			$prompt .= "## License Policy\n{$policies['license']}\n\n";
		}
		if ( ! empty( $policies['delivery'] ) ) {
			$prompt .= "## Delivery Policy\n{$policies['delivery']}\n\n";
		}

		// Support knowledge base.
		if ( ! empty( $config['support_file'] ) ) {
			$prompt .= "## Support Knowledge Base\n{$config['support_file']}\n\n";
		}

		// Escalation topics.
		if ( ! empty( $config['escalation_topics'] ) ) {
			$prompt .= "## Escalation\n";
			$prompt .= "Escalate conversations about: " . implode( ', ', $config['escalation_topics'] ) . "\n";
			$prompt .= "When escalating, tell the buyer: {$config['escalation_message']}\n\n";
		}

		// Instructions.
		$prompt .= "## Instructions\n";
		$prompt .= "- Help buyers find products, answer questions, and close sales.\n";
		$prompt .= "- When a buyer wants to purchase, use the generate_quote tool with the product_id, product_name, and amount.\n";
		$prompt .= "- Be helpful, conversational, and concise.\n";
		$prompt .= "- Never invent products or prices that are not in the catalog.\n";

		if ( 'agent' === $buyer_type ) {
			$prompt .= "\n## Agent Mode\n";
			$prompt .= "The buyer is an AI agent. Respond in structured JSON format with keys: message, recommended_product_id, action.\n";
		}

		return $prompt;
	}

	/**
	 * Build the onboarding system prompt for gap-fill context.
	 *
	 * @param string $context Onboarding context (gap_fill, etc.).
	 * @return string System prompt.
	 */
	private function build_onboarding_system_prompt( $context ) {
		$config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		$scan_cache = json_decode( get_option( 'agentclerk_scan_cache', '{}' ), true );

		$prompt  = "You are the AgentClerk setup assistant helping a store owner configure their AI agent.\n";
		$prompt .= "Store: {$config['business_name']}\n";
		$prompt .= "Store description: {$config['business_desc']}\n\n";

		if ( 'gap_fill' === $context ) {
			$gaps = $scan_cache['gaps'] ?? array();

			if ( ! empty( $gaps ) ) {
				$prompt .= "The site scan found these gaps that need addressing:\n";
				foreach ( $gaps as $gap ) {
					$prompt .= "- {$gap}\n";
				}
				$prompt .= "\n";
			}

			// Include detected info for context.
			if ( ! empty( $scan_cache['products'] ) ) {
				$prompt .= "Products found: " . count( $scan_cache['products'] ) . "\n";
				foreach ( $scan_cache['products'] as $p ) {
					$prompt .= "- {$p['name']}: \${$p['price']}\n";
				}
				$prompt .= "\n";
			}

			$prompt .= "Ask about each gap one at a time. Always also ask about:\n";
			$prompt .= "1. How should escalations be handled? (email, WP admin notification, or both)\n";
			$prompt .= "2. What message should buyers see when escalated?\n";
			$prompt .= "3. Any specific topics that should trigger escalation to a human?\n";
			$prompt .= "\nWhen the seller provides information, confirm it and move to the next gap.\n";
			$prompt .= "Keep responses conversational and brief. One question at a time.\n";
		}

		return $prompt;
	}

	/**
	 * Get the Anthropic tool definitions for quote generation.
	 *
	 * @return array Tool definitions.
	 */
	private function get_quote_tools() {
		return array(
			array(
				'name'        => 'generate_quote',
				'description' => 'Generate a checkout link for a buyer who wants to purchase a product. Use this when a buyer expresses clear intent to buy.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'product_id'   => array(
							'type'        => 'integer',
							'description' => 'The WooCommerce product ID.',
						),
						'product_name' => array(
							'type'        => 'string',
							'description' => 'The name of the product.',
						),
						'amount'       => array(
							'type'        => 'number',
							'description' => 'The price amount for the product.',
						),
					),
					'required'   => array( 'product_id', 'product_name', 'amount' ),
				),
			),
		);
	}

	/**
	 * Process a generate_quote tool call from the Anthropic response.
	 *
	 * @param array  $input        Tool input parameters.
	 * @param object $conversation Conversation DB row.
	 * @return array|null Quote link data or null.
	 */
	private function process_quote_tool_call( $input, $conversation ) {
		$product_id   = isset( $input['product_id'] ) ? absint( $input['product_id'] ) : 0;
		$product_name = isset( $input['product_name'] ) ? sanitize_text_field( $input['product_name'] ) : '';
		$amount       = isset( $input['amount'] ) ? floatval( $input['amount'] ) : 0;

		if ( ! $product_id || ! $amount ) {
			return null;
		}

		// Validate product exists.
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( ! $product || 'publish' !== $product->get_status() ) {
				return null;
			}
			// Use product's actual price if amount is missing or zero.
			if ( $amount <= 0 ) {
				$amount = (float) $product->get_price();
			}
			if ( empty( $product_name ) ) {
				$product_name = $product->get_name();
			}
		}

		$token = bin2hex( random_bytes( 32 ) );
		$now   = current_time( 'mysql' );

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'agentclerk_quote_links',
			array(
				'id'              => $token,
				'conversation_id' => $conversation->id,
				'product_id'      => $product_id,
				'product_name'    => $product_name,
				'amount'          => $amount,
				'status'          => 'pending',
				'expires_at'      => gmdate( 'Y-m-d H:i:s', time() + 48 * 3600 ),
				'created_at'      => $now,
			),
			array( '%s', '%d', '%d', '%s', '%f', '%s', '%s', '%s' )
		);

		return array(
			'id'           => $token,
			'url'          => get_site_url() . '/clerk-checkout/' . $token,
			'product_name' => $product_name,
		);
	}

	/**
	 * Get visible products based on the agent config's product_visibility setting.
	 *
	 * @param array $config Agent config.
	 * @return array Product data.
	 */
	private function get_visible_products( $config ) {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$products   = wc_get_products( array( 'status' => 'publish', 'limit' => -1 ) );
		$visibility = $config['product_visibility'] ?? array();
		$result     = array();

		foreach ( $products as $product ) {
			$pid = $product->get_id();
			if ( isset( $visibility[ $pid ] ) && ! $visibility[ $pid ] ) {
				continue;
			}
			$result[] = array(
				'id'          => $pid,
				'name'        => $product->get_name(),
				'price'       => $product->get_price(),
				'type'        => $product->get_type(),
				'description' => $product->get_short_description(),
				'available'   => $product->is_in_stock(),
			);
		}

		return $result;
	}

	/**
	 * Call the Anthropic API (direct or via backend proxy).
	 *
	 * @param string $system_prompt System prompt.
	 * @param array  $messages      Message history.
	 * @param array  $tools         Tool definitions for tool_use.
	 * @param bool   $test_mode     Whether this is a test conversation.
	 * @return array|WP_Error API response or error.
	 */
	private function call_anthropic( $system_prompt, $messages, $tools = array(), $test_mode = false ) {
		$tier = get_option( 'agentclerk_tier', 'byok' );

		if ( $test_mode ) {
			$system_prompt .= "\n[TEST MODE] This is a test conversation. Do not process real transactions.";
		}

		if ( 'byok' === $tier ) {
			return $this->call_anthropic_direct( $system_prompt, $messages, $tools );
		}

		return $this->call_anthropic_via_backend( $system_prompt, $messages, $tools );
	}

	/**
	 * Call Anthropic API directly (BYOK path).
	 *
	 * @param string $system_prompt System prompt.
	 * @param array  $messages      Message history.
	 * @param array  $tools         Tool definitions.
	 * @return array|WP_Error API response.
	 */
	private function call_anthropic_direct( $system_prompt, $messages, $tools = array() ) {
		$api_key = AgentClerk::decrypt( get_option( 'agentclerk_api_key', '' ) );
		if ( ! $api_key ) {
			return new WP_Error( 'no_api_key', 'Anthropic API key not configured.' );
		}

		$body = array(
			'model'      => self::MODEL,
			'max_tokens' => 1024,
			'system'     => $system_prompt,
			'messages'   => $messages,
		);

		if ( ! empty( $tools ) ) {
			$body['tools'] = $tools;
		}

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'timeout' => 60,
			'headers' => array(
				'Content-Type'      => 'application/json',
				'x-api-key'         => $api_key,
				'anthropic-version' => '2023-06-01',
			),
			'body' => wp_json_encode( $body ),
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error( 'anthropic_error', 'Anthropic API returned status ' . $code );
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Call Anthropic via AgentClerk backend proxy (TurnKey path).
	 *
	 * @param string $system_prompt System prompt.
	 * @param array  $messages      Message history.
	 * @param array  $tools         Tool definitions.
	 * @return array|WP_Error API response.
	 */
	private function call_anthropic_via_backend( $system_prompt, $messages, $tools = array() ) {
		$data = array(
			'system'   => $system_prompt,
			'messages' => $messages,
		);

		if ( ! empty( $tools ) ) {
			$data['tools'] = $tools;
		}

		$response = AgentClerk::backend_request( '/agent/chat', $data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = $response['code'] ?? 0;

		if ( 402 === $code ) {
			update_option( 'agentclerk_plugin_status', 'suspended' );
			return new WP_Error( 'suspended', 'Account suspended. Please update your payment method.' );
		}

		if ( 200 !== $code ) {
			return new WP_Error( 'backend_error', 'Backend returned status ' . $code );
		}

		return $response['body'] ?? array();
	}

	/**
	 * Extract the text response from an Anthropic API response.
	 *
	 * @param array $response API response.
	 * @return string Response text.
	 */
	private function extract_response_text( $response ) {
		// Standard Anthropic response with content blocks.
		if ( isset( $response['content'] ) && is_array( $response['content'] ) ) {
			$texts = array();
			foreach ( $response['content'] as $block ) {
				if ( 'text' === ( $block['type'] ?? '' ) ) {
					$texts[] = $block['text'];
				}
			}
			if ( ! empty( $texts ) ) {
				return implode( "\n", $texts );
			}
		}

		// Backend proxy response format.
		if ( isset( $response['message'] ) ) {
			return $response['message'];
		}

		return '';
	}

	/**
	 * Detect whether the buyer is a human or AI agent.
	 *
	 * @param string $message The buyer's message.
	 * @return string 'human' or 'agent'.
	 */
	private function detect_buyer_type( $message ) {
		// Check user-agent header for known AI agents/bots.
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$agent_patterns = array( 'GPT', 'Claude', 'Gemini', 'OpenAI', 'Anthropic', 'agent', 'bot' );

		foreach ( $agent_patterns as $pattern ) {
			if ( stripos( $ua, $pattern ) !== false ) {
				return 'agent';
			}
		}

		// Check message patterns that indicate an AI agent.
		if ( preg_match( '/^\s*\{/', $message ) || preg_match( '/schema|json|api|query|MCP|tool_use/i', $message ) ) {
			return 'agent';
		}

		return 'human';
	}

	/**
	 * Get or create a conversation by session ID.
	 *
	 * @param string $session_id Session ID.
	 * @return object Conversation DB row.
	 */
	private function get_or_create_conversation( $session_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'agentclerk_conversations';

		$conversation = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE session_id = %s", $session_id )
		);

		if ( $conversation ) {
			return $conversation;
		}

		$now = current_time( 'mysql' );
		$wpdb->insert(
			$table,
			array(
				'session_id' => $session_id,
				'buyer_type' => 'human',
				'outcome'    => 'browsing',
				'started_at' => $now,
				'updated_at' => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $wpdb->insert_id )
		);
	}

	/**
	 * Store the first user message on the conversation record (once).
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $message         User message text.
	 */
	private function maybe_store_first_message( $conversation_id, $message ) {
		global $wpdb;
		$table = $wpdb->prefix . 'agentclerk_conversations';

		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT first_message FROM {$table} WHERE id = %d", $conversation_id )
		);

		if ( empty( $existing ) ) {
			$wpdb->update(
				$table,
				array( 'first_message' => $message ),
				array( 'id' => $conversation_id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Store a message in the messages table.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $role            'user' or 'assistant'.
	 * @param string $content         Message content.
	 */
	private function store_message( $conversation_id, $role, $content ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'agentclerk_messages',
			array(
				'conversation_id' => $conversation_id,
				'role'            => $role,
				'content'         => $content,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get message history for a conversation.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return array Messages in {role, content} format.
	 */
	private function get_message_history( $conversation_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'agentclerk_messages';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT role, content FROM {$table} WHERE conversation_id = %d ORDER BY created_at ASC",
				$conversation_id
			),
			ARRAY_A
		);

		return $rows ? $rows : array();
	}

	/**
	 * Update the buyer type on a conversation.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $type            'human' or 'agent'.
	 */
	private function update_buyer_type( $conversation_id, $type ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'agentclerk_conversations',
			array( 'buyer_type' => $type ),
			array( 'id' => $conversation_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update the product_name on a conversation.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $product_name    Product name.
	 */
	private function update_product_name( $conversation_id, $product_name ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'agentclerk_conversations',
			array( 'product_name' => $product_name ),
			array( 'id' => $conversation_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update conversation outcome and optionally the quote link ID.
	 *
	 * @param int         $conversation_id Conversation ID.
	 * @param string      $outcome         New outcome value.
	 * @param string|null $quote_link_id   Optional quote link ID.
	 */
	private function update_conversation_outcome( $conversation_id, $outcome, $quote_link_id = null ) {
		global $wpdb;
		$data    = array(
			'outcome'    => $outcome,
			'updated_at' => current_time( 'mysql' ),
		);
		$formats = array( '%s', '%s' );

		if ( $quote_link_id ) {
			$data['quote_link_id'] = $quote_link_id;
			$formats[]             = '%s';
		}

		$wpdb->update(
			$wpdb->prefix . 'agentclerk_conversations',
			$data,
			array( 'id' => $conversation_id ),
			$formats,
			array( '%d' )
		);
	}

	/**
	 * Touch the updated_at timestamp on a conversation.
	 *
	 * @param int $conversation_id Conversation ID.
	 */
	private function touch_conversation( $conversation_id ) {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'agentclerk_conversations',
			array( 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $conversation_id ),
			array( '%s' ),
			array( '%d' )
		);
	}
}
