<?php
/**
 * File containing the class Job_Dashboard_Shortcode.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

use WP_Job_Manager\UI\Notice;
use WP_Job_Manager\UI\Redirect_Message;
use WP_Job_Manager\UI\UI_Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-job-overlay.php';

/**
 * Job Dashboard Shortcode.
 *
 * @since 2.3.0
 */
class Job_Dashboard_Shortcode {

	use Singleton;

	/**
	 * Dashboard message.
	 *
	 * @access private
	 * @var string
	 */
	private $job_dashboard_message = '';

	/**
	 * Cache of job post IDs currently displayed on job dashboard.
	 *
	 * @var int[]
	 */
	private $job_dashboard_job_ids;

	/**
	 * Constructor.
	 */
	public function __construct() {

		add_shortcode( 'job_dashboard', [ $this, 'output_job_dashboard' ] );

		add_action( 'wp', [ $this, 'handle_actions' ] );

		add_action( 'job_manager_job_dashboard_content_edit', [ $this, 'edit_job' ] );

		add_filter( 'paginate_links', [ $this, 'filter_paginate_links' ], 10, 1 );

		add_action( 'job_manager_job_dashboard_column_company', [ self::class, 'the_company' ] );
		add_action( 'job_manager_job_dashboard_column_date', [ self::class, 'the_date' ] );
		add_action( 'job_manager_job_dashboard_column_date', [ self::class, 'the_expiration_date' ] );

		add_action( 'job_manager_job_dashboard_columns', [ $this, 'maybe_display_company_column' ], 8 );
		add_action( 'job_manager_job_dashboard_column_job_title', [ self::class, 'the_job_title' ], 10 );
		add_action( 'job_manager_job_dashboard_column_job_title', [ self::class, 'the_status' ], 12 );
		add_action( 'job_manager_job_dashboard_column_actions', [ self::class, 'the_primary_action' ], 10, 2 );

		Job_Overlay::instance();
	}
	/**
	 * Add 'company' column if user has multiple companies.
	 *
	 * @param array $columns
	 */
	public function maybe_display_company_column( $columns ) {
		if ( $this->user_has_multiple_companies() ) {
			$columns = array_merge( [ 'company' => __( 'Company', 'wp-job-manager' ) ], $columns );
		}

		return $columns;
	}

