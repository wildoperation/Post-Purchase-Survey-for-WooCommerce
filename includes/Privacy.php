<?php
namespace PPS;

/**
 * WordPress personal-data exporter and eraser for survey responses.
 * Responses are tied to orders, which are tied to a customer email address.
 */
class Privacy {

	/**
	 * Orders processed per privacy request page.
	 *
	 * @var int
	 */
	const ORDERS_PER_PAGE = 25;

	/**
	 * Create hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Register the personal-data exporter.
	 *
	 * @param array $exporters Registered exporters.
	 *
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters[ Util::ns( 'responses' ) ] = array(
			'exporter_friendly_name' => __( 'Post-Purchase Survey Responses', 'post-purchase-survey-for-woocommerce' ),
			'callback'               => array( $this, 'export_personal_data' ),
		);

		return $exporters;
	}

	/**
	 * Register the personal-data eraser.
	 *
	 * @param array $erasers Registered erasers.
	 *
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers[ Util::ns( 'responses' ) ] = array(
			'eraser_friendly_name' => __( 'Post-Purchase Survey Responses', 'post-purchase-survey-for-woocommerce' ),
			'callback'             => array( $this, 'erase_personal_data' ),
		);

		return $erasers;
	}

	/**
	 * Get a page of order IDs for an email address.
	 *
	 * @param string $email_address The email address.
	 * @param int    $page The page of results.
	 *
	 * @return array
	 */
	protected function get_order_ids( $email_address, $page ) {
		$order_ids = wc_get_orders(
			array(
				'billing_email' => $email_address,
				'limit'         => self::ORDERS_PER_PAGE,
				'paged'         => $page,
				'return'        => 'ids',
				'type'          => 'shop_order',
			)
		);

		return Util::arrayify( $order_ids, true );
	}

	/**
	 * Export survey responses for an email address.
	 *
	 * @param string $email_address The email address.
	 * @param int    $page The page of results.
	 *
	 * @return array
	 */
	public function export_personal_data( $email_address, $page = 1 ) {
		$page      = max( 1, (int) $page );
		$order_ids = $this->get_order_ids( $email_address, $page );

		$export_items = array();

		if ( ! empty( $order_ids ) ) {
			$responses = ResponseRepository::instance()->get_by_order_ids( $order_ids );

			foreach ( $responses as $response ) {
				$question_text = get_the_title( (int) $response->question_id );

				$data = array(
					array(
						'name'  => __( 'Order ID', 'post-purchase-survey-for-woocommerce' ),
						'value' => $response->order_id,
					),
					array(
						'name'  => __( 'Question', 'post-purchase-survey-for-woocommerce' ),
						'value' => $question_text !== '' ? $question_text : '#' . $response->question_id,
					),
					array(
						'name'  => __( 'Answer', 'post-purchase-survey-for-woocommerce' ),
						'value' => $response->answer_label,
					),
				);

				if ( $response->other_text ) {
					$data[] = array(
						'name'  => __( 'Additional details', 'post-purchase-survey-for-woocommerce' ),
						'value' => $response->other_text,
					);
				}

				$data[] = array(
					'name'  => __( 'Submitted', 'post-purchase-survey-for-woocommerce' ),
					'value' => get_date_from_gmt( $response->created_at, 'Y-m-d H:i:s' ),
				);

				$export_items[] = array(
					'group_id'    => Util::ns( 'responses', '_' ),
					'group_label' => __( 'Post-Purchase Survey Responses', 'post-purchase-survey-for-woocommerce' ),
					'item_id'     => Util::ns( 'response-' . $response->id ),
					'data'        => $data,
				);
			}
		}

		return array(
			'data' => $export_items,
			'done' => count( $order_ids ) < self::ORDERS_PER_PAGE,
		);
	}

	/**
	 * Erase survey responses for an email address.
	 * Removes both the custom-table rows and the order meta.
	 *
	 * @param string $email_address The email address.
	 * @param int    $page The page of results.
	 *
	 * @return array
	 */
	public function erase_personal_data( $email_address, $page = 1 ) {
		$page      = max( 1, (int) $page );
		$order_ids = $this->get_order_ids( $email_address, $page );

		$repository    = ResponseRepository::instance();
		$items_removed = false;

		foreach ( $order_ids as $order_id ) {
			$deleted = $repository->delete_by_order( $order_id );

			if ( $deleted > 0 ) {
				$items_removed = true;
			}

			$order = wc_get_order( $order_id );

			if ( $order && $order->meta_exists( Plugin::meta_key_answer() ) ) {
				$order->delete_meta_data( Plugin::meta_key_answer() );
				$order->delete_meta_data( Plugin::meta_key_answer_value() );
				$order->delete_meta_data( Plugin::meta_key_other_text() );
				$order->save();

				$items_removed = true;
			}
		}

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => count( $order_ids ) < self::ORDERS_PER_PAGE,
		);
	}
}
