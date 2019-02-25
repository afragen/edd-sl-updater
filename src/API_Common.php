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
