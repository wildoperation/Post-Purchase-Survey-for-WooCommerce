<?php
namespace PPS;

use PPS\Vendor\WOAdminFramework\WOAdmin;
use PPS\Vendor\WOAdminFramework\WOSettings;

/**
 * The Survey admin page: enable toggle, question selection, and the "Other" option.
 */
class AdminSurvey extends WOAdmin {

	/**
	 * WOSettings framework instance.
	 *
	 * @var WOSettings
	 */
	protected $sf;

	/**
	 * Create hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_pps_search_questions', array( $this, 'ajax_search_questions' ) );

		add_filter(
			'option_page_capability_' . $this->sf()->key( 'survey' ),
			function () {
				return Plugin::capability();
			}
		);
	}

	/**
	 * Create a new WOSettings instance if necessary.
	 *
	 * @return WOSettings
	 */
	protected function sf() {
		if ( ! $this->sf ) {
			$this->sf = new WOSettings( Plugin::ns() );
		}

		return $this->sf;
	}

	/**
	 * The survey settings array.
	 *
	 * @return array
	 */
	public static function settings() {
		return array(
			'survey' => array(
				'title'      => __( 'Survey', 'post-purchase-survey-for-woocommerce' ),
				'initialize' => Survey::default_survey(),
				'sections'   => array(
					'active_survey' => array(
						'fields' => array(
							'enabled'   => __( 'Enable survey', 'post-purchase-survey-for-woocommerce' ),
							'questions' => __( 'Survey questions', 'post-purchase-survey-for-woocommerce' ),
						),
					),
					'display'       => array(
						'title'  => __( 'Display', 'post-purchase-survey-for-woocommerce' ),
						'fields' => array(
							'position'  => __( 'Survey position', 'post-purchase-survey-for-woocommerce' ),
							'thank_you' => __( 'Thank you message', 'post-purchase-survey-for-woocommerce' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Register settings from the settings array.
	 *
	 * @return void
	 */
	public function register_settings() {
		$this->sf()->add_sections_and_settings(
			self::settings(),
			$this
		);
	}

	/**
	 * Call back for the survey settings section.
	 *
	 * @return void
	 */
	public function settings_callback_pps_active_survey() {}

	/**
	 * Call back for the display settings section.
	 *
	 * @return void
	 */
	public function settings_callback_pps_display() {}

	/**
	 * Enable survey field.
	 *
	 * @return void
	 */
	public function field_pps_enabled() {
		$id = array( $this->sf()->key( 'survey' ) => 'enabled' );

		$this->sf()->checkbox( $id, $this->sf()->get( 'enabled', 'survey' ) );
		$this->sf()->label( $id, esc_html__( 'Show the survey on the order-received (thank you) page', 'post-purchase-survey-for-woocommerce' ) );
	}

	/**
	 * Survey position field.
	 *
	 * @return void
	 */
	public function field_pps_position() {
		$id = array( $this->sf()->key( 'survey' ) => 'position' );

		$this->sf()->select(
			$id,
			array(
				'below' => __( 'Below the order details', 'post-purchase-survey-for-woocommerce' ),
				'above' => __( 'Above the order details', 'post-purchase-survey-for-woocommerce' ),
			),
			$this->sf()->get( 'position', 'survey' )
		);
		$this->sf()->message( '<em>' . esc_html__( 'Works with both the classic and block-based order confirmation pages. If a customized template does not output the "above" position, the survey falls back to below the order details.', 'post-purchase-survey-for-woocommerce' ) . '</em>' );
	}

	/**
	 * Thank-you message field.
	 *
	 * @return void
	 */
	public function field_pps_thank_you() {
		$id = array( $this->sf()->key( 'survey' ) => 'thank_you' );

		$this->sf()->input(
			$id,
			$this->sf()->get( 'thank_you', 'survey' ),
			'text',
			array(
				'classes'     => array( 'regular-text' ),
				'placeholder' => Survey::default_survey()['thank_you'],
			)
		);
		$this->sf()->message( '<em>' . esc_html__( 'Shown in place of the survey after a customer responds.', 'post-purchase-survey-for-woocommerce' ) . '</em>' );
	}

	/**
	 * The question picker field.
	 * Selected questions are stored in order; the search input finds question
	 * posts over AJAX. Draft and pending questions can be selected but are
	 * badged and never display to customers.
	 *
	 * @return void
	 */
	public function field_pps_questions() {
		$settings_key = $this->sf()->key( 'survey' );
		$selected     = Survey::selected_questions();
		$max          = Survey::max_questions();

		?>
		<div class="pps-question-picker" data-max="<?php echo esc_attr( $max ); ?>">
			<ul class="pps-question-picker__selected">
				<?php foreach ( $selected as $question ) : ?>
					<?php $this->selected_question_row( $settings_key, $question ); ?>
				<?php endforeach; ?>
			</ul>

			<p class="pps-question-picker__search-wrap">
				<label class="screen-reader-text" for="pps-question-search"><?php esc_html_e( 'Search questions', 'post-purchase-survey-for-woocommerce' ); ?></label>
				<input type="text" id="pps-question-search" class="pps-question-picker__search regular-text" placeholder="<?php esc_attr_e( 'Search questions&hellip;', 'post-purchase-survey-for-woocommerce' ); ?>" />
			</p>

			<p class="description pps-question-picker__max-note" <?php echo count( $selected ) < $max ? 'style="display:none"' : ''; ?>>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: the maximum number of survey questions. */
						_n( 'The survey shows %d question. Remove the selected question to choose a different one.', 'The survey shows up to %d questions.', $max, 'post-purchase-survey-for-woocommerce' ),
						$max
					)
				);
				?>
			</p>

			<p class="description"><?php esc_html_e( 'Draft and pending questions can be selected while you prepare them, but only published questions are shown to customers.', 'post-purchase-survey-for-woocommerce' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render one selected-question row.
	 *
	 * @param string $settings_key The namespaced survey option key.
	 * @param array  $question A question array (id, text, status).
	 *
	 * @return void
	 */
	protected function selected_question_row( $settings_key, $question ) {
		$status_labels = self::status_labels();

		?>
		<li class="pps-question-picker__question" data-id="<?php echo esc_attr( $question['id'] ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $settings_key ); ?>[question_ids][]" value="<?php echo esc_attr( $question['id'] ); ?>" />
			<span class="pps-question-picker__handle dashicons dashicons-menu" aria-hidden="true"></span>
			<span class="pps-question-picker__title"><?php echo esc_html( $question['text'] !== '' ? $question['text'] : __( '(no title)', 'post-purchase-survey-for-woocommerce' ) ); ?></span>
			<?php if ( $question['status'] !== 'publish' ) : ?>
				<span class="pps-badge pps-badge--<?php echo esc_attr( $question['status'] ); ?>"><?php echo esc_html( isset( $status_labels[ $question['status'] ] ) ? $status_labels[ $question['status'] ] : $question['status'] ); ?></span>
			<?php endif; ?>
			<a href="<?php echo esc_url( get_edit_post_link( $question['id'] ) ); ?>" class="pps-question-picker__edit"><?php esc_html_e( 'Edit', 'post-purchase-survey-for-woocommerce' ); ?></a>
			<button type="button" class="button-link-delete pps-question-picker__remove"><?php esc_html_e( 'Remove', 'post-purchase-survey-for-woocommerce' ); ?></button>
		</li>
		<?php
	}

	/**
	 * Post-status labels used in the picker.
	 *
	 * @return array
	 */
	public static function status_labels() {
		return array(
			'publish' => __( 'Published', 'post-purchase-survey-for-woocommerce' ),
			'draft'   => __( 'Draft', 'post-purchase-survey-for-woocommerce' ),
			'pending' => __( 'Pending', 'post-purchase-survey-for-woocommerce' ),
		);
	}

	/**
	 * Sanitize the survey settings group.
	 *
	 * @param array $input The input to sanitize.
	 *
	 * @return array
	 */
	public function sanitize_pps_survey( $input ) {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die();
		}

		$input = Util::arrayify( $input, true );

		$question_ids = array();

		if ( isset( $input['question_ids'] ) && is_array( $input['question_ids'] ) ) {
			foreach ( $input['question_ids'] as $question_id ) {
				$question_id = absint( $question_id );

				if ( ! $question_id || in_array( $question_id, $question_ids, true ) ) {
					continue;
				}

				$post = get_post( $question_id );

				if ( ! $post || $post->post_type !== Plugin::posttype_question() || ! in_array( $post->post_status, array( 'publish', 'draft', 'pending' ), true ) ) {
					continue;
				}

				$question_ids[] = $question_id;
			}
		}

		$question_ids = array_slice( $question_ids, 0, Survey::max_questions() );

		$output = array(
			'enabled'      => WOAdmin::sanitize_by_type( isset( $input['enabled'] ) ? $input['enabled'] : 0, 'bool' ),
			'question_ids' => $question_ids,
			'position'     => ( isset( $input['position'] ) && in_array( $input['position'], array( 'below', 'above' ), true ) ) ? $input['position'] : 'below',
			'thank_you'    => WOAdmin::sanitize_by_type( isset( $input['thank_you'] ) ? $input['thank_you'] : '', 'str' ),
		);

		Survey::flush_settings_cache();

		return $output;
	}

	/**
	 * AJAX search for question posts (any of published/draft/pending).
	 *
	 * @return void
	 */
	public function ajax_search_questions() {
		$this->authorize_ajax_action( 'pps_admin', 'nonce', Plugin::capability() );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified in authorize_ajax_action().
		$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

		$args = array(
			'post_type'      => Plugin::posttype_question(),
			'post_status'    => array( 'publish', 'draft', 'pending' ),
			'posts_per_page' => 20,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $term !== '' ) {
			$args['s'] = $term;
		}

		$status_labels = self::status_labels();
		$results       = array();

		foreach ( get_posts( $args ) as $post ) {
			$results[] = array(
				'id'           => $post->ID,
				'title'        => $post->post_title !== '' ? $post->post_title : __( '(no title)', 'post-purchase-survey-for-woocommerce' ),
				'status'       => $post->post_status,
				'status_label' => isset( $status_labels[ $post->post_status ] ) ? $status_labels[ $post->post_status ] : $post->post_status,
				'edit_url'     => get_edit_post_link( $post->ID, 'raw' ),
			);
		}

		wp_send_json_success( $results );
	}
}
