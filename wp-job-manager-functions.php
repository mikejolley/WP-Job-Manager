<?php
/**
 * Global WP Job Manager functions.
 *
 * New global functions are discouraged whenever possible.
 *
 * @package wp-job-manager
 */

if ( ! function_exists( 'get_job_listings' ) ) :
	/**
	 * Queries job listings with certain criteria and returns them.
	 *
	 * @since 1.0.5
	 * @param string|array|object $args Arguments used to retrieve job listings.
	 * @return WP_Query
	 */
	function get_job_listings( $args = [] ) {
		global $job_manager_keyword;

		$args = wp_parse_args(
			$args,
			[
				'search_location'   => '',
				'search_keywords'   => '',
				'search_categories' => [],
				'job_types'         => [],
				'post_status'       => [],
				'offset'            => 0,
				'posts_per_page'    => 20,
				'orderby'           => 'date',
				'order'             => 'DESC',
				'featured'          => null,
				'filled'            => null,
				'remote_position'   => null,
				'fields'            => 'all',
				'featured_first'    => 0,
			]
		);

		/**
		 * Perform actions that need to be done prior to the start of the job listings query.
		 *
		 * @since 1.26.0
		 *
		 * @param array $args Arguments used to retrieve job listings.
		 */
		do_action( 'get_job_listings_init', $args );

		if ( ! empty( $args['post_status'] ) ) {
			$post_status = $args['post_status'];
		} elseif ( 0 === intval( get_option( 'job_manager_hide_expired', get_option( 'job_manager_hide_expired_content', 1 ) ) ) ) {
			$post_status = [ 'publish', 'expired' ];
		} else {
			$post_status = 'publish';
		}

		$query_args = [
			'post_type'              => \WP_Job_Manager_Post_Types::PT_LISTING,
			'post_status'            => $post_status,
			'ignore_sticky_posts'    => 1,
			'offset'                 => absint( $args['offset'] ),
			'posts_per_page'         => intval( $args['posts_per_page'] ), // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Known slow query.
			'orderby'                => $args['orderby'],
			'order'                  => $args['order'],
			'tax_query'              => [], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Empty.
			'meta_query'             => [], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Empty.
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'cache_results'          => false,
			'fields'                 => $args['fields'],
		];

		if ( $args['posts_per_page'] < 0 ) {
			$query_args['no_found_rows'] = true;
		}

		$remote_position_search = false;

		if ( ! is_null( $args['remote_position'] ) ) {
			$remote_position_search = [
				'key'     => '_remote_position',
				'value'   => '1',
				'compare' => $args['remote_position'] ? '=' : '!=',
			];

			if ( '!=' === $remote_position_search['compare'] && apply_filters( 'job_manager_get_job_listings_remote_position_check_not_exists', true, $args ) ) {
				$remote_position_search = [
					'relation' => 'OR',
					$remote_position_search,
					[
						'key'     => '_remote_position',
						'compare' => 'NOT EXISTS',
					],
				];
			}
		}

		if ( ! empty( $args['search_location'] ) ) {
			$location_meta_keys = [ 'geolocation_formatted_address', '_job_location', 'geolocation_state_long' ];
			$location_search    = [ 'relation' => 'OR' ];
			$locations          = explode( ';', $args['search_location'] );

			foreach ( $locations as $location ) {
				$location = trim( $location );
				if ( ! empty( $location ) ) {
					$location_subquery = [ 'relation' => 'OR' ];
					foreach ( $location_meta_keys as $meta_key ) {
						$location_subquery[] = [
							'key'     => $meta_key,
							'value'   => $location,
							'compare' => 'like',
						];
					}
					$location_search[] = $location_subquery;
				}
			}

			if ( $remote_position_search ) {
				$location_search = [
					'relation' => 'AND',
					$remote_position_search,
					$location_search,
				];
			}

			$query_args['meta_query'][] = $location_search;

		} elseif ( $remote_position_search ) {
			$query_args['meta_query'][] = $remote_position_search;
		}

		if ( ! is_null( $args['featured'] ) ) {
			$query_args['meta_query'][] = [
				'key'     => '_featured',
				'value'   => '1',
				'compare' => $args['featured'] ? '=' : '!=',
			];
		}

		if ( ! is_null( $args['filled'] ) || 1 === absint( get_option( 'job_manager_hide_filled_positions' ) ) ) {
			$query_args['meta_query'][] = [
				'key'     => '_filled',
				'value'   => '1',
				'compare' => $args['filled'] ? '=' : '!=',
			];
		}

		if ( ! empty( $args['job_types'] ) ) {
			$query_args['tax_query'][] = [
				'taxonomy' => \WP_Job_Manager_Post_Types::TAX_LISTING_TYPE,
				'field'    => 'slug',
				'terms'    => $args['job_types'],
			];
		}

		if ( ! empty( $args['search_categories'] ) ) {
			$field                     = is_numeric( $args['search_categories'][0] ) ? 'term_id' : 'slug';
			$operator                  = 'all' === get_option( 'job_manager_category_filter_type', 'all' ) && count( $args['search_categories'] ) > 1 ? 'AND' : 'IN';
			$query_args['tax_query'][] = [
				'taxonomy'         => \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY,
				'field'            => $field,
				'terms'            => array_values( $args['search_categories'] ),
				'include_children' => 'AND' !== $operator,
				'operator'         => $operator,
			];
		}

		if ( 'featured' === $args['orderby'] ) {
			$query_args['orderby'] = [
				'menu_order' => 'ASC',
				'date'       => 'DESC',
				'ID'         => 'DESC',
			];
		}

		if ( 'rand_featured' === $args['orderby'] ) {
			$query_args['orderby'] = [
				'menu_order' => 'ASC',
				'rand'       => 'ASC',
			];
		}

		if ( isset( $args['featured_first'] ) ) {
			$args['featured_first'] = filter_var( $args['featured_first'], FILTER_VALIDATE_BOOLEAN );
		}

		if ( true === $args['featured_first'] && 'featured' !== $args['orderby'] && 'rand_featured' !== $args['orderby'] ) {
			$query_args['orderby'] = [
				'menu_order'           => 'ASC',
				$query_args['orderby'] => $query_args['order'],
			];
		}

		$job_manager_keyword = sanitize_text_field( $args['search_keywords'] );

		if ( ! empty( $job_manager_keyword ) && strlen( $job_manager_keyword ) >= apply_filters( 'job_manager_get_listings_keyword_length_threshold', 2 ) ) {
			$query_args['s'] = $job_manager_keyword;
			add_filter( 'posts_search', 'get_job_listings_keyword_search', 10, 2 );
		}

		$query_args = apply_filters( 'job_manager_get_listings', $query_args, $args );

		if ( empty( $query_args['meta_query'] ) ) {
			unset( $query_args['meta_query'] );
		}

		if ( empty( $query_args['tax_query'] ) ) {
			unset( $query_args['tax_query'] );
		}

		/** This filter is documented in wp-job-manager.php */
		$query_args['lang'] = apply_filters( 'wpjm_lang', null );

		// Filter args.
		$query_args = apply_filters( 'get_job_listings_query_args', $query_args, $args );

		do_action( 'before_get_job_listings', $query_args, $args );

		$should_cache = 'rand_featured' !== $args['orderby'] && 'rand' !== $args['orderby'];

		// Cache results.
		if ( apply_filters( 'get_job_listings_cache_results', $should_cache ) ) {
			$to_hash            = wp_json_encode( $query_args );
			$query_args_hash    = 'jm_' . md5( $to_hash . JOB_MANAGER_VERSION ) . WP_Job_Manager_Cache_Helper::get_transient_version( 'get_job_listings' );
			$result             = false;
			$cached_query_posts = get_transient( $query_args_hash );
			if ( is_string( $cached_query_posts ) ) {
				$cached_query_posts = json_decode( $cached_query_posts, false );
				if (
					$cached_query_posts
					&& is_object( $cached_query_posts )
					&& isset( $cached_query_posts->max_num_pages )
					&& isset( $cached_query_posts->found_posts )
					&& isset( $cached_query_posts->posts )
					&& is_array( $cached_query_posts->posts )
				) {
					if ( in_array( $query_args['fields'], [ 'ids', 'id=>parent' ], true ) ) {
						// For these special requests, just return the array of results as set.
						$posts = $cached_query_posts->posts;
					} else {
						$posts = array_map( 'get_post', $cached_query_posts->posts );
					}

					$result = new WP_Query();
					$result->parse_query( $query_args );
					$result->posts         = $posts;
					$result->found_posts   = intval( $cached_query_posts->found_posts );
					$result->max_num_pages = intval( $cached_query_posts->max_num_pages );
					$result->post_count    = count( $posts );
				}
			}

			if ( false === $result ) {
				$result = new WP_Query( $query_args );

				$cacheable_result                  = [];
				$cacheable_result['posts']         = array_values( $result->posts );
				$cacheable_result['found_posts']   = $result->found_posts;
				$cacheable_result['max_num_pages'] = $result->max_num_pages;
				set_transient( $query_args_hash, wp_json_encode( $cacheable_result ), DAY_IN_SECONDS );
			}
		} else {
			$result = new WP_Query( $query_args );
		}

		do_action( 'after_get_job_listings', $query_args, $args );

		remove_filter( 'posts_search', 'get_job_listings_keyword_search', 10 );

		return $result;
	}
