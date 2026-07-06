<?php
namespace PPS;

/**
 * Class for handling various tasks during activation, updating, etc.
 */
class Install {

	/**
	 * The current database schema version.
	 * Bump this when the responses table schema changes.
	 *
	 * @return string
	 */
	public static function db_version() {
		return '1.2.0';
	}

	/**
	 * Fired on plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		self::maybe_seed_example_question();
		self::maybe_update();
	}

	/**
	 * Seed a draft example question on first activation.
	 * It ships as a draft so store owners review it before anything is shown
	 * to customers; the survey itself is also disabled by default.
	 *
	 * @return void
	 */
	public static function maybe_seed_example_question() {
		if ( Options::instance()->get( 'seeded', null, true ) ) {
			return;
		}

		/*
		 * The activation request runs after init, so the post type isn't
		 * registered yet. Registration is idempotent.
		 */
		if ( ! post_type_exists( Plugin::posttype_question() ) ) {
			( new PostTypes() )->register_post_types();
		}

		$existing = get_posts(
			array(
				'post_type'      => Plugin::posttype_question(),
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( empty( $existing ) ) {
			$question_id = wp_insert_post(
				array(
					'post_type'   => Plugin::posttype_question(),
					'post_status' => 'draft',
					'post_title'  => Survey::default_question_text(),
				)
			);

			if ( $question_id && ! is_wp_error( $question_id ) ) {
				update_post_meta( $question_id, Plugin::meta_key_answers(), Survey::default_answer_options() );
				update_post_meta( $question_id, Plugin::meta_key_question_other(), 1 );
				update_post_meta( $question_id, Plugin::meta_key_question_other_label(), Survey::default_other_label() );
			}
		}

		Options::instance()->update( 'seeded', 1 );
	}

	/**
	 * Set the plugin version in the database to the current version.
	 *
	 * @return void
	 */
	public static function set_dbversion() {
		Options::instance()->update( 'version', Plugin::version() );
	}

	/**
	 * If the database version doesn't match the current version, run updates.
	 *
	 * @return void
	 */
	public static function maybe_update() {
		if ( wp_doing_ajax() ) {
			return;
		}

		$version = Util::get_dbversion();

		if ( $version !== Plugin::version() ) {
			self::update();
		}

		if ( Util::get_dbversion( 'db_version' ) !== self::db_version() ) {
			self::create_tables();
		}

		if ( is_admin() && ! self::table_exists() ) {
			self::create_tables();
		}
	}

	/**
	 * Tasks to run during a version update.
	 *
	 * @return void
	 */
	public static function update() {
		self::set_dbversion();
	}

	/**
	 * Check that the responses table exists.
	 *
	 * @return bool
	 */
	public static function table_exists() {
		global $wpdb;

		$table_name = ResponseRepository::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query ok and do not want caching.
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) ) === $table_name;
	}

	/**
	 * Create or update the responses table with dbDelta.
	 * dbDelta is idempotent, so this is safe to run on every activation or upgrade.
	 *
	 * The unique key allows multiple rows per (order, question) so Pro
	 * multi-choice questions never need a schema change; "one response per
	 * order per question" is enforced by ResponseRepository::insert().
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		$table_name      = ResponseRepository::table();
		$charset_collate = $wpdb->get_charset_collate();

		self::drop_legacy_indexes();

		$sql = "CREATE TABLE $table_name (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT(20) UNSIGNED NOT NULL,
			question_id INT(11) UNSIGNED NOT NULL DEFAULT 1,
			answer_value VARCHAR(191) NOT NULL,
			answer_label TEXT NOT NULL,
			is_other TINYINT(1) NOT NULL DEFAULT 0,
			other_text TEXT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_question_answer (order_id,question_id,answer_value),
			KEY created_at (created_at)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		Options::instance()->update( 'db_version', self::db_version() );
	}

	/**
	 * Drop indexes from earlier schema versions that dbDelta will not remove.
	 * The original order_question unique key blocked multiple rows per
	 * (order, question), which Pro multi-choice questions need.
	 *
	 * @return void
	 */
	private static function drop_legacy_indexes() {
		global $wpdb;

		if ( ! self::table_exists() ) {
			return;
		}

		$table_name = ResponseRepository::table();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Idempotent schema migration on this plugin's own table; name is built from $wpdb->prefix and a constant.
		$legacy = $wpdb->get_var( $wpdb->prepare( "SHOW INDEX FROM {$table_name} WHERE Key_name = %s", 'order_question' ) );

		if ( $legacy !== null ) {
			$wpdb->query( "ALTER TABLE {$table_name} DROP INDEX order_question" );
		}
		// phpcs:enable
	}
}
