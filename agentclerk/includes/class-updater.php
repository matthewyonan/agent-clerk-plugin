<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Self-hosted plugin updater.
 *
 * Checks app.agentclerk.io for new plugin versions and integrates
 * with WordPress's native update system. No WordPress.org listing required.
 *
 * The backend should expose:
 *   GET /plugin/info — returns JSON with:
 *     {
 *       "version": "1.1.0",
 *       "download_url": "https://app.agentclerk.io/releases/agentclerk-1.1.0.zip",
 *       "tested": "6.7",
 *       "requires": "6.0",
 *       "requires_php": "7.4",
 *       "changelog": "<ul><li>Bug fixes</li></ul>"
 *     }
 *
 * @since 1.1.0
 */
class AgentClerk_Updater {

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $slug = 'agentclerk';

	/**
	 * Full path to the main plugin file, relative to plugins dir.
	 * e.g. "agentclerk/agentclerk.php"
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Current installed version.
	 *
	 * @var string
	 */
	private $current_version;

	/**
	 * Remote endpoint for version info.
	 *
	 * @var string
	 */
	private $update_url;

	/**
	 * Cached remote data.
	 *
	 * @var array|null
	 */
	private $remote_data = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->plugin_file     = plugin_basename( AGENTCLERK_PLUGIN_DIR . 'agentclerk.php' );
		$this->current_version = AGENTCLERK_VERSION;
		$this->update_url      = AGENTCLERK_BACKEND_URL . '/plugin/info';

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );
	}

	/**
	 * Check the remote server for a newer version.
	 *
	 * Hooks into the update_plugins transient so WordPress shows
	 * the update notice on the Plugins page.
	 *
	 * @param object $transient The update_plugins transient.
	 * @return object Modified transient.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = $this->get_remote_data();
		if ( ! $remote || empty( $remote['version'] ) ) {
			return $transient;
		}

		if ( version_compare( $remote['version'], $this->current_version, '>' ) ) {
			$transient->response[ $this->plugin_file ] = (object) array(
				'slug'        => $this->slug,
				'plugin'      => $this->plugin_file,
				'new_version' => $remote['version'],
				'url'         => 'https://agentclerk.io',
				'package'     => $remote['download_url'] ?? '',
				'tested'      => $remote['tested'] ?? '',
				'requires'    => $remote['requires'] ?? '6.0',
			);
		} else {
			// No update available — still report so WP doesn't re-check constantly.
			$transient->no_update[ $this->plugin_file ] = (object) array(
				'slug'        => $this->slug,
				'plugin'      => $this->plugin_file,
				'new_version' => $this->current_version,
				'url'         => 'https://agentclerk.io',
			);
		}

		return $transient;
	}

	/**
	 * Provide plugin info for the "View Details" modal in WordPress.
	 *
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The API action.
	 * @param object             $args   Plugin arguments.
	 * @return false|object
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}

		$remote = $this->get_remote_data();
		if ( ! $remote ) {
			return $result;
		}

		return (object) array(
			'name'          => 'AgentClerk',
			'slug'          => $this->slug,
			'version'       => $remote['version'] ?? $this->current_version,
			'author'        => '<a href="https://abrilliantway.com">A Brilliant Way</a>',
			'homepage'      => 'https://agentclerk.io',
			'download_link' => $remote['download_url'] ?? '',
			'tested'        => $remote['tested'] ?? '',
			'requires'      => $remote['requires'] ?? '6.0',
			'requires_php'  => $remote['requires_php'] ?? '7.4',
			'sections'      => array(
				'description' => 'AI seller agent for WooCommerce. Answers buyers, closes sales, handles support automatically.',
				'changelog'   => $remote['changelog'] ?? '',
			),
			'banners'       => array(
				'low'  => 'https://agentclerk.io/wp-content/uploads/banner-772x250.png',
				'high' => 'https://agentclerk.io/wp-content/uploads/banner-1544x500.png',
			),
		);
	}

	/**
	 * Clear the cached remote data after an update completes.
	 *
	 * @param object $upgrader WP_Upgrader instance.
	 * @param array  $options  Update options.
	 */
	public function clear_cache( $upgrader, $options ) {
		if ( 'update' === ( $options['action'] ?? '' ) && 'plugin' === ( $options['type'] ?? '' ) ) {
			delete_transient( 'agentclerk_update_check' );
			$this->remote_data = null;
		}
	}

	/**
	 * Fetch version info from the backend, with 12-hour caching.
	 *
	 * @return array|null Remote data or null on failure.
	 */
	private function get_remote_data() {
		if ( null !== $this->remote_data ) {
			return $this->remote_data;
		}

		$cached = get_transient( 'agentclerk_update_check' );
		if ( false !== $cached ) {
			$this->remote_data = $cached;
			return $cached;
		}

		$response = wp_remote_get( $this->update_url, array(
			'timeout' => 10,
			'headers' => array(
				'X-AgentClerk-Site'    => get_site_url(),
				'X-AgentClerk-Version' => $this->current_version,
			),
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// Cache the failure briefly so we don't hammer the server.
			set_transient( 'agentclerk_update_check', array(), 1 * HOUR_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['version'] ) ) {
			set_transient( 'agentclerk_update_check', array(), 1 * HOUR_IN_SECONDS );
			return null;
		}

		set_transient( 'agentclerk_update_check', $data, 12 * HOUR_IN_SECONDS );
		$this->remote_data = $data;

		return $data;
	}
}
