<?php
/**
 * EDD SL Updater
 *
 * @package edd-sl-updater
 * @author Easy Digital Downloads
 * @license MIT
 */

/**
 * Plugin Name:       EDD SL Updater
 * Plugin URI:        https://github.com/afragen/edd-sl-updater
 * Description:       A universal updater for EDD Software Licensing.
 * Version:           0.1
 * Author:            Andy Fragen
 * License:           MIT
 * Network:           true
 * Text Domain:       edd-sl-updater
 * GitHub Plugin URI: https://github.com/afragen/edd-sl-updater
 * Requires WP:       4.6
 * Requires PHP:      5.4
 */

// Exit if called directly.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once __DIR__ . '/vendor/autoload.php';
