<?php
namespace PPSFW;

/**
 * The Reports page: response counts and response rate, per question,
 * selected with a dropdown filter.
 */
class AdminReports {

	/**
	 * The available date-range presets.
	 *
	 * @return array
	 */
	public static function presets() {
		return array(
			'last1'      => __( 'Last 1 day', 'post-purchase-survey-for-woocommerce' ),
			'last7'      => __( 'Last 7 days', 'post-purchase-survey-for-woocommerce' ),
			'last30'     => __( 'Last 30 days', 'post-purchase-survey-for-woocommerce' ),
			'last90'     => __( 'Last 90 days', 'post-purchase-survey-for-woocommerce' ),
			'this_month' => __( 'This month', 'post-purchase-survey-for-woocommerce' ),
			'this_year'  => __( 'This year', 'post-purchase-survey-for-woocommerce' ),
			'all'        => __( 'All time', 'post-purchase-survey-for-woocommerce' ),
			'custom'     => __( 'Custom range', 'post-purchase-survey-for-woocommerce' ),
		);
	}

	/**
	 * Resolve a preset + optional custom dates into a date range.
	 *
	 * @param string $preset The preset key.
	 * @param string $from A Y-m-d date (custom preset only).
	 * @param string $to A Y-m-d date (custom preset only).
	 *
	 * @return array preset, from_local, to_local (Y-m-d or ''), from_utc, to_utc (datetime or null)
	 */
	public static function resolve_range( $preset, $from = '', $to = '' ) {
		if ( ! isset( self::presets()[ $preset ] ) ) {
			$preset = 'last30';
		}

		$now   = current_datetime();
		$today = $now->format( 'Y-m-d' );

		$from_local = '';
		$to_local   = '';

		switch ( $preset ) {
			case 'last1':
				$from_local = $today;
				$to_local   = $today;
				break;

			case 'last7':
				$from_local = $now->modify( '-6 days' )->format( 'Y-m-d' );
				$to_local   = $today;
				break;

			case 'last30':
				$from_local = $now->modify( '-29 days' )->format( 'Y-m-d' );
				$to_local   = $today;
				break;

			case 'last90':
				$from_local = $now->modify( '-89 days' )->format( 'Y-m-d' );
				$to_local   = $today;
				break;

			case 'this_month':
				$from_local = $now->format( 'Y-m-01' );
				$to_local   = $today;
				break;

			case 'this_year':
				$from_local = $now->format( 'Y-01-01' );
				$to_local   = $today;
				break;

			case 'custom':
				$from_local = self::validate_date( $from );
				$to_local   = self::validate_date( $to );
				break;

			case 'all':
			default:
				break;
		}

		return array(
			'preset'     => $preset,
			'from_local' => $from_local,
			'to_local'   => $to_local,
			'from_utc'   => $from_local ? Util::local_date_to_utc( $from_local ) : null,
			'to_utc'     => $to_local ? Util::local_date_to_utc( $to_local, true ) : null,
		);
	}

	/**
	 * Validate a Y-m-d date string.
	 *
	 * @param string $date The date to validate.
	 *
	 * @return string The date, or an empty string if invalid.
	 */
	private static function validate_date( $date ) {
		if ( ! $date || ! is_string( $date ) ) {
			return '';
		}

		$datetime = \DateTime::createFromFormat( 'Y-m-d', $date );

		if ( $datetime && $datetime->format( 'Y-m-d' ) === $date ) {
			return $date;
		}

		return '';
	}

