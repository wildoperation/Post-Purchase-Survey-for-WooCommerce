<?php
namespace PPSFW;

/**
 * Displays the customer's survey answer on the single-order admin screen.
 * Works with both the classic order screen and HPOS.
 */
class OrderMeta {

	/**
	 * Create hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
	}

	/**
	 * The screen IDs for the single-order admin view.
	 *
	 * @return array
	 */
	protected function order_screen_ids() {
		$screen_ids = array( 'shop_order' );

		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screen_ids[] = wc_get_page_screen_id( 'shop-order' );
		}

		return array_unique( array_filter( $screen_ids ) );
	}

	/**
	 * Register the meta box.
	 *
	 * @return void
	 */
	public function add_meta_box() {
		foreach ( $this->order_screen_ids() as $screen_id ) {
			add_meta_box(
				Util::ns( 'response' ),
				__( 'Post-Purchase Survey', 'post-purchase-survey-for-woocommerce' ),
				array( $this, 'render_meta_box' ),
				$screen_id,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the meta box.
	 *
	 * @param mixed $post_or_order A WP_Post (classic) or WC_Order (HPOS).
	 *
	 * @return void
	 */
	public function render_meta_box( $post_or_order ) {
		$order = ( $post_or_order instanceof \WP_Post ) ? wc_get_order( $post_or_order->ID ) : $post_or_order;

		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$responses = ResponseRepository::instance()->rows_by_order( $order->get_id() );

		if ( empty( $responses ) ) {
			?>
			<p><em><?php esc_html_e( 'No survey response for this order.', 'post-purchase-survey-for-woocommerce' ); ?></em></p>
			<?php
			return;
		}

		foreach ( $responses as $response ) {
			$this->render_response( $response );
		}
	}

	/**
	 * Render one survey response.
	 *
	 * @param object $response A response row.
	 *
	 * @return void
	 */
	protected function render_response( $response ) {
		$question_text = get_the_title( (int) $response->question_id );

		if ( $question_text === '' ) {
			/* translators: %d: a question ID that no longer exists. */
			$question_text = sprintf( __( 'Question #%d (deleted)', 'post-purchase-survey-for-woocommerce' ), (int) $response->question_id );
		}

		?>
		<div class="ppsfw-order-response">
			<p class="ppsfw-order-question"><strong><?php echo esc_html( $question_text ); ?></strong></p>

			<p class="ppsfw-order-answer"><?php echo esc_html( $response->answer_label ); ?></p>

			<?php if ( $response->is_other && $response->other_text ) : ?>
				<p class="ppsfw-order-other"><?php echo esc_html( $response->other_text ); ?></p>
			<?php endif; ?>

			<p class="ppsfw-order-date">
				<em>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: the date the survey response was submitted. */
							__( 'Submitted %s', 'post-purchase-survey-for-woocommerce' ),
							get_date_from_gmt( $response->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
						)
					);
					?>
				</em>
			</p>
		</div>
		<?php
	}
}
