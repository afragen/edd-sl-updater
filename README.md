# EDD Sofware Licensing Updater

## Plugin Updater Example

```php
( new EDD\Software_Licensing\Updater\Plugin_Updater_Admin(
	array(
		'file' => __FILE__,
		'api_url' => 'http://easydigitaldownloads.com',
		'item_name' => 'Sample Plugin',
		'item_id' => 123,       // ID of the product
		'version' => '1.0',                    // current version number
		'author'  => 'Easy Digital Downloads', // author of this plugin
		'beta'    => false,
	)
) )->load_hooks();
```

## Theme Updater Example

```php
// Loads the updater classes
( new Theme_Updater_Admin(

	// Config settings
	$config = array(
		'remote_api_url' => 'https://easydigitaldownloads.com', // Site where EDD is hosted
		'item_name'      => 'Theme Name', // Name of theme
		'theme_slug'     => 'theme-slug', // Theme slug
		'version'        => '1.0.0', // The current version of this theme
		'author'         => 'Easy Digital Downloads', // The author of this theme
		'download_id'    => '', // Optional, used for generating a license renewal link
		'renew_url'      => '', // Optional, allows for a custom license renewal link
		'beta'           => false, // Optional, set to true to opt into beta versions
	)
) )->load_hooks();
```
