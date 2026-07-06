<?php
namespace PPS\Vendor\WOAdminFramework;

use PPS\Vendor\WOAdminFramework\WOOptions;

/**
 * Class for working with the Settings API.
 * Extends framework WOOptions class for working with options.
 */
class WOSettings extends WOOPtions {

	/**
	 * An instance of WOForms
	 *
	 * @var WOForms
	 */
	public $wo_forms;

	/**
	 * __construct()
	 *
	 * @param string $ns The current namespace.
	 */
	public function __construct( $ns ) {
		parent::__construct( $ns );

		$this->wo_forms = new WOForms();
	}

	/**
	 * Get an ID either from the string provided or an array.
	 *
	 * @param string|array $id The variable to pull the ID from.
	 *
	 * @return string
	 */
	private function id( $id ) {
		if ( is_array( $id ) ) {
			return $id[ array_key_first( $id ) ];
		}

		return $id;
	}

	/**
	 * Create a name from an ID.
	 * Formats as an HTML array if an array is provided.
	 *
	 * @param string|array $id The variable to pull the name from.
	 *
	 * @return string
	 */
	private function name( $id ) {
		if ( is_array( $id ) ) {
			$key = array_key_first( $id );
			return $key . '[' . $id[ $key ] . ']';
		}

		return $id;
	}

	/**
	 * Start the page wrapper.
	 *
	 * @return void
	 */
	public function start() {
		?>
		<div class="wrap">
		<?php
	}

	/**
	 * End the page wrapper.
	 *
	 * @return void
	 */
	public function end() {
		?>
		</div>
		<?php
	}

	/**
	 * Format the page title.
	 * Page title should already be translated.
	 *
	 * @param string $title The page title.
	 *
	 * @return void
	 */
	public function title( $title ) {
		?>
		<h2><?php echo esc_html( $title ); ?></h2>
		<?php
	}

	/**
	 * The settings form tag.
	 *
	 * @param string $action The form action.
	 * @param string $method The form method.
	 *
	 * @return void
	 */
	public function form_start( $action = 'options.php', $method = 'post', $formdata = false ) {
		?>
		<form method="<?php echo esc_attr( $method ); ?>" action="<?php echo esc_url( $action ); ?>"
								<?php
								if ( $formdata ) :
									?>
			enctype="multipart/form-data"<?php endif; ?>>
		<?php
	}

	/**
	 * End form tag.
	 *
	 * @return void
	 */
	public function form_end() {
		?>
		</div>
		</form>
		<?php
	}

	/**
	 * Gets the URL to a tab on a settings page.
	 *
	 * @param string $tab_key The key of the tab.
	 * @param string $admin_url The URL to append the parameter to.
	 *
	 * @return string
	 */
	public function get_tab_url( $tab_key, $admin_url ) {
		return add_query_arg(
			array(
				'tab' => $tab_key,
			),
			$admin_url
		);
	}

	/**
	 * Creates page tabs from a settings array.
	 *
	 * @param array  $settings An array of settings.
	 * @param string $admin_url The URL this tab appears on.
	 *
	 * @return array
	 */
	public function create_tabs_from_settings( $settings, $admin_url ) {

		$tabs = array();

		foreach ( $settings as $key => $group ) {
			$opt_key = $this->key( $key );
			$tabs[]  = array(
				'key'  => $opt_key,
				'text' => $group['title'],
				'url'  => $this->get_tab_url( $opt_key, $admin_url ),
			);
		}

		return $tabs;
	}

	/**
	 * Displays settings tabs on a page.
	 * Tab text should already be translated.
	 *
	 * @param array       $tabs An array of tabs to display.
	 * @param null|string $active_tab The currently active tab.
	 *
	 * @return void
	 */
	public function display_tabs( $tabs = array(), $active_tab = null ) {
		if ( empty( $tabs ) ) {
			return;
		}
		?>
		<div class="nav-tab-wrapper woadmin-nav-tab-wrapper">
			<?php foreach ( $tabs as $tab ) : ?>
				<a href="<?php echo esc_url( $tab['url'] ); ?>" class="nav-tab<?php echo $active_tab === $tab['key'] ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $tab['text'] ); ?></a>
			<?php endforeach; ?>
		</div>
		<div class="woadmin-form-inner">
		<?php
	}

