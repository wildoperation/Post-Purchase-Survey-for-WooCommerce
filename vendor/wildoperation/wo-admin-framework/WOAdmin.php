<?php
namespace PPSFW\Vendor\WOAdminFramework;

/**
 * General admin helper classes to be extended by a plugin's Admin class.
 */
class WOAdmin {
	/**
	 * The URL to the assets folder within this framework.
	 *
	 * @var string
	 */
	protected $assets_url;

	/**
	 * Set and return the assets_url
	 *
	 * @return string
	 */
	public function assets_url() {
		if ( ! $this->assets_url ) {
			$this->assets_url = plugin_dir_url( __FILE__ ) . 'dist/';
		}

		return $this->assets_url;
	}

	/**
	 * Enqueue styles from this framework.
	 *
	 * @return void
	 */
	public function enqueue_woadmin_styles( $extras = array() ) {
		if ( ! empty( $extras ) ) {
			foreach ( $extras as $extra ) {
				wp_enqueue_style( $extra );
			}
		}

		wp_enqueue_style( 'woadmin', $this->assets_url() . 'css/admin.css', $extras, WOUtilities::version() );
	}

	/**
	 * Enqueue woadmin scripts.
	 * Current $media is optional, but it's also the only script used.
	 *
	 * @param array $scripts The scripts to enqueue.
	 *
	 * @return void
	 */
	public function enqueue_woadmin_scripts( $scripts = array() ) {
		if ( in_array( 'media', $scripts, true ) ) {
			wp_enqueue_media();
			wp_register_script( 'woadmin-media', $this->assets_url() . 'js/media.js', array( 'jquery' ) );
			wp_enqueue_script( 'woadmin-media' );
		}

		if ( in_array( 'color-picker', $scripts, true ) ) {
			wp_enqueue_script( 'wp-color-picker' );
			wp_register_script( 'woadmin-color-picker', $this->assets_url() . 'js/color-picker.js', array( 'jquery', 'wp-color-picker' ) );
			wp_enqueue_script( 'woadmin-color-picker' );
		}
	}

	/**
	 * Helper function for authorizing ajax actions.
	 * Checks the nonce and the capability.
	 * Optionally, looks for required data in $_REQUEST before allowing.
	 *
	 * @param string $action_nonce The action.
	 * @param string $nonce_key The nonce key.
	 * @param string $capability The capability to check the current user against.
	 * @param array  $required_data Optional required data.
	 *
	 * @return void
	 */
	public function authorize_ajax_action( $action_nonce, $nonce_key, $capability, $required_data = array() ) {
		check_ajax_referer( $action_nonce, $nonce_key );

		if ( ! current_user_can( $capability ) ) {
			wp_die();
		}

		if ( $required_data ) {
			$required_data = WOUtilities::arrayify( $required_data );

			foreach ( $required_data as $required ) {
				if ( ! isset( $_REQUEST[ $required ] ) ) {
					wp_die();
				}
			}
		}
	}

	/**
	 * Sanitize input by type.
	 *
	 * @param mixed  $input The input to sanitize.
	 * @param string $type The type to sanitize against.
	 *
	 * @return mixed
	 */
	public static function sanitize_by_type( $input, $type = 'str' ) {
		if ( is_array( $input ) ) {
			$value    = array();
			$is_assoc = false;

			/**
			 * If we have a mixed type (array, sometimes not an array, text, number, etc), detect if it's assoc.
			 * Otherwise, check to see if we expect an assoc array.
			 */
			if ( $type === 'mixed' && ! WOUtilities::array_is_list( $input ) ) {
				$is_assoc = true;
			} elseif ( strtolower( substr( $type, 0, 5 ) ) === 'assoc' ) {
				$is_assoc = true;
				$type     = explode( '_', $type )[1];
			}

			foreach ( $input as $key => $i ) {
				if ( empty( $i ) ) {
					continue;
				}

				if ( $is_assoc ) {
					$value[ $key ] = self::do_sanitize_by_type( $i, $type );
				} else {
					$value[] = self::do_sanitize_by_type( $i, $type );
				}
			}
		} else {
			$value = self::do_sanitize_by_type( $input, $type );
		}

		return $value;
	}

	/**
	 * Execute sanitize input by type.
	 *
	 * @param mixed  $input The input to sanitize.
	 * @param string $type The type to sanitize against.
	 *
	 * @return mixed
	 */
	private static function do_sanitize_by_type( $input, $type ) {
		$type = strtolower( $type );

		switch ( $type ) {
			case 'date':
				$input  = sanitize_text_field( $input );
				$format = 'F j, Y';
				$date   = \DateTime::createFromFormat( $format, $input );

				if ( $date && $date->format( $format ) ) {
					$value = $date->format( $format );
				} else {
					$value = null;
				}
				break;

			case 'boolean':
			case 'bool':
				$value = intval( wp_strip_all_tags( $input ) ) > 0 ? 1 : 0;
				break;

			case 'integer':
			case 'integers':
			case 'int':
			case 'ints':
			case 'timestamp':
			case 'timestamps':
				if ( ( $type === 'ints' || $type === 'integers' || $type === 'timestamps' ) && ( is_string( $input ) && strpos( $input, ',' ) !== false ) ) {
					$input = explode( ',', $input );
				}

				if ( is_array( $input ) || ( $input === null && ( $type === 'ints' || $type === 'integers' || $type === 'timestamps' ) ) ) {
					$value = WOUtilities::sanitize_int_array( $input, true );
				} else {
					$value = ( ! $input || $input === null ) ? null : intval( wp_strip_all_tags( $input ) );
				}
				break;

			case 'url':
				$value = sanitize_url( wp_strip_all_tags( $input ) );
				break;

			case 'textarea':
				$value = sanitize_textarea_field( $input );
				break;

			case 'richcontent':
			case 'editor':
				$value = ( self::allow_unfiltered_html() ) ? $input : wp_kses_post( $input );
				break;

			case 'mixed':
				$value = WOUtilities::sanitize_mixed_input( $input );
				break;

			case 'str':
			default:
				$value = sanitize_text_field( $input );
				break;
		}

		return $value;
	}

	/**
	 * Check if unfiltered HTML is allowed.
	 *
	 * @return bool
	 */
	public static function allow_unfiltered_html() {
		if ( defined( 'DISALLOW_UNFILTERED_HTML' ) && DISALLOW_UNFILTERED_HTML ) {
			return false;
		}

		if ( ! current_user_can( 'unfiltered_html' ) ) {
			return false;
		}

		return true;
	}
}
