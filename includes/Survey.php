<?php
namespace PPSFW;

/**
 * The survey model.
 * Questions are ppsfw_question posts (title = question, answers in post meta).
 * The survey configuration (enabled, selected questions, "Other" option) is an option group.
 */
class Survey {

	/**
	 * Cached, normalized survey configuration for this request.
	 *
	 * @var null|array
	 */
	private static $survey = null;

	/**
	 * Cached, normalized display settings for this request.
	 *
	 * @var null|array
	 */
	private static $settings = null;

	/**
	 * The maximum number of questions in the active survey.
	 * The free version supports one; Pro raises this via the filter.
	 *
	 * @return int
	 */
	public static function max_questions() {
		/**
		 * Filter the maximum number of questions in the active survey.
		 *
		 * @param int $max The maximum number of questions.
		 */
		return max( 1, (int) apply_filters( 'ppsfw_max_questions', 1 ) );
	}

	/**
	 * Default survey configuration.
	 * The survey ships disabled; store owners must review and enable it.
	 *
	 * @return array
	 */
	public static function default_survey() {
		return array(
			'enabled'      => 0,
			'question_ids' => array(),
			'position'     => 'below',
			'thank_you'    => __( 'Thanks for your feedback!', 'post-purchase-survey-for-woocommerce' ),
		);
	}

	/**
	 * The default "Other" option label.
	 *
	 * @return string
	 */
	public static function default_other_label() {
		return __( 'Other', 'post-purchase-survey-for-woocommerce' );
	}

	/**
	 * Default plugin settings (Settings page).
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'delete_data' => 0,
		);
	}

	/**
	 * The default answer options used for the seeded example question.
	 *
	 * @return array
	 */
	public static function default_answer_options() {
		return array(
			array(
				'value'   => 'search-engine',
				'label'   => __( 'Search engine (Google, Bing, etc.)', 'post-purchase-survey-for-woocommerce' ),
				'enabled' => 1,
			),
			array(
				'value'   => 'social-media',
				'label'   => __( 'Social media', 'post-purchase-survey-for-woocommerce' ),
				'enabled' => 1,
			),
			array(
				'value'   => 'friend-family',
				'label'   => __( 'Friend or family', 'post-purchase-survey-for-woocommerce' ),
				'enabled' => 1,
			),
			array(
				'value'   => 'online-ad',
				'label'   => __( 'Online advertisement', 'post-purchase-survey-for-woocommerce' ),
				'enabled' => 1,
			),
			array(
				'value'   => 'podcast-video',
				'label'   => __( 'Podcast or video', 'post-purchase-survey-for-woocommerce' ),
				'enabled' => 1,
			),
		);
	}

	/**
	 * The default question text (used for the seeded example question).
	 *
	 * @return string
	 */
	public static function default_question_text() {
		return __( 'How did you hear about us?', 'post-purchase-survey-for-woocommerce' );
	}

	/**
	 * Get the normalized survey configuration.
	 *
	 * @return array
	 */
	public static function survey() {
		if ( self::$survey !== null ) {
			return self::$survey;
		}

		$defaults = self::default_survey();
		$stored   = Options::instance()->get_group( 'survey', array() );

		if ( empty( $stored ) || ! is_array( $stored ) ) {
			self::$survey = $defaults;

			return self::$survey;
		}

		self::$survey = array(
			'enabled'      => Util::truthy( isset( $stored['enabled'] ) ? $stored['enabled'] : 0 ),
			'question_ids' => isset( $stored['question_ids'] ) && is_array( $stored['question_ids'] ) ? array_values( array_filter( array_map( 'absint', $stored['question_ids'] ) ) ) : array(),
			'position'     => isset( $stored['position'] ) && in_array( $stored['position'], array( 'below', 'above' ), true ) ? $stored['position'] : $defaults['position'],
			'thank_you'    => isset( $stored['thank_you'] ) && trim( $stored['thank_you'] ) !== '' ? trim( $stored['thank_you'] ) : $defaults['thank_you'],
		);

		return self::$survey;
	}

	/**
	 * Get the normalized display settings.
	 *
	 * @return array
	 */
	public static function settings() {
		if ( self::$settings !== null ) {
			return self::$settings;
		}

		$defaults = self::default_settings();
		$stored   = Options::instance()->get_group( 'settings', array() );

		if ( empty( $stored ) || ! is_array( $stored ) ) {
			self::$settings = $defaults;

			return self::$settings;
		}

		self::$settings = array(
			'delete_data' => Util::truthy( isset( $stored['delete_data'] ) ? $stored['delete_data'] : 0 ),
		);

		return self::$settings;
	}

	/**
	 * Clear the cached configuration (used after saving).
	 *
	 * @return void
	 */
	public static function flush_settings_cache() {
		self::$survey   = null;
		self::$settings = null;
	}