endif;

if ( ! function_exists( '_wpjm_shuffle_featured_post_results_helper' ) ) :
	/**
	 * Helper function to maintain featured status when shuffling results.
	 *
	 * @param WP_Post $a
	 * @param WP_Post $b
	 *
	 * @return bool
	 */
	function _wpjm_shuffle_featured_post_results_helper( $a, $b ) {
		if ( -1 === $a->menu_order || -1 === $b->menu_order ) {
			// Left is featured.
			if ( 0 === $b->menu_order ) {
				return -1;
			}
			// Right is featured.
			if ( 0 === $a->menu_order ) {
				return 1;
			}
		}
		return wp_rand( -1, 1 );
	}
endif;

if ( ! function_exists( 'get_job_listings_keyword_search' ) ) :
	/**
	 * Adds join and where query for keywords.
	 *
	 * @since 1.21.0
	 * @since 1.26.0 Moved from the `posts_clauses` filter to the `posts_search` to use WP Query's keyword
	 *               search for `post_title` and `post_content`.
	 * @since 2.4.0 Reimplemented to provide the same functionality with WP core search:
	 *                 - Support for double quotes and negating terms (-).
	 *                 - Breaks down terms into individual words.
	 *                 - Meta and taxonomy name search happens together with search in title, excerpt and post content.
	 *
	 * @param string   $search   The search string.
	 * @param WP_Query $wp_query The query.
	 *
	 * @return string
	 */
	function get_job_listings_keyword_search( $search, $wp_query ) {
		global $wpdb;

		if ( ! function_exists( 'job_manager_construct_secondary_conditions' ) && ! function_exists( 'job_manager_construct_post_conditions' ) ) {
				/**
				 * Constructs SQL clauses that return posts which have metas and terms that include or exclude the search term.
				 *
				 * @param string $search_term    The search term.
				 * @param bool   $is_excluding   Whether posts should be excluded if they match the search terms.
				 * @param string $wildcard_search The wildcard character or empty string for exact matches.
				 *
				 * @return array The SQL clauses.
				 */
			function job_manager_construct_secondary_conditions( $search_term, $is_excluding, $wildcard_search ) {
				global $wpdb;

				if ( empty( $search_term ) ) {
					return [];
				}

				$searchable_meta_keys = [
					'_application',
					'_company_name',
					'_company_tagline',
					'_company_website',
					'_company_twitter',
					'_job_location',
				];

				/**
				 * Filters the meta keys that are used in job search.
				 *
				 * @param array $searchable_meta_keys The meta keys.
				 */
				$searchable_meta_keys = apply_filters( 'job_listing_searchable_meta_keys', $searchable_meta_keys );

				$not_string = $is_excluding ? 'NOT ' : '';
				$conditions = [];
				$meta_value = $wildcard_search . $wpdb->esc_like( $search_term ) . $wildcard_search;

				/**
				 * Can be used to disable searching post meta for job searches.
				 *
				 * @param bool $enable_meta_search Return false to disable meta search.
				 */
				if ( apply_filters( 'job_listing_search_post_meta', true ) ) {

					// Only selected meta keys.
					if ( $searchable_meta_keys ) {
						$meta_keys = implode( "','", array_map( 'esc_sql', $searchable_meta_keys ) );
						// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Variables are safe or escaped.
						$conditions[] = $wpdb->prepare( "{$wpdb->posts}.ID {$not_string}IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ( '{$meta_keys}' ) AND meta_value LIKE %s )", $meta_value );
					} else {
						// No meta keys defined, search all post meta value.
						$conditions[] = $wpdb->prepare( "{$wpdb->posts}.ID {$not_string}IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_value LIKE %s )", $meta_value );
						// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					}
				}

				// Search taxonomy.
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Variables are safe or escaped.
				$conditions[] = $wpdb->prepare( "{$wpdb->posts}.ID {$not_string}IN ( SELECT object_id FROM {$wpdb->term_relationships} AS tr LEFT JOIN {$wpdb->term_taxonomy} AS tt ON tr.term_taxonomy_id = tt.term_taxonomy_id LEFT JOIN {$wpdb->terms} AS t ON tt.term_id = t.term_id WHERE t.name LIKE %s )", $meta_value );

				return $conditions;
			}

			/**
			 * Constructs SQL clauses that return posts which include or exclude the search term in the provided columns.
			 * The function replicates the functionality of WP_Query::parse_search.
			 *
			 * @see WP_Query::parse_search()
			 *
			 * @param string $search_term     The search term to match.
			 * @param bool   $is_excluding    Whether posts that match the search term should be excluded.
			 * @param string $wildcard_search The wildcard character or empty string for exact matches.
			 * @param array  $search_columns   The columns to check.
			 *
			 * @return array The SQL clauses.
			 */
			function job_manager_construct_post_conditions( $search_term, $is_excluding, $wildcard_search, $search_columns ) {
				global $wpdb;

				if ( $is_excluding ) {
					$like_op = 'NOT LIKE';
				} else {
					$like_op = 'LIKE';
				}

				$like = $wildcard_search . $wpdb->esc_like( $search_term ) . $wildcard_search;

				$conditions = [];
				foreach ( $search_columns as $search_column ) {
					$search_column = esc_sql( $search_column );
					//phpcs:disabled WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Variables are safe or escaped.
					$conditions[] = $wpdb->prepare( "( {$wpdb->posts}.$search_column $like_op %s )", $like );
				}

				// Filter documented in WP_Query::get_posts.
				$allow_query_attachment_by_filename = apply_filters( 'wp_allow_query_attachment_by_filename', false );
				if ( ! empty( $allow_query_attachment_by_filename ) ) {
					// sq1 is the wp_postmeta join for attachments in WP_Query::get_posts.
					$conditions[] = $wpdb->prepare( "(sq1.meta_value $like_op %s)", $like );
					//phpcs:enabled WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}

				return $conditions;
			}
		}

		/**
		 * This function aims to provide similar search functionality with WP core while also including meta and taxonomy terms
		 * in the searched columns. The functionality of WP_Query::parse_search is replicated but with additional SQL
		 * clauses which are generated in the job_manager_construct_secondary_conditions function.
		 */
		$default_search_columns = [ 'post_title', 'post_excerpt', 'post_content' ];
		$search_columns         = ! empty( $wp_query->query_vars['search_columns'] ) ? $wp_query->query_vars['search_columns'] : $default_search_columns;
		if ( ! is_array( $search_columns ) ) {
			$search_columns = [ $search_columns ];
		}

		// Filter documented in WP_Query::parse_search.
		$search_columns = (array) apply_filters( 'post_search_columns', $search_columns, $wp_query->query_vars['s'], $wp_query );

		// Use only supported search columns.
		$search_columns = array_intersect( $search_columns, $default_search_columns );
		if ( empty( $search_columns ) ) {
			$search_columns = $default_search_columns;
		}

		// Search terms starting with the exclusion prefix should be removed from the job search results.
		$exclusion_prefix = apply_filters( 'wp_query_search_exclusion_prefix', '-' );
		$wildcard_search  = ! empty( $wp_query->query_vars['exact'] ) ? '' : '%';
		$new_search       = '';
		$searchand        = '';

		foreach ( $wp_query->query_vars['search_terms'] as $search_term ) {
			$is_excluding = $exclusion_prefix && str_starts_with( $search_term, $exclusion_prefix );

			if ( $is_excluding ) {
				$search_term = substr( $search_term, 1 );
				$andor_op    = 'AND';
			} else {
				$andor_op = 'OR';
			}

			$conditions = job_manager_construct_post_conditions( $search_term, $is_excluding, $wildcard_search, $search_columns );
			$conditions = array_merge( $conditions, job_manager_construct_secondary_conditions( $search_term, $is_excluding, $wildcard_search ) );

			$new_search .= "$searchand(" . implode( " $andor_op ", $conditions ) . ')';

			$searchand = ' AND ';
		}

		if ( ! empty( $new_search ) ) {
			$new_search = " AND ({$new_search}) ";
			if ( ! is_user_logged_in() ) {
				$new_search .= " AND ({$wpdb->posts}.post_password = '') ";
			}
		} else {
			return $search;
		}

		return $new_search;
	}
endif;

if ( ! function_exists( 'get_job_listing_post_statuses' ) ) :
	/**
	 * Gets post statuses used for jobs.
	 *
	 * @since 1.12.0
	 * @return array
	 */
	function get_job_listing_post_statuses() {
		return apply_filters(
			'job_listing_post_statuses',
			[
				'draft'           => _x( 'Draft', 'post status', 'wp-job-manager' ),
				'expired'         => _x( 'Expired', 'post status', 'wp-job-manager' ),
				'preview'         => _x( 'Preview', 'post status', 'wp-job-manager' ),
				'pending'         => _x( 'Pending approval', 'post status', 'wp-job-manager' ),
				'pending_payment' => _x( 'Pending payment', 'post status', 'wp-job-manager' ),
				'publish'         => _x( 'Active', 'post status', 'wp-job-manager' ),
				'future'          => _x( 'Scheduled', 'post status', 'wp-job-manager' ),
			]
		);
	}
endif;

if ( ! function_exists( 'get_featured_job_ids' ) ) :
	/**
	 * Gets the ids of featured jobs.
	 *
	 * @since 1.0.4
	 * @return array
	 */
	function get_featured_job_ids() {
		return get_posts(
			[
				'posts_per_page'   => -1,
				'suppress_filters' => false,
				'post_type'        => \WP_Job_Manager_Post_Types::PT_LISTING,
				'post_status'      => 'publish',
				'meta_key'         => '_featured', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Used in production with no issues.
				'meta_value'       => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Used in production with no issues.
				'fields'           => 'ids',
			]
		);
	}
endif;

if ( ! function_exists( 'get_job_listing_types' ) ) :
	/**
	 * Gets job listing types.
	 *
	 * @since 1.0.0
	 * @param string|array $fields
	 * @return WP_Term[]
	 */
	function get_job_listing_types( $fields = 'all' ) {
		if ( ! get_option( 'job_manager_enable_types' ) ) {
			return [];
		} else {
			$args = [
				'fields'     => $fields,
				'hide_empty' => false,
				'order'      => 'ASC',
				'orderby'    => 'name',
			];

			$args = apply_filters( 'get_job_listing_types_args', $args );

			// Prevent users from filtering the taxonomy.
			$args['taxonomy'] = \WP_Job_Manager_Post_Types::TAX_LISTING_TYPE;

			return get_terms( $args );
		}
	}
endif;

if ( ! function_exists( 'get_job_listing_categories' ) ) :
	/**
	 * Gets job categories.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	function get_job_listing_categories() {
		if ( ! get_option( 'job_manager_enable_categories' ) ) {
			return [];
		}

		$args = [
			'orderby'    => 'name',
			'order'      => 'ASC',
			'hide_empty' => false,
		];

		/**
		 * Change the category query arguments.
		 *
		 * @since 1.31.0
		 *
		 * @param array $args
		 */
		$args = apply_filters( 'get_job_listing_category_args', $args );

		// Prevent users from filtering the taxonomy.
		$args['taxonomy'] = \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY;

		return get_terms( $args );
	}
endif;

if ( ! function_exists( 'job_manager_get_filtered_links' ) ) :
	/**
	 * Shows links after filtering jobs
	 *
	 * @since 1.0.6
	 * @param array $args
	 * @return string
	 */
	function job_manager_get_filtered_links( $args = [] ) {
		$job_categories = [];
		$types          = get_job_listing_types();

		// Convert to slugs.
		if ( $args['search_categories'] ) {
			foreach ( $args['search_categories'] as $category ) {
				if ( is_numeric( $category ) ) {
					$category_object = get_term_by( 'id', $category, \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY );
					if ( ! is_wp_error( $category_object ) ) {
						$job_categories[] = $category_object->slug;
					}
				} else {
					$job_categories[] = $category;
				}
			}
		}

		$links = apply_filters(
			'job_manager_job_filters_showing_jobs_links',
			[
				'reset'    => [
					'name' => __( 'Reset', 'wp-job-manager' ),
					'url'  => '#',
				],
				'rss_link' => [
					'name' => __( 'RSS', 'wp-job-manager' ),
					'url'  => get_job_listing_rss_link(
						apply_filters(
							'job_manager_get_listings_custom_filter_rss_args',
							[
								'job_types'       => isset( $args['filter_job_types'] ) ? implode( ',', $args['filter_job_types'] ) : '',
								'search_location' => $args['search_location'],
								'job_categories'  => implode( ',', $job_categories ),
								'search_keywords' => $args['search_keywords'],
							]
						)
					),
				],
			],
			$args
		);

		if (
			count( (array) $args['filter_job_types'] ) === count( $types )
			&& empty( $args['search_keywords'] )
			&& empty( $args['search_location'] )
			&& empty( $args['search_categories'] )
			&& ! apply_filters( 'job_manager_get_listings_custom_filter', false )
		) {
			unset( $links['reset'] );
		}

		$return = '';

		foreach ( $links as $key => $link ) {
			$attrs   = ! empty( $link['onclick'] ) ? ' onclick="' . esc_attr( $link['onclick'] ) . '"' : '';
			$return .= '<a href="' . esc_url( $link['url'] ) . '" class="' . esc_attr( $key ) . '"' . $attrs . '>' . wp_kses_post( $link['name'] ) . '</a>';
		}

		return $return;
	}
endif;

if ( ! function_exists( 'get_job_listing_rss_link' ) ) :
	/**
	 * Get the Job Listing RSS link
	 *
	 * @since 1.0.0
	 * @param array $args
	 * @return string
	 */
	function get_job_listing_rss_link( $args = [] ) {
		$rss_link = add_query_arg( urlencode_deep( array_merge( [ 'feed' => WP_Job_Manager_Post_Types::get_job_feed_name() ], $args ) ), home_url() );
		return $rss_link;
	}
endif;

if ( ! function_exists( 'wp_job_manager_notify_new_user' ) ) :
	/**
	 * Handles notification of new users.
	 *
	 * @since 1.23.10
	 * @param  int         $user_id
	 * @param  string|bool $password
	 */
	function wp_job_manager_notify_new_user( $user_id, $password ) {
		global $wp_version;

		if ( version_compare( $wp_version, '4.3.1', '<' ) ) {
			// phpcs:ignore WordPress.WP.DeprecatedParameters.Wp_new_user_notificationParam2Found
			wp_new_user_notification( $user_id, $password );
		} else {
			$notify = 'admin';
			if ( empty( $password ) ) {
				$notify = 'both';
			}
			wp_new_user_notification( $user_id, null, $notify );
		}
	}
endif;

if ( ! function_exists( 'wp_job_manager_create_account' ) ) :
	/**
	 * Handles account creation.
	 *
	 * @since 1.0.0
	 * @param  string|array|object $args containing username, email, role.
	 * @param  string              $deprecated role string.
	 * @return WP_Error|bool True if account was created.
	 */
	function wp_job_manager_create_account( $args, $deprecated = '' ) {
		// Soft Deprecated in 1.20.0.
		if ( ! is_array( $args ) ) {
			$args = [
				'username' => '',
				'password' => false,
				'email'    => $args,
				'role'     => $deprecated,
			];
		} else {
			$defaults = [
				'username' => '',
				'email'    => '',
				'password' => false,
				'role'     => get_option( 'default_role' ),
			];

			$args = wp_parse_args( $args, $defaults );
		}

		$username = sanitize_user( $args['username'], true );
		$email    = apply_filters( 'user_registration_email', sanitize_email( $args['email'] ) );

		if ( empty( $email ) ) {
			return new WP_Error( 'validation-error', __( 'Invalid email address.', 'wp-job-manager' ) );
		}

		if ( empty( $username ) ) {
			$username = sanitize_user( current( explode( '@', $email ) ), true );
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'validation-error', __( 'Your email address isn&#8217;t correct.', 'wp-job-manager' ) );
		}

		if ( email_exists( $email ) ) {
			return new WP_Error( 'validation-error', __( 'This email is already registered, please choose another one.', 'wp-job-manager' ) );
		}

		// Ensure username is unique.
		$append     = 1;
		$o_username = $username;

		while ( username_exists( $username ) ) {
			$username = $o_username . $append;
			$append ++;
		}

		// Final error checking.
		$reg_errors = new WP_Error();
		$reg_errors = apply_filters( 'job_manager_registration_errors', $reg_errors, $username, $email );

		do_action( 'job_manager_register_post', $username, $email, $reg_errors );

		if ( $reg_errors->get_error_code() ) {
			return $reg_errors;
		}

		// Create account.
		$new_user = [
			'user_login' => $username,
			'user_pass'  => $args['password'],
			'user_email' => $email,
			'role'       => $args['role'],
		];

		// User is forced to set up account with email sent to them. This password will remain a secret.
		if ( empty( $new_user['user_pass'] ) ) {
			$new_user['user_pass'] = wp_generate_password();
		}

		$user_id = wp_insert_user( apply_filters( 'job_manager_create_account_data', $new_user ) );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		/**
		 * Send notification to new users.
		 *
		 * @since 1.28.0
		 *
		 * @param  int         $user_id
		 * @param  string|bool $password
		 * @param  array       $new_user {
		 *     Information about the new user.
		 *
		 *     @type string $user_login Username for the user.
		 *     @type string $user_pass  Password for the user (may be blank).
		 *     @type string $user_email Email for the new user account.
		 *     @type string $role       New user's role.
		 * }
		 */
		do_action( 'wpjm_notify_new_user', $user_id, $args['password'], $new_user );

		// Login.
		add_action( 'set_logged_in_cookie', '_wpjm_update_global_login_cookie' );
		wp_set_auth_cookie( $user_id, true, is_ssl() );
		wp_set_current_user( $user_id );
		remove_action( 'set_logged_in_cookie', '_wpjm_update_global_login_cookie' );

		return true;
	}
