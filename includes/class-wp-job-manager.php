<?php
/**
 * File containing the class WP_Job_Manager.
 *
 * @package wp-job-manager
 * @since   1.33.0
 */

use WP_Job_Manager\Job_Overlay;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles core plugin hooks and action setup.
 *
 * @since 1.0.0
 */
class WP_Job_Manager {
	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.26.0
	 */
	private static $instance = null;

	/**
	 * Forms.
	 *
	 * @var WP_Job_Manager_Forms
	 */
	public $forms;

	/**
	 * Post types.
	 *
	 * @var WP_Job_Manager_Post_Types
	 */
	public $post_types;

	/**
	 * Stats.
	 *
	 * @var WP_Job_Manager\Stats
	 */
	public $stats;

	/**
	 * Main WP Job Manager Instance.
	 *
	 * Ensures only one instance of WP Job Manager is loaded or can be loaded.
	 *
	 * @since  1.26.0
	 * @static
	 * @see WPJM()
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
		// Includes.
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/trait-singleton.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-install.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-post-types.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-ajax.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-shortcodes.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-api.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-forms.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-geocode.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-blocks.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-cache-helper.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/helper/class-wp-job-manager-helper.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-email.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/abstracts/abstract-wp-job-manager-email-template.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-email-notifications.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-data-exporter.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/admin/class-wp-job-manager-settings.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-com-api.php';

		/**
		 * Controls whether promoted jobs are enabled.
		 *
		 * @since 2.3.0
		 *
		 * @param bool $enable_promoted_jobs Whether promoted jobs are enabled.
		 */
		if ( apply_filters( 'job_manager_enable_promoted_jobs', true ) ) {
			include_once JOB_MANAGER_PLUGIN_DIR . '/includes/promoted-jobs/class-wp-job-manager-promoted-jobs.php';
		}

		if ( is_admin() ) {
			include_once JOB_MANAGER_PLUGIN_DIR . '/includes/admin/class-wp-job-manager-admin.php';
		}

		\WP_Job_Manager\UI\UI::instance();
		\WP_Job_Manager\UI\UI_Settings::instance();

		// Load 3rd party customizations.
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/3rd-party/3rd-party.php';

		// Init classes.
		$this->forms      = WP_Job_Manager_Forms::instance();
		$this->post_types = WP_Job_Manager_Post_Types::instance();
		$this->stats      = WP_Job_Manager\Stats::instance();

		WP_Job_Manager\Dev_Tools::init();

		// Schedule cron jobs.
		add_action( 'init', [ __CLASS__, 'maybe_schedule_cron_jobs' ] );

		// Switch theme.
		add_action( 'after_switch_theme', [ 'WP_Job_Manager_Ajax', 'add_endpoint' ], 10 );
		add_action( 'after_switch_theme', [ $this->post_types, 'register_post_types' ], 11 );
		add_action( 'after_switch_theme', 'flush_rewrite_rules', 15 );

		// Actions.
		add_action( 'after_setup_theme', [ $this, 'load_plugin_textdomain' ] );
		add_action( 'after_setup_theme', [ $this, 'include_template_functions' ], 11 );
		add_action( 'widgets_init', [ $this, 'widgets_init' ] );
		add_action( 'wp_loaded', [ $this, 'register_shared_assets' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'frontend_scripts' ] );
		add_action( 'wp_footer', [ $this, 'maybe_localize_jquery_ui_datepicker' ], 1 );
		add_action( 'admin_init', [ $this, 'updater' ] );
		add_action( 'admin_init', [ $this, 'add_privacy_policy_content' ] );
		add_action( 'wp_logout', [ $this, 'cleanup_job_posting_cookies' ] );
		add_action( 'init', [ 'WP_Job_Manager_Email_Notifications', 'init' ] );
		add_action( 'rest_api_init', [ $this, 'rest_init' ] );
		add_action( 'plugins_loaded', [ $this, 'include_admin_files' ] );