	/**
	 * Build a question array from a question post.
	 *
	 * @param int|\WP_Post $post The question post (or ID).
	 *
	 * @return null|array
	 */
	public static function question_from_post( $post ) {
		$post = get_post( $post );

		if ( ! $post || $post->post_type !== Plugin::posttype_question() ) {
			return null;
		}

		$options = get_post_meta( $post->ID, Plugin::meta_key_answers(), true );

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$other_label = get_post_meta( $post->ID, Plugin::meta_key_question_other_label(), true );

		if ( ! is_string( $other_label ) || trim( $other_label ) === '' ) {
			$other_label = self::default_other_label();
		}

		return array(
			'id'            => $post->ID,
			'text'          => get_the_title( $post ),
			'status'        => $post->post_status,
			/**
			 * Filter the answer options for a question.
			 *
			 * @param array $options Answer options: arrays of value, label, enabled.
			 * @param int   $question_id The question (post) ID.
			 */
			'options'       => apply_filters( 'ppsfw_answer_options', $options, $post->ID ),
			'other_enabled' => Util::truthy( get_post_meta( $post->ID, Plugin::meta_key_question_other(), true ) ),
			'other_label'   => trim( $other_label ),
		);
	}

	/**
	 * The questions selected for the survey, regardless of post status.
	 * Used by the admin; the front end uses active_questions().
	 *
	 * @return array Question arrays keyed by question (post) ID.
	 */
	public static function selected_questions() {
		$questions = array();

		foreach ( self::survey()['question_ids'] as $question_id ) {
			$question = self::question_from_post( $question_id );

			if ( $question ) {
				$questions[ $question['id'] ] = $question;
			}
		}

		return $questions;
	}

	/**
	 * The questions that display on the order-received page:
	 * selected, published, with at least one enabled answer, capped at max_questions().
	 *
	 * @return array Question arrays keyed by question (post) ID.
	 */
	public static function active_questions() {
		$questions = array();
		$max       = self::max_questions();

		foreach ( self::selected_questions() as $question ) {
			if ( count( $questions ) >= $max ) {
				break;
			}

			if ( $question['status'] !== 'publish' ) {
				continue;
			}

			if ( empty( self::enabled_options( $question ) ) ) {
				continue;
			}

			$questions[ $question['id'] ] = $question;
		}

		/**
		 * Filter the active survey questions.
		 *
		 * @param array $questions Question arrays keyed by question (post) ID.
		 */
		return apply_filters( 'ppsfw_get_questions', $questions );
	}

	/**
	 * Get a single active question by ID.
	 *
	 * @param int $question_id The question (post) ID.
	 *
	 * @return null|array
	 */
	public static function active_question( $question_id ) {
		$questions = self::active_questions();

		return isset( $questions[ $question_id ] ) ? $questions[ $question_id ] : null;
	}

	/**
	 * The enabled answer options for a question.
	 *
	 * @param array $question A question array.
	 *
	 * @return array
	 */
	public static function enabled_options( $question ) {
		if ( empty( $question['options'] ) || ! is_array( $question['options'] ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$question['options'],
				function ( $option ) {
					return is_array( $option ) && ! empty( $option['enabled'] ) && isset( $option['value'], $option['label'] ) && $option['value'] !== '' && $option['label'] !== '';
				}
			)
		);
	}

	/**
	 * Whether the survey is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$survey = self::survey();

		return (bool) $survey['enabled'];
	}

	/**
	 * The survey position on the order-received page.
	 *
	 * @return string
	 */
	public static function position() {
		$survey = self::survey();

		return $survey['position'];
	}

	/**
	 * The thank-you message shown after a response is saved.
	 *
	 * @return string
	 */
	public static function thank_you_message() {
		$survey = self::survey();

		return $survey['thank_you'];
	}

	/**
	 * Sanitize answer option rows posted as parallel arrays.
	 * Generates a stable value slug for new rows and keeps existing slugs
	 * so reports don't lose history when labels are edited.
	 *
	 * @param array $labels Posted labels.
	 * @param array $values Posted (possibly empty) stable values.
	 * @param array $enabled Posted enabled flags.
	 *
	 * @return array
	 */
	public static function sanitize_answer_rows( $labels, $values, $enabled ) {
		$labels  = is_array( $labels ) ? array_values( $labels ) : array();
		$values  = is_array( $values ) ? array_values( $values ) : array();
		$enabled = is_array( $enabled ) ? array_values( $enabled ) : array();

		$options = array();
		$used    = array( Plugin::other_value() => true );

		foreach ( $labels as $i => $label ) {
			if ( count( $options ) >= 50 ) {
				break;
			}

			$label = sanitize_text_field( $label );

			if ( $label === '' ) {
				continue;
			}

			$value = isset( $values[ $i ] ) ? sanitize_key( $values[ $i ] ) : '';

			if ( $value === '' || isset( $used[ $value ] ) ) {
				$value = self::generate_option_value( $label, $used );
			}

			$used[ $value ] = true;

			$options[] = array(
				'value'   => $value,
				'label'   => $label,
				'enabled' => Util::truthy( isset( $enabled[ $i ] ) ? $enabled[ $i ] : 1 ) ? 1 : 0,
			);
		}

		return $options;
	}

	/**
	 * Generate a unique, stable value slug for an answer option.
	 *
	 * @param string $label The option label.
	 * @param array  $used Values already in use (as keys).
	 *
	 * @return string
	 */
	public static function generate_option_value( $label, $used ) {
		$base = sanitize_key( str_replace( ' ', '-', remove_accents( strtolower( $label ) ) ) );
		$base = preg_replace( '/-{2,}/', '-', $base );
		$base = trim( substr( $base, 0, 50 ), '-' );

		if ( $base === '' ) {
			$base = 'option';
		}

		$value  = $base;
		$suffix = 2;

		while ( isset( $used[ $value ] ) ) {
			$value = $base . '-' . $suffix;
			++$suffix;
		}

		return $value;
	}
}
