<?php
/**
 * EDD SL Updater
 *
 * @package edd-sl-updater
 * @author Easy Digital Downloads
 * @license MIT
 */

/**
 * Plugin Name:       EDD Software Licensing Updater
 * Plugin URI:        https://github.com/afragen/edd-sl-updater
 * Description:       A universal updater for EDD Software Licensing products.
 * Version:           0.11.10
 * Author:            Andy Fragen
 * License:           MIT
 * Network:           true
 * Text Domain:       edd-sl-updater
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/afragen/edd-sl-updater
 * Requires at least: 4.7
 * Requires PHP:      5.6
 */

namespace EDD\Software_Licensing\Updater;

// Exit if called directly.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once __DIR__ . '/vendor/autoload.php';
( new Bootstrap( __FILE__ ) )->run();
