<?php
namespace PPSFW\Vendor\WOAdminFramework;

/**
 * Class for working with namespaced options in your plugin.
 */
class WOOptions {

	/**
	 * An array of current options.
	 *
	 * @var array
	 */
	protected $options;

	/**
	 * The ns of your plugin.
	 *
	 * @var string
	 */
	protected $ns;

	/**
	 * __construct()
	 *
	 * @param string $ns The ns for your options.
	 */
	public function __construct( $ns ) {
		$this->ns      = $ns;
		$this->options = array();
	}

	/**
	 * Create a prefix using the current namespace.
	 *
	 * @return string
	 */
	protected function ns() {
		return $this->ns . '_';
	}

	/**
	 * Create an option key using the ns and a string.
	 *
	 * @param string $key The key to append to the ns.
	 *
	 * @return string
	 */
	public function key( $key ) {
		return $this->ns() . $key;
	}

	/**
	 * Refresh cached option from the database.
	 *
	 * @param string $key The non-namespaced key to refresh.
	 * @param array  $default_value The default value if option is not found.
	 *
	 * @return void
	 */
	public function refresh( $key, $default_value = array() ) {
		$this->options[ $key ] = get_option( $this->key( $key ), $default_value );
	}

	/**
	 * Gets an option from the database or cached option set.
	 * Pulls the option from a group if one is specified.
	 *
	 * @param string      $option The option key (or sub-option key if part of a group).
	 * @param string|null $group The option group if one is in use.
	 * @param mixed       $default_value The default value if the option is not found.
	 *
	 * @return mixed
	 */
	public function get( $option, $group = null, $default_value = null ) {

		if ( $group !== null ) {
			if ( ! isset( $this->options[ $group ] ) ) {
				$this->refresh( $group );
			}

			if ( isset( $this->options[ $group ][ $option ] ) ) {
				return $this->options[ $group ][ $option ];
			}
		} else {
			if ( ! isset( $this->options[ $option ] ) ) {
				$this->refresh( $option );
			}

			if ( isset( $this->options[ $option ] ) ) {
				return $this->options[ $option ];
			}
		}

		return $default_value;
	}

	/**
	 * Get a group of options.
	 *
	 * @param string      $option The option key (or sub-option key if part of a group).
	 * @param string|null $group The option group if one is in use.
	 * @param mixed       $default_value The default value if the option is not found.
	 *
	 * @return mixed
	 */
	public function get_group( $group, $default_value = array() ) {

		if ( ! isset( $this->options[ $group ] ) ) {
			$this->refresh( $group );
		}

		if ( isset( $this->options[ $group ] ) ) {
			return $this->options[ $group ];
		}

		return $default_value;
	}

	/**
	 * Delete an option.
	 * Simply the native WP delete_option function, but allows for use of short key without namespace.
	 *
	 * @param string $key The non-namespaced key to delete.
	 *
	 * @return void
	 */
	public function delete( $key ) {
		$option_key = $this->key( $key );
		delete_option( $option_key );

		if ( isset( $this->options[ $option_key ] ) ) {
			unset( $this->options[ $option_key ] );
		}
	}

	/**
	 * Updates an option in the database. Optionally refreshes the cached data.
	 * Allows for use of short key without namespace.
	 * Note that if an option is stored in a group, this will update hte entire group with $data.
	 *
	 * @param string $key The non-namespaced key to update.
	 * @param mixed  $data The data to save.
	 * @param bool   $refresh Optionally refresh the cached data.
	 *
	 * @return void
	 */
	public function update( $key, $data, $refresh = false ) {
		$option_key = $this->key( $key );
		$update     = update_option( $option_key, $data );

		/**
		 * Remove the cached option, because the data is incorrect.
		 */
		if ( isset( $this->options[ $option_key ] ) ) {
			unset( $this->options[ $option_key ] );
		}

		if ( $refresh ) {
			$this->refresh( $key );
		}

		return $update;
	}

	/**
	 * Initialize an option if it does not already exist in the database.
	 * Allows for use of a callback to calculate the default value.
	 *
	 * @param string $key The non-namespaced key to initialize.
	 * @param mixed  $default_value The default value to save to the database.
	 *
	 * @return void
	 */
	public function initialize( $key, $default_value = array(), $autoload = 'yes' ) {
		$option_key = $this->key( $key );

		if ( false === get_option( $option_key ) ) {
			if ( ! empty( $default_value ) ) {
				foreach ( $default_value as $dkey => $value ) {
					if ( is_array( $value ) && isset( $value['callback'] ) && count( $value['callback'] ) === 2 ) {
						try {
							$class = $value['callback'][0];
							$func  = $value['callback'][1];

							$instance = new $class();
							$value    = $instance->$func();

							if ( is_bool( $value ) ) {
								$value = ( $value ) ? 1 : 0;
							}

							$default_value[ $dkey ] = $value;

						} catch ( \Exception $e ) {
							error_log( 'Caught exception: ', $e->getMessage(), "\n" );
						}
					}
				}

				add_option( $option_key, $default_value, '', $autoload );
			}
		}
	}
}
