<?php
/**
 * File containing the class WP_Job_Manager_Settings.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the management of plugin settings.
 *
 * @since 1.0.0
 */
class WP_Job_Manager_Settings {

	/**
	 * Maximium value for the "Listing duration" setting (100 years).
	 */
	public const MAX_ALLOWED_SUBMISSION_DAYS = 36500;

	/**
	 * Maximum value for the "Listing limit" setting (1,000,000 listings).
	 */
	public const MAX_ALLOWED_SUBMISSION_LIMIT = 1000000;

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.26.0
	 */
	private static $instance = null;

	/**
	 * Our Settings.
	 *
	 * @var array Settings.
	 */
	protected $settings = [];

	/**
	 * Settings group.
	 *
	 * @var array Settings.
	 */
	protected $settings_group;

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
		$this->settings_group = 'job_manager';
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Get Job Manager Settings
	 *
	 * @return array
	 */
	public function get_settings() {
		if ( 0 === count( $this->settings ) ) {
			$this->init_settings();
		}
		return $this->settings;
	}

	/**
	 * Set a single setting.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value Value.
	 */
	public function set_setting( $option, $value ) {
		update_option( $option, $value );
	}

	/**
	 * Initializes the configuration for the plugin's setting fields.
	 *
	 * @access protected
	 */
	protected function init_settings() {
		// Prepare roles option.
		$roles         = function_exists( 'get_editable_roles' ) ? get_editable_roles() : [];
		$account_roles = [];

		foreach ( $roles as $key => $role ) {
			if ( 'administrator' === $key ) {
				continue;
			}
			$account_roles[ $key ] = $role['name'];
		}

		$this->settings = apply_filters(
			'job_manager_settings',
			[
				'general'        => [
					__( 'General', 'wp-job-manager' ),
					[
						[
							'name'    => 'job_manager_date_format',
							'std'     => 'relative',
							'label'   => __( 'Date Format', 'wp-job-manager' ),
							'desc'    => __( 'Choose how you want the published date for jobs to be displayed on the front-end.', 'wp-job-manager' ),
							'type'    => 'radio',
							'options' => [
								'relative' => __( 'Relative to the current date (e.g., 1 day, 1 week, 1 month ago)', 'wp-job-manager' ),
								'default'  => __( 'Default date format as defined in Settings', 'wp-job-manager' ),
							],
							'track'   => 'value',
						],
						[
							'name'       => \WP_Job_Manager\Stats::OPTION_ENABLE_STATS,
							'std'        => '0',
							'label'      => __( 'Job Statistics', 'wp-job-manager' ),
							'cb_label'   => __( 'Enable job statistics', 'wp-job-manager' ),
							'desc'       => __( 'Collect anonymous visitor data about job listings (page views, search impressions), and show them in the job dashboard.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'       => 'job_manager_google_maps_api_key',
							'std'        => '',
							'label'      => __( 'Google Maps API Key', 'wp-job-manager' ),
							// translators: Placeholder %s is URL to set up a Google Maps API key.
							'desc'       => sprintf( __( 'Google requires an API key to retrieve location information for job listings. Acquire an API key from the <a href="%s">Google Maps API developer site</a>.', 'wp-job-manager' ), 'https://developers.google.com/maps/documentation/geocoding/get-api-key' ),
							'attributes' => [],
						],
						[
							'name'       => 'job_manager_delete_data_on_uninstall',
							'std'        => '0',
							'label'      => __( 'Delete Data On Uninstall', 'wp-job-manager' ),
							'cb_label'   => __( 'Delete Job Manager data when the plugin is deleted. Once the plugin is uninstalled, only job listings can be restored (30 days).', 'wp-job-manager' ),
							'desc'       => '',
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'       => 'job_manager_bypass_trash_on_uninstall',
							'std'        => '0',
							'label'      => __( 'Bypass Trash For Job Listings', 'wp-job-manager' ),
							'cb_label'   => __( 'If checked, when the plugin is uninstalled, job listings will be permanently deleted immediately.', 'wp-job-manager' ),
							'desc'       => '',
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
					],
				],
				'job_listings'   => [
					__( 'Job Listings', 'wp-job-manager' ),
					[
						[
							'name'        => 'job_manager_per_page',
							'std'         => '10',
							'placeholder' => '',
							'label'       => __( 'Listings Per Page', 'wp-job-manager' ),
							'desc'        => __( 'Number of job listings to display per page.', 'wp-job-manager' ),
							'attributes'  => [],
							'track'       => 'value',
						],
						[
							'name'    => 'job_manager_job_listing_pagination_type',
							'std'     => 'load_more',
							'label'   => __( 'Pagination Type', 'wp-job-manager' ),
							'desc'    => __( 'Determines whether to show page numbered links or a Load More Listings button.', 'wp-job-manager' ),
							'type'    => 'radio',
							'options' => [
								'load_more'  => __( 'Load More Listings button', 'wp-job-manager' ),
								'pagination' => __( 'Page numbered links', 'wp-job-manager' ),
							],
							'track'   => 'value',
						],
						[
							'name'       => 'job_manager_hide_filled_positions',
							'std'        => '0',
							'label'      => __( 'Filled Listings', 'wp-job-manager' ),
							'cb_label'   => __( 'Hide filled listings', 'wp-job-manager' ),
							'desc'       => __( 'Filled job listings will not be included in search results, sitemap and feeds.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'       => 'job_manager_hide_expired',
							'std'        => get_option( 'job_manager_hide_expired_content' ) ? '1' : '0', // back compat.
							'label'      => __( 'Expired Listings', 'wp-job-manager' ),
							'cb_label'   => __( 'Hide expired listings', 'wp-job-manager' ),
							'desc'       => __( 'Expired job listings will not be shown to the users on the job board.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'       => 'job_manager_hide_expired_content',
							'std'        => '1',
							'label'      => __( 'Hide Expired Listings Content', 'wp-job-manager' ),
							'cb_label'   => __( 'Hide content in expired single job listings', 'wp-job-manager' ),
							'desc'       => __( 'Your site will display the titles of expired listings, but not the content of the listings. Otherwise, expired listings display their full content minus the application area.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'       => 'job_manager_enable_categories',
							'std'        => '0',
							'label'      => __( 'Categories', 'wp-job-manager' ),
							'cb_label'   => __( 'Enable listing categories', 'wp-job-manager' ),
							'desc'       => __( 'This lets users select from a list of categories when submitting a job. Note: an admin has to create categories before site users can select them.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'       => 'job_manager_enable_default_category_multiselect',
							'std'        => '0',
							'label'      => __( 'Multi-select Categories', 'wp-job-manager' ),
							'cb_label'   => __( 'Default to category multiselect', 'wp-job-manager' ),
							'desc'       => __( 'The category selection box will default to allowing multiple selections on the [jobs] shortcode. Without this, visitors will only be able to select a single category when filtering jobs.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'    => 'job_manager_category_filter_type',
							'std'     => 'any',
							'label'   => __( 'Category Filter Type', 'wp-job-manager' ),
							'desc'    => __( 'Determines the logic used to display jobs when selecting multiple categories.', 'wp-job-manager' ),
							'type'    => 'radio',
							'options' => [
								'any' => __( 'Jobs will be shown if within ANY selected category', 'wp-job-manager' ),
								'all' => __( 'Jobs will be shown if within ALL selected categories', 'wp-job-manager' ),
							],
							'track'   => 'value',
						],
						[
							'name'       => 'job_manager_enable_types',
							'std'        => '1',
							'label'      => __( 'Types', 'wp-job-manager' ),
							'cb_label'   => __( 'Enable listing types', 'wp-job-manager' ),
							'desc'       => __( 'This lets users select from a list of types when submitting a job. Note: an admin has to create types before site users can select them.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'       => 'job_manager_multi_job_type',
							'std'        => '0',
							'label'      => __( 'Multi-select Listing Types', 'wp-job-manager' ),
							'cb_label'   => __( 'Allow multiple types for listings', 'wp-job-manager' ),
							'desc'       => __( 'This allows users to select more than one type when submitting a job. The metabox on the post editor and the selection box on the front-end job submission form will both reflect this.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'       => 'job_manager_enable_remote_position',
							'std'        => '1',
							'label'      => __( 'Remote Position', 'wp-job-manager' ),
							'cb_label'   => __( 'Enable Remote Position', 'wp-job-manager' ),
							'desc'       => __( 'This lets users select if the listing is a remote position when submitting a job.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'       => 'job_manager_enable_salary',
							'std'        => '0',
							'label'      => __( 'Salary', 'wp-job-manager' ),
							'cb_label'   => __( 'Enable Job Salary', 'wp-job-manager' ),
							'desc'       => __( 'This lets users add a salary when submitting a job.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'       => 'job_manager_enable_salary_currency',
							'std'        => '0',
							'label'      => __( 'Salary Currency', 'wp-job-manager' ),
							'cb_label'   => __( 'Enable Job Salary Currency Customization', 'wp-job-manager' ),
							'desc'       => __( 'This lets users add a salary currency when submitting a job.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'        => 'job_manager_default_salary_currency',
							'std'         => 'USD',
							'label'       => __( 'Default Salary Currency', 'wp-job-manager' ),
							'cb_label'    => __( 'Default Currency used by salaries', 'wp-job-manager' ),
							'desc'        => __( 'Sets the default currency used by salaries', 'wp-job-manager' ),
							'type'        => 'text',
							'placeholder' => __( 'e.g. USD', 'wp-job-manager' ),
							'attributes'  => [],
							'track'       => 'value',
						],
						[
							'name'       => 'job_manager_enable_salary_unit',
							'std'        => '0',
							'label'      => __( 'Salary Unit', 'wp-job-manager' ),
							'cb_label'   => __( 'Enable Job Salary Unit Customization', 'wp-job-manager' ),
							'desc'       => __( 'This lets users add a salary unit when submitting a job.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'       => 'job_manager_default_salary_unit',
							'std'        => 'YEAR',
							'label'      => __( 'Default Salary Unit', 'wp-job-manager' ),
							'cb_label'   => __( 'Default Unit used by salaries', 'wp-job-manager' ),
							'desc'       => __( 'Sets the default period unit used by salaries', 'wp-job-manager' ),
							'type'       => 'select',
							'options'    => job_manager_get_salary_unit_options(),
							'attributes' => [],
							'track'      => 'value',
						],
						[
							'name'     => 'job_manager_display_location_address',
							'std'      => '0',
							'label'    => __( 'Location Display', 'wp-job-manager' ),
							'cb_label' => __( 'Display Location Address', 'wp-job-manager' ),
							'desc'     => __( 'Display the full address of the job listing location if it is detected by Google Maps Geocoding API. If full address is not available then it will display whatever text the user submitted for the location.', 'wp-job-manager' ),
							'type'     => 'checkbox',
							'track'    => 'bool',
						],
						[
							'name'     => 'job_manager_strip_job_description_shortcodes',
							'std'      => '0',
							'label'    => __( 'Strip Shortcodes', 'wp-job-manager' ),
							'cb_label' => __( 'Strip shortcodes from Job description', 'wp-job-manager' ),
							'desc'     => __( 'If enabled, shortcodes will be stripped from the job description on the single job listing page.', 'wp-job-manager' ),
							'type'     => 'checkbox',
							'track'    => 'bool',
						],
					],
				],
				'job_submission' => [
					__( 'Job Submission', 'wp-job-manager' ),
					[
						[
							'name'       => 'job_manager_user_requires_account',
							'std'        => '1',
							'label'      => __( 'Account Required', 'wp-job-manager' ),
							'cb_label'   => __( 'Require an account to submit listings', 'wp-job-manager' ),
							'desc'       => __( 'Limits job listing submissions to registered, logged-in users.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'       => 'job_manager_enable_registration',
							'std'        => '0',
							'label'      => __( 'Account Creation', 'wp-job-manager' ),
							'cb_label'   => __( 'Enable account creation during submission', 'wp-job-manager' ),
							'desc'       => __( 'Includes account creation on the listing submission form, to allow non-registered users to create an account and submit a job listing simultaneously.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[

							'name'       => 'job_manager_enable_scheduled_listings',
							'std'        => '0',
							'label'      => __( 'Scheduled Listings', 'wp-job-manager' ),
							'cb_label'   => __( 'Enable scheduled listings', 'wp-job-manager' ),
							'desc'       => __( 'Allow employers to set a date in the future for the listing to publish.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'       => 'job_manager_generate_username_from_email',
							'std'        => '1',
							'label'      => __( 'Account Username', 'wp-job-manager' ),
							'cb_label'   => __( 'Generate usernames from email addresses', 'wp-job-manager' ),
							'desc'       => __( 'Automatically generates usernames for new accounts from the registrant\'s email address. If this is not enabled, a "username" field will display instead.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'       => 'job_manager_use_standard_password_setup_email',
							'std'        => '1',
							'label'      => __( 'Account Password', 'wp-job-manager' ),
							'cb_label'   => __( 'Email new users a link to set a password', 'wp-job-manager' ),
							'desc'       => __( 'Sends an email to the user with their username and a link to set their password. If this is not enabled, a "password" field will display instead, and their email address won\'t be verified.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'    => 'job_manager_registration_role',
							'std'     => 'employer',
							'label'   => __( 'Account Role', 'wp-job-manager' ),
							'desc'    => __( 'Any new accounts created during submission will have this role. If you haven\'t enabled account creation during submission in the options above, your own method of assigning roles will apply.', 'wp-job-manager' ),
							'type'    => 'select',
							'options' => $account_roles,
							'track'   => 'value',
						],
						[
							'name'       => 'job_manager_submission_requires_approval',
							'std'        => '1',
							'label'      => __( 'Moderate New Listings', 'wp-job-manager' ),
							'cb_label'   => __( 'Require admin approval of all new listing submissions', 'wp-job-manager' ),
							'desc'       => __( 'Sets all new submissions to "pending." They will not appear on your site until an admin approves them.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'       => 'job_manager_user_can_edit_pending_submissions',
							'std'        => '0',
							'label'      => __( 'Allow Pending Edits', 'wp-job-manager' ),
							'cb_label'   => __( 'Allow editing of pending listings', 'wp-job-manager' ),
							'desc'       => __( 'Users can continue to edit pending listings until they are approved by an admin.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
						[
							'name'       => 'job_manager_user_edit_published_submissions',
							'std'        => 'yes',
							'label'      => __( 'Allow Published Edits', 'wp-job-manager' ),
							'cb_label'   => __( 'Allow editing of published listings', 'wp-job-manager' ),
							'desc'       => __( 'Choose whether published job listings can be edited and if edits require admin approval. When moderation is required, the original job listings will be unpublished while edits await admin approval.', 'wp-job-manager' ),
							'type'       => 'radio',
							'options'    => [
								'no'            => __( 'Users cannot edit', 'wp-job-manager' ),
								'yes'           => __( 'Users can edit without admin approval', 'wp-job-manager' ),
								'yes_moderated' => __( 'Users can edit, but edits require admin approval', 'wp-job-manager' ),
							],
							'attributes' => [],
							'track'      => 'value',
						],
						[
							'name'              => 'job_manager_submission_duration',
							'std'               => '30',
							'label'             => __( 'Listing Duration', 'wp-job-manager' ),
							'desc'              => __( 'Listings will display for the set number of days, then expire. Leave this field blank if you don\'t want listings to have an expiration date.', 'wp-job-manager' ),
							'type'              => 'number',
							'attributes'        => [],
							'sanitize_callback' => [ $this, 'sanitize_submission_duration' ],
							'placeholder'       => __( 'No limit', 'wp-job-manager' ),
							'track'             => 'value',
						],
						[
							'name'              => 'job_manager_renewal_days',
							'std'               => 5,
							'label'             => __( 'Renewal Window', 'wp-job-manager' ),
							'desc'              => __( 'Sets the number of days before expiration where users are given the option to renew their listings. For example, entering "7" will allow users to renew their listing one week before expiration. Entering "0" will disable renewals entirely.', 'wp-job-manager' ),
							'type'              => 'number',
							'attributes'        => [],
							'sanitize_callback' => [ $this, 'sanitize_renewal_days' ],
							'track'             => 'value',
						],
						[
							'name'              => 'job_manager_submission_limit',
							'std'               => '',
							'label'             => __( 'Listing Limit', 'wp-job-manager' ),
							'desc'              => __( 'How many listings are users allowed to post. Can be left blank to allow unlimited listings per account.', 'wp-job-manager' ),
							'type'              => 'number',
							'attributes'        => [],
							'sanitize_callback' => [ $this, 'sanitize_submission_limit' ],
							'placeholder'       => __( 'No limit', 'wp-job-manager' ),
							'track'             => 'value',
						],
						[
							'name'    => 'job_manager_allowed_application_method',
							'std'     => '',
							'label'   => __( 'Application Method', 'wp-job-manager' ),
							'desc'    => __( 'Choose the application method job listers will need to provide. Specify URL or email address only, or allow listers to choose which they prefer.', 'wp-job-manager' ),
							'type'    => 'radio',
							'options' => [
								''      => __( 'Email address or website URL', 'wp-job-manager' ),
								'email' => __( 'Email addresses only', 'wp-job-manager' ),
								'url'   => __( 'Website URLs only', 'wp-job-manager' ),
							],
							'track'   => 'value',
						],
						[
							'name'       => 'job_manager_show_agreement_job_submission',
							'std'        => '0',
							'label'      => __( 'Terms and Conditions Checkbox', 'wp-job-manager' ),
							'cb_label'   => __( 'Enable required Terms and Conditions checkbox on the form', 'wp-job-manager' ),
							'desc'       => __( 'Require a Terms and Conditions checkbox to be marked before a job can be submitted. The linked page can be set from the <a href="#settings-job_pages" class="nav-internal">Pages</a> settings tab.', 'wp-job-manager' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
					],
				],
				'captcha'        => [
					__( 'CAPTCHA', 'wp-job-manager' ),
					[
						[
							'name'        => 'job_manager_recaptcha_label',
							'std'         => __( 'Are you human?', 'wp-job-manager' ),
							'placeholder' => '',
							'label'       => __( 'Field Label', 'wp-job-manager' ),
							'desc'        => __( 'The label used for the CAPTCHA field on forms.', 'wp-job-manager' ),
							'attributes'  => [],
						],
						[
							'name'        => 'job_manager_recaptcha_version',
							'std'         => 'v2',
							'placeholder' => '',
							'label'       => __( 'reCAPTCHA Version', 'wp-job-manager' ),
							'desc'        => __( 'Choose between reCAPTCHA v2 or v3. Note: you will need API keys for the specific version you choose.', 'wp-job-manager' ),
							'type'        => 'radio',
							'options'     => [
								'v2' => __( 'reCaptcha v2', 'wp-job-manager' ),
								'v3' => __( 'reCaptcha v3', 'wp-job-manager' ),
							],
							'track'       => 'value',
						],
						[
							'name'        => 'job_manager_recaptcha_site_key',
							'std'         => '',
							'placeholder' => '',
							'label'       => __( 'Site Key', 'wp-job-manager' ),
							// translators: Placeholder %s is URL to set up Google reCAPTCHA API key.
							'desc'        => sprintf( __( 'You can retrieve your reCAPTCHA site key from <a href="%s">Google\'s reCAPTCHA admin dashboard</a>.', 'wp-job-manager' ), 'https://www.google.com/recaptcha/admin#list' ),
							'attributes'  => [],
						],
						[
							'name'        => 'job_manager_recaptcha_secret_key',
							'std'         => '',
							'placeholder' => '',
							'label'       => __( 'Secret Key', 'wp-job-manager' ),
							// translators: Placeholder %s is URL to set up Google reCAPTCHA API key.
							'desc'        => sprintf( __( 'You can retrieve your reCAPTCHA secret key from <a href="%s">Google\'s reCAPTCHA admin dashboard</a>.', 'wp-job-manager' ), 'https://www.google.com/recaptcha/admin#list' ),
							'attributes'  => [],
						],
						[
							'name'       => 'job_manager_enable_recaptcha_job_submission',
							'std'        => '0',
							'label'      => __( 'Job Submission Form', 'wp-job-manager' ),
							'cb_label'   => __( 'Display a CAPTCHA field on job submission form.', 'wp-job-manager' ),
							'desc'       => sprintf( __( 'This will help prevent bots from submitting job listings. You must have entered a valid site key and secret key above.', 'wp-job-manager' ), 'https://www.google.com/recaptcha/admin#list' ),
							'type'       => 'checkbox',
							'attributes' => [],
							'track'      => 'bool',
						],
					],
				],
				'job_pages'      => [
					__( 'Pages', 'wp-job-manager' ),
					[
						[
							'name'  => 'job_manager_submit_job_form_page_id',
							'std'   => '',
							'label' => __( 'Submit Job Form Page', 'wp-job-manager' ),
							'desc'  => __( 'Select the page where you\'ve used the [submit_job_form] shortcode. This lets the plugin know the location of the form.', 'wp-job-manager' ),
							'type'  => 'page',
							'track' => 'bool',
						],
						[
							'name'  => 'job_manager_job_dashboard_page_id',
							'std'   => '',
							'label' => __( 'Job Dashboard Page', 'wp-job-manager' ),
							'desc'  => __( 'Select the page where you\'ve used the [job_dashboard] shortcode. This lets the plugin know the location of the dashboard.', 'wp-job-manager' ),
							'type'  => 'page',
							'track' => 'bool',
						],
						[
							'name'  => 'job_manager_jobs_page_id',
							'std'   => '',
							'label' => __( 'Job Listings Page', 'wp-job-manager' ),
							'desc'  => __( 'Select the page where you\'ve used the [jobs] shortcode. This lets the plugin know the location of the job listings page.', 'wp-job-manager' ),
							'type'  => 'page',
							'track' => 'bool',
						],
						[
							'name'  => 'job_manager_terms_and_conditions_page_id',
							'std'   => '',
							'label' => __( 'Terms and Conditions Page', 'wp-job-manager' ),
							'desc'  => __( 'Select the page to link when "Terms and Conditions Checkbox" is enabled. See setting in "Job Submission" tab.', 'wp-job-manager' ),
							'type'  => 'page',
							'track' => 'bool',
						],
					],
				],
				'job_visibility' => [
					__( 'Job Visibility', 'wp-job-manager' ),
					[
						[
							'name'              => 'job_manager_browse_job_listings_capability',
							'std'               => [],
							'label'             => __( 'Browse Job Capability', 'wp-job-manager' ),
							'type'              => 'capabilities',
							'sanitize_callback' => [ $this, 'sanitize_capabilities' ],
							// translators: Placeholder %s is the url to the WordPress core documentation for capabilities and roles.
							'desc'              => sprintf( __( 'Enter which <a href="%s">roles or capabilities</a> allow visitors to browse job listings. If no value is selected, everyone (including logged out guests) will be able to browse job listings.', 'wp-job-manager' ), 'http://codex.wordpress.org/Roles_and_Capabilities' ),
							'track'             => 'bool',
						],
						[
							'name'              => 'job_manager_view_job_listing_capability',
							'std'               => [],
							'label'             => __( 'View Job Capability', 'wp-job-manager' ),
							'type'              => 'capabilities',
							'sanitize_callback' => [ $this, 'sanitize_capabilities' ],
							// translators: Placeholder %s is the url to the WordPress core documentation for capabilities and roles.
							'desc'              => sprintf( __( 'Enter which <a href="%s">roles or capabilities</a> allow visitors to view a single job listing. If no value is selected, everyone (including logged out guests) will be able to view job listings.', 'wp-job-manager' ), 'http://codex.wordpress.org/Roles_and_Capabilities' ),
							'track'             => 'bool',
						],
					],
				],
			]
		);
	}

	/**
	 * Registers the plugin's settings with WordPress's Settings API.
	 */
	public function register_settings() {
		$this->init_settings();

		foreach ( $this->settings as $section ) {
			foreach ( $section[1] as $option ) {
				if ( isset( $option['std'] ) ) {
					add_option( $option['name'], $option['std'] );
				}
				$args = [];
				if ( isset( $option['sanitize_callback'] ) ) {
					$args['sanitize_callback'] = $option['sanitize_callback'];
				}
				register_setting( $this->settings_group, $option['name'], $args );
			}
		}
	}

	/**
	 * Shows the plugin's settings page.
	 */
	public function output() {
		$this->init_settings();

		?>

		<div class="wrap job-manager-settings-wrap">
			<form class="job-manager-options" method="post" action="options.php">

				<?php settings_fields( $this->settings_group ); ?>

				<div class="job-manager-settings-header-wrap">
					<div class="job-manager-settings-header">
						<div class="job-manager-settings-header-row">
							<div class="job-manager-settings-logo-wrap">
								<?php
								// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes escaped in method.
								echo $this->get_logo();
								?>
							</div>
							<input type="submit" class="job-manager-settings-submit wpjm-button is-outline" value="<?php esc_attr_e( 'Save Changes', 'wp-job-manager' ); ?>" />
						</div>

						<div class="nav-tab-wrapper">
							<?php
							foreach ( $this->settings as $key => $section ) {
								echo '<a href="' . esc_url( '#settings-' . sanitize_title( $key ) ) . '" class="nav-tab">' . esc_html( $section[0] ) . '</a>';
							}
							?>
						</div>
					</div>
				</div>
				<div class="job-manager-settings-body">
					<div class="wp-header-end"></div>

				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Used for basic flow.
				if ( ! empty( $_GET['settings-updated'] ) ) {
					flush_rewrite_rules();
					echo '<div class="updated fade job-manager-updated"><p>' . esc_html__( 'Settings successfully saved', 'wp-job-manager' ) . '</p></div>';
				}

				foreach ( $this->settings as $key => $section ) {
					$section_args = isset( $section[2] ) ? (array) $section[2] : [];
					echo '<div id="settings-' . esc_attr( sanitize_title( $key ) ) . '" class="settings_panel">';
					if ( ! empty( $section_args['before'] ) ) {
						echo '<p class="before-settings">' . wp_kses_post( $section_args['before'] ) . '</p>';
					}
					echo '<table class="form-table settings parent-settings">';

					foreach ( $section[1] as $option ) {
						$value = get_option( $option['name'] );
						$this->output_field( $option, $value );
					}

					echo '</table>';
					if ( ! empty( $section_args['after'] ) ) {
						echo '<p class="after-settings">' . wp_kses_post( $section_args['after'] ) . '</p>';
					}
					echo '</div>';

				}
				?>
				</div>
				<div class="job-manager-settings-footer-mobile">
					<input type="submit" class="job-manager-settings-submit wpjm-button is-outline" value="<?php esc_attr_e( 'Save Changes', 'wp-job-manager' ); ?>" />
				</div>
			</form>
		</div>
		<script type="text/javascript">
			jQuery('.nav-internal').click(function (e) {
				e.preventDefault();
				jQuery('.nav-tab-wrapper a[href="' + jQuery(this).attr('href') + '"]').click();

				return false;
			});

			if ( jQuery.isFunction( jQuery.fn.select2 ) ) {

				if ( jQuery( '.settings-role-select' ).length > 0 ) {
					// This fixes a issue where backspace on role just turns it into search.
					// @see https://github.com/select2/select2/issues/3354#issuecomment-277419278 for more info.
					jQuery.fn.select2.amd.require(
						['select2/selection/search' ],
						function ( Search ) {
							Search.prototype.searchRemoveChoice = function (decorated, item) {
								this.trigger(
									'unselect',
									{
										data: item
									}
								);

								this.$search.val( '' );
								this.handleSearch();
							};
						},
						null,
						true
					);
				}
			}

			var job_listings_admin_select2_settings = {
				'tags': true // Allows for free entry of custom capabilities.
			};

			jQuery('.nav-tab-wrapper a').click(function() {
				if ( '#' !== jQuery(this).attr( 'href' ).substr( 0, 1 ) ) {
					return false;
				}
				jQuery('.settings_panel').hide();
				jQuery('.nav-tab-active').removeClass('nav-tab-active');
				var $content = jQuery( jQuery(this).attr('href') );
				$content.show();
				jQuery(this).addClass('nav-tab-active');
				window.location.hash = jQuery(this).attr('href');
				jQuery( 'form.job-manager-options' ).attr( 'action', 'options.php' + jQuery(this).attr( 'href' ) );
				$content.find( '.settings-role-select' ).select2( job_listings_admin_select2_settings );
				window.scrollTo( 0, 0 );
				return false;
			});
			var goto_hash = window.location.hash;
			if ( '#' === goto_hash.substr( 0, 1 ) ) {
				jQuery( 'form.job-manager-options' ).attr( 'action', 'options.php' + jQuery(this).attr( 'href' ) );
			}
			if ( goto_hash ) {
				var the_tab = jQuery( 'a[href="' + goto_hash + '"]' );
				if ( the_tab.length > 0 ) {
					the_tab.click();
				} else {
					jQuery( '.nav-tab-wrapper a:first' ).click();
				}
			} else {
				jQuery( '.nav-tab-wrapper a:first' ).click();
			}
			var $use_standard_password_setup_email = jQuery('#setting-job_manager_use_standard_password_setup_email');
			var $generate_username_from_email = jQuery('#setting-job_manager_generate_username_from_email');
			var $job_manager_registration_role = jQuery('#setting-job_manager_registration_role');
			var $job_manager_enable_salary_currency = jQuery('#setting-job_manager_enable_salary_currency');
			var $job_manager_enable_salary_unit = jQuery('#setting-job_manager_enable_salary_unit');
			var $job_manager_default_salary_currency = jQuery('#setting-job_manager_default_salary_currency');
			var $job_manager_default_salary_unit = jQuery('#setting-job_manager_default_salary_unit');

			jQuery('#setting-job_manager_enable_registration').change(function(){
				var $job_manager_enable_registration_is_checked = jQuery( this ).is(':checked');
				$job_manager_registration_role.closest('tr').toggle($job_manager_enable_registration_is_checked);
				$use_standard_password_setup_email.closest('tr').toggle($job_manager_enable_registration_is_checked);
				$generate_username_from_email.closest('tr').toggle($job_manager_enable_registration_is_checked);
			}).change();

			jQuery('#setting-job_manager_enable_salary').change(function(){
				var $job_manager_enable_salary_is_checked = jQuery( this ).is(':checked');
				$job_manager_enable_salary_currency.closest('tr').toggle($job_manager_enable_salary_is_checked);
				$job_manager_enable_salary_unit.closest('tr').toggle($job_manager_enable_salary_is_checked);
				$job_manager_default_salary_currency.closest('tr').toggle($job_manager_enable_salary_is_checked);
				$job_manager_default_salary_unit.closest('tr').toggle($job_manager_enable_salary_is_checked);
			}).change();

			jQuery( '.sub-settings-expander' ).on( 'change', function() {
				var $expandable = jQuery(this).parent().siblings( '.sub-settings-expandable' );
				var checked = jQuery(this).is( ':checked' );
				if ( checked ) {
					$expandable.addClass( 'expanded' );
				} else {
					$expandable.removeClass( 'expanded' );
				}
			} ).trigger( 'change' );
		</script>
		<?php
	}

	/**
	 * Checkbox input field.
	 *
	 * @param array  $option
	 * @param array  $attributes
	 * @param mixed  $value
	 * @param string $ignored_placeholder
	 */
	protected function input_checkbox( $option, $attributes, $value, $ignored_placeholder ) {
		if ( ! isset( $option['hidden_value'] ) ) {
			$option['hidden_value'] = '0';
		}
		?>
		<label>
		<input type="hidden" name="<?php echo esc_attr( $option['name'] ); ?>" value="<?php echo esc_attr( $option['hidden_value'] ); ?>" />
		<input
			id="setting-<?php echo esc_attr( $option['name'] ); ?>"
			name="<?php echo esc_attr( $option['name'] ); ?>"
			type="checkbox"
			value="1"
			<?php
			echo implode( ' ', $attributes ) . ' '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			checked( '1', $value );
			?>
		/> <?php echo wp_kses_post( $option['cb_label'] ); ?></label>
		<?php
		if ( ! empty( $option['desc'] ) ) {
			echo ' <p class="description">' . wp_kses_post( $option['desc'] ) . '</p>';
		}
	}

	/**
	 * Text area input field.
	 *
	 * @param array  $option
	 * @param array  $attributes
	 * @param mixed  $value
	 * @param string $placeholder
	 */
	protected function input_textarea( $option, $attributes, $value, $placeholder ) {
		?>
		<textarea
			id="setting-<?php echo esc_attr( $option['name'] ); ?>"
			class="large-text"
			cols="50"
			rows="3"
			name="<?php echo esc_attr( $option['name'] ); ?>"
			<?php
			echo implode( ' ', $attributes ) . ' '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $placeholder; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		><?php echo esc_textarea( $value ); ?></textarea>
		<?php

		if ( ! empty( $option['desc'] ) ) {
			echo ' <p class="description">' . wp_kses_post( $option['desc'] ) . '</p>';
		}
	}

	/**
	 * Select input field.
	 *
	 * @param array  $option
	 * @param array  $attributes
	 * @param mixed  $value
	 * @param string $ignored_placeholder
	 */
	protected function input_select( $option, $attributes, $value, $ignored_placeholder ) {
		?>
		<select
			id="setting-<?php echo esc_attr( $option['name'] ); ?>"
			class="regular-text"
			name="<?php echo esc_attr( $option['name'] ); ?>"
			autocomplete="off"
			<?php
			echo implode( ' ', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		>
		<?php
		foreach ( $option['options'] as $key => $name ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $name ) . '</option>';
		}
		?>
		</select>
		<?php

		if ( ! empty( $option['desc'] ) ) {
			echo ' <p class="description">' . wp_kses_post( $option['desc'] ) . '</p>';
		}
	}

	/**
	 * Color input field.
	 *
	 * @param array  $option
	 * @param array  $ignored_attributes
	 * @param mixed  $value
	 * @param string $ignored_placeholder
	 */
	protected function input_color( $option, $ignored_attributes, $value, $ignored_placeholder ) {
		?>
		<input
			id="setting-<?php echo esc_attr( $option['name'] ); ?>"
			class="job-alerts-color-picker"
			type="color"
			name="<?php echo esc_attr( $option['name'] ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
		/>
		<?php
		if ( ! empty( $option['desc'] ) ) {
			echo ' <p class="description">' . wp_kses_post( $option['desc'] ) . '</p>';
		}
	}

	/**
	 * Multiple Choice field output (radio or checkbox).
	 *
	 * @param array  $option Option data.
	 * @param mixed  $value  Current value.
	 * @param string $type   'radio' or 'checkbox'.
	 */
	protected function multiple_choice_output( $option, $value, $type ) {
		?>
		<fieldset>
		<legend class="screen-reader-text">
		<span><?php echo esc_html( $option['label'] ); ?></span>
		</legend>
		<?php
		if ( ! empty( $option['desc'] ) ) {
			echo ' <p class="description">' . wp_kses_post( $option['desc'] ) . '</p>';
		}
		foreach ( $option['options'] as $key => $name ) {
			$input_name = esc_attr( 'checkbox' === $type ? $option['name'] . '[fields][' . $key . ']' : $option['name'] );
			$input_type = esc_attr( $type );
			$is_checked = 'checkbox' === $type
				? checked( isset( $value['fields'] ) && is_array( $value['fields'] ) && in_array( $key, $value['fields'], true ), true, 0 )
				: checked( $value, $key, false );
			$label      = esc_html( $name );

			echo "<label><input name='{$input_name}' type='{$input_type}' value='{$key}' {$is_checked} />{$label}</label><br>"; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
		</fieldset>
		<?php
	}
	/**
	 * Radio input field.
	 *
	 * @param array  $option
	 * @param array  $ignored_attributes
	 * @param mixed  $value
	 * @param string $ignored_placeholder
	 */
	protected function input_radio( $option, $ignored_attributes, $value, $ignored_placeholder ) {
		$this->multiple_choice_output( $option, $value, 'radio' );
	}

	/**
	 * Multiple Checkbox input field.
	 *
	 * @param array  $option
	 * @param array  $ignored_attributes
	 * @param mixed  $value
	 * @param string $ignored_placeholder
	 */
	protected function input_multi_checkbox( $option, $ignored_attributes, $value, $ignored_placeholder ) {
		$this->multiple_choice_output( $option, $value, 'checkbox' );
	}
	/**
	 * Page input field.
	 *
	 * @param array  $option
	 * @param array  $ignored_attributes
	 * @param mixed  $value
	 * @param string $ignored_placeholder
	 */
	protected function input_page( $option, $ignored_attributes, $value, $ignored_placeholder ) {
		$args = [
			'name'             => $option['name'],
			'id'               => $option['name'],
			'sort_column'      => 'menu_order',
			'sort_order'       => 'ASC',
			'show_option_none' => __( '--no page--', 'wp-job-manager' ),
			'echo'             => false,
			'selected'         => absint( $value ),
		];

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe output.
		echo str_replace( ' id=', " data-placeholder='" . esc_attr__( 'Select a page&hellip;', 'wp-job-manager' ) . "' id=", wp_dropdown_pages( $args ) );

		if ( ! empty( $option['desc'] ) ) {
			echo ' <p class="description">' . wp_kses_post( $option['desc'] ) . '</p>';
		}
	}

	/**
	 * Hidden input field.
	 *
	 * @param array  $option
	 * @param array  $attributes
	 * @param mixed  $value
	 * @param string $ignored_placeholder
	 */
	protected function input_hidden( $option, $attributes, $value, $ignored_placeholder ) {
		$human_value = $value;
		if ( $option['human_value'] ) {
			$human_value = $option['human_value'];
		}
		?>
		<input
			id="setting-<?php echo esc_attr( $option['name'] ); ?>"
			type="hidden"
			name="<?php echo esc_attr( $option['name'] ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			<?php
			echo implode( ' ', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		/><strong><?php echo esc_html( $human_value ); ?></strong>
		<?php

		if ( ! empty( $option['desc'] ) ) {
			echo ' <p class="description">' . wp_kses_post( $option['desc'] ) . '</p>';
		}
	}

	/**
	 * Password input field.
	 *
	 * @param array  $option
	 * @param array  $attributes
	 * @param mixed  $value
	 * @param string $placeholder
	 */
	protected function input_password( $option, $attributes, $value, $placeholder ) {
		?>
		<input
			id="setting-<?php echo esc_attr( $option['name'] ); ?>"
			class="regular-text"
			type="password"
			name="<?php echo esc_attr( $option['name'] ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			<?php
			echo implode( ' ', $attributes ) . ' '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $placeholder; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		/>
		<?php

		if ( ! empty( $option['desc'] ) ) {
			echo ' <p class="description">' . wp_kses_post( $option['desc'] ) . '</p>';
		}
	}

	/**
	 * Number input field.
	 *
	 * @param array  $option
	 * @param array  $attributes
	 * @param mixed  $value
	 * @param string $placeholder
	 */
	protected function input_number( $option, $attributes, $value, $placeholder ) {
		$field_name      = $option['name'] ?? '';
		$text_class_name = 'small-text';

		$regular_text_inputs = [
			'job_manager_submission_duration' => true,
			'job_manager_submission_limit'    => true,
		];

		if ( isset( $regular_text_inputs[ $field_name ] ) ) {
			$text_class_name = 'regular-text';
		}

		echo isset( $option['before'] ) ? wp_kses_post( $option['before'] ) : '';
		?>
		<input
			id="setting-<?php echo esc_attr( $field_name ); ?>"
			class="<?php echo esc_attr( $text_class_name ); ?>"
			type="number"
			name="<?php echo esc_attr( $field_name ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			<?php
			echo implode( ' ', $attributes ) . ' '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $placeholder; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		/>
		<?php
		echo isset( $option['after'] ) ? wp_kses_post( $option['after'] ) : '';
		if ( ! empty( $option['desc'] ) ) {
			echo ' <p class="description">' . wp_kses_post( $option['desc'] ) . '</p>';
		}
	}

	/**
	 * Text input field.
	 *
	 * @param array  $option
	 * @param array  $attributes
	 * @param mixed  $value
	 * @param string $placeholder
	 */
	protected function input_text( $option, $attributes, $value, $placeholder ) {
		?>
		<input
			id="setting-<?php echo esc_attr( $option['name'] ); ?>"
			class="regular-text"
			type="text"
			name="<?php echo esc_attr( $option['name'] ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			<?php
			echo implode( ' ', $attributes ) . ' '; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $placeholder; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		/>
		<?php

		if ( ! empty( $option['desc'] ) ) {
			echo ' <p class="description">' . wp_kses_post( $option['desc'] ) . '</p>';
		}
	}

	/**
	 * Outputs the field row.
	 *
	 * @param array $option
	 * @param mixed $value
	 */
	protected function output_field( $option, $value ) {
		$placeholder    = ( ! empty( $option['placeholder'] ) ) ? 'placeholder="' . esc_attr( $option['placeholder'] ) . '"' : '';
		$class          = ! empty( $option['class'] ) ? $option['class'] : '';
		$option['type'] = ! empty( $option['type'] ) ? $option['type'] : 'text';
		$attributes     = [];
		if ( ! empty( $option['attributes'] ) && is_array( $option['attributes'] ) ) {
			foreach ( $option['attributes'] as $attribute_name => $attribute_value ) {
				$attributes[] = esc_attr( $attribute_name ) . '="' . esc_attr( $attribute_value ) . '"';
			}
		}

		echo '<tr valign="top" class="' . esc_attr( $class ) . '">';

		if ( ! empty( $option['label'] ) ) {
			echo '<th scope="row"><label for="setting-' . esc_attr( $option['name'] ) . '">' . esc_html( $option['label'] ) . '</a></th><td>';
		} else {
			echo '<td colspan="2">';
		}

		$method_name = 'input_' . $option['type'];
		if ( method_exists( $this, $method_name ) ) {
			$this->$method_name( $option, $attributes, $value, $placeholder );
		} else {
			/**
			 * Allows for custom fields in admin setting panes.
			 *
			 * @since 1.14.0
			 *
			 * @param string $option     Field name.
			 * @param array  $attributes Array of attributes.
			 * @param mixed  $value      Field value.
			 * @param string $value      Placeholder text.
			 */
			do_action( 'wp_job_manager_admin_field_' . $option['type'], $option, $attributes, $value, $placeholder );
		}
		echo '</td></tr>';
	}

	/**
	 * Multiple settings stored in one setting array that are shown when the `enable` setting is checked.
	 *
	 * @param array  $option
	 * @param array  $attributes
	 * @param array  $values
	 * @param string $placeholder
	 */
	protected function input_multi_enable_expand( $option, $attributes, $values, $placeholder ) {
		if ( empty( $values ) ) {
			$values = [];
		}

		echo '<div class="setting-enable-expand">';
		$enable_option               = $option['enable_field'];
		$enable_option['name']       = $option['name'] . '[' . $enable_option['name'] . ']';
		$enable_option['type']       = 'checkbox';
		$enable_option['attributes'] = [ 'class="sub-settings-expander"' ];

		if ( isset( $enable_option['force_value'] ) && is_bool( $enable_option['force_value'] ) ) {
			if ( true === $enable_option['force_value'] ) {
				$values[ $option['enable_field']['name'] ] = '1';
			} else {
				$values[ $option['enable_field']['name'] ] = '0';
			}

			$enable_option['hidden_value'] = $values[ $option['enable_field']['name'] ];
			$enable_option['attributes'][] = 'disabled="disabled"';
		}

		$value = $values[ $option['enable_field']['name'] ] ?? '';

		$this->input_checkbox( $enable_option, $enable_option['attributes'], $value, null );

		echo '<div class="sub-settings-expandable">';
		$this->input_multi( $option, $attributes, $values, $placeholder );
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Multiple settings stored in one setting array.
	 *
	 * @param array  $option
	 * @param array  $ignored_attributes
	 * @param array  $values
	 * @param string $ignored_placeholder
	 */
	protected function input_multi( $option, $ignored_attributes, $values, $ignored_placeholder ) {
		echo '<table class="form-table settings child-settings">';
		foreach ( $option['settings'] as $sub_option ) {
			$value              = isset( $values[ $sub_option['name'] ] ) ? $values[ $sub_option['name'] ] : $sub_option['std'];
			$sub_option['name'] = $option['name'] . '[' . $sub_option['name'] . ']';
			$this->output_field( $sub_option, $value );
		}
		echo '</table>';
	}

	/**
	 * Proxy for text input field.
	 *
	 * @param array  $option
	 * @param array  $attributes
	 * @param mixed  $value
	 * @param string $placeholder
	 */
	protected function input_input( $option, $attributes, $value, $placeholder ) {
		$this->input_text( $option, $attributes, $value, $placeholder );
	}

	/**
	 * Outputs the capabilities or roles input field.
	 *
	 * @param array    $option              Option arguments for settings input.
	 * @param string[] $attributes          Attributes on the HTML element. Strings must already be escaped.
	 * @param string[] $value               Current value.
	 * @param string   $ignored_placeholder We set the placeholder in the method. This is ignored.
	 */
	protected function input_capabilities( $option, $attributes, $value, $ignored_placeholder ) {
		if ( ! is_array( $value ) ) {
			$value = [ $value ];
		}

		$option['options']     = self::get_capabilities_and_roles( $value );
		$option['placeholder'] = esc_html__( 'Everyone (Public)', 'wp-job-manager' );

		?>
		<select
			id="setting-<?php echo esc_attr( $option['name'] ); ?>"
			class="regular-text settings-role-select"
			name="<?php echo esc_attr( $option['name'] ); ?>[]"
			multiple="multiple"
			data-placeholder="<?php echo esc_attr( $option['placeholder'] ); ?>"
			<?php
			echo implode( ' ', $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		>
			<?php
			foreach ( $option['options'] as $key => $name ) {
				echo '<option value="' . esc_attr( $key ) . '" ' . selected( in_array( $key, $value, true ) ? $key : null, $key, false ) . '>' . esc_html( $name ) . '</option>';
			}
			?>
		</select>
		<?php

		if ( ! empty( $option['desc'] ) ) {
			echo ' <p class="description">' . wp_kses_post( $option['desc'] ) . '</p>';
		}
	}

	/**
	 * Sanitize the options related to capabilities, making the necessary conversions
	 *
	 * @param string[]|string $value
	 * @return string[]
	 */
	public function sanitize_capabilities( $value ) {
		$value = wp_unslash( $value );
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		$result = [];

		if ( ! empty( $value ) ) {
			foreach ( $value as $item ) {
				$item = trim( sanitize_text_field( $item ) );
				if ( $item ) {
					$result[] = $item;
				}
			}
		}

		return $result;
	}

	/**
	 * Internal helper for numeric sanitization.
	 *
	 * @param stirng|int $value
	 * @param int        $min
	 * @param int        $max
	 * @param mixed      $default (optional).
	 * @param bool       $include_min (optional).
	 * @return string|int
	 */
	private function sanitize_numeric_boundaries( $value, $min, $max, $default = '', $include_min = true ) {
		if ( ! is_numeric( $value ) ) {
			return $default;
		}

		if ( ! $include_min && $value <= $min ) {
			return $default;
		} elseif ( $value < $min ) {
			return $default;
		}

		return $value > $max ? $default : $value;
	}

	/**
	 * Sanitize the submission duration value between 1 and MAX_ALLOWED_SUBMISSION_DAYS days
	 *
	 * @param string|int $value
	 * @return string|int
	 */
	public function sanitize_submission_duration( $value ) {
		return $this->sanitize_numeric_boundaries( $value, 0, self::MAX_ALLOWED_SUBMISSION_DAYS, '', false );
	}

	/**
	 * Sanitizes the renewal days value between 0 and MAX_ALLOWED_SUBMISSION_DAYS days
	 *
	 * @param string|int $value
	 * @return string|int
	 */
	public function sanitize_renewal_days( $value ) {
		return $this->sanitize_numeric_boundaries( $value, 0, self::MAX_ALLOWED_SUBMISSION_DAYS );
	}

	/**
	 * Sanitize the submission limit between 0 and MAX_ALLOWED_SUBMISSION_LIMIT
	 *
	 * @param string|int $value
	 * @return string|int
	 */
	public function sanitize_submission_limit( $value ) {
		return $this->sanitize_numeric_boundaries( $value, 0, self::MAX_ALLOWED_SUBMISSION_LIMIT );
	}

	/**
	 * Get the list of roles and capabilities to use in select dropdown.
	 *
	 * @param array $caps Selected capabilities to ensure they show up in the list.
	 * @return array
	 */
	private static function get_capabilities_and_roles( array $caps = [] ) {
		$capabilities_and_roles = [];
		$roles                  = get_editable_roles();

		foreach ( $roles as $key => $role ) {
			$capabilities_and_roles[ $key ] = $role['name'];
		}
		// Go through custom user selected capabilities and add them to the list.
		foreach ( $caps as $value ) {
			if ( ! is_string( $value ) || empty( $value ) || isset( $capabilities_and_roles[ $value ] ) ) {
				continue;
			}
			$capabilities_and_roles[ $value ] = $value;
		}

		return $capabilities_and_roles;
	}

	/**
	 * Get the logo for the page.
	 *
	 * @return string
	 */
	protected function get_logo(): string {
		return '<img class="job-manager-settings-logo"
					src="' . esc_url( JOB_MANAGER_PLUGIN_URL . '/assets/images/jm-full-logo.png' ) . '"
					alt="' . esc_attr__( 'Job Manager', 'wp-job-manager' ) . '" />';
	}
}
