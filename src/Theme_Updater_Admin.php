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
 * Class Theme_Updater_Admin
 */
class Theme_Updater_Admin {
	use API_Common;

	/**
	 * Variables required for the theme updater
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $api_url     = null;
	protected $theme_slug  = null;
	protected $item_name   = null;
	protected $item_id     = null;
	protected $version     = null;
	protected $author      = null;
	protected $download_id = null;
	protected $renew_url   = null;
	protected $strings     = null;

	/**
	 * Class constructor.
	 *
	 * @param array $config Configuration parameters.
	 */
	public function __construct( $config = [] ) {
		$config = wp_parse_args(
			$config,
			[
				'api_url'     => 'http://easydigitaldownloads.com',
				'theme_slug'  => get_template(),
				'item_name'   => '',
				'item_id'     => '',
				'download_id' => '',
				'license'     => '',
				'version'     => '',
				'author'      => '',
				'renew_url'   => '',
				'beta'        => false,
			]
		);

		// Set config arguments.
		$this->api_url     = $config['api_url'];
		$this->item_name   = $config['item_name'];
		$this->item_id     = $config['item_id'];
		$this->download_id = $config['download_id'];
		$this->theme_slug  = sanitize_key( $config['theme_slug'] );
		$this->version     = $config['version'];
		$this->author      = $config['author'];
		$this->renew_url   = $config['renew_url'];
		$this->beta        = $config['beta'];

		// Populate version fallback.
		if ( empty( $config['version'] ) ) {
			$theme         = wp_get_theme( $this->theme_slug );
			$this->version = $theme->get( 'Version' );
		}

		$this->strings = $this->get_strings();

		/**
		 * Fires after the theme $config is setup.
		 *
		 * @since 1.0.0
		 *
		 * @param array $config Array of EDD SL theme data.
		 */
		do_action( 'post_edd_sl_theme_updater_setup', $config );
	}

	/**
	 * Load all our hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_action( 'init', [ $this, 'updater' ] );
		add_action( 'admin_menu', [ $this, 'license_menu' ] );
		add_action( 'admin_init', [ $this, 'register_option' ] );
		add_action( 'admin_init', [ $this, 'license_action' ] );
		add_action( 'admin_notices', [ $this, 'show_error' ] );
		add_action( 'update_option_' . $this->theme_slug . '_license_key', [ $this, 'activate_license' ], 10, 2 );
		add_filter( 'http_request_args', [ $this, 'disable_wporg_request' ], 5, 2 );
	}

	/**
	 * Creates the updater class.
	 */
	public function updater() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// If there is no valid license key status, don't allow updates.
		if ( 'valid' !== get_option( $this->theme_slug . '_license_key_status', false ) ) {
			return;
		}

