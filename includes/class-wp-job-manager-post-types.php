<?php
/**
 * File containing the class WP_Job_Manager_Post_Types.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles displays and hooks for the Job Listing custom post type.
 *
 * @since 1.0.0
 */
class WP_Job_Manager_Post_Types {

	const PERMALINK_OPTION_NAME = 'job_manager_permalinks';

	/** Post Types */

	/**
	 * Constant for the post type name used for job listings.
	 */
	public const PT_LISTING = 'job_listing';

	/**
	 * Constant for the post type name used for saving guest user data.
	 */
	public const PT_GUEST_USER = 'job_guest_user';

	/** Capabilities */

	/**
	 * Constant for the capability name used for managing the job listings.
	 */
	public const CAP_MANAGE_LISTINGS = 'manage_job_listings';

	/**
	 * Constant for the capability name used for editing a job listing.
	 */
	public const CAP_EDIT_LISTING = 'edit_job_listing';

	/**
	 * Constant for the capability name used for reading job listings.
	 */
	public const CAP_READ_LISTING = 'read_job_listing';

	/**
	 * Constant for the capability name used for deleting job listings.
	 */
	public const CAP_DELETE_LISTING = 'delete_job_listing';

	/**
	 * Constant for the capability name used for editing job listings.
	 */
	public const CAP_EDIT_LISTINGS = 'edit_job_listings';

	/**
	 * Constant for the capability name used for editing others job listings.
	 */
	public const CAP_EDIT_OTHERS_LISTINGS = 'edit_others_job_listings';

	/**
	 * Constant for the capability name used for publishing job listings.
	 */
	public const CAP_PUBLISH_LISTINGS = 'publish_job_listings';

	/**
	 * Constant for the capability name used for reading private job listings.
	 */
	public const CAP_READ_PRIVATE_LISTINGS = 'read_private_job_listings';

	/**
	 * Constant for the capability name used for deleting job listings.
	 */
	public const CAP_DELETE_LISTINGS = 'delete_job_listings';

	/**
	 * Constant for the capability name used for deleting private job listings.
	 */
	public const CAP_DELETE_PRIVATE_LISTINGS = 'delete_private_job_listings';

	/**
	 * Constant for the capability name used for deleting published job listings.
	 */
	public const CAP_DELETE_PUBLISHED_LISTINGS = 'delete_published_job_listings';

	/**
	 * Constant for the capability name used for deleting others job listings.
	 */
	public const CAP_DELETE_OTHERS_LISTINGS = 'delete_others_job_listings';

	/**
	 * Constant for the capability name used for editing private job listings.
	 */
	public const CAP_EDIT_PRIVATE_LISTINGS = 'edit_private_job_listings';

	/**
	 * Constant for the capability name used for editing published job listings.
	 */
	public const CAP_EDIT_PUBLISHED_LISTINGS = 'edit_published_job_listings';

	/**
	 * Constant for the capability name used for managing job listing terms.
	 */
	public const CAP_MANAGE_LISTING_TERMS = 'manage_job_listing_terms';

	/**
	 * Constant for the capability name used for editing job listing terms.
	 */
	public const CAP_EDIT_LISTING_TERMS = 'edit_job_listing_terms';

	/**
	 * Constant for the capability name used for deleting job listing terms.
	 */
	public const CAP_DELETE_LISTING_TERMS = 'delete_job_listing_terms';

	/**
	 * Constant for the capability name used for assigning job listing terms.
	 */
	public const CAP_ASSIGN_LISTING_TERMS = 'assign_job_listing_terms';

	/**
	 * Constant for the capability name used for the post type used for saving guest user data.
	 */
	public const CAP_GUEST_USER = 'job_guest_user';

	/** Taxonomies */

	/**
	 * Constant for the job listing category taxonomy name.
	 */
	public const TAX_LISTING_CATEGORY = 'job_listing_category';

	/**
	 * Constant for the job listing type taxonomy name.
	 */
	public const TAX_LISTING_TYPE = 'job_listing_type';

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.26.0
	 */
	private static $instance = null;

	/**
	 * Job IDs that were submitted in this request.
	 *
	 * @var array
	 */
	private $submitted_job_ids = [];

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
		add_action( 'init', [ $this, 'register_post_types' ], 0 );
		add_action( 'init', [ $this, 'prepare_block_editor' ] );
		add_action( 'init', [ $this, 'register_meta_fields' ] );
		add_filter( 'admin_head', [ $this, 'admin_head' ] );
		add_action( 'job_manager_check_for_expired_jobs', [ $this, 'check_for_expired_jobs' ] );
		add_action( 'job_manager_delete_old_previews', [ $this, 'delete_old_previews' ] );

		add_action( 'transition_post_status', [ $this, 'transition_post_status' ], 10, 3 );

		add_action( 'wp_head', [ $this, 'noindex_expired_filled_job_listings' ], 0 );
		add_action( 'wp_footer', [ $this, 'output_structured_data' ] );
		add_filter( 'wp_sitemaps_posts_query_args', [ $this, 'sitemaps_maybe_hide_filled' ], 10, 2 );

		add_filter( 'the_job_description', 'wptexturize' );
		add_filter( 'the_job_description', 'convert_smilies' );
		add_filter( 'the_job_description', 'convert_chars' );
		add_filter( 'the_job_description', 'wpautop' );
		add_filter( 'the_job_description', 'shortcode_unautop' );
		add_filter( 'the_job_description', 'prepend_attachment' );
		if ( '1' === get_option( 'job_manager_strip_job_description_shortcodes' ) ) {
			add_filter( 'the_job_description', 'strip_shortcodes' );
		}
		if ( ! empty( $GLOBALS['wp_embed'] ) ) {
			add_filter( 'the_job_description', [ $GLOBALS['wp_embed'], 'run_shortcode' ], 8 );
			add_filter( 'the_job_description', [ $GLOBALS['wp_embed'], 'autoembed' ], 8 );
		}

		add_action( 'job_manager_application_details_email', [ $this, 'application_details_email' ] );
		add_action( 'job_manager_application_details_url', [ $this, 'application_details_url' ] );

		add_filter( 'wp_insert_post_data', [ $this, 'fix_post_name' ], 10, 2 );
		add_action( 'add_post_meta', [ $this, 'maybe_add_geolocation_data' ], 10, 3 );
		add_action( 'update_post_meta', [ $this, 'update_post_meta' ], 10, 4 );
		add_action( 'wp_insert_post', [ $this, 'maybe_add_default_meta_data' ], 10, 2 );
		add_filter( 'post_types_to_delete_with_user', [ $this, 'delete_user_add_job_listings_post_type' ] );

		add_action( 'transition_post_status', [ $this, 'track_job_submission' ], 10, 3 );

		add_action( 'parse_query', [ $this, 'add_feed_query_args' ] );

