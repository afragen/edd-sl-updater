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
 * Class Bootstrap
 */
class Bootstrap {
	/**
	 * File name.
	 *
	 * @var string
	 */
	protected $file;

	/**
	 * Class constructor.
	 *
	 * @param string $file File path.
	 */
	public function __construct( $file ) {
		$this->file = $file;
	}

	/**
	 * Let's get started.
	 *
	 * @return void
	 */
	public function run() {
		add_action(
			'init',
			function() {
				load_plugin_textdomain( 'edd-sl-updater' );
			}
		);

		// Initiate decoupled language pack updating.
		( new \Fragen\Translations_Updater\Init( __NAMESPACE__ ) )->edd_run();

		$updater_config = [
			'type'      => 'plugin',
			'file'      => $this->file,
			'api_url'   => 'http://easydigitaldownloads.com',
			'item_name' => 'EDD SL Updater',
			'item_id'   => 123,
			'version'   => '1.0',
			'author'    => 'Easy Digital Downloads',
			'beta'      => false,
		];
		// ( new Init() )->run( $updater_config );
	}
}
