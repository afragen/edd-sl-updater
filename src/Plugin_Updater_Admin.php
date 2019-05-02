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
 * Class Plugin_Updater_Admin
 */
class Plugin_Updater_Admin extends Settings {
	use API_Common;

	/**
	 * Variables.
	 *
	 * @var string|array
	 */
	protected $api_url     = null;
	protected $api_data    = [];
	protected $name        = null;
	protected $item_name   = null;
	protected $item_id     = null;
	protected $download_id = null;
	protected $file        = null;
	protected $slug        = null;
	protected $version     = null;
	protected $license     = null;
	protected $wp_override = false;
	protected $cache_key   = null;
	protected $strings     = null;
	protected $data        = null;

	/**
	 * Class constructor.
	 *
	 * @param array $config Configuration data.
	 */
	public function __construct( $config ) {
		global $edd_plugin_data;
		$config = wp_parse_args(
			$config,
			[
				'file'        => '',
				'api_url'     => 'http://easydigitaldownloads.com',
				'item_name'   => '',
				'item_id'     => '',
				'download_id' => '',
				'version'     => '',
				'license'     => '',
				'author'      => '',
				'renew_url'   => '',
				'beta'        => false,
			]
		);

		// Set config arguments.
		$this->api_url     = $config['api_url'];
		$this->name        = $config['item_name'];
		$this->item_name   = $config['item_name'];
		$this->item_id     = $config['item_id'];
		$this->download_id = $config['item_id'];
		$this->file        = plugin_basename( $config['file'] );
		$this->slug        = dirname( $this->file );
		$this->version     = $config['version'];
		$this->author      = $config['author'];
		$this->renew_url   = $config['renew_url'];
		$this->beta        = $config['beta'];
		$this->license     = trim( get_option( $this->slug . '_license_key' ) );
		$this->api_data    = $config;
		$this->version     = $config['version'];
		$this->wp_override = isset( $config['wp_override'] ) ? (bool) $config['wp_override'] : false;
		$this->beta        = ! empty( $this->api_data['beta'] ) ? true : false;
		$this->cache_key   = 'edd_sl_' . md5( serialize( $this->slug . $this->api_data['license'] . $this->beta ) );

		$edd_plugin_data[ $this->slug ] = $this->api_data;

		// Populate version fallback.
		if ( empty( $config['version'] ) ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin        = get_plugin_data( $config['file'] );
			$this->version = $plugin['Version'];
		}

		$config['slug']                   = $this->slug;
		$config['file']                   = $this->file;
		$this->strings                    = $this->get_strings();
		$this->data     = $config;

		/**
		 * Fires after the $config is setup.
		 *
		 * @since 1.0.0
		 *
		 * @param array $config Array of EDD SL plugin data.
		 */
		do_action( 'post_edd_sl_plugin_updater_setup', $config );
	}

	/**
	 * Load all our hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_action( 'init', [ $this, 'updater' ] );
		add_action( 'admin_init', [ $this, 'register_option' ] );
		add_action( 'admin_init', [ $this, 'license_action' ] );
		add_action( 'admin_notices', [ $this, 'show_error' ] );
		add_action( 'admin_init', [ $this, 'update_settings' ] );
		add_filter( 'edd_sl_updater_add_admin_page', [ $this, 'license_page' ] );
	}

	/**
	 * Creates the updater class.
	 *
	 * @return void
	 */
	public function updater() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// If there is no valid license key status, don't allow updates.
		if ( 'valid' !== get_option( $this->slug . '_license_key_status', false ) ) {
			return;
		}

		( new Plugin_Updater(
			[
				'api_url'     => $this->api_url,
				'api_data'    => $this->api_data,
				'name'        => $this->name,
				'file'        => $this->file,
				'item_id'     => $this->item_id,
				'slug'        => $this->slug,
				'version'     => $this->version,
				'license'     => $this->license,
				'author'      => $this->author,
				'wp_override' => $this->wp_override,
				'beta'        => $this->beta,
				'cache_key'   => $this->cache_key,
			],
			$this->strings
		) )->load_hooks();
	}

	/**
	 * Activates the license.
	 *
	 * @since 1.0.0
	 */
	public function activate_license() {
		// Listen for our activate button to be clicked.
		if ( isset( $_POST[ $this->slug . '_license_activate' ] ) ) {
			// run a quick security check.
			if ( ! check_admin_referer( $this->slug . '_nonce', $this->slug . '_nonce' ) ) {
				return; // Get out if we didn't click the Activate button.
			}

			// Data to send in our API request.
			$api_params = [
				'edd_action' => 'activate_license',
				'license'    => $this->license,
				'item_name'  => rawurlencode( $this->name ), // the name of our product in EDD.
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
		}

		if ( isset( $license_data, $license_data->license ) ) {
			update_option( $this->slug . '_license_key_status', $license_data->license );
			delete_transient( $this->slug . '_license_message' );
		}

		if ( ! empty( $message ) ) {
			$error_data['success']       = false;
			$error_data['error_code']    = esc_attr__( 'activate_plugin_license', 'edd-sl-updater' );
			$error_data['error_message'] = esc_html( $message );
		} else {
			$error_data = null;
		}
		$this->redirect( $error_data );
	}

	/**
	 * Deactivates the license.
	 *
	 * @since 1.0.0
	 */
	public function deactivate_license() {
		// Listen for our activate button to be clicked.
		if ( isset( $_POST[ $this->slug . '_license_deactivate' ] ) ) {
			// Run a quick security check.
			if ( ! check_admin_referer( $this->slug . '_nonce', $this->slug . '_nonce' ) ) {
				return; // get out if we didn't click the Activate button.
			}

			// data to send in our API request.
			$api_params = [
				'edd_action' => 'deactivate_license',
				'license'    => $this->license,
				'item_name'  => rawurlencode( $this->name ), // the name of our product in EDD.
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
			if ( ! empty( $message ) ) {
				$error_data['success']       = false;
				$error_data['error_code']    = esc_attr__( 'deactivate_plugin_license', 'edd-sl-updater' );
				$error_data['error_message'] = esc_html( $message );
			} else {
				$error_data['success'] = true;
			}

			if ( ! isset( $license_data->license ) || 'deactivated' === $license_data->license ) {
				delete_option( $this->slug . '_license_key_status' );
				delete_transient( $this->slug . '_license_message' );
			}
			$this->redirect( $error_data );
		}
	}

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
}
