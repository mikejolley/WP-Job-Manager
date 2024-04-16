<?php
/**
 * File containing the class WP_Job_Manager_Form_Submit_Job.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the editing of Job Listings from the public facing frontend (from within `[submit_job_form]` shortcode).
 *
 * @extends WP_Job_Manager_Form
 * @since 1.0.0
 */
class WP_Job_Manager_Form_Submit_Job extends WP_Job_Manager_Form {

	/**
	 * Form name.
	 *
	 * @var string
	 */
	public $form_name = 'submit-job';

	/**
	 * Job listing ID.
	 *
	 * @access protected
	 * @var int
	 */
	protected $job_id;

	/**
	 * Preview job (unused)
	 *
	 * @access protected
	 * @var string
	 */
	protected $preview_job;

	/**
	 * Stores static instance of class.
	 *
	 * @access protected
	 * @var WP_Job_Manager_Form_Submit_Job The single instance of the class
	 */
	protected static $instance = null;

	/**
	 * Resume edit session key.
	 *
	 * @var mixed
	 */
	private $resume_edit;

	/**
	 * Returns static instance of class.
	 *
	 * @return self
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
		add_action( 'wp', [ $this, 'process' ] );
		add_action( 'submit_job_form_start', [ $this, 'output_submit_form_nonce_field' ] );
		add_action( 'preview_job_form_start', [ $this, 'output_preview_form_nonce_field' ] );
		add_action( 'job_manager_job_submitted', [ $this, 'track_job_submission' ] );

		// Listing renewal support.
		WP_Job_Manager_Helper_Renewals::instance( $this );

		// Recaptcha support.
		WP_Job_Manager\WP_Job_Manager_Recaptcha::instance()->maybe_enable_recaptcha(
			'job_manager_enable_recaptcha_job_submission',
			[ 'submit_job_form_end' ],
			[ 'submit_job_form_validate_fields', 'submit_draft_job_form_validate_fields' ]
		);

		if ( $this->use_agreement_checkbox() ) {
			add_action( 'submit_job_form_end', [ $this, 'display_agreement_checkbox_field' ] );
			add_filter( 'submit_job_form_validate_fields', [ $this, 'validate_agreement_checkbox' ] );
		}

		$this->steps = (array) apply_filters(
			'submit_job_steps',
			[
				'submit'  => [
					'name'     => __( 'Submit Details', 'wp-job-manager' ),
					'view'     => [ $this, 'submit' ],
					'handler'  => [ $this, 'submit_handler' ],
					'priority' => 10,
				],
				'preview' => [
					'name'     => __( 'Preview', 'wp-job-manager' ),
					'view'     => [ $this, 'preview' ],
					'handler'  => [ $this, 'preview_handler' ],
					'priority' => 20,
				],
				'done'    => [
					'name'     => __( 'Done', 'wp-job-manager' ),
					'before'   => [ $this, 'done_before' ],
					'view'     => [ $this, 'done' ],
					'priority' => 30,
				],
			]
		);

		uasort( $this->steps, [ $this, 'sort_by_priority' ] );

		// phpcs:disable WordPress.Security.NonceVerification.Missing,  WordPress.Security.NonceVerification.Recommended -- Check happens later when possible. Input is used safely.
		// Get step/job.
		if ( isset( $_POST['step'] ) ) {
			$this->step = is_numeric( $_POST['step'] ) ? max( absint( $_POST['step'] ), 0 ) : array_search( sanitize_text_field( $_POST['step'] ), array_keys( $this->steps ), true );
		} elseif ( ! empty( $_GET['step'] ) ) {
			$this->step = is_numeric( $_GET['step'] ) ? max( absint( $_GET['step'] ), 0 ) : array_search( sanitize_text_field( $_GET['step'] ), array_keys( $this->steps ), true );
		}

		$this->job_id = ! empty( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;
		if ( 0 === $this->job_id ) {
			$this->job_id = ! empty( $_POST['job_id'] ) ? absint( $_POST['job_id'] ) : 0;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing,  WordPress.Security.NonceVerification.Recommended

		if ( ! job_manager_user_can_edit_job( $this->job_id ) ) {
			$this->job_id = 0;
		}

		// Allow resuming from cookie.
		$this->resume_edit = false;
		if (
			! isset( $_GET['new'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
			&& (
				'before' === get_option( 'job_manager_paid_listings_flow' )
				|| ! $this->job_id
			)
			&& ! empty( $_COOKIE['wp-job-manager-submitting-job-id'] )
			&& ! empty( $_COOKIE['wp-job-manager-submitting-job-key'] )
			&& empty( $this->job_id )
		) {
			$job_id     = absint( $_COOKIE['wp-job-manager-submitting-job-id'] );
			$job_status = get_post_status( $job_id );

			if (
				(
					'preview' === $job_status
					|| 'pending_payment' === $job_status
				)
				&& get_post_meta( $job_id, '_submitting_key', true ) === $_COOKIE['wp-job-manager-submitting-job-key']
			) {
				$this->job_id      = $job_id;
				$this->resume_edit = get_post_meta( $job_id, '_submitting_key', true );
			}
		}

		// Load job details.
		if ( $this->job_id ) {
			$job_status = get_post_status( $this->job_id );
			if ( 'expired' === $job_status || WP_Job_Manager_Helper_Renewals::job_can_be_renewed( $this->job_id ) ) {
				if ( ! job_manager_user_can_edit_job( $this->job_id ) ) {
					$this->job_id = 0;
					$this->step   = 0;
				}
			} elseif ( ! in_array( $job_status, apply_filters( 'job_manager_valid_submit_job_statuses', [ 'preview', 'draft' ] ), true ) ) {
				$this->job_id = 0;
				$this->step   = 0;
			}
		}
	}

	/**
	 * Gets the submitted job ID.
	 *
	 * @return int
	 */
	public function get_job_id() {
		return absint( $this->job_id );
	}

