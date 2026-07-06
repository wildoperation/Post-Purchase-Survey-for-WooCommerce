<?php
namespace PPS;

/**
 * Registers the survey question post type.
 * The post title is the question; answers are stored in post meta.
 */
class PostTypes {

	/**
	 * Create hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
	}

	/**
	 * Register the question post type.
	 * The post type creates the plugin's top-level admin menu, positioned
	 * below WooCommerce's Products menu.
	 *
	 * @return void
	 */
	public function register_post_types() {
		$labels = array(
			'name'                  => __( 'Survey Questions', 'post-purchase-survey-for-woocommerce' ),
			'singular_name'         => __( 'Survey Question', 'post-purchase-survey-for-woocommerce' ),
			'menu_name'             => __( 'Purchase Survey', 'post-purchase-survey-for-woocommerce' ),
			'name_admin_bar'        => __( 'Survey Question', 'post-purchase-survey-for-woocommerce' ),
			'all_items'             => __( 'Questions', 'post-purchase-survey-for-woocommerce' ),
			'add_new_item'          => __( 'Add New Question', 'post-purchase-survey-for-woocommerce' ),
			'add_new'               => __( 'Add New Question', 'post-purchase-survey-for-woocommerce' ),
			'new_item'              => __( 'New Question', 'post-purchase-survey-for-woocommerce' ),
			'edit_item'             => __( 'Edit Question', 'post-purchase-survey-for-woocommerce' ),
			'update_item'           => __( 'Update Question', 'post-purchase-survey-for-woocommerce' ),
			'view_item'             => __( 'View Question', 'post-purchase-survey-for-woocommerce' ),
			'view_items'            => __( 'View Questions', 'post-purchase-survey-for-woocommerce' ),
			'search_items'          => __( 'Search Questions', 'post-purchase-survey-for-woocommerce' ),
			'not_found'             => __( 'No questions found. Create one, then select it on the Survey screen.', 'post-purchase-survey-for-woocommerce' ),
			'not_found_in_trash'    => __( 'No questions found in Trash', 'post-purchase-survey-for-woocommerce' ),
			'items_list'            => __( 'Question list', 'post-purchase-survey-for-woocommerce' ),
			'items_list_navigation' => __( 'Questions list navigation', 'post-purchase-survey-for-woocommerce' ),
			'filter_items_list'     => __( 'Filter question list', 'post-purchase-survey-for-woocommerce' ),
		);

		$capabilities = array(
			'edit_post'          => Plugin::capability(),
			'delete_post'        => Plugin::capability(),
			'edit_posts'         => Plugin::capability(),
			'edit_others_posts'  => Plugin::capability(),
			'delete_posts'       => Plugin::capability(),
			'publish_posts'      => Plugin::capability(),
			'read_private_posts' => Plugin::capability(),
			'create_posts'       => Plugin::capability(),
		);

		$args = array(
			'label'               => __( 'Survey Question', 'post-purchase-survey-for-woocommerce' ),
			'description'         => __( 'A post-purchase survey question', 'post-purchase-survey-for-woocommerce' ),
			'labels'              => $labels,
			'supports'            => array( 'title' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_in_rest'        => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 56,
			'menu_icon'           => 'dashicons-feedback',
			'show_in_admin_bar'   => false,
			'show_in_nav_menus'   => false,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capabilities'        => $capabilities,
		);

		register_post_type( Plugin::posttype_question(), $args );
	}

	/**
	 * The title placeholder on the question edit screen.
	 *
	 * @param string   $placeholder The current placeholder.
	 * @param \WP_Post $post The current post.
	 *
	 * @return string
	 */
	public function title_placeholder( $placeholder, $post ) {
		if ( $post && $post->post_type === Plugin::posttype_question() ) {
			return __( 'Enter your question, e.g. "How did you hear about us?"', 'post-purchase-survey-for-woocommerce' );
		}

		return $placeholder;
	}
}
