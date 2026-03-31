<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AgentClerk Public Widget
 *
 * Handles conditional asset enqueuing, product page embeds,
 * /clerk full-page chat, and support page chat on the front end.
 *
 * @package AgentClerk
 * @since   1.0.0
 */
class AgentClerk_Widget {

	/**
	 * Singleton instance.
	 *
	 * @var AgentClerk_Widget|null
	 */
	private static $instance = null;

	/**
	 * Return the singleton instance.
	 *
	 * @return AgentClerk_Widget
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — registers front-end hooks only.
	 */
	private function __construct() {
		if ( is_admin() ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'product_page_embed' ) );
		add_action( 'woocommerce_after_shop_loop', array( $this, 'category_page_embed' ) );
		add_filter( 'the_content', array( $this, 'filter_page_content' ) );
		add_action( 'wp_head', array( $this, 'output_agent_meta_tags' ) );
		add_action( 'wp_footer', array( $this, 'output_agent_instructions_footer' ) );
		add_filter( 'robots_txt', array( $this, 'add_robots_agent_hints' ), 10, 2 );
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton.' );
	}

	/* ───────────────────────────────────────────────
	 *  Asset Enqueuing
	 * ─────────────────────────────────────────────── */

	/**
	 * Conditionally enqueue public CSS and JS based on placement settings.
	 */
	/**
	 * Output meta tags in <head> for AI agent discoverability.
	 * Agents that parse HTML can find the API endpoints without prior knowledge.
	 */
	/**
	 * Append AI agent discovery hints to robots.txt.
	 *
	 * @param string $output Existing robots.txt content.
	 * @param bool   $public Whether the site is public.
	 * @return string Modified robots.txt.
	 */
	/**
	 * Output agent instructions in the footer for pages with the floating widget.
	 * Skips /clerk and product pages (they have instructions in their own HTML).
	 */
	public function output_agent_instructions_footer() {
		if ( get_option( 'agentclerk_plugin_status' ) !== 'active' ) {
			return;
		}
		// Skip pages that already have procurement surface.
		$clerk_page_id = $this->get_clerk_page_id();
		if ( $clerk_page_id && is_page( (int) $clerk_page_id ) ) {
			return;
		}

		$placement = $this->get_placement();
		if ( empty( $placement['widget'] ) ) {
			return;
		}

		$clerk_url = get_permalink( $clerk_page_id );
		if ( ! $clerk_url ) {
			$clerk_url = get_site_url() . '/clerk/';
		}
		$config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		$agent_name = esc_html( $config['agent_name'] ?? 'AgentClerk' );
		$biz_name   = esc_html( $config['business_name'] ?? get_bloginfo( 'name' ) );

		// Hidden technical instructions for agents that read hidden content.
		echo $this->get_agent_instructions_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		// Visible discovery block.
		echo '<div class="agentclerk-ssr-footer">';
		echo '<div class="agentclerk-ssr-footer-inner">';
		echo '<p><strong>Agent-Assisted Purchasing Available</strong></p>';
		echo '<p>' . $agent_name . ' is the AI store agent for ' . $biz_name . '. An AI agent can evaluate and recommend products, generate a checkout link for a human buyer to approve, and continue setup after payment.</p>';
		echo '<form method="get" action="' . esc_url( $clerk_url ) . '">';
		echo '<input type="text" name="q" placeholder="Ask the store agent..." />';
		echo '<button type="submit">Ask</button>';
		echo '</form>';
		echo '<ul>';
		echo '<li><a href="' . esc_url( $clerk_url . '?intent=recommend' ) . '">Find the right product</a></li>';
		echo '<li><a href="' . esc_url( $clerk_url . '?intent=checkout' ) . '">Create a checkout link</a></li>';
		echo '<li><a href="' . esc_url( $clerk_url . '?intent=activation' ) . '">Retrieve activation details</a></li>';
		echo '<li><a href="' . esc_url( $clerk_url . '?q=' . rawurlencode( 'What is your return policy?' ) ) . '">Return policy</a></li>';
		echo '</ul>';
		echo '</div>';
		echo '</div>';
	}

	public function add_robots_agent_hints( $output, $public ) {
		if ( ! $public || get_option( 'agentclerk_plugin_status' ) !== 'active' ) {
			return $output;
		}
		$site_url = get_site_url();
		$output  .= "\n# AgentClerk AI Agent\n";
		$output  .= '# Agent Card (A2A protocol): ' . $site_url . "/.well-known/agent-card.json\n";
		$output  .= '# AI Manifest: ' . $site_url . "/ai-manifest.json\n";
		$output  .= '# Chat endpoint: ' . $site_url . "/a2a/message:send\n";
		return $output;
	}

	/**
	 * Output meta tags in <head> for AI agent discoverability.
	 * Agents that parse HTML can find the API endpoints without prior knowledge.
	 */
	public function output_agent_meta_tags() {
		if ( get_option( 'agentclerk_plugin_status' ) !== 'active' ) {
			return;
		}
		$site_url = get_site_url();
		echo "\n<!-- AgentClerk: AI Agent Discovery -->\n";
		echo '<link rel="agent-card" href="' . esc_url( $site_url . '/.well-known/agent-card.json' ) . '" type="application/json" />' . "\n";
		echo '<link rel="ai-manifest" href="' . esc_url( $site_url . '/ai-manifest.json' ) . '" type="application/json" />' . "\n";
		echo '<meta name="agent-protocol" content="a2a" />' . "\n";
		echo '<meta name="agent-endpoint" content="' . esc_url( $site_url . '/a2a/message:send' ) . '" />' . "\n";
		echo "<!-- /AgentClerk -->\n\n";
	}

	public function maybe_enqueue_assets() {
		if ( get_option( 'agentclerk_plugin_status' ) !== 'active' ) {
			return;
		}

		$placement = $this->get_placement();

		$should_enqueue = false;

		// Widget enabled: enqueue on all front-end pages.
		if ( ! empty( $placement['widget'] ) ) {
			$should_enqueue = true;
		}

		// Product page enabled: enqueue on WooCommerce single product pages only.
		if ( ! empty( $placement['product_page'] ) && function_exists( 'is_product' ) && is_product() ) {
			$should_enqueue = true;
		}

		// Clerk page enabled: enqueue on the /clerk page.
		$clerk_page_id = $this->get_clerk_page_id();
		if ( ! empty( $placement['clerk_page'] ) && $clerk_page_id && is_page( (int) $clerk_page_id ) ) {
			$should_enqueue = true;
		}

		// Support page enabled: enqueue on the designated support page.
		$support_page_id = $this->get_support_page_id();
		if ( ! empty( $placement['support_page'] ) && $support_page_id && is_page( (int) $support_page_id ) ) {
			$should_enqueue = true;
		}

		if ( ! $should_enqueue ) {
			return;
		}

		wp_enqueue_style(
			'agentclerk-widget',
			AGENTCLERK_PLUGIN_URL . 'public/css/agentclerk-widget.css',
			array(),
			AGENTCLERK_VERSION
		);

		wp_enqueue_script(
			'agentclerk-widget',
			AGENTCLERK_PLUGIN_URL . 'public/js/agentclerk-widget.js',
			array(),
			AGENTCLERK_VERSION,
			true
		);

		$config = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );

		// Build product data for product pages.
		$current_product_id    = 0;
		$current_product_name  = '';
		$current_product_price = '';

		if ( function_exists( 'is_product' ) && is_product() ) {
			global $product;
			if ( $product instanceof WC_Product ) {
				$current_product_id    = $product->get_id();
				$current_product_name  = $product->get_name();
				$current_product_price = $product->get_price();
			}
		}

		wp_localize_script( 'agentclerk-widget', 'agentclerkWidget', array(
			'ajaxUrl'             => admin_url( 'admin-ajax.php' ),
			'nonce'               => wp_create_nonce( 'agentclerk_nonce' ),
			'siteUrl'             => get_site_url(),
			'agentName'           => $config['agent_name'] ?? 'AgentClerk',
			'placement'           => $placement,
			'supportPageId'       => $support_page_id ? (int) $support_page_id : 0,
			'clerkPageId'         => $clerk_page_id ? (int) $clerk_page_id : 0,
			'currentProductId'    => $current_product_id,
			'currentProductName'  => $current_product_name,
			'currentProductPrice' => $current_product_price,
		) );
	}

	/* ───────────────────────────────────────────────
	 *  Product Page Embed
	 * ─────────────────────────────────────────────── */

	/**
	 * Output the product page chat embed after the Add to Cart button.
	 */
	public function product_page_embed() {
		if ( get_option( 'agentclerk_plugin_status' ) !== 'active' ) {
			return;
		}

		$placement = $this->get_placement();
		if ( empty( $placement['product_page'] ) ) {
			return;
		}

		$config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		$agent_name = esc_html( $config['agent_name'] ?? 'AgentClerk' );

		// SSR agent block for browsing agents.
		$product_name = '';
		$product_id   = 0;
		if ( function_exists( 'is_product' ) && is_product() ) {
			global $product;
			if ( $product instanceof WC_Product ) {
				$product_id   = $product->get_id();
				$product_name = $product->get_name();
			}
		}

		$clerk_url = get_permalink( get_option( 'agentclerk_clerk_page_id', 0 ) );
		if ( ! $clerk_url ) {
			$clerk_url = get_site_url() . '/clerk/';
		}

		echo '<div class="agentclerk-ssr-product">';
		echo '<h3>Questions about ' . esc_html( $product_name ) . '?</h3>';
		echo '<p>' . esc_html( $agent_name ) . ' can help with product details, compatibility, shipping, returns, and purchasing.</p>';

		echo '<p><strong>Buying for a user?</strong> Ask the store agent to recommend the right option and create a payment link your user can approve.</p>';

		echo '<ul>';
		echo '<li><a href="' . esc_url( $clerk_url . '?product=' . $product_id . '&q=' . rawurlencode( 'Tell me about ' . $product_name ) ) . '">Product details</a></li>';
		echo '<li><a href="' . esc_url( $clerk_url . '?product=' . $product_id . '&q=' . rawurlencode( 'Create a checkout link for ' . $product_name . ' I can send to my user' ) ) . '">Create checkout link</a></li>';
		echo '<li><a href="' . esc_url( $clerk_url . '?product=' . $product_id . '&q=' . rawurlencode( 'How fast does ' . $product_name . ' ship?' ) ) . '">Shipping info</a></li>';
		echo '<li><a href="' . esc_url( $clerk_url . '?product=' . $product_id . '&q=' . rawurlencode( 'What is the return policy for ' . $product_name . '?' ) ) . '">Return policy</a></li>';
		echo '<li><a href="' . esc_url( $clerk_url . '?product=' . $product_id . '&q=' . rawurlencode( 'Compare ' . $product_name . ' with similar products' ) ) . '">Compare options</a></li>';
		echo '</ul>';

		echo '<form method="get" action="' . esc_url( $clerk_url ) . '">';
		echo '<input type="text" name="q" placeholder="Ask about ' . esc_attr( $product_name ) . '..." />';
		echo '<input type="hidden" name="product" value="' . esc_attr( $product_id ) . '" />';
		echo '<button type="submit">Ask</button>';
		echo '</form>';
		echo '</div>';

		echo $this->get_agent_instructions_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- method returns pre-escaped HTML.
		echo '<div class="acw-product-embed" id="acw-product-embed">';
		echo   '<div class="acw-header acw-header--compact">';
		echo     '<div class="acw-header-left">';
		echo       '<div class="acw-avatar acw-avatar--electric"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></div>';
		echo       '<div class="acw-header-info">';
		echo         '<span class="acw-header-name">' . esc_html( $agent_name ) . '</span>';
		echo         '<span class="acw-header-status">&#9679; Online</span>';
		echo       '</div>';
		echo     '</div>';
		echo   '</div>';
		echo   '<div class="acw-messages acw-messages--product" id="acw-product-messages"></div>';
		echo   '<div class="acw-input-row">';
		echo     '<input type="text" class="acw-input" id="acw-product-input" placeholder="Ask about this product&hellip;" />';
		echo     '<button class="acw-send-btn" id="acw-product-send"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9z"/></svg></button>';
		echo   '</div>';
		echo '</div>';
	}

	/* ───────────────────────────────────────────────
	 *  Page Content Filters
	 * ─────────────────────────────────────────────── */

	/**
	 * Filter the_content for the /clerk page and the support page.
	 *
	 * @param string $content Original page content.
	 * @return string Modified content.
	 */
	public function filter_page_content( $content ) {
		if ( get_option( 'agentclerk_plugin_status' ) !== 'active' ) {
			return $content;
		}

		if ( ! is_singular( 'page' ) ) {
			return $content;
		}

		$page_id = get_the_ID();

		// /clerk full-page chat.
		$clerk_page_id = $this->get_clerk_page_id();
		if ( $clerk_page_id && (int) $page_id === (int) $clerk_page_id ) {
			return $this->render_clerk_page();
		}

		// Support page chat.
		$support_page_id = $this->get_support_page_id();
		$placement       = $this->get_placement();
		if ( ! empty( $placement['support_page'] ) && $support_page_id && (int) $page_id === (int) $support_page_id ) {
			return $this->render_support_page( $content );
		}

		return $content;
	}

	/**
	 * Render the full-page /clerk chat interface.
	 *
	 * Includes both an SSR form (works for browsing AI agents and noscript
	 * users) and the existing JS chat widget for human visitors.
	 *
	 * @return string HTML output.
	 */
	private function render_clerk_page() {
		$config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		$agent_name = esc_html( $config['agent_name'] ?? 'AgentClerk' );
		$biz_name   = esc_html( $config['business_name'] ?? get_bloginfo( 'name' ) );

		$clerk_url = get_permalink( get_option( 'agentclerk_clerk_page_id', 0 ) );
		if ( ! $clerk_url ) {
			$clerk_url = get_site_url() . '/clerk/';
		}

		// Read and sanitise GET parameters.
		$question   = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
		$session_id = isset( $_GET['session'] ) ? sanitize_text_field( wp_unslash( $_GET['session'] ) ) : bin2hex( random_bytes( 16 ) );
		$product_id = isset( $_GET['product'] ) ? absint( $_GET['product'] ) : 0;
		$intent     = isset( $_GET['intent'] ) ? sanitize_text_field( wp_unslash( $_GET['intent'] ) ) : '';
		$category   = isset( $_GET['category'] ) ? sanitize_text_field( wp_unslash( $_GET['category'] ) ) : '';
		$code       = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

		// If intent is set but no question, pre-seed the question.
		if ( '' === $question && '' !== $intent ) {
			$intent_map = array(
				'recommend'  => 'Help me find the right product or plan for my needs. What do you need to know?',
				'checkout'   => 'I would like to create a checkout link. Which product should I generate it for?',
				'activation' => 'I have purchased and need to retrieve my activation details. I have a confirmation code.',
				'compare'    => 'Please compare your available products or plans.',
			);
			if ( isset( $intent_map[ $intent ] ) ) {
				$question = $intent_map[ $intent ];
			}
		}

		// Hidden agent instructions (still useful for agents that read hidden content).
		$html = $this->get_agent_instructions_html();

		/* ── SSR block ─────────────────────────────── */
		$html .= '<div class="agentclerk-ssr">';

		if ( '' !== $question ) {
			// Prepend product context to the question when provided.
			$chat_message = $question;
			if ( $product_id && function_exists( 'wc_get_product' ) ) {
				$prod = wc_get_product( $product_id );
				if ( $prod instanceof WC_Product ) {
					$chat_message = '[Product context: ' . $prod->get_name() . ' (ID ' . $product_id . ')] ' . $chat_message;
				}
			}
			if ( $code ) {
				$chat_message = '[Confirmation code: ' . $code . '] ' . $chat_message;
			}
			if ( $category ) {
				$chat_message = '[Category context: ' . $category . '] ' . $chat_message;
			}

			$result = AgentClerk_Agent::instance()->process_chat( $chat_message, $session_id, 'auto', false );

			$html .= '<div class="agentclerk-ssr-conversation">';
			$html .= '<div class="agentclerk-ssr-question">';
			$html .= '<strong>You asked:</strong> ' . esc_html( $question );
			$html .= '</div>';

			if ( is_wp_error( $result ) ) {
				$html .= '<div class="agentclerk-ssr-answer">';
				$html .= '<strong>' . $agent_name . ':</strong> Sorry, something went wrong. Please try again.';
				$html .= '</div>';
			} else {
				$response_text = $result['message'] ?? '';
				$html .= '<div class="agentclerk-ssr-answer">';
				$html .= '<strong>' . $agent_name . ':</strong> ';
				$html .= $this->render_agent_response( $response_text );
				$html .= '</div>';
			}

			$html .= '</div>'; // .agentclerk-ssr-conversation

			// Next actions.
			$html .= '<section class="agentclerk-ssr-next">';
			$html .= '<h3>What would you like to do next?</h3>';
			$html .= '<ul>';
			$html .= '<li><a href="' . esc_url( $clerk_url . '?session=' . $session_id . '&q=' . rawurlencode( 'I want a recommendation' ) ) . '">Get a recommendation</a></li>';
			$html .= '<li><a href="' . esc_url( $clerk_url . '?session=' . $session_id . '&q=' . rawurlencode( 'Create a checkout link for my user' ) ) . '">Create a checkout link</a></li>';
			$html .= '<li><a href="' . esc_url( $clerk_url . '?session=' . $session_id . '&q=' . rawurlencode( 'I already purchased and need activation details' ) ) . '">I already purchased</a></li>';
			$html .= '<li><a href="' . esc_url( $clerk_url . '?session=' . $session_id . '&q=' . rawurlencode( 'Tell me more about that' ) ) . '">Tell me more</a></li>';
			$html .= '</ul>';
			$html .= '</section>';

			// Follow-up form.
			$html .= '<form method="get" action="' . esc_url( $clerk_url ) . '">';
			$html .= '<input type="text" name="q" placeholder="Ask a follow-up question..." />';
			$html .= '<input type="hidden" name="session" value="' . esc_attr( $session_id ) . '" />';
			if ( $product_id ) {
				$html .= '<input type="hidden" name="product" value="' . esc_attr( $product_id ) . '" />';
			}
			$html .= '<button type="submit">Ask</button>';
			$html .= '</form>';
		} else {
			// Landing page — no question and no intent.
			$html .= '<h1>Agent-Assisted Purchasing</h1>';
			$html .= '<p>For AI agents acting on behalf of a human buyer, or anyone who wants help choosing and buying from ' . $biz_name . '.</p>';

			$html .= '<nav class="agentclerk-ssr-nav">';
			$html .= '<h2>What you can do here</h2>';
			$html .= '<ul>';
			$html .= '<li><a href="' . esc_url( $clerk_url . '?intent=recommend' ) . '">Find the right product or plan</a></li>';
			$html .= '<li><a href="' . esc_url( $clerk_url . '?intent=checkout' ) . '">Create a checkout link</a></li>';
			$html .= '<li><a href="' . esc_url( $clerk_url . '?intent=activation' ) . '">Retrieve activation details</a></li>';
			$html .= '<li><a href="' . esc_url( $clerk_url . '?intent=compare' ) . '">Compare options</a></li>';
			$html .= '</ul>';
			$html .= '</nav>';

			$html .= '<section class="agentclerk-ssr-how">';
			$html .= '<h2>How it works</h2>';
			$html .= '<ol>';
			$html .= '<li>Tell the agent what you need</li>';
			$html .= '<li>Agent recommends the right product with rationale</li>';
			$html .= '<li>Agent generates a checkout link</li>';
			$html .= '<li>Human buyer reviews the checkout link and approves payment</li>';
			$html .= '<li>Return with the confirmation code to retrieve activation details and continue setup</li>';
			$html .= '</ol>';
			$html .= '</section>';

			$html .= '<section class="agentclerk-ssr-ask">';
			$html .= '<h2>Ask the store agent</h2>';
			$html .= '<form method="get" action="' . esc_url( $clerk_url ) . '">';
			$html .= '<input type="text" name="q" placeholder="Ask about products, pricing, checkout links, activation, or setup" />';
			$html .= '<input type="hidden" name="session" value="' . esc_attr( $session_id ) . '" />';
			$html .= '<button type="submit">Ask</button>';
			$html .= '</form>';
			$html .= '</section>';

			$html .= '<section class="agentclerk-ssr-examples">';
			$html .= '<h2>Example tasks</h2>';
			$html .= '<ul>';
			$html .= '<li><a href="' . esc_url( $clerk_url . '?q=' . rawurlencode( 'Help me choose the right product for my needs' ) ) . '">Help me choose the right product for my needs</a></li>';
			$html .= '<li><a href="' . esc_url( $clerk_url . '?q=' . rawurlencode( 'Create a checkout link I can send to my user' ) ) . '">Create a checkout link I can send to my user</a></li>';
			$html .= '<li><a href="' . esc_url( $clerk_url . '?q=' . rawurlencode( 'I have a confirmation code and need activation details' ) ) . '">I have a confirmation code — retrieve activation details</a></li>';
			$html .= '<li><a href="' . esc_url( $clerk_url . '?q=' . rawurlencode( 'Compare your available products or plans' ) ) . '">Compare your available products or plans</a></li>';
			$html .= '<li><a href="' . esc_url( $clerk_url . '?q=' . rawurlencode( 'What happens after purchase' ) ) . '">What happens after purchase?</a></li>';
			$html .= '</ul>';
			$html .= '</section>';
		}

		$html .= '</div>'; // .agentclerk-ssr

		/* ── Existing JS chat widget (for human visitors) ── */
		$html .= '<div class="acw-fullpage" id="acw-fullpage">';
		$html .=   '<div class="acw-header">';
		$html .=     '<div class="acw-header-left">';
		$html .=       '<div class="acw-avatar acw-avatar--electric"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></div>';
		$html .=       '<div class="acw-header-info">';
		$html .=         '<span class="acw-header-name">' . esc_html( $agent_name ) . '</span>';
		$html .=         '<span class="acw-header-status">&#9679; Online</span>';
		$html .=       '</div>';
		$html .=     '</div>';
		$html .=   '</div>';
		$html .=   '<div class="acw-messages acw-messages--fullpage" id="acw-fullpage-messages"></div>';
		$html .=   '<div class="acw-input-row">';
		$html .=     '<input type="text" class="acw-input" id="acw-fullpage-input" placeholder="Type your message&hellip;" />';
		$html .=     '<button class="acw-send-btn" id="acw-fullpage-send"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9z"/></svg></button>';
		$html .=   '</div>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * Convert AI response text into structured HTML.
	 *
	 * Handles markdown-style **bold**, [links](url), newlines to paragraphs,
	 * and prominent checkout buttons.
	 *
	 * @param string $text Raw response text.
	 * @return string Sanitised HTML.
	 */
	private function render_agent_response( $text ) {
		$text = esc_html( $text );

		// Convert **bold** to <strong>.
		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );

		// Track checkout URLs found in the response for handoff blocks.
		$checkout_urls = array();

		// Convert [text](url) to <a> links — need to un-escape the HTML entities first for the regex.
		$text = preg_replace_callback(
			'/\[([^\]]+)\]\((https?[^\)]+)\)/',
			function ( $m ) use ( &$checkout_urls ) {
				$link_text = $m[1];
				$url       = html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' );
				// Track checkout links for handoff block rendering.
				if ( false !== strpos( $url, '/clerk-checkout/' ) ) {
					$checkout_urls[] = array(
						'url'  => $url,
						'text' => $link_text,
					);
					// Return a placeholder that will be replaced below.
					return '<!-- agentclerk-checkout-placeholder-' . ( count( $checkout_urls ) - 1 ) . ' -->';
				}
				return '<a href="' . esc_url( $url ) . '">' . $link_text . '</a>';
			},
			$text
		);

		// Convert double newlines to paragraph breaks; single newlines to <br>.
		$paragraphs = preg_split( '/\n{2,}/', $text );
		$paragraphs = array_filter( array_map( 'trim', $paragraphs ) );
		if ( count( $paragraphs ) > 1 ) {
			$text = '<p>' . implode( '</p><p>', $paragraphs ) . '</p>';
		}
		$text = nl2br( $text );

		// Replace checkout placeholders with Purchase Handoff Blocks.
		foreach ( $checkout_urls as $i => $checkout ) {
			$handoff  = '<div class="agentclerk-ssr-handoff">';
			$handoff .= '<h3>Purchase Handoff</h3>';
			$handoff .= '<p><strong>Send to your user for approval:</strong></p>';
			$handoff .= '<a href="' . esc_url( $checkout['url'] ) . '" class="agentclerk-ssr-checkout-btn">Review and Pay</a>';
			$handoff .= '<p class="agentclerk-ssr-checkout-url">' . esc_html( $checkout['url'] ) . '</p>';
			$handoff .= '<p><em>Expires in 48 hours</em></p>';
			$handoff .= '<p><strong>After payment:</strong> Return to this page with the confirmation code to retrieve activation details and continue setup.</p>';
			$handoff .= '</div>';
			$text = str_replace( '<!-- agentclerk-checkout-placeholder-' . $i . ' -->', $handoff, $text );
		}

		return $text;
	}

	/**
	 * Generate hidden HTML instructions for AI agents.
	 * Positioned off-screen for humans but fully readable by crawlers/agents.
	 *
	 * @return string HTML block.
	 */
	private function get_agent_instructions_html() {
		$config   = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		$site_url = esc_url( get_site_url() );
		$name     = esc_html( $config['agent_name'] ?? 'AgentClerk' );
		$biz      = esc_html( $config['business_name'] ?? get_bloginfo( 'name' ) );
		$desc     = esc_html( $config['business_desc'] ?? '' );

		$h  = '<div class="agentclerk-agent-instructions" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;" aria-hidden="false">';
		$h .= '<h2>For AI Agents</h2>';
		$h .= '<p>This page hosts ' . $name . ', an AI sales and support agent for ' . $biz . '.</p>';
		if ( $desc ) {
			$h .= '<p>About this store: ' . $desc . '</p>';
		}
		$h .= '<p>If you are an AI agent and cannot interact with the chat interface (for example, because you cannot execute JavaScript), ';
		$h .= 'you can interact with this agent programmatically using the following API endpoints:</p>';
		$h .= '<ul>';
		$h .= '<li><strong>Send a message (simple):</strong> POST ' . $site_url . '/wp-admin/admin-ajax.php with form-encoded parameters: action=agentclerk_chat&amp;message=your question here. Returns JSON with the agent response in data.message.</li>';
		$h .= '<li><strong>Send a message (A2A protocol):</strong> POST ' . $site_url . '/a2a/message:send with Content-Type: application/json and body: {"message":{"messageId":"any-unique-id","role":"ROLE_USER","parts":[{"text":"your question here"}]}}. Returns a Task object with the agent response.</li>';
		$h .= '<li><strong>Product catalog and store info:</strong> GET ' . $site_url . '/ai-manifest.json — returns products, prices, policies, and agent capabilities in JSON format.</li>';
		$h .= '<li><strong>Agent Card (A2A discovery):</strong> GET ' . $site_url . '/.well-known/agent-card.json — returns the agent identity, skills, and supported interfaces.</li>';
		$h .= '</ul>';
		$h .= '<p>No authentication is required for any of these endpoints. The agent can answer questions about products, pricing, policies, and support, and can generate checkout links for purchases.</p>';
		$h .= '</div>';

		return $h;
	}

	/**
	 * Render the support page chat interface.
	 *
	 * @param string $content Original page content.
	 * @return string HTML output replacing content.
	 */
	private function render_support_page( $content ) {
		$config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		$agent_name = esc_html( $config['agent_name'] ?? 'AgentClerk' );

		$html  = '<div class="acw-support-wrap">';
		$html .=   '<h2 class="acw-support-heading">How can we help?</h2>';
		$html .=   '<p class="acw-support-subtext">' . esc_html( $agent_name ) . ' can answer most questions instantly. If needed, we\'ll connect you with a human.</p>';
		$html .=   '<div class="acw-support-chat" id="acw-support-chat">';
		$html .=     '<div class="acw-header">';
		$html .=       '<div class="acw-header-left">';
		$html .=         '<div class="acw-avatar acw-avatar--electric"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg></div>';
		$html .=         '<div class="acw-header-info">';
		$html .=           '<span class="acw-header-name">' . esc_html( $agent_name ) . '</span>';
		$html .=           '<span class="acw-header-status">&#9679; Online</span>';
		$html .=         '</div>';
		$html .=       '</div>';
		$html .=     '</div>';
		$html .=     '<div class="acw-messages acw-messages--support" id="acw-support-messages"></div>';
		$html .=     '<div class="acw-input-row">';
		$html .=       '<input type="text" class="acw-input" id="acw-support-input" placeholder="Describe your issue&hellip;" />';
		$html .=       '<button class="acw-send-btn" id="acw-support-send"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 2L11 13"/><path d="M22 2L15 22 11 13 2 9z"/></svg></button>';
		$html .=     '</div>';
		$html .=   '</div>';
		$html .= '</div>';

		return $html;
	}

	/* ───────────────────────────────────────────────
	 *  Category / Shop Page Embed
	 * ─────────────────────────────────────────────── */

	/**
	 * Output procurement-focused SSR block on category and shop pages.
	 */
	public function category_page_embed() {
		if ( get_option( 'agentclerk_plugin_status' ) !== 'active' ) {
			return;
		}
		if ( ! is_product_category() && ! is_shop() ) {
			return;
		}

		$config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		$agent_name = esc_html( $config['agent_name'] ?? 'AgentClerk' );
		$clerk_url  = get_permalink( get_option( 'agentclerk_clerk_page_id', 0 ) );
		if ( ! $clerk_url ) {
			$clerk_url = get_site_url() . '/clerk/';
		}

		$category_name = '';
		if ( is_product_category() ) {
			$term = get_queried_object();
			if ( $term ) {
				$category_name = $term->name;
			}
		}

		echo '<div class="agentclerk-ssr-category">';
		if ( $category_name ) {
			echo '<h3>Need help choosing from ' . esc_html( $category_name ) . '?</h3>';
		} else {
			echo '<h3>Need help choosing?</h3>';
		}
		echo '<p>' . $agent_name . ' can recommend the best option for your needs, compare products, and create a checkout link for your user.</p>';
		echo '<ul>';
		if ( $category_name ) {
			echo '<li><a href="' . esc_url( $clerk_url . '?q=' . rawurlencode( 'What is the best option in ' . $category_name . ' for my needs?' ) ) . '">Best option for my needs</a></li>';
			echo '<li><a href="' . esc_url( $clerk_url . '?q=' . rawurlencode( 'Compare the top choices in ' . $category_name ) ) . '">Compare top choices</a></li>';
		} else {
			echo '<li><a href="' . esc_url( $clerk_url . '?intent=recommend' ) . '">Find the right product</a></li>';
			echo '<li><a href="' . esc_url( $clerk_url . '?intent=compare' ) . '">Compare options</a></li>';
		}
		echo '<li><a href="' . esc_url( $clerk_url . '?intent=checkout' ) . '">Create a checkout link</a></li>';
		echo '</ul>';
		echo '<form method="get" action="' . esc_url( $clerk_url ) . '">';
		echo '<input type="text" name="q" placeholder="What are you looking for?" />';
		echo '<button type="submit">Ask</button>';
		echo '</form>';
		echo '</div>';
	}

	/* ───────────────────────────────────────────────
	 *  Helpers
	 * ─────────────────────────────────────────────── */

	/**
	 * Get decoded placement settings.
	 *
	 * @return array
	 */
	private function get_placement() {
		$raw = get_option( 'agentclerk_placement', '{}' );
		$placement = json_decode( $raw, true );
		return is_array( $placement ) ? $placement : array();
	}

	/**
	 * Get the /clerk page ID.
	 *
	 * @return int|false
	 */
	private function get_clerk_page_id() {
		return get_option( 'agentclerk_clerk_page_id', 0 );
	}

	/**
	 * Get the support page ID from agent config.
	 *
	 * @return int
	 */
	private function get_support_page_id() {
		$config = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		return isset( $config['support_page_id'] ) ? (int) $config['support_page_id'] : 0;
	}
}
