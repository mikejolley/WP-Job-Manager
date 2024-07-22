<?php
/**
 * File containing the class WP_Job_Manager_Admin.
 *
 * @package wp-job-manager
 */

use WP_Job_Manager\Job_Overlay;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles front admin page for WP Job Manager.
 *
 * @since 1.0.0
 */
class WP_Job_Manager_Admin {

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.26.0
	 */
	private static $instance = null;

	/**
	 * Settings page.
	 *
	 * @var WP_Job_Manager_Settings
	 */
	private $settings_page;

	/**
	 * Whether promoted jobs are enabled.
	 *
	 * @var bool
	 */
	private $are_promoted_jobs_enabled;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.26.0
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wp_version;

		include_once dirname( __FILE__ ) . '/class-notices-conditions-checker.php';
		include_once dirname( __FILE__ ) . '/class-wp-job-manager-admin-notices.php';
		include_once dirname( __FILE__ ) . '/class-wp-job-manager-cpt.php';
		WP_Job_Manager_CPT::instance();

		/**
		 * Documented in class-wp-job-manager.php
		 */
		$this->are_promoted_jobs_enabled = apply_filters( 'job_manager_enable_promoted_jobs', true );
		if ( $this->are_promoted_jobs_enabled ) {
			include_once dirname( __FILE__ ) . '/class-wp-job-manager-promoted-jobs-admin.php';
		}

		include_once dirname( __FILE__ ) . '/class-wp-job-manager-writepanels.php';
		include_once dirname( __FILE__ ) . '/class-wp-job-manager-setup.php';
		include_once dirname( __FILE__ ) . '/class-wp-job-manager-addons-landing-page.php';
		include_once dirname( __FILE__ ) . '/class-wp-job-manager-addons.php';

		$this->settings_page = WP_Job_Manager_Settings::instance();
		WP_Job_Manager_Addons_Landing_Page::instance();
		Job_Overlay::instance();

