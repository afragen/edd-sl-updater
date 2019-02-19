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

class Plugin_Updater_Admin {
	use API_Common;

	private $api_url     = '';
	private $api_data    = [];
	private $name        = '';
	private $file        = '';
	private $slug        = '';
	private $version     = '';
	private $license     = '';
	private $wp_override = false;
	private $cache_key   = '';

	public function __construct( $config ) {
		global $edd_plugin_data;
		$config = wp_parse_args(
			$config,
			[
				'file'        => '',
				'api_url'     => 'http://easydigitaldownloads.com',
				'plugin_slug' => '',
				'item_name'   => '',
				'download_id' => '',
				'version'     => '',
				'license'     => '',
				'author'      => '',
				'renew_url'   => '',
				'beta'        => false,
			]
		);

		/**
		 * Fires after the $edd_plugin_data is setup.
		 *
		 * @since x.x.x
		 *
		 * @param array $edd_plugin_data Array of EDD SL plugin data.
		 */
		do_action( 'post_edd_sl_plugin_updater_setup', $edd_plugin_data );

		// Set config arguments
		$this->api_url     = $config['api_url'];
		$this->name        = $config['item_name'];
		$this->file        = plugin_basename( $config['file'] );
		$this->slug        = sanitize_key( $config['plugin_slug'] );
		$this->version     = $config['version'];
		$this->author      = $config['author'];
		$this->download_id = $config['download_id'];
		$this->renew_url   = $config['renew_url'];
		$this->beta        = $config['beta'];
		$this->license     = trim( get_option( $this->slug . '_license_key' ) );
		$this->api_data    = $config;
		// $this->slug        = basename( $config['file'], '.php' );
		$this->version     = $config['version'];
		$this->wp_override = isset( $config['wp_override'] ) ? (bool) $config['wp_override'] : false;
		$this->beta        = ! empty( $this->api_data['beta'] ) ? true : false;
		$this->cache_key   = 'edd_sl_' . md5( serialize( $this->slug . $this->api_data['license'] . $this->beta ) );

		$edd_plugin_data[ $this->slug ] = $this->api_data;

		// Populate version fallback
		if ( empty( $config['version'] ) ) {
			if ( ! function_exists( 'get_plugin_data' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$plugin        = get_plugin_data( $config['file'] );
			$this->version = $plugin['Version'];
		}
	}

	public function load_hooks() {
		add_action( 'init', [ $this, 'updater' ] );
		add_action( 'admin_menu', [ $this, 'license_menu' ] );
		add_action( 'admin_init', [ $this, 'register_option' ] );
		add_action( 'admin_init', [ $this, 'license_action' ] );
		add_action( 'update_option_' . $this->slug . '_license_key', [ $this, 'activate_license' ] );
	}

	/************************************
	 the code below is just a standard
	 options page. Substitute with
	 your own.
	 *************************************/
	public function license_menu() {
		add_plugins_page(
			$this->name . ' License',
			$this->name . ' License',
			'manage_options',
			$this->slug . '-license',
			[ $this, 'license_page' ]
		);
	}

	public function register_option() {
		register_setting(
			$this->slug . '_license',
			$this->slug . '_license_key',
			'sanitize_license'
		);
	}

	public function sanitize_license( $new ) {
		$old = $this->license;
		if ( $old && $old !== $new ) {
			delete_option( $this->slug . '_license_status' ); // new license has been entered, so must reactivate
		}
		return $new;
	}

	public function updater() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/* If there is no valid license key status, don't allow updates. */
		if ( 'valid' !== get_option( $this->slug . '_license_key_status', false ) ) {
			// return;
		}

		( new Plugin_Updater(
			[
				'api_url'     => $this->api_url,
				'api_data'    => $this->api_data,
				'name'        => $this->name,
				'file'        => $this->file,
				'slug'        => $this->slug,
				'version'     => $this->version,
				'license'     => $this->license,
				'author'      => $this->author,
				'wp_override' => $this->wp_override,
				'beta'        => $this->beta,
			]
		) )->load_hooks();

	}

	public function license_page() {
		$license = $this->license;
		$status  = get_option( $this->slug . '_license_status' );
		?>
		<div class="wrap">
		<h2><?php _e( 'Plugin License Options' ); ?></h2>
		<form method="post" action="options.php">
			<?php settings_fields( $this->slug . '_license' ); ?>
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row" valign="top">
							<?php _e( 'License Key' ); ?>
						</th>
						<td>
							<input id="<?php echo $this->slug; ?>_license_key" name="<?php echo $this->slug; ?>_license_key" type="text" class="regular-text" value="<?php esc_attr_e( $license ); ?>" />
							<label class="description" for="<?php echo $this->slug; ?>_license_key"><?php _e( 'Enter your license key' ); ?></label>
						</td>
					</tr>
					<?php if ( $license ) { ?>
						<tr valign="top">
							<th scope="row" valign="top">
								<?php _e( 'Activate License' ); ?>
							</th>
							<td>
								<?php
								wp_nonce_field( $this->slug . '_nonce', $this->slug . '_nonce' );
								if ( 'valid' === $status ) {
									?>
									<span style="color:green;"><?php _e( 'active' ); ?></span>

									<input type="submit" class="button-secondary" name="<?php echo $this->slug; ?>_license_deactivate" value="<?php _e( 'Deactivate License' ); ?>"/>
									<?php
								} else {
									?>
									<input type="submit" class="button-secondary" name="<?php echo $this->slug; ?>_license_activate" value="<?php _e( 'Activate License' ); ?>"/>
									<?php
								}
								?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
				<?php submit_button(); ?>
		</form>
		<?php
	}

	/************************************
	 this illustrates how to activate
	 a license key
	 *************************************/
	public function activate_license() {
		// listen for our activate button to be clicked
		if ( isset( $_POST[ $this->slug . '_license_activate' ] ) ) {
			// run a quick security check
			if ( ! check_admin_referer( $this->slug . '_nonce', $this->slug . '_nonce' ) ) {
				return; // get out if we didn't click the Activate button
			}

			// data to send in our API request
			$api_params = [
				'edd_action' => 'activate_license',
				'license'    => $this->license,
				'item_name'  => rawurlencode( $this->name ), // the name of our product in EDD
				'url'        => home_url(),
			];

			add_filter( 'edd_sl_api_request_verify_ssl', '__return_false' );
			$license_data = $this->get_api_response( $this->api_url, $api_params );
			// Call the custom API.
			// $response = wp_remote_post(
			// EDD_SAMPLE_STORE_URL,
			// array(
			// 'timeout'   => 15,
			// 'sslverify' => false,
			// 'body'      => $api_params,
			// )
			// );
			// make sure the response came back okay
			// if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// if ( is_wp_error( $response ) ) {
			// $message = $response->get_error_message();
			// } else {
			// $message = __( 'An error occurred, please try again.' );
			// }
			// } else {
			// $license_data = json_decode( wp_remote_retrieve_body( $response ) );
			if ( $license_data->success ) {
				switch ( $license_data->error ) {
					case 'expired':
						$message = sprintf(
							__( 'Your license key expired on %s.' ),
							date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
						);
						break;

					case 'disabled':
					case 'revoked':
						$message = __( 'Your license key has been disabled.' );
						break;

					case 'missing':
						$message = __( 'Invalid license.' );
						break;

					case 'invalid':
					case 'site_inactive':
						$message = __( 'Your license is not active for this URL.' );
						break;

					case 'item_name_mismatch':
						$message = sprintf( __( 'This appears to be an invalid license key for %s.' ), $this->item_name );
						break;

					case 'no_activations_left':
						$message = __( 'Your license key has reached its activation limit.' );
						break;

					default:
						$message = __( 'An error occurred, please try again.' );
						break;
				}
			}
			update_option( $this->slug . '_license_status', $license_data->license );
		}

		if ( ! empty( $message ) ) {
			$error_data['success']       = false;
			$error_data['error_code']    = __( 'activate_plugin_license' );
			$error_data['error_message'] = $message;
		} else {
			$error_data = null;
		}

		// Check if anything passed on a message constituting a failure
		// if ( ! empty( $message ) ) {
		// $base_url = admin_url( 'plugins.php?page=' . $this->slug . '-license' );
		// $redirect = add_query_arg(
		// array(
		// 'sl_activation' => 'false',
		// 'message'       => rawurlencode( $message ),
		// ),
		// $base_url
		// );
		//
		// wp_redirect( $redirect );
		// exit();
		// }
		// $license_data->license will be either "valid" or "invalid"
		// wp_redirect( admin_url( 'plugins.php?page=' . $this->slug . '-license' ) );
		// exit();
		$this->redirect( $error_data );
	}



	/***********************************************
	 Illustrates how to deactivate a license key.
	 This will decrease the site count
	 ***********************************************/
	public function deactivate_license() {

		// listen for our activate button to be clicked
		if ( isset( $_POST['edd_license_deactivate'] ) ) {

			// run a quick security check
			if ( ! check_admin_referer( $this->slug . '_nonce', $this->slug . '_nonce' ) ) {
				return; // get out if we didn't click the Activate button
			}

			// data to send in our API request
			$api_params = array(
				'edd_action' => 'deactivate_license',
				'license'    => $this->license,
				'item_name'  => rawurlencode( $this->name ), // the name of our product in EDD
				'url'        => home_url(),
			);

			// Call the custom API.
			add_filter( 'edd_sl_api_request_verify_ssl', '__return_false' );
			$license_data = $this->get_api_response( $this->api_url, $api_params );
			// $response = wp_remote_post(
			// $this->api_url,
			// array(
			// 'timeout'   => 15,
			// 'sslverify' => false,
			// 'body'      => $api_params,
			// )
			// );
			// make sure the response came back okay
			// if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			//
			// if ( is_wp_error( $response ) ) {
			// $message = $response->get_error_message();
			// } else {
			// $message = __( 'An error occurred, please try again.' );
			// }
			//
			// $base_url = admin_url( 'plugins.php?page=' . $this->slug . '-license' );
			// $redirect = add_query_arg(
			// array(
			// 'sl_activation' => 'false',
			// 'message'       => urlencode( $message ),
			// ),
			// $base_url
			// );
			//
			// wp_redirect( $redirect );
			// exit();
			// }
			// decode the license data
			// $license_data = json_decode( wp_remote_retrieve_body( $response ) );
			// $license_data->license will be either "deactivated" or "failed"
			if ( 'deactivated' === $license_data->license ) {
				delete_option( $this->slug . '_license_status' );
			}
			$this->redirect();
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

	/************************************
	 this illustrates how to check if
	 a license key is still valid
	 the updater does this for you,
	 so this is only needed if you
	 want to do something custom
	 *************************************/

	public function check_license() {
		global $wp_version;

		$api_params = array(
			'edd_action' => 'check_license',
			'license'    => $this->license,
			'item_name'  => rawurlencode( $this->name ),
			'url'        => home_url(),
		);

		// Call the custom API.
		// $response = wp_remote_post(
		// EDD_SAMPLE_STORE_URL,
		// array(
		// 'timeout'   => 15,
		// 'sslverify' => false,
		// 'body'      => $api_params,
		// )
		// );
		//
		// if ( is_wp_error( $response ) ) {
		// return false;
		// }
		// $license_data = json_decode( wp_remote_retrieve_body( $response ) );
		add_filter( 'edd_sl_api_request_verify_ssl', '__return_false' );
		$license_data = $this->get_api_response( $this->api_url, $api_params );

		if ( 'valid' === $license_data->license ) {
			echo 'valid';
			exit; // this license is still valid
		} else {
			echo 'invalid';
			exit; // this license is no longer valid
		}
	}

}