endif;

/**
 * Allows for immediate access to the logged in cookie after mid-request login.
 *
 * @since 1.32.2
 * @access private
 *
 * @param string $logged_in_cookie Logged in cookie.
 */
function _wpjm_update_global_login_cookie( $logged_in_cookie ) {
	$_COOKIE[ LOGGED_IN_COOKIE ] = $logged_in_cookie;
}

/**
 * Checks if the user can upload a file via the Ajax endpoint.
 *
 * @since 1.26.2
 * @return bool
 */
function job_manager_user_can_upload_file_via_ajax() {
	$can_upload = is_user_logged_in() && job_manager_user_can_post_job();

	if ( has_filter( 'job_manager_ajax_file_upload_enabled' ) ) {
		_deprecated_hook( 'job_manager_ajax_file_upload_enabled', '1.30.0', 'job_manager_user_can_upload_file_via_ajax' );
		$can_upload = apply_filters( 'job_manager_ajax_file_upload_enabled', $can_upload );
	}

	/**
	 * Override ability of a user to upload a file via Ajax.
	 *
	 * @since 1.26.2
	 * @param bool $can_upload True if they can upload files from Ajax endpoint.
	 */
	return apply_filters( 'job_manager_user_can_upload_file_via_ajax', $can_upload );
}

