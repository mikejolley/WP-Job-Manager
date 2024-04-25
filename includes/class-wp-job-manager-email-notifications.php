<?php
/**
 * File containing the class WP_Job_Manager_Email_Notifications.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Pelago\Emogrifier\CssInliner;
use Pelago\Emogrifier\HtmlProcessor\CssToAttributeConverter;
/**
 * Base class for WP Job Manager's email notification system.
 *
 * @since 1.31.0
 */
final class WP_Job_Manager_Email_Notifications {
	const EMAIL_SETTING_PREFIX     = 'job_manager_email_';
	const EMAIL_SETTING_ENABLED    = 'enabled';
	const EMAIL_SETTING_PLAIN_TEXT = 'plain_text';

	/**
	 * Notifications to be scheduled.
	 *
	 * @var array
	 */
	private static $deferred_notifications = [];

	/**
	 * Sets up initial hooks.
	 *
	 * @static
	 */
	public static function init() {
		add_action( 'job_manager_send_notification', [ __CLASS__, 'schedule_notification' ], 10, 2 );
		add_action( 'job_manager_email_init', [ __CLASS__, 'lazy_init' ] );
		add_action( 'job_manager_email_job_details', [ __CLASS__, 'output_job_details' ], 10, 4 );
		add_action( 'job_manager_email_header', [ __CLASS__, 'output_header' ], 10, 3 );
		add_action( 'job_manager_email_footer', [ __CLASS__, 'output_footer' ], 10, 3 );
		add_action( 'job_manager_email_daily_notices', [ __CLASS__, 'send_employer_expiring_notice' ] );
		add_action( 'job_manager_email_daily_notices', [ __CLASS__, 'send_admin_expiring_notice' ] );
		add_filter( 'job_manager_settings', [ __CLASS__, 'add_job_manager_email_settings' ], 1 );
		add_action( 'job_manager_job_submitted', [ __CLASS__, 'send_new_job_notification' ] );
		add_action( 'job_manager_user_edit_job_listing', [ __CLASS__, 'send_updated_job_notification' ] );
	}

	/**
	 * Gets list of email notifications handled by WP Job Manager core.
	 *
	 * @return array
	 */
	public static function core_email_notifications() {
		return [
			'WP_Job_Manager_Email_Admin_New_Job',
			'WP_Job_Manager_Email_Admin_Updated_Job',
			'WP_Job_Manager_Email_Admin_Expiring_Job',
			'WP_Job_Manager_Email_Employer_Expiring_Job',
		];
	}

	/**
	 * Sets up an email notification to be sent at the end of the script's execution.
	 *
	 * Do not call manually.
	 *
	 * @access private
	 *
	 * @param string $notification
	 * @param array  $args
	 */
	public static function schedule_notification( $notification, $args = [] ) {
		self::maybe_init();

		self::$deferred_notifications[] = [ $notification, $args ];
	}

	/**
	 * Sends all notifications collected during execution.
	 *
	 * Do not call manually.
	 *
	 * @access private
	 */
	public static function send_deferred_notifications() {
		$email_notifications = self::get_email_notifications( true );

		foreach ( self::$deferred_notifications as $email ) {
			if (
				! is_string( $email[0] )
				|| ! isset( $email_notifications[ $email[0] ] )
			) {
				continue;
			}

			$email_class            = $email_notifications[ $email[0] ];
			$email_notification_key = $email[0];
			$email_args             = is_array( $email[1] ) ? $email[1] : [];

			self::send_email( $email[0], new $email_class( $email_args, self::get_email_settings( $email_notification_key ) ) );
		}

		self::$deferred_notifications = [];
	}

