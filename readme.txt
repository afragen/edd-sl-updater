# EDD Sofware Licensing Updater

Author URI: https://easydigitaldownloads.com
Plugin URI: https://easydigitaldownloads.com
Contributors: easydigitaldownloads, afragen
Donate link: https://easydigitaldownloads.com/donate/
Tags:
Requires at least: 4.6
Requires PHP: 5.4
Tested up to: 5.1
Stable Tag: x.x
License: MIT

## Description

A universal updater for EDD Software Licensing products.

PRs welcome at [EDD SL Updater](https://github.com/afragen/edd-sl-updater).

## Plugin Updater Example

The following is an example of how it instantiate the updater from a plugin.

    // Loads the updater classes
    function prefix_plugin_updater() {
    	( new EDD\Software_Licensing\Updater\Plugin_Updater_Admin(
    		array(
        		'file'      => __FILE__,
    			'api_url'   => 'http://eddstore.test', // Site where EDD is hosted.
    			'item_name' => 'EDD Test Plugin', // Name of plugin.
    			'item_id'   => 11, // ID of the product.
    			'version'   => '0.9', // Current version number.
    			'author'    => 'Andy Fragen', // Author of this plugin.
    			'beta'      => false, // Optional, set to true to opt into beta versions.
    		)
    	) )->load_hooks();
    }
    add_action( 'plugins_loaded', 'prefix_plugin_updater' );


## Theme Updater Example

The following is an example of how it instantiate the updater from a theme.

    // Loads the updater classes
    function prefix_theme_updater() {
    	( new EDD\Software_Licensing\Updater\Theme_Updater_Admin(
    		array(
    			'api_url'     => 'http://eddstore.test', // Site where EDD is hosted.
    			'item_name'   => 'EDD Test Theme', // Name of theme.
    			'item_id'     => 27, // ID of the product.
    			'theme_slug'  => 'edd-test-theme', // Theme slug.
    			'version'     => '1.0', // The current version of this theme.
    			'author'      => 'Andy Fragen', // The author of this theme.
    			'download_id' => '', // Optional, used for generating a license renewal link.
    			'renew_url'   => '', // Optional, allows for a custom license renewal link.
    			'beta'        => false, // Optional, set to true to opt into beta versions.
    		)
    	) )->load_hooks();
    }
    add_action( 'after_setup_theme', 'prefix_theme_updater' );

## Changelog
[Changelog](./CHANGES.md)
