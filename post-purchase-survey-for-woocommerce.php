<?php
/**
 * Plugin Name:       Post-Purchase Survey for WooCommerce
 * Plugin URI:        https://wildoperation.com
 * Description:       Ask customers "How did you hear about us?" on the WooCommerce thank-you page and report which channels drive your orders.
 * Version:           1.0.0
 * Author:            Wild Operation
 * Author URI:        https://wildoperation.com
 * License:           GPL-2.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       post-purchase-survey-for-woocommerce
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:   10.9
 *
 * @package WordPress
 * @subpackage Post-Purchase Survey for WooCommerce
 * @since 1.0.0
 * @version 1.0.0
 */

/* Abort! */
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'PPS_LOADED', true );
define( 'PPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PPS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Load
 */
require PPS_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Activation
 */
register_activation_hook(
	__FILE__,
	function () {
		PPS\Install::activate();
	}
);

/**
 * WooCommerce compatibility declarations.
 * This must run at the top level because before_woocommerce_init fires before plugins_loaded.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * Review request framework
 */
add_action(
	'admin_init',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		new \PPS\Vendor\WOWPRB\WPPluginReviewBug(
			__FILE__,
			'post-purchase-survey-for-woocommerce',
			array(
				'intro'            => __( 'Your reviews are invaluable to Post-Purchase Survey for WooCommerce and help us maintain a free version of this plugin. We appreciate your support! If you need assistance, please visit the support section of this plugin.', 'post-purchase-survey-for-woocommerce' ),
				'rate_link_text'   => __( 'Leave ★★★★★ rating', 'post-purchase-survey-for-woocommerce' ),
				'need_help_text'   => __( 'I need help', 'post-purchase-survey-for-woocommerce' ),
				'remind_link_text' => __( 'Remind me later', 'post-purchase-survey-for-woocommerce' ),
				'nobug_link_text'  => __( 'Don\'t ask again', 'post-purchase-survey-for-woocommerce' ),
			),
			array(
				'need_help_url' => PPS\Plugin::support_url(),
			)
		);
	},
	1
);

/**
 * Initialize; plugins_loaded
 */
add_action(
	'plugins_loaded',
	function () {
		/**
		 * WooCommerce is required. Show an admin notice and bail if it isn't active.
		 */
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				function () {
					?>
					<div class="notice notice-error">
						<p><?php esc_html_e( 'Post-Purchase Survey for WooCommerce requires WooCommerce to be installed and active.', 'post-purchase-survey-for-woocommerce' ); ?></p>
					</div>
					<?php
				}
			);

			return;
		}

		/**
		 * Has the plugin version updated?
		 */
		PPS\Install::maybe_update();

		/**
		 * Initiate classes and their hooks.
		 */
		$classes = array(
			'PPS\PostTypes',
			'PPS\Front',
			'PPS\Admin',
			'PPS\AdminSurvey',
			'PPS\AdminQuestionMeta',
			'PPS\AdminReports',
			'PPS\OrderMeta',
			'PPS\Privacy',
		);

		foreach ( $classes as $class ) {
			$instance = new $class();

			if ( method_exists( $instance, 'hooks' ) ) {
				$instance->hooks();
			}
		}
	},
	10
);