	/**
	 * Send email notifications for a specific email now and return the status. Not usually necessary but useful if you
	 * need to check to make sure a notification was sent.
	 *
	 * @param string $email_key Email key to send notifications for.
	 * @return bool True if an email notification was sent.
	 */
	public static function send_notifications( $email_key ) {
		$email_sent          = false;
		$email_notifications = self::get_email_notifications( true );
		foreach ( self::$deferred_notifications as $index => $email ) {
			if (
				! is_string( $email[0] )
				|| ! isset( $email_notifications[ $email[0] ] )
				|| $email[0] !== $email_key
			) {
				continue;
			}

			$email_class = $email_notifications[ $email[0] ];
			$email_args  = is_array( $email[1] ) ? $email[1] : [];

			if ( self::send_email( $email[0], new $email_class( $email_args, self::get_email_settings( $email_key ) ) ) ) {
				$email_sent = true;
			}

			unset( self::$deferred_notifications[ $index ] );
		}

		return $email_sent;
	}

	/**
	 * Initialize if necessary.
	 */
	public static function maybe_init() {
		if ( 0 === did_action( 'job_manager_email_init' ) ) {
			/**
			 * Lazily load remaining files needed for email notifications. Do this here instead of in
			 * `shutdown` for proper logging in case of syntax errors.
			 *
			 * @since 1.31.0
			 */
			do_action( 'job_manager_email_init' );
		}
	}

	/**
	 * Include email files.
	 *
	 * Do not call manually.
	 *
	 * @access private
	 */
	public static function lazy_init() {
		add_action( 'shutdown', [ __CLASS__, 'send_deferred_notifications' ] );

		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/emails/class-wp-job-manager-email-admin-new-job.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/emails/class-wp-job-manager-email-admin-updated-job.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/emails/class-wp-job-manager-email-employer-expiring-job.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/emails/class-wp-job-manager-email-admin-expiring-job.php';

		// Load Vendor Autoload.
		require_once JOB_MANAGER_PLUGIN_DIR . '/vendor/autoload.php';
	}

	/**
	 * Clear the deferred notifications email array.
	 *
	 * Do not call manually. Only for help with tests.
	 *
	 * @access private
	 */
	public static function clear_deferred_notifications() {
		if ( ! defined( 'PHPUNIT_WPJM_TESTSUITE' ) || ! PHPUNIT_WPJM_TESTSUITE ) {
			die( 'This is just for use while testing' );
		}
		self::$deferred_notifications = [];
	}

	/**
	 * Gets a list of all email notifications that WP Job Manager handles.
	 *
	 * @param bool $enabled_notifications_only
	 * @return array
	 */
	public static function get_email_notifications( $enabled_notifications_only = false ) {
		self::maybe_init();

		/**
		 * Retrieves all email notifications to be sent.
		 *
		 * @since 1.31.0
		 *
		 * @param array $email_notifications All the email notifications to be registered.
		 */
		$email_notification_classes = array_unique( apply_filters( 'job_manager_email_notifications', self::core_email_notifications() ) );
		$email_notifications        = [];

		/**
		 * Email class in loop.
		 *
		 * @var WP_Job_Manager_Email $email_class
		 */
		foreach ( $email_notification_classes as $email_class ) {
			// Check to make sure email notification is valid.
			if ( ! self::is_email_notification_valid( $email_class ) ) {
				continue;
			}

			// PHP 5.2: Using `call_user_func()` but `$email_class::get_key()` preferred.
			$email_notification_key = call_user_func( [ $email_class, 'get_key' ] );
			if (
				isset( $email_notifications[ $email_notification_key ] )
				|| ( $enabled_notifications_only && ! self::is_email_notification_enabled( $email_notification_key ) )
			) {
				continue;
			}

			$email_notifications[ $email_notification_key ] = $email_class;
		}

		return $email_notifications;
	}

	/**
	 * Show details about the job listing.
	 *
	 * @param WP_Post              $job            The job listing to show details for.
	 * @param WP_Job_Manager_Email $email          Email object for the notification.
	 * @param bool                 $sent_to_admin  True if this is being sent to an administrator.
	 * @param bool                 $plain_text     True if the email is being sent as plain text.
	 */
	public static function output_job_details( $job, $email, $sent_to_admin, $plain_text = false ) {
		$template_segment = self::locate_template_file( 'email-job-details', $plain_text );
		if ( ! file_exists( $template_segment ) ) {
			return;
		}

		$fields = self::get_job_detail_fields( $job, $sent_to_admin, $plain_text );

		include $template_segment;
	}

