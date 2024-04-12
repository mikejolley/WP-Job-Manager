<?php
/**
 * File containing the class WP_Job_Manager_Admin_Notices.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Job_Manager\Admin\Notices_Conditions_Checker;
use WP_Job_Manager\Admin\Release_Notice;
use WP_Job_Manager\WP_Job_Manager_Com_API;

/**
 * WP_Job_Manager_Admin_Notices class.
 *
 * @since 1.32.0
 */
class WP_Job_Manager_Admin_Notices {
	/**
	 * Notice states.
	 *
	 * @deprecated 1.40.0 This option should not be used anymore.
	 */
	const STATE_OPTION                  = 'job_manager_admin_notices';
	const NOTICE_CORE_SETUP             = 'core_setup';
	const NOTICE_ADDON_UPDATE_AVAILABLE = 'addon_update_available';
	const DISMISS_NOTICE_ACTION         = 'wp_job_manager_dismiss_notice';
	const DISMISSED_NOTICES_OPTION      = 'wp_job_manager_dismissed_notices';
	const DISMISSED_NOTICES_USER_META   = 'wp_job_manager_dismissed_notices';

	const ALLOWED_HTML = [
		'div'    => [
			'class' => [],
		],
		'a'      => [
			'target' => [],
			'href'   => [],
			'rel'    => [],
			'class'  => [],
		],
		'img'    => [
			'src'   => [],
			'alt'   => [],
			'class' => [],
		],
		'em'     => [],
		'ul'     => [],
		'ol'     => [],
		'li'     => [],
		'p'      => [],
		'strong' => [],
	];

	/**
	 * Current notices for admin user.
	 *
	 * @var array
	 */
	private static $notice_state;

	/**
	 * Initialize admin notice handling.
	 */
	public static function init() {
		add_action( 'admin_notices', [ __CLASS__, 'display_notices' ] );
		add_action( 'wp_loaded', [ __CLASS__, 'handle_notice_action' ], 10 );
		add_action( 'wp_loaded', [ __CLASS__, 'dismiss_notices' ], 11 );
		add_action( 'wp_ajax_wp_job_manager_dismiss_notice', [ __CLASS__, 'handle_notice_dismiss' ] );
		add_filter( 'wpjm_admin_notices', [ __CLASS__, 'maybe_add_addon_update_available_notice' ] );
		add_filter( 'wpjm_admin_notices', [ __CLASS__, 'paid_listings_renewal_notice' ] );
		add_filter( 'wpjm_admin_notices', [ __CLASS__, 'we_have_addons_notice' ] );
		add_filter( 'wpjm_admin_notices', [ __CLASS__, 'maybe_add_core_setup_notice' ] );

		Release_Notice::init();
	}

	/**
	 * Get and show our icon in WPJM notices.
	 *
	 * @param string $icon_name Icon ID passed from API.
	 *
	 * @return string path to the image
	 */
	private static function get_icon( $icon_name ) {
		switch ( $icon_name ) {
			case 'wpjm':
				return JOB_MANAGER_PLUGIN_URL . '/assets/images/wpjm-logo.png';
		}

		return JOB_MANAGER_PLUGIN_URL . '/assets/images/wpjm-logo.png';
	}

	/**
	 * Add a notice to be displayed in WP admin.
	 *
	 * @since      1.32.0
	 * @deprecated 1.40.0 Use the `job_manager_admin_notices` filter instead to add your own notices. You might need to persist an option/flag by yourself.
	 *
	 * @param string $notice Name of the notice.
	 */
	public static function add_notice( $notice ) {
		$notice = sanitize_key( $notice );

		if ( ! in_array( $notice, self::get_notice_state(), true ) ) {
			self::$notice_state[] = $notice;
			self::save_notice_state();
		}
	}

