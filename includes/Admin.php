<?php
namespace PPS;

use PPS\Vendor\WOAdminFramework\WOAdmin;
use PPS\Vendor\WOAdminFramework\WOSettings;

/**
 * Admin class that sets up the plugin menu, pages, notices, and display settings.
 * Extends WOAdmin framework class.
 */
class Admin extends WOAdmin {
	/**
	 * WOSettings framework instance.
	 *
	 * @var WOSettings
	 */
	protected $sf;

	/**
	 * The custom admin menu hooks created during the admin_menu hook.
	 *
	 * @var array
	 */
	public $admin_menu_hooks;

	/**
	 * __construct
	 */
	public function __construct() {
		$this->admin_menu_hooks = array();
	}

	/**
	 * Create hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'create_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_notices', array( $this, 'survey_disabled_notice' ) );
		add_filter( 'plugin_action_links_' . PPS_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );

		/**
		 * Settings are saved through options.php, which defaults to manage_options.
		 * Allow shop managers (manage_woocommerce) to save this plugin's settings.
		 */
		add_filter(
			'option_page_capability_' . $this->sf()->key( 'settings' ),
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
	 * Add links to the plugin screen.
	 *
	 * @param array $links Existing links passed into this function.
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$action_links = array(
			array(
				'title' => __( 'Survey', 'post-purchase-survey-for-woocommerce' ),
				'url'   => self::survey_admin_url(),
			),
			array(
				'title' => __( 'Settings', 'post-purchase-survey-for-woocommerce' ),
				'url'   => self::settings_admin_url(),
			),
		);

		$plugin_links = array();

		foreach ( $action_links as $action_link ) {
			$plugin_links[ Util::ns( sanitize_title( $action_link['title'] ), '_' ) ] = '<a href="' . esc_url( $action_link['url'] ) . '">' . esc_html( $action_link['title'] ) . '</a>';
		}

		return array_merge( $plugin_links, $links );
	}

	/**
	 * The plugin settings array (Settings page).
	 *
	 * @return array
	 */
	public static function settings() {
		return array(
			'settings' => array(
				'title'      => __( 'Settings', 'post-purchase-survey-for-woocommerce' ),
				'initialize' => Survey::default_settings(),
				'sections'   => array(
					'data' => array(
						'title'  => __( 'Data', 'post-purchase-survey-for-woocommerce' ),
						'fields' => array(
							'delete_data' => __( 'Uninstall', 'post-purchase-survey-for-woocommerce' ),
						),
					),
				),
			),
		);
	}

	/**
	 * The parent menu slug (the question post type list).
	 *
	 * @return string
	 */
	public static function parent_slug() {
		return 'edit.php?post_type=' . Plugin::posttype_question();
	}