	/**
	 * Get the job fields to show in email templates.
	 *
	 * @param WP_Post $job
	 * @param bool    $sent_to_admin
	 * @param bool    $plain_text
	 * @return array
	 */
	private static function get_job_detail_fields( WP_Post $job, $sent_to_admin, $plain_text = false ) {
		$fields = [];

		$fields['job_title'] = [
			'label' => __( 'Job title', 'wp-job-manager' ),
			'value' => $job->post_title,
		];

		if ( $sent_to_admin || 'publish' === $job->post_status ) {
			$fields['job_title']['url'] = get_permalink( $job );
		}

		$job_location = get_the_job_location( $job );
		if ( ! empty( $job_location ) ) {
			$fields['job_location'] = [
				'label' => __( 'Location', 'wp-job-manager' ),
				'value' => $job_location,
			];
		}

		if ( get_option( 'job_manager_enable_types' ) && wp_count_terms( \WP_Job_Manager_Post_Types::TAX_LISTING_TYPE ) > 0 ) {
			$job_types = wpjm_get_the_job_types( $job );
			if ( ! empty( $job_types ) ) {
				$fields['job_type'] = [
					'label' => __( 'Job type', 'wp-job-manager' ),
					'value' => implode( ', ', wp_list_pluck( $job_types, 'name' ) ),
				];
			}
		}

		if ( get_option( 'job_manager_enable_categories' ) && wp_count_terms( \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY ) > 0 ) {
			$job_categories = wpjm_get_the_job_categories( $job );
			if ( ! empty( $job_categories ) ) {
				$fields['job_category'] = [
					'label' => __( 'Job category', 'wp-job-manager' ),
					'value' => implode( ', ', wp_list_pluck( $job_categories, 'name' ) ),
				];
			}
		}

		$company_name = get_the_company_name( $job );
		if ( ! empty( $company_name ) ) {
			$fields['company_name'] = [
				'label' => __( 'Company name', 'wp-job-manager' ),
				'value' => $company_name,
			];
		}

		$company_website = get_the_company_website( $job );
		if ( ! empty( $company_website ) ) {
			$fields['company_website'] = [
				'label' => __( 'Company website', 'wp-job-manager' ),
				'value' => $plain_text ? $company_website : sprintf( '<a href="%1$s">%1$s</a>', esc_url( $company_website, [ 'http', 'https' ] ) ),
			];
		}

		$job_expires    = WP_Job_Manager_Post_Types::instance()->get_job_expiration( $job );
		$wp_date_format = get_option( 'date_format' ) ?: JOB_MANAGER_DATE_FORMAT_FALLBACK;
		if ( ! empty( $job_expires ) ) {
			$job_expires_str       = wp_date( $wp_date_format, $job_expires->getTimestamp() );
			$fields['job_expires'] = [
				'label' => __( 'Listing expires', 'wp-job-manager' ),
				'value' => $job_expires_str,
			];
		}

		if ( $sent_to_admin ) {
			$author = get_user_by( 'ID', $job->post_author );
			if ( $author instanceof WP_User ) {
				$fields['author'] = [
					'label' => __( 'Posted by', 'wp-job-manager' ),
					'value' => $author->user_nicename,
					'url'   => 'mailto:' . $author->user_email,
				];
			}
		}

		/**
		 * Modify the fields shown in email notifications in the details summary a job listing.
		 *
		 * @since 1.31.0
		 *
		 * @param array   $fields         {
		 *     Array of fields. Each field is keyed with a unique identifier.
		 *     {
		 *          @type string $label Label to show next to field.
		 *          @type string $value Value for field.
		 *          @type string $url   URL to provide with the value (optional).
		 *     }
		 * }
		 * @param WP_Post $job            Job listing.
		 * @param bool    $sent_to_admin  True if being sent in an admin notification.
		 * @param bool    $plain_text     True if being sent as plain text.
		 */
		return apply_filters( 'job_manager_emails_job_detail_fields', $fields, $job, $sent_to_admin, $plain_text );
	}

