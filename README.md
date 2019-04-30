# EDD Sofware Licensing Updater

* Author URI: https://easydigitaldownloads.com
* Plugin URI: https://easydigitaldownloads.com
* Contributors: easydigitaldownloads, afragen
* Donate link: https://easydigitaldownloads.com/donate/
* Tags:
* Requires at least: 4.6
* Requires PHP: 5.4
* Tested up to: 5.1
* Stable Tag: master
* License: MIT

## Description

A universal updater for EDD Software Licensing products.

This plugin would be installed by all users of EDD Software Licensing Addons so they would be able to see updates from the store. This allows EDD Addon developers to simplify the code required to access the update system from EDD Software Licensing.

PRs welcome at [EDD SL Updater](https://github.com/afragen/edd-sl-updater).

## Installation for EDD SL Add-ons

The following code examples show how to instantiate and install the EDD SL Updater plugin for EDD SL Add-on plugins and themes.

In the samples, the `wp-dependency.json` file **must** be included with all EDD SL Add-ons. To add the appropriate elements to your `composer.json` run the following command from your plugin/theme folder.

`composer require afragen/wp-dependency-installer`

The code installed via composer will automatically install and activate the EDD SL Updater plugin as a required dependency for any EDD SL Add-on.

Additionally, you may need then need to run `composer update` prior to plugin distribution.

The EDD SL Updater now uses a universal instantiation function that will correctly instantiate your plugin or theme based upon the configuration array passed to the function.

### Plugin Updater Example

The following is an example of how to instantiate the updater from a plugin. It is run from the main plugin file.

```php
// Automatically install EDD SL Updater.
require_once __DIR__ . '/vendor/autoload.php';
\WP_Dependency_Installer::instance()->run( __DIR__ );

// Loads the updater classes
function prefix_plugin_updater() {
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
add_action( 'plugins_loaded', 'prefix_plugin_updater' );
```

### Theme Updater Example

The following is an example of how to instantiate the updater from a theme. It is run from the theme's `functions.php` file.

```php
// Automatically install EDD SL Updater.
require_once __DIR__ . '/vendor/autoload.php';
\WP_Dependency_Installer::instance()->run( __DIR__ );

// Loads the updater classes
function prefix_theme_updater() {
	$config = [
		'type'        => 'theme', // Declare the type.
		'api_url'     => 'http://eddstore.test', // Site where EDD SL store is located.
		'item_name'   => 'EDD Test Theme', // Name of theme.
		'item_id'     => 27, // ID of the product.
		'slug'        => 'edd-test-theme', // Theme slug.
		'version'     => '1.0', // Current version of this theme.
		'author'      => 'Andy Fragen', // Author of this theme
		'download_id' => '', // Optional, used for generating a license renewal link.
		'renew_url'   => '', // Optional, allows for a custom license renewal link.
		'beta'        => false, // Optional, set to true to opt into beta versions.
	];
	if ( class_exists( 'EDD\\Software_Licensing\\Updater\\Bootstrap' ) ) {
		( new EDD\Software_Licensing\Updater\Init() )->run( $config );
	}
}
add_action( 'after_setup_theme', 'prefix_theme_updater' );
```

## Changelog
[Changelog](./CHANGES.md)
