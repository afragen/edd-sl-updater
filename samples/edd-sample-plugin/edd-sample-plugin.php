<?php
/*
Plugin Name: EDD Sample Plugin
Description: Illustrates how to include an updater in your plugin for EDD Software Licensing
Author: Andy Fragen
Version: 1.0
License: MIT
*/

// Automatically install EDD SL Updater.
require_once __DIR__ . '/vendor/autoload.php';
\WP_Dependency_Installer::instance()->run( __DIR__ );

function edd_test_plugin_updater() {
	if ( class_exists( 'EDD\\Software_Licensing\\Updater\\Bootstrap' ) ) {
		( new EDD\Software_Licensing\Updater\Plugin_Updater_Admin(
			[
				'file'      => __FILE__,
				'api_url'   => 'http://eddstore.test',
				'item_name' => 'EDD Test Plugin',
				'item_id'   => 11, // ID of the product.
				'version'   => '1.0', // current version number.
				'author'    => 'Andy Fragen', // author of this plugin.
				'beta'      => false,
			]
		) )->load_hooks();
	}
}
add_action( 'plugins_loaded', 'edd_test_plugin_updater' );