	/**
	 * Output email header.
	 *
	 * @param string $email_notification_key  Email notification key for email being sent.
	 * @param bool   $sent_to_admin           True if this is being sent to an administrator.
	 * @param bool   $plain_text              True if the email is being sent as plain text.
	 */
	public static function output_header( $email_notification_key, $sent_to_admin, $plain_text = false ) {
		$template_segment = self::email_template_path_alternative( $email_notification_key, 'email-header', $plain_text );
		if ( false === $template_segment ) {
			$template_segment = self::locate_template_file( 'email-header', $plain_text );
		}
		if ( ! $template_segment || ! file_exists( $template_segment ) ) {
			return;
		}
		include $template_segment;
	}

	/**
	 * Output email footer.
	 *
	 * @param string $email_notification_key  Email notification key for email being sent.
	 * @param bool   $sent_to_admin           True if this is being sent to an administrator.
	 * @param bool   $plain_text              True if the email is being sent as plain text.
	 */
	public static function output_footer( $email_notification_key, $sent_to_admin, $plain_text = false ) {
		$template_segment = self::email_template_path_alternative( $email_notification_key, 'email-footer', $plain_text );
		if ( false === $template_segment ) {
			$template_segment = self::locate_template_file( 'email-footer', $plain_text );
		}
		if ( ! $template_segment || ! file_exists( $template_segment ) ) {
			return;
		}
		include $template_segment;
	}

	/**
	 * Checks for an alternative email template segment in the template path specified by the current email.
	 * Useful to provide alternative email headers and footers for a specific WPJM extension plugin.
	 *
	 * @param string $email_notification_key  Email notification key for email being sent.
	 * @param string $template_name           Name of the template to check.
	 * @param bool   $plain_text              True if the email is being sent as plain text.
	 * @return bool|string Returns path to template path alternative or false if none exists.
	 */
	private static function email_template_path_alternative( $email_notification_key, $template_name, $plain_text ) {
		$email_class = self::get_email_class( $email_notification_key );
		if ( ! $email_class || ! is_subclass_of( $email_class, 'WP_Job_Manager_Email_Template' ) ) {
			return false;
		}

		$template_default_path = call_user_func( [ $email_class, 'get_template_default_path' ] );
		if ( '' === $template_default_path ) {
			return false;
		}

		$template_path = call_user_func( [ $email_class, 'get_template_path' ] );
		$template      = self::locate_template_file( $template_name, $plain_text, $template_path, $template_default_path );
		if ( '' === $template ) {
			return false;
		}

		return $template;
	}

	/**
	 * Locate template file.
	 *
	 * @param string $template_name
	 * @param bool   $plain_text
	 * @param string $template_path
	 * @param string $default_path
	 * @return string
	 */
	public static function locate_template_file( $template_name, $plain_text = false, $template_path = 'job_manager', $default_path = '' ) {
		return locate_job_manager_template( WP_Job_Manager_Email_Template::generate_template_file_name( $template_name, $plain_text ), $template_path, $default_path );
	}

	/**
	 * Add email notification settings for the job manager context.
	 *
	 * @param array $settings
	 * @return array
	 */
	public static function add_job_manager_email_settings( $settings ) {
		return self::add_email_settings( $settings, WP_Job_Manager_Email::get_context() );
	}

