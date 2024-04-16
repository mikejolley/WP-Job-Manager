<?php
/**
 * File containing the class WP_Job_Manager_Data_Cleaner.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Methods for cleaning up all plugin data.
 *
 * @author Automattic
 * @since 1.31.0
 */
class WP_Job_Manager_Data_Cleaner {

	/**
	 * Custom post types to be deleted.
	 *
	 * @var array
	 */
	private const CUSTOM_POST_TYPES = [
		\WP_Job_Manager_Post_Types::PT_LISTING,
		\WP_Job_Manager_Post_Types::PT_GUEST_USER,
	];

	/**
	 * Custom tables to be deleted.
	 *
	 * @var array
	 */
	private const CUSTOM_TABLES = [
		'wpjm_stats',
	];

	/**
	 * Taxonomies to be deleted.
	 *
	 * @var array
	 */
	private const TAXONOMIES = [
		\WP_Job_Manager_Post_Types::TAX_LISTING_CATEGORY,
		\WP_Job_Manager_Post_Types::TAX_LISTING_TYPE,
	];

	/** Cron jobs to be unscheduled.
	 *
	 * @var array
	 */
	private const CRON_JOBS = [
		'job_manager_check_for_expired_jobs',
		'job_manager_delete_old_previews',
		'job_manager_email_daily_notices',
		'job_manager_usage_tracking_send_usage_data',

		// Old cron jobs.
		'job_manager_clear_expired_transients',
	];

	/**
	 * Options to be deleted.
	 *
	 * @var array
	 */
	private const OPTIONS = [
		'wp_job_manager_version',
		'job_manager_installed_terms',
		'wpjm_permalinks',
		'job_manager_permalinks',
		'job_manager_helper',
		'job_manager_date_format',
		'job_manager_google_maps_api_key',
		'job_manager_usage_tracking_enabled',
		'job_manager_usage_tracking_opt_in_hide',
		'job_manager_per_page',
		'job_manager_hide_filled_positions',
		'job_manager_hide_expired',
		'job_manager_hide_expired_content',
		'job_manager_enable_categories',
		'job_manager_enable_default_category_multiselect',
		'job_manager_category_filter_type',
		'job_manager_enable_types',
		'job_manager_multi_job_type',
		'job_manager_user_requires_account',
		'job_manager_enable_registration',
		'job_manager_generate_username_from_email',
		'job_manager_use_standard_password_setup_email',
		'job_manager_registration_role',
		'job_manager_submission_requires_approval',
		'job_manager_renewal_days',
		'job_manager_show_agreement_job_submission',
		'job_manager_terms_and_conditions_page_id',
		'job_manager_user_can_edit_pending_submissions',
		'job_manager_user_edit_published_submissions',
		'job_manager_submission_duration',
		'job_manager_allowed_application_method',
		'job_manager_recaptcha_label',
		'job_manager_recaptcha_site_key',
		'job_manager_recaptcha_secret_key',
		'job_manager_enable_recaptcha_job_submission',
		'job_manager_submit_job_form_page_id',
		'job_manager_job_dashboard_page_id',
		'job_manager_jobs_page_id',
		'job_manager_submit_page_slug',
		'job_manager_job_dashboard_page_slug',
		'job_manager_delete_data_on_uninstall',
		'job_manager_email_admin_updated_job',
		'job_manager_email_admin_new_job',
		'job_manager_email_admin_expiring_job',
		'job_manager_email_employer_expiring_job',
		'job_manager_admin_notices',
		'job_manager_bypass_trash_on_uninstall',
		'widget_widget_featured_jobs',
		'widget_widget_recent_jobs',
		'job_manager_job_listing_pagination_type',
		'job_manager_enable_salary',
		'job_manager_enable_salary_unit',
		'job_manager_default_salary_unit',
		'job_manager_enable_salary_currency',
		'job_manager_default_salary_currency',
		'job_manager_promoted_jobs_status_update_last_check',
		'job_manager_promoted_jobs_webhook_interval',
		'job_manager_promoted_jobs_cron_interval',
		'job_manager_display_usage_tracking_once',
	];

	/**
	 * Site options to be deleted.
	 *
	 * @var array
	 */
	private const SITE_OPTIONS = [
		'job_manager_helper',
	];

