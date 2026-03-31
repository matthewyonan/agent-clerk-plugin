<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI manifest generator for AgentClerk.
 *
 * Serves the ai-manifest.json endpoint that external AI agents use to
 * discover the store's products and capabilities. Cached for 15 minutes
 * via transient. Schema is versioned.
 *
 * @since 1.0.0
 */
class AgentClerk_Manifest {

	/**
	 * Singleton instance.
	 *
	 * @var AgentClerk_Manifest|null
	 */
	private static $instance = null;

	/**
	 * Manifest schema version.
	 *
	 * @var string
	 */
	const SCHEMA_VERSION = '1.0';

	/**
	 * Cache duration in seconds (15 minutes).
	 *
	 * @var int
	 */
	const CACHE_TTL = 900;

	/**
	 * Return the singleton instance.
	 *
	 * @return AgentClerk_Manifest
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
		add_action( 'template_redirect', array( $this, 'handle_manifest_request' ) );
		add_action( 'save_post_product', array( $this, 'bust_cache' ) );
		add_action( 'woocommerce_update_product', array( $this, 'bust_cache' ) );
	}

	/**
	 * Handle the ai-manifest.json request via template_redirect.
	 */
	public function handle_manifest_request() {
		if ( ! get_query_var( 'agentclerk_manifest' ) ) {
			return;
		}

		// Return cached version if available.
		$cached = get_transient( 'agentclerk_manifest_cache' );
		if ( $cached ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'X-AgentClerk-Cached: true' );
			echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-encoded JSON.
			exit;
		}

		$manifest = $this->generate_manifest();
		$json     = wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );

		set_transient( 'agentclerk_manifest_cache', $json, self::CACHE_TTL );

		header( 'Content-Type: application/json; charset=utf-8' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-encoded JSON.
		exit;
	}

	/**
	 * Generate the full manifest array.
	 *
	 * @return array Manifest data.
	 */
	private function generate_manifest() {
		$config   = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		$products = $this->get_visible_products( $config );

		$manifest = array(
			'manifest_version' => self::SCHEMA_VERSION,
			'schema_version'   => self::SCHEMA_VERSION,
			'generated_at'     => current_time( 'c' ),
			'business'         => array(
				'name'        => $config['business_name'] ?? get_bloginfo( 'name' ),
				'description' => $config['business_desc'] ?? get_bloginfo( 'description' ),
				'url'         => get_site_url(),
			),
			'agent_endpoint'    => get_site_url() . '/a2a/message:send',
			'agent_card'        => get_site_url() . '/.well-known/agent-card.json',
			'chat_page'         => get_permalink( get_option( 'agentclerk_clerk_page_id', 0 ) ) ?: null,
			'checkout_base_url' => get_site_url() . '/clerk-checkout/',
			'products'          => $products,
			'policies'          => array(
				'refund'   => ! empty( $config['policies']['refund'] ) ? mb_substr( $config['policies']['refund'], 0, 500 ) : null,
				'license'  => ! empty( $config['policies']['license'] ) ? mb_substr( $config['policies']['license'], 0, 500 ) : null,
				'delivery' => ! empty( $config['policies']['delivery'] ) ? mb_substr( $config['policies']['delivery'], 0, 500 ) : null,
			),
			'fulfillment_types' => $this->detect_fulfillment_types( $products ),
			'currency'          => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD',
			'support'           => array(
				'escalation_available' => true,
				'clerk_page'           => get_permalink( get_option( 'agentclerk_clerk_page_id', 0 ) ) ?: null,
			),
		);

		return $manifest;
	}

	/**
	 * Get visible products based on the agent config visibility settings.
	 * Only includes published, visible products.
	 *
	 * @param array $config Agent config.
	 * @return array Product data for the manifest.
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

			// Skip products explicitly hidden in config.
			if ( isset( $visibility[ $pid ] ) && ! $visibility[ $pid ] ) {
				continue;
			}

			$result[] = array(
				'id'          => $pid,
				'name'        => $product->get_name(),
				'price'       => (float) $product->get_price(),
				'type'        => $product->is_downloadable() ? 'digital' : 'physical',
				'description' => $product->get_short_description(),
				'available'   => $product->is_in_stock(),
				'url'         => get_permalink( $pid ),
			);
		}

		return $result;
	}

	/**
	 * Detect fulfillment types from the product list.
	 *
	 * @param array $products Product data.
	 * @return array Fulfillment type strings.
	 */
	private function detect_fulfillment_types( $products ) {
		$types = array();

		foreach ( $products as $p ) {
			if ( 'digital' === $p['type'] && ! in_array( 'digital_download', $types, true ) ) {
				$types[] = 'digital_download';
			}
			if ( 'physical' === $p['type'] && ! in_array( 'physical_shipping', $types, true ) ) {
				$types[] = 'physical_shipping';
			}
		}

		return $types ? $types : array( 'digital_download' );
	}

	/**
	 * Bust the manifest transient cache.
	 */
	public function bust_cache() {
		delete_transient( 'agentclerk_manifest_cache' );
	}
}