	/**
	 * Add email notification settings for a context.
	 *
	 * @param array  $settings
	 * @param string $context
	 * @return array
	 */
	public static function add_email_settings( $settings, $context ) {
		$email_notifications = self::get_email_notifications( false );
		$email_settings      = [];

		foreach ( $email_notifications as $email_notification_key => $email_class ) {
			$email_notification_context = $email_class::get_context();
			if ( $context !== $email_notification_context ) {
				continue;
			}

			$email_settings[] = [
				'type'         => 'multi_enable_expand',
				'class'        => 'email-setting-row no-separator',
				'name'         => self::EMAIL_SETTING_PREFIX . $email_class::get_key(),
				'enable_field' => [
					'name'        => self::EMAIL_SETTING_ENABLED,
					'cb_label'    => $email_class::get_name(),
					'desc'        => $email_class::get_description(),
					'force_value' => $email_class::get_enabled_force_value(),
				],
				'label'        => false,
				'std'          => self::get_email_setting_defaults( $email_notification_key ),
				'settings'     => self::get_email_setting_fields( $email_notification_key ),
				'track'        => 'bool',
			];
		}

		if ( ! empty( $email_settings ) ) {
			$settings['email_notifications'] = [
				__( 'Email Notifications', 'wp-job-manager' ),
				$email_settings,
				[
					'before' => __( 'Select the email notifications to enable.', 'wp-job-manager' ),
				],
			];
		}

		return $settings;
	}

	/**
	 * Checks if a particular notification is enabled or not.
	 *
	 * @param string $email_notification_key
	 * @return bool
	 */
	public static function is_email_notification_enabled( $email_notification_key ) {
		$settings    = self::get_email_settings( $email_notification_key );
		$email_class = self::get_email_class( $email_notification_key );

		$enabled_force_value           = $email_class ? $email_class::get_enabled_force_value() : null;
		$is_email_notification_enabled = ! empty( $settings[ self::EMAIL_SETTING_ENABLED ] );

		if ( is_bool( $enabled_force_value ) ) {
			$is_email_notification_enabled = $enabled_force_value;
		}

		/**
		 * Filter whether an notification email is enabled.
		 *
		 * @since 1.31.0
		 *
		 * @param bool   $is_email_notification_enabled
		 * @param string $email_notification_key
		 */
		return apply_filters( 'job_manager_email_is_email_notification_enabled', $is_email_notification_enabled, $email_notification_key );
	}

	/**
	 * Checks if we should send emails using plain text.
	 *
	 * @since 1.31.0
	 * @since 1.34.1 Added arguments to allow for per-recipient customization.
	 *
	 * @param string $email_notification_key  Unique ID for this email notification.
	 * @param array  $args                    Email arguments.
	 * @return bool
	 */
	public static function send_as_plain_text( $email_notification_key, $args = [] ) {
		$settings = self::get_email_settings( $email_notification_key );

		$send_as_plain_text = ! empty( $settings[ self::EMAIL_SETTING_PLAIN_TEXT ] );

		/**
		 * Filter whether to send emails as plain text.
		 *
		 * @since 1.31.0
		 *
		 * @param bool   $send_as_plain_text      Whether to send this email as plain text.
		 * @param string $email_notification_key  Unique ID for this email notification.
		 * @param array  $args                    Email arguments.
		 */
		return apply_filters( 'job_manager_email_send_as_plain_text', $send_as_plain_text, $email_notification_key, $args );
	}

	/**
	 * Sending notices to employers for expiring job listings.
	 */
	public static function send_employer_expiring_notice() {
		self::maybe_init();

		$email_key = WP_Job_Manager_Email_Employer_Expiring_Job::get_key();
		if ( ! self::is_email_notification_enabled( $email_key ) ) {
			return;
		}
		$settings    = self::get_email_settings( $email_key );
		$days_notice = WP_Job_Manager_Email_Employer_Expiring_Job::get_notice_period( $settings );
		self::send_expiring_notice( $email_key, $days_notice );
	}

	/**
	 * Sending notices to the site administrator for expiring job listings.
	 */
	public static function send_admin_expiring_notice() {
		self::maybe_init();

		$email_key = WP_Job_Manager_Email_Admin_Expiring_Job::get_key();
		if ( ! self::is_email_notification_enabled( $email_key ) ) {
			return;
		}
		$settings    = self::get_email_settings( $email_key );
		$days_notice = WP_Job_Manager_Email_Admin_Expiring_Job::get_notice_period( $settings );
		self::send_expiring_notice( $email_key, $days_notice );
	}

