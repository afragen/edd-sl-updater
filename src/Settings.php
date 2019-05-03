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
 * Class Settings
 */
class Settings {

	/**
	 * Load hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_action( 'admin_menu', [ $this, 'add_plugin_menu' ] );
	}

	/**
	 * Registers the option used to store the license key in the options table.
	 */
	public function register_option() {
		register_setting(
			$this->slug . '-license',
			$this->slug . '_license_key',
			[]
		);
	}

	/**
	 * Add options page.
	 */
	public function add_plugin_menu() {
		global $_registered_pages;
		if ( isset( $_registered_pages['settings_page_edd-sl-updater'] ) ) {
			return;
		}

		$parent     = 'options-general.php';
		$capability = 'manage_options';

		add_submenu_page(
			$parent,
			esc_html__( 'EDD SL Licenses', 'edd-sl-updater' ),
			esc_html__( 'EDD SL Licenses', 'edd-sl-updater' ),
			$capability,
			'edd-sl-updater',
			[ $this, 'create_admin_page' ]
		);
	}

	/**
	 * Options page callback.
	 */
	public function create_admin_page() {
		?>
		<div class="wrap">
			<h2>
				<?php esc_html_e( 'EDD SL Licenses', 'edd-sl-updater' ); ?>
			</h2>
			<form method='post' action='options.php'>
			<table class='form-table'>
			<tbody>
				<tr>
				<th></th>
				<th>
				<?php esc_html_e( 'License Key', 'edd-sl-updater' ); ?></th>
				<th>
				<?php esc_html_e( 'License Action', 'edd-sl-updater' ); ?></th>
				</th>
				</tr>
		<?php
		/**
		 * Action hook to add admin page data to appropriate $tab.
		 *
		 * @since 8.0.0
		 */
		do_action( 'edd_sl_updater_add_admin_page' );

		echo '</tbody></table></div>';
		submit_button();
		echo '</form>';
	}

	/**
	 * Update settings.
	 *
	 * @return void|bool
	 */
	public function update_settings() {
		if ( ! isset( $_POST['_wp_http_referer'] ) ) {
			return false;
		}
		$query = parse_url( $_POST['_wp_http_referer'], PHP_URL_QUERY );
		parse_str( $query, $arr );

		if ( isset( $_POST['option_page'] ) &&
			'edd-sl-updater' === $arr['page']
		) {
			foreach ( array_keys( $_POST ) as $key ) {
				if ( false !== strpos( $key, '_deactivate' ) ) {
					return;
				}
				if ( false !== strpos( $key, '_activate' ) ) {
					return;
				}
			}

			foreach ( $_POST as $option => $value ) {
				if ( false !== strpos( $option, '_license_key' ) ) {
					$slug  = str_replace( '_license_key', '', $option );
					$value = $this->sanitize_license( $slug, $value );
					update_option( sanitize_key( $option ), sanitize_text_field( $value ) );
					delete_transient( $slug . '_license_message' );
				}
			}
			$this->redirect();
		}
	}

	/**
	 * Sanitize license.
	 *
	 * @param string $slug Slug.
	 * @param string $new  License.
	 *
	 * @return string $new
	 */
	public function sanitize_license( $slug, $new ) {
		$old = get_option( $slug . '_license_key' );
		if ( $old && $old !== $new ) {
			delete_option( $this->slug . '_license_key_status' );
			delete_transient( $this->slug . '_license_message' );
		}

		return $new;
	}

	/**
	 * Outputs the markup used on the plugin license page.
	 */
	public function license_page() {
		$license = $this->license;
		$status  = get_option( $this->slug . '_license_key_status', false );

		// Checks license status to display under license key.
		if ( ! $license ) {
			$message = $this->strings['enter-key'];
		} else {
			// delete_transient( $this->slug . '_license_message' );
			if ( ! get_transient( $this->slug . '_license_message', false ) ) {
				set_transient( $this->slug . '_license_message', $this->check_license( $this->slug ), DAY_IN_SECONDS );
			}
			$message = get_transient( $this->slug . '_license_message' );
		}
		settings_fields( $this->data['slug'] . '_license' );
		$form_table_row = ( new License_Form() )->row( $this->data, $license, $status, $message, $this->strings );
		/**
		 * Filter to echo a customized license form table.
		 *
		 * @since 1.0.0
		 *
		 * @param string $form_table  Table HTML for a license page setting.
		 * @param string $plugin_data EDD SL Add-on data.
		 * @param string $license     EDD SL license.
		 * @param string $status      License status.
		 * @param string $message     License message.
		 * @param array  $strings     Messaging strings.
		 */
		echo esc_html( apply_filters( 'edd_sl_license_form_table', $form_table_row, $this->data, $license, $status, $message, $this->strings ) );
	}
}
