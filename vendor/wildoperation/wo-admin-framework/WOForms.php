<?php
namespace PPS\Vendor\WOAdminFramework;

/**
 * Create form elements for use in meta or options.
 */
class WOForms {
	/**
	 * __construct()
	 */
	public function __construct() {}

	public static function form_elements_allowed_html() {
		return array_merge(
			wp_kses_allowed_html( 'post' ),
			array(
				'label'    => array(
					'class' => array(),
					'for'   => array(),
				),
				'select'   => array(
					'class'    => array(),
					'id'       => array(),
					'name'     => array(),
					'disabled' => array(),
				),
				'option'   => array(
					'value'    => array(),
					'disabled' => array(),
					'selected' => array(),
				),
				'optgroup' => array(),
				'input'    => array(
					'type'        => array(),
					'value'       => array(),
					'name'        => array(),
					'id'          => array(),
					'class'       => array(),
					'placeholder' => array(),
					'step'        => array(),
					'min'         => array(),
					'max'         => array(),
					'readonly'    => array(),
					'disabled'    => array(),
					'checked'     => array(),
					'selected'    => array(),
				),
				'textarea' => array(
					'class' => array(),
					'rows'  => array(),
					'cols'  => array(),
					'name'  => array(),
					'id'    => array(),
				),
			)
		);
	}

	/**
	 * Maybe add a class attribute to something.
	 *
	 * @param null $classes The classes to include in the attribute.
	 *
	 * @return string
	 */
	public function maybe_class( $classes = null ) {
		if ( $classes ) {

			if ( is_array( $classes ) ) {
				$classes = implode( ' ', $classes );
			}

			return ' class="' . esc_attr( $classes ) . '"';
		}

		return '';
	}

	/**
	 * Maybe add a disabled prop to something.
	 *
	 * @param bool $disabled If something is disabled.
	 *
	 * @return string
	 */
	private function maybe_disable( $disabled ) {
		if ( $disabled ) {
			return ' disabled';
		}

		return '';
	}

