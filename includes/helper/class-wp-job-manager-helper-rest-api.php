<?php
/**
 * File containing the class WP_Job_Manager_Helper_REST_API.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Helper_REST_API
 */
class WP_Job_Manager_Helper_REST_API {

	/**
	 * The nonce helper to validate the request.
	 *
	 * @var WP_Job_Manager_Helper_Nonce
	 */
	private WP_Job_Manager_Helper_Nonce $nonce;

	/**
	 * The namespace.
	 *
	 * @var string
	 */
	private const NAMESPACE = 'wpjm-internal/v1';

	/**
	 * Rest base for the current object.
	 *
	 * @var string
	 */
	private const REST_BASE = '/licensing';

	/**
	 * Construct the REST API class.
	 *
	 * @param WP_Job_Manager_Helper_Nonce $nonce
	 */
	public function __construct( WP_Job_Manager_Helper_Nonce $nonce ) {
		$this->nonce = $nonce;
	}

	/**
	 * Initialize the hooks for the REST API.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
	}

	/**
	 * Register the REST routes related to licensing.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			self::NAMESPACE,
			self::REST_BASE . '/receive-wpcom-license-key',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'receive_wpcom_license_key' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'plugin_slug'  => [
							'type'     => 'string',
							'required' => true,
						],
						'license_key'  => [
							'type'     => 'string',
							'required' => true,
						],
						'custom_nonce' => [
							'type'     => 'string',
							'required' => true,
						],
					],
				],
			]
		);
	}


	/**
	 * Receives the license key for the given plugin slug from the WPCOM website.
	 * This endpoint is expected to be called as the `activation_url` from flush WPCOM license.
	 *
	 * @param \WP_REST_Request $request The current request.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function receive_wpcom_license_key( $request ) {
		$license_key  = sanitize_text_field( $request->get_param( 'license_key' ) );
		$plugin_slug  = sanitize_text_field( $request->get_param( 'plugin_slug' ) );
		$custom_nonce = $request->get_param( 'custom_nonce' );
		$response     = [
			'success'  => false,
			'messages' => [],
		];

		if ( $this->nonce->check_custom_nonce( $custom_nonce, 'receive-license-' . $plugin_slug ) ) {
			$instance = WP_Job_Manager_Helper::instance();
			$instance->activate_license( $plugin_slug, $license_key );
			$messages = $instance->get_messages( $plugin_slug );
			$success  = ! empty( $messages );
			if ( ! $success ) {
				$messages[] = [
					'type'    => 'error',
					'message' => __( 'An error occurred while activating the license.', 'wp-job-manager' ),
				];
			}
			foreach ( $messages as $message ) {
				if ( 'error' === $message['type'] ) {
					$success = false;
					break;
				}
			}

			$response['success']  = $success;
			$response['messages'] = $messages;
		} else {
			$response['messages'][] = [
				'type'    => 'error',
				'message' => __( 'Invalid nonce.', 'wp-job-manager' ),
			];
		}

		// Pass through the API response from the license server.
		return rest_ensure_response( $response );
	}


}