	/**
	 * Transient names (as MySQL regexes) to be deleted. The prefixes
	 * "_transient_" and "_transient_timeout_" will be prepended.
	 *
	 * @var array
	 */
	private const TRANSIENTS = [
		'_job_manager_activation_redirect', // Legacy transient that should still be removed.
		'get_job_listings-transient-version',
		'jm_.*',
		'wpjm_.*',
	];

	/**
	 * Role to be removed.
	 *
	 * @var array
	 */
	private const ROLE = 'employer';

	/**
	 * Capabilities to be deleted.
	 *
	 * @var array
	 */
	private const CAPS = [
		\WP_Job_Manager_Post_Types::CAP_MANAGE_LISTINGS,
		\WP_Job_Manager_Post_Types::CAP_EDIT_LISTING,
		\WP_Job_Manager_Post_Types::CAP_READ_LISTING,
		\WP_Job_Manager_Post_Types::CAP_DELETE_LISTING,
		\WP_Job_Manager_Post_Types::CAP_EDIT_LISTINGS,
		\WP_Job_Manager_Post_Types::CAP_EDIT_OTHERS_LISTINGS,
		\WP_Job_Manager_Post_Types::CAP_PUBLISH_LISTINGS,
		\WP_Job_Manager_Post_Types::CAP_READ_PRIVATE_LISTINGS,
		\WP_Job_Manager_Post_Types::CAP_DELETE_LISTINGS,
		\WP_Job_Manager_Post_Types::CAP_DELETE_PRIVATE_LISTINGS,
		\WP_Job_Manager_Post_Types::CAP_DELETE_PUBLISHED_LISTINGS,
		\WP_Job_Manager_Post_Types::CAP_DELETE_OTHERS_LISTINGS,
		\WP_Job_Manager_Post_Types::CAP_EDIT_PRIVATE_LISTINGS,
		\WP_Job_Manager_Post_Types::CAP_EDIT_PUBLISHED_LISTINGS,
		\WP_Job_Manager_Post_Types::CAP_MANAGE_LISTING_TERMS,
		\WP_Job_Manager_Post_Types::CAP_EDIT_LISTING_TERMS,
		\WP_Job_Manager_Post_Types::CAP_DELETE_LISTING_TERMS,
		\WP_Job_Manager_Post_Types::CAP_ASSIGN_LISTING_TERMS,
	];

	/**
	 * User meta key names to be deleted.
	 *
	 * @var array
	 */
	private const USER_META_KEYS = [
		'_company_logo',
		'_company_name',
		'_company_website',
		'_company_tagline',
		'_company_twitter',
		'_company_video',
	];

	/**
	 * Cleanup all data.
	 *
	 * @access public
	 */
	public static function cleanup_all() {
		self::cleanup_custom_post_types();
		self::cleanup_custom_tables();
		self::cleanup_taxonomies();
		self::cleanup_pages();
		self::cleanup_cron_jobs();
		self::cleanup_roles_and_caps();
		self::cleanup_transients();
		self::cleanup_user_meta();
		self::cleanup_options();
		self::cleanup_site_options();
	}

	/**
	 * Cleanup data for custom post types.
	 *
	 * @access private
	 */
	private static function cleanup_custom_post_types() {
		foreach ( self::CUSTOM_POST_TYPES as $post_type ) {
			$items = get_posts(
				[
					'post_type'   => $post_type,
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				]
			);

			foreach ( $items as $item ) {
				if ( ! get_option( 'job_manager_bypass_trash_on_uninstall' ) ) {
					wp_trash_post( $item );
				} else {
					wp_delete_post( $item, true );
				}
			}
		}
	}

