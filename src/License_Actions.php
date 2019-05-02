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
						date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
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
			$error_data['success']    = false;
			$error_data['error_code'] =
			/* translators: %s: item type 'plugin' or 'theme' */
			sprintf( esc_attr__( 'activate_license_%s', 'edd-sl-updater' ), $this->data['type'] );
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
			$error_data['success']    = false;
			$error_data['error_code'] =
			/* translators: %s: item type 'plugin' or 'theme' */
			sprintf( esc_attr__( 'activate_license_%s', 'edd-sl-updater' ), $this->data['type'] );
			$error_data['error_message'] = esc_html( $message );
		} else {
			$error_data = null;
		}
		$this->redirect( $error_data );
	}
}
