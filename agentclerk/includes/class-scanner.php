<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site scanner for AgentClerk onboarding.
 *
 * Fetches sitemaps, extracts page content, detects policies, identifies gaps,
 * and builds the support knowledge file. Supports both synchronous scanning
 * (called from admin AJAX) and asynchronous dispatch via wp_schedule_single_event.
 *
 * AJAX hooks for start_scan / scan_progress are registered in AgentClerk_Admin,
 * NOT here. This class provides the scanning logic only.
 *
 * @since 1.0.0
 */
class AgentClerk_Scanner {

	/**
	 * Singleton instance.
	 *
	 * @var AgentClerk_Scanner|null
	 */
	private static $instance = null;

	/**
	 * Return the singleton instance.
	 *
	 * @return AgentClerk_Scanner
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Register the cron hook for async scanning.
	 */
	private function __construct() {
		add_action( 'agentclerk_run_scan', array( $this, 'run_scan_async' ) );
	}

	/**
	 * Start a site scan synchronously. Returns scan results.
	 *
	 * This is the primary entry point called by AgentClerk_Admin::start_scan().
	 *
	 * @return array Scan results.
	 */
	public static function start_scan() {
		$scanner = self::instance();
		return $scanner->execute_scan();
	}

	/**
	 * Dispatch an async scan via wp_schedule_single_event.
	 *
	 * Sets initial progress transient and spawns cron.
	 */
	public static function dispatch_async() {
		set_transient( 'agentclerk_scan_progress', array(
			'total'     => 0,
			'completed' => 0,
			'status'    => 'starting',
		), 3600 );

		wp_schedule_single_event( time(), 'agentclerk_run_scan' );
		spawn_cron();
	}

	/**
	 * Async scan handler (cron hook).
	 */
	public function run_scan_async() {
		$this->execute_scan();
	}

	/**
	 * Execute the full site scan.
	 *
	 * @return array Scan results.
	 */
	private function execute_scan() {
		$site_url = get_site_url();
		$urls     = self::fetch_sitemap_urls( $site_url );
		$total    = count( $urls );

		set_transient( 'agentclerk_scan_progress', array(
			'total'     => $total,
			'completed' => 0,
			'status'    => 'scanning',
		), 3600 );

		$pages    = array();
		$policies = array( 'refund' => '', 'license' => '', 'delivery' => '' );

		foreach ( $urls as $i => $url ) {
			$page_data = self::scan_page( $url );
			if ( $page_data ) {
				$pages[]  = $page_data;
				$policies = self::extract_policies( $page_data, $policies );
			}

			set_transient( 'agentclerk_scan_progress', array(
				'total'     => $total,
				'completed' => $i + 1,
				'status'    => 'scanning',
			), 3600 );
		}

		$products = self::get_wc_products();
		$gaps     = self::identify_gaps( $pages, $policies, $products );

		$scan_results = array(
			'pages'      => $pages,
			'products'   => $products,
			'policies'   => $policies,
			'gaps'       => $gaps,
			'page_count' => $total,
			'timestamp'  => current_time( 'mysql' ),
		);

		update_option( 'agentclerk_scan_cache', wp_json_encode( $scan_results ) );

		// Merge detected policies into agent config.
		$config             = json_decode( get_option( 'agentclerk_agent_config', '{}' ), true );
		$config['policies'] = $policies;

		// Auto-generate the support knowledge file.
		$support_file           = self::build_support_file( $scan_results );
		$config['support_file'] = $support_file;
		update_option( 'agentclerk_agent_config', wp_json_encode( $config ) );

		// Mark scan complete.
		set_transient( 'agentclerk_scan_progress', array(
			'total'     => $total,
			'completed' => $total,
			'status'    => 'complete',
			'gaps'      => $gaps,
		), 3600 );

		return $scan_results;
	}

	/**
	 * Fetch URLs from the site's sitemap.xml, falling back to sitemap_index.xml.
	 *
	 * @param string $site_url Site root URL.
	 * @return array List of page URLs.
	 */
	private static function fetch_sitemap_urls( $site_url ) {
		$urls    = array();
		$sitemap = wp_remote_get( $site_url . '/sitemap.xml', array( 'timeout' => 15 ) );

		if ( is_wp_error( $sitemap ) || 200 !== wp_remote_retrieve_response_code( $sitemap ) ) {
			$sitemap = wp_remote_get( $site_url . '/sitemap_index.xml', array( 'timeout' => 15 ) );
		}

		if ( is_wp_error( $sitemap ) || 200 !== wp_remote_retrieve_response_code( $sitemap ) ) {
			return array( $site_url );
		}

		$body = wp_remote_retrieve_body( $sitemap );
		$xml  = @simplexml_load_string( $body );

		if ( ! $xml ) {
			return array( $site_url );
		}

		// Handle sitemap index (contains <sitemap> children).
		if ( isset( $xml->sitemap ) ) {
			foreach ( $xml->sitemap as $sub ) {
				$sub_urls = self::parse_sitemap( (string) $sub->loc );
				$urls     = array_merge( $urls, $sub_urls );
			}
		} elseif ( isset( $xml->url ) ) {
			foreach ( $xml->url as $entry ) {
				$urls[] = (string) $entry->loc;
			}
		}

		// Filter out non-page resources.
		$exclude  = array( '.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp', '/wp-admin/', '/wp-json/', '/feed/' );
		$filtered = array();

		foreach ( $urls as $url ) {
			$skip = false;
			foreach ( $exclude as $ex ) {
				if ( stripos( $url, $ex ) !== false ) {
					$skip = true;
					break;
				}
			}
			if ( ! $skip ) {
				$filtered[] = $url;
			}
		}

		return $filtered;
	}