/**
 * Checks if an the user can post a job. If accounts are required, and reg is enabled, users can post (they signup at the same time).
 *
 * @since 1.5.1
 * @return bool
 */
function job_manager_user_can_post_job() {
	$can_post = true;

	if ( ! is_user_logged_in() ) {
		if ( job_manager_user_requires_account() && ! job_manager_enable_registration() ) {
			$can_post = false;
		}
	}

	return apply_filters( 'job_manager_user_can_post_job', $can_post );
}

/**
 * Checks if the user can edit a job.
 *
 * @since 1.5.1
 * @param int|WP_Post $job_id
 * @return bool
 */
function job_manager_user_can_edit_job( $job_id ) {
	$can_edit = true;

	if ( ! is_user_logged_in() || ! $job_id ) {
		$can_edit = false;
	} else {
		$job = get_post( $job_id );

		if ( ! $job || \WP_Job_Manager_Post_Types::PT_LISTING !== $job->post_type || ( absint( $job->post_author ) !== get_current_user_id() && ! current_user_can( 'edit_post', $job_id ) ) ) {
			$can_edit = false;
		}
	}

	return apply_filters( 'job_manager_user_can_edit_job', $can_edit, $job_id );
}

/**
 * Checks if the visitor is currently on a WPJM page, job listing, or taxonomy.
 *
 * @since 1.30.0
 *
 * @return bool
 */
function is_wpjm() {
	/**
	 * Filter the result of is_wpjm()
	 *
	 * @since 1.30.0
	 *
	 * @param bool $is_wpjm
	 */
	return apply_filters( 'is_wpjm', ( is_wpjm_page() || has_wpjm_shortcode() || is_wpjm_job_listing() || is_wpjm_taxonomy() ) );
}

/**
 * Checks if the visitor is currently on a WPJM page.
 *
 * @since 1.30.0
 *
 * @return bool
 */
