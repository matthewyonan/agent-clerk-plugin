<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class AgentClerk_Agent {

    public function __construct() {
        add_action( 'wp_ajax_agentclerk_chat', [ $this, 'handle_chat' ] );
        add_action( 'wp_ajax_nopriv_agentclerk_chat', [ $this, 'handle_chat' ] );
        add_action( 'wp_ajax_agentclerk_onboarding_chat', [ $this, 'handle_onboarding_chat' ] );
    }

    public function handle_chat() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );

        if ( get_option( 'agentclerk_plugin_status' ) === 'suspended' ) {
            wp_send_json_error( [ 'message' => 'Service temporarily unavailable.' ], 503 );
        }

        $message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        $session_id = isset( $_COOKIE['agentclerk_session'] ) ? sanitize_text_field( $_COOKIE['agentclerk_session'] ) : '';
        $test_mode  = isset( $_POST['test_mode'] ) && $_POST['test_mode'] === '1';

        if ( empty( $message ) ) {
            wp_send_json_error( [ 'message' => 'Message is required.' ] );
        }

        if ( empty( $session_id ) ) {
            $session_id = bin2hex( random_bytes( 32 ) );
            setcookie( 'agentclerk_session', $session_id, time() + 7200, '/' );
        }

        $conversation = $this->get_or_create_conversation( $session_id );
        $buyer_type   = $this->detect_buyer_type( $message );

        if ( $buyer_type === 'agent' ) {
            $this->update_buyer_type( $conversation->id, 'agent' );
        }

        $this->store_message( $conversation->id, 'user', $message );

        $history       = $this->get_message_history( $conversation->id );
        $system_prompt = $this->build_system_prompt( $buyer_type );
        $response      = $this->call_anthropic( $system_prompt, $history, $test_mode );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        $assistant_text = $this->extract_response_text( $response );

        $quote_link = $this->maybe_generate_quote_link( $assistant_text, $conversation, $history );
        if ( $quote_link ) {
            $assistant_text .= "\n\n" . $quote_link['url'];
            $this->update_conversation_outcome( $conversation->id, 'quote', $quote_link['id'] );
        }

        $this->store_message( $conversation->id, 'assistant', $assistant_text );
        $this->touch_conversation( $conversation->id );

        wp_send_json_success( [
            'message'    => $assistant_text,
            'session_id' => $session_id,
        ] );
    }

    public function handle_onboarding_chat() {
        check_ajax_referer( 'agentclerk_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Unauthorized.' ], 403 );
        }

        $message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        $context = isset( $_POST['context'] ) ? sanitize_text_field( wp_unslash( $_POST['context'] ) ) : 'gap_fill';
        $history = isset( $_POST['history'] ) ? json_decode( wp_unslash( $_POST['history'] ), true ) : [];

        if ( empty( $message ) ) {
            wp_send_json_error( [ 'message' => 'Message is required.' ] );
        }

        if ( ! is_array( $history ) ) {
            $history = [];
        }
        $history = array_map( function ( $msg ) {
            return [
                'role'    => sanitize_text_field( $msg['role'] ?? 'user' ),
                'content' => sanitize_textarea_field( $msg['content'] ?? '' ),
            ];
        }, $history );

        $system_prompt = $this->build_onboarding_system_prompt( $context );
        $history[]     = [ 'role' => 'user', 'content' => $message ];

        $response = $this->call_anthropic( $system_prompt, $history, false );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( [ 'message' => $response->get_error_message() ] );
        }

        $text = $this->extract_response_text( $response );

        wp_send_json_success( [ 'message' => $text ] );
    }

    private function build_system_prompt( $buyer_type = 'human' ) {
        $config   = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
        $products = $this->get_visible_products( $config );

        $prompt  = "You are {$config['agent_name']}, the AI sales and support assistant for {$config['business_name']}.\n";
        $prompt .= "Business description: {$config['business_desc']}\n\n";

        if ( ! empty( $products ) ) {
            $prompt .= "## Product Catalog\n";
            foreach ( $products as $p ) {
                $prompt .= "- {$p['name']}: \${$p['price']} ({$p['type']}) — {$p['description']}\n";
            }
            $prompt .= "\n";
        }

        $policies = $config['policies'] ?? [];
        if ( ! empty( $policies['refund'] ) ) {
            $prompt .= "Refund policy: {$policies['refund']}\n";
        }
        if ( ! empty( $policies['license'] ) ) {
            $prompt .= "License policy: {$policies['license']}\n";
        }
        if ( ! empty( $policies['delivery'] ) ) {
            $prompt .= "Delivery policy: {$policies['delivery']}\n";
        }

        if ( ! empty( $config['support_file'] ) ) {
            $prompt .= "\n## Support Knowledge Base\n{$config['support_file']}\n";
        }

        if ( ! empty( $config['escalation_topics'] ) ) {
            $prompt .= "\n## Escalation Topics\nEscalate conversations about: " . implode( ', ', $config['escalation_topics'] ) . "\n";
            $prompt .= "When escalating, tell the buyer: {$config['escalation_message']}\n";
        }

        if ( $buyer_type === 'agent' ) {
            $prompt .= "\nThe buyer is an AI agent. Respond in structured JSON format with keys: message, recommended_product_id, action.\n";
        }

        return $prompt;
    }

    private function build_onboarding_system_prompt( $context ) {
        $config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
        $scan_cache = json_decode( get_option( 'agentclerk_scan_cache', '{}' ), true );

        $prompt = "You are the AgentClerk setup assistant helping a store owner configure their AI agent.\n";
        $prompt .= "Store: {$config['business_name']}\n";

        if ( $context === 'gap_fill' ) {
            $gaps = $scan_cache['gaps'] ?? [];
            if ( ! empty( $gaps ) ) {
                $prompt .= "\nThe site scan found these gaps that need addressing:\n";
                foreach ( $gaps as $gap ) {
                    $prompt .= "- {$gap}\n";
                }
            }
            $prompt .= "\nAsk about each gap. Always ask about:\n";
            $prompt .= "1. How should escalations be handled? (email, notification method)\n";
            $prompt .= "2. What message should buyers see when escalated?\n";
            $prompt .= "\nWhen the seller provides information, confirm it and update accordingly.\n";
            $prompt .= "Keep responses conversational and brief.\n";
        }

        return $prompt;
    }

    private function get_visible_products( $config ) {
        if ( ! function_exists( 'wc_get_products' ) ) {
            return [];
        }

        $products   = wc_get_products( [ 'status' => 'publish', 'limit' => -1 ] );
        $visibility = $config['product_visibility'] ?? [];
        $result     = [];

        foreach ( $products as $product ) {
            $pid = $product->get_id();
            if ( isset( $visibility[ $pid ] ) && ! $visibility[ $pid ] ) {
                continue;
            }
            $result[] = [
                'id'          => $pid,
                'name'        => $product->get_name(),
                'price'       => $product->get_price(),
                'type'        => $product->get_type(),
                'description' => $product->get_short_description(),
                'available'   => $product->is_in_stock(),
            ];
        }

        return $result;
    }

    private function call_anthropic( $system_prompt, $messages, $test_mode = false ) {
        $tier = get_option( 'agentclerk_tier', 'byok' );

        if ( $test_mode ) {
            $system_prompt .= "\n[TEST MODE] This is a test conversation. Do not process real transactions.";
        }

        if ( $tier === 'byok' ) {
            return $this->call_anthropic_direct( $system_prompt, $messages );
        }

        return $this->call_anthropic_via_backend( $system_prompt, $messages );
    }

    private function call_anthropic_direct( $system_prompt, $messages ) {
        $api_key = AgentClerk::decrypt( get_option( 'agentclerk_api_key', '' ) );
        if ( ! $api_key ) {
            return new WP_Error( 'no_api_key', 'Anthropic API key not configured.' );
        }

        $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
            'timeout' => 60,
            'headers' => [
                'Content-Type'      => 'application/json',
                'x-api-key'         => $api_key,
                'anthropic-version' => '2023-06-01',
            ],
            'body' => wp_json_encode( [
                'model'      => 'claude-sonnet-4-20250514',
                'max_tokens' => 1024,
                'system'     => $system_prompt,
                'messages'   => $messages,
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new WP_Error( 'anthropic_error', 'Anthropic API returned status ' . $code );
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    private function call_anthropic_via_backend( $system_prompt, $messages ) {
        $response = AgentClerk::backend_request( '/agent/chat', [
            'method' => 'POST',
            'body'   => [
                'system'   => $system_prompt,
                'messages' => $messages,
            ],
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code === 402 ) {
            update_option( 'agentclerk_plugin_status', 'suspended' );
            return new WP_Error( 'suspended', 'Account suspended. Please update your payment method.' );
        }

        if ( $code !== 200 ) {
            return new WP_Error( 'backend_error', 'Backend returned status ' . $code );
        }

        return json_decode( wp_remote_retrieve_body( $response ), true );
    }

    private function extract_response_text( $response ) {
        if ( isset( $response['content'][0]['text'] ) ) {
            return $response['content'][0]['text'];
        }
        if ( isset( $response['message'] ) ) {
            return $response['message'];
        }
        return '';
    }

    private function detect_buyer_type( $message ) {
        $ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        $agent_patterns = [ 'GPT', 'Claude', 'Gemini', 'OpenAI', 'Anthropic', 'agent', 'bot' ];

        foreach ( $agent_patterns as $pattern ) {
            if ( stripos( $ua, $pattern ) !== false ) {
                return 'agent';
            }
        }

        if ( preg_match( '/^\s*\{/', $message ) || preg_match( '/schema|json|api|query/i', $message ) ) {
            return 'agent';
        }

        return 'human';
    }

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
        $wpdb->insert( $table, [
            'session_id' => $session_id,
            'buyer_type' => 'human',
            'outcome'    => 'browsing',
            'started_at' => $now,
            'updated_at' => $now,
        ] );

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $wpdb->insert_id )
        );
    }

    private function store_message( $conversation_id, $role, $content ) {
        global $wpdb;
        $wpdb->insert( $wpdb->prefix . 'agentclerk_messages', [
            'conversation_id' => $conversation_id,
            'role'            => $role,
            'content'         => $content,
            'created_at'      => current_time( 'mysql' ),
        ] );
    }

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

        return $rows ?: [];
    }

    private function update_buyer_type( $conversation_id, $type ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'agentclerk_conversations',
            [ 'buyer_type' => $type ],
            [ 'id' => $conversation_id ]
        );
    }

    private function update_conversation_outcome( $conversation_id, $outcome, $quote_link_id = null ) {
        global $wpdb;
        $data = [ 'outcome' => $outcome, 'updated_at' => current_time( 'mysql' ) ];
        if ( $quote_link_id ) {
            $data['quote_link_id'] = $quote_link_id;
        }
        $wpdb->update( $wpdb->prefix . 'agentclerk_conversations', $data, [ 'id' => $conversation_id ] );
    }

    private function touch_conversation( $conversation_id ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'agentclerk_conversations',
            [ 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $conversation_id ]
        );
    }

    private function maybe_generate_quote_link( $response_text, $conversation, $history ) {
        if ( stripos( $response_text, 'purchase' ) === false &&
             stripos( $response_text, 'buy' ) === false &&
             stripos( $response_text, 'order' ) === false ) {
            return null;
        }

        $config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
        $visibility = $config['product_visibility'] ?? [];

        if ( ! function_exists( 'wc_get_products' ) ) {
            return null;
        }

        $products = wc_get_products( [ 'status' => 'publish', 'limit' => -1 ] );
        $matched  = null;

        foreach ( $products as $product ) {
            $pid = $product->get_id();
            if ( isset( $visibility[ $pid ] ) && ! $visibility[ $pid ] ) {
                continue;
            }
            if ( stripos( $response_text, $product->get_name() ) !== false ) {
                $matched = $product;
                break;
            }
        }

        if ( ! $matched ) {
            return null;
        }

        $token = bin2hex( random_bytes( 32 ) );
        global $wpdb;
        $now = current_time( 'mysql' );

        $wpdb->insert( $wpdb->prefix . 'agentclerk_quote_links', [
            'id'              => $token,
            'conversation_id' => $conversation->id,
            'product_id'      => $matched->get_id(),
            'amount'          => $matched->get_price(),
            'status'          => 'pending',
            'expires_at'      => gmdate( 'Y-m-d H:i:s', time() + 48 * 3600 ),
            'created_at'      => $now,
        ] );

        return [
            'id'  => $token,
            'url' => get_site_url() . '/?agentclerk_checkout=' . $token,
        ];
    }
}