	/**
	 * Read the report filters from the current request.
	 *
	 * @return array
	 */
	protected function current_range() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters; capability is checked by add_submenu_page.
		$preset = isset( $_GET['ppsfw_preset'] ) ? sanitize_key( wp_unslash( $_GET['ppsfw_preset'] ) ) : 'last30';
		$from   = isset( $_GET['ppsfw_from'] ) ? sanitize_text_field( wp_unslash( $_GET['ppsfw_from'] ) ) : '';
		$to     = isset( $_GET['ppsfw_to'] ) ? sanitize_text_field( wp_unslash( $_GET['ppsfw_to'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return self::resolve_range( $preset, $from, $to );
	}

	/**
	 * The questions available in the reports filter:
	 * the active survey questions first, then any question with responses.
	 *
	 * @return array Labels keyed by question (post) ID.
	 */
	public static function question_choices() {
		$choices = array();

		foreach ( Survey::selected_questions() as $question ) {
			$choices[ $question['id'] ] = $question['text'];
		}

		foreach ( ResponseRepository::instance()->question_ids_with_responses() as $question_id ) {
			if ( isset( $choices[ $question_id ] ) ) {
				continue;
			}

			$post = get_post( $question_id );

			if ( $post && $post->post_type === Plugin::posttype_question() ) {
				$choices[ $question_id ] = get_the_title( $post );
			} else {
				/* translators: %d: a question ID that no longer exists. */
				$choices[ $question_id ] = sprintf( __( 'Question #%d (deleted)', 'post-purchase-survey-for-woocommerce' ), $question_id );
			}
		}

		return $choices;
	}

	/**
	 * The currently selected report question.
	 *
	 * @param array $choices Question choices keyed by ID.
	 *
	 * @return int
	 */
	protected function current_question( $choices ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter; capability is checked by add_submenu_page.
		$question_id = isset( $_GET['ppsfw_question'] ) ? absint( $_GET['ppsfw_question'] ) : 0;

		if ( $question_id && isset( $choices[ $question_id ] ) ) {
			return $question_id;
		}

		return empty( $choices ) ? 0 : (int) array_key_first( $choices );
	}

	/**
	 * Count orders created in a date range.
	 * Used for the response-rate metric.
	 *
	 * @param array $range A resolved range array.
	 *
	 * @return int
	 */
	public static function orders_count( $range ) {
		$args = array(
			'limit'    => 1,
			'paginate' => true,
			'return'   => 'ids',
			'status'   => array( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed' ),
		);

		if ( $range['from_local'] && $range['to_local'] ) {
			$args['date_created'] = $range['from_local'] . '...' . $range['to_local'];
		} elseif ( $range['from_local'] ) {
			$args['date_created'] = '>=' . $range['from_local'];
		} elseif ( $range['to_local'] ) {
			$args['date_created'] = '<=' . $range['to_local'];
		}

		/**
		 * Filter the wc_get_orders arguments used to compute the report response rate.
		 *
		 * @param array $args The wc_get_orders arguments.
		 * @param array $range The resolved date range.
		 */
		$args = apply_filters( 'ppsfw_report_query_args', $args, $range );

		$results = wc_get_orders( $args );

		return isset( $results->total ) ? (int) $results->total : 0;
	}

	/**
	 * Build the aggregated report rows for a range + question.
	 * Labels prefer the question's current options so renamed options stay
	 * recognizable, falling back to the label stored at the time of the answer.
	 *
	 * @param array $range A resolved range array.
	 * @param int   $question_id The question (post) ID.
	 *
	 * @return array rows (array), total (int)
	 */
	public static function report_data( $range, $question_id ) {
		$repository = ResponseRepository::instance();

		$counts = $repository->counts( $range['from_utc'], $range['to_utc'], $question_id );
		$total  = 0;

		foreach ( $counts as $row ) {
			$total += (int) $row->total;
		}

		$question       = Survey::question_from_post( $question_id );
		$current_labels = array();

		if ( $question ) {
			foreach ( $question['options'] as $option ) {
				if ( is_array( $option ) && isset( $option['value'], $option['label'] ) ) {
					$current_labels[ $option['value'] ] = $option['label'];
				}
			}

			$current_labels[ Plugin::other_value() ] = $question['other_label'];
		}

		$rows = array();

		foreach ( $counts as $row ) {
			$count = (int) $row->total;

			$rows[] = array(
				'value'      => $row->answer_value,
				'label'      => isset( $current_labels[ $row->answer_value ] ) ? $current_labels[ $row->answer_value ] : $row->answer_label,
				'is_other'   => (bool) $row->is_other,
				'count'      => $count,
				'percentage' => $total > 0 ? round( ( $count / $total ) * 100, 1 ) : 0,
			);
		}

		return array(
			'rows'  => $rows,
			'total' => $total,
		);
	}

	/**
	 * Render the Reports page content.
	 *
	 * @return void
	 */
	public function render() {
		$range    = $this->current_range();
		$choices  = self::question_choices();
		$question = $this->current_question( $choices );

		$this->render_filters( $range, $choices, $question );

		if ( ! $question ) {
			$this->render_empty_state();

			return;
		}

		$report = self::report_data( $range, $question );

		if ( $report['total'] === 0 ) {
			$this->render_empty_state( $question );

			return;
		}

		$orders        = self::orders_count( $range );
		$response_rate = $orders > 0 ? round( ( $report['total'] / $orders ) * 100, 1 ) : null;

		$this->render_stats( $report['total'], $orders, $response_rate );
		$this->render_question_heading( $choices[ $question ] );
		$this->render_table( $report );
	}

	/**
	 * Render the question text as a heading directly above the answer table.
	 *
	 * @param string $question_text The question text.
	 *
	 * @return void
	 */
	protected function render_question_heading( $question_text ) {
		?>
		<h3 class="ppsfw-report-question"><?php echo esc_html( $question_text ); ?></h3>
		<?php
	}

	/**
	 * Render the filter form (question + date range).
	 *
	 * @param array $range The resolved range.
	 * @param array $choices Question choices keyed by ID.
	 * @param int   $question The selected question ID.
	 *
	 * @return void
	 */
	protected function render_filters( $range, $choices, $question ) {
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>" class="ppsfw-report-filters">
			<input type="hidden" name="post_type" value="<?php echo esc_attr( Plugin::posttype_question() ); ?>" />
			<input type="hidden" name="page" value="<?php echo esc_attr( Admin::admin_slug( 'reports' ) ); ?>" />

			<?php if ( ! empty( $choices ) ) : ?>
				<label for="ppsfw_question"><?php esc_html_e( 'Question', 'post-purchase-survey-for-woocommerce' ); ?></label>
				<select name="ppsfw_question" id="ppsfw_question">
					<?php foreach ( $choices as $choice_id => $choice_label ) : ?>
						<option value="<?php echo esc_attr( $choice_id ); ?>" <?php selected( $choice_id, $question ); ?>><?php echo esc_html( $choice_label ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>

			<label for="ppsfw_preset"><?php esc_html_e( 'Date range', 'post-purchase-survey-for-woocommerce' ); ?></label>
			<select name="ppsfw_preset" id="ppsfw_preset">
				<?php foreach ( self::presets() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $range['preset'] ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>

			<span class="ppsfw-report-filters__custom">
				<label class="screen-reader-text" for="ppsfw_from"><?php esc_html_e( 'From date', 'post-purchase-survey-for-woocommerce' ); ?></label>
				<input type="date" name="ppsfw_from" id="ppsfw_from" value="<?php echo esc_attr( $range['preset'] === 'custom' ? $range['from_local'] : '' ); ?>" />
				<span aria-hidden="true">&ndash;</span>
				<label class="screen-reader-text" for="ppsfw_to"><?php esc_html_e( 'To date', 'post-purchase-survey-for-woocommerce' ); ?></label>
				<input type="date" name="ppsfw_to" id="ppsfw_to" value="<?php echo esc_attr( $range['preset'] === 'custom' ? $range['to_local'] : '' ); ?>" />
			</span>

			<button type="submit" class="button"><?php esc_html_e( 'Apply', 'post-purchase-survey-for-woocommerce' ); ?></button>

			<p class="description"><?php esc_html_e( 'The custom dates apply when "Custom range" is selected.', 'post-purchase-survey-for-woocommerce' ); ?></p>
		</form>
		<?php
	}

	/**
	 * Render the headline stats.
	 *
	 * @param int        $total Total responses.
	 * @param int        $orders Orders in range.
	 * @param null|float $response_rate The response rate percentage or null.
	 *
	 * @return void
	 */
	protected function render_stats( $total, $orders, $response_rate ) {
		?>
		<div class="ppsfw-report-stats">
			<div class="ppsfw-report-stat">
				<span class="ppsfw-report-stat__value"><?php echo esc_html( number_format_i18n( $total ) ); ?></span>
				<span class="ppsfw-report-stat__label"><?php esc_html_e( 'Responses', 'post-purchase-survey-for-woocommerce' ); ?></span>
			</div>
			<div class="ppsfw-report-stat">
				<span class="ppsfw-report-stat__value"><?php echo esc_html( number_format_i18n( $orders ) ); ?></span>
				<span class="ppsfw-report-stat__label"><?php esc_html_e( 'Orders in range', 'post-purchase-survey-for-woocommerce' ); ?></span>
			</div>
			<div class="ppsfw-report-stat">
				<span class="ppsfw-report-stat__value">
					<?php
					if ( $response_rate !== null ) {
						echo esc_html( number_format_i18n( $response_rate, 1 ) . '%' );
					} else {
						echo '&mdash;';
					}
					?>
				</span>
				<span class="ppsfw-report-stat__label"><?php esc_html_e( 'Response rate', 'post-purchase-survey-for-woocommerce' ); ?></span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the per-answer report table.
	 *
	 * @param array $report Report data (rows, total).
	 *
	 * @return void
	 */
	protected function render_table( $report ) {
		?>
		<table class="widefat striped ppsfw-report-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Answer', 'post-purchase-survey-for-woocommerce' ); ?></th>
					<th class="ppsfw-report-table__num"><?php esc_html_e( 'Responses', 'post-purchase-survey-for-woocommerce' ); ?></th>
					<th class="ppsfw-report-table__num"><?php esc_html_e( 'Percentage', 'post-purchase-survey-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $report['rows'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['label'] ); ?></td>
						<td class="ppsfw-report-table__num"><?php echo esc_html( number_format_i18n( $row['count'] ) ); ?></td>
						<td class="ppsfw-report-table__num"><?php echo esc_html( number_format_i18n( $row['percentage'], 1 ) . '%' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th><?php esc_html_e( 'Total', 'post-purchase-survey-for-woocommerce' ); ?></th>
					<th class="ppsfw-report-table__num"><?php echo esc_html( number_format_i18n( $report['total'] ) ); ?></th>
					<th class="ppsfw-report-table__num">100%</th>
				</tr>
			</tfoot>
		</table>
		<?php
	}

	/**
	 * Render the empty state, including a preview of how the survey appears to customers.
	 *
	 * @param int $question_id The selected question ID, if any.
	 *
	 * @return void
	 */
	protected function render_empty_state( $question_id = 0 ) {
		$active   = Survey::active_questions();
		$question = $question_id ? Survey::question_from_post( $question_id ) : null;

		if ( ! $question && ! empty( $active ) ) {
			$question = reset( $active );
		}

		$options = $question ? Survey::enabled_options( $question ) : array();

		?>
		<div class="ppsfw-report-empty">
			<h3><?php esc_html_e( 'No responses yet', 'post-purchase-survey-for-woocommerce' ); ?></h3>

			<?php if ( ! Survey::is_enabled() ) : ?>
				<p>
					<?php esc_html_e( 'The survey is currently disabled.', 'post-purchase-survey-for-woocommerce' ); ?>
					<a href="<?php echo esc_url( Admin::survey_admin_url() ); ?>"><?php esc_html_e( 'Enable it on the Survey screen to start collecting responses.', 'post-purchase-survey-for-woocommerce' ); ?></a>
				</p>
			<?php elseif ( empty( $active ) ) : ?>
				<p>
					<?php esc_html_e( 'The survey has no published question with enabled answers, so it is not being shown.', 'post-purchase-survey-for-woocommerce' ); ?>
					<a href="<?php echo esc_url( Admin::survey_admin_url() ); ?>"><?php esc_html_e( 'Review the Survey screen.', 'post-purchase-survey-for-woocommerce' ); ?></a>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'Responses will appear here after customers answer the survey on the order-received (thank you) page. No responses were found for this question in the selected date range.', 'post-purchase-survey-for-woocommerce' ); ?></p>
			<?php endif; ?>

			<?php if ( $question && ! empty( $options ) ) : ?>
				<p><?php esc_html_e( 'Here is how the survey currently appears to customers:', 'post-purchase-survey-for-woocommerce' ); ?></p>

				<div class="ppsfw-report-preview">
					<strong><?php echo esc_html( $question['text'] ); ?></strong>
					<ul>
						<?php foreach ( $options as $option ) : ?>
							<li><?php echo esc_html( $option['label'] ); ?></li>
						<?php endforeach; ?>

						<?php if ( ! empty( $question['other_enabled'] ) ) : ?>
							<li><?php echo esc_html( $question['other_label'] ); ?> <em><?php esc_html_e( '(with a free-text field)', 'post-purchase-survey-for-woocommerce' ); ?></em></li>
						<?php endif; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