	/**
	 * Handles shortcode which lists the logged in user's jobs.
	 *
	 * @param array $attrs
	 *
	 * @return string
	 */
	public function output_job_dashboard( $attrs ) {
		if ( ! is_user_logged_in() ) {
			ob_start();
			get_job_manager_template( 'job-dashboard-login.php' );

			return ob_get_clean();
		}

		$attrs          = shortcode_atts(
			[
				'posts_per_page' => '25',
			],
			$attrs
		);
		$posts_per_page = $attrs['posts_per_page'];

		Job_Overlay::instance()->init_dashboard_overlay();

		ob_start();

		// If doing an action, show conditional content if needed....
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
		$action = isset( $_REQUEST['action'] ) ? sanitize_title( wp_unslash( $_REQUEST['action'] ) ) : false;
		if ( ! empty( $action ) ) {
			// Show alternative content if a plugin wants to.
			if ( has_action( 'job_manager_job_dashboard_content_' . $action ) ) {
				do_action( 'job_manager_job_dashboard_content_' . $action, $attrs );

				return ob_get_clean();
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';

		// ....If not show the job dashboard.
		$jobs = new \WP_Query(
			$this->get_job_dashboard_query_args(
				[
					'posts_per_page' => $posts_per_page,
					's'              => $search,
				]
			),
		);

		// Cache IDs for access check later on.
		$this->job_dashboard_job_ids = wp_list_pluck( $jobs->posts, 'ID' );

		$message = Redirect_Message::get_message( 'updated' );

		if ( ! empty( $message ) ) {
			//phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the notice class.
			echo '<div class="alignwide">' . $message . '</div>';
		}

		$job_dashboard_columns = apply_filters(
			'job_manager_job_dashboard_columns',
			[
				'job_title' => __( 'Title', 'wp-job-manager' ),
				'date'      => __( 'Date', 'wp-job-manager' ),
			]
		);

		$job_actions = [];
		foreach ( $jobs->posts as $job ) {
			$job_actions[ $job->ID ] = $this->get_job_actions( $job );
		}

		/**
		 * Output content before the job dashboard.
		 *
		 * @param \WP_Query $jobs The jobs displayed.
		 */
		do_action( 'job_manager_job_dashboard_before', $jobs );

		get_job_manager_template(
			'job-dashboard.php',
			[
				'jobs'                  => $jobs->posts,
				'job_actions'           => $job_actions,
				'max_num_pages'         => $jobs->max_num_pages,
				'job_dashboard_columns' => $job_dashboard_columns,
				'search_input'          => $search,
			]
		);

		Job_Overlay::instance()->output_modal_element();

		/**
		 * Output content after the job dashboard.
		 *
		 * @param \WP_Query $jobs The jobs displayed.
		 */
		do_action( 'job_manager_job_dashboard_after', $jobs );

		return ob_get_clean();
	}

	/**
	 * Get the actions available to the user for a job listing on the job dashboard page.
	 *
	 * @param \WP_Post $job The job post object.
	 *
	 * @return array
	 */
	public function get_job_actions( $job ) {
		if ( ! $this->can_manage_job( $job ) ) {
			return [];
		}

		$base_url = self::get_job_dashboard_page_url();

		$base_nonce_action_name = 'job_manager_my_job_actions';

		$actions = [];

		switch ( $job->post_status ) {
			case 'publish':
				if ( \WP_Job_Manager_Post_Types::job_is_editable( $job->ID ) ) {
					$actions['edit'] = [
						'label' => __( 'Edit', 'wp-job-manager' ),
						'nonce' => false,
					];
				}
				if ( is_position_filled( $job ) ) {
					$actions['mark_not_filled'] = [
						'label' => __( 'Mark not filled', 'wp-job-manager' ),
						'nonce' => $base_nonce_action_name,
					];
				} else {
					$actions['mark_filled'] = [
						'label' => __( 'Mark filled', 'wp-job-manager' ),
						'nonce' => $base_nonce_action_name,
					];
				}
				if (
					get_option( 'job_manager_renewal_days' ) > 0
					&& \WP_Job_Manager_Helper_Renewals::job_can_be_renewed( $job )
					&& \WP_Job_Manager_Helper_Renewals::is_wcpl_renew_compatible()
					&& \WP_Job_Manager_Helper_Renewals::is_spl_renew_compatible()
				) {
					$actions['renew'] = [
						'label' => __( 'Renew', 'wp-job-manager' ),
						'nonce' => $base_nonce_action_name,
					];
				}
				break;
			case 'expired':
				if ( job_manager_get_permalink( 'submit_job_form' ) ) {
					$actions['relist'] = [
						'label' => __( 'Relist', 'wp-job-manager' ),
						'nonce' => $base_nonce_action_name,
					];
				}
				break;
			case 'pending_payment':
			case 'pending':
				if ( \WP_Job_Manager_Post_Types::job_is_editable( $job->ID ) ) {
					$actions['edit'] = [
						'label' => __( 'Edit', 'wp-job-manager' ),
						'nonce' => false,
					];
				}
				break;
			case 'draft':
			case 'preview':
				$actions['continue'] = [
					'label' => __( 'Continue Submission', 'wp-job-manager' ),
					'nonce' => $base_nonce_action_name,
				];
				break;
		}

		$actions['duplicate'] = [
			'label' => __( 'Duplicate', 'wp-job-manager' ),
			'nonce' => $base_nonce_action_name,
		];

		$actions['delete'] = [
			'label' => __( 'Delete', 'wp-job-manager' ),
			'nonce' => $base_nonce_action_name,
		];

		/**
		 * Filter the actions available to the current user for a job on the job dashboard page.
		 *
		 * @since 1.0.0
		 *
		 * @param array    $actions Actions to filter.
		 * @param \WP_Post $job Job post object.
		 */
		$actions = apply_filters( 'job_manager_my_job_actions', $actions, $job );

		// For backwards compatibility, convert `nonce => true` to the nonce action name.
		foreach ( $actions as $key => &$action ) {
			if ( isset( $action['nonce'] ) && true === $action['nonce'] ) {
				$action['nonce'] = $base_nonce_action_name;
			}

			$action_url = add_query_arg(
				[
					'action' => $key,
					'job_id' => $job->ID,
				],
				'?'
			);

			if ( $action['nonce'] ) {
				$action_url = wp_nonce_url( $action_url, $action['nonce'] );
			}

			$action['name'] = $key;
			$action['url']  = $action_url;
		}

		return $actions;
	}

	/**
	 * Determine the highlighted primary action for a job.
	 *
	 * @param \WP_Post $job The job.
	 * @param array    $actions Available job action.
	 *
	 * @return array|false
	 */
	public static function get_primary_action( $job, $actions ) {

		$action_order = [
			'mark_filled',
			'renew',
			'relist',
			'continue',
			'edit',
			'delete',
			'duplicate',
			'mark_not_filled',
		];

		$primary_action = false;

		foreach ( $action_order as $action ) {
			if ( isset( $actions[ $action ] ) ) {
				$primary_action = $actions[ $action ];
				break;
			}
		}

		/**
		 * Filter the highlighted primary action for a job on the job dashboard page.
		 */
		return apply_filters( 'job_manager_my_job_primary_action', $primary_action, $job, $actions );

	}

	/**
	 * Filters the url from paginate_links to avoid multiple calls for same action in job dashboard
	 *
	 * @param string $link
	 *
	 * @return string
	 */
	public function filter_paginate_links( $link ) {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used for comparison only.
		if ( $this->is_job_dashboard_page() && isset( $_GET['action'] ) && in_array(
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used for comparison only.
			$_GET['action'],
			[
				'mark_filled',
				'mark_not_filled',
			],
			true
		) ) {
			return remove_query_arg( [ 'action', 'job_id', '_wpnonce' ], $link );
		}

		return $link;
	}

	/**
	 * Displays edit job form.
	 */
	public function edit_job() {
		global $job_manager;

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output should be appropriately escaped in the form generator.
		echo $job_manager->forms->get_form( 'edit-job' );
	}

	/**
	 * Helper function used to check if page is WPJM dashboard page.
	 *
	 * Checks if page has 'job_dashboard' shortcode.
	 *
	 * @access private
	 * @return bool True if page is dashboard page, false otherwise.
	 */
	private function is_job_dashboard_page() {
		global $post;

		if ( is_page() && has_shortcode( $post->post_content, 'job_dashboard' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Handles actions on job dashboard.
	 *
	 * @throws \Exception On action handling error.
	 */
	public function handle_actions() {

		/**
		 * Determine if the shortcode action handler should run.
		 *
		 * @since 1.35.0
		 *
		 * @param bool $should_run_handler Should the handler run.
		 */
		$should_run_handler = apply_filters( 'job_manager_should_run_shortcode_action_handler', $this->is_job_dashboard_page() );

		if ( ! $should_run_handler
			|| empty( $_REQUEST['action'] )
			|| empty( $_REQUEST['job_id'] )
			|| empty( $_REQUEST['_wpnonce'] )
		) {
			return;
		}

		$job_id = absint( $_REQUEST['job_id'] );
		$action = sanitize_title( wp_unslash( $_REQUEST['action'] ) );

		$job         = get_post( $job_id );
		$job_actions = $this->get_job_actions( $job );

		if ( ! isset( $job_actions[ $action ] )
			|| empty( $job_actions[ $action ]['nonce'] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce should not be modified.
			|| ! wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ), $job_actions[ $action ]['nonce'] )
		) {
			return;
		}

		try {
			if ( empty( $job ) || \WP_Job_Manager_Post_Types::PT_LISTING !== $job->post_type || ! job_manager_user_can_edit_job( $job_id ) ) {
				throw new \Exception( __( 'Invalid ID', 'wp-job-manager' ) );
			}

			switch ( $action ) {
				case 'mark_filled':
					// Check status.
					if ( 1 === intval( $job->_filled ) ) {
						throw new \Exception( __( 'This position has already been filled', 'wp-job-manager' ) );
					}

					// Update.
					update_post_meta( $job_id, '_filled', 1 );

					// Message.
					// translators: Placeholder %s is the job listing title.
					$this->job_dashboard_message = Notice::success( sprintf( __( '%s has been filled', 'wp-job-manager' ), wpjm_get_the_job_title( $job ) ) );
					break;
				case 'mark_not_filled':
					// Check status.
					if ( 1 !== intval( $job->_filled ) ) {
						throw new \Exception( __( 'This position is not filled', 'wp-job-manager' ) );
					}

					// Update.
					update_post_meta( $job_id, '_filled', 0 );

					// Message.
					// translators: Placeholder %s is the job listing title.
					$this->job_dashboard_message = Notice::success( sprintf( __( '%s has been marked as not filled', 'wp-job-manager' ), wpjm_get_the_job_title( $job ) ) );
					break;
				case 'delete':
					// Trash it.
					wp_trash_post( $job_id );

					// Message.
					// translators: Placeholder %s is the job listing title.
					$this->job_dashboard_message = Notice::success( sprintf( __( '%s has been deleted', 'wp-job-manager' ), wpjm_get_the_job_title( $job ) ) );

					break;
				case 'duplicate':
					if ( ! job_manager_get_permalink( 'submit_job_form' ) ) {
						throw new \Exception( __( 'Missing submission page.', 'wp-job-manager' ) );
					}

					$new_job_id = job_manager_duplicate_listing( $job_id );

					if ( $new_job_id ) {
						wp_safe_redirect( add_query_arg( [ 'job_id' => absint( $new_job_id ) ], job_manager_get_permalink( 'submit_job_form' ) ) );
						exit;
					}

					break;
				case 'relist':
				case 'renew':
				case 'continue':
					if ( ! job_manager_get_permalink( 'submit_job_form' ) ) {
						throw new \Exception( __( 'Missing submission page.', 'wp-job-manager' ) );
					}

					$query_args = [
						'job_id' => absint( $job_id ),
						'action' => $action,
					];

					if ( 'renew' === $action ) {
						$query_args['nonce'] = wp_create_nonce( 'job_manager_renew_job_' . $job_id );
					}
					wp_safe_redirect( add_query_arg( $query_args, job_manager_get_permalink( 'submit_job_form' ) ) );
					exit;
				default:
					do_action( 'job_manager_job_dashboard_do_action_' . $action, $job_id );
					break;
			}

			do_action( 'job_manager_my_job_do_action', $action, $job_id );

			/**
			 * Set a success message for a custom dashboard action handler.
			 *
			 * When left empty, no success message will be shown.
			 *
			 * @since 1.31.1
			 *
			 * @param string $message Text for the success message. Default: empty string.
			 * @param string $action The name of the custom action.
			 * @param int    $job_id The ID for the job that's been altered.
			 */
			$success_message = apply_filters( 'job_manager_job_dashboard_success_message', '', $action, $job_id );
			if ( $success_message ) {
				$this->job_dashboard_message = Notice::success( $success_message );
			}
		} catch ( \Exception $e ) {
			$this->job_dashboard_message = Notice::error( $e->getMessage() );
		}

		Redirect_Message::redirect( remove_query_arg( [ 'action', 'job_id', '_wpnonce' ] ), $this->job_dashboard_message, 'updated' );

	}

	/**
	 * Add expiration details to the job dashboard date column.
	 *
	 * @param \WP_Post $job
	 *
	 * @output string
	 */
	public static function the_expiration_date( $job ) {
		$expiration = \WP_Job_Manager_Post_Types::instance()->get_job_expiration( $job );

		if ( 'publish' === $job->post_status && ! empty( $expiration ) ) {

			// translators: Placeholder is the expiration date of the job listing.
			echo '<div class="job-expires"><small>' . UI_Elements::rel_time( $expiration, __( 'Expires in %s', 'wp-job-manager' ) ) . '</small></div>';
		}
	}

	/**
	 * Show company details.
	 *
	 * @param \WP_Post $job
	 *
	 * @output string
	 */
	public static function the_company( $job ) {
		the_company_logo( 'thumbnail', '', $job );
	}

	/**
	 * Show location.
	 *
	 * @param \WP_Post $job
	 *
	 * @output string
	 */
	public static function the_location( $job ) {
		$location = get_the_job_location( $job );

		if ( ! $location ) {
			return;
		}

		?>
		<div class="jm-ui-row">
			<?php echo UI_Elements::icon( 'location' ); ?>
			<?php echo esc_html( $location ); ?>
		</div>
		<?php
	}

	/**
	 * Show job title.
	 *
	 * @param \WP_Post $job
	 *
	 * @output string
	 */
	public static function the_job_title( $job ) {
		echo '<a class="job-title" data-job-id="' . esc_attr( (string) $job->ID ) . '" href="' . esc_url( get_permalink( $job->ID ) ) . '">' . esc_html( get_the_title( $job ) ?? $job->ID ) . '</a>';
	}

	/**
	 * Show job title.
	 *
	 * @param \WP_Post $job
	 *
	 * @output string
	 */
	public static function the_date( $job ) {
		echo '<div>' . esc_html( wp_date( apply_filters( 'job_manager_get_dashboard_date_format', 'M d, Y' ), get_post_datetime( $job )->getTimestamp() ) ) . '</div>';
	}

	/**
	 * Show the primary action as a button.
	 *
	 * @param \WP_Post $job The job post.
	 * @param array    $actions Available actions.
	 *
	 * @output string
	 */
	public static function the_primary_action( $job, $actions ) {
		$action = self::get_primary_action( $job, $actions );

		if ( ! $action ) {
			return;
		}

		echo UI_Elements::button(
			[
				'label' => $action['label'],
				'url'   => $action['url'],
				'class' => 'job-dashboard-action-' . esc_attr( $action['name'] ) . ' jm-dashboard-action jm-dashboard-action--primary jm-ui-button--small',
			],
			'jm-ui-button--outline'
		);
	}

	/**
	 * Add job status to the job dashboard title column.
	 *
	 * @param \WP_Post $job
	 *
	 * @output string
	 */
	public static function the_status( $job ) {

		echo '<div class="job-status jm-ui-row">';

		$status = [];

		if ( is_position_filled( $job ) ) {
			$status[] = '<span class="job-status-filled jm-ui-row">'
						. UI_Elements::icon( 'check' )
						. esc_html__( 'Filled', 'wp-job-manager' ) . '</span>';
		}

		if ( is_position_featured( $job ) && 'publish' === $job->post_status ) {
			$status[] = '<span class="job-status-featured jm-ui-row">'
						. UI_Elements::icon( 'star' )
						. esc_html__( 'Featured', 'wp-job-manager' ) . '</span>';
		}

		$status_icon = [
			'pending'         => 'alert',
			'pending_payment' => 'alert',
			'draft'           => 'edit',
			'expired'         => 'alert',
		][ $job->post_status ] ?? null;

		$status[] = '<span class="job-status-' . esc_attr( $job->post_status ) . ' jm-ui-row">'
					. ( $status_icon ? UI_Elements::icon( $status_icon ) : '' )
					. esc_html( get_the_job_status( $job ) ) . '</span>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		echo implode( '<span class="jm-separator">|</span>', $status );

		echo '</div>';
	}

	/**
	 * Get the URL of the [job_dashboard] page.
	 *
	 * @return string|false
	 */
	public static function get_job_dashboard_page_url() {
		$page_id = get_option( 'job_manager_job_dashboard_page_id' );
		if ( $page_id ) {
			return (string) get_permalink( $page_id );
		} else {
			return home_url( '/' );
		}
	}

	/**
	 * Check if the current user can manage this job listing.
	 *
	 * @param \WP_Post|null $job
	 *
	 * @return bool
	 */
	public function can_manage_job( $job ) {

		if ( ! get_current_user_id()
			|| empty( $job )
			|| ! $job instanceof \WP_Post
			|| \WP_Job_Manager_Post_Types::PT_LISTING !== $job->post_type ) {
			return false;
		}

		return is_admin()
			? current_user_can( \WP_Job_Manager_Post_Types::CAP_MANAGE_LISTINGS, $job->ID )
			: $this->is_job_available_on_dashboard( $job );
	}

	/**
	 * Check if a job is listed on the current user's job dashboard page.
	 *
	 * @param \WP_Post $job Job post object.
	 *
	 * @return bool
	 */
	public function is_job_available_on_dashboard( \WP_Post $job ) {
		// Check cache of currently displayed job dashboard IDs first to avoid lots of queries.
		if ( ! empty( $this->job_dashboard_job_ids ) && in_array( (int) $job->ID, $this->job_dashboard_job_ids, true ) ) {
			return true;
		}

		$args           = $this->get_job_dashboard_query_args();
		$args['p']      = $job->ID;
		$args['fields'] = 'ids';

		$query = new \WP_Query( $args );

		return (int) $query->post_count > 0;
	}

	/**
	 * Helper that generates the job dashboard query args.
	 *
	 * @param array $args Additional query args.
	 *
	 * @return array
	 */
	private function get_job_dashboard_query_args( $args = [] ) {
		$args = wp_parse_args(
			$args,
			[
				'post_type'           => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_status'         => [ 'publish', 'expired', 'pending', 'draft', 'preview' ],
				'ignore_sticky_posts' => 1,
				'orderby'             => 'date',
				'order'               => 'desc',
				'author'              => get_current_user_id(),
				'posts_per_page'      => -1,
			]
		);

		if ( get_option( 'job_manager_enable_scheduled_listings' ) ) {
			$args['post_status'][] = 'future';
		}

		if ( ! empty( $args['posts_per_page'] ) && $args['posts_per_page'] > 0 ) {
			$args['offset'] = ( max( 1, get_query_var( 'paged' ) ) - 1 ) * $args['posts_per_page'];
		}

		/**
		 * Customize the query that is used to get jobs on the job dashboard.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args Arguments to pass to \WP_Query.
		 */
		return apply_filters( 'job_manager_get_dashboard_jobs_args', $args );
	}

	/**
	 * Check if the current user has multiple companies.
	 *
	 * @return bool
	 */
	private function user_has_multiple_companies() {
		global $wpdb;

		$user_id = get_current_user_id();

		$cache_key       = 'wpjm_user_' . $user_id . '_companies_count';
		$companies_count = get_transient( $cache_key );

		if ( false === $companies_count ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Query is cached.
			$companies_count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT meta_value) FROM {$wpdb->postmeta} WHERE meta_key = '_company_name' AND `meta_value` != '' AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_author = %d AND post_type = 'job_listing')", $user_id ) );

			set_transient( $cache_key, $companies_count, 24 * HOUR_IN_SECONDS );
		}

		return $companies_count > 1;
	}

}