	/**
	 * Initializes the fields used in the form.
	 */
	public function init_fields() {
		if ( $this->fields ) {
			return;
		}

		$allowed_application_method = get_option( 'job_manager_allowed_application_method', '' );
		switch ( $allowed_application_method ) {
			case 'email':
				$application_method_label       = __( 'Application email', 'wp-job-manager' );
				$application_method_placeholder = __( 'you@example.com', 'wp-job-manager' );
				$application_method_sanitizer   = 'email';
				break;
			case 'url':
				$application_method_label       = __( 'Application URL', 'wp-job-manager' );
				$application_method_placeholder = __( 'https://', 'wp-job-manager' );
				$application_method_sanitizer   = 'url';
				break;
			default:
				$application_method_label       = __( 'Application email/URL', 'wp-job-manager' );
				$application_method_placeholder = __( 'Enter an email address or website URL', 'wp-job-manager' );
				$application_method_sanitizer   = 'url_or_email';
				break;
		}

		if ( job_manager_multi_job_type() ) {
			$job_type = 'term-multiselect';
		} else {
			$job_type = 'term-select';
		}
		$this->fields = apply_filters(
			'submit_job_form_fields',
			[
				'job'     => [
					'job_title'           => [
						'label'       => __( 'Job Title', 'wp-job-manager' ),
						'type'        => 'text',
						'required'    => true,
						'placeholder' => '',
						'priority'    => 1,
					],
					'job_location'        => [
						'label'       => __( 'Location', 'wp-job-manager' ),
						'description' => __( 'Leave this blank if the location is not important', 'wp-job-manager' ),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => __( 'e.g. "London"', 'wp-job-manager' ),
						'priority'    => 2,
					],
					'remote_position'     => [
						'label'       => __( 'Remote Position', 'wp-job-manager' ),
						'description' => __( 'Select if this is a remote position.', 'wp-job-manager' ),
						'type'        => 'checkbox',
						'required'    => false,
						'priority'    => 3,
					],
					'job_type'            => [
						'label'       => __( 'Job type', 'wp-job-manager' ),
						'type'        => $job_type,
						'required'    => true,
						'placeholder' => __( 'Choose job type&hellip;', 'wp-job-manager' ),
						'priority'    => 4,
						'default'     => 'full-time',
						'taxonomy'    => \WP_Job_Manager_Post_Types::TAX_LISTING_TYPE,
					],
					'job_category'        => [
						'label'       => __( 'Job category', 'wp-job-manager' ),
						'type'        => 'term-multiselect',
						'required'    => true,
						'placeholder' => '',
						'priority'    => 5,
						'default'     => '',
						'taxonomy'    => \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY,
					],
					'job_description'     => [
						'label'    => __( 'Description', 'wp-job-manager' ),
						'type'     => 'wp-editor',
						'required' => true,
						'priority' => 6,
					],
					'application'         => [
						'label'       => $application_method_label,
						'type'        => 'text',
						'sanitizer'   => $application_method_sanitizer,
						'required'    => true,
						'placeholder' => $application_method_placeholder,
						'priority'    => 7,
					],
					'job_salary'          => [
						'label'       => __( 'Salary', 'wp-job-manager' ),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => __( 'e.g. 20000', 'wp-job-manager' ),
						'priority'    => 8,
					],
					'job_salary_currency' => [
						'label'       => __( 'Salary Currency', 'wp-job-manager' ),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => __( 'e.g. USD', 'wp-job-manager' ),
						'description' => __( 'Add a salary currency, this field is optional. Leave it empty to use the default salary currency.', 'wp-job-manager' ),
						'priority'    => 9,
					],
					'job_salary_unit'     => [
						'label'       => __( 'Salary Unit', 'wp-job-manager' ),
						'type'        => 'select',
						'options'     => job_manager_get_salary_unit_options(),
						'description' => __( 'Add a salary period unit, this field is optional. Leave it empty to use the default salary unit, if one is defined.', 'wp-job-manager' ),
						'required'    => false,
						'priority'    => 10,
					],
				],
				'company' => [
					'company_name'    => [
						'label'       => __( 'Company name', 'wp-job-manager' ),
						'type'        => 'text',
						'required'    => true,
						'placeholder' => __( 'Enter the name of the company', 'wp-job-manager' ),
						'priority'    => 1,
					],
					'company_website' => [
						'label'       => __( 'Website', 'wp-job-manager' ),
						'type'        => 'text',
						'sanitizer'   => 'url',
						'required'    => false,
						'placeholder' => __( 'http://', 'wp-job-manager' ),
						'priority'    => 2,
					],
					'company_tagline' => [
						'label'       => __( 'Tagline', 'wp-job-manager' ),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => __( 'Briefly describe your company', 'wp-job-manager' ),
						'maxlength'   => 64,
						'priority'    => 3,
					],
					'company_video'   => [
						'label'       => __( 'Video', 'wp-job-manager' ),
						'type'        => 'text',
						'sanitizer'   => 'url',
						'required'    => false,
						'placeholder' => __( 'A link to a video about your company', 'wp-job-manager' ),
						'priority'    => 4,
					],
					'company_twitter' => [
						'label'       => __( 'Twitter username', 'wp-job-manager' ),
						'type'        => 'text',
						'required'    => false,
						'placeholder' => __( '@yourcompany', 'wp-job-manager' ),
						'priority'    => 5,
					],
					'company_logo'    => [
						'label'              => __( 'Logo', 'wp-job-manager' ),
						'type'               => 'file',
						'required'           => false,
						'placeholder'        => '',
						'priority'           => 6,
						'ajax'               => true,
						'multiple'           => false,
						'allowed_mime_types' => [
							'jpg'  => 'image/jpeg',
							'jpeg' => 'image/jpeg',
							'gif'  => 'image/gif',
							'png'  => 'image/png',
						],
					],
				],
			]
		);

		if ( ! get_option( 'job_manager_enable_categories' ) || 0 === intval( wp_count_terms( \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY ) ) ) {
			unset( $this->fields['job']['job_category'] );
		}
		if ( ! get_option( 'job_manager_enable_types' ) || 0 === intval( wp_count_terms( \WP_Job_Manager_Post_Types::TAX_LISTING_TYPE ) ) ) {
			unset( $this->fields['job']['job_type'] );
		}
		if ( get_option( 'job_manager_enable_salary' ) ) {
			if ( ! get_option( 'job_manager_enable_salary_currency' ) ) {
				unset( $this->fields['job']['job_salary_currency'] );
			}
			if ( ! get_option( 'job_manager_enable_salary_unit' ) ) {
				unset( $this->fields['job']['job_salary_unit'] );
			}
		} else {
			unset( $this->fields['job']['job_salary'], $this->fields['job']['job_salary_currency'], $this->fields['job']['job_salary_unit'] );
		}
		if ( ! get_option( 'job_manager_enable_remote_position' ) ) {
			unset( $this->fields['job']['remote_position'] );
		}

		if ( get_option( 'job_manager_enable_scheduled_listings' ) ) {
			$this->fields['job']['job_schedule_listing'] = [
				'label'       => __( 'Scheduled Date', 'wp-job-manager' ),
				'description' => __( 'Optionally set the date when this listing will be published.', 'wp-job-manager' ),
				'type'        => 'date',
				'required'    => false,
				'placeholder' => '',
				'priority'    => '6.5',
			];
		}

		return $this->fields;
	}

