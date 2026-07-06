<?php
namespace PPS;

use PPS\Vendor\WOAdminFramework\WOForms;
use PPS\Vendor\WOAdminFramework\WOMeta;

/**
 * The answers repeater meta box on the question edit screen.
 */
class AdminQuestionMeta {

	/**
	 * WOMeta framework instance.
	 *
	 * @var WOMeta
	 */
	protected $wo_meta;

	/**
	 * WOForms instance.
	 *
	 * @var WOForms
	 */
	protected $wo_forms;

	/**
	 * Create hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'add_meta_boxes_' . Plugin::posttype_question(), array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . Plugin::posttype_question(), array( $this, 'save_posted_metadata' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'edit_form_after_title', array( $this, 'after_title_note' ) );
		add_action( 'admin_post_pps_delete_question_responses', array( $this, 'delete_question_responses' ) );
		add_action( 'admin_notices', array( $this, 'responses_deleted_notice' ) );
	}

	/**
	 * A note below the title field explaining how question edits affect report data.
	 *
	 * @param \WP_Post $post The current post.
	 *
	 * @return void
	 */
	public function after_title_note( $post ) {
		if ( ! $post || $post->post_type !== Plugin::posttype_question() ) {
			return;
		}

		?>
		<p class="description pps-question-note"><?php esc_html_e( 'Changing a question will not reset responses and report data. To do so, create a new question.', 'post-purchase-survey-for-woocommerce' ); ?></p>
		<?php
	}

	/**
	 * Create a new WOMeta instance if necessary.
	 *
	 * @return WOMeta
	 */
	protected function meta() {
		if ( ! $this->wo_meta ) {
			$this->wo_meta = new WOMeta( Plugin::ns() );
		}

		return $this->wo_meta;
	}

	/**
	 * Create a new WOForms instance if necessary.
	 *
	 * @return WOForms
	 */
	protected function forms() {
		if ( ! $this->wo_forms ) {
			$this->wo_forms = new WOForms();
		}

		return $this->wo_forms;
	}

	/**
	 * Enqueue the repeater assets on the question edit screen.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		$screen = get_current_screen();

		if ( ! $screen || $screen->id !== Plugin::posttype_question() ) {
			return;
		}

		$this->meta()->repeater_enqueue();

		wp_enqueue_style( Util::ns( 'admin' ), Plugin::assets_url() . 'css/admin.css', array( 'woadmin' ), Plugin::version() );
	}

	/**
	 * Register the meta boxes.
	 *
	 * @return void
	 */
	public function add_meta_boxes( $post ) {
		add_meta_box(
			Util::ns( 'answers' ),
			__( 'Answer Options', 'post-purchase-survey-for-woocommerce' ),
			array( $this, 'render_answers_meta_box' ),
			Plugin::posttype_question(),
			'normal',
			'high'
		);

		/**
		 * The quick report only makes sense for questions that could have
		 * responses, so it is skipped on the Add New screen.
		 */
		if ( $post && $post->post_status !== 'auto-draft' ) {
			add_meta_box(
				Util::ns( 'question-report' ),
				__( 'Report', 'post-purchase-survey-for-woocommerce' ),
				array( $this, 'render_report_meta_box' ),
				Plugin::posttype_question(),
				'normal',
				'default'
			);
		}
	}