	/**
	 * Parse a single sitemap XML and return its URLs.
	 *
	 * @param string $url Sitemap URL.
	 * @return array List of page URLs.
	 */
	private static function parse_sitemap( $url ) {
		$urls    = array();
		$request = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) {
			return $urls;
		}

		$xml = @simplexml_load_string( wp_remote_retrieve_body( $request ) );
		if ( $xml && isset( $xml->url ) ) {
			foreach ( $xml->url as $entry ) {
				$urls[] = (string) $entry->loc;
			}
		}

		return $urls;
	}

	/**
	 * Scan a single page for text content and metadata.
	 *
	 * @param string $url Page URL.
	 * @return array|null Page data or null on failure.
	 */
	private static function scan_page( $url ) {
		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$html = wp_remote_retrieve_body( $response );
		$text = self::strip_to_text( $html );
		$type = self::classify_page( $url, $text );

		$emails = array();
		if ( preg_match_all( '/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $text, $matches ) ) {
			$emails = array_unique( $matches[0] );
		}

		return array(
			'url'    => $url,
			'type'   => $type,
			'text'   => mb_substr( $text, 0, 5000 ),
			'emails' => array_values( $emails ),
		);
	}

	/**
	 * Strip HTML to plain text, removing scripts, styles, nav, and footer.
	 *
	 * @param string $html Raw HTML.
	 * @return string Clean text.
	 */
	private static function strip_to_text( $html ) {
		$html = preg_replace( '/<script[^>]*>.*?<\/script>/si', '', $html );
		$html = preg_replace( '/<style[^>]*>.*?<\/style>/si', '', $html );
		$html = preg_replace( '/<nav[^>]*>.*?<\/nav>/si', '', $html );
		$html = preg_replace( '/<footer[^>]*>.*?<\/footer>/si', '', $html );
		$text = wp_strip_all_tags( $html );
		$text = preg_replace( '/\s+/', ' ', $text );
		return trim( $text );
	}

	/**
	 * Classify a page by its URL and content.
	 *
	 * @param string $url  Page URL.
	 * @param string $text Page text content.
	 * @return string Page type classification.
	 */
	private static function classify_page( $url, $text ) {
		$lower_url  = strtolower( $url );
		$lower_text = strtolower( $text );
		$combined   = $lower_url . ' ' . $lower_text;

		if ( preg_match( '/faq|frequently.asked/i', $combined ) ) {
			return 'faq';
		}
		if ( preg_match( '/refund|return.policy/i', $combined ) ) {
			return 'refund_policy';
		}
		if ( preg_match( '/privacy.policy/i', $combined ) ) {
			return 'privacy_policy';
		}
		if ( preg_match( '/terms|tos|terms.of.service/i', $combined ) ) {
			return 'terms';
		}
		if ( preg_match( '/contact|support|help/i', $lower_url ) ) {
			return 'contact';
		}
		if ( preg_match( '/about/i', $lower_url ) ) {
			return 'about';
		}
		if ( preg_match( '/shop|store|product/i', $lower_url ) ) {
			return 'shop';
		}

		return 'general';
	}

	/**
	 * Extract policies from a scanned page.
	 *
	 * @param array $page     Page data from scan_page().
	 * @param array $policies Current policy texts.
	 * @return array Updated policies.
	 */
	private static function extract_policies( $page, $policies ) {
		if ( 'refund_policy' === $page['type'] && empty( $policies['refund'] ) ) {
			$policies['refund'] = mb_substr( $page['text'], 0, 2000 );
		}
		if ( 'terms' === $page['type'] && empty( $policies['license'] ) ) {
			$policies['license'] = mb_substr( $page['text'], 0, 2000 );
		}
		if ( stripos( $page['text'], 'delivery' ) !== false || stripos( $page['text'], 'shipping' ) !== false ) {
			if ( empty( $policies['delivery'] ) ) {
				$policies['delivery'] = mb_substr( $page['text'], 0, 2000 );
			}
		}

		return $policies;
	}

	/**
	 * Identify knowledge gaps from scan results.
	 *
	 * @param array $pages    Scanned pages.
	 * @param array $policies Detected policies.
	 * @param array $products WooCommerce products.
	 * @return array List of gap descriptions.
	 */
	private static function identify_gaps( $pages, $policies, $products ) {
		$gaps = array();

		if ( empty( $policies['refund'] ) ) {
			$gaps[] = 'No refund policy found';
		}
		if ( empty( $policies['license'] ) ) {
			$gaps[] = 'No license/terms policy found';
		}
		if ( empty( $policies['delivery'] ) ) {
			$gaps[] = 'No delivery/shipping information found';
		}

		$has_faq           = false;
		$has_support_email = false;
		$has_about         = false;

		foreach ( $pages as $page ) {
			if ( 'faq' === $page['type'] ) {
				$has_faq = true;
			}
			if ( 'contact' === $page['type'] || ! empty( $page['emails'] ) ) {
				$has_support_email = true;
			}
			if ( 'about' === $page['type'] ) {
				$has_about = true;
			}
		}

		if ( ! $has_faq ) {
			$gaps[] = 'No FAQ page found';
		}
		if ( ! $has_support_email ) {
			$gaps[] = 'No support/contact email found';
		}
		if ( ! $has_about ) {
			$gaps[] = 'No about page found';
		}
		if ( empty( $products ) ) {
			$gaps[] = 'No WooCommerce products found';
		}

		return $gaps;
	}

	/**
	 * Get all published WooCommerce products.
	 *
	 * @return array Product data.
	 */
	private static function get_wc_products() {
		if ( ! function_exists( 'wc_get_products' ) ) {
			return array();
		}

		$products = wc_get_products( array( 'status' => 'publish', 'limit' => -1 ) );
		$result   = array();

		foreach ( $products as $product ) {
			$result[] = array(
				'id'          => $product->get_id(),
				'name'        => $product->get_name(),
				'price'       => $product->get_price(),
				'type'        => $product->get_type(),
				'description' => $product->get_short_description(),
				'status'      => $product->get_status(),
			);
		}

		return $result;
	}

	/**
	 * Build a support knowledge file from scan results.
	 *
	 * This auto-generates a formatted knowledge base that gets embedded
	 * in the agent's system prompt.
	 *
	 * @param array $scan_results Full scan results.
	 * @return string Formatted support knowledge text.
	 */
	public static function build_support_file( $scan_results ) {
		$text = "# Support Knowledge Base\n\n";
		$text .= "Generated: " . current_time( 'Y-m-d H:i:s' ) . "\n\n";

		// Products section.
		if ( ! empty( $scan_results['products'] ) ) {
			$text .= "## Products\n";
			foreach ( $scan_results['products'] as $p ) {
				$price = isset( $p['price'] ) ? '$' . $p['price'] : 'Price not set';
				$type  = isset( $p['type'] ) ? $p['type'] : 'unknown';
				$text .= "- {$p['name']}: {$price} ({$type})\n";
				if ( ! empty( $p['description'] ) ) {
					$text .= "  {$p['description']}\n";
				}
			}
			$text .= "\n";
		}

		// FAQ section.
		foreach ( $scan_results['pages'] as $page ) {
			if ( 'faq' === $page['type'] ) {
				$text .= "## FAQ\n";
				$text .= "Source: {$page['url']}\n";
				$text .= mb_substr( $page['text'], 0, 2000 ) . "\n\n";
			}
		}

		// Contact / support section.
		$contact_emails = array();
		foreach ( $scan_results['pages'] as $page ) {
			if ( 'contact' === $page['type'] ) {
				$text .= "## Contact & Support\n";
				$text .= "Source: {$page['url']}\n";
				$text .= mb_substr( $page['text'], 0, 1000 ) . "\n\n";
			}
			if ( ! empty( $page['emails'] ) ) {
				$contact_emails = array_merge( $contact_emails, $page['emails'] );
			}
		}

		if ( ! empty( $contact_emails ) ) {
			$contact_emails = array_unique( $contact_emails );
			$text .= "## Detected Contact Emails\n";
			foreach ( $contact_emails as $email ) {
				$text .= "- {$email}\n";
			}
			$text .= "\n";
		}

		// Policy summaries.
		$policies = isset( $scan_results['policies'] ) ? $scan_results['policies'] : array();

		if ( ! empty( $policies['refund'] ) ) {
			$text .= "## Refund Policy\n";
			$text .= mb_substr( $policies['refund'], 0, 1500 ) . "\n\n";
		}
		if ( ! empty( $policies['license'] ) ) {
			$text .= "## License / Terms\n";
			$text .= mb_substr( $policies['license'], 0, 1500 ) . "\n\n";
		}
		if ( ! empty( $policies['delivery'] ) ) {
			$text .= "## Delivery / Shipping\n";
			$text .= mb_substr( $policies['delivery'], 0, 1500 ) . "\n\n";
		}

		// About section.
		foreach ( $scan_results['pages'] as $page ) {
			if ( 'about' === $page['type'] ) {
				$text .= "## About\n";
				$text .= "Source: {$page['url']}\n";
				$text .= mb_substr( $page['text'], 0, 1000 ) . "\n\n";
			}
		}

		// Gaps section.
		if ( ! empty( $scan_results['gaps'] ) ) {
			$text .= "## Known Gaps\n";
			$text .= "The following information was not found during the site scan:\n";
			foreach ( $scan_results['gaps'] as $gap ) {
				$text .= "- {$gap}\n";
			}
			$text .= "\n";
		}

		return $text;
	}
}