	/**
	 * Create a label for form element.
	 *
	 * @param string $id The ID of the form element.
	 * @param string $text The text for the label.
	 * @param array  $args Optional arguments.
	 *
	 * @return string|void
	 */
	public function label( $id, $text, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'classes'      => null,
				'display'      => true,
				'close'        => true,
				'allowed_html' => array(
					'a'      => array(
						'class'  => array(),
						'target' => array(),
						'rel'    => array(),
						'href'   => array(),
					),
					'br'     => array( 'class' => array() ),
					'em'     => array( 'class' => array() ),
					'strong' => array( 'class' => array() ),
					'span'   => array( 'class' => array() ),
					'code'   => array( 'class' => array() ),
				),
			)
		);

		$html  = '<label for="' . esc_attr( $id ) . '"';
		$html .= $this->maybe_class( $args['classes'] );
		$html .= '>';

		if ( $text ) {
			$html .= wp_kses( $text, $args['allowed_html'] );
		}

		if ( $args['close'] ) {
			$html .= '</label>';
		}

		if ( ! $args['display'] ) {
			return $html;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The framework has no context of what the plugin is doing and unsafe HTML may be desired.
		echo $html;
	}

	/**
	 * Creates a select dropdown on a settings page.
	 *
	 * @param string $name Form field name.
	 * @param array  $options The options in the select field.
	 * @param mixed  $current_value The current value to select.
	 * @param array  $args Optional args.
	 *
	 * @return string|void
	 */
	public function select( $name, $options, $current_value, $args = array() ) {

		$args = wp_parse_args(
			$args,
			array(
				'classes'    => null,
				'display'    => true,
				'id'         => null,
				'empty_text' => null,
				'disabled'   => false,
			)
		);

		if ( ! $args['id'] ) {
			$args['id'] = $name;
		}

		$html  = '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $args['id'] ) . '"';
		$html .= $this->maybe_class( $args['classes'] );
		$html .= $this->maybe_disable( $args['disabled'] );
		$html .= '>';

		if ( $args['empty_text'] ) {
			$html .= '<option value="" ' . selected( '', $current_value, false ) . '>' . esc_html( $args['empty_text'] ) . '</option>';
		}

		foreach ( $options as $option_value => $text ) {
			$disabled          = false;
			$this_option_value = $option_value;

			if ( substr( $this_option_value, 0, 9 ) === 'disabled:' ) {
				$disabled     = true;
				$this_option_value = substr( $this_option_value, 9 );
			} elseif ( substr( $this_option_value, 0, 16 ) === 'woadmin_divider:' ) {
				$this_option_value = '';
			}

			$html .= '<option value="' . esc_attr( $this_option_value ) . '" ';

			if ( $disabled ) {
				$html .= 'disabled';
			}

			if ( $this_option_value !== '' ) {
				$html .= selected( $this_option_value, $current_value, false );
			}

			$html .= '>' . esc_html( $text ) . '</option>';
		}

		$html .= '</select>';

		if ( ! $args['display'] ) {
			return $html;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The framework has no context of what the plugin is doing and unsafe HTML may be desired.
		echo $html;
	}

	/**
	 * Creates an input of specified $type.
	 *
	 * @param string $name Form field name.
	 * @param mixed  $current_value The current value to select.
	 * @param string $type The type of input (e.g., 'text' or 'number').
	 * @param array  $args Optional args.
	 *
	 * @return string|void
	 */
	public function input( $name, $current_value, $type = 'text', $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'classes'     => null,
				'display'     => true,
				'id'          => null,
				'placeholder' => null,
				'min'         => null,
				'max'         => null,
				'disabled'    => false,
				'readonly'    => false,
				'step'        => null,
				'accept'      => null,
				'data'        => array(),
			)
		);

		if ( ! $args['id'] ) {
			$args['id'] = $name;
		}

		$html  = '<input type="' . esc_attr( $type ) . '" value="' . esc_attr( $current_value ) . '" name="' . esc_attr( $name ) . '" id="' . esc_attr( $args['id'] ) . '"';
		$html .= $this->maybe_class( $args['classes'] );

		if ( $args['placeholder'] ) {
			$html .= ' placeholder="' . esc_attr( $args['placeholder'] ) . '"';
		}

		if ( $args['step'] ) {
			$html .= ' step="' . esc_attr( $args['step'] ) . '"';
		}

		if ( $args['min'] ) {
			$html .= ' min="' . esc_attr( $args['min'] ) . '"';
		}

		if ( $args['max'] ) {
			$html .= ' max="' . esc_attr( $args['max'] ) . '"';
		}

		if ( $args['accept'] ) {
			$html .= ' accept="' . esc_attr( $args['accept'] ) . '"';
		}

		if ( ! empty( $args['data'] ) ) {
			foreach ( $args['data'] as $key => $value ) {
				$html .= ' data-' . esc_html( $key ) . '="' . esc_attr( $value ) . '"';
			}
		}

		$html .= $this->maybe_disable( $args['disabled'] );

		if ( $args['readonly'] === true ) {
			$html .= ' readonly';
		}

		$html .= ' />';

		if ( ! $args['display'] ) {
			return $html;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The framework has no context of what the plugin is doing and unsafe HTML may be desired.
		echo $html;
	}

	/**
	 * Creates a textarea.
	 *
	 * @param string $name Form field name.
	 * @param mixed  $current_value The current value of the textarea.
	 * @param array  $args Optional args.
	 *
	 * @return string|void
	 */
	public function textarea( $name, $current_value, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'classes'  => null,
				'display'  => true,
				'id'       => null,
				'rows'     => 10,
				'cols'     => 90,
				'disabled' => false,
			)
		);

		if ( ! $args['id'] ) {
			$args['id'] = $name;
		}

		$html  = '<textarea name="' . esc_attr( $name ) . '" id="' . esc_attr( $args['id'] ) . '" rows="' . esc_attr( $args['rows'] ) . '" cols="' . esc_attr( $args['cols'] ) . '"';
		$html .= $this->maybe_class( $args['classes'] );
		$html .= $this->maybe_disable( $args['disabled'] );
		$html .= '>' . $current_value . '</textarea>';

		if ( ! $args['display'] ) {
			return $html;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The framework has no context of what the plugin is doing and unsafe HTML may be desired.
		echo $html;
	}

	/**
	 * Creates a checkbox.
	 *
	 * @param string $name Form field name.
	 * @param mixed  $current_value The current value.
	 * @param mixed  $checked_value The value when checked.
	 * @param array  $args Optional args to pass to WOForms.
	 *
	 * @return string|void
	 */
	public function checkbox( $name, $current_value, $checked_value = 1, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'classes'  => null,
				'display'  => true,
				'id'       => null,
				'disabled' => false,
			)
		);

		if ( ! $args['id'] ) {
			$args['id'] = $name;
		}

		$html  = '<input type="checkbox" value="' . esc_attr( $checked_value ) . '" name="' . esc_attr( $name ) . '" id="' . esc_attr( $args['id'] ) . '"' . checked( $checked_value, $current_value, false );
		$html .= $this->maybe_class( $args['classes'] );
		$html .= $this->maybe_disable( $args['disabled'] );
		$html .= ' />';

		if ( ! $args['display'] ) {
			return $html;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The framework has no context of what the plugin is doing and unsafe HTML may be desired.
		echo $html;
	}

	/**
	 * Creates a group of checkboxes or radios.
	 *
	 * @param string $name Form field name.
	 * @param array  $options A key/value array of inputs.
	 * @param mixed  $current_value The current values that should be checked.
	 * @param string $type Type of input to display.
	 * @param array  $args Optional args to pass to WOForms.
	 *
	 * @return string|void
	 */
	public function inputgroup( $name, $options, $current_value, $type = 'radio', $args = array() ) {

		if ( empty( $options ) ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'classes'      => array( 'woforms-input-group' ),
				'display'      => true,
				'label_wrap'   => false,
				'text_classes' => array(),
				'id'           => null,
				'empty_text'   => null,
				'wrap'         => true,
			)
		);

		if ( ! $args['id'] ) {
			$args['id'] = str_replace( '[]', '', $name );
		}

		if ( $args['classes'] && ! $args['wrap'] ) {
			$args['wrap'] = true;
		}

		if ( $args['label_wrap'] ) {
			$args['wrap'] = true;
			$wrap_element = 'label';
		} else {
			$wrap_element = 'span';
		}

		if ( $type === 'checkbox' ) {
			if ( $current_value === null || $current_value === false ) {
				$current_value = array();
			}

			$current_value = WOUtilities::arrayify( $current_value );

			if ( substr( $name, -2 ) !== '[]' ) {
				$name .= '[]';
			}
		}

		$html = '';
		$idx  = 1;

		foreach ( $options as $value => $text ) {
			$id = $args['id'] . '_' . $idx;

			$disabled = false;
			if ( substr( $value, 0, 9 ) === 'disabled:' ) {
				$disabled = true;
				$value    = substr( $value, 9 );
			}

			if ( $args['wrap'] ) {
				if ( $wrap_element !== 'label' ) {
					$html .= '<' . $wrap_element . '>';
				} else {
					$html .= $this->label(
						$id,
						'',
						array(
							'display' => false,
							'close'   => false,
						)
					);
				}
			}

			$html .= '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"';

			if ( $type === 'radio' ) {
				$html .= checked( $value, $current_value, false );
			} else {
				$html .= checked( true, in_array( $value, $current_value ), false );
			}

			$html .= $this->maybe_disable( $disabled );

			$html .= ' />';

			if ( ! $args['label_wrap'] ) {
				$html .= $this->label(
					$id,
					$text,
					array(
						'display' => false,
						'classes' => $args['text_classes'],
					)
				);
			} else {
				$html .= '<span' . $this->maybe_class( $args['text_classes'] ) . '>' . wp_kses(
					$text,
					array(
						'a'      => array(
							'class'  => array(),
							'target' => array(),
							'rel'    => array(),
							'href'   => array(),
						),
						'br'     => array( 'class' => array() ),
						'em'     => array( 'class' => array() ),
						'strong' => array( 'class' => array() ),
						'span'   => array( 'class' => array() ),
						'code'   => array( 'class' => array() ),
					)
				) . '</span>';
			}

			if ( $args['wrap'] ) {
				$html .= '</' . $wrap_element . '>';
			}

			++$idx;
		}

		if ( $args['wrap'] ) {
			$html = '<div' . $this->maybe_class( $args['classes'] ) . '>' . $html . '</div>';
		}

		if ( ! $args['display'] ) {
			return $html;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The framework has no context of what the plugin is doing and unsafe HTML may be desired.
		echo $html;
	}

	/**
	 * Creates a message.
	 *
	 * @param string $message The message to display.
	 * @param array  $args Optional args to pass to WOForms.
	 *
	 * @return string
	 */
	public function message( $message, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'classes'       => array( 'woforms-message' ),
				'display'       => true,
				'allowed_html'  => self::form_elements_allowed_html(),
				'element'       => 'p',
				'inner_element' => null,
			)
		);

		$html  = '<' . wp_strip_all_tags( $args['element'] );
		$html .= $this->maybe_class( $args['classes'] );
		$html .= '>';

		if ( $args['inner_element'] ) {
			$html .= '<' . wp_strip_all_tags( $args['inner_element'] ) . '>';
		}

		$html .= $message;

		if ( $args['inner_element'] ) {
			$html .= '</' . wp_strip_all_tags( $args['inner_element'] ) . '>';
		}

		$html .= '</' . wp_strip_all_tags( $args['element'] ) . '>';

		if ( ! $args['display'] ) {
			return wp_kses( $html, $args['allowed_html'] );
		}

		echo wp_kses( $html, $args['allowed_html'] );
	}
}