		// Single job content.
		$this->job_content_filter( true );
	}

	/**
	 * Prepare CPTs for special block editor situations.
	 */
	public function prepare_block_editor() {
		if ( false === job_manager_multi_job_type() ) {
			add_filter( 'rest_prepare_taxonomy', [ $this, 'hide_job_type_block_editor_selector' ], 10, 3 );
		}
	}

	/**
	 * Forces job listings to just have the classic block. This is necessary with the use of the classic editor on
	 * the frontend.
	 *
	 * @deprecated 1.35.2
	 *
	 * @param array   $allowed_block_types
	 * @param WP_Post $post
	 * @return array
	 */
	public function force_classic_block( $allowed_block_types, $post ) {
		_deprecated_function( __METHOD__, '1.35.2' );

		if ( self::PT_LISTING === $post->post_type ) {
			return [ 'core/freeform' ];
		}
		return $allowed_block_types;
	}

	/**
	 * Filters a taxonomy returned from the REST API.
	 *
	 * Allows modification of the taxonomy data right before it is returned.
	 *
	 * @param WP_REST_Response $response  The response object.
	 * @param object           $taxonomy  The original taxonomy object.
	 * @param WP_REST_Request  $request   Request used to generate the response.
	 *
	 * @return WP_REST_Response
	 */
	public function hide_job_type_block_editor_selector( $response, $taxonomy, $request ) {
		if (
			self::TAX_LISTING_TYPE === $taxonomy->name
			&& 'edit' === $request->get_param( 'context' )
		) {
			$response->data['visibility']['show_ui'] = false;
		}
		return $response;
	}

	/**
	 * Registers the custom post type and taxonomies.
	 */
	public function register_post_types() {
		if ( post_type_exists( self::PT_LISTING ) ) {
			return;
		}

		$admin_capability = self::CAP_MANAGE_LISTINGS;

		$permalink_structure = self::get_permalink_structure();

		/**
		 * Taxonomies
		 */
		if ( get_option( 'job_manager_enable_categories' ) ) {
			$singular = __( 'Job category', 'wp-job-manager' );
			$plural   = __( 'Job categories', 'wp-job-manager' );

			if ( current_theme_supports( 'job-manager-templates' ) ) {
				$rewrite = [
					'slug'         => $permalink_structure['category_rewrite_slug'],
					'with_front'   => false,
					'hierarchical' => false,
				];
				$public  = true;
			} else {
				$rewrite = false;
				$public  = false;
			}

			register_taxonomy(
				self::TAX_LISTING_CATEGORY,
				apply_filters( 'register_taxonomy_job_listing_category_object_type', [ self::PT_LISTING ] ),
				apply_filters(
					'register_taxonomy_job_listing_category_args',
					[
						'hierarchical'          => true,
						'update_count_callback' => '_update_post_term_count',
						'label'                 => $plural,
						'labels'                => [
							'name'              => $plural,
							'singular_name'     => $singular,
							'menu_name'         => __( 'Categories', 'wp-job-manager' ),
							// translators: Placeholder %s is the plural label of the job listing category taxonomy type.
							'search_items'      => sprintf( __( 'Search %s', 'wp-job-manager' ), $plural ),
							// translators: Placeholder %s is the plural label of the job listing category taxonomy type.
							'all_items'         => sprintf( __( 'All %s', 'wp-job-manager' ), $plural ),
							// translators: Placeholder %s is the singular label of the job listing category taxonomy type.
							'parent_item'       => sprintf( __( 'Parent %s', 'wp-job-manager' ), $singular ),
							// translators: Placeholder %s is the singular label of the job listing category taxonomy type.
							'parent_item_colon' => sprintf( __( 'Parent %s:', 'wp-job-manager' ), $singular ),
							// translators: Placeholder %s is the singular label of the job listing category taxonomy type.
							'edit_item'         => sprintf( __( 'Edit %s', 'wp-job-manager' ), $singular ),
							// translators: Placeholder %s is the singular label of the job listing category taxonomy type.
							'update_item'       => sprintf( __( 'Update %s', 'wp-job-manager' ), $singular ),
							// translators: Placeholder %s is the singular label of the job listing category taxonomy type.
							'add_new_item'      => sprintf( __( 'Add New %s', 'wp-job-manager' ), $singular ),
							// translators: Placeholder %s is the singular label of the job listing category taxonomy type.
							'new_item_name'     => sprintf( __( 'New %s Name', 'wp-job-manager' ), $singular ),
						],
						'show_ui'               => true,
						'show_tagcloud'         => false,
						'public'                => $public,
						'capabilities'          => [
							'manage_terms' => $admin_capability,
							'edit_terms'   => $admin_capability,
							'delete_terms' => $admin_capability,
							'assign_terms' => $admin_capability,
						],
						'rewrite'               => $rewrite,
						'show_in_rest'          => true,
						'rest_base'             => 'job-categories',

					]
				)
			);
		}

		if ( get_option( 'job_manager_enable_types' ) ) {
			$singular = __( 'Job type', 'wp-job-manager' );
			$plural   = __( 'Job types', 'wp-job-manager' );

			if ( current_theme_supports( 'job-manager-templates' ) ) {
				$rewrite = [
					'slug'         => $permalink_structure['type_rewrite_slug'],
					'with_front'   => false,
					'hierarchical' => false,
				];
				$public  = true;
			} else {
				$rewrite = false;
				$public  = false;
			}

			register_taxonomy(
				self::TAX_LISTING_TYPE,
				apply_filters( 'register_taxonomy_job_listing_type_object_type', [ self::PT_LISTING ] ),
				apply_filters(
					'register_taxonomy_job_listing_type_args',
					[
						'hierarchical'         => true,
						'label'                => $plural,
						'labels'               => [
							'name'              => $plural,
							'singular_name'     => $singular,
							'menu_name'         => __( 'Types', 'wp-job-manager' ),
							// translators: Placeholder %s is the plural label of the job listing job type taxonomy type.
							'search_items'      => sprintf( __( 'Search %s', 'wp-job-manager' ), $plural ),
							// translators: Placeholder %s is the plural label of the job listing job type taxonomy type.
							'all_items'         => sprintf( __( 'All %s', 'wp-job-manager' ), $plural ),
							// translators: Placeholder %s is the singular label of the job listing job type taxonomy type.
							'parent_item'       => sprintf( __( 'Parent %s', 'wp-job-manager' ), $singular ),
							// translators: Placeholder %s is the singular label of the job listing job type taxonomy type.
							'parent_item_colon' => sprintf( __( 'Parent %s:', 'wp-job-manager' ), $singular ),
							// translators: Placeholder %s is the singular label of the job listing job type taxonomy type.
							'edit_item'         => sprintf( __( 'Edit %s', 'wp-job-manager' ), $singular ),
							// translators: Placeholder %s is the singular label of the job listing job type taxonomy type.
							'update_item'       => sprintf( __( 'Update %s', 'wp-job-manager' ), $singular ),
							// translators: Placeholder %s is the singular label of the job listing job type taxonomy type.
							'add_new_item'      => sprintf( __( 'Add New %s', 'wp-job-manager' ), $singular ),
							// translators: Placeholder %s is the singular label of the job listing job type taxonomy type.
							'new_item_name'     => sprintf( __( 'New %s Name', 'wp-job-manager' ), $singular ),
						],
						'show_ui'              => true,
						'show_tagcloud'        => false,
						'public'               => $public,
						'capabilities'         => [
							'manage_terms' => $admin_capability,
							'edit_terms'   => $admin_capability,
							'delete_terms' => $admin_capability,
							'assign_terms' => $admin_capability,
						],
						'rewrite'              => $rewrite,
						'show_in_rest'         => true,
						'rest_base'            => 'job-types',
						'meta_box_sanitize_cb' => [ $this, 'sanitize_job_type_meta_box_input' ],
					]
				)
			);
			if ( function_exists( 'wpjm_job_listing_employment_type_enabled' ) && wpjm_job_listing_employment_type_enabled() ) {
				register_meta(
					'term',
					'employment_type',
					[
						'object_subtype'    => self::TAX_LISTING_TYPE,
						'show_in_rest'      => true,
						'type'              => 'string',
						'single'            => true,
						'description'       => esc_html__( 'Employment Type', 'wp-job-manager' ),
						'sanitize_callback' => [ $this, 'sanitize_employment_type' ],
					]
				);
			}
		}

		/**
		 * Post types
		 */
		$singular = __( 'Job', 'wp-job-manager' );
		$plural   = __( 'Jobs', 'wp-job-manager' );

		/**
		 * Set whether to add archive page support when registering the job listing post type.
		 *
		 * @since 1.30.0
		 *
		 * @param bool $enable_job_archive_page
		 */
		if ( apply_filters( 'job_manager_enable_job_archive_page', current_theme_supports( 'job-manager-templates' ) ) ) {
			$has_archive = $permalink_structure['jobs_archive_rewrite_slug'];
		} else {
			$has_archive = false;
		}

		$rewrite = [
			'slug'       => $permalink_structure['job_rewrite_slug'],
			'with_front' => false,
			'feeds'      => true,
			'pages'      => false,
		];

		register_post_type(
			self::PT_LISTING,
			apply_filters(
				'register_post_type_job_listing',
				[
					'labels'                => [
						'name'                  => $plural,
						'singular_name'         => $singular,
						'menu_name'             => esc_html__( 'Job Manager', 'wp-job-manager' ),
						// translators: Placeholder %s is the plural label of the job listing post type.
						'all_items'             => sprintf( esc_html__( 'All %s', 'wp-job-manager' ), $plural ),
						'add_new'               => esc_html__( 'Add New', 'wp-job-manager' ),
						// translators: Placeholder %s is the singular label of the job listing post type.
						'add_new_item'          => sprintf( esc_html__( 'Add %s', 'wp-job-manager' ), $singular ),
						'edit'                  => esc_html__( 'Edit', 'wp-job-manager' ),
						// translators: Placeholder %s is the singular label of the job listing post type.
						'edit_item'             => sprintf( esc_html__( 'Edit %s', 'wp-job-manager' ), $singular ),
						// translators: Placeholder %s is the singular label of the job listing post type.
						'new_item'              => sprintf( esc_html__( 'New %s', 'wp-job-manager' ), $singular ),
						// translators: Placeholder %s is the singular label of the job listing post type.
						'view'                  => sprintf( esc_html__( 'View %s', 'wp-job-manager' ), $singular ),
						// translators: Placeholder %s is the singular label of the job listing post type.
						'view_item'             => sprintf( esc_html__( 'View %s', 'wp-job-manager' ), $singular ),
						// translators: Placeholder %s is the singular label of the job listing post type.
						'search_items'          => sprintf( esc_html__( 'Search %s', 'wp-job-manager' ), $plural ),
						// translators: Placeholder %s is the singular label of the job listing post type.
						'not_found'             => sprintf( esc_html__( 'No %s found', 'wp-job-manager' ), $plural ),
						// translators: Placeholder %s is the plural label of the job listing post type.
						'not_found_in_trash'    => sprintf( esc_html__( 'No %s found in trash', 'wp-job-manager' ), $plural ),
						// translators: Placeholder %s is the singular label of the job listing post type.
						'parent'                => sprintf( esc_html__( 'Parent %s', 'wp-job-manager' ), $singular ),
						'featured_image'        => esc_html__( 'Company Logo', 'wp-job-manager' ),
						'set_featured_image'    => esc_html__( 'Set company logo', 'wp-job-manager' ),
						'remove_featured_image' => esc_html__( 'Remove company logo', 'wp-job-manager' ),
						'use_featured_image'    => esc_html__( 'Use as company logo', 'wp-job-manager' ),
					],
					// translators: Placeholder %s is the plural label of the job listing post type.
					'description'           => sprintf( esc_html__( 'This is where you can create and manage %s.', 'wp-job-manager' ), $plural ),
					'public'                => true,
					'show_ui'               => true,
					'capability_type'       => self::PT_LISTING,
					'map_meta_cap'          => true,
					'publicly_queryable'    => true,
					'exclude_from_search'   => false,
					'hierarchical'          => false,
					'rewrite'               => $rewrite,
					'query_var'             => true,
					'supports'              => [ 'title', 'editor', 'custom-fields', 'publicize', 'thumbnail', 'author' ],
					'has_archive'           => $has_archive,
					'show_in_nav_menus'     => false,
					'delete_with_user'      => true,
					'show_in_rest'          => true,
					'rest_base'             => 'job-listings',
					'rest_controller_class' => 'WP_REST_Posts_Controller',
					'template'              => [ [ 'core/freeform' ] ],
					'template_lock'         => 'all',
					'menu_position'         => 30,
				]
			)
		);

		/**
		 * Feeds
		 */
		add_feed( self::get_job_feed_name(), [ $this, 'job_feed' ] );

		/**
		 * Post status
		 */
		register_post_status(
			'expired',
			[
				'label'                     => _x( 'Expired', 'post status', 'wp-job-manager' ),
				'public'                    => true,
				'protected'                 => true,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				// translators: Placeholder %s is the number of expired posts of this type.
				'label_count'               => _n_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'wp-job-manager' ),
			]
		);
		register_post_status(
			'preview',
			[
				'label'                     => _x( 'Preview', 'post status', 'wp-job-manager' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => true,
				// translators: Placeholder %s is the number of posts in a preview state.
				'label_count'               => _n_noop( 'Preview <span class="count">(%s)</span>', 'Preview <span class="count">(%s)</span>', 'wp-job-manager' ),
			]
		);

		/**
		 * Custom post type used to store guest user data.
		 */
		register_post_type(
			self::PT_GUEST_USER,
			[
				apply_filters(
					'register_post_type_job_guest_user',
					[
						'description'         => esc_html__( 'This is where guest user data is stored.', 'wp-job-manager' ),
						'public'              => false,
						'show_ui'             => false,
						'capability_type'     => self::CAP_GUEST_USER,
						'map_meta_cap'        => false,
						'publicly_queryable'  => false,
						'exclude_from_search' => true,
						'hierarchical'        => false,
						'rewrite'             => false,
						'query_var'           => false,
						'supports'            => [ 'title', 'custom-fields' ],
						'has_archive'         => false,
						'show_in_nav_menus'   => false,
						'show_in_rest'        => false,
					]
				),
			]
		);
	}

	/**
	 * Change label for admin menu item to show number of Job Listing items pending approval.
	 */
	public function admin_head() {
		global $menu;

		$pending_jobs = WP_Job_Manager_Cache_Helper::get_listings_count();

		// No need to go further if no pending jobs, menu is not set, or is not an array.
		if ( empty( $pending_jobs ) || empty( $menu ) || ! is_array( $menu ) ) {
			return;
		}

		// Try to pull menu_name from post type object to support themes/plugins that change the menu string.
		$post_type = get_post_type_object( self::PT_LISTING );
		$plural    = isset( $post_type->labels, $post_type->labels->menu_name ) ? $post_type->labels->menu_name : esc_html__( 'Job Listings', 'wp-job-manager' );

		foreach ( $menu as $key => $menu_item ) {
			if ( strpos( $menu_item[0], $plural ) === 0 ) {
				// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Only way to add pending listing count.
				$menu[ $key ][0] .= " <span class='awaiting-mod update-plugins count-" . esc_attr( $pending_jobs ) . "'><span class='pending-count'>" . absint( number_format_i18n( $pending_jobs ) ) . '</span></span>';
				break;
			}
		}
	}

	/**
	 * Filter the post content of job listings.
	 *
	 * @since 1.33.0
	 * @param string $post_content Post content to filter.
	 */
	public static function output_kses_post( $post_content ) {
		echo wp_kses( $post_content, self::kses_allowed_html() );
	}

	/**
	 * Returns the expanded set of tags allowed in job listing content.
	 *
	 * @since 1.33.0
	 * @return string
	 */
	private static function kses_allowed_html() {
		/**
		 * Change the allowed tags in job listing content.
		 *
		 * @since 1.33.0
		 *
		 * @param array $allowed_html Tags allowed in job listing posts.
		 */
		return apply_filters(
			'job_manager_kses_allowed_html',
			array_replace_recursive( // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.array_replace_recursiveFound
				wp_kses_allowed_html( 'post' ),
				[
					'iframe' => [
						'src'             => true,
						'width'           => true,
						'height'          => true,
						'frameborder'     => true,
						'marginwidth'     => true,
						'marginheight'    => true,
						'scrolling'       => true,
						'title'           => true,
						'allow'           => true,
						'allowfullscreen' => true,
					],
				]
			)
		);
	}

	/**
	 * Sanitize job type meta box input data from WP admin.
	 *
	 * @param WP_Taxonomy $taxonomy  Taxonomy being sterilized.
	 * @param mixed       $input     Raw term data from the 'tax_input' field.
	 * @return int[]|int
	 */
	public function sanitize_job_type_meta_box_input( $taxonomy, $input ) {
		if ( is_array( $input ) ) {
			return array_map( 'intval', $input );
		}
		return intval( $input );
	}

	/**
	 * Toggles content filter on and off.
	 *
	 * @param bool $enable
	 */
	private function job_content_filter( $enable ) {
		if ( ! $enable ) {
			remove_filter( 'the_content', [ $this, 'job_content' ] );
		} else {
			add_filter( 'the_content', [ $this, 'job_content' ] );
		}
	}

	/**
	 * Adds extra content before/after the post for single job listings.
	 *
	 * @param string $content
	 * @return string
	 */
	public function job_content( $content ) {
		global $post;

		if (
			! is_singular( self::PT_LISTING ) ||
			! in_the_loop() ||
			self::PT_LISTING !== $post->post_type ||
			( post_password_required() && ! is_super_admin() )
		) {
			return $content;
		}

		ob_start();

		$this->job_content_filter( false );

		do_action( 'job_content_start' );

		get_job_manager_template_part( 'content-single', self::PT_LISTING );

		do_action( 'job_content_end' );

		$this->job_content_filter( true );

		return apply_filters( 'job_manager_single_job_content', ob_get_clean(), $post );
	}

	/**
	 * Generates the RSS feed for Job Listings.
	 */
	public function job_feed() {
		global $job_manager_keyword;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Input used to filter public data in feed.
		$input_posts_per_page  = isset( $_GET['posts_per_page'] ) ? absint( $_GET['posts_per_page'] ) : 10;
		$input_search_location = isset( $_GET['search_location'] ) ? sanitize_text_field( wp_unslash( $_GET['search_location'] ) ) : false;

		if ( isset( $_GET['job_types'] ) ) {
			$sanitized_job_types = sanitize_text_field( wp_unslash( $_GET['job_types'] ) );
			$input_job_types     = empty( $sanitized_job_types ) ? false : explode( ',', $sanitized_job_types );
		} else {
			$input_job_types = false;
		}

		if ( isset( $_GET['job_categories'] ) ) {
			$sanitized_job_categories = sanitize_text_field( wp_unslash( $_GET['job_categories'] ) );
			$input_job_categories     = empty( $sanitized_job_categories ) ? false : explode( ',', $sanitized_job_categories );
		} else {
			$input_job_categories = false;
		}

		$job_manager_keyword = isset( $_GET['search_keywords'] ) ? sanitize_text_field( wp_unslash( $_GET['search_keywords'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		$query_args = [
			'post_type'           => self::PT_LISTING,
			'post_status'         => 'publish',
			'ignore_sticky_posts' => 1,
			'posts_per_page'      => $input_posts_per_page,
			'paged'               => absint( get_query_var( 'paged', 1 ) ),
			'tax_query'           => [], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Empty.
			'meta_query'          => [], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Empty.
		];

		if ( ! empty( $input_search_location ) ) {
			$location_meta_keys = [ 'geolocation_formatted_address', '_job_location', 'geolocation_state_long' ];
			$location_search    = [ 'relation' => 'OR' ];
			$locations          = explode( ';', $input_search_location );
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
			$query_args['meta_query'][] = $location_search;
		}

		// Hide filled positions from the job feed.
		if ( 1 === absint( get_option( 'job_manager_hide_filled_positions' ) ) ) {
			$query_args['meta_query'][] = [
				'key'   => '_filled',
				'value' => '0',
			];
		}

		if ( ! empty( $input_job_types ) ) {
			$query_args['tax_query'][] = [
				'taxonomy' => self::TAX_LISTING_TYPE,
				'field'    => 'slug',
				'terms'    => $input_job_types + [ 0 ],
			];
		}

		if ( ! empty( $input_job_categories ) ) {
			$cats                      = $input_job_categories + [ 0 ];
			$field                     = is_numeric( $cats ) ? 'term_id' : 'slug';
			$operator                  = 'all' === get_option( 'job_manager_category_filter_type', 'all' ) && count( $cats ) > 1 ? 'AND' : 'IN';
			$query_args['tax_query'][] = [
				'taxonomy'         => self::TAX_LISTING_CATEGORY,
				'field'            => $field,
				'terms'            => $cats,
				'include_children' => 'AND' !== $operator,
				'operator'         => $operator,
			];
		}

		if ( ! empty( $job_manager_keyword ) ) {
			$query_args['s'] = $job_manager_keyword;
			add_filter( 'posts_search', 'get_job_listings_keyword_search', 10, 2 );
		}

		if ( empty( $query_args['meta_query'] ) ) {
			unset( $query_args['meta_query'] );
		}

		if ( empty( $query_args['tax_query'] ) ) {
			unset( $query_args['tax_query'] );
		}

		// phpcs:ignore WordPress.WP.DiscouragedFunctions
		query_posts( apply_filters( 'job_feed_args', $query_args ) );
		add_action( 'rss2_ns', [ $this, 'job_feed_namespace' ] );
		add_action( 'rss2_item', [ $this, 'job_feed_item' ] );
		do_feed_rss2( false );
		remove_filter( 'posts_search', 'get_job_listings_keyword_search', 10 );
	}

	/**
	 * Adds query arguments in order to make sure that the feed properly queries the 'job_listing' type.
	 *
	 * @param WP_Query $wp
	 */
	public function add_feed_query_args( $wp ) {

		// Let's leave if not the job feed.
		if ( ! isset( $wp->query_vars['feed'] ) || self::get_job_feed_name() !== $wp->query_vars['feed'] ) {
			return;
		}

		// Leave if not a feed.
		if ( false === $wp->is_feed ) {
			return;
		}

		// If the post_type was already set, let's get out of here.
		if ( isset( $wp->query_vars['post_type'] ) && ! empty( $wp->query_vars['post_type'] ) ) {
			return;
		}

		$wp->query_vars['post_type'] = self::PT_LISTING;
	}

	/**
	 * Adds a custom namespace to the job feed.
	 */
	public function job_feed_namespace() {
		echo 'xmlns:job_listing="' . esc_url( site_url() ) . '"' . "\n";
	}

	/**
	 * Adds custom data to the job feed.
	 */
	public function job_feed_item() {
		$post_id   = get_the_ID();
		$location  = get_the_job_location( $post_id );
		$company   = get_the_company_name( $post_id );
		$job_types = wpjm_get_the_job_types( $post_id );
		$salary    = get_the_job_salary( $post_id );

		if ( $location ) {
			echo '<job_listing:location><![CDATA[' . esc_html( $location ) . "]]></job_listing:location>\n";
		}
		if ( ! empty( $job_types ) ) {
			$job_types_names = implode( ', ', wp_list_pluck( $job_types, 'name' ) );
			echo '<job_listing:job_type><![CDATA[' . esc_html( $job_types_names ) . "]]></job_listing:job_type>\n";
		}
		if ( $company ) {
			echo '<job_listing:company><![CDATA[' . esc_html( $company ) . "]]></job_listing:company>\n";
		}
		if ( $salary ) {
			echo '<job_listing:salary><![CDATA[' . esc_html( $salary ) . "]]></job_listing:salary>\n";
		}

		/**
		 * Fires at the end of each job RSS feed item.
		 *
		 * @param int $post_id The post ID of the job.
		 */
		do_action( 'job_feed_item', $post_id );
	}

	/**
	 * Maintenance task to expire jobs.
	 */
	public function check_for_expired_jobs() {
		$expired_date_comparison = current_datetime();
		if ( ! $this->jobs_expires_end_of_day() ) {
			$expired_date_comparison = $expired_date_comparison->add( new DateInterval( 'P1D' ) );
		}

		// Change status to expired.
		$job_ids = get_posts(
			[
				'post_type'      => self::PT_LISTING,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Used in production with no issues.
					'relation' => 'AND',
					[
						'key'     => '_job_expires',
						'value'   => 0,
						'compare' => '>',
					],
					[
						'key'     => '_job_expires',
						'value'   => $expired_date_comparison->format( 'Y-m-d' ),
						'compare' => '<',
					],
				],
			]
		);

		if ( $job_ids ) {
			foreach ( $job_ids as $job_id ) {
				$job_data                = [];
				$job_data['ID']          = $job_id;
				$job_data['post_status'] = 'expired';
				wp_update_post( $job_data );
			}
		}

		// Delete old expired jobs.

		/**
		 * Set whether or not we should delete expired jobs after a certain amount of time.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $delete_expired_jobs Whether we should delete expired jobs after a certain amount of time. Defaults to false.
		 */
		if ( apply_filters( 'job_manager_delete_expired_jobs', false ) ) {
			/**
			 * Days to preserve expired job listings before deleting them.
			 *
			 * @since 1.0.0
			 *
			 * @param int $delete_expired_jobs_days Number of days to preserve expired job listings before deleting them.
			 */
			$delete_expired_jobs_days = apply_filters( 'job_manager_delete_expired_jobs_days', 30 );

			$date_cutoff = current_datetime()->sub( new DateInterval( 'P' . $delete_expired_jobs_days . 'D' ) );
			$job_ids     = get_posts(
				[
					'post_type'      => self::PT_LISTING,
					'post_status'    => 'expired',
					'fields'         => 'ids',
					'date_query'     => [
						[
							'column' => 'post_modified',
							'before' => $date_cutoff->format( 'Y-m-d' ),
						],
					],
					'posts_per_page' => -1,
				]
			);

			if ( $job_ids ) {
				foreach ( $job_ids as $job_id ) {
					wp_trash_post( $job_id );
				}
			}
		}
	}

	/**
	 * Deletes old previewed jobs after 30 days to keep the DB clean.
	 */
	public function delete_old_previews() {
		// Delete old jobs stuck in preview.
		$date_cutoff = current_datetime()->sub( new DateInterval( 'P30D' ) );
		$job_ids     = get_posts(
			[
				'post_type'      => self::PT_LISTING,
				'post_status'    => 'preview',
				'fields'         => 'ids',
				'date_query'     => [
					[
						'column' => 'post_modified',
						'before' => $date_cutoff->format( 'Y-m-d' ),
					],
				],
				'posts_per_page' => -1,
			]
		);

		if ( $job_ids ) {
			foreach ( $job_ids as $job_id ) {
				wp_delete_post( $job_id, true );
			}
		}
	}

	/**
	 * Typo wrapper for `set_expiry` method.
	 *
	 * @param WP_Post $post
	 * @since 1.0.0
	 * @deprecated 1.0.1
	 */
	public function set_expirey( $post ) {
		_deprecated_function( __METHOD__, '1.0.1', 'WP_Job_Manager_Post_Types::set_expiry' );
		$this->set_expiry( $post );
	}

	/**
	 * Handle tasks related to transition job listing post statuses.
	 *
	 * @access private
	 *
	 * @param string  $new_status The new post status.
	 * @param string  $old_status The old post status.
	 * @param WP_Post $post       The post object.
	 */
	public function transition_post_status( $new_status, $old_status, $post ) {
		if ( self::PT_LISTING !== $post->post_type ) {
			return;
		}

		$published_post_statuses = [ 'publish' ];
		$submitted_post_statuses = [ 'publish', 'pending' ];

		// If we're coming to a published post status from a non-published post status, set the expiry.
		if (
			'new' !== $old_status
			&& in_array( $new_status, $published_post_statuses, true )
			&& ! in_array( $old_status, $published_post_statuses, true )
		) {
			$this->set_expiry( $post );
		}

		// If we're transitioning to a submitted post status on a public submission, fire submitted hook.
		if (
			get_post_meta( $post->ID, '_public_submission', true )
			&& in_array( $new_status, $submitted_post_statuses, true )
			&& ! in_array( $old_status, $submitted_post_statuses, true )
		) {
			$this->submitted_job_ids[] = $post->ID;

			add_action( 'shutdown', [ $this, 'ensure_job_submission_action_triggered' ], 1 );
		}

	}

	/**
	 * Ensures jobs that are submitted on the frontend always have the job submitted action fired.
	 *
	 * @access private
	 */
	public function ensure_job_submission_action_triggered() {
		foreach ( $this->submitted_job_ids as $job_id ) {
			if ( get_post_meta( $job_id, '_public_submission', true ) ) {
				delete_post_meta( $job_id, '_public_submission' );

				/** This action is documented in includes/forms/class-wp-job-manager-form-submit-job.php */
				do_action( 'job_manager_job_submitted', $job_id );
			}
		}

		$this->submitted_job_ids = [];
	}

	/**
	 * Sets expiry date when job status changes.
	 *
	 * @param WP_Post $post
	 */
	public function set_expiry( $post ) {
		if ( self::PT_LISTING !== $post->post_type ) {
			return;
		}

		$expiration_date = $this->get_job_expiration( $post );

		// Clear the expiration field if it has expired.
		if ( $this->has_job_expired( $post ) ) {
			$this->set_job_expiration( $post, null );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce check handled by WP core.
		$input_job_expires          = isset( $_POST['_job_expires'] ) ? sanitize_text_field( wp_unslash( $_POST['_job_expires'] ) ) : null;
		$input_job_expires_datetime = ! empty( $input_job_expires ) ? DateTimeImmutable::createFromFormat( 'Y-m-d', $input_job_expires, wp_timezone() ) : null;

		// See if the user has set the expiry manually.
		if ( ! empty( $input_job_expires_datetime ) ) {
			$this->set_job_expiration( $post, $input_job_expires_datetime );
		} elseif ( ! $expiration_date ) {
			// No manual setting? Lets generate a date if there isn't already one.
			$expires = calculate_job_expiry( $post->ID, true );

			$this->set_job_expiration( $post, $expires );

			// In case we are saving a post, ensure post data is updated so the field is not overridden.
			if ( null !== $input_job_expires ) {
				$_POST['_job_expires'] = $expires ? $expires->format( 'Y-m-d' ) : '';
			}
		}
	}

	/**
	 * Set the job expiration date.
	 *
	 * @param WP_Post|int       $job          Job post object or ID.
	 * @param DateTimeImmutable $date_expires Date time object for the job expiration date. Null if to be cleared.
	 *
	 * @return false
	 * @throws TypeError When bad argument is passed to `$date_expires`.
	 */
	public function set_job_expiration( $job, $date_expires ) {
		$job = get_post( $job );

		if ( self::PT_LISTING !== $job->post_type ) {
			return false;
		}

		if ( null === $date_expires ) {
			update_post_meta( $job->ID, '_job_expires', '' );
		} elseif ( $date_expires instanceof DateTimeImmutable ) {
			update_post_meta( $job->ID, '_job_expires', $date_expires->format( 'Y-m-d' ) );
		} else {
			throw new TypeError( sprintf( 'The `$date_expires` argument passed to %s must be an instance of DateTimeImmutable or null', __METHOD__ ) );
		}
	}

	/**
	 * Get the job expiration date object.
	 *
	 * @param int|WP_Post $job Job post object or ID.
	 *
	 * @return false|DateTimeImmutable
	 */
	public function get_job_expiration( $job ) {
		$job = get_post( $job );

		if ( self::PT_LISTING !== $job->post_type ) {
			return false;
		}

		$job_expires = false;

		if ( metadata_exists( 'post', $job->ID, '_job_expires' ) ) {
			$expires_str = get_post_meta( $job->ID, '_job_expires', true );

			$job_expires = DateTimeImmutable::createFromFormat( 'Y-m-d', $expires_str, wp_timezone() );
		}

		if ( $job_expires ) {
			$job_expires = $this->prepare_job_expires_time( $job_expires );
		}

		return $job_expires;
	}

	/**
	 * Prepare a job expiration date time object by normalizing the time
	 *
	 * @param DateTimeImmutable $job_expires Job object.
	 *
	 * @return DateTimeImmutable
	 */
	public function prepare_job_expires_time( DateTimeImmutable $job_expires ) {
		if ( $this->jobs_expires_end_of_day() ) {
			return $job_expires->setTime( 23, 59, 59 );
		}

		return $job_expires->setTime( 0, 0 );
	}

	/**
	 * Check if jobs should expire at the end of the day. If not, they'll expire at the start of the day.
	 *
	 * @return bool
	 */
	private function jobs_expires_end_of_day() {
		/**
		 * Override if a job expires at the end of the day instead of start of the day.
		 *
		 * @since 1.35.0
		 *
		 * @param bool $end_of_day True if the job expires at the end of the day; otherwise it will end at the start of the day.
		 */
		return apply_filters( 'job_manager_jobs_expire_end_of_day', true );
	}

	/**
	 * Check if a job has expired.
	 *
	 * @param int|WP_Post $job Job post object or ID.
	 *
	 * @return bool
	 */
	public function has_job_expired( $job ) {
		$expiration = $this->get_job_expiration( $job );

		if ( ! $expiration ) {
			return false;
		}

		return $expiration->getTimestamp() < current_datetime()->getTimestamp();
	}

	/**
	 * Displays the application content when the application method is an email.
	 *
	 * @param stdClass $apply
	 */
	public function application_details_email( $apply ) {
		get_job_manager_template( 'job-application-email.php', [ 'apply' => $apply ] );
	}

	/**
	 * Displays the application content when the application method is a url.
	 *
	 * @param stdClass $apply
	 */
	public function application_details_url( $apply ) {
		get_job_manager_template( 'job-application-url.php', [ 'apply' => $apply ] );
	}

	/**
	 * Fixes post name when wp_update_post changes it.
	 *
	 * @param array $data
	 * @param array $postarr
	 * @return array
	 */
	public function fix_post_name( $data, $postarr ) {
		if ( self::PT_LISTING === $data['post_type']
			&& 'pending' === $data['post_status']
			&& ! current_user_can( 'publish_posts' )
			&& isset( $postarr['post_name'] )
		) {
			$data['post_name'] = $postarr['post_name'];
		}
		return $data;
	}

	/**
	 * Check if a job is editable.
	 *
	 * @since 1.34.1
	 *
	 * @param int $job_id Job ID to check.
	 * @return bool
	 */
	public static function job_is_editable( $job_id ) {
		$job_is_editable = true;
		$post_status     = get_post_status( $job_id );

		if (
			( 'publish' === $post_status && ! wpjm_user_can_edit_published_submissions() )
			|| ( 'publish' !== $post_status && ! job_manager_user_can_edit_pending_submissions() )
		) {
			$job_is_editable = false;
		}

		/**
		 * Allows filtering on whether a job can be edited after it has gone past the `preview` stage.
		 *
		 * @since 1.34.1
		 *
		 * @param bool $job_is_editable If the job is editable.
		 * @param int  $job_id          Job ID to check.
		 */
		return apply_filters( 'job_manager_job_is_editable', $job_is_editable, $job_id );
	}

	/**
	 * Returns the name of the job RSS feed.
	 *
	 * @return string
	 */
	public static function get_job_feed_name() {
		/**
		 * Change the name of the job feed.
		 *
		 * NOTE: When you override this, you must re-save permalink settings to clear the rewrite cache.
		 *
		 * @since 1.32.0
		 *
		 * @param string $job_feed_name Slug used for the job feed.
		 */
		return apply_filters( 'job_manager_job_feed_name', 'job_feed' );
	}

	/**
	 * Get the permalink settings directly from the option.
	 *
	 * @return array Permalink settings option.
	 */
	public static function get_raw_permalink_settings() {
		/**
		 * Option `wpjm_permalinks` was renamed to match other options in 1.32.0.
		 *
		 * Reference to the old option and support for non-standard plugin updates will be removed in 1.34.0.
		 */
		$legacy_permalink_settings = '[]';
		if ( false !== get_option( 'wpjm_permalinks', false ) ) {
			$legacy_permalink_settings = wp_json_encode( get_option( 'wpjm_permalinks', [] ) );
			delete_option( 'wpjm_permalinks' );
		}

		return (array) json_decode( get_option( self::PERMALINK_OPTION_NAME, $legacy_permalink_settings ), true );
	}

	/**
	 * Retrieves permalink settings.
	 *
	 * @see https://github.com/woocommerce/woocommerce/blob/3.0.8/includes/wc-core-functions.php#L1573
	 * @since 1.28.0
	 * @return array
	 */
	public static function get_permalink_structure() {
		// Switch to the site's default locale, bypassing the active user's locale.
		if ( function_exists( 'switch_to_locale' ) && did_action( 'admin_init' ) ) {
			switch_to_locale( get_locale() );
		}

		$permalink_settings = self::get_raw_permalink_settings();

		// First-time activations will get this cleared on activation.
		if ( ! array_key_exists( 'jobs_archive', $permalink_settings ) ) {
			// Create entry to prevent future checks.
			$permalink_settings['jobs_archive'] = '';
			if ( current_theme_supports( 'job-manager-templates' ) ) {
				// This isn't the first activation and the theme supports it. Set the default to legacy value.
				$permalink_settings['jobs_archive'] = _x( 'jobs', 'Post type archive slug - resave permalinks after changing this', 'wp-job-manager' );
			}
			update_option( self::PERMALINK_OPTION_NAME, wp_json_encode( $permalink_settings ) );
		}

		$permalinks = wp_parse_args(
			$permalink_settings,
			[
				'job_base'      => '',
				'category_base' => '',
				'type_base'     => '',
				'jobs_archive'  => '',
			]
		);

		// Ensure rewrite slugs are set. Use legacy translation options if not.
		$permalinks['job_rewrite_slug']          = untrailingslashit( empty( $permalinks['job_base'] ) ? _x( 'job', 'Job permalink - resave permalinks after changing this', 'wp-job-manager' ) : $permalinks['job_base'] );
		$permalinks['category_rewrite_slug']     = untrailingslashit( empty( $permalinks['category_base'] ) ? _x( 'job-category', 'Job category slug - resave permalinks after changing this', 'wp-job-manager' ) : $permalinks['category_base'] );
		$permalinks['type_rewrite_slug']         = untrailingslashit( empty( $permalinks['type_base'] ) ? _x( 'job-type', 'Job type slug - resave permalinks after changing this', 'wp-job-manager' ) : $permalinks['type_base'] );
		$permalinks['jobs_archive_rewrite_slug'] = untrailingslashit( empty( $permalinks['jobs_archive'] ) ? 'job-listings' : $permalinks['jobs_archive'] );

		// Restore the original locale.
		if ( function_exists( 'restore_current_locale' ) && did_action( 'admin_init' ) ) {
			restore_current_locale();
		}
		return $permalinks;
	}

	/**
	 * Generates location data if a post is added.
	 *
	 * @param int    $object_id
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 */
	public function maybe_add_geolocation_data( $object_id, $meta_key, $meta_value ) {
		if ( '_job_location' !== $meta_key || self::PT_LISTING !== get_post_type( $object_id ) ) {
			return;
		}
		do_action( 'job_manager_job_location_edited', $object_id, $meta_value );
	}

	/**
	 * Triggered when updating meta on a job listing.
	 *
	 * @param int    $meta_id
	 * @param int    $object_id
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 */
	public function update_post_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( self::PT_LISTING !== get_post_type( $object_id ) ) {
			return;
		}

		switch ( $meta_key ) {
			case '_job_location':
				$this->maybe_update_geolocation_data( $meta_id, $object_id, $meta_key, $meta_value );
				break;
			case '_featured':
				$this->maybe_update_menu_order( $meta_id, $object_id, $meta_key, $meta_value );
				break;
		}
	}

	/**
	 * Generates location data if a post is updated.
	 *
	 * @param int    $meta_id (Unused).
	 * @param int    $object_id
	 * @param string $meta_key (Unused).
	 * @param mixed  $meta_value
	 */
	public function maybe_update_geolocation_data( $meta_id, $object_id, $meta_key, $meta_value ) {
		do_action( 'job_manager_job_location_edited', $object_id, $meta_value );
	}

	/**
	 * Maybe sets menu_order if the featured status of a job is changed.
	 *
	 * @param int    $meta_id (Unused).
	 * @param int    $object_id
	 * @param string $meta_key (Unused).
	 * @param mixed  $meta_value
	 */
	public function maybe_update_menu_order( $meta_id, $object_id, $meta_key, $meta_value ) {
		global $wpdb;

		if ( 1 === intval( $meta_value ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Update post menu order without firing actions.
			$wpdb->update(
				$wpdb->posts,
				[ 'menu_order' => -1 ],
				[ 'ID' => $object_id ]
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Update post menu order without firing actions.
			$wpdb->update(
				$wpdb->posts,
				[ 'menu_order' => 0 ],
				[
					'ID'         => $object_id,
					'menu_order' => -1,
				]
			);
		}

		clean_post_cache( $object_id );
	}

	/**
	 * Legacy.
	 *
	 * @param int    $meta_id
	 * @param int    $object_id
	 * @param string $meta_key
	 * @param mixed  $meta_value
	 * @deprecated 1.19.1
	 */
	public function maybe_generate_geolocation_data( $meta_id, $object_id, $meta_key, $meta_value ) {
		_deprecated_function( __METHOD__, '1.19.1', 'WP_Job_Manager_Post_Types::maybe_update_geolocation_data' );
		$this->maybe_update_geolocation_data( $meta_id, $object_id, $meta_key, $meta_value );
	}

	/**
	 * Maybe sets default meta data for job listings.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function maybe_add_default_meta_data( $post_id, $post ) {
		if ( empty( $post ) || self::PT_LISTING === $post->post_type ) {
			add_post_meta( $post_id, '_filled', 0, true );
			add_post_meta( $post_id, '_featured', 0, true );
		}
	}

	/**
	 * Track job submission from the backend.
	 *
	 * @param string  $new_status  New post status.
	 * @param string  $old_status  Old status.
	 * @param WP_Post $post        Post object.
	 */
	public function track_job_submission( $new_status, $old_status, $post ) {
		if ( empty( $post ) || self::PT_LISTING !== get_post_type( $post ) ) {
			return;
		}

		if ( $new_status === $old_status || 'publish' !== $new_status ) {
			return;
		}

		// For the purpose of this event, we only care about admin requests and REST API requests.
		if ( ! is_admin() && ! WP_Job_Manager_Usage_Tracking::is_rest_request() ) {
			return;
		}

		$source = WP_Job_Manager_Usage_Tracking::is_rest_request() ? 'rest_api' : 'admin';

		if ( 'pending' === $old_status ) {
			// Track approving a new job listing.
			WP_Job_Manager_Usage_Tracking::track_job_approval(
				$post->ID,
				[
					'source' => $source,
				]
			);

			return;
		}

		WP_Job_Manager_Usage_Tracking::track_job_submission(
			$post->ID,
			[
				'source'     => $source,
				'old_status' => $old_status,
			]
		);
	}

	/**
	 * Hide filled job listings in WP core sitemaps when the `Hide filled positions` setting
	 * is enabled.
	 *
	 * @access private
	 * @since 1.34.3
	 *
	 * @param array  $query_args Array of WP_Query arguments.
	 * @param string $post_type  Post type name.
	 *
	 * @return array
	 */
	public function sitemaps_maybe_hide_filled( $query_args, $post_type ) {
		if (
			self::PT_LISTING !== $post_type
			|| 1 !== absint( get_option( 'job_manager_hide_filled_positions' ) )
		) {
			return $query_args;
		}

		if ( ! isset( $query_args['meta_query'] ) ) {
			$query_args['meta_query'] = []; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Empty.
		}

		$query_args['meta_query'][] = [
			'key'   => '_filled',
			'value' => '0',
		];

		return $query_args;
	}

	/**
	 * Add noindex for expired and filled job listings.
	 */
	public function noindex_expired_filled_job_listings() {
		if ( ! is_single() ) {
			return;
		}

		$post = get_post();
		if ( ! $post || self::PT_LISTING !== $post->post_type ) {
			return;
		}

		if ( wpjm_allow_indexing_job_listing() ) {
			return;
		}

		if ( function_exists( 'wp_robots_no_robots' ) ) {
			add_filter( 'wp_robots', 'wp_robots_no_robots' );
		} else {
			wp_no_robots();
		}
	}

	/**
	 * Add structured data to the footer of job listing pages.
	 */
	public function output_structured_data() {
		if ( ! is_single() ) {
			return;
		}

		if ( ! wpjm_output_job_listing_structured_data() ) {
			return;
		}

		$structured_data = wpjm_get_job_listing_structured_data();
		if ( ! empty( $structured_data ) ) {
			echo '<!-- WP Job Manager Structured Data -->' . "\r\n";
			echo '<script type="application/ld+json">' . wpjm_esc_json( wp_json_encode( $structured_data ), true ) . '</script>';
		}
	}

	/**
	 * Sanitize and verify employment type.
	 *
	 * @param string $employment_type
	 * @return string
	 */
	public function sanitize_employment_type( $employment_type ) {
		$employment_types = wpjm_job_listing_employment_type_options();
		if ( ! isset( $employment_types[ $employment_type ] ) ) {
			return null;
		}
		return $employment_type;
	}

	/**
	 * Registers job listing meta fields.
	 */
	public function register_meta_fields() {
		$fields = self::get_job_listing_fields();

		foreach ( $fields as $meta_key => $field ) {
			register_meta(
				'post',
				$meta_key,
				[
					'type'              => $field['data_type'],
					'show_in_rest'      => $field['show_in_rest'],
					'description'       => $field['label'],
					'sanitize_callback' => $field['sanitize_callback'],
					'auth_callback'     => $field['auth_edit_callback'],
					'single'            => true,
					'object_subtype'    => self::PT_LISTING,
				]
			);
		}
	}

	/**
	 * Returns configuration for custom fields on Job Listing posts.
	 *
	 * @return array See `job_manager_job_listing_data_fields` filter for more documentation.
	 */
	public static function get_job_listing_fields() {
		$default_field = [
			'label'              => null,
			'placeholder'        => null,
			'description'        => null,
			'priority'           => 10,
			'value'              => null,
			'default'            => null,
			'classes'            => [],
			'type'               => 'text',
			'data_type'          => 'string',
			'show_in_admin'      => true,
			'show_in_rest'       => false,
			'auth_edit_callback' => [ __CLASS__, 'auth_check_can_edit_job_listings' ],
			'auth_view_callback' => null,
			'sanitize_callback'  => [ __CLASS__, 'sanitize_meta_field_based_on_input_type' ],
		];

		$allowed_application_method     = get_option( 'job_manager_allowed_application_method', '' );
		$application_method_label       = __( 'Application email/URL', 'wp-job-manager' );
		$application_method_placeholder = __( 'Enter an email address or website URL', 'wp-job-manager' );

		if ( 'email' === $allowed_application_method ) {
			$application_method_label       = __( 'Application email', 'wp-job-manager' );
			$application_method_placeholder = __( 'you@example.com', 'wp-job-manager' );
		} elseif ( 'url' === $allowed_application_method ) {
			$application_method_label       = __( 'Application URL', 'wp-job-manager' );
			$application_method_placeholder = __( 'https://', 'wp-job-manager' );
		}

		$job_expires_description = __( 'Job listing expires at the end of the day.', 'wp-job-manager' );
		if ( ! self::instance()->jobs_expires_end_of_day() ) {
			$job_expires_description = __( 'Job listing expires at the start of the day.', 'wp-job-manager' );
		}

		$fields = [
			'_job_location'        => [
				'label'         => __( 'Location', 'wp-job-manager' ),
				'placeholder'   => __( 'e.g. "London"', 'wp-job-manager' ),
				'description'   => __( 'Leave this blank if the location is not important.', 'wp-job-manager' ),
				'priority'      => 1,
				'data_type'     => 'string',
				'show_in_admin' => true,
				'show_in_rest'  => true,
			],
			'_application'         => [
				'label'             => $application_method_label,
				'placeholder'       => $application_method_placeholder,
				'description'       => __( 'This field is required for the "application" area to appear beneath the listing.', 'wp-job-manager' ),
				'priority'          => 2,
				'data_type'         => 'string',
				'show_in_admin'     => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ __CLASS__, 'sanitize_meta_field_application' ],
			],
			'_company_name'        => [
				'label'         => __( 'Company Name', 'wp-job-manager' ),
				'placeholder'   => '',
				'priority'      => 3,
				'data_type'     => 'string',
				'show_in_admin' => true,
				'show_in_rest'  => true,
			],
			'_company_website'     => [
				'label'             => __( 'Company Website', 'wp-job-manager' ),
				'placeholder'       => '',
				'priority'          => 4,
				'data_type'         => 'string',
				'show_in_admin'     => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ __CLASS__, 'sanitize_meta_field_url' ],
			],
			'_company_tagline'     => [
				'label'         => __( 'Company Tagline', 'wp-job-manager' ),
				'placeholder'   => __( 'Brief description about the company', 'wp-job-manager' ),
				'priority'      => 5,
				'data_type'     => 'string',
				'show_in_admin' => true,
				'show_in_rest'  => true,
			],
			'_company_twitter'     => [
				'label'         => __( 'Company Twitter', 'wp-job-manager' ),
				'placeholder'   => '@yourcompany',
				'priority'      => 6,
				'data_type'     => 'string',
				'show_in_admin' => true,
				'show_in_rest'  => true,
			],
			'_company_video'       => [
				'label'             => __( 'Company Video', 'wp-job-manager' ),
				'placeholder'       => __( 'URL to the company video', 'wp-job-manager' ),
				'type'              => 'file',
				'priority'          => 8,
				'data_type'         => 'string',
				'show_in_admin'     => true,
				'show_in_rest'      => true,
				'sanitize_callback' => [ __CLASS__, 'sanitize_meta_field_url' ],
			],
			'_filled'              => [
				'label'         => __( 'Position Filled', 'wp-job-manager' ),
				'type'          => 'checkbox',
				'priority'      => 9,
				'data_type'     => 'integer',
				'show_in_admin' => true,
				'show_in_rest'  => true,
				'description'   => __( 'Filled listings will no longer accept applications.', 'wp-job-manager' ),
			],
			'_featured'            => [
				'label'              => __( 'Featured Listing', 'wp-job-manager' ),
				'type'               => 'checkbox',
				'description'        => __( 'Featured listings will be sticky during searches, and can be styled differently.', 'wp-job-manager' ),
				'priority'           => 10,
				'data_type'          => 'integer',
				'show_in_admin'      => true,
				'show_in_rest'       => true,
				'auth_edit_callback' => [ __CLASS__, 'auth_check_can_manage_job_listings' ],
			],
			'_job_expires'         => [
				'label'              => __( 'Listing Expiry Date', 'wp-job-manager' ),
				'description'        => $job_expires_description,
				'priority'           => 11,
				'show_in_admin'      => true,
				'show_in_rest'       => true,
				'data_type'          => 'string',
				'classes'            => [ 'job-manager-datepicker' ],
				'auth_edit_callback' => [ __CLASS__, 'auth_check_can_manage_job_listings' ],
				'auth_view_callback' => [ __CLASS__, 'auth_check_can_edit_job_listings' ],
				'sanitize_callback'  => [ __CLASS__, 'sanitize_meta_field_date' ],
			],
			'_remote_position'     => [
				'label'         => __( 'Remote Position', 'wp-job-manager' ),
				'description'   => __( 'Select if this is a remote position.', 'wp-job-manager' ),
				'type'          => 'checkbox',
				'priority'      => 12,
				'data_type'     => 'integer',
				'show_in_admin' => (bool) get_option( 'job_manager_enable_remote_position' ),
				'show_in_rest'  => true,
			],
			'_job_salary'          => [
				'label'         => __( 'Salary', 'wp-job-manager' ),
				'type'          => 'text',
				'placeholder'   => __( 'e.g. 20000', 'wp-job-manager' ),
				'priority'      => 13,
				'description'   => __( 'Add a salary field, this field is optional.', 'wp-job-manager' ),
				'data_type'     => 'string',
				'show_in_admin' => (bool) get_option( 'job_manager_enable_salary' ),
				'show_in_rest'  => true,
			],
			'_job_salary_currency' => [
				'label'         => __( 'Salary Currency', 'wp-job-manager' ),
				'type'          => 'text',
				'data_type'     => 'string',
				'placeholder'   => __( 'e.g. USD', 'wp-job-manager' ),
				'priority'      => 14,
				'description'   => __( 'Add a salary currency, this field is optional. Leave it empty to use the default salary currency.', 'wp-job-manager' ),
				'default'       => '',
				'show_in_admin' => get_option( 'job_manager_enable_salary' ) && get_option( 'job_manager_enable_salary_currency' ),
				'show_in_rest'  => true,
			],
			'_job_salary_unit'     => [
				'label'         => __( 'Salary Unit', 'wp-job-manager' ),
				'type'          => 'select',
				'data_type'     => 'string',
				'options'       => job_manager_get_salary_unit_options(),
				'priority'      => 15,
				'description'   => __( 'Add a salary period unit, this field is optional. Leave it empty to use the default salary unit, if one is defined.', 'wp-job-manager' ),
				'default'       => '',
				'show_in_admin' => get_option( 'job_manager_enable_salary' ) && get_option( 'job_manager_enable_salary_unit' ),
				'show_in_rest'  => true,
			],
		];

		/**
		 * Filters job listing data fields.
		 *
		 * For the REST API, do not pass fields you don't want to be visible to the current visitor when `show_in_rest`
		 * is `true`. To add values and other data when generating the WP admin form, use filter
		 * `job_manager_job_listing_wp_admin_fields` which should have `$post_id` in context.
		 *
		 * @since 1.0.0
		 * @since 1.27.0 $post_id was added.
		 * @since 1.33.0 Used both in WP admin and REST API. Removed `$post_id` attribute. Added fields for REST API.
		 *
		 * @param array    $fields  {
		 *     Job listing meta fields for REST API and WP admin. Associative array with meta key as the index.
		 *     All fields except for `$label` are optional and have working defaults.
		 *
		 *     @type array $meta_key {
		 *         @type string        $label              Label to show for field. Used in: WP Admin; REST API.
		 *         @type string        $placeholder        Placeholder to show in empty form fields. Used in: WP Admin.
		 *         @type string        $description        Longer description to shown below form field.
		 *                                                 Used in: WP Admin.
		 *         @type array         $classes            Classes to apply to form input field. Used in: WP Admin.
		 *         @type int           $priority           Field placement priority for WP admin. Lower is first.
		 *                                                 Used in: WP Admin (Default: 10).
		 *         @type string        $value              Override standard retrieval of meta value in form field.
		 *                                                 Used in: WP Admin.
		 *         @type string        $default            Default value on form field if no other value is set for
		 *                                                 field. Used in: WP Admin (Since 1.33.0).
		 *         @type string        $type               Type of form field to render. Used in: WP Admin
		 *                                                 (Default: 'text').
		 *         @type string        $data_type          Data type to cast to. Options: 'string', 'boolean',
		 *                                                 'integer', 'number'.  Used in: REST API. (Since 1.33.0;
		 *                                                 Default: 'string').
		 *         @type bool|callable $show_in_admin      Whether field should be displayed in WP admin. Can be
		 *                                                 callable that returns boolean. Used in: WP Admin
		 *                                                 (Since 1.33.0; Default: true).
		 *         @type bool|array    $show_in_rest       Whether data associated with this meta key can put in REST
		 *                                                 API response for job listings. Can be used to pass REST API
		 *                                                 arguments in `show_in_rest` parameter. Used in: REST API
		 *                                                 (Since 1.33.0; Default: false).
		 *         @type callable      $auth_edit_callback {
		 *             Decides if specific user can edit the meta key. Used in: WP Admin; REST API.
		 *             Defaults to callable that limits to those who can edit specific the job listing (also limited
		 *             by relevant endpoints).
		 *
		 *             @see WP core filter `auth_{$object_type}_meta_{$meta_key}_for_{$object_subtype}`.
		 *             @since 1.33.0
		 *
		 *             @param bool   $allowed   Whether the user can add the object meta. Default false.
		 *             @param string $meta_key  The meta key.
		 *             @param int    $object_id Post ID for Job Listing.
		 *             @param int    $user_id   User ID.
		 *
		 *             @return bool
		 *         }
		 *         @type callable      $auth_view_callback {
		 *             Decides if specific user can view value of the meta key. Used in: REST API.
		 *             Defaults to visible to all (if shown in REST API, which by default is false).
		 *
		 *             @see WPJM method `WP_Job_Manager_REST_API::prepare_job_listing()`.
		 *             @since 1.33.0
		 *
		 *             @param bool   $allowed   Whether the user can add the object meta. Default false.
		 *             @param string $meta_key  The meta key.
		 *             @param int    $object_id Post ID for Job Listing.
		 *             @param int    $user_id   User ID.
		 *
		 *             @return bool
		 *         }
		 *         @type callable      $sanitize_callback  {
		 *             Sanitizes the meta value before saving to database. Used in: WP Admin; REST API; Frontend.
		 *             Defaults to callable that sanitizes based on the field type.
		 *
		 *             @see WP core filter `auth_{$object_type}_meta_{$meta_key}_for_{$object_subtype}`
		 *             @since 1.33.0
		 *
		 *             @param mixed  $meta_value Value of meta field that needs sanitization.
		 *             @param string $meta_key   Meta key that is being sanitized.
		 *
		 *             @return mixed
		 *         }
		 *     }
		 * }
		 */
		$fields = apply_filters( 'job_manager_job_listing_data_fields', $fields );

		// Ensure default fields are set.
		foreach ( $fields as $key => $field ) {
			$fields[ $key ] = array_merge( $default_field, $field );
		}

		return $fields;
	}

	/**
	 * Sanitize meta fields based on input type.
	 *
	 * @param mixed  $meta_value Value of meta field that needs sanitization.
	 * @param string $meta_key   Meta key that is being sanitized.
	 * @return mixed
	 */
	public static function sanitize_meta_field_based_on_input_type( $meta_value, $meta_key ) {
		$fields = self::get_job_listing_fields();

		if ( is_string( $meta_value ) ) {
			$meta_value = trim( $meta_value );
		}

		$type = 'text';
		if ( isset( $fields[ $meta_key ] ) ) {
			$type = $fields[ $meta_key ]['type'];
		}

		if ( 'textarea' === $type || 'wp_editor' === $type ) {
			return wp_kses_post( wp_unslash( $meta_value ) );
		}

		if ( 'checkbox' === $type ) {
			if ( $meta_value && '0' !== $meta_value ) {
				return 1;
			}

			return 0;
		}

		if ( is_array( $meta_value ) ) {
			return array_filter( array_map( 'sanitize_text_field', $meta_value ) );
		}

		return sanitize_text_field( $meta_value );
	}

	/**
	 * Sanitize `_application` meta field.
	 *
	 * @param string $meta_value Value of meta field that needs sanitization.
	 * @return string
	 */
	public static function sanitize_meta_field_application( $meta_value ) {
		if ( is_email( $meta_value ) ) {
			return sanitize_email( $meta_value );
		}

		return self::sanitize_meta_field_url( $meta_value );
	}

	/**
	 * Sanitize URL meta fields.
	 *
	 * @param string $meta_value Value of meta field that needs sanitization.
	 * @return string
	 */
	public static function sanitize_meta_field_url( $meta_value ) {
		$meta_value = trim( $meta_value );
		if ( '' === $meta_value ) {
			return $meta_value;
		}

		return esc_url_raw( $meta_value );
	}

	/**
	 * Sanitize date meta fields.
	 *
	 * @param string $meta_value Value of meta field that needs sanitization.
	 * @return string
	 */
	public static function sanitize_meta_field_date( $meta_value ) {
		$meta_value = trim( $meta_value );

		// Matches yyyy-mm-dd.
		if ( ! preg_match( '/[\d]{4}\-[\d]{2}\-[\d]{2}/', $meta_value ) ) {
			return '';
		}

		// Checks for valid date.
		$test_date = DateTimeImmutable::createFromFormat( 'Y-m-d', $meta_value, wp_timezone() );
		if ( ! $test_date || $test_date->format( 'Y-m-d' ) !== $meta_value ) {
			return '';
		}

		return $meta_value;
	}

	/**
	 * Checks if user can manage job listings.
	 *
	 * @param bool   $allowed   Whether the user can edit the job listing meta.
	 * @param string $meta_key  The meta key.
	 * @param int    $post_id   Job listing's post ID.
	 * @param int    $user_id   User ID.
	 *
	 * @return bool Whether the user can edit the job listing meta.
	 */
	public static function auth_check_can_manage_job_listings( $allowed, $meta_key, $post_id, $user_id ) {
		$user = get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			return false;
		}

		return $user->has_cap( self::CAP_MANAGE_LISTINGS );
	}

	/**
	 * Checks if user can edit job listings.
	 *
	 * @param bool   $allowed   Whether the user can edit the job listing meta.
	 * @param string $meta_key  The meta key.
	 * @param int    $post_id   Job listing's post ID.
	 * @param int    $user_id   User ID.
	 *
	 * @return bool Whether the user can edit the job listing meta.
	 */
	public static function auth_check_can_edit_job_listings( $allowed, $meta_key, $post_id, $user_id ) {
		$user = get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			return false;
		}

		if ( empty( $post_id ) ) {
			return current_user_can( self::CAP_EDIT_LISTINGS );
		}

		return job_manager_user_can_edit_job( $post_id );
	}

	/**
	 * Checks if user can edit other's job listings.
	 *
	 * @param bool   $allowed   Whether the user can edit the job listing meta.
	 * @param string $meta_key  The meta key.
	 * @param int    $post_id   Job listing's post ID.
	 * @param int    $user_id   User ID.
	 *
	 * @return bool Whether the user can edit the job listing meta.
	 */
	public static function auth_check_can_edit_others_job_listings( $allowed, $meta_key, $post_id, $user_id ) {
		$user = get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			return false;
		}

		return $user->has_cap( self::CAP_EDIT_OTHERS_LISTINGS );
	}

	/**
	 * Add post type for Job Manager to list of post types deleted with user.
	 *
	 * @since 1.33.0
	 *
	 * @param array $types
	 * @return array
	 */
	public function delete_user_add_job_listings_post_type( $types ) {
		$types[] = self::PT_LISTING;

		return $types;
	}
}