	/**
	 * Use agreement checkbox field on the form?
	 *
	 * @since 1.35.2
	 *
	 * @return bool
	 */
	private function use_agreement_checkbox() {
		return 1 === absint( get_option( 'job_manager_show_agreement_job_submission' ) );
	}

	/**
	 * Checks if application field should use skip email / URL validation.
	 *
	 * @return bool
	 */
	protected function should_application_field_skip_email_url_validation() {
		/**
		 * Force application field to skip email / URL validation.
		 *
		 * @since 1.34.2
		 *
		 * @param bool  $is_forced Whether the application field is forced to skip email / URL validation.
		 */
		return apply_filters( 'job_manager_application_field_skip_email_url_validation', false );
	}

	/**
	 * Validates the posted fields.
	 *
	 * @param array $values
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 * @throws Exception Uploaded file is not a valid mime-type or other validation error.
	 */
	protected function validate_fields( $values ) {
		foreach ( $this->fields as $group_key => $group_fields ) {
			foreach ( $group_fields as $key => $field ) {
				if (
					$field['required']
					&& ( ! isset( $field['empty'] ) || $field['empty'] )
				) {
					// translators: Placeholder %s is the label for the required field.
					return new WP_Error( 'validation-error', sprintf( __( '%s is a required field', 'wp-job-manager' ), $field['label'] ) );
				}
				if ( ! empty( $field['taxonomy'] ) && in_array( $field['type'], [ 'term-checklist', 'term-select', 'term-multiselect' ], true ) ) {
					if ( is_array( $values[ $group_key ][ $key ] ) ) {
						$check_value = $values[ $group_key ][ $key ];
					} else {
						$check_value = empty( $values[ $group_key ][ $key ] ) ? [] : [ $values[ $group_key ][ $key ] ];
					}
					foreach ( $check_value as $term ) {
						if ( ! term_exists( $term, $field['taxonomy'] ) ) {
							// translators: Placeholder %s is the field label that is did not validate.
							return new WP_Error( 'validation-error', sprintf( __( '%s is invalid', 'wp-job-manager' ), $field['label'] ) );
						}
					}
				}
				if ( 'file' === $field['type'] ) {
					if ( is_array( $values[ $group_key ][ $key ] ) ) {
						$check_value = array_filter( $values[ $group_key ][ $key ] );
					} else {
						$check_value = array_filter( [ $values[ $group_key ][ $key ] ] );
					}
					if ( ! empty( $check_value ) ) {
						foreach ( $check_value as $file_url ) {

							if ( ! is_numeric( $file_url ) ) {
								/**
								 * Set this flag to true to reject files from external URLs during job submission.
								 *
								 * @since 1.34.3
								 *
								 * @param bool   $reject_external_files  The flag.
								 * @param string $key                    The field key.
								 * @param string $group_key              The group.
								 * @param array  $field                  An array containing the information for the field.
								 */
								$reject_external_files = apply_filters( 'job_manager_submit_job_reject_external_files', false, $key, $group_key, $field );

								// Check image path.
								$baseurl = wp_upload_dir()['baseurl'];

								if ( $reject_external_files && 0 !== strpos( $file_url, $baseurl ) ) {
									throw new Exception( __( 'Invalid image path.', 'wp-job-manager' ) );
								}
							}

							// Check mime types.
							if ( ! empty( $field['allowed_mime_types'] ) ) {
								$file_url  = current( explode( '?', $file_url ) );
								$file_info = wp_check_filetype( $file_url );

								if ( ! is_numeric( $file_url ) && $file_info && ! in_array( $file_info['type'], $field['allowed_mime_types'], true ) ) {
									// translators: Placeholder %1$s is field label; %2$s is the file mime type; %3$s is the allowed mime-types.
									throw new Exception( sprintf( __( '"%1$s" (filetype %2$s) needs to be one of the following file types: %3$s', 'wp-job-manager' ), $field['label'], $file_info['ext'], implode( ', ', array_keys( $field['allowed_mime_types'] ) ) ) );
								}
							}

							// Check if attachment is valid.
							if ( is_numeric( $file_url ) ) {
								continue;
							}
							$file_url = esc_url( $file_url, [ 'http', 'https' ] );
							if ( empty( $file_url ) ) {
								throw new Exception( __( 'Invalid attachment provided.', 'wp-job-manager' ) );
							}
						}
					}
				}
				if ( empty( $field['file_limit'] ) && empty( $field['multiple'] ) ) {
					$field['file_limit'] = 1;
				}
				if ( 'file' === $field['type'] && ! empty( $field['file_limit'] ) ) {
					$file_limit = intval( $field['file_limit'] );
					if ( is_array( $values[ $group_key ][ $key ] ) ) {
						$check_value = array_filter( $values[ $group_key ][ $key ] );
					} else {
						$check_value = array_filter( [ $values[ $group_key ][ $key ] ] );
					}
					if ( count( $check_value ) > $file_limit ) {
						// translators: Placeholder %d is the number of files to that users are limited to.
						$message = esc_html__( 'You are only allowed to upload a maximum of %d files.', 'wp-job-manager' );
						if ( ! empty( $field['file_limit_message'] ) ) {
							$message = $field['file_limit_message'];
						}

						throw new Exception( esc_html( sprintf( $message, $file_limit ) ) );
					}
				}
			}
		}

		// Application method.
		if ( ! $this->should_application_field_skip_email_url_validation() && isset( $values['job']['application'] ) ) {
			$allowed_application_method = get_option( 'job_manager_allowed_application_method', '' );
			$application_required       = isset( $this->fields['job']['application']['required'] ) && $this->fields['job']['application']['required'];

			$is_valid = true;

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked earlier when required.
			$posted_value = isset( $_POST['application'] ) ? sanitize_text_field( wp_unslash( $_POST['application'] ) ) : false;
			if ( $posted_value && empty( $values['job']['application'] ) ) {
				$is_valid                                    = false;
				$this->fields['job']['application']['value'] = $posted_value;
			}

			if ( $application_required || ! empty( $values['job']['application'] ) ) {
				switch ( $allowed_application_method ) {
					case 'email':
						if ( ! $is_valid || ! is_email( $values['job']['application'] ) ) {
							throw new Exception( __( 'Please enter a valid application email address', 'wp-job-manager' ) );
						}
						break;
					case 'url':
						if ( ! $is_valid || ! filter_var( $values['job']['application'], FILTER_VALIDATE_URL ) ) {
							throw new Exception( __( 'Please enter a valid application URL', 'wp-job-manager' ) );
						}
						break;
					default:
						if ( ! is_email( $values['job']['application'] ) ) {
							if ( ! $is_valid || ! filter_var( $values['job']['application'], FILTER_VALIDATE_URL ) ) {
								throw new Exception( __( 'Please enter a valid application email address or URL', 'wp-job-manager' ) );
							}
						}
						break;
				}
			}
		}

		/**
		 * Perform additional validation on the job submission fields.
		 *
		 * @since 1.0.4
		 *
		 * @param bool  $is_valid Whether the fields are valid.
		 * @param array $fields   Array of all fields being validated.
		 * @param array $values   Submitted input values.
		 */
		return apply_filters( 'submit_job_form_validate_fields', true, $this->fields, $values );
	}

