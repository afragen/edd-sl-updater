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

/**
 * Class Bootstrap.
 */
class Bootstrap {
	protected $file;
	protected $dir;

	/**
	 * Class constructor.
	 *
	 * @param string $file File path.
	 */
	public function __construct( $file ) {
		$this->file = $file;
		$this->dir  = dirname( $file );
	}

	/**
	 * Let's get started.
	 *
	 * @return void
	 */
	public function run() {
		require_once $this->dir . '/vendor/autoload.php';
		add_action(
			'init',
			function() {
				load_plugin_textdomain( 'edd-sl-updater' );
			}
		);

		// ( new Plugin_Updater_Admin(
		// array(
		// 'file'      => $this->file,
		// 'api_url'   => 'http://easydigitaldownloads.com',
		// 'item_name' => 'EDD SL Updater',
		// 'item_id'   => 123,
		// 'version'   => '1.0',
		// 'author'    => 'Easy Digital Downloads',
		// 'beta'      => false,
		// )
		// ) )->load_hooks();
	}
}
