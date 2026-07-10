<?php
/**
 * Uninstall Post-Purchase Survey for WooCommerce.
 *
 * Always removes plugin options. Response data (custom table + order meta) is
 * only removed when the store owner opted in via the "delete all plugin data
 * on uninstall" setting.
 *
 * @package Post-Purchase Survey for WooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

$ppsfw_settings    = get_option( 'ppsfw_settings' );
$ppsfw_delete_data = is_array( $ppsfw_settings ) && ! empty( $ppsfw_settings['delete_data'] );

/**
 * Options.
 */
$ppsfw_options = array(
	'ppsfw_settings',
	'ppsfw_survey',
	'ppsfw_version',
	'ppsfw_db_version',
	'ppsfw_seeded',
	'worb_post-purchase-survey-for-woocommerce_check',
	'worb_post-purchase-survey-for-woocommerce_nobug',
);

foreach ( $ppsfw_options as $ppsfw_option ) {
	delete_option( $ppsfw_option );
}

delete_metadata( 'user', 0, 'ppsfw_disabled_notice_dismissed', '', true );

/**
 * Response data and question posts (opt-in only).
 */
if ( $ppsfw_delete_data ) {
	global $wpdb;

	$ppsfw_question_ids = get_posts(
		array(
			'post_type'      => 'ppsfw_question',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $ppsfw_question_ids as $ppsfw_question_id ) {
		wp_delete_post( $ppsfw_question_id, true );
	}

	$ppsfw_meta_keys = array( '_ppsfw_answer', '_ppsfw_answer_value', '_ppsfw_other_text' );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uninstall cleanup of this plugin's own table and meta; table names are built from $wpdb->prefix and constants, placeholders are prepared.

	$ppsfw_table = $wpdb->prefix . 'ppsfw_responses';
	$wpdb->query( "DROP TABLE IF EXISTS {$ppsfw_table}" );

	$ppsfw_meta_placeholders = implode( ',', array_fill( 0, count( $ppsfw_meta_keys ), '%s' ) );

	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ({$ppsfw_meta_placeholders})", $ppsfw_meta_keys ) );

	/**
	 * HPOS order meta table, if it exists.
	 */
	$ppsfw_hpos_meta_table = $wpdb->prefix . 'wc_orders_meta';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $ppsfw_hpos_meta_table ) ) ) === $ppsfw_hpos_meta_table ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$ppsfw_hpos_meta_table} WHERE meta_key IN ({$ppsfw_meta_placeholders})", $ppsfw_meta_keys ) );
	}

	// phpcs:enable
}
