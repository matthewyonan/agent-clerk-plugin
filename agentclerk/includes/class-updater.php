<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Self-hosted plugin updater.
 *
 * Checks the AgentClerk backend for new plugin versions (which in turn
 * checks GitHub releases) and integrates with WordPress's native update
 * system. Works for installs outside the WordPress.org directory.
 *
 * Backend endpoint:
 *   POST /api/plugin/update-check
 *   Body: { "currentVersion": "1.1.0" }
 *   Auth: X-AgentClerk-Secret + X-AgentClerk-Site headers
 *
 *   Returns on update available:
 *   {
 *     "update": {
 *       "slug": "agentclerk",
 *       "new_version": "1.2.0",
 *       "package": "https://github.com/.../releases/download/v1.2.0/agentclerk.zip",
 *       "url": "https://github.com/.../releases/tag/v1.2.0"
 *     }
 *   }
 *
 *   Returns when current: { "update": null }
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
	 * Cached remote update data.
	 *
	 * @var array|null
	 */
	private $remote_data = null;

	/**
	 * Constructor. Hooks into WordPress update system.
	 */
	public function __construct() {
		$this->plugin_file     = plugin_basename( AGENTCLERK_PLUGIN_DIR . 'agentclerk.php' );
		$this->current_version = AGENTCLERK_VERSION;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache' ), 10, 2 );
	}

	/**
	 * Inject update info into WordPress's update_plugins transient.
	 *
	 * This is what makes "Update Available" appear on the Plugins page.
	 *
	 * @param object $transient The update_plugins transient.
	 * @return object Modified transient.
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$update = $this->get_update_data();

		if ( $update && ! empty( $update['new_version'] ) ) {
			$transient->response[ $this->plugin_file ] = (object) array(
				'slug'        => $update['slug'] ?? $this->slug,
				'plugin'      => $this->plugin_file,
				'new_version' => $update['new_version'],
				'url'         => $update['url'] ?? 'https://agentclerk.io',
				'package'     => $update['package'] ?? '',
				'tested'      => $update['tested'] ?? '',
				'requires'    => $update['requires'] ?? '6.0',
			);
		} else {
			// No update — tell WP so it doesn't re-check constantly.
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
	 * Provide plugin info for the "View Details" modal on the Plugins page.
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

		$update = $this->get_update_data();
		$version = ( $update && ! empty( $update['new_version'] ) )
			? $update['new_version']
			: $this->current_version;

		return (object) array(
			'name'          => 'AgentClerk',
			'slug'          => $this->slug,
			'version'       => $version,
			'author'        => '<a href="https://abrilliantway.com">A Brilliant Way</a>',
			'homepage'      => 'https://agentclerk.io',
			'download_link' => ( $update && ! empty( $update['package'] ) ) ? $update['package'] : '',
			'tested'        => $update['tested'] ?? '',
			'requires'      => $update['requires'] ?? '6.0',
			'requires_php'  => $update['requires_php'] ?? '7.4',
			'sections'      => array(
				'description' => 'AI seller agent for WooCommerce. Answers buyers, closes sales, handles support automatically.',
				'changelog'   => $update['changelog'] ?? '<p>See <a href="' . esc_url( $update['url'] ?? 'https://github.com/matthewyonan/agent-clerk-plugin/releases' ) . '">release notes on GitHub</a>.</p>',
			),
		);
	}

	/**
	 * Clear cached update data after an update completes.
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
	 * Get update data from the backend, with 12-hour caching.
	 *
	 * POSTs to /api/plugin/update-check with the current version.
	 * The backend checks GitHub releases and returns the update payload
	 * if a newer version exists, or null if current.
	 *
	 * @return array|null Update data array or null if no update / error.
	 */
	private function get_update_data() {
		if ( null !== $this->remote_data ) {
			return $this->remote_data;
		}

		$cached = get_transient( 'agentclerk_update_check' );
		if ( false !== $cached ) {
			$this->remote_data = $cached;
			return $cached ?: null;
		}

		$install_secret = get_option( 'agentclerk_install_secret', '' );

		$response = wp_remote_post( AGENTCLERK_BACKEND_URL . '/plugin/update-check', array(
			'timeout' => 10,
			'headers' => array(
				'Content-Type'        => 'application/json',
				'X-AgentClerk-Secret' => $install_secret,
				'X-AgentClerk-Site'   => get_site_url(),
			),
			'body' => wp_json_encode( array(
				'currentVersion' => $this->current_version,
			) ),
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// Cache the failure briefly so we don't hammer the server.
			set_transient( 'agentclerk_update_check', '', 1 * HOUR_IN_SECONDS );
			$this->remote_data = '';
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || ! isset( $body['update'] ) ) {
			set_transient( 'agentclerk_update_check', '', 1 * HOUR_IN_SECONDS );
			$this->remote_data = '';
			return null;
		}

		// $body['update'] is either the update object or null.
		$update = $body['update'];

		if ( empty( $update ) ) {
			// Already on latest.
			set_transient( 'agentclerk_update_check', '', 12 * HOUR_IN_SECONDS );
			$this->remote_data = '';
			return null;
		}

		set_transient( 'agentclerk_update_check', $update, 12 * HOUR_IN_SECONDS );
		$this->remote_data = $update;

		return $update;
	}
}
