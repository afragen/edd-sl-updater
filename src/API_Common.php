<?php
/**
 * EDD SL Updater
 *
 * @package edd-sl-updater
 * @author Easy Digital Downloads
 * @license MIT
 */

namespace EDD\Software_Licensing\Updater;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait API_Common {
	/**
	 * Get default strings.
	 *
	 * @return array $default_strings
	 */
	public function get_strings() {
		$default_strings = [
			'theme-license'             => __( 'Theme License', 'edd-sl-updater' ),
			'plugin-license'            => __( 'Plugin License', 'edd-sl-updater' ),
			'enter-key'                 => __( 'Enter your theme license key.', 'edd-sl-updater' ),
			'license-key'               => __( 'License Key', 'edd-sl-updater' ),
			'license-action'            => __( 'License Action', 'edd-sl-updater' ),
			'deactivate-license'        => __( 'Deactivate License', 'edd-sl-updater' ),
			'activate-license'          => __( 'Activate License', 'edd-sl-updater' ),
			'status-unknown'            => __( 'License status is unknown.', 'edd-sl-updater' ),
			'renew'                     => __( 'Renew?', 'edd-sl-updater' ),
			'unlimited'                 => __( 'unlimited', 'edd-sl-updater' ),
			'license-key-is-active'     => __( 'License key is active.', 'edd-sl-updater' ),
			'expires%s'                 => __( 'Expires %s.', 'edd-sl-updater' ),
			'expires-never'             => __( 'Lifetime License.', 'edd-sl-updater' ),
			'%1$s/%2$-sites'            => __( 'You have %1$s / %2$s sites activated.', 'edd-sl-updater' ),
			'license-key-expired-%s'    => __( 'License key expired %s.', 'edd-sl-updater' ),
			'license-key-expired'       => __( 'License key has expired.', 'edd-sl-updater' ),
			'license-keys-do-not-match' => __( 'License keys do not match.', 'edd-sl-updater' ),
			'license-is-inactive'       => __( 'License is inactive.', 'edd-sl-updater' ),
			'license-key-is-disabled'   => __( 'License key is disabled.', 'edd-sl-updater' ),
			'site-is-inactive'          => __( 'Site is inactive.', 'edd-sl-updater' ),
			'license-status-unknown'    => __( 'License status is unknown.', 'edd-sl-updater' ),
			'update-notice'             => __( "Updating this theme will lose any customizations you have made. 'Cancel' to stop, 'OK' to update.", 'edd-sl-updater' ),
			'update-available'          => __( '<strong>%1$s %2$s</strong> is available. <a href="%3$s" class="thickbox" title="%4s">Check out what\'s new</a> or <a href="%5$s"%6$s>update now</a>.', 'edd-sl-updater' ),
		];

		/**
		 * Filter the default theme strings.
		 *
		 * @since 1.0.0
		 *
		 * @param array $default_strings Array of default strings for theme updater.
		 */
		return apply_filters( 'edd_sl_strings', $default_strings );
	}

	/**
	 * Makes a call to the API.
	 *
	 * @since 1.0.0
	 *
	 * @param  array $api_params to be used for wp_remote_get.
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
			$error_data['error_code']    = __( 'WP_Error' );
			$error_data['error_message'] = $response->get_error_message();
			$this->redirect( $error_data );
		}

		if ( 200 !== $code ) {
			$error_data['success']       = false;
			$error_data['error_code']    = __( 'HTTP Error Code' );
			$error_data['error_message'] = $code;
			$this->redirect( $error_data );
		}

		$response          = json_decode( wp_remote_retrieve_body( $response ) );
		$response->success = true;

		return $response;
	}

	/**
	 * Checks if license is valid and gets expire date.
	 *
	 * @since 1.0.0
	 *
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

		// If response doesn't include license data, return
		if ( ! isset( $license_data->license ) ) {
			$message = $this->strings['license-status-unknown'];

			return $message;
		}

		// We need to update the license status at the same time the message isupdated
		if ( $license_data && isset( $license_data->license ) ) {
			update_option( $slug . '_license_key_status', $license_data->license );
		}

		// Get expire date
		$expires = false;
		if ( isset( $license_data->expires ) && 'lifetime' !== $license_data->expires ) {
			$expires    = date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) );
			$renew_link = '<a href="' . esc_url( $this->get_renewal_link() ) . '"target="_blank">' . $this->strings['renew'] . '</a>';
		} elseif ( isset( $license_data->expires ) && 'lifetime' === $license_data->expires ) {
			$expires = 'lifetime';
		}

		// Get site counts
		$site_count    = property_exists( $license_data, 'site_count' ) ? $license_data->site_count : null;
		$license_limit = property_exists( $license_data, 'license_limit' ) ? $license_data->license_limit : null;

		// If unlimited
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
	 *
	 * @return void
	 */
	public function redirect( $error_data ) {
		if ( $this instanceof Theme_Updater_Admin ) {
			$redirect_url = wp_nonce_url( admin_url( 'themes.php' ) );
			$location     = add_query_arg(
				[ 'page' => $this->theme_slug . '-license' ],
				$redirect_url
			);
		}
		if ( $this instanceof Plugin_Updater_Admin ) {
			$redirect_url = wp_nonce_url( admin_url( 'plugins.php' ) );
			$location     = add_query_arg(
				[ 'page' => $this->slug . '-license' ],
				$redirect_url
			);
		}

		// Save error message data to very short transient.
		if ( ! $error_data['success'] ) {
			if ( $this instanceof Plugin_Updater_Admin ) {
				set_transient( 'plugin_sl_activation', $error_data, 10 );
			}
			if ( $this instanceof Theme_Updater_Admin ) {
				set_transient( 'theme_sl_activation', $error_data, 10 );
			}
		}

		wp_safe_redirect( $location );
		exit();
	}

	/**
	 * Create admin notice for errors.
	 *
	 * @return void
	 */
	public function show_error() {
		$error_data = false;
		if ( $this instanceof Plugin_Updater_Admin ) {
			$error_data = get_transient( 'plugin_sl_activation' );
		}
		if ( $this instanceof Theme_Updater_Admin ) {
			$error_data = get_transient( 'theme_sl_activation' );
		}
		if ( ! $error_data ) {
			return;
		}
		echo '<div class="notice-error notice"><p>';
		printf(
			esc_html__( 'EDD SL - %1$s: %2$s' ),
			$error_data['error_code'],
			$error_data['error_message']
		);
		echo '</p></div>';
	}
}
