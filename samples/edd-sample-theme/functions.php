<?php
/**
 * This is just a demonstration of how theme licensing works with
 * Easy Digital Downloads.
 *
 * @package EDD Sample Theme
 */

/**
 * Load updater.
 * Action is used so that child themes can easily disable.
 */

// Automatically install EDD SL Updater.
require_once __DIR__ . '/vendor/autoload.php';
\WP_Dependency_Installer::instance()->run( __DIR__ );

// Required filter to ensure persist-admin-notices-dismissal properly loads JS.
add_filter( 'pand_theme_loader', '__return_true' );

/**
 * Test theme updater instantiate.
 *
 * @return void
 */
function edd_test_theme_updater() {
	$config = [
		'type'        => 'theme', // Declare the type.
		'api_url'     => 'http://eddstore.test', // Site where EDD is hosted.
		'item_name'   => 'EDD Test Theme', // Name of theme.
		'item_id'     => 27, // Item ID from Downloads page.
		'slug'        => 'edd-test-theme', // Theme slug.
		'version'     => '1.0', // The current version of this theme.
		'author'      => 'Andy Fragen', // The author of this theme.
		'download_id' => '', // Optional, used for generating a license renewal link.
		'renew_url'   => '', // Optional, allows for a custom license renewal link.
		'beta'        => false, // Optional, set to true to opt into beta versions.
	];
	if ( class_exists( 'EDD\\Software_Licensing\\Updater\\Bootstrap' ) ) {
		( new EDD\Software_Licensing\Updater\Init() )->run( $config );
	}
}
add_action( 'after_setup_theme', 'edd_test_theme_updater' );
