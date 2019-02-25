<?php
/*
Plugin Name: EDD Test Plugin
Description: Illustrates how to include an updater in your plugin for EDD Software Licensing
Author: Andy Fragen
Version: 0.9
License: MIT
*/

function edd_test_plugin_updater() {
	( new EDD\Software_Licensing\Updater\Plugin_Updater_Admin(
		array(
			'file'      => __FILE__,
			'api_url'   => 'http://eddstore.test',
			'item_name' => 'EDD Test Plugin',
			'item_id'   => 11,       // ID of the product
			'version'   => '0.9',                    // current version number
			'author'    => 'Andy Fragen', // author of this plugin
			'beta'      => false,
		)
	) )->load_hooks();
}
add_action( 'plugins_loaded', 'edd_test_plugin_updater' );
add_filter(
	'http_request_args',
	function( $r, $url ) {
		if ( false !== strpos( $url, 'eddstore.test' ) ) {
			$r['reject_unsafe_urls'] = false;
		}
		return $r;
	},
	10,
	2
);