function is_wpjm_page() {
	$is_wpjm_page = is_post_type_archive( \WP_Job_Manager_Post_Types::PT_LISTING );

	if ( ! $is_wpjm_page ) {
		$wpjm_page_ids = array_filter(
			[
				get_option( 'job_manager_submit_job_form_page_id', false ),
				get_option( 'job_manager_job_dashboard_page_id', false ),
				get_option( 'job_manager_jobs_page_id', false ),
			]
		);

		/**
		 * Filters a list of all page IDs related to WPJM.
		 *
		 * @since 1.30.0
		 *
		 * @param int[] $wpjm_page_ids
		 */
		$wpjm_page_ids = array_unique( apply_filters( 'job_manager_page_ids', $wpjm_page_ids ) );

		if ( ! empty( $wpjm_page_ids ) ) {
			$is_wpjm_page = is_page( $wpjm_page_ids );
		}
	}

	/**
	 * Filter the result of is_wpjm_page()
	 *
	 * @since 1.30.0
	 *
	 * @param bool $is_wpjm_page
	 */
	return apply_filters( 'is_wpjm_page', $is_wpjm_page );
}

/**
 * Checks if the provided content or the current single page or post has a WPJM shortcode.
 *
 * @param string|null       $content   Content to check. If not provided, it uses the current post content.
 * @param string|array|null $tag Check specifically for one or more shortcodes. If not provided, checks for any WPJM shortcode.
 *
 * @return bool
 */
function has_wpjm_shortcode( $content = null, $tag = null ) {
	global $post;

	$has_wpjm_shortcode = false;

	if ( null === $content && is_singular() && is_a( $post, 'WP_Post' ) ) {
		$content = $post->post_content;
	}

	if ( ! empty( $content ) ) {
		$wpjm_shortcodes = [ 'submit_job_form', 'job_dashboard', 'jobs', 'job', 'job_summary', 'job_apply' ];
		/**
		 * Filters a list of all shortcodes associated with WPJM.
		 *
		 * @since 1.30.0
		 *
		 * @param string[] $wpjm_shortcodes
		 */
		$wpjm_shortcodes = array_unique( apply_filters( 'job_manager_shortcodes', $wpjm_shortcodes ) );

		if ( null !== $tag ) {
			if ( ! is_array( $tag ) ) {
				$tag = [ $tag ];
			}
			$wpjm_shortcodes = array_intersect( $wpjm_shortcodes, $tag );
		}

		foreach ( $wpjm_shortcodes as $shortcode ) {
			if ( has_shortcode( $content, $shortcode ) ) {
				$has_wpjm_shortcode = true;
				break;
			}
		}
	}

	/**
	 * Filter the result of has_wpjm_shortcode()
	 *
	 * @since 1.30.0
	 *
	 * @param bool $has_wpjm_shortcode
	 */
	return apply_filters( 'has_wpjm_shortcode', $has_wpjm_shortcode );
}

/**
 * Checks if the current page is a job listing.
 *
 * @since 1.30.0
 *
 * @return bool
 */
function is_wpjm_job_listing() {
	return is_singular( [ \WP_Job_Manager_Post_Types::PT_LISTING ] );
}

/**
 * Checks if the visitor is on a page for a WPJM taxonomy.
 *
 * @since 1.30.0
 *
 * @return bool
 */
function is_wpjm_taxonomy() {
	return is_tax( get_object_taxonomies( \WP_Job_Manager_Post_Types::PT_LISTING ) );
}

/**
 * Checks to see if the standard password setup email should be used.
 *
 * @since 1.27.0
 *
 * @return bool True if they are to use standard email, false to allow user to set password at first job creation.
 */
function wpjm_use_standard_password_setup_email() {
	$use_standard_password_setup_email = 1 === intval( get_option( 'job_manager_use_standard_password_setup_email' ) );

	/**
	 * Allows an override of the setting for if a password should be auto-generated for new users.
	 *
	 * @since 1.27.0
	 *
	 * @param bool $use_standard_password_setup_email True if a standard account setup email should be sent.
	 */
	return apply_filters( 'wpjm_use_standard_password_setup_email', $use_standard_password_setup_email );
}

/**
 * Returns the list of employment types from Google's modification of schema.org's employmentType.
 *
 * @since 1.28.0
 * @see https://developers.google.com/search/docs/data-types/job-postings#definitions
 *
 * @return array
 */
function wpjm_job_listing_employment_type_options() {
	$employment_types               = [];
	$employment_types['FULL_TIME']  = __( 'Full Time', 'wp-job-manager' );
	$employment_types['PART_TIME']  = __( 'Part Time', 'wp-job-manager' );
	$employment_types['CONTRACTOR'] = __( 'Contractor', 'wp-job-manager' );
	$employment_types['TEMPORARY']  = __( 'Temporary', 'wp-job-manager' );
	$employment_types['INTERN']     = __( 'Intern', 'wp-job-manager' );
	$employment_types['VOLUNTEER']  = __( 'Volunteer', 'wp-job-manager' );
	$employment_types['PER_DIEM']   = __( 'Per Diem', 'wp-job-manager' );
	$employment_types['OTHER']      = __( 'Other', 'wp-job-manager' );

	/**
	 * Filter the list of employment types.
	 *
	 * @since 1.28.0
	 *
	 * @param array List of employment types { string $key => string $label }.
	 */
	return apply_filters( 'wpjm_job_listing_employment_type_options', $employment_types );
}


/**
 * Check if employment type meta fields are enabled on job type terms.
 *
 * @since 1.28.0
 *
 * @return bool
 */
function wpjm_job_listing_employment_type_enabled() {
	/**
	 * Filter whether employment types are enabled for job type terms.
	 *
	 * @since 1.28.0
	 *
	 * @param bool True if employment type meta field is enabled on job type terms.
	 */
	return apply_filters( 'wpjm_job_listing_employment_type_enabled', (bool) get_option( 'job_manager_enable_types' ) );
}

/**
 * Checks if a password should be auto-generated for new users.
 *
 * @since 1.27.0
 *
 * @param string $password Password to validate.
 * @return bool True if password meets rules.
 */
function wpjm_validate_new_password( $password ) {
	// Password must be at least 8 characters long. Trimming here because `wp_hash_password()` will later on.
	$is_valid_password = strlen( trim( $password ) ) >= 8;

	/**
	 * Allows overriding default WPJM password validation rules.
	 *
	 * @since 1.27.0
	 *
	 * @param bool   $is_valid_password True if new password is validated.
	 * @param string $password          Password to validate.
	 */
	return apply_filters( 'wpjm_validate_new_password', $is_valid_password, $password );
}

/**
 * Returns the password rules hint.
 *
 * @return string
 */
function wpjm_get_password_rules_hint() {
	/**
	 * Allows overriding the hint shown below the new password input field. Describes rules set in `wpjm_validate_new_password`.
	 *
	 * @since 1.27.0
	 *
	 * @param string $password_rules Password rules description.
	 */
	return apply_filters( 'wpjm_password_rules_hint', __( 'Passwords must be at least 8 characters long.', 'wp-job-manager' ) );
}

/**
 * Checks if only one type allowed per job.
 *
 * @since 1.25.2
 * @return bool
 */
function job_manager_multi_job_type() {
	return apply_filters( 'job_manager_multi_job_type', 1 === intval( get_option( 'job_manager_multi_job_type' ) ) );
}

/**
 * Checks if registration is enabled.
 *
 * @since 1.5.1
 * @return bool
 */
function job_manager_enable_registration() {
	return apply_filters( 'job_manager_enable_registration', 1 === intval( get_option( 'job_manager_enable_registration' ) ) );
}

/**
 * Checks if usernames are generated from email addresses.
 *
 * @since 1.20.0
 * @return bool
 */
function job_manager_generate_username_from_email() {
	return apply_filters( 'job_manager_generate_username_from_email', 1 === intval( get_option( 'job_manager_generate_username_from_email' ) ) );
}

/**
 * Checks if an account is required to post a job.
 *
 * @since 1.5.1
 * @return bool
 */
