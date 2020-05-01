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
	}

	/**
	 * Set up WordPress filters to hook into WP's update process.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_filter( 'site_transient_update_plugins', [ $this, 'check_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugins_api_filter' ], 10, 3 );
		remove_action( 'after_plugin_row_' . $this->file, 'wp_plugin_update_row', 10 );
		add_action( 'after_plugin_row_' . $this->file, [ $this, 'show_update_notification' ], 10, 2 );
		add_action( 'admin_init', [ $this, 'show_changelog' ] );
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
		global $pagenow;

		if ( ! is_object( $transient ) ) {
			$transient = new \stdClass();
		}

		if ( 'plugins.php' === $pagenow && is_multisite() ) {
			return $transient;
		}

		if ( ! empty( $transient->response ) && ! empty( $transient->response[ $this->file ] ) && false === $this->wp_override ) {
			return $transient;
		}

		$version_info = $this->get_cached_version_info();

		if ( false === $version_info ) {
			$version_info = $this->api_request(
				[
					'slug' => $this->slug,
					'beta' => $this->beta,
				]
			);

			$this->set_version_info_cache( $version_info );
		}

		if ( false !== $version_info && is_object( $version_info ) && isset( $version_info->new_version ) ) {
			if ( version_compare( $this->version, $version_info->new_version, '<' ) ) {
				$transient->response[ $this->file ] = $version_info;

				// Make sure the plugin property is set to the plugin's file/location.
				// See issue 1463 on Software Licensing's GitHub repo.
				$transient->response[ $this->file ]->plugin = $this->file;
			}

			$transient->last_checked           = time();
			$transient->checked[ $this->file ] = $this->version;
		}

		return $transient;
	}

	/**
	 * Show update notification row.
	 *
	 * Needed for multisite subsites, because WP won't tell you otherwise!
	 *
	 * @param string $file   Plugin file.
	 * @param array  $plugin Plugin data.
	 */
	public function show_update_notification( $file, $plugin ) {
		if ( is_network_admin() ) {
			return;
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		if ( ! is_multisite() ) {
			return;
		}

		if ( $this->file !== $file ) {
			return;
		}

		// Remove our filter on the site transient.
		remove_filter( 'site_transient_update_plugins', [ $this, 'check_update' ], 10 );

		$update_cache = get_site_transient( 'update_plugins' );
		$update_cache = is_object( $update_cache ) ? $update_cache : new stdClass();

		if ( empty( $update_cache->response ) || empty( $update_cache->response[ $this->file ] ) ) {
			$version_info = $this->get_cached_version_info();

			if ( false === $version_info ) {
				$version_info = $this->api_request(
					[
						'slug' => $this->slug,
						'beta' => $this->beta,
					]
				);

				// Since we disabled our filter for the transient, we aren't running our object conversion on banners, sections, or icons. Do this now.
				if ( isset( $version_info->banners ) && ! is_array( $version_info->banners ) ) {
					$version_info->banners = $this->convert_object_to_array( $version_info->banners );
				}
				if ( isset( $version_info->contributors ) && ! is_array( $version_info->contrbutors ) ) {
					$version_info->contributors = $this->convert_object_to_array( $version_info->contributors );
				}
				if ( isset( $version_info->sections ) && ! is_array( $version_info->sections ) ) {
					$version_info->sections = $this->convert_object_to_array( $version_info->sections );
				}
				if ( isset( $version_info->icons ) && ! is_array( $version_info->icons ) ) {
					$version_info->icons = $this->convert_object_to_array( $version_info->icons );
				}

				$this->set_version_info_cache( $version_info );
			}

			if ( ! is_object( $version_info ) ) {
				return;
			}

			if ( version_compare( $this->version, $version_info->new_version, '<' ) ) {
				$update_cache->response[ $this->file ] = $version_info;
			}

			$update_cache->last_checked           = time();
			$update_cache->checked[ $this->file ] = $this->version;

			set_site_transient( 'update_plugins', $update_cache );
		} else {
			$version_info = $update_cache->response[ $this->file ];
		}

		// Restore our filter.
		add_filter( 'site_transient_update_plugins', [ $this, 'check_update' ] );

		if ( ! empty( $update_cache->response[ $this->file ] ) && version_compare( $this->version, $version_info->new_version, '<' ) ) {
			// build a plugin list row, with update notification.
			$wp_list_table = _get_list_table( 'WP_Plugins_List_Table' );
			// <tr class="plugin-update-tr"><td colspan="' . $wp_list_table->get_column_count() . '" class="plugin-update colspanchange">
			echo wp_kses_post( '<tr class="plugin-update-tr" id="' . $this->slug . '-update" data-slug="' . $this->slug . '" data-plugin="' . $this->slug . '/' . $file . '">' );
			echo '<td colspan="3" class="plugin-update colspanchange">';
			echo '<div class="update-message notice inline notice-warning notice-alt">';

			$changelog_link = self_admin_url( 'index.php?edd_sl_action=view_plugin_changelog&plugin=' . $this->file . '&slug=' . $this->slug . '&TB_iframe=true&width=772&height=911' );

			if ( empty( $version_info->download_link ) ) {
				printf(
					/* translators: %1: item name, %2: open tag, %3: version number, %4: close tag */
					esc_html__( 'There is a new version of %1$s available. %2$sView version %3$s details%4$s.', 'edd-sl-updater' ),
					esc_html( $version_info->name ),
					'<a target="_blank" class="thickbox" href="' . esc_url( $changelog_link ) . '">',
					esc_html( $version_info->new_version ),
					'</a>'
				);
			} else {
				printf(
					/* translators: %1: item name, %2: open tag, %3: version number, %4: close tag, %5: open tag, %6: close tag */
					esc_html__( 'There is a new version of %1$s available. %2$sView version %3$s details%4$s or %5$supdate now%6$s.', 'edd-sl-updater' ),
					esc_html( $version_info->name ),
					'<a target="_blank" class="thickbox" href="' . esc_url( $changelog_link ) . '">',
					esc_html( $version_info->new_version ),
					'</a>',
					'<a href="' . esc_url( wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $this->file, 'upgrade-plugin_' . $this->file ) ) . '">',
					'</a>'
				);
			}

			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			do_action( "in_plugin_update_message-{$file}", $plugin, $version_info );

			echo '</div></td></tr>';
		}
	}

	/**
	 * Updates information on the "View version x.x details" page with custom data.
	 *
	 * @uses api_request()
	 *
	 * @param  mixed  $_data   Default false.
	 * @param  string $_action The type of information being requested from the Plugin Installation API.
	 * @param  object $_args   Plugin API arguments.
	 * @return object $_data
	 */
	public function plugins_api_filter( $_data, $_action = '', $_args = null ) {
		if ( 'plugin_information' !== $_action ) {
			return $_data;
		}

		if ( ! isset( $_args->slug ) || ( $this->slug !== $_args->slug ) ) {
			return $_data;
		}

		$to_send = [
			'slug'   => $this->slug,
			'is_ssl' => is_ssl(),
			'fields' => [
				'banners' => [],
				'reviews' => false,
				'icons'   => [],
			],
		];

		// Get the transient where we store the api request for this plugin for 24 hours.
		$edd_api_request_transient = $this->get_cached_version_info();

		// If we have no transient-saved value, run the API, set a fresh transient with the API value, and return that value too right now.
		if ( empty( $edd_api_request_transient ) ) {
			$api_response = $this->api_request( $to_send );

			// Expires in 3 hours.
			$this->set_version_info_cache( $api_response );

			if ( false !== $api_response ) {
				$_data = $api_response;
			}
		} else {
			$_data = $edd_api_request_transient;
		}

		// Convert sections into an associative array, since we're getting an object, but Core expects an array.
		if ( isset( $_data->sections ) && ! is_array( $_data->sections ) ) {
			$_data->sections = $this->convert_object_to_array( $_data->sections );
		}
		if ( isset( $_data->contributors ) && ! is_array( $_data->contributors ) ) {
			$_data->contributors = $this->convert_object_to_array( $_data->contributors );
		}
		if ( isset( $_data->banners ) && ! is_array( $_data->banners ) ) {
			$_data->banners = $this->convert_object_to_array( $_data->banners );
		}
		if ( isset( $_data->icons ) && ! is_array( $_data->icons ) ) {
			$_data->icons = $this->convert_object_to_array( $_data->icons );
		}

		if ( ! isset( $_data->plugin ) ) {
			$_data->plugin = $this->file;
		}

		return $_data;
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
		$verify_ssl = $this->verify_ssl();
		if ( false !== strpos( $url, 'https://' ) && strpos( $url, 'edd_action=package_download' ) ) {
			$args['sslverify'] = $verify_ssl;
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
		global $wp_version, $edd_plugin_url_available;

		$verify_ssl = $this->verify_ssl();

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
						'sslverify' => $verify_ssl,
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
	 * Show the changelog.
	 *
	 * @return void
	 */
	public function show_changelog() {
		global $edd_plugin_data;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( empty( $_REQUEST['edd_sl_action'] ) || 'view_plugin_changelog' !== $_REQUEST['edd_sl_action'] ) {
			return;
		}

		if ( empty( $_REQUEST['plugin'] ) ) {
			return;
		}

		if ( empty( $_REQUEST['slug'] ) ) {
			return;
		}
		// phpcs:enable

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to install plugin updates.', 'edd-sl-updater' ), esc_html__( 'Error', 'edd-sl-updater' ), [ 'response' => 403 ] );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data         = $edd_plugin_data[ sanitize_file_name( wp_unslash( $_REQUEST['slug'] ) ) ];
		$beta         = ! empty( $data['beta'] ) ? true : false;
		$version_info = $this->get_cached_version_info();

		if ( false === $version_info ) {
			$api_params = [
				'edd_action' => 'get_version',
				'item_name'  => isset( $data['item_name'] ) ? $data['item_name'] : false,
				'item_id'    => isset( $data['item_id'] ) ? $data['item_id'] : false,
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'slug'       => sanitize_file_name( wp_unslash( $_REQUEST['slug'] ) ),
				'author'     => $data['author'],
				'url'        => home_url(),
				'beta'       => ! empty( $data['beta'] ),
			];

			$version_info = $this->get_api_response( $this->api_url, $api_params );

			if ( ! empty( $version_info ) && isset( $version_info->sections ) ) {
				$version_info->sections = maybe_unserialize( $version_info->sections );
			} else {
				$version_info = false;
			}

			if ( ! empty( $version_info ) ) {
				foreach ( $version_info->sections as $key => $section ) {
					$version_info->$key = (array) $section;
				}
			}

			$this->set_version_info_cache( $version_info );
		}

		if ( ! empty( $version_info ) && isset( $version_info->sections['changelog'] ) ) {
			echo '<div style="background:#fff;padding:10px;">' . esc_attr( $version_info->sections['changelog'] ) . '</div>';
		}

		exit;
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
