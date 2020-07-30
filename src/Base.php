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
 * Trait Base
 */
trait Base {
	/** Variable for repo.
	 *
	 * @var \stdClass
	 */
	public $repo;
	/** Variable for cache.
	 *
	 * @var \stdClass
	 */
	public $response;

	/**
	 * Returns repo cached data.
	 *
	 * @access public
	 *
	 * @param string|bool $repo Repo name or false.
	 *
	 * @return array|bool The repo cache. False if expired.
	 */
	public function get_repo_cache( $repo = false ) {
		if ( ! $repo ) {
			$repo = isset( $this->slug ) ? $this->slug : 'edd-sl';
		}
		$cache_key = 'edd-sl-' . md5( $repo );
		$cache     = get_site_option( $cache_key );

		if ( empty( $cache['timeout'] ) || time() > $cache['timeout'] ) {
			return false;
		}

		return $cache;
	}

	/**
	 * Sets repo data for cache in site option.
	 *
	 * @access public
	 *
	 * @param string      $id       Data Identifier.
	 * @param mixed       $response Data to be stored.
	 * @param string|bool $repo     Repo name or false.
	 * @param string|bool $timeout  Timeout for cache.
	 *                              Default is $hours (12 hours).
	 *
	 * @return bool
	 */
	public function set_repo_cache( $id, $response, $repo = false, $timeout = false ) {
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$hours = 12;
		if ( ! $repo ) {
			$repo = isset( $this->type->slug ) ? $this->type->slug : 'edd-sl';
		}
		$cache_key = 'edd-sl-' . md5( $repo );
		$timeout   = $timeout ? $timeout : '+' . $hours . ' hours';

		$this->response['timeout'] = strtotime( $timeout );
		$this->response[ $id ]     = $response;

		update_site_option( $cache_key, $this->response );

		return true;
	}

	/**
	 * Convert sections from object to array.
	 *
	 * @param \stdClass $data Data from API.
	 *
	 * @return \stdClass
	 */
	public function convert_sections_to_array( $data ) {
		// Convert sections into an associative array, since we're getting an object, but Core expects an array.
		if ( isset( $data->sections ) && is_object( $data->sections ) ) {
			$data->sections = $this->convert_object_to_array( $data->sections );
		}
		if ( isset( $data->contributors ) && is_object( $data->contributors ) ) {
			$data->contributors = $this->convert_object_to_array( $data->contributors );
		}
		if ( isset( $data->banners ) && is_object( $data->banners ) ) {
			$data->banners = $this->convert_object_to_array( $data->banners );
		}
		if ( isset( $data->icons ) && is_object( $data->icons ) ) {
			$data->icons = $this->convert_object_to_array( $data->icons );
		}

		if ( ! isset( $data->plugin ) ) {
			$data->plugin = $this->file;
		}

		return $data;
	}
}