function job_manager_user_requires_account() {
	return apply_filters( 'job_manager_user_requires_account', 1 === intval( get_option( 'job_manager_user_requires_account' ) ) );
}

/**
 * Checks if users are allowed to edit submissions that are pending approval.
 *
 * @since 1.16.1
 * @return bool
 */
function job_manager_user_can_edit_pending_submissions() {
	return apply_filters( 'job_manager_user_can_edit_pending_submissions', 1 === intval( get_option( 'job_manager_user_can_edit_pending_submissions' ) ) );
}

/**
 * Checks if users are allowed to edit published submissions.
 *
 * @since 1.29.0
 * @return bool
 */
function wpjm_user_can_edit_published_submissions() {
	/**
	 * Override the setting for allowing a user to edit published job listings.
	 *
	 * @since 1.29.0
	 *
	 * @param bool $can_edit_published_submissions
	 */
	return apply_filters( 'job_manager_user_can_edit_published_submissions', in_array( get_option( 'job_manager_user_edit_published_submissions' ), [ 'yes', 'yes_moderated' ], true ) );
}

/**
 * Checks if moderation is required when users edit published submissions.
 *
 * @since 1.29.0
 * @return bool
 */
function wpjm_published_submission_edits_require_moderation() {
	$require_moderation = 'yes_moderated' === get_option( 'job_manager_user_edit_published_submissions' );

	/**
	 * Override the setting for user edits to job listings requiring moderation.
	 *
	 * @since 1.29.0
	 *
	 * @param bool $require_moderation
	 */
	return apply_filters( 'job_manager_published_submission_edits_require_moderation', $require_moderation );
}

/**
 * Get the category slugs from the search query string.
 * The query string is made with the category slugs separate by commas.
 *
 * @since 1.34.2
 *
 * @return string[] $search_category_slugs Array of category slugs.
 */
function wpjm_get_category_slugs_from_search_query_string() {
	$search_category_slugs = [];

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Should be checked outside of this function.
	$search_category = ! empty( $_GET['search_category'] ) ? sanitize_text_field( wp_unslash( $_GET['search_category'] ) ) : false;

	if ( $search_category ) {
		$search_category_slugs = explode( ',', $search_category );
	}

	return $search_category_slugs;
}

/**
 * Get categories by slug.
 *
 * @since 1.34.2
 *
 * @param string[]  $search_category_slugs Array of category slugs to search.
 * @param array     $default_args          Default args to search the term categories.
 * @param WP_Term[] $exclude_categories    Array of categories to exclude.
 *
 * @return WP_Term[] $categories Array of categories.
 */
function wpjm_get_categories_by_slug( $search_category_slugs, $default_args, $exclude_categories ) {
	$exclude_category_ids = wp_list_pluck( $exclude_categories, 'term_id' );

	$args = [
		'hide_empty' => false,
		'slug'       => $search_category_slugs,
		'exclude'    => $exclude_category_ids,
	];

	$args = wp_parse_args( $args, $default_args );

	return get_terms( $args );
}

/**
 * Displays category select dropdown.
 *
 * Based on wp_dropdown_categories, with the exception of supporting multiple selected categories.
 *
 * @since 1.14.0
 * @see  wp_dropdown_categories
 * @param string|array|object $args
 * @return string
 */
function job_manager_dropdown_categories( $args = '' ) {
	$defaults = [
		'orderby'         => 'id',
		'order'           => 'ASC',
		'show_count'      => 0,
		'hide_empty'      => 1,
		'parent'          => '',
		'child_of'        => 0,
		'exclude'         => '',
		'echo'            => 1,
		'selected'        => 0,
		'hierarchical'    => 0,
		'name'            => 'cat',
		'id'              => '',
		'class'           => 'job-manager-category-dropdown ' . ( is_rtl() ? 'chosen-rtl' : '' ),
		'depth'           => 0,
		'taxonomy'        => \WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY,
		'value'           => 'id',
		'multiple'        => true,
		'show_option_all' => false,
		'placeholder'     => __( 'Choose a category&hellip;', 'wp-job-manager' ),
		'no_results_text' => __( 'No results match', 'wp-job-manager' ),
		'multiple_text'   => __( 'Select Some Options', 'wp-job-manager' ),
	];

	$r = wp_parse_args( $args, $defaults );

	if ( ! isset( $r['pad_counts'] ) && $r['show_count'] && $r['hierarchical'] ) {
		$r['pad_counts'] = true;
	}

	if ( ! isset( $r['search_category_slugs'] ) ) {
		$r['search_category_slugs'] = wpjm_get_category_slugs_from_search_query_string();
	}

	/** This filter is documented in wp-job-manager.php */
	$r['lang'] = apply_filters( 'wpjm_lang', null );

	// Store in a transient to help sites with many cats.
	$categories_hash = 'jm_cats_' . md5( wp_json_encode( $r ) . WP_Job_Manager_Cache_Helper::get_transient_version( 'jm_get_' . $r['taxonomy'] ) );
	$categories      = get_transient( $categories_hash );

	if ( empty( $categories ) ) {
		$args = [
			'taxonomy'     => $r['taxonomy'],
			'orderby'      => $r['orderby'],
			'order'        => $r['order'],
			'hide_empty'   => $r['hide_empty'],
			'parent'       => $r['parent'],
			'child_of'     => $r['child_of'],
			'exclude'      => $r['exclude'],
			'hierarchical' => $r['hierarchical'],
		];

		$categories = get_terms( $args );

		if ( ! empty( $r['search_category_slugs'] ) ) {
			$categories = array_merge(
				$categories,
				wpjm_get_categories_by_slug( $r['search_category_slugs'], $args, $categories )
			);
		}

		set_transient( $categories_hash, $categories, DAY_IN_SECONDS * 7 );
	}

	$id = $r['id'] ? $r['id'] : $r['name'];

	$output = "<select name='" . esc_attr( $r['name'] ) . "[]' id='" . esc_attr( $id ) . "' class='" . esc_attr( $r['class'] ) . "' " . ( $r['multiple'] ? "multiple='multiple'" : '' ) . " data-placeholder='" . esc_attr( $r['placeholder'] ) . "' data-no_results_text='" . esc_attr( $r['no_results_text'] ) . "' data-multiple_text='" . esc_attr( $r['multiple_text'] ) . "'>\n";

	if ( $r['show_option_all'] ) {
		$output .= '<option value="">' . esc_html( $r['show_option_all'] ) . '</option>';
	}

	if ( ! empty( $categories ) ) {
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/class-wp-job-manager-category-walker.php';

		$walker = new WP_Job_Manager_Category_Walker();

		if ( $r['hierarchical'] ) {
			$depth = $r['depth'];  // Walk the full depth.
		} else {
			$depth = -1; // Flat.
		}

		$output .= $walker->walk( $categories, $depth, $r );
	}

	$output .= "</select>\n";

	if ( $r['echo'] ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $output;
	}

	return $output;
}

/**
 * Gets the page ID of a page if set.
 *
 * @since 1.23.12
 * @param  string $page e.g. job_dashboard, submit_job_form, jobs.
 * @return int
 */
function job_manager_get_page_id( $page ) {
	$page_id = get_option( 'job_manager_' . $page . '_page_id', false );
	if ( $page_id ) {
		/**
		 * Filters the page ID for a WPJM page.
		 *
		 * @since 1.26.0
		 *
		 * @param int $page_id
		 */
		return apply_filters( 'wpjm_page_id', $page_id );
	} else {
		return 0;
	}
}

/**
 * Gets the permalink of a page if set.
 *
 * @since 1.16.0
 * @param  string $page e.g. job_dashboard, submit_job_form, jobs.
 * @return string|bool
 */
