<?php
/**
 * This is just a demonstration of how theme licensing works with
 * Easy Digital Downloads.
 *
 * @package EDD Sample Theme
 */

/**
 * Load theme updater functions.
 * Action is used so that child themes can easily disable.
 */

function prefix_theme_updater() {
	( new EDD\Software_Licensing\Updater\Theme_Updater_Admin(
		array(
			'api_url'     => 'http://eddstore.test', // Site where EDD is hosted
			'item_name'   => 'EDD Test Theme', // Name of theme
			'item_id'     => 27,
			'theme_slug'  => 'edd-test-theme', // Theme slug
			'version'     => '0.9', // The current version of this theme
			'author'      => 'Andy Fragen', // The author of this theme
			'download_id' => '', // Optional, used for generating a license renewal link
			'renew_url'   => '', // Optional, allows for a custom license renewal link
			'beta'        => false, // Optional, set to true to opt into beta versions
		)
	) )->load_hooks();
}
add_action( 'after_setup_theme', 'prefix_theme_updater' );

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