	/**
	 * Remove a notice from those displayed in WP admin.
	 *
	 * @since      1.32.0
	 * @deprecated 1.40.0 Use the `job_manager_admin_notices` filter instead to add your own notices. You might need to persist an option/flag by yourself.
	 *
	 * @param string $notice Name of the notice.
	 */
	public static function remove_notice( $notice ) {
		$notice_state = self::get_notice_state();
		$notice       = sanitize_key( $notice );

		$notice_key = array_search( $notice, $notice_state, true );
		if ( false !== $notice_key ) {
			unset( $notice_state[ $notice_key ] );
			self::$notice_state = array_values( $notice_state );
			self::save_notice_state();
		}
	}

	/**
	 * Clears all enqueued notices.
	 *
	 * @deprecated 1.40.0 Use the `job_manager_admin_notices` filter instead.
	 */
	public static function reset_notices() {
		self::$notice_state = [];
		self::save_notice_state();
	}

	/**
	 * Check for a notice to be displayed in WP admin.
	 *
	 * @since      1.32.0
	 * @deprecated 1.40.0 Use the `job_manager_admin_notices` filter instead.
	 *
	 * @param string $notice Name of the notice. Name is not sanitized for this method.
	 *
	 * @return bool
	 */
	public static function has_notice( $notice ) {
		$notice_state = self::get_notice_state();

		return in_array( $notice, $notice_state, true );
	}

	/**
	 * Set up filters for core admin notices.
	 *
	 * @deprecated since 2.0.0. See maybe_add_core_setup_notice
	 *
	 * Note: For internal use only. Do not call manually.
	 */
	public static function init_core_notices() {
		_deprecated_function( __METHOD__, '2.0.0', 'WP_Job_Manager_Admin_Notices::maybe_add_core_setup_notice' );
	}

