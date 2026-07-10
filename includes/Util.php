<?php
namespace PPSFW;

use PPSFW\Vendor\WOAdminFramework\WOUtilities;

/**
 * Misc helper classes used throughout this plugin.
 * Also bridges some vendor frameworks, so we don't have to interface with those in other classes.
 */
class Util {

	/**
	 * Get the version of the plugin from the database.
	 *
	 * @param string $key The option key for the plugin version.
	 *
	 * @return string
	 */
	public static function get_dbversion( $key = 'version' ) {
		$version = Options::instance()->get( $key );

		if ( ! $version || is_array( $version ) ) {
			return '';
		}

		return $version;
	}

	/**
	 * Converts a string into the correct bool.
	 * Interfaces with WOUtilities::truthy
	 *
	 * @param mixed $value Any string or bool.
	 *
	 * @return bool
	 */
	public static function truthy( $value ) {
		return WOUtilities::truthy( $value );
	}

	/**
	 * If a variable is not an array, bool, or WP_Error, make it an array. Interfaces with WOUtilities.
	 *
	 * @param mixed $arr Variable to check and convert to array.
	 * @param bool  $force Force an array return in some cases.
	 *
	 * @return mixed
	 */
	public static function arrayify( $arr, $force = false ) {
		return WOUtilities::arrayify( $arr, $force );
	}

	/**
	 * Create a prefixed string for use throughout plugin to avoid conflicts.
	 *
	 * @param string $str The string to prefix.
	 * @param string $sep The seperator.
	 * @param string $ns The prefix.
	 *
	 * @return string
	 */
	public static function ns( $str, $sep = '-', $ns = null ) {
		if ( ! $ns ) {
			$ns = Plugin::ns();
		}

		return $ns . $sep . $str;
	}

	/**
	 * Add javascript data variable for an enqueued script.
	 *
	 * @param string $handle The script handle.
	 * @param array  $data The data to add.
	 * @param string $jsvar The javascript object.
	 * @param string $position Before or after the enqueued script.
	 *
	 * @return void
	 */
	public static function enqueue_script_data( $handle, $data, $jsvar = null, $position = 'before' ) {
		$data = wp_json_encode( $data );

		if ( $data ) {
			if ( ! $jsvar ) {
				$jsvar = str_replace( '-', '_', $handle );
			}

			$jsvar = trim( wp_strip_all_tags( $jsvar ) );

			$script = 'var ' . $jsvar . ' = ' . $data . ';';
			wp_add_inline_script( $handle, $script, $position );
		}
	}

	/**
	 * Current time as a UTC MySQL datetime string.
	 *
	 * @return string
	 */
	public static function now_utc() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/**
	 * Convert a site-timezone date string (Y-m-d) to a UTC MySQL datetime for range queries.
	 *
	 * @param string $date A Y-m-d date string in the site timezone.
	 * @param bool   $end_of_day Whether to use the end of the day instead of the start.
	 *
	 * @return string|null
	 */
	public static function local_date_to_utc( $date, $end_of_day = false ) {
		if ( ! $date ) {
			return null;
		}

		$time = $end_of_day ? '23:59:59' : '00:00:00';

		try {
			$datetime = new \DateTime( $date . ' ' . $time, wp_timezone() );
		} catch ( \Exception $e ) {
			return null;
		}

		$datetime->setTimezone( new \DateTimeZone( 'UTC' ) );

		return $datetime->format( 'Y-m-d H:i:s' );
	}
}
