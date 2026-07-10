<?php
namespace PPSFW;

/**
 * All access to the custom responses table.
 */
class ResponseRepository {

	/**
	 * An instance of this class.
	 *
	 * @var null|ResponseRepository
	 */
	private static $instance = null;

	/**
	 * Get or create an instance.
	 *
	 * @return ResponseRepository
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * The full (prefixed) responses table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;

		return $wpdb->prefix . Plugin::responses_table();
	}

	/**
	 * Get a response row for an order + question.
	 *
	 * @param int $order_id The order ID.
	 * @param int $question_id The question ID.
	 *
	 * @return object|null
	 */
	public function get_by_order( $order_id, $question_id ) {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; name is built from $wpdb->prefix and a constant.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d AND question_id = %d", absint( $order_id ), absint( $question_id ) ) );
	}

	/**
	 * Whether a response exists for an order + question.
	 *
	 * @param int $order_id The order ID.
	 * @param int $question_id The question ID.
	 *
	 * @return bool
	 */
	public function exists( $order_id, $question_id ) {
		return $this->get_by_order( $order_id, $question_id ) !== null;
	}

	/**
	 * Insert a response.
	 *
	 * By default the insert is guarded so at most one response row exists per
	 * (order, question): a single atomic INSERT ... SELECT ... WHERE NOT EXISTS
	 * statement, so concurrent double submissions cannot both save. Pro
	 * multi-choice questions pass $one_per_question = false to store several
	 * answers per question; the unique key on (order_id, question_id,
	 * answer_value) still blocks exact-duplicate answers at the database level.
	 *
	 * @param array $data Response data.
	 * @param bool  $one_per_question Whether to reject the insert when the order already has a response for this question.
	 *
	 * @return int|false The new row ID or false on failure/duplicate.
	 */
	public function insert( $data, $one_per_question = true ) {
		global $wpdb;

		$data = wp_parse_args(
			$data,
			array(
				'order_id'     => 0,
				'question_id'  => 0,
				'answer_value' => '',
				'answer_label' => '',
				'is_other'     => 0,
				'other_text'   => null,
				'created_at'   => Util::now_utc(),
			)
		);

		if ( ! $data['order_id'] || ! $data['question_id'] || $data['answer_value'] === '' ) {
			return false;
		}

		$table = self::table();

		$values = array(
			absint( $data['order_id'] ),
			absint( $data['question_id'] ),
			(string) $data['answer_value'],
			(string) $data['answer_label'],
			$data['is_other'] ? 1 : 0,
			$data['other_text'],
			(string) $data['created_at'],
		);

		$suppress = $wpdb->suppress_errors( true );

		if ( $one_per_question ) {
			/*
			 * other_text is nullable; $wpdb->prepare() would coerce null to '',
			 * so the placeholder becomes a NULL literal when unset.
			 */
			$args = $values;

			if ( $data['other_text'] === null ) {
				$other_placeholder = 'NULL';
				unset( $args[5] );
				$args = array_values( $args );
			} else {
				$other_placeholder = '%s';
			}

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; name is built from $wpdb->prefix and a constant, the only other interpolation is a hardcoded NULL literal or %s placeholder, and the replacement count matches the placeholders at runtime.
			$result = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$table} (order_id, question_id, answer_value, answer_label, is_other, other_text, created_at)
					SELECT %d, %d, %s, %s, %d, {$other_placeholder}, %s FROM DUAL
					WHERE NOT EXISTS ( SELECT 1 FROM {$table} WHERE order_id = %d AND question_id = %d )",
					array_merge( $args, array( absint( $data['order_id'] ), absint( $data['question_id'] ) ) )
				)
			);
			// phpcs:enable
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.
			$result = $wpdb->insert(
				$table,
				array(
					'order_id'     => $values[0],
					'question_id'  => $values[1],
					'answer_value' => $values[2],
					'answer_label' => $values[3],
					'is_other'     => $values[4],
					'other_text'   => $values[5],
					'created_at'   => $values[6],
				),
				array( '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
			);
		}

		$wpdb->suppress_errors( $suppress );

		if ( ! $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Aggregate response counts per answer for a date range.
	 * A single grouped query; no per-row loops.
	 *
	 * @param null|string $from_utc UTC datetime string (inclusive) or null.
	 * @param null|string $to_utc UTC datetime string (inclusive) or null.
	 * @param int         $question_id The question (post) ID, or 0 for all questions.
	 *
	 * @return array
	 */
	public function counts( $from_utc = null, $to_utc = null, $question_id = 0 ) {
		global $wpdb;

		$table = self::table();

		$sql  = "SELECT answer_value, MAX(answer_label) AS answer_label, MAX(is_other) AS is_other, COUNT(*) AS total FROM {$table} WHERE question_id >= %d";
		$args = array( 0 );

		if ( $question_id ) {
			$sql  = "SELECT answer_value, MAX(answer_label) AS answer_label, MAX(is_other) AS is_other, COUNT(*) AS total FROM {$table} WHERE question_id = %d";
			$args = array( absint( $question_id ) );
		}

		if ( $from_utc ) {
			$sql   .= ' AND created_at >= %s';
			$args[] = $from_utc;
		}

		if ( $to_utc ) {
			$sql   .= ' AND created_at <= %s';
			$args[] = $to_utc;
		}

		$sql .= ' GROUP BY answer_value ORDER BY total DESC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; query is prepared above.
		return $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
	}

	/**
	 * Total responses for a date range.
	 *
	 * @param null|string $from_utc UTC datetime string (inclusive) or null.
	 * @param null|string $to_utc UTC datetime string (inclusive) or null.
	 * @param int         $question_id The question (post) ID, or 0 for all questions.
	 *
	 * @return int
	 */
	public function total( $from_utc = null, $to_utc = null, $question_id = 0 ) {
		global $wpdb;

		$table = self::table();

		$sql  = "SELECT COUNT(*) FROM {$table} WHERE question_id >= %d";
		$args = array( 0 );

		if ( $question_id ) {
			$sql  = "SELECT COUNT(*) FROM {$table} WHERE question_id = %d";
			$args = array( absint( $question_id ) );
		}

		if ( $from_utc ) {
			$sql   .= ' AND created_at >= %s';
			$args[] = $from_utc;
		}

		if ( $to_utc ) {
			$sql   .= ' AND created_at <= %s';
			$args[] = $to_utc;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; query is prepared above.
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
	}

	/**
	 * The distinct question IDs that have at least one response.
	 * Used by the reports question filter.
	 *
	 * @return array
	 */
	public function question_ids_with_responses() {
		global $wpdb;

		$table = self::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; name is built from $wpdb->prefix and a constant.
		return array_map( 'absint', $wpdb->get_col( "SELECT DISTINCT question_id FROM {$table} ORDER BY question_id DESC" ) );
	}

	/**
	 * All response rows for a set of order IDs (for the privacy exporter).
	 *
	 * @param array $order_ids Order IDs.
	 *
	 * @return array
	 */
	public function get_by_order_ids( $order_ids ) {
		global $wpdb;

		$order_ids = array_filter( array_map( 'absint', Util::arrayify( $order_ids, true ) ) );

		if ( empty( $order_ids ) ) {
			return array();
		}

		$table        = self::table();
		$placeholders = implode( ',', array_fill( 0, count( $order_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table; placeholders are built above and prepared here.
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id IN ({$placeholders}) ORDER BY created_at ASC", $order_ids ) );
	}

	/**
	 * All response rows for one order (for the order admin screen).
	 *
	 * @param int $order_id The order ID.
	 *
	 * @return array
	 */
	public function rows_by_order( $order_id ) {
		return $this->get_by_order_ids( array( absint( $order_id ) ) );
	}

	/**
	 * Delete all responses for a question.
	 *
	 * @param int $question_id The question (post) ID.
	 *
	 * @return int Rows deleted.
	 */
	public function delete_by_question( $question_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$deleted = $wpdb->delete( self::table(), array( 'question_id' => absint( $question_id ) ), array( '%d' ) );

		return $deleted ? (int) $deleted : 0;
	}

	/**
	 * Delete all responses for an order (for the privacy eraser).
	 *
	 * @param int $order_id The order ID.
	 *
	 * @return int Rows deleted.
	 */
	public function delete_by_order( $order_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$deleted = $wpdb->delete( self::table(), array( 'order_id' => absint( $order_id ) ), array( '%d' ) );

		return $deleted ? (int) $deleted : 0;
	}
}