	/**
	 * Dismiss a notice.
	 */
	public static function dismiss_notices() {
		if ( isset( $_GET['wpjm_hide_notice'] ) && isset( $_GET['_wpjm_notice_nonce'] ) ) {
			if ( ! wp_verify_nonce( wp_unslash( $_GET['_wpjm_notice_nonce'] ), 'job_manager_notices_nonce' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce should not be modified.
				wp_die( esc_html__( 'Action failed. Please refresh the page and retry.', 'wp-job-manager' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You don&#8217;t have permission to do this.', 'wp-job-manager' ) );
			}

			$notice_id = sanitize_key( wp_unslash( $_GET['wpjm_hide_notice'] ) );

			self::dismiss_notice( $notice_id );

			wp_safe_redirect( remove_query_arg( [ 'wpjm_hide_notice', '_wpjm_notice_nonce', 'wpjm_notice_action' ] ) );
			exit;
		}
	}

	/**
	 * Call an action when a notice button is clicked.
	 */
	public static function handle_notice_action() {

		if ( isset( $_GET['wpjm_notice_action'] ) && isset( $_GET['_wpjm_notice_nonce'] ) ) {

			$action = sanitize_key( wp_unslash( $_GET['wpjm_notice_action'] ) );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce should not be modified.
			if ( ! wp_verify_nonce( wp_unslash( $_GET['_wpjm_notice_nonce'] ), 'job_manager_notices_nonce' ) ) {
				wp_die( esc_html__( 'Action failed. Please refresh the page and retry.', 'wp-job-manager' ) );
			}

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You don&#8217;t have permission to do this.', 'wp-job-manager' ) );
			}

			do_action( 'job_manager_action_' . $action );
		}
	}

	/**
	 * Returns all the registered notices.
	 * This includes notices coming from wpjobmanager.com and notices added by other plugins using the `job_manager_admin_notices` filter.
	 *
	 * The notices will be later filtered when displayed with `display_notices()` method.
	 *
	 * @return mixed
	 */
	public static function get_notices() {

		$remote_notices = WP_Job_Manager_Com_API::instance()->get_notices();

		/**
		 * Filters the admin notices. Allows to add or remove notices.
		 *
		 * @since 1.40.0
		 *
		 * @param array $notices The admin notices.
		 *
		 * @return array The admin notices.
		 */
		$all_notices = apply_filters( 'wpjm_admin_notices', $remote_notices );

		return $all_notices;
	}

	/**
	 * Get a notice by ID.
	 *
	 * @param string $notice_id Notice ID.
	 *
	 * @return array|false
	 */
	public static function get_notice( $notice_id ) {
		$notices = self::get_notices();

		if ( ! isset( $notices[ $notice_id ] ) ) {
			return false;
		}

		$notice = self::normalize_notice( $notices[ $notice_id ] );

		return $notice;
	}

	/**
	 * Check if a notice was dismissed.
	 *
	 * @param string $notice_id Notice ID.
	 * @param string $is_user_notification Whether it's a user-level or a global notification.
	 *
	 * @return bool
	 */
	public static function is_dismissed( $notice_id, $is_user_notification ) {
		return ( in_array( $notice_id, self::get_dismissed_notices( $is_user_notification ), true ) );
	}

	/**
	 * Displays notices in WP admin.
	 *
	 * Note: For internal use only. Do not call manually.
	 */
	public static function display_notices() {
		/**
		 * Allows WPJM related plugins to set up their notice hooks.
		 *
		 * @since      1.32.0
		 * @deprecated 1.40.0 Use the `job_manager_admin_notices` filter instead to add your own notices.
		 */
		do_action( 'job_manager_init_admin_notices' );

		$notice_state = self::get_notice_state();
		foreach ( $notice_state as $notice ) {
			/**
			 * Allows suppression of individual admin notices.
			 *
			 * @since      1.32.0
			 * @deprecated 1.40.0 Use the `job_manager_admin_notices` filter instead to remove notices.
			 *
			 * @param bool $do_show_notice Set to false to prevent an admin notice from showing up.
			 */

			if ( ! apply_filters( 'job_manager_show_admin_notice_' . $notice, true ) ) {
				continue;
			}

			/**
			 * Handle the display of the admin notice.
			 *
			 * @since      1.32.0
			 * @deprecated 1.40.0 Use the `job_manager_admin_notices` to add your own notices with the normalised format.
			 */
			do_action( 'job_manager_admin_notice_' . $notice );
		}

		$notices = self::get_notices();

		$condition_checker = new Notices_Conditions_Checker();
		foreach ( $notices as $notice_id => $notice ) {
			$notice               = self::normalize_notice( $notice );
			$is_user_notification = 'user' === $notice['type'];
			if ( in_array( $notice_id, self::get_dismissed_notices( $is_user_notification ), true ) ) {
				continue;
			}
			if ( $condition_checker->check( $notice['conditions'] ?? [] ) ) {
				self::render_notice( $notice_id, $notice );
			}
		}
	}

	/**
	 * Normalize notices.
	 *
	 * @param array $notice The notice configuration.
	 *
	 * @return array
	 */
	private static function normalize_notice( $notice ) {
		if ( ! isset( $notice['conditions'] ) || ! is_array( $notice['conditions'] ) ) {
			$notice['conditions'] = [];
		}

		if ( ! isset( $notice['type'] ) ) {
			$notice['type'] = 'site-wide';
		}

		if ( 'site-wide' === $notice['type'] ) {
			// Only admins can see and manage site-wide notifications.
			$notice['conditions'][] = [
				'type'         => 'user_cap',
				'capabilities' => [ 'manage_options' ],
			];
		}

		if ( ! isset( $notice['dismissible'] ) ) {
			$notice['dismissible'] = true;
		}

		return $notice;
	}

	/**
	 * Get the dismissed notifications (either for the user or site-wide).
	 *
	 * @param bool $is_user_notification True if this is for a user notification (vs site-wide notification).
	 *
	 * @return array
	 */
	private static function get_dismissed_notices( $is_user_notification ) {
		if ( $is_user_notification ) {
			$dismissed_notices = get_user_meta( get_current_user_id(), self::DISMISSED_NOTICES_USER_META, true );
			if ( ! $dismissed_notices ) {
				$dismissed_notices = [];
			}
		} else {
			$dismissed_notices = get_option( self::DISMISSED_NOTICES_OPTION, [] );
		}

		return $dismissed_notices;
	}

	/**
	 * Save dismissed notices.
	 *
	 * @param array $dismissed_notices Array of dismissed notices.
	 * @param bool  $is_user_notification True if we are setting user notifications (vs site-wide notifications).
	 */
	private static function save_dismissed_notices( $dismissed_notices, $is_user_notification ) {

		$dismissed_notices = array_unique( $dismissed_notices );
		if ( $is_user_notification ) {
			update_user_meta( get_current_user_id(), self::DISMISSED_NOTICES_USER_META, $dismissed_notices );
		} else {
			update_option( self::DISMISSED_NOTICES_OPTION, $dismissed_notices );
		}
	}

	/**
	 * Handle the dismissal of the notice.
	 *
	 * @access private
	 */
	public static function handle_notice_dismiss() {
		check_ajax_referer( self::DISMISS_NOTICE_ACTION, 'nonce' );

		$notice_id = isset( $_POST['notice'] ) ? sanitize_text_field( wp_unslash( $_POST['notice'] ) ) : false;
		if ( ! $notice_id ) {
			return;
		}

		$notice = self::get_notice( $notice_id );

		if ( ! $notice || ! $notice['dismissible'] || ( 'user' !== $notice['type'] && ! current_user_can( 'manage_options' ) ) ) {
			wp_die( '', '', 403 );
		}

		self::dismiss_notice( $notice_id );
		exit;
	}

	/**
	 * Save notice state as dismissed.
	 *
	 * @param string $notice_id Notice ID.
	 *
	 * @return void
	 */
	public static function dismiss_notice( $notice_id ) {

		$notice = self::get_notice( $notice_id );
		if ( ! $notice ) {
			return;
		}

		$is_user_notification = 'user' === $notice['type'];

		$dismissed_notices   = self::get_dismissed_notices( $is_user_notification );
		$dismissed_notices[] = $notice_id;

		self::save_dismissed_notices( $dismissed_notices, $is_user_notification );

		do_action( 'wp_job_manager_notice_dismissed', $notice, $notice_id, $is_user_notification );
	}

	/**
	 * Helper for display functions to check if current request is for admin on a job manager screen.
	 *
	 * @param array $additional_screens Screen IDs to also show a notice on.
	 *
	 * @deprecated $$next_version$$ Removed. See WP_Job_Manager\Admin\Notices_Conditions_Checker::check instead.
	 *
	 * @return bool
	 */
	public static function is_admin_on_standard_job_manager_screen( $additional_screens = [] ) {

		_deprecated_function( __METHOD__, '2.0.0', 'WP_Job_Manager\Admin\Notices_Conditions_Checker::check' );

		$screen          = get_current_screen();
		$screen_id       = $screen ? $screen->id : '';
		$show_on_screens = array_merge(
			[
				'edit-job_listing',
				'edit-job_listing_category',
				'edit-job_listing_type',
				'job_listing_page_job-manager-marketplace',
				'job_listing_page_job-manager-settings',
			],
			$additional_screens
		);

		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( ! in_array( $screen_id, $show_on_screens, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Displays the setup wizard notice when WPJM is first activated.
	 *
	 * @deprecated since 2.0.0. See maybe_add_core_setup_notice
	 *
	 * Note: For internal use only. Do not call manually.
	 */
	public static function display_core_setup() {
		_deprecated_function( __METHOD__, '2.0.0', 'WP_Job_Manager_Admin_Notices::maybe_add_core_setup_notice' );
	}

	/**
	 * Displays the setup wizard notice when WPJM is first activated.
	 *
	 * @access private.
	 *
	 * @param array $notices WPJM notices.
	 *
	 * @return array
	 */
	public static function maybe_add_core_setup_notice( $notices ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $notices;
		}

		if ( self::has_notice( self::NOTICE_CORE_SETUP ) ) {
			$notices[ self::NOTICE_CORE_SETUP ] = [
				'type'        => 'site-wide',
				'level'       => 'warning',
				'icon'        => 'wpjm',
				'dismissible' => false,
				'heading'     => __( 'You are nearly ready to start listing jobs with Job Manager', 'wp-job-manager' ),
				'message'     => '<p>Go through the setup to start listing your jobs.</p>
			<p>* See <a href="https://wpjobmanager.com/document/getting-started/installation/">manually creating pages</a>.</p>',
				'actions'     => [
					[
						'label' => __( 'Run Setup Wizard', 'wp-job-manager' ),
						'url'   => admin_url( 'index.php?page=job-manager-setup' ),
					],
					[
						'primary' => false,
						'url'     => self::get_dismiss_url( self::NOTICE_CORE_SETUP ),
						'label'   => __( 'Skip Setup*', 'wp-job-manager' ),
					],
				],
				'conditions'  => [
					[
						'type'    => 'screens',
						'screens' => [
							'edit-job_listing',
							'job_listing_page_job-manager-settings',
							'dashboard',
							'plugins',
						],
					],
				],
			];
		}

		return $notices;
	}

	/**
	 * Adds notice that informs about WPJM addon updates available if any.
	 *
	 * @access private
	 *
	 * @param array $notices Existing notices.
	 *
	 * @return array
	 */
	public static function maybe_add_addon_update_available_notice( $notices ) {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return $notices;
		}

		$updates = get_site_transient( 'wpjm_addon_updates_available', [] );
		if ( ! empty( $updates ) ) {
			$notice = self::generate_notice_from_updates( $updates );
			if ( ! is_null( $notice ) ) {
				$notice_id             = self::generate_notice_id_from_updates( $updates );
				$notices[ $notice_id ] = $notice;
			}
		}

		return $notices;
	}

	/**
	 * Get URL for dismiss action for a notice.
	 *
	 * @param string $notice_name
	 * @return string
	 */
	public static function get_dismiss_url( $notice_name ) {
		return wp_nonce_url( add_query_arg( [ 'wpjm_hide_notice' => $notice_name ] ), 'job_manager_notices_nonce', '_wpjm_notice_nonce' );
	}

	/**
	 * Get URL for a custom action for a notice.
	 *
	 * @param string $action_name Name of action.
	 * @param string $dismiss_notice Optional notice ID, if this action should also dismiss the notice.
	 * @return string
	 */
	public static function get_action_url( $action_name, $dismiss_notice = null ) {

		$base_url = '';

		if ( $dismiss_notice ) {
			$base_url = self::get_dismiss_url( $dismiss_notice );
		}

		return wp_nonce_url( add_query_arg( [ 'wpjm_notice_action' => $action_name ], $base_url ), 'job_manager_notices_nonce', '_wpjm_notice_nonce' );
	}

	/**
	 * Gets the current admin notices to be displayed.
	 *
	 * @return array
	 */
	private static function get_notice_state() {
		if ( null === self::$notice_state ) {
			self::$notice_state = json_decode( get_option( self::STATE_OPTION, '[]' ), true );
			if ( ! is_array( self::$notice_state ) ) {
				self::$notice_state = [];
			}
		}

		return self::$notice_state;
	}

	/**
	 * Saves the notice state on shutdown.
	 */
	private static function save_notice_state() {
		if ( null === self::$notice_state ) {
			return;
		}

		update_option( self::STATE_OPTION, wp_json_encode( self::get_notice_state() ), false );
	}

	/**
	 * Given an array of updates, generate a notice.
	 *
	 * @param array $updates The contents of the addon updates transient.
	 *
	 * @return array|null
	 */
	private static function generate_notice_from_updates( $updates ) {
		if ( empty( $updates ) ) {
			return null;
		}

		$plugin_info = WP_Job_Manager_Helper::instance()->get_installed_plugins( false, true );

		$heading = esc_html( _n( 'Job Manager: Plugin update available', 'Job Manager: Plugin updates available', count( $updates ), 'wp-job-manager' ) );
		$message = _n( 'Good news, you can update the following extension to its latest version:', 'Good news, you can update the following extensions to their latest versions:', count( $updates ), 'wp-job-manager' );

		$actions = [];

		$extra_details = '';
		foreach ( $updates as $update ) {
			$info           = $plugin_info[ $update['plugin'] ];
			$plugin_slug    = $info['_product_slug'];
			$icon           = WP_Job_Manager_Addons::instance()->get_icon( $info['PluginURI'] ?? null );
			$extra_details .= '<div class="wpjm-addon-update-notice-info">';
			$extra_details .= '<img class="wpjm-addon-update-notice-info__icon" src="' . esc_url( $icon ) . '" />';
			$extra_details .= '<div class="wpjm-addon-update-notice-info__name">' . esc_html( $info['Name'] ) . '</div>';
			$extra_details .= '<div class="wpjm-addon-update-notice-info__version">';
			$extra_details .= '<a href="https://wpjobmanager.com/release-notes/?job-manager-product=' . esc_attr( $plugin_slug ) . '" target="_blank">';
			// translators: %s is the new version number for the addon.
			$extra_details .= sprintf( esc_html__( 'New Version: %s', 'wp-job-manager' ), $update['new_version'] );
			$extra_details .= '</a>';
			$extra_details .= '</div>';
			$extra_details .= '</div>';
		}

		$actions[] = [
			'label' => _n( 'Update', 'Update All', count( $updates ), 'wp-job-manager' ),
			'url'   => admin_url( 'plugins.php?s=wp-job-manager' ),
		];

		return [
			'type'          => 'site-wide',
			'heading'       => $heading,
			'message'       => $message,
			'actions'       => $actions,
			'icon'          => false,
			'extra_details' => $extra_details,
			'conditions'    => [
				[
					'type'    => 'screens',
					'screens' => [ 'edit-job_listing', 'dashboard' ],
				],
			],
		];
	}

	/**
	 * Renders a notice.
	 *
	 * @param string $notice_id Unique identifier for the notice.
	 * @param array  $notice See `generate_notice_from_updates` for format.
	 */
	private static function render_notice( $notice_id, $notice ) {
		if ( empty( $notice['actions'] ) || ! is_array( $notice['actions'] ) ) {
			$notice['actions'] = [];
		}

		$notice_class   = [];
		$notice_class[] = 'wpjm-admin-notice--' . ( $notice['level'] ?? 'info' );

		$is_dismissible       = $notice['dismissible'] ?? true;
		$notice_wrapper_extra = '';
		if ( $is_dismissible ) {
			wp_enqueue_script( 'job_manager_notice_dismiss' );
			$notice_class[]       = 'is-dismissible';
			$notice_wrapper_extra = self::get_dismissible_notice_wrapper_attributes( $notice_id );
		}

		echo '<div class="notice wpjm-admin-notice ' . esc_attr( implode( ' ', $notice_class ) ) . '"';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		echo $notice_wrapper_extra . '>';

		echo '<div class="wpjm-admin-notice__top">';

		if ( ! isset( $notice['icon'] ) ) {
			$notice['icon'] = 'wpjm';
		}
		if ( ! empty( $notice['icon'] ) ) {
			echo '<img src="' . esc_url( self::get_icon( $notice['icon'] ) ) . '" class="wpjm-admin-notice__icon" alt="WP Job Manager Icon" />';
		}
		echo '<div class="wpjm-admin-notice__message">';
		if ( ! empty( $notice['label'] ) ) {
			echo '<div class="wpjm-admin-notice__label">';
			echo wp_kses( $notice['label'], self::ALLOWED_HTML );
			echo '</div>';
		}
		if ( ! empty( $notice['heading'] ) ) {
			echo '<div class="wpjm-admin-notice__heading">';
			echo wp_kses( $notice['heading'], self::ALLOWED_HTML );
			echo '</div>';
		}
		echo wp_kses( $notice['message'], self::ALLOWED_HTML );
		echo '</div>';
		echo '<div class="wpjm-admin-notice__actions">';
		if ( ! empty( $notice['actions'] ) ) {
			foreach ( $notice['actions'] as $action ) {
				if ( ! isset( $action['label'], $action['url'] ) ) {
					continue;
				}

				$button_class = $action['class'] ?? ( ! isset( $action['primary'] ) || $action['primary'] ? 'is-primary' : 'is-outline' );

				echo '<a href="' . esc_url( $action['url'] ) . '" target="' . esc_attr( $action['target'] ?? '_self' ) . '" rel="noopener noreferrer" class="wpjm-button ' . esc_attr( $button_class ) . '">';
				echo esc_html( $action['label'] );
				echo '</a>';

			}
		}
		if ( $is_dismissible ) {
			echo '<button type="button" class="wpjm-button is-link notice-dismiss wpjm-notice-dismiss wpjm-notice-dismiss--icon"><span class="screen-reader-text">' . esc_html__( 'Dismiss this notice', 'wp-job-manager' ) . '</span></button>';
		}
		echo '</div>';
		echo '</div>';

		if ( ! empty( $notice['image'] ) ) {
			echo '<div class="wpjm-admin-notice__image">';
			echo '<img src="' . esc_url( $notice['image'] ) . '" alt="" />';
			echo '</div>';
		}

		if ( ! empty( $notice['extra_details'] ) ) {
			echo '<div class="wpjm-admin-notice__extra_details">';
			echo wp_kses( $notice['extra_details'], self::ALLOWED_HTML );
			echo '</div>';
		}
		echo '</div>';

	}

	/**
	 * Get attributes for the notice wrapper for dismiss action.
	 *
	 * @param string $notice_id Notice ID.
	 *
	 * @return string
	 */
	public static function get_dismissible_notice_wrapper_attributes( $notice_id ) {
		return sprintf( ' data-dismiss-action="%1$s" data-dismiss-notice="%2$s" data-dismiss-nonce="%3$s"', esc_attr( self::DISMISS_NOTICE_ACTION ), esc_attr( $notice_id ), esc_attr( wp_create_nonce( self::DISMISS_NOTICE_ACTION ) ) );
	}

	/**
	 * Generate unique notice ID based on the updates available.
	 *
	 * @param array $updates The updates available.
	 *
	 * @return string The notice ID.
	 */
	private static function generate_notice_id_from_updates( $updates ) {
		$updates_info = '';
		foreach ( $updates as $update ) {
			$updates_info .= $update['plugin'] . '@' . $update['new_version'];
		}

		return self::NOTICE_ADDON_UPDATE_AVAILABLE . '-' . md5( $updates_info );
	}

	/**
	 * Adds notice to update Simple or WC paid listings plugin to use listing renewal feature.
	 *
	 * @since 1.41.0
	 *
	 * @param array $notices Existing notices.
	 *
	 * @return array Notices.
	 */
	public static function paid_listings_renewal_notice( $notices ) {
		if ( ! WP_Job_Manager_Helper_Renewals::is_wcpl_renew_compatible() ) {
			$notices['wcpl_listing_renewal'] = [
				'level'       => 'info',
				'dismissible' => true,
				'message'     => wp_kses_post(
					__( 'Listing renewals require the latest version of WC Paid Listings. Please update the plugin to enable the feature.', 'wp-job-manager' )
				),
			];
		}
		if ( ! WP_Job_Manager_Helper_Renewals::is_spl_renew_compatible() ) {
			$notices['spl_listing_renewal'] = [
				'level'       => 'info',
				'dismissible' => true,
				'message'     => wp_kses_post(
					__( 'Listing renewals require the latest version of Simple Paid Listings. Please update the plugin to enable the feature.', 'wp-job-manager' )
				),
			];
		}

		return $notices;
	}

	/**
	 * Add notice informing about extensions.
	 *
	 * @param array $notices Existing notices.
	 *
	 * @return array
	 */
	public static function we_have_addons_notice( $notices ) {
		if ( ! current_user_can( 'install_plugins' ) || self::has_notice( self::NOTICE_CORE_SETUP ) ) {
			return $notices;
		}

		$notices['we_have_addons'] = [
			'level'       => 'info',
			'dismissible' => true,
			'heading'     => __( 'Did you know?', 'wp-job-manager' ),
			'message'     => __( ' You can upgrade your job listings with Job Manager extensions and add applications, resumes, alerts, and more!', 'wp-job-manager' ),
			'actions'     => [
				[
					'label' => __( 'View Extensions', 'wp-job-manager' ),
					'url'   => admin_url( 'edit.php?post_type=job_listing&page=job-manager-marketplace' ),
				],
			],
		];

		return $notices;
	}
}

WP_Job_Manager_Admin_Notices::init();
