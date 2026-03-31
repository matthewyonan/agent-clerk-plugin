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
		// Skip pages that already have instructions embedded in their content.
		$clerk_page_id = $this->get_clerk_page_id();
		if ( $clerk_page_id && is_page( (int) $clerk_page_id ) ) {
			return;
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			return;
		}
		$placement = $this->get_placement();
		if ( ! empty( $placement['widget'] ) ) {
			echo $this->get_agent_instructions_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- method returns pre-escaped HTML.
		}
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
	 * @return string HTML output.
	 */
	private function render_clerk_page() {
		$config     = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		$agent_name = esc_html( $config['agent_name'] ?? 'AgentClerk' );

		$html  = $this->get_agent_instructions_html();

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
