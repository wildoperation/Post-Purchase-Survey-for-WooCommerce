<?php
namespace PPS;

/**
 * Front-end rendering and submission handling for the survey.
 */
class Front {

	/**
	 * Create hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'woocommerce_before_thankyou', array( $this, 'render_above' ) );
		add_action( 'woocommerce_thankyou', array( $this, 'render_below' ), 20 );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		add_action( 'wp_ajax_pps_submit_response', array( $this, 'ajax_submit' ) );
		add_action( 'wp_ajax_nopriv_pps_submit_response', array( $this, 'ajax_submit' ) );

		add_action( 'admin_post_pps_submit_response', array( $this, 'post_submit' ) );
		add_action( 'admin_post_nopriv_pps_submit_response', array( $this, 'post_submit' ) );
	}

	/**
	 * Render the survey above the order details (woocommerce_before_thankyou).
	 * This hook only fires on the classic order-received template.
	 *
	 * @param int $order_id The order ID.
	 *
	 * @return void
	 */
	public function render_above( $order_id ) {
		if ( Survey::position() === 'above' ) {
			$this->render( $order_id );
		}
	}

	/**
	 * Render the survey below the order details (woocommerce_thankyou).
	 * This hook fires on the classic template and inside the block-based
	 * Order Confirmation "Additional Information" block.
	 *
	 * @param int $order_id The order ID.
	 *
	 * @return void
	 */
	public function render_below( $order_id ) {
		$position = Survey::position();

		/*
		 * woocommerce_before_thankyou does not fire on the block-based order confirmation
		 * template. If "above" was requested but never rendered, fall back to this hook so
		 * the survey is never silently missing.
		 */
		if ( $position === 'below' || ( $position === 'above' && ! did_action( 'woocommerce_before_thankyou' ) ) ) {
			$this->render( $order_id );
		}
	}

