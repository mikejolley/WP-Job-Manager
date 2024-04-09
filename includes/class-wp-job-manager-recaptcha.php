<?php
/**
 * File containing the WP_Job_Manager_Recaptcha class.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Recaptcha class.
 *
 * @since $$next-version$$
 * @internal
 */
class WP_Job_Manager_Recaptcha {

	use Singleton;

	/**
	 * Site key.
	 *
	 * @var string
	 */
	private $site_key;

	/**
	 * Secret key.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * The reCAPTCHA version.
	 *
	 * @var string
	 */
	private $recaptcha_version;

	const RECAPTCHA_SITE_KEY   = 'job_manager_recaptcha_site_key';
	const RECAPTCHA_SECRET_KEY = 'job_manager_recaptcha_secret_key';
	const RECAPTCHA_VERSION    = 'job_manager_recaptcha_version';

	/**
	 * Initialize class for landing pages.
	 *
	 * @since 2.0.0
	 */
	private function __construct() {
		$this->site_key          = get_option( self::RECAPTCHA_SITE_KEY );
		$this->secret_key        = get_option( self::RECAPTCHA_SECRET_KEY );
		$this->recaptcha_version = get_option( self::RECAPTCHA_VERSION, 'v2' );
	}

	/**
	 * Use reCAPTCHA field on the form?
	 *
	 * @return bool
	 */
	public function use_recaptcha_field() {
		if ( ! $this->is_recaptcha_available() ) {
			return false;
		}
		return 1 === absint( get_option( 'job_manager_enable_recaptcha_job_submission' ) );
	}

	/**
	 * Enqueue the scripts and add appropriate hooks for the recaptcha to load.
	 */
	public static function enqueue_recaptcha() {
		$instance = self::instance();

		if ( $instance->use_recaptcha_field() ) {
			add_action( 'submit_job_form_end', [ $instance, 'display_recaptcha_field' ] );
			add_filter( 'submit_job_form_validate_fields', [ $instance, 'validate_recaptcha_field' ] );
			add_filter( 'submit_draft_job_form_validate_fields', [ $instance, 'validate_recaptcha_field' ] );
		}

		if (
			$instance->use_recaptcha_field() &&
			in_array( $instance->recaptcha_version, [ 'v2', 'v3' ], true )
		) {
			$recaptcha_version = $instance->recaptcha_version;
			$recaptcha_url     = '';

			if ( 'v2' === $recaptcha_version ) {
				$recaptcha_url = 'https://www.google.com/recaptcha/api.js';
			} elseif ( 'v3' === $recaptcha_version ) {
				$recaptcha_url = 'https://www.google.com/recaptcha/api.js?render=' . $instance->site_key;
			}
			wp_enqueue_script( 'recaptcha', $recaptcha_url, [], JOB_MANAGER_VERSION, [ 'strategy' => 'defer' ] );
		}
	}

	/**
	 * Checks whether reCAPTCHA has been set up and is available.
	 *
	 * @return bool
	 */
	public function is_recaptcha_available() {
		$is_recaptcha_available = ! empty( $this->site_key ) && ! empty( $this->secret_key );

		/**
		 * Filter whether reCAPTCHA should be available for this form.
		 *
		 * @since 1.30.0
		 *
		 * @param bool $is_recaptcha_available
		 */
		return apply_filters( 'job_manager_is_recaptcha_available', $is_recaptcha_available );
	}

	/**
	 * Dispaly the reCAPTCHA field in the form.
	 *
	 * @return void
	 */
	public function display_recaptcha_field() {
		$field             = [];
		$field['label']    = get_option( 'job_manager_recaptcha_label' );
		$field['required'] = true;
		$field['site_key'] = $this->site_key;

		$template = 'form-fields/recaptcha-' . ( 'v3' === $this->recaptcha_version ? 'v3-' : '' ) . 'field.php';

		get_job_manager_template(
			$template,
			[
				'key'   => 'recaptcha',
				'field' => $field,
			]
		);
	}

	/**
	 * Validate a reCAPTCHA field.
	 *
	 * @param bool $success
	 *
	 * @return bool|WP_Error
	 */
	public function validate_recaptcha_field( $success ) {
		$recaptcha_field_label = get_option( 'job_manager_recaptcha_label' );

		// translators: %s is the name of the form validation that failed.
		$validation_error = new \WP_Error( 'validation-error', sprintf( esc_html__( '"%s" check failed. Please try again.', 'wp-job-manager' ), $recaptcha_field_label ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check happens earlier (when possible).
		$input_recaptcha_response = isset( $_POST['g-recaptcha-response'] ) ? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) ) : '';

		if ( empty( $input_recaptcha_response ) ) {
			return $validation_error;
		}

		if ( 'v2' === $this->recaptcha_version ) {
			$default_remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			$response            = wp_remote_get(
				add_query_arg(
					[
						'secret'   => $this->secret_key,
						'response' => $input_recaptcha_response,
						'remoteip' => isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : $default_remote_addr,
					],
					'https://www.google.com/recaptcha/api/siteverify'
				)
			);
			if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
				return $validation_error;
			} else {
				$json = json_decode( $response['body'] );
				if ( ! $json || ! $json->success ) {
					return $validation_error;
				}
			}
		} elseif ( 'v3' === $this->recaptcha_version ) {
			$recaptcha_url  = 'https://www.google.com/recaptcha/api/siteverify';
			$recaptcha_data = [
				'secret'   => $this->secret_key,
				'response' => $input_recaptcha_response,
				'remoteip' => sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ),
			];

			$response = wp_remote_post(
				$recaptcha_url,
				[
					'body'    => $recaptcha_data,
					'headers' => [
						'Content-Type' => 'application/x-www-form-urlencoded',
					],
				]
			);

			if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
				return $validation_error;
			} else {
				$response_body = wp_remote_retrieve_body( $response );
				$response_body = json_decode( $response_body );
			}

			/**
			 * Filter the score tolerance for reCAPTCHA v3.
			 *
			 * The score tolerance determines how strict the reCAPTCHA v3 validation is. A higher tolerance allows more leniency in accepting scores. A higher score means more certainty that the user is human.
			 *
			 * @since $$next-version$$
			 *
			 * @param float The score tolerance value. Default is 0.5.
			 */
			$score_tolerance = apply_filters( 'job_manager_recaptcha_v3_score_tolerance', 0.5 );

			if ( ! $response_body->success || $response_body->score < $score_tolerance ) {
				return $validation_error;
			}
		}
		return $success;
	}

	/**
	 * Get the reCAPTCHA version.
	 *
	 * @return string
	 */
	public function get_recaptcha_version() {
		return $this->recaptcha_version;
	}
}
