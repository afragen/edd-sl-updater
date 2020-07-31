<?php
/**
 * EDD SL Updater
 *
 * @package edd-sl-updater
 * @author Andy Fragen
 * @license MIT
 */

namespace EDD\Software_Licensing\Updater;

/**
 * Class Theme_Updater
 */
class Theme_Updater {
	use API_Common;
	use Updater_Common;

	// phpcs:disable Squiz.Commenting.VariableComment.Missing
	private $api_url              = null;
	private $api_data             = [];
	private $response_key         = null;
	private $slug                 = null;
	private $license_key          = null;
	private $cache_key            = null;
	private $version              = null;
	private $author               = null;
	private $strings              = null;
	private $health_check_timeout = 5;
	// phpcs:enable

	/**
	 * Class constructor.
	 *
	 * @param array $args    Array of arguments from the theme requesting an update check.
	 * @param array $strings Strings for the update process.
	 */
	public function __construct( $args = [], $strings = [] ) {
		$defaults = [
			'api_url'   => 'http://easydigitaldownloads.com',
			'slug'      => get_stylesheet(),
			'item_name' => '',
			'license'   => '',
			'version'   => '',
			'author'    => '',
			'beta'      => false,
		];

		$args = wp_parse_args( $args, $defaults );

		$this->api_url      = $args['api_url'];
		$this->api_data     = $args;
		$this->license      = $args['license'];
		$this->item_name    = $args['item_name'];
		$this->version      = $args['version'];
		$this->slug         = sanitize_key( $args['slug'] );
		$this->author       = $args['author'];
		$this->beta         = $args['beta'];
		$this->cache_key    = $args['cache_key'];
		$this->response_key = $this->slug . '-' . $this->beta . '-update-response';
		$this->strings      = $strings;
	}

	/**
	 * Load hooks.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_filter( 'site_transient_update_themes', [ $this, 'update_transient' ] );
		add_filter( 'delete_site_transient_update_themes', [ $this, 'delete_theme_update_transient' ] );
		add_action( 'load-update-core.php', [ $this, 'delete_theme_update_transient' ] );
		add_action( 'load-themes.php', [ $this, 'delete_theme_update_transient' ] );
		add_action( 'load-themes.php', [ $this, 'load_themes_screen' ] );
	}

	/**
	 * Show the update notification when necessary.
	 *
	 * @return void
	 */
	public function load_themes_screen() {
		add_thickbox();
		add_action( 'admin_notices', [ $this, 'update_nag' ] );
	}

	/**
	 * Display the update notifications.
	 *
	 * @return void
	 */
	public function update_nag() {
		$theme        = wp_get_theme( $this->slug );
		$api_response = get_transient( $this->response_key );

		if ( false === $api_response ) {
			return;
		}

		$update_url     = wp_nonce_url( 'update.php?action=upgrade-theme&amp;theme=' . rawurlencode( $this->slug ), 'upgrade-theme_' . $this->slug );
		$update_onclick = ' onclick="if ( confirm(\'' . esc_js( $this->strings['update-notice'] ) . '\') ) {return true;}return false;"';

		if ( version_compare( $this->version, $api_response->new_version, '<' ) ) {
			echo '<div id="update-nag">';
			printf(
				wp_kses_post( $this->strings['update-available'] ),
				esc_attr( $theme->get( 'Name' ) ),
				esc_attr( $api_response->new_version ),
				// $api_response->url . '#TB_inline?width=640&amp;inlineId=' . $this->slug . '_changelog',
				esc_url( $api_response->url . '&TB_iframe=true&width=1024&width=800' ),
				esc_attr( $theme->get( 'Name' ) ),
				esc_url( $update_url ),
				esc_attr( $update_onclick )
			);
			echo '</div>';
			echo '<div id="' . esc_attr( $this->slug ) . '_changelog" style="display:none;">';
			echo wp_kses_post( wpautop( $api_response->sections['changelog'] ) );
			echo '</div>';
		}
	}
	public function delete_theme_update_transient() {
		delete_transient( $this->response_key );
	}
	}

}
