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
trait Updater_Common {

	/**
	 * Convert sections from object to associative array.
	 * Core expects an array.
	 *
	 * @param \stdClass $data Data from API.
	 *
	 * @return \stdClass
	 */
	public function convert_sections_to_array( $data ) {
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

		return $data;
	}

	/**
	 * Convert some objects to arrays when injecting data into the update API.
	 *
	 * Some data like sections, banners, and icons are expected to be an associative array,
	 * however due to the JSON decoding, they are objects. This method allows us to pass
	 * in the object and return an associative array.
	 *
	 * @since 3.6.5
	 *
	 * @param stdClass $data Data to be converted.
	 *
	 * @return array
	 */
	private function convert_object_to_array( $data ) {
		$new_data = [];
		foreach ( $data as $key => $value ) {
			$new_data[ $key ] = is_object( $value ) ? $this->convert_object_to_array( $value ) : $value;
		}

		return $new_data;
	}

	/**
	 * Get cached version info.
	 *
	 * @param string $cache_key Cache key.
	 *
	 * @return string $cache['value']
	 */
	protected function get_cached_version_info( $cache_key = '' ) {
		if ( empty( $cache_key ) ) {
			$cache_key = $this->cache_key;
		}

		$cache = get_site_option( $cache_key );

		if ( empty( $cache['timeout'] ) || time() > $cache['timeout'] ) {
			return false; // Cache is expired.
		}

		if ( empty( $cache['value'] ) ) {
			return false; // Ensure there is some useful data.
		}

		// We need to turn the icons into an array, thanks to WP Core forcing these into an object at some point.
		$cache['value'] = json_decode( $cache['value'] );
		if ( ! empty( $cache['value']->icons ) ) {
			$cache['value']->icons = (array) $cache['value']->icons;
		}

		return $cache['value'];
	}

	/**
	 * Set version info cache.
	 *
	 * @param string $value     Cache value.
	 * @param string $cache_key Cache key.
	 *
	 * @return bool|void
	 */
	protected function set_version_info_cache( $value = '', $cache_key = '' ) {
		if ( empty( $value ) ) {
			return false;
		}
		if ( empty( $cache_key ) ) {
			$cache_key = $this->cache_key;
		}

		$data = [
			'timeout' => strtotime( '+12 hours', time() ),
			'value'   => json_encode( $value ),
		];

		update_site_option( $cache_key, $data );
	}

	/**
	 * Get repo API data from store.
	 * Save to cache.
	 *
	 * @param string $type (plugin|theme).
	 *
	 * @return \stdClass
	 */
	protected function get_repo_api_data( $type ) {
		$file         = 'plugin' === $type ? $this->file : $this->slug;
		$version_info = $this->get_cached_version_info();

		if ( ! $version_info ) {
			$version_info = $this->api_request(
				[
					'edd_action' => 'get_version',
					'license'    => $this->license,
					'name'       => $this->item_name,
					'slug'       => $this->slug,
					'version'    => $this->version,
					'author'     => $this->author,
					'beta'       => $this->beta,
				]
			);
		}

		if ( ! \is_object( $version_info ) ) {
			return false;
		}

		$version_info = $this->convert_sections_to_array( $version_info );
		$this->set_version_info_cache( $version_info );

		// Make sure the plugin|theme property is set.
		$version_info->{$type} = $file;

		// Add for auto update link, WP 5.5.
		$version_info->{'update-available'} = true;

		return $version_info;
	}

	/**
	 * Check for Updates at the defined API endpoint and modify the update transient.
	 *
	 * // TODO: figure out what to do with $this->wp_override.
	 *
	 * @param  array $transient Update transient.
	 * @return array Modified update transienet with custom data.
	 */
	public function update_transient( $transient ) {
		// needed to fix PHP 7.4 warning.
		if ( ! \is_object( $transient ) ) {
			$transient           = new \stdClass();
			$transient->response = null;
		} elseif ( ! \property_exists( $transient, 'response' ) ) {
			$transient->response = null;
		}

		$type    = $this instanceof Plugin_Updater ? 'plugin' : null;
		$type    = $this instanceof Theme_Updater ? 'theme' : $type;
		$file    = 'plugin' === $type ? $this->file : $this->slug;
		$current = $this->get_repo_api_data( $type );

		if ( ! $current ) {
			return $transient;
		}

		// If there is no valid license key status, don't allow updates.
		if ( version_compare( $this->version, $current->new_version, '<' )
			&& 'valid' === get_site_option( $this->slug . '_license_key_status', false )
		) {
			$transient->response[ $file ] = 'plugin' === $type ? $current : (array) $current;
		} else {
			$transient->no_update[ $file ] = 'plugin' === $type ? $current : (array) $current;
		}

		return $transient;
	}
}
