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
 * Trait License_Actions
 */
trait License_Actions {
	use API_Common;

	/**
	 * Checks if a license action was submitted.
	 *
	 * @since 1.0.0
	 */
	public function license_action() {
		if ( isset( $_POST[ $this->slug . '_license_activate' ] ) ) {
			if ( check_admin_referer( $this->slug . '_nonce', $this->slug . '_nonce' ) ) {
				$this->activate_license();
			}
		}

		if ( isset( $_POST[ $this->slug . '_license_deactivate' ] ) ) {
			if ( check_admin_referer( $this->slug . '_nonce', $this->slug . '_nonce' ) ) {
				$this->deactivate_license();
			}
		}
	}

	/**
	 * Activate the license.
	 *
	 * Listen for our activate button to be clicked.
	 * Exit early if we didn't click the Activate button.
	 *
	 * @since 1.0.0
	 */
	public function activate_license() {
		if ( ! isset( $_POST[ $this->slug . '_license_activate' ] ) ) {
			return;
		}
		// run a quick security check.
		if ( ! check_admin_referer( $this->slug . '_nonce', $this->slug . '_nonce' ) ) {
			return;
		}

		// Data to send in our API request.
		$api_params = [
			'edd_action' => 'activate_license',
			'license'    => $this->license,
			'item_name'  => rawurlencode( $this->item_name ), // the name of our product in EDD.
			'item_id'    => $this->item_id,
			'url'        => home_url(),
		];

		add_filter( 'edd_sl_api_request_verify_ssl', '__return_false' );
		$license_data = $this->get_api_response( $this->api_url, $api_params );

		if ( ! $license_data->success && isset( $license_data->error ) ) {
			switch ( $license_data->error ) {
				case 'expired':
					$message = sprintf(
						$this->strings['license-key-expired-%s'],
						date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, time() ) )
					);
					break;
				case 'disabled':
				case 'revoked':
					$message = $this->strings['license-key-is-disabled'];
					break;
				case 'missing':
					$message = $this->strings['status-invalid'];
					break;
				case 'invalid':
				case 'site_inactive':
					$this->strings['license-inactive-url'];
					break;
				case 'item_name_mismatch':
					$message = sprintf( $this->strings['item-name-mismatch-%s'], $this->item_name );
					break;
				case 'no_activations_left':
					$message = $this->strings['license-activation-limit'];
					break;
				default:
					$message = $this->strings['error'];
					break;
			}
		}
		if ( isset( $license_data, $license_data->license ) ) {
			update_option( $this->slug . '_license_key_status', $license_data->license );
			delete_transient( $this->slug . '_license_message' );
		}
		if ( ! empty( $message ) ) {
			$error_data['slug']       = $this->slug;
			$error_data['success']    = false;
			$error_data['error_code'] =
			/* translators: %s: item name */
			sprintf( esc_attr__( 'activate_license-%s', 'edd-sl-updater' ), $this->data['item_name'] );
			$error_data['error_message'] = esc_html( $message );
		} else {
			$error_data = null;
		}
		$this->redirect( $error_data );
	}

	/**
	 * Deactivate the license.
	 *
	 * Listen for our deactivate button to be clicked.
	 * Exit early if we didn't click the deactivate button.
	 *
	 * @since 1.0.0
	 */
	public function deactivate_license() {
		if ( ! isset( $_POST[ $this->slug . '_license_deactivate' ] ) ) {
			return;
		}
		// Run a quick security check.
		if ( ! check_admin_referer( $this->slug . '_nonce', $this->slug . '_nonce' ) ) {
			return;
		}

		// data to send in our API request.
		$api_params = [
			'edd_action' => 'deactivate_license',
			'license'    => $this->license,
			'item_name'  => rawurlencode( $this->item_name ), // the name of our product in EDD.
			'item_id'    => $this->item_id,
			'url'        => home_url(),
		];

		// Call the custom API.
		add_filter( 'edd_sl_api_request_verify_ssl', '__return_false' );
		$license_data = $this->get_api_response( $this->api_url, $api_params );

		// $license_data->license will be either "deactivated" or "failed".
		if ( $license_data->success && property_exists( $license_data, 'error' ) ) {
			$message = $this->strings['error'];
		}
		if ( ! isset( $license_data->license ) || 'deactivated' === $license_data->license ) {
			delete_option( $this->slug . '_license_key_status' );
			delete_transient( $this->slug . '_license_message' );
		}
		if ( ! empty( $message ) ) {
			$error_data['slug']       = $this->slug;
			$error_data['success']    = false;
			$error_data['error_code'] =
			/* translators: %s: item name */
			sprintf( esc_attr__( 'activate_license-%s', 'edd-sl-updater' ), $this->data['item_name'] );
			$error_data['error_message'] = esc_html( $message );
		} else {
			$error_data = null;
		}
		$this->redirect( $error_data );
	}

	/**
	 * Get default strings.
	 *
	 * @return array $default_strings
	 */
	public function get_strings() {
		$default_strings = [
			'theme-license'             => __( 'Theme License', 'edd-sl-updater' ),
			'plugin-license'            => __( 'Plugin License', 'edd-sl-updater' ),
			'enter-key'                 => __( 'Enter your license key.', 'edd-sl-updater' ),
			'license-key'               => __( 'License Key', 'edd-sl-updater' ),
			'license-action'            => __( 'License Action', 'edd-sl-updater' ),
			'deactivate-license'        => __( 'Deactivate License', 'edd-sl-updater' ),
			'activate-license'          => __( 'Activate License', 'edd-sl-updater' ),
			'status-unknown'            => __( 'License status is unknown.', 'edd-sl-updater' ),
			'status-invalid'            => __( 'Invalid License.', 'edd-sl-updater' ),
			/* translators: %s: item name */
			'item-name-mismatch-%s'     => __( 'This appears to be an invalid license key for %s.', 'edd-sl-updater' ),
			'renew'                     => __( 'Renew?', 'edd-sl-updater' ),
			'unlimited'                 => __( 'unlimited', 'edd-sl-updater' ),
			'license-key-is-active'     => __( 'License key is active.', 'edd-sl-updater' ),
			/* translators: %s: expiration date */
			'expires%s'                 => __( 'Expires %s.', 'edd-sl-updater' ),
			'expires-never'             => __( 'Lifetime License.', 'edd-sl-updater' ),
			/* translators: %1: number of sites activated, %2: total number of sites activated */
			'%1$s/%2$-sites'            => __( 'You have %1$s / %2$s sites activated.', 'edd-sl-updater' ),
			/* translators: %s: expiration date */
			'license-key-expired-%s'    => __( 'License key expired on %s.', 'edd-sl-updater' ),
			'license-key-expired'       => __( 'License key has expired.', 'edd-sl-updater' ),
			'license-keys-do-not-match' => __( 'License keys do not match.', 'edd-sl-updater' ),
			'license-is-inactive'       => __( 'License is inactive.', 'edd-sl-updater' ),
			'license-key-is-disabled'   => __( 'License key is disabled.', 'edd-sl-updater' ),
			'license-inactive-url'      => __( 'Your license is not active for this URL.', 'edd-sl-updater' ),
			'site-is-inactive'          => __( 'Site is inactive.', 'edd-sl-updater' ),
			'license-status-unknown'    => __( 'License status is unknown.', 'edd-sl-updater' ),
			'license-activation-limit'  => __( 'Your license key has reached its activation limit.', 'edd-sl-updater' ),
			'update-notice'             => __( "Updating this theme will lose any customizations you have made. 'Cancel' to stop, 'OK' to update.", 'edd-sl-updater' ),
			/* translators: %1: Name, %2: new version, %3: URL, %4: link title, %5: URL, %6: opening tag */
			'update-available'          => __( '<strong>%1$s %2$s</strong> is available. <a href="%3$s" class="thickbox" title="%4$s">Check out what\'s new</a> or <a href="%5$s"%6$s>update now</a>.', 'edd-sl-updater' ),
			'error'                     => __( 'An error occurred, please try again.', 'edd-sl-updater' ),
		];

		/**
		 * Filter the default strings.
		 *
		 * @since 1.0.0
		 *
		 * @param array $default_strings Array of default strings for theme updater.
		 */
		return apply_filters( 'edd_sl_updater_strings', $default_strings );
	}

	/**
	 * Create admin notice for errors.
	 *
	 * @return void
	 */
	public function show_error() {
		$error_data = false;
		if ( $this instanceof Plugin_Updater_Admin ) {
			$error_data = get_transient( 'sl_plugin_activation' );
		}
		if ( $this instanceof Theme_Updater_Admin ) {
			$error_data = get_transient( 'sl_theme_activation' );
		}
		if ( ! $error_data || $this->slug !== $error_data['slug'] ) {
			return;
		}
		echo '<div class="notice-error notice"><p>';
		printf(
			/* translators: %1: error code, %2: error message */
			esc_html__( 'EDD SL - %1$s: %2$s' ),
			esc_attr( $error_data['error_code'] ),
			esc_html( $error_data['error_message'] )
		);
		echo '</p></div>';
	}
}
