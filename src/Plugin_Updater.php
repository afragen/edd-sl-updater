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
 * Class Plugin_Updater
 *
 * Allows plugins to use their own update API.
 */
class Plugin_Updater {
	use API_Common;
	use Updater_Common;

	// phpcs:disable Squiz.Commenting.VariableComment.Missing
	private $api_url              = null;
	private $api_data             = [];
	private $file                 = null;
	private $slug                 = null;
	private $version              = null;
	private $wp_override          = false;
	private $cache_key            = null;
	private $strings              = null;
	private $health_check_timeout = 5;
	// phpcs:enable

	/**
	 * Class constructor.
	 *
	 * @param array $args    Configuration data.
	 * @param array $strings Messaging strings.
	 */
	public function __construct( $args = [], $strings = [] ) {
		global $edd_plugin_data;

		$this->api_url                  = $args['api_url'];
		$this->api_data                 = $args;
		$this->file                     = $args['file'];
		$this->slug                     = $args['slug'];
		$this->version                  = $args['version'];
		$this->wp_override              = $args['wp_override'];
		$this->beta                     = $args['beta'];
		$this->cache_key                = $args['cache_key'];
		$this->strings                  = $strings;
		$edd_plugin_data[ $this->slug ] = $this->api_data;
	}

	/**
	 * Set up WordPress filters to hook into WP's update process.
	 *
	 * @return void
	 */
	public function load_hooks() {
		add_filter( 'site_transient_update_plugins', [ $this, 'update_transient' ], 15, 1 );
		add_filter( 'plugins_api', [ $this, 'plugins_api' ], 99, 3 );
	}

	/**
	 * Updates information on the "View version x.x details" and "View details" page with custom data.
	 *
	 * @param  mixed  $data   Default false.
	 * @param  string $action The type of information being requested from the Plugin Installation API.
	 * @param  object $args   Plugin API arguments.
	 * @return object $data
	 */
	public function plugins_api( $data, $action = '', $args = null ) {
		if ( 'plugin_information' !== $action ) {
			return $data;
		}

		if ( ! isset( $args->slug ) || ( $this->slug !== $args->slug ) ) {
			return $data;
		}

		$data = $this->get_repo_api_data( 'plugin' );

		return $data;
	}
}
