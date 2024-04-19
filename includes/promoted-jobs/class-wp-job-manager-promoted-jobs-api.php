<?php
/**
 * File containing the class WP_Job_Manager_Promoted_Jobs_API.
 *
 * @package wp-job-manager
 */

/**
 * Handles functionality related to the Promoted Jobs REST API.
 *
 * @since 1.42.0
 */
class WP_Job_Manager_Promoted_Jobs_API {

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
	private const REST_BASE = '/promoted-jobs';

	/**
	 * The status handler.
	 *
	 * @var WP_Job_Manager_Promoted_Jobs_Status_Handler
	 */
	private WP_Job_Manager_Promoted_Jobs_Status_Handler $status_handler;

	/**
	 * Constructor.
	 *
	 * @param WP_Job_Manager_Promoted_Jobs_Status_Handler $status_handler The status handler.
	 */
	public function __construct( WP_Job_Manager_Promoted_Jobs_Status_Handler $status_handler ) {
		$this->status_handler = $status_handler;

		add_filter( 'rest_post_dispatch', [ $this, 'add_nocache_headers' ], 10, 3 );
	}

	/**
	 * Initializes the REST API.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}


	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			self::REST_BASE,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => '__return_true',
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			self::REST_BASE . '/(?P<job_id>[\d]+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_job_data' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'job_id' => [
							'required' => true,
							'type'     => 'integer',
						],
					],
				],
			]
		);
		register_rest_route(
			self::NAMESPACE,
			self::REST_BASE . '/verify-token',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'verify_token' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'user_id' => [
							'required' => true,
							'type'     => 'integer',
						],
						'token'   => [
							'required' => true,
							'type'     => 'string',
						],
						'job_id'  => [
							'required' => true,
							'type'     => 'integer',
						],
					],
				],
			]
		);
		register_rest_route(
			self::NAMESPACE,
			self::REST_BASE . '/refresh-status',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'refresh_status' ],
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	/**
	 * Adds no-cache headers to the REST response if they're in the Promoted Jobs API namespace.
	 *
	 * @param WP_REST_Response $response The response data.
	 * @param WP_REST_Server   $server The REST server instance.
	 * @param WP_REST_Request  $request The request used to generate the response.
	 *
	 * @return WP_REST_Response The response data.
	 */
	public function add_nocache_headers( $response, $server, $request ) {
		// Check if the request belongs to the specified namespace and the response is successful.
		if ( str_starts_with( $request->get_route(), '/' . self::NAMESPACE . self::REST_BASE ) && $response->get_status() >= 200 && $response->get_status() < 300 ) {
			// Get the no-cache headers array.
			$nocache_headers = wp_get_nocache_headers();

			// Add nocache headers to the response.
			foreach ( $nocache_headers as $header => $header_value ) {
				if ( empty( $header_value ) ) {
					$server->remove_header( $header );
				} else {
					$server->send_header( $header, $header_value );
				}
			}
		}

		return $response;
	}

	/**
	 * Get all promoted jobs.
	 *
	 * @return WP_Error|WP_REST_Response The response, or WP_Error on failure.
	 */
	public function get_items() {
		global $wpdb;

		$args = [
			'post_type'           => \WP_Job_Manager_Post_Types::PT_LISTING,
			'post_status'         => 'publish',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'posts_per_page'      => -1,
			'meta_query'          => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Returns promoted jobs only which should be a small number.
				[
					'key'     => WP_Job_Manager_Promoted_Jobs::PROMOTED_META_KEY,
					'compare' => 'EXISTS',
				],
			],
		];

		$items = get_posts( $args );

		if ( ! empty( $wpdb->last_errors ) ) {
			return new WP_Error(
				'wpjm_error_getting_jobs',
				$wpdb->last_errors,
				[ 'status' => 500 ]
			);
		}

		$data = array_map( [ $this, 'prepare_item_for_response' ], $items );

		foreach ( $data as $job ) {
			if ( is_wp_error( $job ) ) {
				return $job;
			}
		}

