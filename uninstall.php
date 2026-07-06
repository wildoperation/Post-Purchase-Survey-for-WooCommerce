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

$pps_settings    = get_option( 'pps_settings' );
$pps_delete_data = is_array( $pps_settings ) && ! empty( $pps_settings['delete_data'] );

/**
 * Options.
 */
$pps_options = array(
	'pps_settings',
	'pps_survey',
	'pps_version',
	'pps_db_version',
	'pps_seeded',
	'worb_post-purchase-survey-for-woocommerce_check',
	'worb_post-purchase-survey-for-woocommerce_nobug',
);

foreach ( $pps_options as $pps_option ) {
	delete_option( $pps_option );
}

/**
 * Response data and question posts (opt-in only).
 */
if ( $pps_delete_data ) {
	global $wpdb;

	$pps_question_ids = get_posts(
		array(
			'post_type'      => 'pps_question',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		)
	);

	foreach ( $pps_question_ids as $pps_question_id ) {
		wp_delete_post( $pps_question_id, true );
	}

	$pps_meta_keys = array( '_pps_answer', '_pps_answer_value', '_pps_other_text' );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Uninstall cleanup of this plugin's own table and meta; table names are built from $wpdb->prefix and constants, placeholders are prepared.

	$pps_table = $wpdb->prefix . 'pps_responses';
	$wpdb->query( "DROP TABLE IF EXISTS {$pps_table}" );

	$pps_meta_placeholders = implode( ',', array_fill( 0, count( $pps_meta_keys ), '%s' ) );

	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ({$pps_meta_placeholders})", $pps_meta_keys ) );

	/**
	 * HPOS order meta table, if it exists.
	 */
	$pps_hpos_meta_table = $wpdb->prefix . 'wc_orders_meta';

	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $pps_hpos_meta_table ) ) ) === $pps_hpos_meta_table ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$pps_hpos_meta_table} WHERE meta_key IN ({$pps_meta_placeholders})", $pps_meta_keys ) );
	}

	// phpcs:enable
}