	/**
	 * Render the quick report meta box: all-time response counts for this
	 * question and a button to delete its responses.
	 *
	 * @param \WP_Post $post The question post.
	 *
	 * @return void
	 */
	public function render_report_meta_box( $post ) {
		$report = AdminReports::report_data( AdminReports::resolve_range( 'all' ), $post->ID );

		if ( $report['total'] === 0 ) {
			?>
			<p><em><?php esc_html_e( 'No responses for this question yet.', 'post-purchase-survey-for-woocommerce' ); ?></em></p>
			<?php
			return;
		}

		?>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: the total number of responses. */
					_n( '%s response, all time.', '%s responses, all time.', $report['total'], 'post-purchase-survey-for-woocommerce' ),
					number_format_i18n( $report['total'] )
				)
			);
			?>
			<a href="<?php echo esc_url( add_query_arg( 'pps_question', $post->ID, Admin::reports_admin_url() ) ); ?>"><?php esc_html_e( 'View full report', 'post-purchase-survey-for-woocommerce' ); ?></a>
		</p>

		<table class="widefat striped pps-report-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Answer', 'post-purchase-survey-for-woocommerce' ); ?></th>
					<th class="pps-report-table__num"><?php esc_html_e( 'Responses', 'post-purchase-survey-for-woocommerce' ); ?></th>
					<th class="pps-report-table__num"><?php esc_html_e( 'Percentage', 'post-purchase-survey-for-woocommerce' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $report['rows'] as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['label'] ); ?></td>
						<td class="pps-report-table__num"><?php echo esc_html( number_format_i18n( $row['count'] ) ); ?></td>
						<td class="pps-report-table__num"><?php echo esc_html( number_format_i18n( $row['percentage'], 1 ) . '%' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="pps-delete-responses">
			<input type="hidden" name="action" value="pps_delete_question_responses" />
			<input type="hidden" name="pps_question_id" value="<?php echo esc_attr( $post->ID ); ?>" />
			<?php wp_nonce_field( 'pps_delete_question_responses', 'pps_delete_nonce' ); ?>

			<button type="submit" class="button pps-delete-responses__button" onclick="return window.confirm( '<?php echo esc_js( __( 'Delete all responses for this question? This also removes them from reports and cannot be undone.', 'post-purchase-survey-for-woocommerce' ) ); ?>' );">
				<?php esc_html_e( 'Delete All Responses', 'post-purchase-survey-for-woocommerce' ); ?>
			</button>
		</form>
		<?php
	}

	/**
	 * Handle the "Delete All Responses" action for a question.
	 *
	 * @return void
	 */
	public function delete_question_responses() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to delete survey responses.', 'post-purchase-survey-for-woocommerce' ) );
		}

		check_admin_referer( 'pps_delete_question_responses', 'pps_delete_nonce' );

		$question_id = isset( $_POST['pps_question_id'] ) ? absint( $_POST['pps_question_id'] ) : 0;
		$post        = $question_id ? get_post( $question_id ) : null;

		if ( ! $post || $post->post_type !== Plugin::posttype_question() ) {
			wp_die( esc_html__( 'Invalid question.', 'post-purchase-survey-for-woocommerce' ) );
		}

		$deleted = ResponseRepository::instance()->delete_by_question( $question_id );

		wp_safe_redirect(
			add_query_arg(
				'pps_deleted',
				$deleted,
				get_edit_post_link( $question_id, 'raw' )
			)
		);
		exit;
	}

	/**
	 * Show a notice after responses are deleted.
	 *
	 * @return void
	 */
	public function responses_deleted_notice() {
		$screen = get_current_screen();

		if ( ! $screen || $screen->id !== Plugin::posttype_question() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice from a redirect parameter.
		if ( ! isset( $_GET['pps_deleted'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only notice from a redirect parameter.
		$deleted = absint( $_GET['pps_deleted'] );

		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: the number of deleted responses. */
						_n( '%s survey response deleted.', '%s survey responses deleted.', $deleted, 'post-purchase-survey-for-woocommerce' ),
						number_format_i18n( $deleted )
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the answers repeater.
	 * Rows post as parallel arrays (pps_answer_labels[], pps_answer_values[],
	 * pps_answer_enabled[]) that stay aligned because every row submits all
	 * three inputs in DOM order.
	 *
	 * @param \WP_Post $post The question post.
	 *
	 * @return void
	 */
	public function render_answers_meta_box( $post ) {
		/*
		 * Raw meta only: the edit screen must never display (and re-save)
		 * options injected by the pps_answer_options filter.
		 */
		$options = get_post_meta( $post->ID, Plugin::meta_key_answers(), true );

		if ( ! is_array( $options ) || empty( $options ) ) {
			$options = array(
				array(
					'value'   => '',
					'label'   => '',
					'enabled' => 1,
				),
			);
		}

		$other_label = get_post_meta( $post->ID, Plugin::meta_key_question_other_label(), true );

		$question = array(
			'other_enabled' => Util::truthy( get_post_meta( $post->ID, Plugin::meta_key_question_other(), true ) ),
			'other_label'   => is_string( $other_label ) ? $other_label : '',
		);

		wp_nonce_field( 'pps_save_question', 'pps_question_nonce' );

		$rows = array();

		foreach ( $options as $option ) {
			if ( ! is_array( $option ) ) {
				continue;
			}

			$rows[] = $this->answer_option_row_cells(
				wp_parse_args(
					$option,
					array(
						'value'   => '',
						'label'   => '',
						'enabled' => 1,
					)
				)
			);
		}

		$this->meta()->repeater_table(
			array(
				__( 'Label', 'post-purchase-survey-for-woocommerce' ),
				array(
					'text'  => __( 'Status', 'post-purchase-survey-for-woocommerce' ),
					'width' => '120',
				),
			),
			$rows,
			array(
				'classes' => array( 'pps-answer-options' ),
				'width'   => '620',
			)
		);

		$this->meta()->message( '<em>' . esc_html__( 'Drag to reorder. Disabled options are hidden from customers but keep their reporting history.', 'post-purchase-survey-for-woocommerce' ) . '</em>' );

		$this->render_other_option( $question );
	}

	/**
	 * Render the per-question "Other" option fields below the answers.
	 *
	 * @param array $question The question array.
	 *
	 * @return void
	 */
	protected function render_other_option( $question ) {
		?>
		<div class="pps-question-field pps-question-field--other">
			<p>
				<?php
				$this->forms()->checkbox( 'pps_question_other', $question['other_enabled'] ? 1 : 0 );
				$this->forms()->label( 'pps_question_other', '<strong>' . esc_html__( 'Offer an "Other" option with a free-text field', 'post-purchase-survey-for-woocommerce' ) . '</strong>' );
				?>
			</p>
			<p>
				<?php
				$this->forms()->label( 'pps_question_other_label', esc_html__( '"Other" label', 'post-purchase-survey-for-woocommerce' ) );
				echo '<br />';
				$this->forms()->input(
					'pps_question_other_label',
					$question['other_label'],
					'text',
					array(
						'classes'     => array( 'regular-text' ),
						'placeholder' => Survey::default_other_label(),
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Build the repeater cells for one answer option row.
	 *
	 * @param array $option An answer option (value, label, enabled).
	 *
	 * @return array
	 */
	private function answer_option_row_cells( $option ) {
		$forms = $this->forms();

		$label_cell  = $forms->input(
			'pps_answer_labels[]',
			$option['label'],
			'text',
			array(
				'display' => false,
				'id'      => null,
				'classes' => array( 'widefat' ),
			)
		);
		$label_cell .= $forms->input(
			'pps_answer_values[]',
			$option['value'],
			'hidden',
			array(
				'display' => false,
				'id'      => null,
			)
		);

		$status_cell = $forms->select(
			'pps_answer_enabled[]',
			array(
				'1' => __( 'Enabled', 'post-purchase-survey-for-woocommerce' ),
				'0' => __( 'Disabled', 'post-purchase-survey-for-woocommerce' ),
			),
			! empty( $option['enabled'] ) ? '1' : '0',
			array(
				'display' => false,
				'id'      => null,
			)
		);

		return array( $label_cell, $status_cell );
	}

	/**
	 * Save the posted answer options.
	 *
	 * @param int      $post_id The post ID.
	 * @param \WP_Post $post The post.
	 *
	 * @return void
	 */
	public function save_posted_metadata( $post_id, $post ) {
		if ( ! isset( $_POST['pps_question_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['pps_question_nonce'] ) ), 'pps_save_question' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		if ( ! current_user_can( Plugin::capability() ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized row-by-row in Survey::sanitize_answer_rows().
		$options = Survey::sanitize_answer_rows(
			isset( $_POST['pps_answer_labels'] ) ? wp_unslash( $_POST['pps_answer_labels'] ) : array(),
			isset( $_POST['pps_answer_values'] ) ? wp_unslash( $_POST['pps_answer_values'] ) : array(),
			isset( $_POST['pps_answer_enabled'] ) ? wp_unslash( $_POST['pps_answer_enabled'] ) : array()
		);
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		update_post_meta( $post_id, Plugin::meta_key_answers(), $options );

		/**
		 * Per-question "Other" option.
		 */
		update_post_meta( $post_id, Plugin::meta_key_question_other(), isset( $_POST['pps_question_other'] ) ? 1 : 0 );

		$other_label = isset( $_POST['pps_question_other_label'] ) ? sanitize_text_field( wp_unslash( $_POST['pps_question_other_label'] ) ) : '';

		update_post_meta( $post_id, Plugin::meta_key_question_other_label(), $other_label );
	}
}