	/**
	 * Enqueues scripts and styles for editing and posting a job listing.
	 */
	protected function enqueue_job_form_assets() {
		wp_enqueue_script( 'wp-job-manager-job-submission' );

		WP_Job_Manager::register_style( 'wp-job-manager-job-submission', 'css/job-submission.css', [] );
		wp_enqueue_style( 'wp-job-manager-job-submission' );
	}

	/**
	 * Localize frontend scripts that have been enqueued. This should be called
	 * after the fields are rendered, in case some of them enqueue new scripts.
	 *
	 * @deprecated 1.34.1 No longer needed.
	 */
	public function localize_job_form_scripts() {
		_deprecated_function( __METHOD__, '1.34.1' );
	}

	/**
	 * Returns an array of the job types indexed by slug. (Unused)
	 *
	 * @return array
	 */
	private function job_types() {
		$options = [];
		$terms   = get_job_listing_types();
		foreach ( $terms as $term ) {
			$options[ $term->slug ] = $term->name;
		}
		return $options;
	}

	/**
	 * Displays the form.
	 */
	public function submit() {
		$this->init_fields();

		// Load data if necessary.
		if ( $this->job_id ) {
			$job = get_post( $this->job_id );
			foreach ( $this->fields as $group_key => $group_fields ) {
				foreach ( $group_fields as $key => $field ) {
					if ( isset( $this->fields[ $group_key ][ $key ]['value'] ) ) {
						continue;
					}

					switch ( $key ) {
						case 'job_title':
							$this->fields[ $group_key ][ $key ]['value'] = $job->post_title;
							break;
						case 'job_description':
							$this->fields[ $group_key ][ $key ]['value'] = $job->post_content;
							break;
						case 'job_type':
							$this->fields[ $group_key ][ $key ]['value'] = wp_get_object_terms( $job->ID, \WP_Job_Manager_Post_Types::TAX_LISTING_TYPE, [ 'fields' => 'ids' ] );
							if ( ! job_manager_multi_job_type() ) {
								$this->fields[ $group_key ][ $key ]['value'] = current( $this->fields[ $group_key ][ $key ]['value'] );
							}
							break;
						case 'job_category':
							$this->fields[ $group_key ][ $key ]['value'] = wp_get_object_terms( $job->ID, \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY, [ 'fields' => 'ids' ] );
							break;
						case 'company_logo':
							$this->fields[ $group_key ][ $key ]['value'] = has_post_thumbnail( $job->ID ) ? get_post_thumbnail_id( $job->ID ) : get_post_meta( $job->ID, '_' . $key, true );
							break;
						default:
							$this->fields[ $group_key ][ $key ]['value'] = get_post_meta( $job->ID, '_' . $key, true );
							break;
					}
				}
			}

			$this->fields = apply_filters( 'submit_job_form_fields_get_job_data', $this->fields, $job );

			// Get user meta.
		} elseif ( is_user_logged_in() && empty( $_POST['submit_job'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Safe input.
			if ( ! empty( $this->fields['company'] ) ) {
				foreach ( $this->fields['company'] as $key => $field ) {
					$this->fields['company'][ $key ]['value'] = get_user_meta( get_current_user_id(), '_' . $key, true );
				}
			}
			if ( ! empty( $this->fields['job']['application'] ) ) {
				$allowed_application_method = get_option( 'job_manager_allowed_application_method', '' );
				if ( 'url' !== $allowed_application_method ) {
					$current_user                                = wp_get_current_user();
					$this->fields['job']['application']['value'] = $current_user->user_email;
				}
			}
			$this->fields = apply_filters( 'submit_job_form_fields_get_user_data', $this->fields, get_current_user_id() );
		}

		$this->enqueue_job_form_assets();
		get_job_manager_template(
			'job-submit.php',
			[
				'form'               => $this->form_name,
				'job_id'             => $this->get_job_id(),
				'resume_edit'        => $this->resume_edit,
				'action'             => $this->get_action(),
				'job_fields'         => $this->get_fields( 'job' ),
				'company_fields'     => $this->get_fields( 'company' ),
				'step'               => $this->get_step(),
				'can_continue_later' => $this->can_continue_later(),
				'submit_button_text' => apply_filters( 'submit_job_form_submit_button_text', __( 'Preview', 'wp-job-manager' ) ),
			]
		);
	}

	/**
	 * Handles the submission of form data.
	 *
	 * @throws Exception On validation error.
	 */
	public function submit_handler() {
		try {
			// Init fields.
			$this->init_fields();

			// Get posted values.
			$values = $this->get_posted_fields();

			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Input is used safely. Nonce checked below when possible.
			$input_create_account_username        = isset( $_POST['create_account_username'] ) ? sanitize_text_field( wp_unslash( $_POST['create_account_username'] ) ) : false;
			$input_create_account_password        = isset( $_POST['create_account_password'] ) ? sanitize_text_field( wp_unslash( $_POST['create_account_password'] ) ) : false;
			$input_create_account_password_verify = isset( $_POST['create_account_password_verify'] ) ? sanitize_text_field( wp_unslash( $_POST['create_account_password_verify'] ) ) : false;
			$input_create_account_email           = isset( $_POST['create_account_email'] ) ? sanitize_text_field( wp_unslash( $_POST['create_account_email'] ) ) : false;
			$is_saving_draft                      = $this->can_continue_later() && ! empty( $_POST['save_draft'] );

			if ( empty( $_POST['submit_job'] ) && ! $is_saving_draft ) {
				return;
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			$this->check_submit_form_nonce_field();

			// Validate fields.
			if ( $is_saving_draft ) {
				/**
				 * Perform additional validation on the job submission fields when saving drafts.
				 *
				 * @since 1.33.1
				 *
				 * @param bool  $is_valid Whether the fields are valid.
				 * @param array $fields   Array of all fields being validated.
				 * @param array $values   Submitted input values.
				 */
				$validation_status = apply_filters( 'submit_draft_job_form_validate_fields', true, $this->fields, $values );
			} else {
				$validation_status = $this->validate_fields( $values );
			}

			if ( is_wp_error( $validation_status ) ) {
				throw new Exception( $validation_status->get_error_message() );
			}

			// Account creation.
			if ( ! is_user_logged_in() ) {
				$create_account = false;

				if ( job_manager_enable_registration() ) {
					if ( job_manager_user_requires_account() ) {
						if ( ! job_manager_generate_username_from_email() && empty( $input_create_account_username ) ) {
							throw new Exception( __( 'Please enter a username.', 'wp-job-manager' ) );
						}
						if ( ! wpjm_use_standard_password_setup_email() ) {
							if ( empty( $input_create_account_password ) ) {
								throw new Exception( __( 'Please enter a password.', 'wp-job-manager' ) );
							}
						}
						if ( empty( $input_create_account_email ) ) {
							throw new Exception( __( 'Please enter your email address.', 'wp-job-manager' ) );
						}
					}

					if ( ! wpjm_use_standard_password_setup_email() && ! empty( $input_create_account_password ) ) {
						if ( empty( $input_create_account_password_verify ) || $input_create_account_password_verify !== $input_create_account_password ) {
							throw new Exception( __( 'Passwords must match.', 'wp-job-manager' ) );
						}
						if ( ! wpjm_validate_new_password( sanitize_text_field( wp_unslash( $input_create_account_password ) ) ) ) {
							$password_hint = wpjm_get_password_rules_hint();
							if ( $password_hint ) {
								// translators: Placeholder %s is the password hint.
								throw new Exception( sprintf( __( 'Invalid Password: %s', 'wp-job-manager' ), $password_hint ) );
							} else {
								throw new Exception( __( 'Password is not valid.', 'wp-job-manager' ) );
							}
						}
					}

					if ( ! empty( $input_create_account_email ) ) {
						$create_account = wp_job_manager_create_account(
							[
								'username' => ( job_manager_generate_username_from_email() || empty( $input_create_account_username ) ) ? '' : $input_create_account_username,
								'password' => ( wpjm_use_standard_password_setup_email() || empty( $input_create_account_password ) ) ? '' : $input_create_account_password,
								'email'    => sanitize_text_field( wp_unslash( $input_create_account_email ) ),
								/**
								 * Allow customization of new user creation role
								 *
								 * @param string                         $role     New user registration role (pulled from 'job_manager_registration_role' option)
								 * @param array                          $values   Submitted input values.
								 * @param WP_Job_Manager_Form_Submit_Job $this     Current class object
								 *
								 * @since 1.35.0
								 */
								'role'     => apply_filters( 'submit_job_form_create_account_role', get_option( 'job_manager_registration_role' ), $values, $this ),
							]
						);
					}
				}

				if ( is_wp_error( $create_account ) ) {
					throw new Exception( $create_account->get_error_message() );
				}
			}

			if ( job_manager_user_requires_account() && ! is_user_logged_in() ) {
				throw new Exception( __( 'You must be signed in to post a new listing.', 'wp-job-manager' ) );
			}

			$post_status = '';
			if ( $is_saving_draft ) {
				$post_status = 'draft';
			} elseif ( ! $this->job_id || 'draft' === get_post_status( $this->job_id ) ) {
				$post_status = 'preview';
			}

			// Update the job.
			$this->save_job( $values['job']['job_title'], $values['job']['job_description'], $post_status, $values );
			$this->update_job_data( $values );

			// Mark this job as a public submission so the submission hook is fired.
			update_post_meta( $this->job_id, '_public_submission', true );

			if ( $this->job_id ) {
				// Reset the `_filled` flag.
				update_post_meta( $this->job_id, '_filled', 0 );
			}

			if ( $is_saving_draft ) {
				$job_dashboard_page_id = get_option( 'job_manager_job_dashboard_page_id', false );

				// translators: placeholder is the URL to the job dashboard page.
				$this->add_message( sprintf( __( 'Draft was saved. Job listing drafts can be resumed from the <a href="%s">job dashboard</a>.', 'wp-job-manager' ), get_permalink( $job_dashboard_page_id ) ) );
			} else {
				// Successful, show next step.
				$this->step++;
			}
		} catch ( Exception $e ) {
			$this->add_error( $e->getMessage() );
			return;
		}
	}

	/**
	 * Updates or creates a job listing from posted data.
	 *
	 * @param  string $post_title
	 * @param  string $post_content
	 * @param  string $status
	 * @param  array  $values
	 * @param  bool   $update_slug
	 */
	protected function save_job( $post_title, $post_content, $status = 'preview', $values = [], $update_slug = true ) {
		$job_data = [
			'post_title'     => $post_title,
			'post_content'   => $post_content,
			'post_type'      => \WP_Job_Manager_Post_Types::PT_LISTING,
			'comment_status' => 'closed',
		];

		if ( ! empty( $values['job']['job_schedule_listing'] ) ) {
			$is_scheduled_date = $this->apply_scheduled_date( $job_data, $values['job']['job_schedule_listing'] );

			if ( ! $is_scheduled_date ) {
				unset( $values['job']['job_schedule_listing'] );
			}
		}

		if ( $update_slug ) {
			$job_slug = [];

			// Prepend with company name.
			if ( apply_filters( 'submit_job_form_prefix_post_name_with_company', true ) && ! empty( $values['company']['company_name'] ) ) {
				$job_slug[] = $values['company']['company_name'];
			}

			// Prepend location.
			if ( apply_filters( 'submit_job_form_prefix_post_name_with_location', true ) && ! empty( $values['job']['job_location'] ) ) {
				$job_slug[] = $values['job']['job_location'];
			}

			// Prepend with job type.
			if ( apply_filters( 'submit_job_form_prefix_post_name_with_job_type', true ) && ! empty( $values['job']['job_type'] ) ) {
				if ( ! job_manager_multi_job_type() ) {
					$job_slug[] = $values['job']['job_type'];
				} else {
					$terms = $values['job']['job_type'];

					foreach ( $terms as $term ) {
						$term = get_term_by( 'id', intval( $term ), \WP_Job_Manager_Post_Types::TAX_LISTING_TYPE );

						if ( $term ) {
							$job_slug[] = $term->slug;
						}
					}
				}
			}

			$job_slug[]            = $post_title;
			$job_data['post_name'] = sanitize_title( implode( '-', $job_slug ) );
		}

		if ( $status ) {
			$job_data['post_status'] = $status;
		}

		$job_data = apply_filters( 'submit_job_form_save_job_data', $job_data, $post_title, $post_content, $status, $values );

		if ( $this->job_id ) {
			$job_data['ID'] = $this->job_id;
			wp_update_post( $job_data );
		} else {
			$this->job_id = wp_insert_post( $job_data );

			if ( ! headers_sent() ) {
				$submitting_key = uniqid();

				setcookie( 'wp-job-manager-submitting-job-id', $this->job_id, false, COOKIEPATH, COOKIE_DOMAIN, false );
				setcookie( 'wp-job-manager-submitting-job-key', $submitting_key, false, COOKIEPATH, COOKIE_DOMAIN, false );

				update_post_meta( $this->job_id, '_submitting_key', $submitting_key );
			}
		}
	}

	/**
	 * Creates a file attachment.
	 *
	 * @param  string $attachment_url
	 * @return int attachment id.
	 */
	protected function create_attachment( $attachment_url ) {
		include_once ABSPATH . 'wp-admin/includes/image.php';
		include_once ABSPATH . 'wp-admin/includes/media.php';

		$upload_dir     = wp_upload_dir();
		$attachment_url = esc_url( $attachment_url, [ 'http', 'https' ] );
		if ( empty( $attachment_url ) ) {
			return 0;
		}

		$attachment_url_parts = wp_parse_url( $attachment_url );

		// Relative paths aren't allowed.
		if ( false !== strpos( $attachment_url_parts['path'], '../' ) ) {
			return 0;
		}

		$attachment_url = sprintf( '%s://%s%s', $attachment_url_parts['scheme'], $attachment_url_parts['host'], $attachment_url_parts['path'] );

		$attachment_url = str_replace( [ $upload_dir['baseurl'], WP_CONTENT_URL, site_url( '/' ) ], [ $upload_dir['basedir'], WP_CONTENT_DIR, ABSPATH ], $attachment_url );
		if ( empty( $attachment_url ) || ! is_string( $attachment_url ) ) {
			return 0;
		}

		$attachment = [
			'post_title'   => wpjm_get_the_job_title( $this->job_id ),
			'post_content' => '',
			'post_status'  => 'inherit',
			'post_parent'  => $this->job_id,
			'guid'         => $attachment_url,
		];

		$info = wp_check_filetype( $attachment_url );
		if ( $info ) {
			$attachment['post_mime_type'] = $info['type'];
		}

		$attachment_id = wp_insert_attachment( $attachment, $attachment_url, $this->job_id );

		if ( ! is_wp_error( $attachment_id ) ) {
			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $attachment_url ) );
			return $attachment_id;
		}

		return 0;
	}

	/**
	 * Sets job meta and terms based on posted values.
	 *
	 * @param  array $values
	 */
	protected function update_job_data( $values ) {
		// Set defaults.
		add_post_meta( $this->job_id, '_filled', 0, true );
		add_post_meta( $this->job_id, '_featured', 0, true );

		$maybe_attach = [];

		// Loop fields and save meta and term data.
		foreach ( $this->fields as $group_key => $group_fields ) {
			foreach ( $group_fields as $key => $field ) {
				// Save taxonomies.
				if ( ! empty( $field['taxonomy'] ) ) {
					if ( is_array( $values[ $group_key ][ $key ] ) ) {
						wp_set_object_terms( $this->job_id, $values[ $group_key ][ $key ], $field['taxonomy'], false );
					} else {
						wp_set_object_terms( $this->job_id, [ $values[ $group_key ][ $key ] ], $field['taxonomy'], false );
					}

					// Company logo is a featured image.
				} elseif ( 'company_logo' === $key ) {
					$attachment_id = is_numeric( $values[ $group_key ][ $key ] ) ? absint( $values[ $group_key ][ $key ] ) : $this->create_attachment( $values[ $group_key ][ $key ] );
					if ( empty( $attachment_id ) ) {
						delete_post_thumbnail( $this->job_id );
					} else {
						set_post_thumbnail( $this->job_id, $attachment_id );
					}
					update_user_meta( get_current_user_id(), '_company_logo', $attachment_id );

					// Save meta data.
				} else {
					update_post_meta( $this->job_id, '_' . $key, $values[ $group_key ][ $key ] );

					// Handle attachments.
					if ( 'file' === $field['type'] ) {
						if ( is_array( $values[ $group_key ][ $key ] ) ) {
							foreach ( $values[ $group_key ][ $key ] as $file_url ) {
								$maybe_attach[] = $file_url;
							}
						} else {
							$maybe_attach[] = $values[ $group_key ][ $key ];
						}
					}
				}
			}
		}

		$maybe_attach = array_filter( $maybe_attach );

		// Handle attachments.
		if ( count( $maybe_attach ) && apply_filters( 'job_manager_attach_uploaded_files', true ) ) {
			// Get attachments.
			$attachments     = get_posts( 'post_parent=' . $this->job_id . '&post_type=attachment&fields=ids&numberposts=-1' );
			$attachment_urls = [];

			// Loop attachments already attached to the job.
			foreach ( $attachments as $attachment_id ) {
				$attachment_urls[] = wp_get_attachment_url( $attachment_id );
			}

			foreach ( $maybe_attach as $attachment_url ) {
				if ( ! in_array( $attachment_url, $attachment_urls, true ) ) {
					$this->create_attachment( $attachment_url );
				}
			}
		}

		// And user meta to save time in future.
		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), '_company_name', isset( $values['company']['company_name'] ) ? $values['company']['company_name'] : '' );
			update_user_meta( get_current_user_id(), '_company_website', isset( $values['company']['company_website'] ) ? $values['company']['company_website'] : '' );
			update_user_meta( get_current_user_id(), '_company_tagline', isset( $values['company']['company_tagline'] ) ? $values['company']['company_tagline'] : '' );
			update_user_meta( get_current_user_id(), '_company_twitter', isset( $values['company']['company_twitter'] ) ? $values['company']['company_twitter'] : '' );
			update_user_meta( get_current_user_id(), '_company_video', isset( $values['company']['company_video'] ) ? $values['company']['company_video'] : '' );
		}

		do_action( 'job_manager_update_job_data', $this->job_id, $values );
	}

	/**
	 * Displays preview of Job Listing.
	 */
	public function preview() {
		global $post, $job_preview;

		if ( $this->job_id ) {
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Job preview depends on temporary override. Reset below.
			$post              = get_post( $this->job_id );
			$job_preview       = true;
			$post->post_status = 'preview';

			setup_postdata( $post );

			get_job_manager_template(
				'job-preview.php',
				[
					'form' => $this,
				]
			);

			wp_reset_postdata();
		}
	}

	/**
	 * Helper which formats the scheduled date and sets the appropriate dates in the job data.
	 *
	 * @param array  $job_data       The job data array to modify.
	 * @param string $scheduled_date The scheduled date.
	 *
	 * @return bool True when the scheduled date is a valid future date, false otherwise.
	 */
	public static function apply_scheduled_date( array &$job_data, string $scheduled_date ): bool {
		$maybe_formatted_date = self::maybe_format_future_datetime( $scheduled_date );

		if ( false === $maybe_formatted_date ) {
			$job_data['post_date']     = current_time( 'mysql' );
			$job_data['post_date_gmt'] = current_time( 'mysql', 1 );

			return false;
		}

		$job_data['post_date']     = $maybe_formatted_date;
		$job_data['post_date_gmt'] = $maybe_formatted_date;

		return true;
	}

	/**
	 * Checks that a string is a valid future datetime. Formats datetime for post date.
	 *
	 * @param string $maybe_date_string The date to format.
	 *
	 * @return false|mixed
	 */
	private static function maybe_format_future_datetime( string $maybe_date_string ) {
		if ( empty( $maybe_date_string ) ) {
			return false;
		}

		$time = strtotime( $maybe_date_string );
		if ( false === $time ) {
			return false;
		}

		if ( $time < time() ) {
			return false;
		}

		$fmt = 'Y-m-d H:i:s';
		return wp_date( $fmt, $time );
	}

	/**
	 * Handles the preview step form response.
	 */
	public function preview_handler() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Input is used safely.
		if ( empty( $_POST ) ) {
			return;
		}

		$this->check_preview_form_nonce_field();

		// Edit = show submit form again.
		if ( ! empty( $_POST['edit_job'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Input is used safely.
			$this->step --;
		}

		// Continue = change job status then show next screen.
		if ( ! empty( $_POST['continue'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Input is used safely.
			$job = get_post( $this->job_id );

			if ( in_array( $job->post_status, [ 'preview', 'expired' ], true ) ) {
				// Reset expiry.
				delete_post_meta( $job->ID, '_job_expires' );

				// Update job listing.
				$update_job                = [];
				$update_job['ID']          = $job->ID;
				$update_job['post_status'] = apply_filters( 'submit_job_post_status', get_option( 'job_manager_submission_requires_approval' ) ? 'pending' : 'publish', $job );
				$update_job['post_author'] = get_current_user_id();

				$job_schedule_listing_date = get_post_meta( $job->ID, '_job_schedule_listing', true );
				$this->apply_scheduled_date( $update_job, $job_schedule_listing_date );

				wp_update_post( $update_job );
			}

			$this->step ++;
		}
	}

	/**
	 * Output the nonce field on job submission form.
	 */
	public function output_submit_form_nonce_field() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		wp_nonce_field( 'submit-job-' . $this->job_id, '_wpjm_nonce' );
	}

	/**
	 * Check the nonce field on the submit form.
	 */
	public function check_submit_form_nonce_field() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		if (
			empty( $_REQUEST['_wpjm_nonce'] )
			|| ! wp_verify_nonce( wp_unslash( $_REQUEST['_wpjm_nonce'] ), 'submit-job-' . $this->job_id ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce should not be modified.
		) {
			wp_nonce_ays( 'submit-job-' . $this->job_id );
			die();
		}
	}

	/**
	 * Output the nonce field on job preview form.
	 */
	public function output_preview_form_nonce_field() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		wp_nonce_field( 'preview-job-' . $this->job_id, '_wpjm_nonce' );
	}

	/**
	 * Check the nonce field on the preview form.
	 */
	public function check_preview_form_nonce_field() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		if (
			empty( $_REQUEST['_wpjm_nonce'] )
			|| ! wp_verify_nonce( wp_unslash( $_REQUEST['_wpjm_nonce'] ), 'preview-job-' . $this->job_id ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce should not be modified.
		) {
			wp_nonce_ays( 'preview-job-' . $this->job_id );
			die();
		}
	}

	/**
	 * Displays the final screen after a job listing has been submitted.
	 */
	public function done() {
		get_job_manager_template( 'job-submitted.php', [ 'job' => get_post( $this->job_id ) ] );
	}

	/**
	 * Handles the job submissions before the view is called.
	 */
	public function done_before() {
		delete_post_meta( $this->job_id, '_public_submission' );

		/**
		 * Trigger job submission action.
		 *
		 * @since 1.0.0
		 *
		 * @param int $job_id The job ID.
		 */
		do_action( 'job_manager_job_submitted', $this->job_id );
	}

	/**
	 * Checks if we can resume submission later.
	 *
	 * @return bool
	 */
	protected function can_continue_later() {
		$can_continue_later    = false;
		$job_dashboard_page_id = get_option( 'job_manager_job_dashboard_page_id', false );

		if ( ! $job_dashboard_page_id ) {
			// For now, we're going to block resuming later if no job dashboard page has been set.
			$can_continue_later = false;
		} elseif ( is_user_logged_in() ) {
			// If they're logged in, we can assume they can access the job dashboard to resume later.
			$can_continue_later = true;
		} elseif ( job_manager_user_requires_account() && job_manager_enable_registration() ) {
			// If these are enabled, we know an account will be created on save.
			$can_continue_later = true;
		}

		/**
		 * Override if visitor can resume job submission later.
		 *
		 * @param bool $can_continue_later True if they can resume job later.
		 */
		return apply_filters( 'submit_job_form_can_continue_later', $can_continue_later );
	}

	/**
	 * Send usage tracking event for job submission.
	 *
	 * @param int $post_id Post ID.
	 */
	public function track_job_submission( $post_id ) {
		WP_Job_Manager_Usage_Tracking::track_job_submission(
			$post_id,
			[
				'source'     => 'frontend',
				'old_status' => 'preview',
			]
		);
	}
}