function job_manager_get_permalink( $page ) {
	$page_id = job_manager_get_page_id( $page );
	if ( $page_id ) {
		return get_permalink( $page_id );
	} else {
		return false;
	}
}

/**
 * Filters the upload dir when $job_manager_upload is true.
 *
 * @since 1.21.0
 * @param  array $pathdata
 * @return array
 */
function job_manager_upload_dir( $pathdata ) {
	global $job_manager_upload, $job_manager_uploading_file;

	if ( ! empty( $job_manager_upload ) ) {
		$dir = untrailingslashit( apply_filters( 'job_manager_upload_dir', 'job-manager-uploads/' . sanitize_key( $job_manager_uploading_file ), sanitize_key( $job_manager_uploading_file ) ) );

		if ( empty( $pathdata['subdir'] ) ) {
			$pathdata['path']   = $pathdata['path'] . '/' . $dir;
			$pathdata['url']    = $pathdata['url'] . '/' . $dir;
			$pathdata['subdir'] = '/' . $dir;
		} else {
			$new_subdir         = '/' . $dir . $pathdata['subdir'];
			$pathdata['path']   = str_replace( $pathdata['subdir'], $new_subdir, $pathdata['path'] );
			$pathdata['url']    = str_replace( $pathdata['subdir'], $new_subdir, $pathdata['url'] );
			$pathdata['subdir'] = $new_subdir;
		}
	}

	return $pathdata;
}
add_filter( 'upload_dir', 'job_manager_upload_dir' );

/**
 * Prepares files for upload by standardizing them into an array. This adds support for multiple file upload fields.
 *
 * @since 1.21.0
 * @param  array $file_data
 * @return array
 */
function job_manager_prepare_uploaded_files( $file_data ) {
	$files_to_upload = [];

	if ( is_array( $file_data['name'] ) ) {
		foreach ( $file_data['name'] as $file_data_key => $file_data_value ) {
			if ( $file_data['name'][ $file_data_key ] ) {
				$type              = wp_check_filetype( $file_data['name'][ $file_data_key ] ); // Map mime type to one WordPress recognises.
				$files_to_upload[] = [
					'name'     => $file_data['name'][ $file_data_key ],
					'type'     => $type['type'],
					'tmp_name' => $file_data['tmp_name'][ $file_data_key ],
					'error'    => $file_data['error'][ $file_data_key ],
					'size'     => $file_data['size'][ $file_data_key ],
				];
			}
		}
	} else {
		$type              = wp_check_filetype( $file_data['name'] ); // Map mime type to one WordPress recognises.
		$file_data['type'] = $type['type'];
		$files_to_upload[] = $file_data;
	}

	return apply_filters( 'job_manager_prepare_uploaded_files', $files_to_upload );
}

/**
 * Uploads a file using WordPress file API.
 *
 * @since 1.21.0
 * @param  array|WP_Error      $file Array of $_FILE data to upload.
 * @param  string|array|object $args Optional arguments.
 * @return stdClass|WP_Error Object containing file information, or error.
 */
function job_manager_upload_file( $file, $args = [] ) {
	global $job_manager_upload, $job_manager_uploading_file;

	include_once ABSPATH . 'wp-admin/includes/file.php';
	include_once ABSPATH . 'wp-admin/includes/media.php';

	$args = wp_parse_args(
		$args,
		[
			'file_key'           => '',
			'file_label'         => '',
			'allowed_mime_types' => '',
		]
	);

	$job_manager_upload         = true;
	$job_manager_uploading_file = $args['file_key'];
	$uploaded_file              = new stdClass();
	if ( '' === $args['allowed_mime_types'] ) {
		$allowed_mime_types = job_manager_get_allowed_mime_types( $job_manager_uploading_file );
	} else {
		$allowed_mime_types = $args['allowed_mime_types'];
	}

	/**
	 * Filter file configuration before upload
	 *
	 * This filter can be used to modify the file arguments before being uploaded, or return a WP_Error
	 * object to prevent the file from being uploaded, and return the error.
	 *
	 * @since 1.25.2
	 *
	 * @param array $file               Array of $_FILE data to upload.
	 * @param array $args               Optional file arguments.
	 * @param array $allowed_mime_types Array of allowed mime types from field config or defaults.
	 */
	$file = apply_filters( 'job_manager_upload_file_pre_upload', $file, $args, $allowed_mime_types );

	if ( is_wp_error( $file ) ) {
		return $file;
	}

	if ( ! in_array( $file['type'], $allowed_mime_types, true ) ) {
		// Replace pipe separating similar extensions (e.g. jpeg|jpg) to comma to match the list separator.
		$allowed_file_extensions = implode( ', ', str_replace( '|', ', ', array_keys( $allowed_mime_types ) ) );

		if ( $args['file_label'] ) {
			// translators: %1$s is the file field label; %2$s is the file type; %3$s is the list of allowed file types.
			return new WP_Error( 'upload', sprintf( __( '"%1$s" (filetype %2$s) needs to be one of the following file types: %3$s', 'wp-job-manager' ), $args['file_label'], $file['type'], $allowed_file_extensions ) );
		} else {
			// translators: %s is the list of allowed file types.
			return new WP_Error( 'upload', sprintf( __( 'Uploaded files need to be one of the following file types: %s', 'wp-job-manager' ), $allowed_file_extensions ) );
		}
	} else {
		$upload = wp_handle_upload( $file, apply_filters( 'submit_job_wp_handle_upload_overrides', [ 'test_form' => false ] ) );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'upload', $upload['error'] );
		} else {
			$uploaded_file->url       = $upload['url'];
			$uploaded_file->file      = $upload['file'];
			$uploaded_file->name      = basename( $upload['file'] );
			$uploaded_file->type      = $upload['type'];
			$uploaded_file->size      = $file['size'];
			$uploaded_file->extension = substr( strrchr( $uploaded_file->name, '.' ), 1 );
		}
	}

	$job_manager_upload         = false;
	$job_manager_uploading_file = '';

	return $uploaded_file;
}

/**
 * Returns mime types specifically for WPJM.
 *
 * @since 1.25.1
 * @param   string $field Field used.
 * @return  array  Array of allowed mime types
 */