		return new WP_REST_Response( [ 'jobs' => $data ], 200 );
	}

	/**
	 * Prepare the item for the REST response
	 *
	 * @param WP_Post $item WordPress representation of the item.
	 *
	 * @return array|\WP_Error The response, or WP_Error on failure.
	 */
	private function prepare_item_for_response( WP_Post $item ) {
		$terms = wpjm_get_the_job_types( $item );
		if ( false === $terms ) {
			$terms = [];
		}
		$terms_array = wp_list_pluck( $terms, 'slug' );

		return [
			'id'           => (string) $item->ID,
			'status'       => $item->post_status,
			'promoted'     => WP_Job_Manager_Promoted_Jobs::is_promoted( $item->ID ),
			'title'        => $item->post_title,
			'description'  => $item->post_content,
			'permalink'    => get_permalink( $item ),
			'location'     => get_post_meta( $item->ID, '_job_location', true ),
			'company_name' => get_post_meta( $item->ID, '_company_name', true ),
			'is_remote'    => (bool) get_post_meta( $item->ID, '_remote_position', true ),
			'job_type'     => $terms_array,
			'salary'       => [
				'amount'   => get_post_meta( $item->ID, '_job_salary', true ),
				'currency' => get_the_job_salary_currency( $item ),
				'unit'     => get_the_job_salary_unit_display_text( $item ),
			],
		];
	}

	/**
	 * Update the promoted job status.
	 *
	 * @deprecated 2.1.0
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_Error|WP_REST_Response The response, or WP_Error on failure.
	 */
	public function update_job_status( $request ) {
		_deprecated_function( __METHOD__, '2.1.0', 'WP_Job_Manager_Promoted_Jobs_API::refresh_status' );

		$post_id = $request->get_param( 'id' );
		$status  = $request->get_param( 'status' );
		$post    = get_post( $post_id );

		if ( empty( $post ) ) {
			return new WP_Error( 'not_found', __( 'The promoted job was not found', 'wp-job-manager' ), [ 'status' => 404 ] );
		}

		$result = WP_Job_Manager_Promoted_Jobs::update_promotion( $post_id, $status );

		return new WP_REST_Response(
			[
				'data'    => $result,
				'message' => 'Promoted job status updated',
			],
			200
		);
	}

	/**
	 * Get the job data or error if the job is not found.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response|WP_Error The response, or WP_Error on failure.
	 */
	public function get_job_data( $request ) {
		$job_id = $request->get_param( 'job_id' );
		$post   = get_post( $job_id );

		if ( \WP_Job_Manager_Post_Types::PT_LISTING !== get_post_type( $post ) ) {
			return new WP_Error( 'not_found', __( 'The promoted job was not found', 'wp-job-manager' ), [ 'status' => 404 ] );
		}

		$controller = get_post_type_object( \WP_Job_Manager_Post_Types::PT_LISTING )->get_rest_controller();
		if ( ! ( $controller instanceof WP_REST_Posts_Controller ) || ! $controller->check_read_permission( $post ) || 'publish' !== $post->post_status ) {
			return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to view this job.', 'wp-job-manager' ), [ 'status' => rest_authorization_required_code() ] );
		}

		$job_data = $this->prepare_item_for_response( get_post( $job_id ) );
		if ( is_wp_error( $job_data ) ) {
			return $job_data;
		}

		return rest_ensure_response(
			[
				'job_data' => $job_data,
			]
		);
	}

	/**
	 * Verify if the token is valid or not. Checks that the job exists and the user has access to it.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response The result of the validation.
	 */
	public function verify_token( $request ) {
		$token   = $request->get_param( 'token' );
		$user_id = $request->get_param( 'user_id' );
		$job_id  = $request->get_param( 'job_id' );

		$verified = false;
		// We only verify the token if the job_id exists and user has access to it.
		if ( \WP_Job_Manager_Post_Types::PT_LISTING === get_post_type( $job_id ) ) {
			if ( user_can( $user_id, \WP_Job_Manager_Post_Types::CAP_MANAGE_LISTINGS, $job_id ) ) {
				$verified = WP_Job_Manager_Site_Trust_Token::instance()->validate( 'user', $user_id, $token );
			}
		}

		return rest_ensure_response(
			[
				'verified' => $verified,
			]
		);
	}

	/**
	 * Refreshes the status of the promoted jobs.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_REST_Response The response.
	 */
	public function refresh_status( $request ) {
		$this->status_handler->fetch_updates();
		return new WP_REST_Response( [ 'success' => true ] );
	}
}