	/**
	 * Creates a complete settings page.
	 *
	 * @param string $admin_title The title of the page.
	 * @param string $admin_url The URL of the settings page.
	 * @param array  $settings A settings array from which to build our settings.
	 *
	 * @return void
	 */
	public function settings_page( $admin_title, $admin_url, $settings, $footer_content = array() ) {
		$this->start();
		$this->title( $admin_title );
		$this->form_start();

		settings_errors();

		/**
		 * Tabs
		 */
		$tabs = $this->create_tabs_from_settings( $settings, $admin_url );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Ignoring because we aren't saving any data and this functiopn is called by add_submenu_page (which checks capabilities)
		$active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : $tabs[0]['key'];
		$this->display_tabs( $tabs, $active_tab );

		$active_tab_has_fields = false;

		foreach ( $tabs as $tab ) {
			if ( $active_tab !== $tab['key'] ) {
				continue;
			}
			settings_fields( $tab['key'] );
			do_settings_sections( $tab['key'] );
		}

		foreach ( $settings as $key => $group ) {
			if ( $this->key( $key ) !== $active_tab ) {
				continue;
			}

			if ( ! empty( $group['sections'] ) ) {
				foreach ( $group['sections'] as $section ) {
					if ( ! empty( $section['fields'] ) ) {
						$active_tab_has_fields = true;
						break;
					}
				}
			}

			break;
		}

		/**
		 * End page
		 */
		if ( $active_tab_has_fields ) {
			submit_button();
		}

		if ( ! empty( $footer_content ) ) {
			foreach ( $footer_content as $key => $callback ) {
				if ( $key !== $active_tab ) {
					continue;
				}
				if ( is_callable( $callback ) ) {
					?>
					<div class="woadmin-footer-content">
					<?php call_user_func( $callback ); ?>
					</div>
					<?php
				}
			}
		}
		$this->form_end();
		$this->end();
	}

	/**
	 * Creates settings sections and registers fields.
	 *
	 * @param array $settings A settings array from which to build our settings.
	 * @param mixed $class_instance The class for our settings field callbacks.
	 *
	 * @return void
	 */
	public function add_sections_and_settings( $settings, $class_instance ) {
		foreach ( $settings as $key => $group ) {
			$defaults = isset( $group['initialize'] ) ? $group['initialize'] : array();
			$this->initialize( $key, $defaults );

			/**
			 * This is the overall group
			 * It's a tab, and also the option key for the DB
			 */
			$opt_key = $this->key( $key );

			foreach ( $group['sections'] as $section_key => $section ) {
				$section_key = $this->key( $section_key );

				/**
				 * These are sub-sections within the option group
				 */
				add_settings_section(
					$section_key . '_settings_section',
					isset( $section['title'] ) ? $section['title'] : null,
					array( $class_instance, 'settings_callback_' . $section_key ),
					$opt_key
				);

				/**
				 * Add fields
				 */
				if ( isset( $section['fields'] ) && ! empty( $section['fields'] ) ) {
					foreach ( $section['fields'] as $field_key => $value ) {
						$args = array();

						if ( is_array( $value ) ) {
							$restricted = isset( $value['restricted'] ) ? $value['restricted'] : null;

							if ( $restricted ) {
								$restricted_class = $this->ns . '-mode-restrict ' . $this->ns . '-mode-restrict--' . sanitize_title( $restricted );

								$restricted_val = isset( $value['restricted_val'] ) ? $value['restricted_val'] : '';

								if ( $restricted_val ) {
									$restricted_class .= ' ' . $this->ns . '-mode-restrict--' . sanitize_title( $restricted . '- ' . $restricted_val );
								}

								$args['class'] = $restricted_class;
							}

							$value = $value['title'];
						}

						add_settings_field(
							$field_key,
							$value,
							array( $class_instance, 'field_' . $this->ns . '_' . $field_key ),
							$opt_key,
							$section_key . '_settings_section',
							$args
						);
					}
				}
			}
		}

		/**
		 * Register settings
		 */
		foreach ( $settings as $key => $group ) {
			$key = $this->key( $key );
			register_setting(
				$key,
				$key,
				array(
					'sanitize_callback' => array( $class_instance, 'sanitize_' . $key ),
				),
			);
		}
	}