		( new Theme_Updater(
			[
				'api_url'   => $this->api_url,
				'version'   => $this->version,
				'license'   => trim( get_option( $this->theme_slug . '_license_key' ) ),
				'item_name' => $this->item_name,
				'item_id'   => $this->item_id,
				'author'    => $this->author,
				'beta'      => $this->beta,
			],
			$this->strings
		) )->load_hooks();
	}

	/**
	 * Adds a menu item for the theme license under the appearance menu.
	 */
	public function license_menu() {
		$strings = $this->strings;

		add_theme_page(
			$strings['theme-license'],
			$strings['theme-license'],
			'manage_options',
			$this->theme_slug . '-license',
			[ $this, 'license_page' ]
		);
	}

	/**
	 * Registers the option used to store the license key in the options table.
	 */
	public function register_option() {
		register_setting(
			$this->theme_slug . '-license',
			$this->theme_slug . '_license_key',
			[ $this, 'sanitize_license' ]
		);
	}

	/**
	 * Sanitizes the license key.
	 *
	 * @param  string $new License key that was submitted.
	 * @return string $new Sanitized license key.
	 */
	public function sanitize_license( $new ) {
		$old = get_option( $this->theme_slug . '_license_key' );

		if ( $old && $old !== $new ) {
			// New license has been entered, so must reactivate
			delete_option( $this->theme_slug . '_license_key_status' );
			delete_transient( $this->theme_slug . '_license_message' );
		}

		return $new;
	}

	/**
	 * Outputs the markup used on the theme license page.
	 */
	public function license_page() {
		$license = trim( get_option( $this->theme_slug . '_license_key' ) );
		$status  = get_option( $this->theme_slug . '_license_key_status', false );

		// Checks license status to display under license key.
		if ( ! $license ) {
			$message = $strings['enter-key'];
		} else {
			// delete_transient( $this->theme_slug . '_license_message' );
			if ( ! get_transient( $this->theme_slug . '_license_message', false ) ) {
				set_transient( $this->theme_slug . '_license_message', $this->check_license( $this->theme_slug ), ( 60 * 60 * 24 ) );
			}
			$message = get_transient( $this->theme_slug . '_license_message' );
		} ?>
		<div class="wrap">
			<h2>
			<?php echo esc_attr( $this->strings['theme-license'] . ' - ' . $this->item_name ); ?>
			</h2>
			<form method="post" action="options.php">
				<?php
				settings_fields( $this->theme_slug . '-license' );

				$form_table = ( new License_Form() )->table( $this->theme_slug, $license, $status, $message, $this->strings );

				/**
				 * Filter to echo a customized license form table.
				 *
				 * @since 1.0.0
				 *
				 * @param string $form_table Table HTML for a license page setting.
				 * @param string $slug       EDD SL Add-on slug.
				 * @param string $license    EDD SL license.
				 * @param string $status     License status.
				 * @param string $message    License message.
				 * @param array  $strings    Messaging strings.
				 */
				echo apply_filters( 'edd_sl_license_form_table', $form_table, $this->theme_slug, $license, $status, $message, $this->strings );

				submit_button();
				?>
			</form>
		<?php
	}

	/**
	 * Activates the license key.
	 */
	public function activate_license() {
		$license = trim( get_option( $this->theme_slug . '_license_key' ) );

		// Data to send in our API request.
		$api_params = [
			'edd_action' => 'activate_license',
			'license'    => $license,
			'item_name'  => rawurlencode( $this->item_name ),
			'item_id'    => $this->item_id,
			'url'        => home_url(),
		];

		$license_data = $this->get_api_response( $this->api_url, $api_params );

		if ( $license_data->success && isset( $license_data->error ) ) {
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

		// $response->license will be either "active" or "inactive".
		if ( $license_data && isset( $license_data->license ) ) {
			update_option( $this->theme_slug . '_license_key_status', $license_data->license );
			delete_transient( $this->theme_slug . '_license_message' );
		}

		if ( ! empty( $message ) ) {
			$error_data['success']       = false;
			$error_data['error_code']    = esc_attr__( 'activate_theme_license', 'edd-sl-updater' );
			$error_data['error_message'] = esc_html( $message );
		} else {
			$error_data = null;
		}
		$this->redirect( $error_data );
	}

	/**
	 * Deactivates the license key.
	 */
	public function deactivate_license() {
		// Retrieve the license from the database.
		$license = trim( get_option( $this->theme_slug . '_license_key' ) );

		// Data to send in our API request.
		$api_params = [
			'edd_action' => 'deactivate_license',
			'license'    => $license,
			'item_name'  => rawurlencode( $this->item_name ),
			'item_id'    => $this->item_id,
			'url'        => home_url(),
		];

		add_filter( 'edd_sl_api_request_verify_ssl', '__return_false' );
		$license_data = $this->get_api_response( $this->api_url, $api_params );

		if ( $license_data->success && property_exists( $license_data, 'error' ) ) {
			$message = $this->strings['error'];
		}
		if ( ! empty( $message ) ) {
			$error_data['success']       = false;
			$error_data['error_code']    = esc_attr__( 'deactivate_theme_license', 'edd-sl-updater' );
			$error_data['error_message'] = esc_html( $message );
		} else {
			$error_data['success'] = true;
		}

		// $license_data->license will be either "deactivated" or "failed".
		if ( $license_data && ( 'deactivated' === $license_data->license ) ) {
			delete_option( $this->theme_slug . '_license_key_status' );
			delete_transient( $this->theme_slug . '_license_message' );
		}
		$this->redirect( $error_data );
	}

	/**
	 * Constructs a renewal link.
	 *
	 * @since 1.0.0
	 */
	public function get_renewal_link() {
		// If a renewal link was passed in the config, use that.
		if ( ! empty( $this->renew_url ) ) {
			return $this->renew_url;
		}

		// If download_id was passed in the config, a renewal link can be constructed.
		$license_key = trim( get_option( $this->theme_slug . '_license_key', false ) );
		if ( ! empty( $this->download_id ) && $license_key ) {
			$url  = esc_url( $this->api_url );
			$url .= '/checkout/?edd_license_key=' . $license_key . '&download_id=' . $this->download_id;

			return $url;
		}

		// Otherwise return the api_url.
		return $this->api_url;
	}

	/**
	 * Checks if a license action was submitted.
	 *
	 * @since 1.0.0
	 */
	public function license_action() {
		if ( isset( $_POST[ $this->theme_slug . '_license_activate' ] ) ) {
			if ( check_admin_referer( $this->theme_slug . '_nonce', $this->theme_slug . '_nonce' ) ) {
				$this->activate_license();
			}
		}

		if ( isset( $_POST[ $this->theme_slug . '_license_deactivate' ] ) ) {
			if ( check_admin_referer( $this->theme_slug . '_nonce', $this->theme_slug . '_nonce' ) ) {
				$this->deactivate_license();
			}
		}
	}

	/**
	 * Disable requests to wp.org repository for this theme.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $r
	 * @param string $url
	 *
	 * @return array $r
	 */
	public function disable_wporg_request( $r, $url ) {
		// If it's not a theme update request, bail.
		if ( 0 !== strpos( $url, 'https://api.wordpress.org/themes/update-check/1.1/' ) ) {
			return $r;
		}

		// Decode the JSON response.
		$themes = json_decode( $r['body']['themes'] );

		// Remove the active parent and child themes from the check.
		$parent = get_option( 'template' );
		$child  = get_option( 'stylesheet' );
		unset( $themes->themes->$parent, $themes->themes->$child );

		// Encode the updated JSON response.
		$r['body']['themes'] = json_encode( $themes );

		return $r;
	}
}
