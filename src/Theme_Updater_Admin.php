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
class Theme_Updater_Admin extends Settings {
	use License_Actions;

	// phpcs:disable Squiz.Commenting.VariableComment.Missing
	protected $api_url     = null;
	protected $slug        = null;
	protected $item_name   = null;
	protected $item_id     = null;
	protected $license     = null;
	protected $version     = null;
	protected $author      = null;
	protected $download_id = null;
	protected $renew_url   = null;
	protected $strings     = null;
	protected $data        = null;
	protected $cache_key   = null;
	// phpcs:enable

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
				'slug'        => get_template(),
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
		$this->slug        = sanitize_key( $config['slug'] );
		$this->license     = ! empty( $config['license'] ) ? $config['license'] : trim( get_site_option( $this->slug . '_license_key' ) );
		$this->api_data    = $config;
		$this->version     = $config['version'];
		$this->author      = $config['author'];
		$this->renew_url   = $config['renew_url'];
		$this->beta        = $config['beta'];
		$this->cache_key   = 'edd_sl_' . md5( json_encode( $this->slug . $this->api_data['license'] . $this->beta ) );

		// Populate version fallback.
		if ( empty( $config['version'] ) ) {
			$theme         = wp_get_theme( $this->slug );
			$this->version = $theme->get( 'Version' );
		}

		$this->strings = $this->get_strings();
		$this->data    = $config;

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
		$this->load_settings();
	}

	/**
	 * Load hooks for licence settings.
	 *
	 * @return void
	 */
	public function load_settings() {
		add_action( 'admin_init', [ $this, 'register_option' ] );
		add_action( 'admin_init', [ $this, 'license_action' ] );
		add_action( 'admin_notices', [ $this, 'show_error' ] );
		add_action( 'admin_init', [ $this, 'update_settings' ] );
		add_filter( 'http_request_args', [ $this, 'disable_wporg_request' ], 5, 2 );
		add_filter( 'edd_sl_updater_add_admin_page', [ $this, 'license_page' ] );
	}

	/**
	 * Creates the updater class.
	 */
	public function updater() {
		// Kludge to override capability check when doing cron.
		$doing_cron = defined( 'DOING_CRON' ) && DOING_CRON;
		if ( ! current_user_can( 'manage_options' ) && ! $doing_cron ) {
			return;
		}

		( new Theme_Updater(
			[
				'api_url'   => $this->api_url,
				'api_data'  => $this->api_data,
				'version'   => $this->version,
				'license'   => $this->license,
				'item_name' => $this->item_name,
				'item_id'   => $this->item_id,
				'author'    => $this->author,
				'beta'      => $this->beta,
				'cache_key' => $this->cache_key,
			],
			$this->strings
		) )->load_hooks();
	}
}