		// Filters.
		add_filter( 'wp_privacy_personal_data_exporters', [ 'WP_Job_Manager_Data_Exporter', 'register_wpjm_user_data_exporter' ] );
		add_filter( 'allowed_redirect_hosts', [ $this, 'add_to_allowed_redirect_hosts' ] );

		add_action( 'init', [ $this, 'usage_tracking_init' ] );

		// Defaults for WPJM core actions.
		add_action( 'wpjm_notify_new_user', 'wp_job_manager_notify_new_user', 10, 2 );
	}

	/**
	 * Add the WPJMCOM host to the array of allowed redirect hosts.
	 *
	 * @param array $hosts Allowed redirect hosts.
	 * @return array Updated array of allowed redirect hosts.
	 */
	public function add_to_allowed_redirect_hosts( $hosts ) {
		$hosts[] = wp_parse_url( WP_Job_Manager_Helper_API::get_wpjmcom_url(), PHP_URL_HOST );
		return $hosts;
	}

	/**
	 * Performs plugin activation steps.
	 */
	public function activate() {
		WP_Job_Manager_Ajax::add_endpoint();
		unregister_post_type( \WP_Job_Manager_Post_Types::PT_LISTING );
		add_filter( 'pre_option_job_manager_enable_types', '__return_true' );
		$this->post_types->register_post_types();
		remove_filter( 'pre_option_job_manager_enable_types', '__return_true' );
		WP_Job_Manager_Install::install();
		flush_rewrite_rules();
	}

	/**
	 * Handles tasks after plugin is updated.
	 */
	public function updater() {
		if ( version_compare( JOB_MANAGER_VERSION, get_option( 'wp_job_manager_version' ), '>' ) ) {
			WP_Job_Manager_Install::install();

			flush_rewrite_rules();
		}
	}

	/**
	 * Adds Privacy Policy suggested content.
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			// translators: Placeholders %1$s and %2$s are the names of the two cookies used in WP Job Manager.
			__(
				'This site adds the following cookies to help users resume job submissions that they
				have started but have not completed: %1$s and %2$s',
				'wp-job-manager'
			),
			'<code>wp-job-manager-submitting-job-id</code>',
			'<code>wp-job-manager-submitting-job-key</code>'
		);

		wp_add_privacy_policy_content(
			'WP Job Manager',
			wp_kses_post( wpautop( $content, false ) )
		);
	}

	/**
	 * Loads textdomain for plugin.
	 */
	public function load_plugin_textdomain() {
		load_textdomain( 'wp-job-manager', WP_LANG_DIR . '/wp-job-manager/wp-job-manager-' . apply_filters( 'plugin_locale', get_locale(), 'wp-job-manager' ) . '.mo' );
		load_plugin_textdomain( 'wp-job-manager', false, JOB_MANAGER_PLUGIN_DIR . '/languages/' );
	}

	/**
	 * Loads plugin's core helper template functions.
	 */
	public function include_template_functions() {
		include_once JOB_MANAGER_PLUGIN_DIR . '/wp-job-manager-deprecated.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/wp-job-manager-functions.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/wp-job-manager-template.php';
	}

	/**
	 * Loads the REST API functionality.
	 */
	public function rest_init() {
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-rest-api.php';
		WP_Job_Manager_REST_API::init();
	}

	/**
	 * Loads plugin's widgets.
	 */
	public function widgets_init() {
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-widget.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/widgets/class-wp-job-manager-widget-recent-jobs.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/widgets/class-wp-job-manager-widget-featured-jobs.php';
	}

	/**
	 * Initialize the Usage Tracking system.
	 */
	public function usage_tracking_init() {
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-usage-tracking.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-usage-tracking-data.php';

		WP_Job_Manager_Usage_Tracking::get_instance()->set_callback(
			[ 'WP_Job_Manager_Usage_Tracking_Data', 'get_usage_data' ]
		);

		if ( is_admin() ) {
			WP_Job_Manager_Usage_Tracking::get_instance()->schedule_tracking_task();
		}
	}

	/**
	 * Cleanup the Usage Tracking system for plugin deactivation.
	 */
	public function usage_tracking_cleanup() {
		WP_Job_Manager_Usage_Tracking::get_instance()->unschedule_tracking_task();
	}

	/**
	 * Schedule cron jobs for WPJM events.
	 */
	public static function maybe_schedule_cron_jobs() {
		if ( ! wp_next_scheduled( 'job_manager_check_for_expired_jobs' ) ) {
			wp_schedule_event( time(), 'hourly', 'job_manager_check_for_expired_jobs' );
		}
		if ( ! wp_next_scheduled( 'job_manager_delete_old_previews' ) ) {
			wp_schedule_event( time(), 'daily', 'job_manager_delete_old_previews' );
		}
		if ( ! wp_next_scheduled( 'job_manager_email_daily_notices' ) ) {
			wp_schedule_event( time(), 'daily', 'job_manager_email_daily_notices' );
		}
		if ( class_exists( 'WP_Job_Manager_Promoted_Jobs_Status_Handler' ) && ! wp_next_scheduled( WP_Job_Manager_Promoted_Jobs_Status_Handler::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', WP_Job_Manager_Promoted_Jobs_Status_Handler::CRON_HOOK );
		}
	}

	/**
	 * Unschedule cron jobs. This is run on plugin deactivation.
	 */
	public static function unschedule_cron_jobs() {
		wp_clear_scheduled_hook( 'job_manager_check_for_expired_jobs' );
		wp_clear_scheduled_hook( 'job_manager_delete_old_previews' );
		wp_clear_scheduled_hook( 'job_manager_email_daily_notices' );
		wp_clear_scheduled_hook( 'job_manager_promoted_jobs_notification' );
		wp_clear_scheduled_hook( WP_Job_Manager_Promoted_Jobs_Status_Handler::CRON_HOOK );
	}

	/**
	 * Cleanup job posting cookies.
	 */
	public function cleanup_job_posting_cookies() {
		if ( isset( $_COOKIE['wp-job-manager-submitting-job-id'] ) ) {
			setcookie( 'wp-job-manager-submitting-job-id', '', 0, COOKIEPATH, COOKIE_DOMAIN, false );
		}
		if ( isset( $_COOKIE['wp-job-manager-submitting-job-key'] ) ) {
			setcookie( 'wp-job-manager-submitting-job-key', '', 0, COOKIEPATH, COOKIE_DOMAIN, false );
		}
	}

	/**
	 * Registers assets used in both the frontend and WP admin.
	 */
	public function register_shared_assets() {
		global $wp_scripts;

		$jquery_version = isset( $wp_scripts->registered['jquery-ui-core']->ver ) ? $wp_scripts->registered['jquery-ui-core']->ver : '1.9.2';
		$jquery_version = preg_replace( '/-wp/', '', $jquery_version );
		wp_register_style( 'jquery-ui', '//code.jquery.com/ui/' . $jquery_version . '/themes/smoothness/jquery-ui.min.css', [], $jquery_version );

		// Register datepicker JS. It will be enqueued if needed when a date field is used.
		self::register_script( 'wp-job-manager-datepicker', 'js/datepicker.js', [ 'jquery', 'jquery-ui-datepicker' ], true );
	}

	/**
	 * Registers select2 assets when needed.
	 */
	public static function register_select2_assets() {
		wp_register_script( 'select2', JOB_MANAGER_PLUGIN_URL . '/assets/lib/select2/select2.full.min.js', [ 'jquery' ], '4.0.10', false );
		wp_register_style( 'select2', JOB_MANAGER_PLUGIN_URL . '/assets/lib/select2/select2.min.css', [], '4.0.10' );
	}

	/**
	 * Register a built script file.
	 *
	 * @param string $script_handle The script handle to register.
	 * @param string $script_path   The script path within the assets/dist directory.
	 * @param array  $dependencies  The script dependencies. If set, this will override what is included in the `[script].asset.php` file.
	 * @param bool   $in_footer     Whether to enqueue the script before </body> instead of in the <head>.
	 *
	 * @return bool
	 */
	public static function register_script( $script_handle, $script_path, $dependencies = null, $in_footer = false ) {
		$script_asset_path = realpath(
			JOB_MANAGER_PLUGIN_DIR . '/assets/dist/' .
			substr_replace( $script_path, '.asset.php', - strlen( '.js' ) )
		);

		if ( ! file_exists( $script_asset_path ) ) {
			return false;
		}

		$script_asset = require $script_asset_path;
		$result       = wp_register_script(
			$script_handle,
			JOB_MANAGER_PLUGIN_URL . '/assets/dist/' . $script_path,
			null !== $dependencies ? $dependencies : $script_asset['dependencies'],
			$script_asset['version'],
			$in_footer
		);

		return $result;
	}

	/**
	 * Register a built stylesheet file.
	 *
	 * @param string $style_handle The stylesheet handle to register.
	 * @param string $style_path   The stylesheet path within the assets/dist directory.
	 * @param array  $dependencies The script dependencies. If set, this will override what is included in the `[script].asset.php` file.
	 * @param string $media        The media for which this stylesheet has been defined.
	 *
	 * @return bool
	 */
	public static function register_style( $style_handle, $style_path, $dependencies = null, $media = 'all' ) {
		$script_asset_path = realpath(
			JOB_MANAGER_PLUGIN_DIR . '/assets/dist/' .
			substr_replace( $style_path, '.asset.php', - strlen( '.css' ) )
		);

		if ( ! file_exists( $script_asset_path ) ) {
			return false;
		}

		$script_asset = require $script_asset_path;
		$result       = wp_register_style(
			$style_handle,
			JOB_MANAGER_PLUGIN_URL . '/assets/dist/' . $style_path,
			null !== $dependencies ? $dependencies : $script_asset['dependencies'],
			$script_asset['version'],
			$media
		);

		return $result;
	}

	/**
	 * WordPress localizes this script late in `wp_head`. We sometimes enqueue the datepicker later on.
	 *
	 * @access private
	 * @since 1.34.1
	 */
	public function maybe_localize_jquery_ui_datepicker() {
		// Check if this data has already been added. Prevents outputting localization data multiple times.
		if ( wp_scripts()->get_data( 'jquery-ui-datepicker', 'after' ) ) {
			return;
		}

		wp_localize_jquery_ui_datepicker();
	}

	/**
	 * Registers and enqueues scripts and CSS.
	 *
	 * Note: For enhanced select, 1.32.0 moved to Select2. Chosen is currently packaged but will be removed in an
	 * upcoming release.
	 */
	public function frontend_scripts() {
		$ajax_url         = WP_Job_Manager_Ajax::get_endpoint();
		$ajax_filter_deps = [ 'jquery', 'jquery-deserialize' ];
		$ajax_data        = [
			'ajax_url'                => $ajax_url,
			'is_rtl'                  => is_rtl() ? 1 : 0,
			'i18n_load_prev_listings' => __( 'Load previous listings', 'wp-job-manager' ),
		];
		/**
		 * Retrieves the current language for use when caching requests.
		 *
		 * @since 1.26.0
		 *
		 * @param string|null $lang
		 */
		$ajax_data['lang'] = apply_filters( 'wpjm_lang', null );

		$enhanced_select_shortcodes   = [ 'submit_job_form', 'job_dashboard', 'jobs' ];
		$enhanced_select_used_on_page = has_wpjm_shortcode( null, $enhanced_select_shortcodes );

		/**
		 * Set the constant `JOB_MANAGER_DISABLE_CHOSEN_LEGACY_COMPAT` to true to test for future behavior once
		 * this legacy code is removed and `chosen` is no longer packaged with the plugin.
		 */
		if ( ! defined( 'JOB_MANAGER_DISABLE_CHOSEN_LEGACY_COMPAT' ) || ! JOB_MANAGER_DISABLE_CHOSEN_LEGACY_COMPAT ) {
			if ( is_wpjm_taxonomy() || is_wpjm_job_listing() || is_wpjm_page() ) {
				$enhanced_select_used_on_page = true;
			}

			// Register the script for dependencies that still require it.
			if ( ! wp_script_is( 'chosen', 'registered' ) ) {
				wp_register_script( 'chosen', JOB_MANAGER_PLUGIN_URL . '/assets/lib/jquery-chosen/chosen.jquery.min.js', [ 'jquery' ], '1.1.0', true );
				wp_register_style( 'chosen', JOB_MANAGER_PLUGIN_URL . '/assets/lib/jquery-chosen/chosen.css', [], '1.1.0' );
			}

			// Backwards compatibility for third-party themes/plugins while they transition to Select2.
			wp_localize_script(
				'chosen',
				'job_manager_chosen_multiselect_args',
				apply_filters(
					'job_manager_chosen_multiselect_args',
					[
						'search_contains' => true,
					]
				)
			);

			/**
			 * Filter the use of the deprecated chosen library. Themes and plugins should migrate to Select2. This will be
			 * removed in an upcoming major release.
			 *
			 * @since 1.19.0
			 * @deprecated 1.32.0 Migrate to job_manager_select2_enabled and enable only on pages that need it.
			 *
			 * @param bool $chosen_used_on_page
			 */
			if ( apply_filters( 'job_manager_chosen_enabled', false ) ) {
				_deprecated_hook( 'job_manager_chosen_enabled', '1.32.0', 'job_manager_select2_enabled' );

				// Assume if this filter returns true that the current page should have the multi-select scripts.
				$enhanced_select_used_on_page = true;

				wp_enqueue_script( 'chosen' );
				wp_enqueue_style( 'chosen' );
			}
		}

		/**
		 * Filter the use of the enhanced select.
		 *
		 * Note: Don't depend on `select2` being registered/enqueued in customizations.
		 *
		 * @since 1.32.0
		 *
		 * @param bool $enhanced_select_used_on_page Defaults to only when there are known shortcodes on the page.
		 */
		if ( apply_filters( 'job_manager_enhanced_select_enabled', $enhanced_select_used_on_page ) ) {
			self::register_select2_assets();
			self::register_script( 'wp-job-manager-term-multiselect', 'js/term-multiselect.js', [ 'jquery', 'select2' ], true );
			self::register_script( 'wp-job-manager-multiselect', 'js/multiselect.js', [ 'jquery', 'select2' ], true );
			wp_enqueue_style( 'select2' );

			$ajax_filter_deps[] = 'select2';

			$select2_args = [];
			if ( is_rtl() ) {
				$select2_args['dir'] = 'rtl';
			}

			$select2_args['width'] = '100%';

			wp_localize_script(
				'select2',
				'job_manager_select2_args',
				apply_filters( 'job_manager_select2_args', $select2_args )
			);
		}

		if ( job_manager_user_can_upload_file_via_ajax() ) {
			wp_register_script( 'jquery-iframe-transport', JOB_MANAGER_PLUGIN_URL . '/assets/lib/jquery-fileupload/jquery.iframe-transport.js', [ 'jquery' ], '10.1.0', true );
			wp_register_script( 'jquery-fileupload', JOB_MANAGER_PLUGIN_URL . '/assets/lib/jquery-fileupload/jquery.fileupload.js', [ 'jquery', 'jquery-iframe-transport', 'jquery-ui-widget' ], '10.1.0', true );

			self::register_script( 'wp-job-manager-ajax-file-upload', 'js/ajax-file-upload.js', [ 'jquery', 'jquery-fileupload' ], true );

			ob_start();
			get_job_manager_template(
				'form-fields/uploaded-file-html.php',
				[
					'name'      => '',
					'value'     => '',
					'extension' => 'jpg',
				]
			);
			$js_field_html_img = ob_get_clean();

			ob_start();
			get_job_manager_template(
				'form-fields/uploaded-file-html.php',
				[
					'name'      => '',
					'value'     => '',
					'extension' => 'zip',
				]
			);
			$js_field_html = ob_get_clean();

			wp_localize_script(
				'wp-job-manager-ajax-file-upload',
				'job_manager_ajax_file_upload',
				[
					'ajax_url'               => $ajax_url,
					'js_field_html_img'      => esc_js( str_replace( "\n", '', $js_field_html_img ) ),
					'js_field_html'          => esc_js( str_replace( "\n", '', $js_field_html ) ),
					'i18n_invalid_file_type' => esc_html__( 'Invalid file type. Accepted types:', 'wp-job-manager' ),
				]
			);
		}

		wp_register_script( 'jquery-deserialize', JOB_MANAGER_PLUGIN_URL . '/assets/lib/jquery-deserialize/jquery.deserialize.js', [ 'jquery' ], '1.2.1', true );
		self::register_script( 'wp-job-manager-ajax-filters', 'js/ajax-filters.js', $ajax_filter_deps, true );
		self::register_script( 'wp-job-manager-job-application', 'js/job-application.js', [ 'jquery' ], true );
		self::register_script( 'wp-job-manager-job-submission', 'js/job-submission.js', [ 'jquery' ], true );
		wp_localize_script( 'wp-job-manager-ajax-filters', 'job_manager_ajax_filters', $ajax_data );

		if ( isset( $select2_args ) ) {
			$select2_filters_args = array_merge(
				$select2_args,
				[
					'allowClear'              => true,
					'minimumResultsForSearch' => 10,
					'placeholder'             => __( 'Any Category', 'wp-job-manager' ),
				]
			);

			wp_localize_script(
				'select2',
				'job_manager_select2_filters_args',
				apply_filters( 'job_manager_select2_filters_args', $select2_filters_args )
			);
		}

		wp_localize_script(
			'wp-job-manager-job-submission',
			'job_manager_job_submission',
			[
				// translators: Placeholder %d is the number of files to that users are limited to.
				'i18n_over_upload_limit' => esc_html__( 'You are only allowed to upload a maximum of %d files.', 'wp-job-manager' ),
			]
		);

		wp_localize_script(
			'wp-job-manager-job-submission',
			'job_manager_job_submission',
			[
				'i18n_required_field' => __( 'This field is required.', 'wp-job-manager' ),
			]
		);

		/**
		 * Filter whether to enqueue WPJM core's frontend scripts. By default, they will only be enqueued on WPJM related
		 * pages.
		 *
		 * If your theme or plugin depend on `frontend.css` from WPJM core, you can use the
		 * `job_manager_enqueue_frontend_style` filter.
		 *
		 * Example code for a custom shortcode that depends on the frontend style:
		 *
		 * add_filter( 'job_manager_enqueue_frontend_style', function( $frontend_used_on_page ) {
		 *   global $post;
		 *   if ( is_singular()
		 *        && is_a( $post, 'WP_Post' )
		 *        && has_shortcode( $post->post_content, 'resumes' )
		 *   ) {
		 *     $frontend_used_on_page = true;
		 *   }
		 *   return $frontend_used_on_page;
		 * } );
		 *
		 * @since 1.30.0
		 *
		 * @param bool $is_frontend_style_enabled
		 */
		if ( apply_filters( 'job_manager_enqueue_frontend_style', is_wpjm() ) ) {
			self::register_style( 'wp-job-manager-frontend', 'css/frontend.css', [] );
			wp_enqueue_style( 'wp-job-manager-frontend' );
		} else {
			self::register_style( 'wp-job-manager-job-listings', 'css/job-listings.css', [] );
			wp_enqueue_style( 'wp-job-manager-job-listings' );
		}
	}

	/**
	 * This solves a loading order issue which occurs when is_admin() starts to return true at a point after plugin
	 * load. See the below issue for more information: https://github.com/Automattic/evergreen/issues/136
	 */
	public function include_admin_files() {
		if ( is_admin() && ! class_exists( 'WP_Job_Manager_Admin' ) ) {
			include_once JOB_MANAGER_PLUGIN_DIR . '/includes/admin/class-wp-job-manager-admin.php';
		}
	}
}
