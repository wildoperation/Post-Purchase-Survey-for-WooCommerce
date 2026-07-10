<?php
namespace PPSFW\Vendor\WOAdminFramework;

/**
 * General utility class for use by this framework and plugins.
 */
class WOUtilities {

	/**
	 * The current  version of this framework.
	 *
	 * @return string
	 */
	public static function version() {
		return '1.0.0';
	}

	/**
	 * array_list_list is a native PHP function starting in 8.1, but we're supporting < 8.1.
	 *
	 * @return bool
	 */
	public static function array_is_list( $arr ) {
		if ( $arr === array() ) {
			return true;
		}

		return array_keys( $arr ) === range( 0, count( $arr ) - 1 );
	}

	/**
	 * Determine if $value should be treated as a boolean true or false.
	 *
	 * @param mixed $value The value to check.
	 *
	 * @return bool
	 */
	public static function truthy( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_int( $value ) ) {
			return $value > 0 ? true : false;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( 'yes', 'y', 'true', '1' ) );
		}

		return false;
	}

	/**
	 * If a variable is not an array, bool, or WP_Error, make it an array.
	 *
	 * @param mixed $arr Variable to check and convert to array.
	 * @param bool  $force Force an array return in some cases.
	 *
	 * @return mixed
	 */
	public static function arrayify( $arr, $force = false ) {
		/**
		 * If $arr isn't already an array...
		 */
		if ( ! is_array( $arr ) ) {
			if ( ! $force ) {
				/**
				 * If we have a boolean or a WP_Errror, it will be returned in same format.
				 * Otherwise, create an array.
				 * We would only want to use $force mode if we are expecting an array return no matter what.
				 */
				if ( ! is_bool( $arr ) && ! is_wp_error( $arr ) ) {
					$arr = array( $arr );
				}
			} elseif ( ( $arr === false || is_wp_error( $arr ) ) && ! $force ) {
				/**
				 * If we have FALSE or WP_Error, return empty array.
				 */
				$arr = array();
			} else {
				/**
				 * This could be an array with TRUE inside or any other value.
				 */
				$arr = array( $arr );
			}
		}

		return $arr;
	}

	/**
	 * Sanitize an array of expected integers.
	 *
	 * @param array|string $values The values to sanitize.
	 * @param bool         $force_array_return If values are provided but are not an array, return them in an array.
	 *
	 * @return array|int|null
	 */
	public static function sanitize_int_array( $values, $force_array_return = false ) {
		if ( ! $values ) {
			return null;
		}

		if ( is_array( $values ) ) {
			$values = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $values ) );
		} elseif ( strpos( $values, ',' ) !== false ) {
			$values = explode( ',', sanitize_text_field( wp_unslash( $values ) ) );
		}

		if ( ! is_array( $values ) ) {
			$values = intval( $values );

			if ( $force_array_return ) {
				$values = array( $values );
			}

			return $values;
		}

		return array_map( 'intval', $values );
	}

	/**
	 * Sanitizes integers and strings.
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public static function do_sanitize_mixed_input( $value ) {

		$value = sanitize_text_field( $value );

		if ( is_numeric( $value ) ) {
			if ( strpos( $value, '.' ) === false ) {
				$value = intval( $value );
			} else {
				$value = floatval( $value );
			}
		}

		return $value;
	}

	/**
	 * Sanitize an array or individual value of unknown type.
	 *
	 * @param mixed $values The values to sanitize.
	 *
	 * @return string}int
	 */
	public static function sanitize_mixed_input( $values ) {
		if ( ! $values ) {
			return null;
		}

		$processed_values = null;

		if ( is_array( $values ) ) {
			$processed_values = array();
			foreach ( $values as $value ) {
				$processed_values[] = self::do_sanitize_mixed_input( $value );
			}
		} else {
			$processed_values = self::do_sanitize_mixed_input( $values );
		}

		return $processed_values;
	}
}
