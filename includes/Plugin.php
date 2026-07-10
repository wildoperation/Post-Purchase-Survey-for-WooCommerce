<?php
namespace PPSFW;

/**
 * Misc data used throughout this plugin.
 */
class Plugin {

	/**
	 * The plugin version.
	 *
	 * @return string
	 */
	public static function version() {
		return '1.0.1';
	}

	/**
	 * The plugin title.
	 *
	 * @return string
	 */
	public static function title() {
		return __( 'Post-Purchase Survey', 'post-purchase-survey-for-woocommerce' );
	}

	/**
	 * The menu title.
	 *
	 * @return string
	 */
	public static function menu_title() {
		return self::title();
	}

	/**
	 * The plugin namespace.
	 *
	 * @return string
	 */
	public static function ns() {
		return 'ppsfw';
	}

	/**
	 * The path to the assets directory.
	 *
	 * @return string
	 */
	public static function assets_path() {
		return PPSFW_PLUGIN_DIR . 'dist/';
	}

	/**
	 * The URL to the assets directory.
	 *
	 * @return string
	 */
	public static function assets_url() {
		return PPSFW_PLUGIN_URL . 'dist/';
	}

	/**
	 * The cache-busting version for an enqueued asset.
	 * Uses the file modification time during development (WP_DEBUG) so
	 * rebuilt assets are never served from a stale browser cache.
	 *
	 * @param string $relative The asset path relative to the dist directory.
	 *
	 * @return string
	 */
	public static function asset_version( $relative ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$path = self::assets_path() . ltrim( $relative, '/' );

			if ( file_exists( $path ) ) {
				return (string) filemtime( $path );
			}
		}

		return self::version();
	}

	/**
	 * The required capability for managing this plugin.
	 *
	 * @return string
	 */
	public static function capability() {
		return 'manage_woocommerce';
	}

	/**
	 * The namespaced post type for survey questions.
	 *
	 * @return string
	 */
	public static function posttype_question() {
		return self::ns() . '_question';
	}

	/**
	 * The post meta key holding a question's answer options.
	 *
	 * @return string
	 */
	public static function meta_key_answers() {
		return '_' . self::ns() . '_answers';
	}

	/**
	 * The post meta key holding whether a question offers the "Other" option.
	 *
	 * @return string
	 */
	public static function meta_key_question_other() {
		return '_' . self::ns() . '_other';
	}

	/**
	 * The post meta key holding a question's "Other" option label.
	 *
	 * @return string
	 */
	public static function meta_key_question_other_label() {
		return '_' . self::ns() . '_other_label';
	}

	/**
	 * The base name (without prefix) of the responses table.
	 *
	 * @return string
	 */
	public static function responses_table() {
		return self::ns() . '_responses';
	}

	/**
	 * The order meta key holding the answer label.
	 *
	 * @return string
	 */
	public static function meta_key_answer() {
		return '_' . self::ns() . '_answer';
	}

	/**
	 * The order meta key holding the stable answer value.
	 *
	 * @return string
	 */
	public static function meta_key_answer_value() {
		return '_' . self::ns() . '_answer_value';
	}

	/**
	 * The order meta key holding free-text entered for the "Other" option.
	 *
	 * @return string
	 */
	public static function meta_key_other_text() {
		return '_' . self::ns() . '_other_text';
	}

	/**
	 * The reserved answer value used by the "Other" option.
	 *
	 * @return string
	 */
	public static function other_value() {
		return 'other';
	}

	/**
	 * The URL to the plugin support page.
	 *
	 * @return string
	 */
	public static function support_url() {
		return 'https://wordpress.org/support/plugin/post-purchase-survey-for-woocommerce/';
	}
}