		add_action( 'admin_init', [ $this, 'admin_init' ] );
		add_action( 'current_screen', [ $this, 'conditional_includes' ] );
		add_action( 'admin_menu', [ $this, 'admin_menu' ], 12 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );
	}

	/**
	 * Set up actions during admin initialization.
	 */
	public function admin_init() {
		include_once dirname( __FILE__ ) . '/class-wp-job-manager-taxonomy-meta.php';
	}

	/**
	 * Include admin files conditionally.
	 */
	public function conditional_includes() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		switch ( $screen->id ) {
			case 'options-permalink':
				include 'class-wp-job-manager-permalink-settings.php';
				break;
		}
	}

	/**
	 * Enqueues CSS and JS assets.
	 */
	public function admin_enqueue_scripts() {
		WP_Job_Manager::register_select2_assets();

		WP_Job_Manager::register_style( 'job_manager_brand', 'css/wpjm-brand.css', [] );

		$screen = get_current_screen();

		if ( in_array( $screen->id, apply_filters( 'job_manager_admin_screen_ids', [ 'edit-job_listing', 'plugins', \WP_Job_Manager_Post_Types::PT_LISTING, 'job_listing_page_job-manager-settings', 'job_listing_page_job-manager-marketplace', 'edit-job_listing_type' ] ), true ) ) {

			wp_enqueue_style( 'jquery-ui' );
			wp_enqueue_style( 'select2' );

			WP_Job_Manager::register_style( 'job_manager_admin_css', 'css/admin.css', [ 'job_manager_brand' ] );
			wp_enqueue_style( 'job_manager_admin_css' );

			wp_enqueue_script( 'wp-job-manager-datepicker' );
			wp_register_script( 'jquery-tiptip', JOB_MANAGER_PLUGIN_URL . '/assets/lib/jquery-tiptip/jquery.tipTip.min.js', [ 'jquery' ], JOB_MANAGER_VERSION, true );

			WP_Job_Manager::register_script( 'job_manager_admin_js', 'js/admin.js', [ 'jquery', 'jquery-tiptip', 'select2' ], true );
			wp_enqueue_script( 'job_manager_admin_js' );

			WP_Job_Manager::register_script( 'job_tags_upsell_js', 'js/admin/job-tags-upsell.js', [], true );
			if ( ! class_exists( 'WP_Job_Manager_Job_Tags' ) && $screen->is_block_editor() ) {
				wp_enqueue_script( 'job_tags_upsell_js' );
			}

			wp_localize_script(
				'job_manager_admin_js',
				'job_manager_admin_params',
				[
					'user_selection_strings'      => [
						'no_matches'        => _x( 'No matches found', 'user selection', 'wp-job-manager' ),
						'ajax_error'        => _x( 'Loading failed', 'user selection', 'wp-job-manager' ),
						'input_too_short_1' => _x( 'Please enter 1 or more characters', 'user selection', 'wp-job-manager' ),
						'input_too_short_n' => _x( 'Please enter %qty% or more characters', 'user selection', 'wp-job-manager' ),
						'load_more'         => _x( 'Loading more results&hellip;', 'user selection', 'wp-job-manager' ),
						'searching'         => _x( 'Searching&hellip;', 'user selection', 'wp-job-manager' ),
					],
					'job_listing_promote_strings' => [
						'promote_job' => _x( 'Promote your job', 'job promotion', 'wp-job-manager' ),
						'learn_more'  => _x( 'Learn More', 'job promotion', 'wp-job-manager' ),
						'dismiss'     => _x( 'Don\'t show this again', 'job promotion', 'wp-job-manager' ),
					],
					'ajax_url'                    => admin_url( 'admin-ajax.php' ),
					'search_users_nonce'          => wp_create_nonce( 'search-users' ),
					'promoted_jobs_enabled'       => $this->are_promoted_jobs_enabled,
				]
			);
		}

		if ( \WP_Job_Manager_Post_Types::PT_LISTING === $screen->id && $screen->is_block_editor() && $this->are_promoted_jobs_enabled ) { // Check if it's block editor in job post.
			$post = get_post();

			if ( ! empty( $post ) ) {
				WP_Job_manager::register_script( 'job_manager_job_editor_js', 'js/admin/job-editor.js', [], true );
				wp_enqueue_script( 'job_manager_job_editor_js' );

				wp_add_inline_script(
					'job_manager_job_editor_js',
					sprintf( 'window.wpjm = window.wpjm || {}; window.wpjm.promoteUrl = "%s";', WP_Job_Manager_Promoted_Jobs_Admin::get_promote_url( $post->ID ) ),
					'before'
				);
			}
		}

		WP_Job_manager::register_script( 'job_manager_notice_dismiss', 'js/admin/wpjm-notice-dismiss.js', null, true );
		wp_enqueue_script( 'job_manager_notice_dismiss' );

		WP_Job_Manager::register_style( 'job_manager_admin_menu_css', 'css/menu.css', [] );
		wp_enqueue_style( 'job_manager_admin_menu_css' );

		WP_Job_Manager::register_style( 'job_manager_admin_notices_css', 'css/admin-notices.css', [ 'job_manager_brand' ] );
		wp_enqueue_style( 'job_manager_admin_notices_css' );
	}

	/**
	 * Adds pages to admin menu.
	 */
	public function admin_menu() {
		$item = remove_submenu_page( 'edit.php?post_type=job_listing', 'edit.php?post_type=job_listing' );
		if ( ! $item ) {
			return;
		}
		// change item label to "Job Listings".
		add_submenu_page( 'edit.php?post_type=job_listing', $item[0], esc_html__( 'Job Listings', 'wp-job-manager' ), $item[1], $item[2], '', 0 );
		add_submenu_page( 'edit.php?post_type=job_listing', __( 'Settings', 'wp-job-manager' ), esc_html__( 'Settings', 'wp-job-manager' ), 'manage_options', 'job-manager-settings', [ $this->settings_page, 'output' ] );

		if ( WP_Job_Manager_Helper::instance()->has_licensed_products() || apply_filters( 'job_manager_show_addons_page', true ) ) {
			add_submenu_page( 'edit.php?post_type=job_listing', __( 'WP Job Manager Marketplace', 'wp-job-manager' ), esc_html__( 'Marketplace', 'wp-job-manager' ), 'manage_options', 'job-manager-marketplace', [ $this, 'addons_page' ] );
		}
	}

	/**
	 * Displays marketplace page.
	 */
	public function addons_page() {
		WP_Job_Manager_Addons::instance()->output();
	}
}

WP_Job_Manager_Admin::instance();
