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
	}

}