	/**
	 * Create the submenu pages under the question post type menu.
	 * The post type itself provides the top-level menu and the Questions list.
	 *
	 * @return void
	 */
	public function create_admin_menu() {
		$parent = self::parent_slug();

		$this->admin_menu_hooks['survey'] = add_submenu_page(
			$parent,
			__( 'Survey', 'post-purchase-survey-for-woocommerce' ),
			__( 'Survey', 'post-purchase-survey-for-woocommerce' ),
			Plugin::capability(),
			self::admin_slug( 'survey' ),
			array( $this, 'survey_page' )
		);

		$this->admin_menu_hooks['reports'] = add_submenu_page(
			$parent,
			__( 'Reports', 'post-purchase-survey-for-woocommerce' ),
			__( 'Reports', 'post-purchase-survey-for-woocommerce' ),
			Plugin::capability(),
			self::admin_slug( 'reports' ),
			array( $this, 'reports_page' )
		);

		$this->admin_menu_hooks['settings'] = add_submenu_page(
			$parent,
			__( 'Settings', 'post-purchase-survey-for-woocommerce' ),
			__( 'Settings', 'post-purchase-survey-for-woocommerce' ),
			Plugin::capability(),
			self::admin_slug( 'settings' ),
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Register the display settings from the settings array.
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
	 * Creates an admin page slug from an optional sub string.
	 *
	 * @param null|string $sub Optional sub page slug.
	 *
	 * @return string
	 */
	public static function admin_slug( $sub = null ) {
		$slug = Util::ns( 'survey-plugin' );

		if ( $sub ) {
			$slug = Util::ns( sanitize_title( $sub ) );
		}

		return $slug;
	}

	/**
	 * The admin URL for a plugin subpage.
	 *
	 * @param string $sub The sub page slug.
	 *
	 * @return string
	 */
	public static function page_admin_url( $sub ) {
		return admin_url( self::parent_slug() . '&page=' . self::admin_slug( $sub ) );
	}

	/**
	 * The admin_url for the Survey page.
	 *
	 * @return string
	 */
	public static function survey_admin_url() {
		return self::page_admin_url( 'survey' );
	}

	/**
	 * The admin_url for the Reports page.
	 *
	 * @return string
	 */
	public static function reports_admin_url() {
		return self::page_admin_url( 'reports' );
	}

	/**
	 * The admin_url for the Settings page.
	 *
	 * @return string
	 */
	public static function settings_admin_url() {
		return self::page_admin_url( 'settings' );
	}

	/**
	 * Display the Survey page.
	 *
	 * @return void
	 */
	public function survey_page() {
		$this->maybe_no_published_question_warning();

		$this->sf()->settings_page(
			__( 'Post-Purchase Survey', 'post-purchase-survey-for-woocommerce' ),
			self::survey_admin_url(),
			AdminSurvey::settings()
		);
	}

	/**
	 * Warn when the survey is enabled but no published question will display.
	 *
	 * @return void
	 */
	protected function maybe_no_published_question_warning() {
		if ( ! Survey::is_enabled() || ! empty( Survey::active_questions() ) ) {
			return;
		}

		?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'The survey is enabled, but it has no published question with enabled answers, so customers will not see it. Publish a selected question below (or select a published one).', 'post-purchase-survey-for-woocommerce' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Display the Reports page.
	 *
	 * @return void
	 */
	public function reports_page() {
		$sf = $this->sf();

		$sf->start();
		$sf->title( __( 'Reports', 'post-purchase-survey-for-woocommerce' ) );

		$reports = new AdminReports();
		$reports->render();

		$sf->end();
	}

	/**
	 * Display the Settings page.
	 *
	 * @return void
	 */
	public function settings_page() {
		$this->sf()->settings_page(
			__( 'Post-Purchase Survey', 'post-purchase-survey-for-woocommerce' ),
			self::settings_admin_url(),
			self::settings()
		);
	}

	/**
	 * Show an admin notice on WooCommerce and plugin screens while the survey is disabled.
	 *
	 * @return void
	 */
	public function survey_disabled_notice() {
		if ( Survey::is_enabled() || ! current_user_can( Plugin::capability() ) ) {
			return;
		}

		if ( ! $this->is_screen( $this->notice_screen_ids() ) ) {
			return;
		}

		?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'The Post-Purchase Survey is not currently enabled, so customers will not see it after checkout.', 'post-purchase-survey-for-woocommerce' ); ?>
				<a href="<?php echo esc_url( self::survey_admin_url() ); ?>"><?php esc_html_e( 'Enable the survey', 'post-purchase-survey-for-woocommerce' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * The screens that show the survey-disabled notice:
	 * WooCommerce screens, this plugin's screens, and the Plugins screen.
	 *
	 * @return array
	 */
	protected function notice_screen_ids() {
		$screen_ids = $this->plugin_screen_ids();

		$screen_ids[] = 'plugins';

		if ( function_exists( 'wc_get_screen_ids' ) ) {
			$screen_ids = array_merge( $screen_ids, wc_get_screen_ids() );
		}

		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screen_ids[] = wc_get_page_screen_id( 'shop-order' );
		}

		return array_unique( array_filter( $screen_ids ) );
	}

	/**
	 * This plugin's own screen IDs (menu pages + question post type screens).
	 *
	 * @return array
	 */
	public function plugin_screen_ids() {
		return array_merge(
			array_values( $this->admin_menu_hooks ),
			array(
				Plugin::posttype_question(),
				'edit-' . Plugin::posttype_question(),
			)
		);
	}

	/**
	 * Check the current screen against an array of screen IDs.
	 *
	 * @param array $screen_ids Screen IDs to compare against.
	 *
	 * @return bool
	 */
	public function is_screen( $screen_ids = array() ) {

		$screen = get_current_screen();

		if ( ! isset( $screen->id ) ) {
			return false;
		}

		if ( ! $screen_ids ) {
			$screen_ids = $this->plugin_screen_ids();
		}

		$screen_ids = Util::arrayify( $screen_ids );

		return in_array( $screen->id, $screen_ids, true );
	}

	/**
	 * Enqueue admin scripts and styles on this plugin's screens.
	 * The question edit screen is handled by AdminQuestionMeta.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		if ( ! $this->is_screen() ) {
			return;
		}

		$this->enqueue_woadmin_styles();
		wp_enqueue_style( Util::ns( 'admin' ), Plugin::assets_url() . 'css/admin.css', array( 'woadmin' ), Plugin::asset_version( 'css/admin.css' ) );

		/**
		 * The Survey page question picker.
		 */
		if ( isset( $this->admin_menu_hooks['survey'] ) && $this->is_screen( array( $this->admin_menu_hooks['survey'] ) ) ) {
			$handle = Util::ns( 'survey-admin' );

			wp_register_script(
				$handle,
				Plugin::assets_url() . 'js/survey-admin.js',
				array( 'jquery', 'jquery-ui-core', 'jquery-ui-sortable', 'jquery-ui-autocomplete' ),
				Plugin::asset_version( 'js/survey-admin.js' ),
				array( 'in_footer' => true )
			);
			wp_enqueue_script( $handle );

			Util::enqueue_script_data(
				$handle,
				array(
					'ajax_url'   => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'pps_admin' ),
					'field_name' => $this->sf()->key( 'survey' ) . '[question_ids][]',
					'i18n'       => array(
						'remove' => __( 'Remove', 'post-purchase-survey-for-woocommerce' ),
						'edit'   => __( 'Edit', 'post-purchase-survey-for-woocommerce' ),
					),
				),
				'pps_survey_admin'
			);
		}
	}

	/**
	 * Call back for the data settings section.
	 *
	 * @return void
	 */
	public function settings_callback_pps_data() {}

	/**
	 * Delete data on uninstall field.
	 *
	 * @return void
	 */
	public function field_pps_delete_data() {
		$id = array( $this->sf()->key( 'settings' ) => 'delete_data' );

		$this->sf()->checkbox( $id, $this->sf()->get( 'delete_data', 'settings' ) );
		$this->sf()->label( $id, esc_html__( 'Delete all plugin data (responses, questions, settings, order meta) when the plugin is uninstalled', 'post-purchase-survey-for-woocommerce' ) );
	}

	/**
	 * Sanitize the plugin settings group.
	 *
	 * @param array $input The input to sanitize.
	 *
	 * @return array
	 */
	public function sanitize_pps_settings( $input ) {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die();
		}

		$input = Util::arrayify( $input, true );

		$output = array(
			'delete_data' => WOAdmin::sanitize_by_type( isset( $input['delete_data'] ) ? $input['delete_data'] : 0, 'bool' ),
		);

		Survey::flush_settings_cache();

		return $output;
	}
}
