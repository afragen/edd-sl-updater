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
 * Trait API_Common
 */
trait API_Common {
	/**
	 * Makes a call to the API.
	 *
	 * @param string $url URL for the API call.
	 * @param  array  $api_params to be used for wp_remote_get.
	 * @return array $response decoded JSON response.
	 */
	public function get_api_response( $url, $api_params ) {
		// Call the custom API.
		$verify_ssl = (bool) apply_filters( 'edd_sl_api_request_verify_ssl', true );
		$response   = wp_remote_post(
			$url,
			[
				'timeout'   => 15,
				'sslverify' => $verify_ssl,
				'body'      => $api_params,
			]
		);

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) ) {
			$error_data['slug']          = $this->slug;
			$error_data['success']       = false;
			$error_data['error_code']    = __( 'WP_Error', 'edd-sl-updater' );
			$error_data['error_message'] = $response->get_error_message();
			$this->redirect( $error_data );
		}

		if ( 200 !== $code ) {
			$error_data['slug']          = $this->slug;
			$error_data['success']       = false;
			$error_data['error_code']    = __( 'HTTP Error Code', 'edd-sl-updater' );
			$error_data['error_message'] = $code;
			$this->redirect( $error_data );
		}

		$response = json_decode( wp_remote_retrieve_body( $response ) );

		if ( empty( $response ) ) {
			$response          = new \stdClass();
			$response->success = false;
		}

		$response->success = isset( $response->success ) ? $response->success : true;

		return $response;
	}

	/**
	 * Checks if license is valid and gets expire date.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug    Plugin/theme slug.
	 * @return string $message License status message.
	 */
	public function check_license( $slug ) {
		$license = trim( get_site_option( $slug . '_license_key' ) );

		$api_params = [
			'edd_action' => 'check_license',
			'license'    => $license,
			'item_name'  => rawurlencode( $this->item_name ),
			'item_id'    => $this->item_id,
			'url'        => home_url(),
		];

		$license_data = $this->get_api_response( $this->api_url, $api_params );

		// If response doesn't include license data, return.
		if ( ! isset( $license_data->license ) ) {
			$message = $this->strings['license-status-unknown'];

			return $message;
		}

		// We need to update the license status at the same time the message isupdated.
		if ( $license_data && isset( $license_data->license ) ) {
			update_site_option( $slug . '_license_key_status', $license_data->license );
		}

		// Get expire date.
		$expires = false;
		if ( isset( $license_data->expires ) && 'lifetime' !== $license_data->expires ) {
			$expires    = date_i18n( get_site_option( 'date_format' ), strtotime( $license_data->expires, time() ) );
			$renew_link = '<a href="' . esc_url( $this->get_renewal_link() ) . '"target="_blank">' . esc_attr( $this->strings['renew'] ) . '</a>';
		} elseif ( isset( $license_data->expires ) && 'lifetime' === $license_data->expires ) {
			$expires = 'lifetime';
		}

		// Get site counts.
		$site_count    = property_exists( $license_data, 'site_count' ) ? $license_data->site_count : null;
		$license_limit = property_exists( $license_data, 'license_limit' ) ? $license_data->license_limit : null;

		// If unlimited.
		if ( 0 === $license_limit ) {
			$license_limit = $this->strings['unlimited'];
		}

		switch ( $license_data->license ) {
			case 'valid':
				$message = $this->strings['license-key-is-active'] . ' ';
				if ( isset( $expires ) ) {
					$message = 'lifetime' === $expires ? $message .= $this->strings['expires-never'] : $message .= sprintf( $this->strings['expires%s'], $expires ) . ' ';
				}
				if ( $site_count && $license_limit ) {
					$message .= sprintf( $this->strings['%1$s/%2$-sites'], $site_count, $license_limit );
				}
				break;
			case 'expired':
				$message  = $expires ? sprintf( $this->strings['license-key-expired-%s'], $expires ) : $this->strings['license-key-expired'];
				$message .= $renew_link ? ' ' . $renew_link : null;
				break;
			case 'invalid':
				$message = $this->strings['license-keys-do-not-match'];
				break;
			case 'inactive':
				$message = $this->strings['license-is-inactive'];
				break;
			case 'disabled':
				$message = $this->strings['license-key-is-disabled'];
				break;
			case 'site_inactive':
				$message = $this->strings['site-is-inactive'];
				break;
			default:
				$message = $this->strings['license-status-unknown'];
		}

		return sanitize_text_field( $message );
	}

	/**
	 * Redirect to where we came from.
	 *
	 * @param array $error_data Data for error notice.
	 *                          Default is null.
	 *
	 * @return void
	 */
	public function redirect( $error_data = null ) {
		$redirect_url = wp_nonce_url( admin_url( 'options-general.php' ) );
		$location     = add_query_arg(
			[ 'page' => 'edd-sl-updater' ],
			$redirect_url
		);

		// Save error message data to very short transient.
		if ( ! $error_data['success'] ) {
			if ( $this instanceof Plugin_Updater_Admin ) {
				set_transient( 'sl_plugin_activation', $error_data, 10 );
			}
			if ( $this instanceof Theme_Updater_Admin ) {
				set_transient( 'sl_theme_activation', $error_data, 10 );
			}
		}

		wp_safe_redirect( $location );
		exit();
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
	 * Returns if the SSL of the store should be verified.
	 *
	 * @since  1.6.13
	 * @return bool
	 */
	protected function verify_ssl() {
		return (bool) apply_filters( 'edd_sl_api_request_verify_ssl', true, $this );
	}

	/**
	 * Calls the API and, if successful, returns the object delivered by the API.
	 *
	 * @param  array $data Parameters for the API action.
	 * @return false|object
	 */
	protected function api_request( $data ) {
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

		$data = array_merge( $this->api_data, $data );

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
}