	/**
	 * Creates a form label using our settings ID.
	 *
	 * @param string $id Form field ID.
	 * @param string $text Text of the label.
	 * @param array  $args Optional args to pass to WOForms.
	 *
	 * @return string
	 */
	public function label( $id, $text, $args = array() ) {
		$id = $this->id( $id );
		return $this->wo_forms->label( $id, $text, $args );
	}

	/**
	 * Creates a select dropdown on a settings page.
	 *
	 * @param string $id Form field ID.
	 * @param array  $options The options in the select field.
	 * @param mixed  $current_value The current value to select.
	 * @param array  $args Optional args to pass to WOForms.
	 *
	 * @return string
	 */
	public function select( $id, $options, $current_value = null, $args = array() ) {
		$name       = $this->name( $id );
		$args['id'] = $this->id( $id );

		if ( ! $current_value && isset( $args['default'] ) ) {
			$current_value = $args['default'];
		}

		return $this->wo_forms->select( $name, $options, $current_value, $args );
	}

	/**
	 * Creates a checkbox on a settings page.
	 *
	 * @param string $id Form field ID.
	 * @param mixed  $current_value The current value.
	 * @param mixed  $checked_value The value when checked.
	 * @param array  $args Optional args to pass to WOForms.
	 *
	 * @return string
	 */
	public function checkbox( $id, $current_value, $checked_value = 1, $args = array() ) {
		$name       = $this->name( $id );
		$args['id'] = $this->id( $id );

		return $this->wo_forms->checkbox( $name, $current_value, $checked_value, $args );
	}

	/**
	 * Creates a textarea on a settings page.
	 *
	 * @param string $id Form field ID.
	 * @param mixed  $current_value The current value of the textarea.
	 * @param array  $args Optional args to pass to WOForms.
	 *
	 * @return string
	 */
	public function textarea( $id, $current_value, $args = array() ) {
		$name       = $this->name( $id );
		$args['id'] = $this->id( $id );

		return $this->wo_forms->textarea( $name, $current_value, $args );
	}

	/**
	 * Creates an input of specified $type on a settings page.
	 *
	 * @param string $id Form field ID.
	 * @param mixed  $current_value The current value of the input.
	 * @param string $type The type of input (e.g., 'text' or 'number').
	 * @param array  $args Optional args to pass to WOForms.
	 *
	 * @return string
	 */
	public function input( $id, $current_value, $type = 'text', $args = array() ) {
		$name       = $this->name( $id );
		$args['id'] = $this->id( $id );

		return $this->wo_forms->input( $name, $current_value, $type, $args );
	}

	/**
	 * Creates an input of specified $type on a settings page.
	 *
	 * @param string $id Form field ID.
	 * @param mixed  $current_value The current value of the input.
	 * @param array  $args Optional args to pass to WOForms.
	 *
	 * @return string
	 */
	public function file( $id, $current_value, $args = array() ) {
		$name       = $this->name( $id );
		$args['id'] = $this->id( $id );

		return $this->wo_forms->input( $name, $current_value, 'file', $args );
	}

	/**
	 * Creates an input of specified $type on a settings page.
	 *
	 * @param string $id Form field ID.
	 * @param mixed  $current_value The current value of the input.
	 * @param array  $args Optional args to pass to WOForms.
	 *
	 * @return string
	 */
	public function color( $id, $current_value, $args = array() ) {
		$name            = $this->name( $id );
		$args['id']      = $this->id( $id );
		$args['classes'] = array( 'woadmin-color-picker' );
		$args['data']    = array(
			'default-color' => isset( $args['default_color'] ) ? $args['default_color'] : '#ffffff',
		);

		return $this->wo_forms->input( $name, $current_value, 'text', $args );
	}