	/**
	 * Cleanup data for custom tables.
	 *
	 * @return void
	 */
	private static function cleanup_custom_tables() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- We need to delete the custom tables.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- We don't cache DROP TABLE.
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnsupportedPlaceholder -- %i is supported since WP 6.2.
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- %i is supported since WP 6.2.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange -- We really need to delete the custom tables.
		foreach ( self::CUSTOM_TABLES as $custom_table ) {
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $custom_table ) );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnsupportedPlaceholder
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	/**
	 * Cleanup data for taxonomies.
	 *
	 * @access private
	 */
	private static function cleanup_taxonomies() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( self::TAXONOMIES as $taxonomy ) {
			$terms = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT term_id, term_taxonomy_id FROM $wpdb->term_taxonomy WHERE taxonomy = %s",
					$taxonomy
				)
			);

			// Delete all data for each term.
			foreach ( $terms as $term ) {
				$wpdb->delete( $wpdb->term_relationships, [ 'term_taxonomy_id' => $term->term_taxonomy_id ] );
				$wpdb->delete( $wpdb->term_taxonomy, [ 'term_taxonomy_id' => $term->term_taxonomy_id ] );
				$wpdb->delete( $wpdb->terms, [ 'term_id' => $term->term_id ] );
				$wpdb->delete( $wpdb->termmeta, [ 'term_id' => $term->term_id ] );
			}

			if ( function_exists( 'clean_taxonomy_cache' ) ) {
				clean_taxonomy_cache( $taxonomy );
			}
		}

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Cleanup data for pages.
	 *
	 * @access private
	 */
	private static function cleanup_pages() {
		// Trash the Submit Job page.
		$submit_job_form_page_id = get_option( 'job_manager_submit_job_form_page_id' );
		if ( $submit_job_form_page_id ) {
			wp_trash_post( $submit_job_form_page_id );
		}

		// Trash the Job Dashboard page.
		$job_dashboard_page_id = get_option( 'job_manager_job_dashboard_page_id' );
		if ( $job_dashboard_page_id ) {
			wp_trash_post( $job_dashboard_page_id );
		}

		// Trash the Jobs page.
		$jobs_page_id = get_option( 'job_manager_jobs_page_id' );
		if ( $jobs_page_id ) {
			wp_trash_post( $jobs_page_id );
		}
	}

	/**
	 * Cleanup data for options.
	 *
	 * @access private
	 */
	private static function cleanup_options() {
		foreach ( self::OPTIONS as $option ) {
			delete_option( $option );
		}
	}

	/**
	 * Cleanup data for site options.
	 *
	 * @access private
	 */
	private static function cleanup_site_options() {
		foreach ( self::SITE_OPTIONS as $option ) {
			delete_site_option( $option );
		}
	}

	/**
	 * Cleanup transients from the database.
	 *
	 * @access private
	 */
	private static function cleanup_transients() {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		foreach ( [ '_transient_', '_transient_timeout_' ] as $prefix ) {
			foreach ( self::TRANSIENTS as $transient ) {
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM $wpdb->options WHERE option_name RLIKE %s",
						$prefix . $transient
					)
				);
			}
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Cleanup data for roles and caps.
	 *
	 * @access private
	 */
	private static function cleanup_roles_and_caps() {
		global $wp_roles;

		// Remove caps from roles.
		$role_names = array_keys( $wp_roles->roles );
		foreach ( $role_names as $role_name ) {
			$role = get_role( $role_name );
			self::remove_all_job_manager_caps( $role );
		}

		// Remove caps and role from users.
		$users = get_users( [] );
		foreach ( $users as $user ) {
			self::remove_all_job_manager_caps( $user );
			$user->remove_role( self::ROLE );
		}

		// Remove role.
		remove_role( self::ROLE );
	}

	/**
	 * Helper method to remove WPJM caps from a user or role object.
	 *
	 * @param (WP_User|WP_Role) $object the user or role object.
	 */
	private static function remove_all_job_manager_caps( $object ) {
		foreach ( self::CAPS as $cap ) {
			$object->remove_cap( $cap );
		}
	}

	/**
	 * Cleanup user meta from the database.
	 *
	 * @access private
	 */
	private static function cleanup_user_meta() {
		global $wpdb;

		foreach ( self::USER_META_KEYS as $meta_key ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Delete data across all users.
			$wpdb->delete( $wpdb->usermeta, [ 'meta_key' => $meta_key ] );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		}
	}

	/**
	 * Cleanup cron jobs. Note that this should be done on deactivation, but
	 * doing it here as well for safety.
	 *
	 * @access private
	 */
	private static function cleanup_cron_jobs() {
		foreach ( self::CRON_JOBS as $job ) {
			wp_clear_scheduled_hook( $job );
		}
	}
}
