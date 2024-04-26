<?php
/**
 * File containing the class WP_Job_Manager_Form.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parent abstract class for form classes.
 *
 * @abstract
 * @since 1.0.0
 */
abstract class WP_Job_Manager_Form {

	/**
	 * Form fields.
	 *
	 * @access protected
	 * @var array
	 */
	protected $fields = [];

	/**
	 * Form action.
	 *
	 * @access protected
	 * @var string
	 */
	protected $action = '';

	/**
	 * Form errors.
	 *
	 * @access protected
	 * @var array
	 */
	protected $errors = [];

	/**
	 * Form notices.
	 *
	 * @access protected
	 * @var array
	 */
	protected $messages = [];

	/**
	 * Form steps.
	 *
	 * @access protected
	 * @var array
	 */
	protected $steps = [];

	/**
	 * Current form step.
	 *
	 * @access protected
	 * @var int
	 */
	protected $step = 0;

	/**
	 * Form name.
	 *
	 * @access protected
	 * @var string
	 */
	public $form_name = '';

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, 'Unable to clone ' . __CLASS__, '1.0.0' );
	}

	/**
	 * Unserializes instances of this class is forbidden.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, 'Unable to wake up ' . __CLASS__, '1.0.0' );
	}

	/**
	 * Processes the form result and can also change view if step is complete.
	 */
	public function process() {

		// reset cookie.
		if (
			isset( $_GET['new'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
			isset( $_COOKIE['wp-job-manager-submitting-job-id'] ) &&
			isset( $_COOKIE['wp-job-manager-submitting-job-key'] ) &&
			get_post_meta( sanitize_text_field( wp_unslash( $_COOKIE['wp-job-manager-submitting-job-id'] ) ), '_submitting_key', true ) === $_COOKIE['wp-job-manager-submitting-job-key']
		) {
			delete_post_meta( sanitize_text_field( wp_unslash( $_COOKIE['wp-job-manager-submitting-job-id'] ) ), '_submitting_key' );
			setcookie( 'wp-job-manager-submitting-job-id', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, false );
			setcookie( 'wp-job-manager-submitting-job-key', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, false );
			wp_safe_redirect( remove_query_arg( [ 'new', 'key', 'job_manager_form' ] ) );
			exit;
		}

		$step_key = $this->get_step_key( $this->step );

		if ( $step_key && is_callable( $this->steps[ $step_key ]['handler'] ) ) {
			call_user_func( $this->steps[ $step_key ]['handler'] );
		}

		$next_step_key = $this->get_step_key( $this->step );

		// If the next step has a handler to call before going to the view, run it now.
		if (
			$next_step_key
			&& $step_key !== $next_step_key
			&& isset( $this->steps[ $next_step_key ]['before'] )
			&& is_callable( $this->steps[ $next_step_key ]['before'] )
		) {
			call_user_func( $this->steps[ $next_step_key ]['before'] );
		}

		// if the step changed, but the next step has no 'view', call the next handler in sequence.
		if ( $next_step_key && $step_key !== $next_step_key && ! is_callable( $this->steps[ $next_step_key ]['view'] ) ) {
			$this->process();
		}
	}

	/**
	 * Calls the view handler if set, otherwise call the next handler.
	 *
	 * @param array $atts Attributes to use in the view handler.
	 */
	public function output( $atts = [] ) {
		$step_key = $this->get_step_key( $this->step );
		$this->show_errors();
		$this->show_messages();

		if ( $step_key && is_callable( $this->steps[ $step_key ]['view'] ) ) {
			call_user_func( $this->steps[ $step_key ]['view'], $atts );
		}
	}

	/**
	 * Adds an error.
	 *
	 * @param string $error The error message.
	 */
	public function add_error( $error ) {
		$this->errors[] = $error;
	}

	/**
	 * Displays errors.
	 */
	public function show_errors() {
		foreach ( $this->errors as $error ) {
			echo '<div class="job-manager-error">' . wp_kses_post( $error ) . '</div>';
		}
	}

	/**
	 * Adds an notice.
	 *
	 * @param string $message The notice message.
	 */
	public function add_message( $message ) {
		$this->messages[] = $message;
	}

	/**
	 * Displays notice messages.
	 */
	public function show_messages() {
		foreach ( $this->messages as $message ) {
			echo '<div class="job-manager-info">' . wp_kses_post( $message ) . '</div>';
		}
	}

	/**
	 * Gets the action (URL for forms to post to).
	 * As of 1.22.2 this defaults to the current page permalink.
	 *
	 * @return string
	 */
	public function get_action() {
		$default_action = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$action         = esc_url_raw( $this->action ? $this->action : $default_action );

		/**
		 * Alter form submit action URL
		 *
		 * @since 1.35.0
		 *
		 * @param string              $action Form action value
		 * @param WP_Job_Manager_Form $this   Current form class object
		 *
		 * @return string
		 */
		return apply_filters( 'job_manager_get_form_action', $action, $this );
	}

	/**
	 * Gets form name.
	 *
	 * @since 1.24.0
	 * @return string
	 */
	public function get_form_name() {
		return $this->form_name;
	}

	/**
	 * Gets steps from outside of the class.
	 *
	 * @since 1.24.0
	 */
	public function get_steps() {
		return $this->steps;
	}

	/**
	 * Gets step from outside of the class.
	 */
	public function get_step() {
		return $this->step;
	}

	/**
	 * Gets step key from outside of the class.
	 *
	 * @since 1.24.0
	 * @param string|int $step
	 * @return string
	 */
	public function get_step_key( $step = '' ) {
		if ( ! $step ) {
			$step = $this->step;
		}
		$keys = array_keys( $this->steps );
		return isset( $keys[ $step ] ) ? $keys[ $step ] : '';
	}

	/**
	 * Sets step from outside of the class.
	 *
	 * @since 1.24.0
	 * @param int $step
	 */
	public function set_step( $step ) {
		$this->step = absint( $step );
	}

	/**
	 * Increases step from outside of the class.
	 */
	public function next_step() {
		$this->step ++;
	}

	/**
	 * Decreases step from outside of the class.
	 */
	public function previous_step() {
		$this->step --;
	}

	/**
	 * Gets fields for form.
	 *
	 * @param string $key
	 * @return array
	 */
	public function get_fields( $key ) {
		if ( empty( $this->fields[ $key ] ) ) {
			return [];
		}

		$fields = $this->fields[ $key ];

		uasort( $fields, [ $this, 'sort_by_priority' ] );

		return $fields;
	}

	/**
	 * Sorts array by priority value.
	 *
	 * @param array $a
	 * @param array $b
	 * @return int
	 */
	protected function sort_by_priority( $a, $b ) {
		$a_priority = isset( $a['priority'] ) ? $a['priority'] : 100000;
		$b_priority = isset( $b['priority'] ) ? $b['priority'] : 100000;
		if ( floatval( $a_priority ) === floatval( $b_priority ) ) {
			return 0;
		}
		return ( floatval( $a_priority ) < floatval( $b_priority ) ) ? -1 : 1;
	}

	/**
	 * Initializes form fields.
	 */
	protected function init_fields() {
		$this->fields = [];
	}

	/**
	 * Clears form fields (resets to empty array)
	 */
	public function clear_fields() {
		$this->fields = [];
	}

	/**
	 * Enqueue the scripts for the form.
	 */
	public function enqueue_scripts() {
		_deprecated_function( __METHOD__, '2.3.0', 'WP_Job_Manager\WP_Job_Manager_Form::enqueue_scripts' );
		WP_Job_Manager\WP_Job_Manager_Recaptcha::instance()->enqueue_scripts();
	}


	/**
	 * Output the reCAPTCHA field.
	 *
	 * @deprecated
	 */
	public function display_recaptcha_field() {
		_deprecated_function( __METHOD__, '2.3.0', 'WP_Job_Manager\WP_Job_Manager_Form::display_recaptcha_field' );
		WP_Job_Manager\WP_Job_Manager_Recaptcha::instance()->display_recaptcha_field();
	}

	/**
	 * Validate a reCAPTCHA field.
	 *
	 * @param bool $success
	 *
	 * @deprecated
	 *
	 * @return bool|\WP_Error
	 */
	public function validate_recaptcha_field( $success ) {
		_deprecated_function( __METHOD__, '2.3.0', 'WP_Job_Manager\WP_Job_Manager_Form::validate_recaptcha_field' );
		return WP_Job_Manager\WP_Job_Manager_Recaptcha::instance()->validate_recaptcha_field( $success );
	}

	/**
	 * Checks whether reCAPTCHA has been set up and is available.
	 *
	 * @deprecated
	 *
	 * @return bool
	 */
	public function is_recaptcha_available() {
		_deprecated_function( __METHOD__, '2.3.0', 'WP_Job_Manager\WP_Job_Manager_Form::is_recaptcha_available' );
		return WP_Job_Manager\WP_Job_Manager_Recaptcha::instance()->is_recaptcha_available();
	}

	/**
	 * Output the agreement checkbox field.
	 *
	 * @since 1.35.2
	 */
	public function display_agreement_checkbox_field() {
		$terms_page_id = get_option( 'job_manager_terms_and_conditions_page_id', false );
		$terms_label   = esc_html__( 'Terms and Conditions', 'wp-job-manager' );

		// translators: %1$s placeholder is the Terms and Conditions page link; %2$s placeholder is the Terms and Conditions label.
		$terms_placeholder = $terms_page_id ? sprintf(
			'<a href="%1$s" target="_blank">%2$s</a>',
			get_permalink( $terms_page_id ),
			$terms_label
		) : $terms_label;

		$label_content = sprintf(
			// translators: Placeholder %s is the Terms and Conditions placeholder.
			esc_html__( 'I accept the %s.', 'wp-job-manager' ),
			$terms_placeholder
		);

		$field             = [];
		$field['required'] = true;

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Check only.
		$field['checked'] = isset( $_POST['agreement-checkbox'] ) && '1' === $_POST['agreement-checkbox'];

		/**
		 * Filters the agreement checkbox label.
		 *
		 * @since 1.35.2
		 *
		 * @param string $label Agreement label.
		 */
		$field['label'] = apply_filters( 'job_manager_agreement_label', $label_content );

		get_job_manager_template(
			'form-fields/full-line-checkbox-field.php',
			[
				'key'   => 'agreement-checkbox',
				'field' => $field,
			]
		);
	}

	/**
	 * Validate a agreement checkbox field.
	 *
	 * @since 1.35.2
	 *
	 * @param bool $success
	 *
	 * @return bool|WP_Error
	 */
	public function validate_agreement_checkbox( $success ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Check only.
		if ( empty( $_POST['agreement-checkbox'] ) ) {
			return new WP_Error( 'validation-error', esc_html__( 'Terms and Conditions is a required field', 'wp-job-manager' ) );
		}

		return $success;
	}

	/**
	 * Gets post data for fields.
	 *
	 * @return array of data.
	 */
	protected function get_posted_fields() {
		$this->init_fields();

		$values = [];

		foreach ( $this->fields as $group_key => $group_fields ) {
			foreach ( $group_fields as $key => $field ) {
				// Get the value.
				$field_type = str_replace( '-', '_', $field['type'] );
				$handler    = apply_filters( "job_manager_get_posted_{$field_type}_field", false );

				$this->fields[ $group_key ][ $key ]['empty'] = false;

				if ( ! isset( $field['before_sanitize'] ) ) {
					$field['before_sanitize'] = function ( $value ) use ( $group_key, $key ) {
						if ( is_string( $value ) ) {
							$value = trim( $value );
						}

						$this->fields[ $group_key ][ $key ]['empty'] = $this->is_empty( $value, $key );
					};
				}

				if ( $handler ) {
					$values[ $group_key ][ $key ] = call_user_func( $handler, $key, $field );
				} elseif ( method_exists( $this, "get_posted_{$field_type}_field" ) ) {
					$values[ $group_key ][ $key ] = call_user_func( [ $this, "get_posted_{$field_type}_field" ], $key, $field );
				} else {
					$values[ $group_key ][ $key ] = $this->get_posted_field( $key, $field );
				}

				$this->fields[ $group_key ][ $key ]['value'] = $values[ $group_key ][ $key ];
			}
		}

		/**
		 * Alter values for posted fields.
		 *
		 * Before submitting or editing a job, alter the posted values before they get stored into the database.
		 *
		 * @since 1.32.0
		 *
		 * @param array  $values  The values that have been submitted.
		 * @param array  $fields  The form fields.
		 */
		return apply_filters( 'job_manager_get_posted_fields', $values, $this->fields );
	}

	/**
	 * Checks whether a value is empty.
	 *
	 * @param string|numeric|array|boolean $value The value that is being checked.
	 * @param string                       $key   The key of the field that is being checked.
	 * @return bool True if value is empty, false otherwise.
	 */
	protected function is_empty( $value, $key = '' ) {
		/**
		 * Filter values considered as empty or falsy for required fields.
		 * Useful for example if you want to consider zero (0) as a non-empty value.
		 *
		 * @see http://php.net/manual/en/function.empty.php -- standard default empty values
		 *
		 * @since 1.36.0
		 *
		 * @param array  $false_vals A list of values considered as falsy.
		 * @param string $key        The key that this is being used for.
		 */
		$false_vals = apply_filters( 'submit_job_form_validate_fields_empty_values', [ '', 0, 0.0, '0', null, false, [] ], $key );

		// strict true for type checking.
		if ( in_array( $value, $false_vals, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Navigates through an array and sanitizes the field.
	 *
	 * @since 1.22.0
	 * @since 1.29.1 Added the $sanitizer argument
	 *
	 * @param array|string    $value      The array or string to be sanitized.
	 * @param string|callable $sanitizer  The sanitization method to use. Built in: `url`, `email`, `url_or_email`, or
	 *                                      default (text). Custom single argument callable allowed.
	 * @return array|string   $value      The sanitized array (or string from the callback).
	 */
	protected function sanitize_posted_field( $value, $sanitizer = null ) {
		// Sanitize value.
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $val ) {
				$value[ $key ] = $this->sanitize_posted_field( $val, $sanitizer );
			}

			return $value;
		}

		$value = trim( $value );

		if ( 'url' === $sanitizer ) {
			return esc_url_raw( $this->normalize_url( $value ) );
		} elseif ( 'email' === $sanitizer ) {
			$sanitized_value = sanitize_email( $value );
			if ( $sanitized_value !== $value ) {
				return '';
			}

			return $sanitized_value;
		} elseif ( 'url_or_email' === $sanitizer ) {
			if ( is_email( $value ) ) {
				return sanitize_email( $value );
			}

			// Sanitize as URL.
			return esc_url_raw( $this->normalize_url( $value ) );
		} elseif ( is_callable( $sanitizer ) ) {
			return call_user_func( $sanitizer, $value );
		}

		// Use standard text sanitizer.
		return sanitize_text_field( wp_unslash( $value ) );
	}

	/**
	 * Add missing schemes to assumed URLs and replace spaces with plus signs.
	 *
	 * @param string $value Assumed URL to normalize.
	 *
	 * @return string
	 */
	protected function normalize_url( $value ) {
		if ( ! empty( $value ) && ! preg_match( '/^https?:\/\//', $value ) ) {
			/**
			 * URL scheme to prepend when none is provided.
			 *
			 * @since 1.35.0
			 *
			 * @value string|bool $scheme Scheme to prepend (http or https). False to skip prepending scheme.
			 * @value string      $url    Assumed URL that was passed.
			 */
			$assumed_scheme = apply_filters( 'job_manager_form_assumed_url_scheme', 'http', $value );
			if ( $assumed_scheme ) {
				$value = $assumed_scheme . '://' . $value;
			}
		}

		$value = str_replace( ' ', '+', $value );

		return $value;
	}

	/**
	 * Gets the value of a posted field.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return string|array
	 */
	protected function get_posted_field( $key, $field ) {
		// Allow custom sanitizers with standard text fields.
		if ( ! isset( $field['sanitizer'] ) ) {
			$field['sanitizer'] = null;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification -- WP_Job_Manager_Form::sanitize_posted_field handles the sanitization based on the type of data passed; nonce check happens elsewhere.
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : false;

		if ( isset( $field['before_sanitize'] ) && is_callable( $field['before_sanitize'] ) ) {
			call_user_func( $field['before_sanitize'], $value );
		}

		return false !== $value ? $this->sanitize_posted_field( $value, $field['sanitizer'] ) : '';
	}

	/**
	 * Gets the value of a posted multiselect field.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return array
	 */
	protected function get_posted_multiselect_field( $key, $field ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification -- Nonce check happens elsewhere.
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : false;

		if ( isset( $field['before_sanitize'] ) && is_callable( $field['before_sanitize'] ) ) {
			call_user_func( $field['before_sanitize'], $value );
		}

		return is_array( $value ) ? array_map( 'sanitize_text_field', $value ) : [];
	}

	/**
	 * Gets the value of a posted file field.
	 *
	 * @param  string $key
	 * @param  array  $field
	 *
	 * @return string|array
	 * @throws Exception When the upload fails.
	 */
	protected function get_posted_file_field( $key, $field ) {
		$file = $this->upload_file( $key, $field );

		if ( ! $file ) {
			$file = $this->get_posted_field( 'current_' . $key, $field );
		} elseif ( is_array( $file ) ) {
			$file = array_filter( array_merge( $file, (array) $this->get_posted_field( 'current_' . $key, $field ) ) );
		}

		return $file;
	}

	/**
	 * Gets the value of a posted textarea field.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return string
	 */
	protected function get_posted_textarea_field( $key, $field ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification -- Nonce check happens elsewhere.
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : false;

		if ( isset( $field['before_sanitize'] ) && is_callable( $field['before_sanitize'] ) ) {
			call_user_func( $field['before_sanitize'], $value );
		}

		return false !== $value ? trim( wp_kses_post( $value ) ) : '';
	}

	/**
	 * Gets the value of a posted textarea field.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return string
	 */
	protected function get_posted_wp_editor_field( $key, $field ) {
		return $this->get_posted_textarea_field( $key, $field );
	}

	/**
	 * Gets posted terms for the taxonomy.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return array
	 */
	protected function get_posted_term_checklist_field( $key, $field ) {
		$value = false;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check happens earlier.
		if ( isset( $_POST['tax_input'] ) && isset( $_POST['tax_input'][ $field['taxonomy'] ] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce check happens earlier. Sanitized below.
			$value = wp_unslash( $_POST['tax_input'][ $field['taxonomy'] ] );
		}

		if ( isset( $field['before_sanitize'] ) && is_callable( $field['before_sanitize'] ) ) {
			call_user_func( $field['before_sanitize'], $value );
		}

		return is_array( $value ) ? array_map( 'absint', $value ) : [];
	}

	/**
	 * Gets posted terms for the taxonomy.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return array
	 */
	protected function get_posted_term_multiselect_field( $key, $field ) {
		// phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce check happens elsewhere. Sanitization below.
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : false;

		if ( isset( $field['before_sanitize'] ) && is_callable( $field['before_sanitize'] ) ) {
			call_user_func( $field['before_sanitize'], $value );
		}

		return is_array( $value ) ? array_map( 'absint', $value ) : [];
	}

	/**
	 * Gets posted terms for the taxonomy.
	 *
	 * @param  string $key
	 * @param  array  $field
	 * @return int
	 */
	protected function get_posted_term_select_field( $key, $field ) {
		// phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce check happens elsewhere. Sanitization below.
		$value = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : false;

		if ( isset( $field['before_sanitize'] ) && is_callable( $field['before_sanitize'] ) ) {
			call_user_func( $field['before_sanitize'], $value );
		}

		return $value && $value > 0 ? absint( $value ) : '';
	}

	/**
	 * Handles the uploading of files.
	 *
	 * @param string $field_key
	 * @param array  $field
	 * @throws Exception When file upload failed.
	 * @return  string|array
	 */
	protected function upload_file( $field_key, $field ) {
		$value = null;

		if ( isset( $_FILES[ $field_key ] ) && ! empty( $_FILES[ $field_key ] ) && ! empty( $_FILES[ $field_key ]['name'] ) ) {
			if ( ! empty( $field['allowed_mime_types'] ) ) {
				$allowed_mime_types = $field['allowed_mime_types'];
			} else {
				$allowed_mime_types = job_manager_get_allowed_mime_types();
			}

			$file_urls       = [];
			$files_to_upload = job_manager_prepare_uploaded_files( $_FILES[ $field_key ] ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- see https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards/issues/1720.

			foreach ( $files_to_upload as $file_to_upload ) {
				$uploaded_file = job_manager_upload_file(
					$file_to_upload,
					[
						'file_key'           => $field_key,
						'allowed_mime_types' => $allowed_mime_types,
					]
				);

				if ( is_wp_error( $uploaded_file ) ) {
					throw new Exception( $uploaded_file->get_error_message() );
				} else {
					$file_urls[] = $uploaded_file->url;
				}
			}

			if ( ! empty( $field['multiple'] ) ) {
				$value = $file_urls;
			} else {
				$value = current( $file_urls );
			}
		}

		if ( isset( $field['before_sanitize'] ) && is_callable( $field['before_sanitize'] ) ) {
			call_user_func( $field['before_sanitize'], $value );
		}

		return $value;
	}
}
