<?php
/**
 * Plugin Name: EDD Sample Plugin
 * Description: Illustrates how to include an updater in your plugin for EDD Software Licensing
 * Author: Andy Fragen
 * Version: 1.0
 * License: MIT
 */

// Automatically install EDD SL Updater.
require_once __DIR__ . '/vendor/autoload.php';
\WP_Dependency_Installer::instance()->run( __DIR__ );

/**
 * Load updater.
 * Must be in main plugin file.
 *
 * @return void
 */
function edd_test_plugin_updater() {
	$config = [
		'type'      => 'plugin', // Declare the type.
		'file'      => __FILE__,
		'api_url'   => 'http://eddstore.test', // Site where EDD SL store is located.
		'item_name' => 'EDD Test Plugin', // Name of plugin.
		'item_id'   => 11, // ID of the product.
		'version'   => '1.0', // Current version number.
		'author'    => 'Andy Fragen', // Author of this plugin.
		'beta'      => false,
	];
	if ( class_exists( 'EDD\\Software_Licensing\\Updater\\Bootstrap' ) ) {
		( new EDD\Software_Licensing\Updater\Init() )->run( $config );
	}
}
add_action( 'plugins_loaded', 'edd_test_plugin_updater' );