function job_manager_get_allowed_mime_types( $field = '' ) {
	if ( 'company_logo' === $field ) {
		$allowed_mime_types = [
			'jpg|jpeg|jpe' => 'image/jpeg',
			'gif'          => 'image/gif',
			'png'          => 'image/png',
		];
	} else {
		$allowed_mime_types = [
			'jpg|jpeg|jpe' => 'image/jpeg',
			'gif'          => 'image/gif',
			'png'          => 'image/png',
			'pdf'          => 'application/pdf',
			'doc'          => 'application/msword',
			'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		];
	}

	/**
	 * Mime types to accept in uploaded files.
	 *
	 * Default is image, pdf, and doc(x) files.
	 *
	 * @since 1.25.1
	 *
	 * @param array  {
	 *     Array of allowed file extensions and mime types.
	 *     Key is pipe-separated file extensions. Value is mime type.
	 * }
	 * @param string $field The field key for the upload.
	 */
	return apply_filters( 'job_manager_mime_types', $allowed_mime_types, $field );
}

/**
 * Calculates and returns the job expiry date.
 *
 * @since 1.22.0
 * @since 1.35.0 Added the `$return_datetime` param.
 * @since 1.41.0 Added the `$from_timestamp` param.
 *
 * @param  int                    $job_id          Job ID.
 * @param  bool                   $return_datetime Return the date time object.
 * @param  DateTimeImmutable|null $from_timestamp The timestamp to calculate the expiry from.
 * @return string|DateTimeImmutable When `$return_datetime`, it will return either DateTimeImmutable or null.
 */
function calculate_job_expiry( $job_id, $return_datetime = false, $from_timestamp = null ) {
	// Get duration from the product if set...
	$duration = get_post_meta( $job_id, '_job_duration', true );

	// ...otherwise use the global option.
	if ( ! $duration ) {
		$duration = get_option( 'job_manager_submission_duration' );
	}

	if ( $duration ) {
		if ( ! $from_timestamp ) {
			$from_timestamp = current_datetime();
		}
		$new_job_expiry = $from_timestamp->add( new DateInterval( 'P' . absint( $duration ) . 'D' ) );

		return $return_datetime ? WP_Job_Manager_Post_Types::instance()->prepare_job_expires_time( $new_job_expiry ) : $new_job_expiry->format( 'Y-m-d' );
	}

	return $return_datetime ? null : '';
}

/**
 * Duplicates a listing.
 *
 * @since 1.25.0
 * @param  int $post_id
 * @return int 0 on fail or the post ID.
 */
function job_manager_duplicate_listing( $post_id ) {
	global $wpdb;

	if ( empty( $post_id ) ) {
		return 0;
	}

	$post = get_post( $post_id );
	if ( ! $post || \WP_Job_Manager_Post_Types::PT_LISTING !== $post->post_type ) {
		return 0;
	}

	/**
	 * Duplicate the post.
	 */
	$new_post_id = wp_insert_post(
		[
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $post->post_author,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_name'      => $post->post_name,
			'post_parent'    => $post->post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => 'preview',
			'post_title'     => $post->post_title,
			'post_type'      => $post->post_type,
			'to_ping'        => $post->to_ping,
			'menu_order'     => $post->menu_order,
		]
	);

	/**
	 * Copy taxonomies.
	 */
	$taxonomies = get_object_taxonomies( $post->post_type );

	foreach ( $taxonomies as $taxonomy ) {
		$post_terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'slugs' ] );
		wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
	}

	/*
	 * Duplicate post meta, aside from some reserved fields.
	 */

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Easiest way to retrieve raw meta values without filters.
	$post_meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id=%d", $post_id ) );

	if ( ! empty( $post_meta ) ) {
		$post_meta = wp_list_pluck( $post_meta, 'meta_value', 'meta_key' );

		$default_duplicate_ignore_keys = [ '_filled', '_featured', '_job_expires', '_job_duration', '_package_id', '_user_package_id', '_edit_lock', '_submitting_key', '_tracked_submitted', '_tracked_approved' ];
		$duplicate_ignore_keys         = apply_filters( 'job_manager_duplicate_listing_ignore_keys', $default_duplicate_ignore_keys, true );

		foreach ( $post_meta as $meta_key => $meta_value ) {
			$sanitized_key = preg_replace( "/[^\x20-\x7E]/", '', $meta_key );

			if ( in_array( $sanitized_key, $duplicate_ignore_keys, true ) ) {
				continue;
			}

			if ( 1 === preg_match( '/^(_wp_|_oembed_)/', $sanitized_key ) ) {
				continue;
			}

			update_post_meta( $new_post_id, wp_slash( $meta_key ), wp_slash( maybe_unserialize( $meta_value ) ) );
		}
	}

	update_post_meta( $new_post_id, '_filled', 0 );
	update_post_meta( $new_post_id, '_featured', 0 );

	return $new_post_id;
}

/**
 * Escape JSON for use on HTML or attribute text nodes.
 *
 * @since 1.32.2
 *
 * @param string $json JSON to escape.
 * @param bool   $html True if escaping for HTML text node, false for attributes. Determines how quotes are handled.
 * @return string Escaped JSON.
 */
function wpjm_esc_json( $json, $html = false ) {
	return _wp_specialchars(
		$json,
		$html ? ENT_NOQUOTES : ENT_QUOTES, // Escape quotes in attribute nodes only.
		'UTF-8',                           // json_encode() outputs UTF-8 (really just ASCII), not the blog's charset.
		true                               // Double escape entities: `&amp;` -> `&amp;amp;`.
	);
}

/**
 * Count user job listings
 *
 * @param  integer $user_id
 * @return int
 */
function job_manager_count_user_job_listings( $user_id = 0 ) {
	global $wpdb;

	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_author = %d AND post_type = 'job_listing' AND post_status IN ( 'publish', 'pending', 'expired', 'hidden' );", $user_id ) );
}

/**
 * True if an the user can browse resumes.
 *
 * @return bool
 */
function job_manager_user_can_browse_job_listings() {
	$can_browse = true;
	$caps       = get_option( 'job_manager_browse_job_listings_capability' );

	if ( $caps ) {
		$can_browse = false;
		foreach ( $caps as $cap ) {
			if ( current_user_can( $cap ) ) {
				$can_browse = true;
				break;
			}
		}
	}

	/**
	 * Filter if the current user can or cannot browse job listings
	 *
	 * @since 1.37.0
	 *
	 * @param boolean $can_browse
	 */
	return apply_filters( 'job_manager_user_can_browse_job_listings', $can_browse );
}

/**
 * True if an the user can view a resume.
 *
 * @since 1.37.0
 *
 * @param  int $job_id
 * @return bool
 */
function job_manager_user_can_view_job_listing( $job_id ) {
	$can_view = true;
	$job      = get_post( $job_id );

	// Allow previews.
	if ( 'preview' === $job->post_status ) {
		return true;
	}

	$caps = get_option( 'job_manager_view_job_listing_capability' );

	if ( $caps ) {
		$can_view = false;
		foreach ( $caps as $cap ) {
			if ( current_user_can( $cap ) ) {
				$can_view = true;
				break;
			}
		}
	}

	if ( $job->post_author > 0 && absint( $job->post_author ) === get_current_user_id() ) {
		$can_view = true;
	}

	/**
	 * Filter if the current user can or cannot view a given job
	 *
	 * @since 1.37.0
	 *
	 * @param boolean $can_view
	 * @param int     $job_id
	 */
	return apply_filters( 'job_manager_user_can_view_job', $can_view, $job_id );
}

/**
 * Return an associative array containing the options for salary units, based on Google Structured Data documentation.
 *
 * @param boolean $include_empty Defines if we should include an empty option as default.
 * @return array Where the key is the identifier used by Google Structured Data, and the value is a translated label.
 */
function job_manager_get_salary_unit_options( $include_empty = true ) {
	$options = [
		''      => __( '--', 'wp-job-manager' ),
		'YEAR'  => __( 'Year', 'wp-job-manager' ),
		'MONTH' => __( 'Month', 'wp-job-manager' ),
		'WEEK'  => __( 'Week', 'wp-job-manager' ),
		'DAY'   => __( 'Day', 'wp-job-manager' ),
		'HOUR'  => __( 'Hour', 'wp-job-manager' ),
	];
	if ( ! $include_empty ) {
		unset( $options[''] );
	}
	/**
	 * Filter the salary unit options that should appear to the user
	 *
	 * @since 1.37.0
	 * @param array $options Where the key is the identifier used by Google Structured Data, and the value is a translated label.
	 * @param boolean $include_empty Defines if we should include an empty option as default.
	 */
	return apply_filters( 'job_manager_get_salary_unit_options', $options, $include_empty );
}

/**
 * Check if user can submit job listings.
 *
 * @return bool
 * @since 1.41.0
 */
function job_manager_user_can_submit_job_listing() {
	$submission_limit = get_option( 'job_manager_submission_limit', '' );
	$job_count        = job_manager_count_user_job_listings();
	$can_submit       = '' === $submission_limit || $submission_limit >= $job_count;
	/**
	 * Filter if the current user can or cannot submit job listings
	 *
	 * @since 1.41.0
	 *
	 * @param boolean $can_submit
	 */
	return apply_filters( 'job_manager_user_can_submit_job_listing', $can_submit );
}
