<?php
/**
 * EDD SL Updater
 *
 * @package edd-sl-updater
 * @author Easy Digital Downloads
 * @license MIT
 */

namespace EDD\Software_Licensing\Updater;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin_Updater
 *
 * Allows plugins to use their own update API.
 */
class Plugin_Updater {
	use API_Common;
	use Base;

	// phpcs:disable Squiz.Commenting.VariableComment.Missing
	private $api_url              = null;
	private $api_data             = [];
	private $file                 = null;
	private $slug                 = null;
	private $version              = null;
	private $wp_override          = false;
	private $cache_key            = null;
	private $strings              = null;
	private $health_check_timeout = 5;
	// phpcs:enable

	/**
	 * Class constructor.
	 *
	 * @param array $args    Configuration data.
	 * @param array $strings Messaging strings.
	 */
	public function __construct( $args = [], $strings = [] ) {
		global $edd_plugin_data;

		$this->api_url                  = $args['api_url'];
		$this->api_data                 = $args;
		$this->file                     = $args['file'];
		$this->slug                     = $args['slug'];
		$this->version                  = $args['version'];
		$this->wp_override              = $args['wp_override'];
		$this->beta                     = $args['beta'];
		$this->cache_key                = $args['cache_key'];
		$this->strings                  = $strings;
		$edd_plugin_data[ $this->slug ] = $this->api_data;

		$response = $this->get_repo_cache( $this->slug );
		if ( ! $response || ! isset( $response['data'] ) ) {
			$this->set_repo_cache( 'data', $args, $this->slug );
		}
	}