	/**
	 * Fire the action to send a new job notification to the admin.
	 *
	 * @param int $job_id
	 */
	public static function send_new_job_notification( $job_id ) {
		do_action( 'job_manager_send_notification', 'admin_new_job', [ 'job_id' => $job_id ] );
	}

	/**
	 * Fire the action to send a updated job notification to the admin.
	 *
	 * @param int $job_id
	 */
	public static function send_updated_job_notification( $job_id ) {
		do_action( 'job_manager_send_notification', 'admin_updated_job', [ 'job_id' => $job_id ] );
	}

	/**
	 * Send notice based on job expiration date.
	 *
	 * @param string $email_notification_key
	 * @param int    $days_notice
	 */
	private static function send_expiring_notice( $email_notification_key, $days_notice ) {
		$notice_before_datetime = current_datetime()->add( new DateInterval( 'P' . $days_notice . 'D' ) );

		$job_ids = get_posts(
			[
				'post_type'      => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Used in production with no issues.
					[
						'key'   => '_job_expires',
						'value' => $notice_before_datetime->format( 'Y-m-d' ),
					],
				],
			]
		);

		if ( $job_ids ) {
			foreach ( $job_ids as $job_id ) {
				do_action( 'job_manager_send_notification', $email_notification_key, [ 'job_id' => $job_id ] );
			}
		}
	}

	/**
	 * Get the setting fields for an email.
	 *
	 * @param string $email_notification_key
	 * @return array
	 */
	private static function get_email_setting_fields( $email_notification_key ) {
		$email_class   = self::get_email_class( $email_notification_key );
		$core_settings = [
			[
				'name'    => 'plain_text',
				'std'     => '0',
				'label'   => __( 'Format', 'wp-job-manager' ),
				'type'    => 'radio',
				'options' => [
					'1' => __( 'Send plain text email', 'wp-job-manager' ),
					'0' => __( 'Send rich text email', 'wp-job-manager' ),
				],
			],
		];

		if ( 'employer_expiring_job' !== $email_notification_key ) {
			array_unshift(
				$core_settings,
				[
					'name'        => 'email_to',
					'placeholder' => 'Defaults to Admin email',
					'std'         => '',
					'label'       => __( 'Email', 'wp-job-manager' ),
					'type'        => 'text',
				]
			);
		}
		$email_settings = call_user_func( [ $email_class, 'get_setting_fields' ] );
		return array_merge( $core_settings, $email_settings );
	}

	/**
	 * Get the settings for the email.
	 *
	 * @param string $email_notification_key
	 * @return array
	 */
	private static function get_email_settings( $email_notification_key ) {
		$option_name  = self::EMAIL_SETTING_PREFIX . $email_notification_key;
		$option_value = get_option( $option_name );
		if ( empty( $option_value ) || ! is_array( $option_value ) ) {
			$option_value = [];
		}
		$default_settings = self::get_email_setting_defaults( $email_notification_key );

		return array_merge( $default_settings, $option_value );
	}

	/**
	 * Gets the default values for the email notification.
	 *
	 * @param string $email_notification_key
	 * @return array
	 */
	private static function get_email_setting_defaults( $email_notification_key ) {
		$settings    = self::get_email_setting_fields( $email_notification_key );
		$email_class = self::get_email_class( $email_notification_key );

		$defaults                                = [];
		$defaults[ self::EMAIL_SETTING_ENABLED ] = call_user_func( [ $email_class, 'is_default_enabled' ] ) ? '1' : '0';

		foreach ( $settings as $setting ) {
			$defaults[ $setting['name'] ] = null;
			if ( isset( $setting['std'] ) ) {
				$defaults[ $setting['name'] ] = $setting['std'];
			}
		}

		return $defaults;
	}

	/**
	 * Get the email class from the unique key.
	 *
	 * @param string $email_notification_key
	 * @return bool|string
	 */
	private static function get_email_class( $email_notification_key ) {
		$email_notifications = self::get_email_notifications( false );

		return isset( $email_notifications[ $email_notification_key ] ) ? $email_notifications[ $email_notification_key ] : false;
	}

	/**
	 * Returns the total number of deferred notifications to be sent.
	 *
	 * Do not use. Used just in unit tests.
	 *
	 * @access private
	 *
	 * @return int
	 */
	public static function get_deferred_notification_count() {
		return count( self::$deferred_notifications );
	}

	/**
	 * Returns the hash representation of the notifications to be sent.
	 *
	 * Do not use. Used just in unit tests.
	 *
	 * @access private
	 *
	 * @return string[]
	 */
	public static function get_deferred_notification_hashes() {
		return array_map(
			function( $value ) {
				return sha1( wp_json_encode( $value ) );
			},
			self::$deferred_notifications
		);
	}

	/**
	 * Confirms an email notification is valid.
	 *
	 * @access private
	 *
	 * @param string $email_class
	 * @return bool
	 */
	private static function is_email_notification_valid( $email_class ) {
		// PHP 5.2: Using `call_user_func()` but `$email_class::get_key()` preferred.
		return is_string( $email_class )
				&& class_exists( $email_class )
				&& is_subclass_of( $email_class, 'WP_Job_Manager_Email' )
				&& false !== call_user_func( [ $email_class, 'get_key' ] )
				&& false !== call_user_func( [ $email_class, 'get_name' ] );
	}

	/**
	 * Sends an email notification.
	 *
	 * @access private
	 *
	 * @param string               $email_notification_key
	 * @param WP_Job_Manager_Email $email
	 * @return bool
	 */
	private static function send_email( $email_notification_key, WP_Job_Manager_Email $email ) {

		global $job_manager_doing_email;
		$job_manager_doing_email = true;

		if ( ! $email->is_valid() ) {
			return false;
		}

		$fields = [ 'to', 'from', 'subject', 'rich_content', 'plain_content', 'attachments', 'cc', 'headers' ];
		$args   = [];
		foreach ( $fields as $field ) {
			$method = 'get_' . $field;

			/**
			 * Filter email values for job manager notifications.
			 *
			 * @since 1.31.0
			 *
			 * @param mixed                $email_field_value Value to be filtered.
			 * @param WP_Job_Manager_Email $email             Email notification object.
			 */
			$args[ $field ] = apply_filters( "job_manager_email_{$email_notification_key}_{$field}", $email->$method(), $email );
		}

		$args['headers'] = is_array( $args['headers'] ) ? $args['headers'] : [];
		$send_to         = $args['to'];
		if ( ! is_array( $send_to ) ) {
			$send_to = array_filter( array_map( 'sanitize_email', preg_split( '/[,|;]\s?/', $send_to ) ) );
		}

		$sent_count = 0;
		foreach ( $send_to as $to_email ) {
			$args['to'] = $to_email;

			$is_plain_text_only = self::send_as_plain_text( $email_notification_key, $args );

			$content_plain = self::get_email_content( $email_notification_key, $args, true );

			if ( $is_plain_text_only ) {
				$body = $content_plain;
			} else {
				$body = self::get_email_content( $email_notification_key, $args, false );
			}

			/**
			 * Filter all email arguments for job manager notifications.
			 *
			 * @since 1.34.1
			 *
			 * @param mixed                $email_field_value Value to be filtered.
			 * @param WP_Job_Manager_Email $email             Email notification object.
			 */
			$args = apply_filters( "job_manager_email_{$email_notification_key}_args", $args, $email );

			$headers = $args['headers'];
			if ( ! empty( $args['from'] ) ) {
				$headers[] = 'From: ' . $args['from'];
			}

			if ( ! empty( $args['cc'] ) ) {
				$headers[] = 'CC: ' . $args['cc'];
			}

			/**
			 * Allows for short-circuiting the actual sending of email notifications.
			 *
			 * @since 1.31.0
			 *
			 * @param bool                  $do_send_notification   True if we should send the notification.
			 * @param WP_Job_Manager_Email  $email                  Email notification object.
			 * @param array                 $args                   Email arguments for generating email.
			 * @param string                $content                Email content.
			 * @param array                 $headers                Email headers.
			 */
			if ( ! apply_filters( 'job_manager_email_do_send_notification', true, $email, $args, $body, $headers ) ) {
				continue;
			}

			$set_content_type = fn() => 'text/html';

			if ( ! $is_plain_text_only ) {
				add_filter( 'wp_mail_content_type', $set_content_type );
			}

			if ( wp_mail( $to_email, $args['subject'], $body, $headers, $args['attachments'] ) ) {
				$sent_count++;
			}

			remove_filter( 'wp_mail_content_type', $set_content_type );
		}

		$job_manager_doing_email = false;

		return $sent_count > 0;
	}

	/**
	 * Generates the content for an email.
	 *
	 * @access private
	 *
	 * @param string $email_notification_key Unique email notification key.
	 * @param array  $args Arguments passed for generating email.
	 * @param bool   $is_plain_text Whether to generate plain text or rich text content.
	 *
	 * @return string
	 */
	private static function get_email_content( $email_notification_key, $args, $is_plain_text ) {

		ob_start();

		/**
		 * Output the header for all job manager emails.
		 *
		 * @since 1.31.0
		 *
		 * @param string $email_notification_key Unique email notification key.
		 * @param array  $args Arguments passed for generating email.
		 * @param bool   $is_plain_text True if sending plain text email.
		 */
		do_action( 'job_manager_email_header', $email_notification_key, $args, $is_plain_text );

		if ( $is_plain_text ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain text e-mail.
			echo wp_specialchars_decode( wp_strip_all_tags( $args['plain_content'] ) );
		} else {
			echo wp_kses_post( ( $args['rich_content'] ) );
		}

		/**
		 * Output the footer for all job manager emails.
		 *
		 * @since 1.31.0
		 *
		 * @param string $email_notification_key Unique email notification key.
		 * @param array  $args Arguments passed for generating email.
		 * @param bool   $is_plain_text True if sending plain text email.
		 */
		do_action( 'job_manager_email_footer', $email_notification_key, $args, $is_plain_text );

		$content = ob_get_clean();
		if ( ! $is_plain_text ) {
			$content = self::inject_styles( $content );
		}

		/**
		 * Filter the content of the email.
		 *
		 * @since 1.31.0
		 *
		 * @param string $content Email content.
		 * @param string $email_notification_key Unique email notification key.
		 * @param array  $args Arguments passed for generating email.
		 * @param bool   $is_plain_text True if sending plain text email.
		 */
		return apply_filters( 'job_manager_email_content', $content, $email_notification_key, $args, $is_plain_text );
	}

	/**
	 * Inject inline styles into email content.
	 *
	 * @param string $content
	 * @return string
	 */
	private static function inject_styles( $content ) {
		if ( class_exists( CssInliner::class ) ) {
			try {
				$dom_document = CssInliner::fromHtml( $content )->inlineCss( self::get_styles() )->getDomDocument();
				$content      = CssToAttributeConverter::fromDomDocument( $dom_document )->convertCssToVisualAttributes()->render();

			} catch ( Exception $e ) {
				trigger_error( 'Unable to inject styles into email notification: ' . $e->getMessage() ); // @codingStandardsIgnoreLine
			}
		}
		return $content;
	}

	/**
	 * Gets the CSS styles to be used in email notifications.
	 *
	 * @return string
	 */
	private static function get_styles() {
		$email_styles_template = self::locate_template_file( 'email-styles' );
		if ( ! file_exists( $email_styles_template ) ) {
			return '';
		}
		ob_start();
		include $email_styles_template;
		return ob_get_clean();
	}

}
