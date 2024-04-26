<?php
/**
 * File containing the class WP_Job_Manager_Shortcodes.
 *
 * @package wp-job-manager
 */

use WP_Job_Manager\Job_Dashboard_Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/class-job-dashboard-shortcode.php';

/**
 * Handles the shortcodes for WP Job Manager.
 *
 * @since 1.0.0
 */
class WP_Job_Manager_Shortcodes {

	use \WP_Job_Manager\Singleton;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp', [ $this, 'handle_redirects' ] );

		add_action( 'job_manager_job_filters_end', [ $this, 'job_filter_job_types' ], 20 );
		add_action( 'job_manager_job_filters_end', [ $this, 'job_filter_results' ], 30 );
		add_action( 'job_manager_output_jobs_no_results', [ $this, 'output_no_results' ] );
		add_shortcode( 'submit_job_form', [ $this, 'submit_job_form' ] );

		add_shortcode( 'jobs', [ $this, 'output_jobs' ] );
		add_shortcode( 'job', [ $this, 'output_job' ] );
		add_shortcode( 'job_summary', [ $this, 'output_job_summary' ] );
		add_shortcode( 'job_apply', [ $this, 'output_job_apply' ] );

		Job_Dashboard_Shortcode::instance();
	}

	/**
	 * Handle redirects
	 */
	public function handle_redirects() {
		$submit_job_form_page_id = get_option( 'job_manager_submit_job_form_page_id' );

		if ( ! is_user_logged_in() || ! is_page( $submit_job_form_page_id ) ||
			 // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Input is used safely.
			( ! empty( $_REQUEST['job_id'] ) && job_manager_user_can_edit_job( intval( $_REQUEST['job_id'] ) ) )
		) {
			return;
		}

		$submission_limit = get_option( 'job_manager_submission_limit' );
		$job_count        = job_manager_count_user_job_listings();

		if (
			$submit_job_form_page_id &&
			! \job_manager_user_can_submit_job_listing()
		) {
			$employer_dashboard_page_id = get_option( 'job_manager_job_dashboard_page_id' );
			if ( $employer_dashboard_page_id ) {
				$redirect_url = get_permalink( $employer_dashboard_page_id );
			} else {
				$redirect_url = home_url( '/' );
			}

			/**
			 * Filter on the URL visitors will be redirected upon exceeding submission limit.
			 *
			 * @since 1.35.2
			 *
			 * @param string $redirect_url     URL to redirect when user has exceeded submission limit.
			 * @param int    $submission_limit Maximum number of listings a user can submit.
			 * @param int    $job_count        Number of job listings the user has submitted.
			 */
			$redirect_url = apply_filters(
				'job_manager_redirect_url_exceeded_listing_limit',
				$redirect_url,
				$submission_limit,
				$job_count
			);

			if ( $redirect_url ) {
				wp_safe_redirect( esc_url( $redirect_url ) );

				exit;
			}
		}
	}

	/**
	 * Shows the job submission form.
	 *
	 * @param array $atts
	 * @return string|null
	 */
	public function submit_job_form( $atts = [] ) {
		return $GLOBALS['job_manager']->forms->get_form( 'submit-job', $atts );
	}

	/**
	 * Handles shortcode which lists the logged in user's jobs.
	 *
	 * @deprecated 2.3.0 - Moved to Job_Dashboard_Shortcode.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function job_dashboard( $atts ) {
		_deprecated_function( __METHOD__, '2.3.0', 'Job_Dashboard_Shortcode::output_job_dashboard_shortcode' );

		return Job_Dashboard_Shortcode::instance()->output_job_dashboard( $atts );
	}

	/**
	 * Get the actions available to the user for a job listing on the job dashboard page.
	 *
	 * @deprecated 2.3.0 - Moved to Job_Dashboard_Shortcode.
	 *
	 * @param WP_Post $job The job post object.
	 *
	 * @return array
	 */
	public function get_job_actions( $job ) {
		_deprecated_function( __METHOD__, '2.3.0', 'Job_Dashboard_Shortcode::get_job_actions' );

		return Job_Dashboard_Shortcode::instance()->get_job_actions( $job );
	}

	/**
	 * Filters the url from paginate_links to avoid multiple calls for same action in job dashboard
	 *
	 * @deprecated 2.3.0 - Moved to Job_Dashboard_Shortcode.
	 *
	 * @param string $link
	 * @return string
	 */
	public function filter_paginate_links( $link ) {
		_deprecated_function( __METHOD__, '2.3.0', 'Job_Dashboard_Shortcode::filter_paginate_links' );

		return Job_Dashboard_Shortcode::instance()->filter_paginate_links( $link );
	}

	/**
	 * Displays edit job form.
	 *
	 * @deprecated 2.3.0 - Moved to Job_Dashboard_Shortcode.
	 */
	public function edit_job() {
		_deprecated_function( __METHOD__, '2.3.0', 'Job_Dashboard_Shortcode::edit_job' );

		Job_Dashboard_Shortcode::instance()->edit_job();
	}

	/**
	 * Lists all job listings.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function output_jobs( $atts ) {
		ob_start();

		if ( ! job_manager_user_can_browse_job_listings() ) {
			get_job_manager_template_part( 'access-denied', 'browse-job_listings' );
			return ob_get_clean();
		}

		$atts = shortcode_atts(
			apply_filters(
				'job_manager_output_jobs_defaults',
				[
					'per_page'                  => get_option( 'job_manager_per_page' ),
					'orderby'                   => 'featured',
					'order'                     => 'DESC',

					// Filters + cats.
					'show_filters'              => true,
					'show_categories'           => true,
					'show_category_multiselect' => get_option( 'job_manager_enable_default_category_multiselect', false ),
					'show_pagination'           => 'pagination' === get_option( 'job_manager_job_listing_pagination_type' ) ? true : false,
					'show_more'                 => 'load_more' === get_option( 'job_manager_job_listing_pagination_type' ) ? true : false,

					// Limit what jobs are shown based on category, post status, and type.
					'categories'                => '',
					'job_types'                 => '',
					'post_status'               => '',
					'featured'                  => null, // True to show only featured, false to hide featured, leave null to show both.
					'filled'                    => null, // True to show only filled, false to hide filled, leave null to show both/use the settings.
					'remote_position'           => null, // True to show only remote, false to hide remote, leave null to show both.
					'featured_first'            => false, // True to show featured first, false to show in default order.

					// Default values for filters.
					'location'                  => '',
					'keywords'                  => '',
					'selected_category'         => '',
					'selected_job_types'        => implode( ',', array_values( get_job_listing_types( 'id=>slug' ) ) ),
				]
			),
			$atts
		);

		if ( ! get_option( 'job_manager_enable_categories' ) ) {
			$atts['show_categories'] = false;
		}

		// String and bool handling.
		$atts['show_filters']              = $this->string_to_bool( $atts['show_filters'] );
		$atts['show_categories']           = $this->string_to_bool( $atts['show_categories'] );
		$atts['show_category_multiselect'] = $this->string_to_bool( $atts['show_category_multiselect'] );
		$atts['show_more']                 = $this->string_to_bool( $atts['show_more'] );
		$atts['show_pagination']           = $this->string_to_bool( $atts['show_pagination'] );
		$atts['featured_first']            = $this->string_to_bool( $atts['featured_first'] );

		if ( ! is_null( $atts['featured'] ) ) {
			$atts['featured'] = ( is_bool( $atts['featured'] ) && $atts['featured'] ) || in_array( $atts['featured'], [ 1, '1', 'true', 'yes' ], true );
		}

		if ( ! is_null( $atts['filled'] ) ) {
			$atts['filled'] = ( is_bool( $atts['filled'] ) && $atts['filled'] ) || in_array( $atts['filled'], [ 1, '1', 'true', 'yes' ], true );
		}

		if ( ! is_null( $atts['remote_position'] ) ) {
			$atts['remote_position'] = ( is_bool( $atts['remote_position'] ) && $atts['remote_position'] ) || in_array( $atts['remote_position'], [ 1, '1', 'true', 'yes' ], true );
		}

		// By default, use client-side state to populate form fields.
		$disable_client_state = false;

		// Get keywords, location, category and type from querystring if set.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Input is used safely.
		if ( ! empty( $_GET['search_keywords'] ) ) {
			$atts['keywords']     = sanitize_text_field( wp_unslash( $_GET['search_keywords'] ) );
			$disable_client_state = true;
		}
		if ( ! empty( $_GET['search_location'] ) ) {
			$atts['location']     = sanitize_text_field( wp_unslash( $_GET['search_location'] ) );
			$disable_client_state = true;
		}
		if ( ! empty( $_GET['search_category'] ) ) {
			$atts['selected_category'] = sanitize_text_field( wp_unslash( $_GET['search_category'] ) );
			$disable_client_state      = true;
		}
		if ( ! empty( $_GET['search_job_type'] ) ) {
			$atts['selected_job_types'] = sanitize_text_field( wp_unslash( $_GET['search_job_type'] ) );
			$disable_client_state       = true;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Array handling.
		$atts['categories']         = is_array( $atts['categories'] ) ? $atts['categories'] : array_filter( array_map( 'trim', explode( ',', $atts['categories'] ) ) );
		$atts['selected_category']  = is_array( $atts['selected_category'] ) ? $atts['selected_category'] : array_filter( array_map( 'trim', explode( ',', $atts['selected_category'] ) ) );
		$atts['job_types']          = is_array( $atts['job_types'] ) ? $atts['job_types'] : array_filter( array_map( 'trim', explode( ',', $atts['job_types'] ) ) );
		$atts['post_status']        = is_array( $atts['post_status'] ) ? $atts['post_status'] : array_filter( array_map( 'trim', explode( ',', $atts['post_status'] ) ) );
		$atts['selected_job_types'] = is_array( $atts['selected_job_types'] ) ? $atts['selected_job_types'] : array_filter( array_map( 'trim', explode( ',', $atts['selected_job_types'] ) ) );

		// Normalize field for categories.
		if ( ! empty( $atts['selected_category'] ) ) {
			foreach ( $atts['selected_category'] as $cat_index => $category ) {
				if ( ! is_numeric( $category ) ) {
					$term = get_term_by( 'slug', $category, \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY );

					if ( $term ) {
						$atts['selected_category'][ $cat_index ] = $term->term_id;
					}
				}
			}
		}

		$data_attributes = [
			'location'                   => $atts['location'],
			'keywords'                   => $atts['keywords'],
			'show_filters'               => $atts['show_filters'] ? 'true' : 'false',
			'show_pagination'            => $atts['show_pagination'] ? 'true' : 'false',
			'per_page'                   => $atts['per_page'],
			'orderby'                    => $atts['orderby'],
			'order'                      => $atts['order'],
			'categories'                 => implode( ',', $atts['categories'] ),
			'disable-form-state-storage' => $disable_client_state,
			'featured_first'             => $atts['featured_first'] ? 'true' : 'false',
		];

		if ( $atts['show_filters'] ) {
			get_job_manager_template(
				'job-filters.php',
				[
					'per_page'                  => $atts['per_page'],
					'orderby'                   => $atts['orderby'],
					'order'                     => $atts['order'],
					'show_categories'           => $atts['show_categories'],
					'categories'                => $atts['categories'],
					'selected_category'         => $atts['selected_category'],
					'job_types'                 => $atts['job_types'],
					'atts'                      => $atts,
					'location'                  => $atts['location'],
					'remote_position'           => $atts['remote_position'],
					'keywords'                  => $atts['keywords'],
					'selected_job_types'        => $atts['selected_job_types'],
					'show_category_multiselect' => $atts['show_category_multiselect'],
				]
			);

			get_job_manager_template( 'job-listings-start.php' );
			get_job_manager_template( 'job-listings-end.php' );

			if ( ! $atts['show_pagination'] && $atts['show_more'] ) {
				echo '<a class="load_more_jobs" href="#" style="display:none;"><strong>' . esc_html__( 'Load more listings', 'wp-job-manager' ) . '</strong></a>';
			}
		} else {
			$jobs = get_job_listings(
				apply_filters(
					'job_manager_output_jobs_args',
					[
						'search_location'   => $atts['location'],
						'search_keywords'   => $atts['keywords'],
						'post_status'       => $atts['post_status'],
						'search_categories' => $atts['categories'],
						'job_types'         => $atts['job_types'],
						'orderby'           => $atts['orderby'],
						'order'             => $atts['order'],
						'posts_per_page'    => $atts['per_page'],
						'featured'          => $atts['featured'],
						'filled'            => $atts['filled'],
						'remote_position'   => $atts['remote_position'],
						'featured_first'    => $atts['featured_first'],
					]
				)
			);

			if ( ! empty( $atts['job_types'] ) ) {
				$data_attributes['job_types'] = implode( ',', $atts['job_types'] );
			}

			if ( $jobs->have_posts() ) {
				get_job_manager_template( 'job-listings-start.php' );
				while ( $jobs->have_posts() ) {
					$jobs->the_post();
					get_job_manager_template_part( 'content', \WP_Job_Manager_Post_Types::PT_LISTING );
				}
				get_job_manager_template( 'job-listings-end.php' );
				if ( $jobs->found_posts > $atts['per_page'] && $atts['show_more'] ) {
					wp_enqueue_script( 'wp-job-manager-ajax-filters' );
					if ( $atts['show_pagination'] ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template output.
						echo get_job_listing_pagination( $jobs->max_num_pages );
					} else {
						echo '<a class="load_more_jobs" href="#"><strong>' . esc_html__( 'Load more listings', 'wp-job-manager' ) . '</strong></a>';
					}
				}
			} else {
				do_action( 'job_manager_output_jobs_no_results' );
			}
			wp_reset_postdata();
		}

		$data_attributes_string = '';
		if ( ! is_null( $atts['featured'] ) ) {
			$data_attributes['featured'] = $atts['featured'] ? 'true' : 'false';
		}
		if ( ! is_null( $atts['filled'] ) ) {
			$data_attributes['filled'] = $atts['filled'] ? 'true' : 'false';
		}
		if ( ! is_null( $atts['remote_position'] ) ) {
			$data_attributes['remote_position'] = $atts['remote_position'] ? 'true' : 'false';
		}
		if ( ! empty( $atts['post_status'] ) ) {
			$data_attributes['post_status'] = implode( ',', $atts['post_status'] );
		}

		$data_attributes['post_id'] = isset( $GLOBALS['post'] ) ? $GLOBALS['post']->ID : 0;

		/**
		 * Pass additional data to the job listings <div> wrapper.
		 *
		 * @since 1.34.0
		 *
		 * @param array $data_attributes {
		 *     Key => Value array of data attributes to pass.
		 *
		 *     @type string $$key Value to pass as a data attribute.
		 * }
		 * @param array $atts            Attributes for the shortcode.
		 */
		$data_attributes = apply_filters( 'job_manager_jobs_shortcode_data_attributes', $data_attributes, $atts );

		foreach ( $data_attributes as $key => $value ) {
			$data_attributes_string .= 'data-' . esc_attr( $key ) . '="' . esc_attr( $value ) . '" ';
		}

		$job_listings_output = apply_filters( 'job_manager_job_listings_output', ob_get_clean() );

		return '<div class="job_listings" ' . $data_attributes_string . '>' . $job_listings_output . '</div>';
	}

	/**
	 * Displays some content when no results were found.
	 */
	public function output_no_results() {
		get_job_manager_template( 'content-no-jobs-found.php' );
	}

	/**
	 * Gets string as a bool.
	 *
	 * @param  string $value
	 * @return bool
	 */
	public function string_to_bool( $value ) {
		return ( is_bool( $value ) && $value ) || in_array( $value, [ 1, '1', 'true', 'yes' ], true );
	}

	/**
	 * Shows job types.
	 *
	 * @param  array $atts
	 */
	public function job_filter_job_types( $atts ) {
		$job_types          = is_array( $atts['job_types'] ) ? $atts['job_types'] : array_filter( array_map( 'trim', explode( ',', $atts['job_types'] ) ) );
		$selected_job_types = is_array( $atts['selected_job_types'] ) ? $atts['selected_job_types'] : array_filter( array_map( 'trim', explode( ',', $atts['selected_job_types'] ) ) );

		get_job_manager_template(
			'job-filter-job-types.php',
			[
				'job_types'          => $job_types,
				'atts'               => $atts,
				'selected_job_types' => $selected_job_types,
			]
		);
	}

	/**
	 * Shows results div.
	 */
	public function job_filter_results() {
		echo '<div class="showing_jobs"></div>';
	}

	/**
	 * Shows a single job.
	 *
	 * @param array $atts
	 * @return string|null
	 */
	public function output_job( $atts ) {
		$atts = shortcode_atts(
			[
				'id' => '',
			],
			$atts
		);

		if ( ! $atts['id'] ) {
			return null;
		}

		ob_start();

		$args = [
			'post_type'   => \WP_Job_Manager_Post_Types::PT_LISTING,
			'post_status' => 'publish',
			'p'           => $atts['id'],
		];

		$jobs = new WP_Query( $args );

		if ( $jobs->have_posts() ) {
			while ( $jobs->have_posts() ) {
				$jobs->the_post();
				echo '<h1>' . wp_kses_post( wpjm_get_the_job_title() ) . '</h1>';
				get_job_manager_template_part( 'content-single', \WP_Job_Manager_Post_Types::PT_LISTING );
			}
		}

		wp_reset_postdata();

		return '<div class="job_shortcode single_job_listing">' . ob_get_clean() . '</div>';
	}

	/**
	 * Handles the Job Summary shortcode.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function output_job_summary( $atts ) {
		$atts = shortcode_atts(
			[
				'id'       => '',
				'width'    => '250px',
				'align'    => 'left',
				'featured' => null, // True to show only featured, false to hide featured, leave null to show both (when leaving out id).
				'limit'    => 1,
			],
			$atts
		);

		ob_start();

		$args = [
			'post_type'   => \WP_Job_Manager_Post_Types::PT_LISTING,
			'post_status' => 'publish',
		];

		if ( ! $atts['id'] ) {
			$args['posts_per_page'] = $atts['limit'];
			$args['orderby']        = 'rand';
			if ( ! is_null( $atts['featured'] ) ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Query results are limited.
				$args['meta_query'] = [
					[
						'key'     => '_featured',
						'value'   => '1',
						'compare' => $atts['featured'] ? '=' : '!=',
					],
				];
			}
		} else {
			$args['p'] = absint( $atts['id'] );
		}

		$jobs = new WP_Query( $args );

		if ( $jobs->have_posts() ) {
			while ( $jobs->have_posts() ) {
				$jobs->the_post();
				$width = $atts['width'] ? $atts['width'] : 'auto';
				echo '<div class="job_summary_shortcode align' . esc_attr( $atts['align'] ) . '" style="width: ' . esc_attr( $width ) . '">';
				get_job_manager_template_part( 'content-summary', \WP_Job_Manager_Post_Types::PT_LISTING );
				echo '</div>';
			}
		}

		wp_reset_postdata();

		return ob_get_clean();
	}

	/**
	 * Shows the application area.
	 *
	 * @param array $atts
	 * @return string
	 */
	public function output_job_apply( $atts ) {
		$new_atts = shortcode_atts(
			[
				'id' => '',
			],
			$atts
		);
		$id       = $new_atts['id'];

		ob_start();

		$args = [
			'post_type'   => \WP_Job_Manager_Post_Types::PT_LISTING,
			'post_status' => 'publish',
		];

		if ( ! $id ) {
			return '';
		} else {
			$args['p'] = absint( $id );
		}

		$jobs = new WP_Query( $args );

		if ( $jobs->have_posts() ) {
			while ( $jobs->have_posts() ) {
				$jobs->the_post();
				$apply = get_the_job_application_method();
				do_action( 'job_manager_before_job_apply_' . absint( $id ) );
				if ( apply_filters( 'job_manager_show_job_apply_' . absint( $id ), true ) ) {
					echo '<div class="job-manager-application-wrapper">';
					do_action( 'job_manager_application_details_' . $apply->type, $apply );
					echo '</div>';
				}
				do_action( 'job_manager_after_job_apply_' . absint( $id ) );
			}
			wp_reset_postdata();
		}

		return ob_get_clean();
	}

}

WP_Job_Manager_Shortcodes::instance();
