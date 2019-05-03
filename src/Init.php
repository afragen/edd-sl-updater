<?php
/**
 * EDD SL Updater
 *
 * @package edd-sl-updater
 * @author Easy Digital Downloads
 * @license MIT
 */

namespace EDD\Software_Licensing\Updater;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Init
 */
class Init {

	/**
	 * Universal settings/updater loader.
	 *
	 * Load the correct settings/updater from a single function.
	 *
	 * @param array $config Configuration data for plugin or theme.
	 *
	 * @return void
	 */
	public function run( $config ) {
		( new Settings() )->load_hooks();
		if ( in_array( 'plugin', $config, true ) ) {
			( new Plugin_Updater_Admin( $config ) )->load_hooks();
		}
		if ( in_array( 'theme', $config, true ) ) {
			( new Theme_Updater_Admin( $config ) )->load_hooks();
		}
	}

	/**
	 * Universal updater.
	 *
	 * Load the correct updater from a single function.
	 *
	 * @param array $config Configuration data for plugin or theme.
	 *
	 * @return void
	 */
	public function updater( $config ) {
		if ( in_array( 'plugin', $config, true ) ) {
			( new Plugin_Updater_Admin( $config ) )->updater();
		}
		if ( in_array( 'theme', $config, true ) ) {
			( new Theme_Updater_Admin( $config ) )->updater();
		}
	}
}