	/**
	 * Creates an input of specified $type on a settings page.
	 *
	 * @param string $id Form field ID.
	 * @param mixed  $current_value The current value of the input.
	 * @param array  $args Optional args to pass to WOForms.
	 *
	 * @return string
	 */
	public function media( $id, $current_value, $args = array() ) {
		$name                = $this->name( $id );
		$args['id']          = $this->id( $id );
		$args['display']     = false;
		$args['classes']     = array( 'woadmin-media-input' );
		$args['button_text'] = isset( $args['button_text'] ) ? $args['button_text'] : __( 'Select File', 'wo-admin-framework' );

		$input  = '<div class="woadmin-media-upload">';
		$input .= $this->wo_forms->input( $name, $current_value, 'hidden', $args );
		$input .= '<button class="woadmin-media-button button">' . esc_html( $args['button_text'] ) . '</button>';
		$input .= '</div>';

		return $input;
	}

	/**
	 * Creates an input of specified $type on a settings page.
	 *
	 * @param string $id Form field ID.
	 * @param mixed  $current_value The current value of the input.
	 * @param array  $args Optional args to pass to WOForms.
	 *
	 * @return string
	 */
	public function image( $id, $current_value, $args = array() ) {
		$name       = $this->name( $id );
		$args['id'] = $this->id( $id );

		$current_value = isset( $current_value ) ? intval( $current_value ) : null;

		$input = $this->media( $id, $current_value, $args );

		$input .= '<div class="woadmin-media-preview">';
		if ( $current_value ) {
			$attach_url = wp_get_attachment_image_url( $current_value, 'thumbnail' );
			$input     .= '<img src="' . esc_url( $attach_url ) . '" alt="" />';
			$input     .= '<button class="woadmin-media-remove">' . __( 'X', 'wo-admin-framework' ) . '</button>';
		}

		$input .= '</div>';

		echo $input;
	}

	/**
	 * Creates a group of checkboxes on a settings page.
	 *
	 * @param string $id Form field ID.
	 * @param array  $checkboxes A key/value array of checkboxes.
	 * @param array  $current_values An array of current values that should be checked.
	 * @param array  $args Optional args to pass to WOForms.
	 *
	 * @return string
	 */
	public function checkgroup( $id, $checkboxes, $current_values = array(), $args = array() ) {
		$name       = $this->name( $id );
		$args['id'] = $this->id( $id );
		return $this->wo_forms->inputgroup( $name, $checkboxes, $current_values, 'checkbox', $args );
	}

	/**
	 * Creates a group of radios on a settings page.
	 *
	 * @param string $id Form field ID.
	 * @param array  $radios A key/value array of checkboxes.
	 * @param mixed  $current_value The current value to select.
	 * @param array  $args Optional args to pass to WOForms.
	 *
	 * @return string
	 */
	public function radiogroup( $id, $radios, $current_value = null, $args = array() ) {
		$name       = $this->name( $id );
		$args['id'] = $this->id( $id );
		return $this->wo_forms->inputgroup( $name, $radios, $current_value, 'radio', $args );
	}

	/**
	 * Creates a message on a settings page.
	 *
	 * @param string $message The message to display.
	 * @param array  $args Optional args to pass to WOForms.
	 *
	 * @return string
	 */
	public function message( $message, $args = array() ) {
		return $this->wo_forms->message( $message, $args );
	}

	/**
	 * Default sanitization for settings. Treats everything as a text field or returns null.
	 *
	 * @param mixed $value The value to sanitize.
	 *
	 * @return mixed
	 */
	public function sanitize_default( $value ) {
		if ( ! $value ) {
			return null;
		}

		return sanitize_text_field( $value );
	}

	/**
	 * A basic sanitization function that checks for capability and then uses sanitize_default if allowed.
	 *
	 * @param mixed $input The input to sanitize.
	 * @param mixed $capability The capability to check.
	 *
	 * @return mixed
	 */
	public function sanitize_input_basic( $input, $capability ) {
		if ( ! current_user_can( $capability ) ) {
			die();
		}

		if ( ! $input ) {
			return null;
		}

		$output = array();

		if ( $input ) {
			if ( is_array( $input ) ) {
				foreach ( $input as $key => $value ) {
					$output[ $key ] = $this->sanitize_default( $value );
				}
			} else {
				$output = $this->sanitize_default( $input );
			}
		}

		return $output;
	}
}