	/**
	 * Render the survey (or the thank-you message if the order already has responses).
	 * Any failure here must never break the order-received page, so everything is
	 * built into a string and errors fail silently.
	 *
	 * @param int $order_id The order ID.
	 *
	 * @return void
	 */
	public function render( $order_id ) {
		static $rendered = false;

		if ( $rendered ) {
			return;
		}

		try {
			$order = wc_get_order( $order_id );

			if ( ! $this->should_display( $order ) ) {
				return;
			}

			$questions = Survey::active_questions();

			if ( empty( $questions ) ) {
				return;
			}

			$rendered = true;

			$unanswered = $this->unanswered_questions( $order, $questions );

			if ( empty( $unanswered ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in thank_you_html().
				echo $this->thank_you_html();

				return;
			}

			/**
			 * Fires before the survey form is rendered.
			 *
			 * @param \WC_Order $order The current order.
			 */
			do_action( 'pps_render_before_form', $order );

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in form_html().
			echo $this->form_html( $order, $unanswered );

			/**
			 * Fires after the survey form is rendered.
			 *
			 * @param \WC_Order $order The current order.
			 */
			do_action( 'pps_render_after_form', $order );
		} catch ( \Throwable $t ) {
			return;
		}
	}

	/**
	 * The questions from a set that don't have a response for this order yet.
	 *
	 * @param \WC_Order $order The order.
	 * @param array     $questions Question arrays keyed by question ID.
	 *
	 * @return array
	 */
	protected function unanswered_questions( $order, $questions ) {
		$repository = ResponseRepository::instance();

		return array_filter(
			$questions,
			function ( $question ) use ( $repository, $order ) {
				return ! $repository->exists( $order->get_id(), $question['id'] );
			}
		);
	}

	/**
	 * Whether the survey should display for this order.
	 *
	 * @param mixed $order The order (hopefully a \WC_Order).
	 *
	 * @return bool
	 */
	protected function should_display( $order ) {
		$display = true;

		if ( ! Survey::is_enabled() ) {
			$display = false;
		}

		if ( $display && ( ! $order || ! is_a( $order, 'WC_Order' ) ) ) {
			return false;
		}

		if ( $display && in_array( $order->get_status(), array( 'cancelled', 'refunded', 'failed', 'checkout-draft' ), true ) ) {
			$display = false;
		}

		if ( $display && ! $this->verify_order_access( $order ) ) {
			$display = false;
		}

		/**
		 * Filter whether the survey should display for an order.
		 *
		 * @param bool           $display Whether to display the survey.
		 * @param \WC_Order|bool $order The current order.
		 */
		return apply_filters( 'pps_should_display', $display, $order );
	}

	/**
	 * Verify the visitor is allowed to see this order's survey.
	 * Either the order key in the URL matches, or the order belongs to the logged-in customer.
	 *
	 * @param \WC_Order $order The order.
	 *
	 * @return bool
	 */
	protected function verify_order_access( $order ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Read-only comparison against the order key, which is itself the credential; sanitized with wc_clean().
		$order_key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';

		if ( $order_key && hash_equals( $order->get_order_key(), $order_key ) ) {
			return true;
		}

		if ( is_user_logged_in() && $order->get_customer_id() && $order->get_customer_id() === get_current_user_id() ) {
			return true;
		}

		return false;
	}

	/**
	 * The survey form markup.
	 * The free version renders a single question; the markup supports several
	 * so Pro multi-question flows bolt on without a rewrite.
	 *
	 * @param \WC_Order $order The order.
	 * @param array     $questions Question arrays keyed by question ID.
	 *
	 * @return string
	 */
	protected function form_html( $order, $questions ) {
		$html  = '<div class="pps-survey" id="pps-survey">';
		$html .= '<form class="pps-survey__form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';

		foreach ( $questions as $question ) {
			$html .= $this->question_fieldset_html( $question );
		}

		$html .= '<input type="hidden" name="action" value="pps_submit_response" />';
		$html .= '<input type="hidden" name="pps_order_id" value="' . esc_attr( $order->get_id() ) . '" />';
		$html .= '<input type="hidden" name="pps_order_key" value="' . esc_attr( $order->get_order_key() ) . '" />';
		$html .= wp_nonce_field( 'pps_submit_response', 'pps_nonce', false, false );

		$html .= '<button type="submit" class="button pps-survey__submit">' . esc_html__( 'Submit', 'post-purchase-survey-for-woocommerce' ) . '</button>';
		$html .= '<span class="pps-survey__error" role="alert" aria-live="polite"></span>';
		$html .= '</form>';
		$html .= '</div>';

		return $html;
	}

	/**
	 * The fieldset markup for one question.
	 *
	 * @param array $question A question array.
	 *
	 * @return string
	 */
	protected function question_fieldset_html( $question ) {
		$question_id = absint( $question['id'] );
		$options     = Survey::enabled_options( $question );

		$field_id = function ( $suffix ) use ( $question_id ) {
			return 'pps-q' . $question_id . '-' . $suffix;
		};

		$html  = '<fieldset class="pps-survey__fieldset">';
		$html .= '<legend class="pps-survey__question">' . esc_html( $question['text'] ) . '</legend>';
		$html .= '<ul class="pps-survey__options">';

		foreach ( $options as $option ) {
			$id = $field_id( sanitize_html_class( $option['value'] ) );

			$html .= '<li class="pps-survey__option">';
			$html .= '<input type="radio" name="pps_answer[' . esc_attr( $question_id ) . ']" required id="' . esc_attr( $id ) . '" value="' . esc_attr( $option['value'] ) . '" />';
			$html .= '<label for="' . esc_attr( $id ) . '">' . esc_html( $option['label'] ) . '</label>';
			$html .= '</li>';
		}

		if ( ! empty( $question['other_enabled'] ) ) {
			$other_id = $field_id( 'option-other' );
			$text_id  = $field_id( 'other-text' );

			$html .= '<li class="pps-survey__option pps-survey__option--other">';
			$html .= '<input type="radio" name="pps_answer[' . esc_attr( $question_id ) . ']" required id="' . esc_attr( $other_id ) . '" value="' . esc_attr( Plugin::other_value() ) . '" class="pps-survey__other-radio" />';
			$html .= '<label for="' . esc_attr( $other_id ) . '">' . esc_html( $question['other_label'] ) . '</label>';
			$html .= '<span class="pps-survey__other-text">';
			$html .= '<label class="screen-reader-text" for="' . esc_attr( $text_id ) . '">' . esc_html__( 'Please tell us more', 'post-purchase-survey-for-woocommerce' ) . '</label>';
			$html .= '<input type="text" name="pps_other_text[' . esc_attr( $question_id ) . ']" id="' . esc_attr( $text_id ) . '" maxlength="500" placeholder="' . esc_attr__( 'Please tell us more', 'post-purchase-survey-for-woocommerce' ) . '" />';
			$html .= '</span>';
			$html .= '</li>';
		}

		$html .= '</ul>';
		$html .= '</fieldset>';

		return $html;
	}

	/**
	 * The thank-you message markup shown after a response is saved.
	 *
	 * @return string
	 */
	public function thank_you_html() {
		return '<div class="pps-survey pps-survey--thanks" id="pps-survey"><p>' . esc_html( Survey::thank_you_message() ) . '</p></div>';
	}

	/**
	 * Enqueue front-end assets on the order-received page only.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! function_exists( 'is_order_received_page' ) ) {
			return;
		}

		if ( ! is_order_received_page() && ! is_wc_endpoint_url( 'order-received' ) ) {
			return;
		}

		if ( ! Survey::is_enabled() ) {
			return;
		}

		wp_enqueue_style( Util::ns( 'survey' ), Plugin::assets_url() . 'css/front.css', array(), Plugin::version() );

		$handle = Util::ns( 'survey' );

		wp_register_script(
			$handle,
			Plugin::assets_url() . 'js/survey.js',
			array(),
			Plugin::version(),
			array(
				'strategy'  => 'defer',
				'in_footer' => true,
			)
		);
		wp_enqueue_script( $handle );

		Util::enqueue_script_data(
			$handle,
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			),
			'pps_survey'
		);
	}

	/**
	 * AJAX submission handler.
	 *
	 * @return void
	 */
	public function ajax_submit() {
		$result = $this->process_submission();

		if ( $result['success'] ) {
			wp_send_json_success(
				array(
					'html' => $this->thank_you_html(),
				)
			);
		}

		wp_send_json_error(
			array(
				'message' => $result['message'],
			),
			400
		);
	}

	/**
	 * No-JS form submission handler (admin-post.php).
	 * Redirects back to the order-received page, where the saved response
	 * causes the thank-you message to render in place of the form.
	 *
	 * @return void
	 */
	public function post_submit() {
		$result = $this->process_submission();

		$redirect = home_url( '/' );

		if ( ! empty( $result['order'] ) ) {
			$redirect = $result['order']->get_checkout_order_received_url();
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Validate and store a survey submission.
	 * Shared by the AJAX handler and the no-JS fallback.
	 *
	 * @return array success (bool), message (string), order (\WC_Order|null)
	 */
	protected function process_submission() {
		$fail = function ( $message ) {
			return array(
				'success' => false,
				'message' => $message,
				'order'   => null,
			);
		};

		$generic_error = __( 'Sorry, your response could not be saved. Please refresh the page and try again.', 'post-purchase-survey-for-woocommerce' );

		if ( ! isset( $_POST['pps_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['pps_nonce'] ) ), 'pps_submit_response' ) ) {
			return $fail( $generic_error );
		}

		if ( ! Survey::is_enabled() ) {
			return $fail( $generic_error );
		}

		$order_id = isset( $_POST['pps_order_id'] ) ? absint( $_POST['pps_order_id'] ) : 0;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized with wc_clean().
		$order_key = isset( $_POST['pps_order_key'] ) ? wc_clean( wp_unslash( $_POST['pps_order_key'] ) ) : '';

		$order = $order_id ? wc_get_order( $order_id ) : false;

		if ( ! $order || ! is_a( $order, 'WC_Order' ) || ! $order_key || ! hash_equals( $order->get_order_key(), $order_key ) ) {
			return $fail( $generic_error );
		}

		$questions = Survey::active_questions();

		if ( empty( $questions ) ) {
			return $fail( $generic_error );
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per value below.
		$posted_answers = isset( $_POST['pps_answer'] ) && is_array( $_POST['pps_answer'] ) ? wp_unslash( $_POST['pps_answer'] ) : array();
		$posted_other   = isset( $_POST['pps_other_text'] ) && is_array( $_POST['pps_other_text'] ) ? wp_unslash( $_POST['pps_other_text'] ) : array();
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$repository = ResponseRepository::instance();

		$saved            = 0;
		$already_answered = 0;

		foreach ( $questions as $question ) {
			$question_id = $question['id'];

			if ( $repository->exists( $order->get_id(), $question_id ) ) {
				++$already_answered;
				continue;
			}

			if ( ! isset( $posted_answers[ $question_id ] ) ) {
				continue;
			}

			$answer  = sanitize_key( $posted_answers[ $question_id ] );
			$options = Survey::enabled_options( $question );

			$answer_label = '';
			$is_other     = false;

			if ( $answer === Plugin::other_value() && ! empty( $question['other_enabled'] ) ) {
				$is_other     = true;
				$answer_label = $question['other_label'];
			} else {
				foreach ( $options as $option ) {
					if ( $option['value'] === $answer ) {
						$answer_label = $option['label'];
						break;
					}
				}
			}

			if ( $answer === '' || $answer_label === '' ) {
				continue;
			}

			$other_text = null;

			if ( $is_other && isset( $posted_other[ $question_id ] ) ) {
				$other_text = sanitize_textarea_field( $posted_other[ $question_id ] );
				$other_text = mb_substr( $other_text, 0, 500 );

				if ( $other_text === '' ) {
					$other_text = null;
				}
			}

			$data = array(
				'order_id'     => $order->get_id(),
				'question_id'  => $question_id,
				'answer_value' => $answer,
				'answer_label' => $answer_label,
				'is_other'     => $is_other ? 1 : 0,
				'other_text'   => $other_text,
				'created_at'   => Util::now_utc(),
			);

			/**
			 * Filter the response data before it is saved.
			 *
			 * @param array     $data The response data.
			 * @param \WC_Order $order The order.
			 */
			$data = apply_filters( 'pps_response_data', $data, $order );

			$inserted = $repository->insert( $data );

			if ( ! $inserted ) {
				/**
				 * A duplicate insert (unique key violation) means another submission
				 * won the race; that's still answered from the customer's view.
				 */
				if ( $repository->exists( $order->get_id(), $question_id ) ) {
					++$already_answered;
				}

				continue;
			}

			++$saved;

			/**
			 * Write the answer to order meta (HPOS-safe) so it is visible on the
			 * order admin screen and readable by other tools. With multiple
			 * questions (Pro), these keys reflect the primary question's answer.
			 */
			$order->update_meta_data( Plugin::meta_key_answer(), $data['answer_label'] );
			$order->update_meta_data( Plugin::meta_key_answer_value(), $data['answer_value'] );

			if ( $data['other_text'] ) {
				$order->update_meta_data( Plugin::meta_key_other_text(), $data['other_text'] );
			}

			$order->save();

			/**
			 * Fires after a response is saved.
			 *
			 * @param int   $order_id The order ID.
			 * @param array $data The saved response data.
			 */
			do_action( 'pps_after_response_saved', $order->get_id(), $data );
		}

		if ( $saved > 0 || $already_answered >= count( $questions ) ) {
			return array(
				'success' => true,
				'message' => '',
				'order'   => $order,
			);
		}

		return $fail( __( 'Please choose an answer before submitting.', 'post-purchase-survey-for-woocommerce' ) );
	}
}
