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

class Bootstrap {

    protected $file;
	protected $dir;

	public function __construct( $file ) {
        $this->file = $file;
		$this->dir = dirname($file);
	}

	public function run() {
		require_once $this->dir . '/vendor/autoload.php';
		( new Plugin_Updater_Admin(
			array(
				'file'        => __FILE__,
				'api_url'     => 'http://easydigitaldownloads.com',
				'plugin_slug' => 'edd-sl-updater',
				'item_name'   => 'EDD SL Updater',
				'item_id'     => 123,       // ID of the product
				'version'     => '1.0',                    // current version number
				'author'      => 'Easy Digital Downloads', // author of this plugin
				'beta'        => false,
			),
			array()
		) )->load_hooks();
	}
}
