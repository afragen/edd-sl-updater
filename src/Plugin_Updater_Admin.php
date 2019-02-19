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
	private $slug        = '';
	private $version     = '';
	private $wp_override = false;
	private $cache_key   = '';
	private $option_key  = '';

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
				'option_key'  => '',
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
		$this->slug        = sanitize_key( $config['plugin_slug'] );
		$this->version     = $config['version'];
		$this->author      = $config['author'];
		$this->download_id = $config['download_id'];
		$this->renew_url   = $config['renew_url'];
		$this->beta        = $config['beta'];
		$this->option_key  = $config['option_key'];

		// $this->api_url     = trailingslashit( $this->api_url );
		$this->api_data = $config;
		// $this->name        = plugin_basename( $config['file'] );
		// $this->slug        = basename( $config['file'], '.php' );
		$this->version     = $config['version'];
		$this->wp_override = isset( $config['wp_override'] ) ? (bool) $config['wp_override'] : false;
		$this->beta        = ! empty( $this->api_data['beta'] ) ? true : false;
		$this->cache_key   = 'edd_sl_' . md5( serialize( $this->slug . $this->api_data['license'] . $this->beta ) );

		$edd_plugin_data[ $this->slug ] = $this->api_data;

		// Populate version fallback
		if ( empty( $config['version'] ) ) {
			if ( ! function_exists('get_plugin_data')){
			require_once( ABSPATH . 'wp-admin/includes/plugin.php');
			}
			$plugin        = get_plugin_data( $config['file'] );
			$this->version = $plugin['Version'];
		}

		// Strings passed in from the updater config
		// $this->strings = $strings;
	}

	public function load_hooks() {
		add_action( 'init', [ $this, 'updater' ] );
		add_action( 'admin_menu', [ $this, 'license_menu' ] );
		add_action( 'admin_init', [ $this, 'register_option' ] );
		add_action( 'admin_init', [ $this, 'activate_license' ] );
		add_action( 'admin_init', [ $this, 'deactivate_license' ] );
		// add_action( 'admin_notices', [ $this, 'edd_sample_admin_notices' ] );
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
		// creates our settings in the options table
		register_setting(
			$this->slug . '-license',
			$this->option_key,
			'sanitize_license'
		);
	}

	public function sanitize_license( $new ) {
		$old = get_option( $this->option_key );
		if ( $old && $old !== $new ) {
			delete_option( 'edd_sample_license_status' ); // new license has been entered, so must reactivate
		}
		return $new;
	}

	public function updater() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		/* If there is no valid license key status, don't allow updates. */
		if ( 'valid' !== get_option( $this->slug . '_license_key_status', false ) ) {
			return;
		}

		// if ( ! class_exists( 'EDD_Theme_Updater' ) ) {
		// Load our custom theme updater
		// include dirname( __FILE__ ) . '/theme-updater-class.php';
		// }
		( new Plugin_Updater(
			[
				'api_url'     => $this->api_url,
				'api_data'    => $this->api_data,
				'name'        => $this->name,
				'slug'        => $this->slug,
				'version'     => $this->version,
				'license'     => trim( get_option( $this->slug . '_license_key' ) ),
				'author'      => $this->author,
				'wp_override' => $this->wp_override,
				'beta'        => $this->beta,
			]
		) )->load_hooks();

	}

	public function license_page() {
		$license = get_option( $this->option_key );
		$status  = get_option( 'edd_sample_license_status' );
		?>
	<div class="wrap">
		<h2><?php _e( 'Plugin License Options' ); ?></h2>
		<form method="post" action="options.php">

			<?php settings_fields( 'edd_sample_license' ); ?>

			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row" valign="top">
							<?php _e( 'License Key' ); ?>
						</th>
						<td>
							<input id="edd_sample_license_key" name="edd_sample_license_key" type="text" class="regular-text" value="<?php esc_attr_e( $license ); ?>" />
							<label class="description" for="edd_sample_license_key"><?php _e( 'Enter your license key' ); ?></label>
						</td>
					</tr>
					<?php if ( false !== $license ) { ?>
						<tr valign="top">
							<th scope="row" valign="top">
								<?php _e( 'Activate License' ); ?>
							</th>
							<td>
								<?php if ( $status !== false && $status == 'valid' ) { ?>
									<span style="color:green;"><?php _e( 'active' ); ?></span>
									<?php wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' ); ?>
									<input type="submit" class="button-secondary" name="edd_license_deactivate" value="<?php _e( 'Deactivate License' ); ?>"/>
									<?php
								} else {
									wp_nonce_field( 'edd_sample_nonce', 'edd_sample_nonce' );
									?>
									<input type="submit" class="button-secondary" name="edd_license_activate" value="<?php _e( 'Activate License' ); ?>"/>
								<?php } ?>
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
		if ( isset( $_POST['edd_license_activate'] ) ) {

			// run a quick security check
			if ( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) ) {
				return; // get out if we didn't click the Activate button
			}

			// retrieve the license from the database
			$license = trim( get_option( 'edd_sample_license_key' ) );

			// data to send in our API request
			$api_params = array(
				'edd_action' => 'activate_license',
				'license'    => $license,
				'item_name'  => urlencode( $this->name ), // the name of our product in EDD
				'url'        => home_url(),
			);

			// Call the custom API.
			$response = wp_remote_post(
				EDD_SAMPLE_STORE_URL,
				array(
					'timeout'   => 15,
					'sslverify' => false,
					'body'      => $api_params,
				)
			);

			// make sure the response came back okay
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

				if ( is_wp_error( $response ) ) {
					$message = $response->get_error_message();
				} else {
					$message = __( 'An error occurred, please try again.' );
				}
			} else {

				$license_data = json_decode( wp_remote_retrieve_body( $response ) );

				if ( false === $license_data->success ) {

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
			}

			// Check if anything passed on a message constituting a failure
			if ( ! empty( $message ) ) {
				$base_url = admin_url( 'plugins.php?page=' . $this->slug . '-license' );
				$redirect = add_query_arg(
					array(
						'sl_activation' => 'false',
						'message'       => rawurlencode( $message ),
					),
					$base_url
				);

				wp_redirect( $redirect );
				exit();
			}

			// $license_data->license will be either "valid" or "invalid"
			update_option( 'edd_sample_license_status', $license_data->license );
			wp_redirect( admin_url( 'plugins.php?page=' . $this->slug . '-license' ) );
			exit();
		}
	}


	/***********************************************
	 Illustrates how to deactivate a license key.
	 This will decrease the site count
	 ***********************************************/
	public function deactivate_license() {

		// listen for our activate button to be clicked
		if ( isset( $_POST['edd_license_deactivate'] ) ) {

			// run a quick security check
			if ( ! check_admin_referer( 'edd_sample_nonce', 'edd_sample_nonce' ) ) {
				return; // get out if we didn't click the Activate button
			}

			// retrieve the license from the database
			$license = trim( get_option( $this->option_key ) );

			// data to send in our API request
			$api_params = array(
				'edd_action' => 'deactivate_license',
				'license'    => $license,
				'item_name'  => rawurlencode( $this->name ), // the name of our product in EDD
				'url'        => home_url(),
			);

			// Call the custom API.
			$response = wp_remote_post(
				$this->api_url,
				array(
					'timeout'   => 15,
					'sslverify' => false,
					'body'      => $api_params,
				)
			);

			// make sure the response came back okay
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

				if ( is_wp_error( $response ) ) {
					$message = $response->get_error_message();
				} else {
					$message = __( 'An error occurred, please try again.' );
				}

				$base_url = admin_url( 'plugins.php?page=' . $this->slug . '-license' );
				$redirect = add_query_arg(
					array(
						'sl_activation' => 'false',
						'message'       => urlencode( $message ),
					),
					$base_url
				);

				wp_redirect( $redirect );
				exit();
			}

			// decode the license data
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			// $license_data->license will be either "deactivated" or "failed"
			if ( $license_data->license == 'deactivated' ) {
				delete_option( 'edd_sample_license_status' );
			}

			wp_redirect( admin_url( 'plugins.php?page=' . $this->slug . '-license' ) );
			exit();

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

		$license = trim( get_option( 'edd_sample_license_key' ) );

		$api_params = array(
			'edd_action' => 'check_license',
			'license'    => $license,
			'item_name'  => rawurlencode( $this->name ),
			'url'        => home_url(),
		);

		// Call the custom API.
		$response = wp_remote_post(
			EDD_SAMPLE_STORE_URL,
			array(
				'timeout'   => 15,
				'sslverify' => false,
				'body'      => $api_params,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$license_data = json_decode( wp_remote_retrieve_body( $response ) );

		if ( $license_data->license == 'valid' ) {
			echo 'valid';
			exit;
			// this license is still valid
		} else {
			echo 'invalid';
			exit;
			// this license is no longer valid
		}
	}

	/**
	 * This is a means of catching errors from the activation method above and displaying it to the customer
	 */
	function edd_sample_admin_notices() {
		if ( isset( $_GET['sl_activation'] ) && ! empty( $_GET['message'] ) ) {

			switch ( $_GET['sl_activation'] ) {

				case 'false':
					$message = urldecode( $_GET['message'] );
					?>
				<div class="error">
					<p><?php echo $message; ?></p>
				</div>
					<?php
					break;

				case 'true':
				default:
					// Developers can put a custom success message here for when activation is successful if they way.
					break;

			}
		}
	}

}
