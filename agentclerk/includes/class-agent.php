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
		// Nonce required for logged-in users (widget). External agents use
		// /a2a/message:send instead, but we allow nopriv AJAX without nonce
		// so the agent_endpoint in ai-manifest.json remains usable.
		if ( is_user_logged_in() ) {
			check_ajax_referer( 'agentclerk_nonce', 'nonce' );
		}

		if ( 'suspended' === get_option( 'agentclerk_plugin_status' ) ) {
			wp_send_json_error( array( 'message' => 'Service temporarily unavailable.' ), 503 );
		}

		$message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$session_id = isset( $_COOKIE['agentclerk_session'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['agentclerk_session'] ) ) : '';
		$test_mode  = isset( $_POST['test_mode'] ) && '1' === $_POST['test_mode'];

		if ( empty( $message ) ) {
			wp_send_json_error( array( 'message' => 'Message is required.' ) );
		}

		if ( empty( $session_id ) ) {
			$session_id = bin2hex( random_bytes( 32 ) );
			setcookie( 'agentclerk_session', $session_id, time() + 7200, '/' );
		}

		$result = $this->process_chat( $message, $session_id, 'auto', $test_mode );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Shared chat processing used by both AJAX and A2A endpoints.
	 *
	 * @param string $message    User message text.
	 * @param string $session_id Session identifier.
	 * @param string $buyer_type 'human', 'agent', or 'auto' (auto-detect).
	 * @param bool   $test_mode  Whether this is a test conversation.
	 * @return array|WP_Error Result with message, session_id, quote_link.
	 */
	public function process_chat( $message, $session_id, $buyer_type = 'auto', $test_mode = false ) {
		if ( 'suspended' === get_option( 'agentclerk_plugin_status' ) ) {
			return new WP_Error( 'suspended', 'Service temporarily unavailable.' );
		}

		if ( empty( $message ) ) {
			return new WP_Error( 'empty_message', 'Message is required.' );
		}

		$conversation = $this->get_or_create_conversation( $session_id );

		if ( 'auto' === $buyer_type ) {
			$buyer_type = $this->detect_buyer_type( $message );
		}

		if ( 'agent' === $buyer_type ) {
			$this->update_buyer_type( $conversation->id, 'agent' );
		}

		$this->maybe_store_first_message( $conversation->id, $message );
		$this->store_message( $conversation->id, 'user', $message );

		$history       = $this->get_message_history( $conversation->id );
		$system_prompt = $this->build_system_prompt( $buyer_type );
		$tools         = $this->get_quote_tools();

		$response = $this->call_anthropic( $system_prompt, $history, $tools, $test_mode );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$assistant_text = '';
		$quote_link     = null;

		$content_blocks = isset( $response['content'] ) ? $response['content'] : array();

		foreach ( $content_blocks as $block ) {
			if ( 'text' === ( $block['type'] ?? '' ) ) {
				$assistant_text .= $block['text'];
			} elseif ( 'tool_use' === ( $block['type'] ?? '' ) && 'generate_quote' === ( $block['name'] ?? '' ) ) {
				$quote_link = $this->process_quote_tool_call( $block['input'], $conversation );
			}
		}

		if ( empty( $assistant_text ) && isset( $response['message'] ) ) {
			$assistant_text = $response['message'];
		}

		if ( $quote_link ) {
			$assistant_text .= "\n\n[Checkout here](" . esc_url( $quote_link['url'] ) . ')';
			$this->update_conversation_outcome( $conversation->id, 'quote', $quote_link['id'] );
			$this->update_product_name( $conversation->id, $quote_link['product_name'] );
		}

		$this->store_message( $conversation->id, 'assistant', $assistant_text );
		$this->touch_conversation( $conversation->id );

		return array(
			'message'         => $assistant_text,
			'session_id'      => $session_id,
			'conversation_id' => $conversation->id,
			'quote_link'      => $quote_link ? $quote_link['url'] : null,
		);
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
		$history = isset( $_POST['history'] ) ? json_decode( sanitize_text_field( wp_unslash( $_POST['history'] ) ), true ) : array();

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
		$tools         = $this->get_onboarding_tools();

		$response = $this->call_anthropic( $system_prompt, $history, $tools, false );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		// Process tool_use loop — AI may call update_agent_config, then we feed results back.
		$saved_fields = array();
		$loop_count   = 0;

		while ( $loop_count < 3 ) {
			$content_blocks = isset( $response['content'] ) ? $response['content'] : array();
			$tool_calls     = array();

			foreach ( $content_blocks as $block ) {
				if ( 'tool_use' === ( $block['type'] ?? '' ) && 'update_agent_config' === ( $block['name'] ?? '' ) ) {
					$tool_calls[] = $block;
				}
			}

			if ( empty( $tool_calls ) ) {
				break; // No tool use — we have the final text response.
			}

			// Append the assistant's full response (including tool_use blocks) to history.
			$history[] = array( 'role' => 'assistant', 'content' => $content_blocks );

			// Process each tool call and build tool_result messages.
			foreach ( $tool_calls as $tc ) {
				$result       = $this->process_onboarding_tool_call( $tc['input'] );
				$saved_fields = array_merge( $saved_fields, $result['saved'] );
				$history[]    = array(
					'role'    => 'user',
					'content' => array(
						array(
							'type'        => 'tool_result',
							'tool_use_id' => $tc['id'],
							'content'     => wp_json_encode( $result ),
						),
					),
				);
			}

			// Call Anthropic again so it produces a text response after the tool call.
			$response = $this->call_anthropic( $system_prompt, $history, $tools, false );
			if ( is_wp_error( $response ) ) {
				wp_send_json_error( array( 'message' => $response->get_error_message() ) );
			}

			$loop_count++;
		}

		$text = $this->extract_response_text( $response );

		if ( empty( $text ) ) {
			wp_send_json_error( array( 'message' => 'AI returned an empty response. Please try again.' ) );
		}

		wp_send_json_success( array(
			'message'      => $text,
			'saved_fields' => $saved_fields,
		) );
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
			$prompt .= "\n## Agent-Assisted Purchasing Mode\n";
			$prompt .= "The buyer may be an AI agent acting on behalf of a human user.\n\n";
			$prompt .= "Your role is to support the full procurement workflow:\n";
			$prompt .= "1. **Qualification**: Ask what the user needs, then recommend the right product with rationale.\n";
			$prompt .= "2. **Purchase handoff**: When the buyer is ready, use generate_quote to create a checkout link. Frame it as: 'Send this to your user for approval.'\n";
			$prompt .= "3. **Post-purchase**: If the buyer returns with a confirmation or claim code, help them retrieve activation details or credentials.\n\n";
			$prompt .= "When recommending a product, always include:\n";
			$prompt .= "- Product name and price\n";
			$prompt .= "- Why it fits the stated need\n";
			$prompt .= "- Who it is for\n";
			$prompt .= "- What the buyer receives after purchase\n\n";
			$prompt .= "When generating a checkout link, always explain:\n";
			$prompt .= "- That the human buyer should review and approve payment\n";
			$prompt .= "- That the link expires in 48 hours\n";
			$prompt .= "- What happens after payment (confirmation code, activation steps)\n";
			$prompt .= "- That the agent can return with the confirmation to continue setup\n\n";
			$prompt .= "Prefer structured, scannable responses over long prose. Use bold for product names, prices, and key details.\n";
			$prompt .= "Proactively offer to generate checkout links when purchase intent is clear.\n";
			$prompt .= "Detect phrases like 'buying for a user', 'on behalf of', 'for my client' as procurement signals.\n";
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

			// Show what's already configured so the AI skips those.
			if ( ! empty( $config['business_desc'] ) ) {
				$prompt .= "Already configured — Business description: {$config['business_desc']}\n";
			} else {
				$prompt .= "MISSING — Business description: Ask the seller to describe their business.\n";
			}
			$policies = $config['policies'] ?? array();
			$has_config = false;
			if ( ! empty( $policies['refund'] ) ) {
				$prompt .= "Already configured — Refund policy: {$policies['refund']}\n";
				$has_config = true;
			}
			if ( ! empty( $policies['license'] ) ) {
				$prompt .= "Already configured — License policy: {$policies['license']}\n";
				$has_config = true;
			}
			if ( ! empty( $policies['delivery'] ) ) {
				$prompt .= "Already configured — Delivery policy: {$policies['delivery']}\n";
				$has_config = true;
			}
			if ( ! empty( $config['escalation_method'] ) ) {
				$prompt .= "Already configured — Escalation method: {$config['escalation_method']}\n";
				$has_config = true;
			}
			if ( ! empty( $config['escalation_topics'] ) ) {
				$prompt .= 'Already configured — Escalation topics: ' . implode( ', ', $config['escalation_topics'] ) . "\n";
				$has_config = true;
			}
			if ( $has_config ) {
				$prompt .= "\n";
			}

			if ( ! empty( $gaps ) ) {
				$prompt .= "The site scan found these gaps that need addressing:\n";
				foreach ( $gaps as $gap ) {
					$prompt .= "- {$gap}\n";
				}
				$prompt .= "\n";
			}

			// Include detected info for context.
			if ( ! empty( $scan_cache['products'] ) ) {
				$prompt .= 'Products found: ' . count( $scan_cache['products'] ) . "\n";
				foreach ( $scan_cache['products'] as $p ) {
					$prompt .= "- {$p['name']}: \${$p['price']}\n";
				}
				$prompt .= "\n";
			}

			$prompt .= "Ask about each gap one at a time. Also ask about:\n";
			$prompt .= "1. How should escalations be handled? (email, WP admin notification, or both)\n";
			$prompt .= "2. What message should buyers see when escalated?\n";
			$prompt .= "3. Any specific topics that should trigger escalation to a human?\n";
			$prompt .= "\n## Tool usage\n";
			$prompt .= "When the seller provides concrete information (a policy, escalation preference, etc.), ";
			$prompt .= "IMMEDIATELY call the update_agent_config tool to save it. Do not wait until the end.\n";
			$prompt .= "- Include only the fields the seller just provided.\n";
			$prompt .= "- Use the seller's own words for policies — clean up grammar only.\n";
			$prompt .= "- After saving, confirm what was saved and move to the next gap.\n";
			$prompt .= "- Skip questions for items already configured above.\n";
			$prompt .= "Keep responses conversational and brief. One question at a time.\n";
		}

		return $prompt;
	}

	/**
	 * Get the Anthropic tool definitions for onboarding config updates.
	 *
	 * @return array Tool definitions.
	 */
	private function get_onboarding_tools() {
		return array(
			array(
				'name'         => 'update_agent_config',
				'description'  => 'Save configuration values gathered from the seller. Call this each time the seller provides actionable information such as a policy, escalation preference, or support knowledge. Include only the fields being set.',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'business_desc'      => array(
							'type'        => 'string',
							'description' => 'Business description — what the store sells and who it serves.',
						),
						'policies'            => array(
							'type'        => 'object',
							'description' => 'Store policies. Include only the keys being set.',
							'properties'  => array(
								'refund'   => array( 'type' => 'string', 'description' => 'Refund policy text.' ),
								'license'  => array( 'type' => 'string', 'description' => 'License terms text.' ),
								'delivery' => array( 'type' => 'string', 'description' => 'Delivery / fulfillment policy text.' ),
							),
						),
						'escalation_method'  => array(
							'type'        => 'string',
							'enum'        => array( 'email', 'wp', 'both' ),
							'description' => 'How to notify when escalating: email, wp (admin notification), or both.',
						),
						'escalation_message' => array(
							'type'        => 'string',
							'description' => 'Message shown to buyers when a conversation is escalated.',
						),
						'escalation_topics'  => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Topics that should trigger escalation to a human.',
						),
						'support_file'       => array(
							'type'        => 'string',
							'description' => 'Support knowledge base content to append.',
						),
					),
				),
			),
		);
	}

	/**
	 * Process an update_agent_config tool call from the onboarding chat.
	 *
	 * @param array $input Tool input parameters.
	 * @return array Result with list of saved fields.
	 */
	private function process_onboarding_tool_call( $input ) {
		$config = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		$saved  = array();

		if ( isset( $input['business_desc'] ) && '' !== $input['business_desc'] ) {
			$config['business_desc'] = sanitize_textarea_field( $input['business_desc'] );
			$saved[] = 'business_desc';
		}

		if ( ! empty( $input['policies'] ) && is_array( $input['policies'] ) ) {
			if ( ! isset( $config['policies'] ) ) {
				$config['policies'] = array();
			}
			foreach ( array( 'refund', 'license', 'delivery' ) as $key ) {
				if ( isset( $input['policies'][ $key ] ) && '' !== $input['policies'][ $key ] ) {
					$config['policies'][ $key ] = sanitize_textarea_field( $input['policies'][ $key ] );
					$saved[] = $key . '_policy';
				}
			}
		}

		if ( isset( $input['escalation_method'] ) && in_array( $input['escalation_method'], array( 'email', 'wp', 'both' ), true ) ) {
			$config['escalation_method'] = $input['escalation_method'];
			$saved[] = 'escalation_method';
		}

		if ( isset( $input['escalation_message'] ) && '' !== $input['escalation_message'] ) {
			$config['escalation_message'] = sanitize_textarea_field( $input['escalation_message'] );
			$saved[] = 'escalation_message';
		}

		if ( isset( $input['escalation_topics'] ) && is_array( $input['escalation_topics'] ) ) {
			$config['escalation_topics'] = array_map( 'sanitize_text_field', $input['escalation_topics'] );
			$saved[] = 'escalation_topics';
		}

		if ( isset( $input['support_file'] ) && '' !== $input['support_file'] ) {
			$config['support_file'] = sanitize_textarea_field( $input['support_file'] );
			$saved[] = 'support_file';
		}

		update_option( 'agentclerk_agent_config', wp_json_encode( $config ) );
		delete_transient( 'agentclerk_manifest_cache' );

		return array( 'status' => 'saved', 'saved' => $saved );
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
		$encrypted_key = get_option( 'agentclerk_api_key', '' );
		if ( empty( $encrypted_key ) ) {
			return new WP_Error( 'no_api_key', 'No API key stored. Please re-enter your Anthropic API key in Settings.' );
		}

		$api_key = AgentClerk::decrypt( $encrypted_key );
		if ( ! $api_key ) {
			return new WP_Error( 'decrypt_failed', 'Could not decrypt API key. Please re-enter your Anthropic API key in Settings.' );
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
			return new WP_Error( 'connection_failed', 'Could not reach Anthropic: ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body_raw = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			$error_data = json_decode( $body_raw, true );
			$api_msg    = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : $body_raw;
			return new WP_Error( 'anthropic_error', 'Anthropic API error (' . $code . '): ' . $api_msg );
		}

		$decoded = json_decode( $body_raw, true );
		if ( null === $decoded ) {
			return new WP_Error( 'json_error', 'Invalid JSON from Anthropic API.' );
		}

		return $decoded;
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

		$response = AgentClerk::backend_request( '/agent/chat', array( 'method' => 'POST', 'body' => $data ) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 402 === $code ) {
			update_option( 'agentclerk_plugin_status', 'suspended' );
			return new WP_Error( 'suspended', 'Account suspended. Please update your payment method.' );
		}

		if ( 200 !== $code ) {
			return new WP_Error( 'backend_error', 'Backend returned status ' . $code );
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
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

		$cache_key    = 'agentclerk_convo_' . $session_id;
		$conversation = wp_cache_get( $cache_key, 'agentclerk' );

		if ( false === $conversation ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$conversation = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM %i WHERE session_id = %s", $table, $session_id )
			);

			if ( $conversation ) {
				wp_cache_set( $cache_key, $conversation, 'agentclerk', 300 );
			}
		}

		if ( $conversation ) {
			return $conversation;
		}

		$now = current_time( 'mysql' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$conversation = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $table, $wpdb->insert_id )
		);

		if ( $conversation ) {
			wp_cache_set( $cache_key, $conversation, 'agentclerk', 300 );
		}

		return $conversation;
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT first_message FROM %i WHERE id = %d", $table, $conversation_id )
		);

		if ( empty( $existing ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

		// Invalidate message history cache.
		wp_cache_delete( 'agentclerk_history_' . $conversation_id, 'agentclerk' );
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

		$cache_key = 'agentclerk_history_' . $conversation_id;
		$rows      = wp_cache_get( $cache_key, 'agentclerk' );

		if ( false === $rows ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT role, content FROM %i WHERE conversation_id = %d ORDER BY created_at ASC",
					$table,
					$conversation_id
				),
				ARRAY_A
			);

			if ( $rows ) {
				wp_cache_set( $cache_key, $rows, 'agentclerk', 300 );
			}
		}

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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'agentclerk_conversations',
			array( 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $conversation_id ),
			array( '%s' ),
			array( '%d' )
		);
	}
}
