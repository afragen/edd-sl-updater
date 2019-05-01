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
 * Class License_Form
 */
class License_Form {
	/**
	 * Create generic license form table row for each add-on.
	 *
	 * Can override using `edd_sl_license_form_table` filter.
	 *
	 * @param array  $addon    Array of add-on data.
	 * @param string $license EDD SL license.
	 * @param string $status  'valid' or 'invalid'.
	 * @param string $message Activation/deactivation messsage.
	 * @param array  $strings Messaging strings.
	 *
	 * @return void
	 */
	public function row( $addon, $license, $status, $message, $strings ) {
		$slug     = $addon['slug'];
		$name     = $addon['item_name'];
		$dashicon = 'plugin' === $addon['type'] ? '<span class="dashicons dashicons-admin-plugins"></span>&nbsp;&nbsp;' : '&nbsp;';
		$dashicon = 'theme' === $addon['type'] ? '<span class="dashicons dashicons-admin-appearance"></span>&nbsp;&nbsp;' : $dashicon;

		echo '<tr valign="top">';
		?>
			<th scope="row" valign="top">
				<?php echo( $dashicon . esc_html( $name ) ); ?>
			</th>
			<td>
				<input id="<?php echo esc_attr( $slug ); ?>_license_key" name="<?php echo esc_attr( $slug ); ?>_license_key" type="text" class="regular-text" value="<?php echo esc_attr( $license, 'edd-sl-updater' ); ?>" />
				<label class="description" for="<?php echo esc_attr( $slug ); ?>_license_key"></label>
				<p class="description">
					<?php echo esc_html( $message ); ?>
				</p>
			</td>
		<?php
		if ( $license ) {
			echo '<td>';
			wp_nonce_field( $slug . '_nonce', $slug . '_nonce' );
			if ( 'valid' === $status ) {
				?>
				<input type="submit" class="button-secondary" name="<?php echo esc_attr( $slug ); ?>_license_deactivate" value="<?php echo esc_attr( $strings['deactivate-license'] ); ?>">
				<?php
			} else {
				?>
				<input type="submit" class="button-secondary" name="<?php echo esc_attr( $slug ); ?>_license_activate" value="<?php echo esc_attr( $strings['activate-license'] ); ?>"/>
				<?php
			}
			echo '</td>';
		}
		echo '</tr>';
	}
}