	/**
	 * Set up WordPress filters to hook into WP's update process.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_filter( 'site_transient_update_plugins', [ $this, 'check_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugins_api' ], 10, 3 );
	}

	/**
	 * Check for Updates at the defined API endpoint and modify the update array.
	 *
	 * This function dives into the update API just when WordPress creates its update array,
	 * then adds a custom API call and injects the custom plugin data retrieved from the API.
	 * It is reassembled from parts of the native WordPress plugin update code.
	 * See wp-includes/update.php line 121 for the original wp_update_plugins() function.
	 *
	 * @uses api_request()
	 *
	 * @param  array $transient Update array build by WordPress.
	 * @return array Modified update array with custom plugin data.
	 */
	public function check_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new \stdClass();
		}

		// if ( ! empty( $transient->response )
		// && ( ! empty( $transient->response[ $this->file ] ) || ! empty( $transient->no_response[ $this->file ] ) )
		// && false === $this->wp_override
		// ) {
		// return $transient;
		// }

		$response = $this->get_repo_cache( $this->slug );

		// TODO: use $this->wp_override.
		if ( ! $response || ! isset( $response['transient'] ) ) {
			$current = $this->get_repo_api_data();
		} else {
			$current = $response['transient'];
		}

		if ( version_compare( $this->version, $current->new_version, '<' ) ) {
			$transient->response[ $this->file ] = $current;
		} else {
			$transient->no_update[ $this->file ] = $current;
		}
		$transient->last_checked           = time();
		$transient->checked[ $this->file ] = $this->version;

		return $transient;
	}

	/**
	 * Get repo API data from store.
	 * Save to cache.
	 *
	 * @return \stdClass
	 */
	public function get_repo_api_data() {
		$version_info = $this->get_cached_version_info();

		if ( ! $version_info ) {
			$version_info = $this->api_request(
				[
					'slug' => $this->slug,
					'beta' => $this->beta,
				]
			);
		}

		// Make sure the plugin property is set to the plugin's file/location.
		// See issue 1463 on Software Licensing's GitHub repo.
		$version_info->{'plugin'} = $this->file;

		// Add for auto update link, WP 5.5.
		$version_info->{'update-available'} = true;

		$version_info = $this->convert_sections_to_array( $version_info );
		$this->set_version_info_cache( $version_info );
		$this->set_repo_cache( 'transient', $version_info, $this->slug );

		return $version_info;
	}

	/**
	 * Updates information on the "View version x.x details" and "View details" page with custom data.
	 *
	 * @param  mixed  $data   Default false.
	 * @param  string $action The type of information being requested from the Plugin Installation API.
	 * @param  object $args   Plugin API arguments.
	 * @return object $data
	 */
	public function plugins_api( $data, $action = '', $args = null ) {
		if ( 'plugin_information' !== $action ) {
			return $data;
		}

		if ( ! isset( $args->slug ) || ( $this->slug !== $args->slug ) ) {
			return $data;
		}

		$response = $this->get_repo_cache( $this->slug );
		if ( ! $response || ! isset( $response['transient'] ) ) {
			$data = $this->get_repo_api_data();
		} else {
			$data = $response['transient'];
		}

		return $data;
	}

	/**
	 * Convert some objects to arrays when injecting data into the update API.
	 *
	 * Some data like sections, banners, and icons are expected to be an associative array,
	 * however due to the JSON decoding, they are objects. This method allows us to pass
	 * in the object and return an associative array.
	 *
	 * @since 3.6.5
	 *
	 * @param stdClass $data Data to be converted.
	 *
	 * @return array
	 */
	private function convert_object_to_array( $data ) {
		$new_data = [];
		foreach ( $data as $key => $value ) {
			$new_data[ $key ] = is_object( $value ) ? $this->convert_object_to_array( $value ) : $value;
		}

		return $new_data;
	}

	/**
	 * Disable SSL verification in order to prevent download update failures.
	 *
	 * @param  array  $args Array of HTTP args.
	 * @param  string $url  URL.
	 * @return object $array
	 */
	public function http_request_args( $args, $url ) {
		if ( false !== strpos( $url, 'https://' ) && strpos( $url, 'edd_action=package_download' ) ) {
			$args['sslverify'] = $this->verify_ssl();
		}

		return $args;
	}

	/**
	 * Calls the API and, if successful, returns the object delivered by the API.
	 *
	 * @param  array $_data Parameters for the API action.
	 * @return false|object
	 */
	private function api_request( $_data ) {
		global $edd_plugin_url_available;

		// Do a quick status check on this domain if we haven't already checked it.
		$store_hash = md5( $this->api_url );
		if ( ! is_array( $edd_plugin_url_available ) || ! isset( $edd_plugin_url_available[ $store_hash ] ) ) {
			$test_url_parts = parse_url( $this->api_url );

			$scheme = ! empty( $test_url_parts['scheme'] ) ? $test_url_parts['scheme'] : 'http';
			$host   = ! empty( $test_url_parts['host'] ) ? $test_url_parts['host'] : '';
			$port   = ! empty( $test_url_parts['port'] ) ? ':' . $test_url_parts['port'] : '';

			if ( empty( $host ) ) {
				$edd_plugin_url_available[ $store_hash ] = false;
			} else {
				$test_url                                = $scheme . '://' . $host . $port;
				$response                                = wp_remote_get(
					$test_url,
					[
						'timeout'   => $this->health_check_timeout,
						'sslverify' => $this->verify_ssl(),
					]
				);
				$edd_plugin_url_available[ $store_hash ] = is_wp_error( $response ) ? false : true;
			}
		}

		if ( false === $edd_plugin_url_available[ $store_hash ] ) {
			return;
		}

		$data = array_merge( $this->api_data, $_data );

		if ( $this->slug !== $data['slug'] ) {
			return;
		}

		/**
		 * Plugins are not able to update from the store.
		 * This would cause the store to go into maintence mode as the plugin
		 * tries to update, likely resulting in a non-responsive site.
		 *
		 * @link https://github.com/easydigitaldownloads/easy-digital-downloads/issues/7168
		 */
		if ( trailingslashit( home_url() ) === $this->api_url ) {
			return false; // Don't allow a plugin to ping itself.
		}

		$api_params = [
			'edd_action' => 'get_version',
			'license'    => ! empty( $data['license'] ) ? $data['license'] : '',
			'item_name'  => isset( $data['name'] ) ? $data['name'] : false,
			'item_id'    => isset( $data['item_id'] ) ? $data['item_id'] : false,
			'version'    => isset( $data['version'] ) ? $data['version'] : false,
			'slug'       => $data['slug'],
			'author'     => $data['author'],
			'url'        => home_url(),
			'beta'       => ! empty( $data['beta'] ),
		];

		$request = $this->get_api_response( $this->api_url, $api_params );

		if ( $request && isset( $request->sections ) ) {
			$request->sections = maybe_unserialize( $request->sections );
		} else {
			$request = false;
		}

		if ( $request && isset( $request->banners ) ) {
			$request->banners = maybe_unserialize( $request->banners );
		}

		if ( $request && isset( $request->icons ) ) {
			$request->icons = maybe_unserialize( $request->icons );
		}

		if ( ! empty( $request->sections ) ) {
			foreach ( $request->sections as $key => $section ) {
				$request->$key = (array) $section;
			}
		}

		return $request;
	}

	/**
	 * Get cached version info.
	 *
	 * @param string $cache_key Cache key.
	 *
	 * @return string $cache['value']
	 */
	public function get_cached_version_info( $cache_key = '' ) {
		if ( empty( $cache_key ) ) {
			$cache_key = $this->cache_key;
		}

		$cache = get_option( $cache_key );

		if ( empty( $cache['timeout'] ) || time() > $cache['timeout'] ) {
			return false; // Cache is expired.
		}

		if ( empty( $cache['value'] ) ) {
			return false; // Ensure there is some useful data.
		}

		// We need to turn the icons into an array, thanks to WP Core forcing these into an object at some point.
		$cache['value'] = json_decode( $cache['value'] );
		if ( ! empty( $cache['value']->icons ) ) {
			$cache['value']->icons = (array) $cache['value']->icons;
		}

		return $cache['value'];
	}

	/**
	 * Set version info cache.
	 *
	 * @param string $value     Cache value.
	 * @param string $cache_key Cache key.
	 *
	 * @return bool|void
	 */
	public function set_version_info_cache( $value = '', $cache_key = '' ) {
		if ( empty( $value ) ) {
			return false;
		}
		if ( empty( $cache_key ) ) {
			$cache_key = $this->cache_key;
		}

		$data = [
			'timeout' => strtotime( '+3 hours', time() ),
			'value'   => json_encode( $value ),
		];

		update_option( $cache_key, $data, 'no' );
	}

	/**
	 * Returns if the SSL of the store should be verified.
	 *
	 * @since  1.6.13
	 * @return bool
	 */
	private function verify_ssl() {
		return (bool) apply_filters( 'edd_sl_api_request_verify_ssl', true, $this );
	}
}
