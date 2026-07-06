<?php
namespace PPS;

use PPS\Vendor\WOAdminFramework\WOOptions;

/**
 * Class for making handling options easier.
 * Interfaces with WOOptions framework.
 */
class Options {
	/**
	 * WOOptions instance.
	 *
	 * @var WOOptions
	 */
	private $optf;

	/**
	 * An instance of this class.
	 *
	 * @var null|Options
	 */
	private static $instance = null;

	/**
	 * __construct
	 */
	public function __construct() {
		$this->optf = new WOOptions( Plugin::ns() );
	}

	/**
	 * Get or create an instance.
	 *
	 * @return Options
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get an option and process it if necessary.
	 *
	 * @param string      $option The option name.
	 * @param null|string $group The group name, if necessary.
	 * @param bool        $truthy Whether to perform truthy conversion on the option value.
	 * @param mixed       $default_value Optional default value.
	 * @param bool        $trim Optionally trim the value.
	 *
	 * @return mixed
	 */
	public function get( $option, $group = null, $truthy = false, $default_value = null, $trim = true ) {

		$value = $this->optf->get( $option, $group, $default_value );

		if ( $trim && $value && is_string( $value ) ) {
			$value = trim( $value );
		}

		if ( $truthy ) {
			return Util::truthy( $value );
		}

		return $value;
	}

	/**
	 * Get an entire option group.
	 *
	 * @param string $group The group name.
	 * @param mixed  $default_value Optional default value.
	 *
	 * @return mixed
	 */
	public function get_group( $group, $default_value = array() ) {
		return $this->optf->get_group( $group, $default_value );
	}

	/**
	 * Delete an option by key.
	 * Keys are converted into namespaced keys by WOOptions.
	 *
	 * @param string $key The key to delete.
	 *
	 * @return void
	 */
	public function delete( $key ) {
		$this->optf->delete( $key );
	}

	/**
	 * Update an option by key and value.
	 * Keys are converted into namespaced keys by WOOptions.
	 *
	 * @param string $key The key to update.
	 * @param mixed  $value The value to update with.
	 * @param bool   $refresh Whether or not to refresh the cached options.
	 *
	 * @return void
	 */
	public function update( $key, $value, $refresh = true ) {
		$this->optf->update( $key, $value, $refresh );
	}

	/**
	 * Update a single option in a group.
	 *
	 * @param string $key The key to update.
	 * @param mixed  $value The value to update with.
	 * @param string $group The group name.
	 *
	 * @return void
	 */
	public function update_one( $key, $value, $group ) {
		$group_values = $this->optf->get_group( $group );

		$group_values[ $key ] = $value;

		$this->update( $group, $group_values, true );
	}
}
