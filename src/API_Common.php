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
			$error_data['success']       = false;
			$error_data['error_code']    = __( 'WP_Error', 'edd-sl-updater' );
			$error_data['error_message'] = $response->get_error_message();
			$this->redirect( $error_data );
		}

		if ( 200 !== $code ) {
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
		$license = trim( get_option( $slug . '_license_key' ) );

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
			update_option( $slug . '_license_key_status', $license_data->license );
		}

		// Get expire date.
		$expires = false;
		if ( isset( $license_data->expires ) && 'lifetime' !== $license_data->expires ) {
			$expires    = date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) );
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
}
