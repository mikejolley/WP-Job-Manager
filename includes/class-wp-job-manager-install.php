<?php
/**
 * File containing the class WP_Job_Manager_Install.
 *
 * @package wp-job-manager
 */

use WP_Job_Manager\Admin\Release_Notice;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the installation of the WP Job Manager plugin.
 *
 * @since 1.0.0
 */
class WP_Job_Manager_Install {

	/**
	 * Installs WP Job Manager.
	 */
	public static function install() {
		global $wpdb;

		self::init_user_roles();
		self::default_terms();

		$is_new_install = false;

		// Fresh installs should be prompted to set up their instance.
		if ( ! get_option( 'wp_job_manager_version' ) ) {
			include_once JOB_MANAGER_PLUGIN_DIR . '/includes/admin/class-wp-job-manager-admin-notices.php';
			WP_Job_Manager_Admin_Notices::add_notice( WP_Job_Manager_Admin_Notices::NOTICE_CORE_SETUP );
			$is_new_install = true;
		}

		require_once __DIR__ . '/../lib/usage-tracking/class-wp-job-manager-usage-tracking-base.php';

		// On new installs display the usage tracking notice with one week delay and for existing installs display it right away.
		if ( false === get_option( WP_Job_Manager_Usage_Tracking_Base::DISPLAY_ONCE_OPTION ) ) {
			$time_to_show_notice = $is_new_install ? time() + WEEK_IN_SECONDS : time() - 10;
			update_option( WP_Job_Manager_Usage_Tracking_Base::DISPLAY_ONCE_OPTION, $time_to_show_notice );
		}

		// Update featured posts ordering.
		if ( version_compare( get_option( 'wp_job_manager_version', JOB_MANAGER_VERSION ), '1.22.0', '<' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One time data update.
			$wpdb->query( "UPDATE {$wpdb->posts} p SET p.menu_order = 0 WHERE p.post_type='job_listing';" );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One time data update.
			$wpdb->query( "UPDATE {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id SET p.menu_order = -1 WHERE pm.meta_key = '_featured' AND pm.meta_value='1' AND p.post_type='job_listing';" );
		}

		// Update default term meta with employment types.
		if ( version_compare( get_option( 'wp_job_manager_version', JOB_MANAGER_VERSION ), '1.28.0', '<' ) ) {
			self::add_employment_types();
		}

		// Update legacy options.
		if ( false === get_option( 'job_manager_submit_job_form_page_id', false ) && get_option( 'job_manager_submit_page_slug' ) ) {
			$page_id = get_page_by_path( get_option( 'job_manager_submit_page_slug' ) )->ID;
			update_option( 'job_manager_submit_job_form_page_id', $page_id );
		}
		if ( false === get_option( 'job_manager_job_dashboard_page_id', false ) && get_option( 'job_manager_job_dashboard_page_slug' ) ) {
			$page_id = get_page_by_path( get_option( 'job_manager_job_dashboard_page_slug' ) )->ID;
			update_option( 'job_manager_job_dashboard_page_id', $page_id );
		}

		// Scheduled hook was removed in 1.33.4.
		if ( wp_next_scheduled( 'job_manager_clear_expired_transients' ) ) {
			wp_clear_scheduled_hook( 'job_manager_clear_expired_transients' );
		}

		if ( $is_new_install ) {
			$permalink_options                 = (array) json_decode( get_option( 'job_manager_permalinks', '[]' ), true );
			$permalink_options['jobs_archive'] = '';
			update_option( 'job_manager_permalinks', wp_json_encode( $permalink_options ) );

			update_option( \WP_Job_Manager\Stats::OPTION_ENABLE_STATS, true );
			\WP_Job_Manager_Admin_Notices::dismiss_notice( Release_Notice::NOTICE_ID );
		}

		\WP_Job_Manager\Stats::instance()->migrate_db();

		delete_transient( 'wp_job_manager_addons_html' );
		update_option( 'wp_job_manager_version', JOB_MANAGER_VERSION );
	}

	/**
	 * Initializes user roles.
	 */
	private static function init_user_roles() {
		$roles = wp_roles();

		if ( is_object( $roles ) ) {
			add_role(
				'employer',
				__( 'Employer', 'wp-job-manager' ),
				[
					'read'         => true,
					'edit_posts'   => false,
					'delete_posts' => false,
				]
			);

			$capabilities = self::get_core_capabilities();

			foreach ( $capabilities as $cap_group ) {
				foreach ( $cap_group as $cap ) {
					$roles->add_cap( 'administrator', $cap );
				}
			}
		}
	}

	/**
	 * Returns capabilities.
	 *
	 * @return array
	 */
	private static function get_core_capabilities() {
		return [
			'core'                                 => [
				\WP_Job_Manager_Post_Types::CAP_MANAGE_LISTINGS,
			],
			\WP_Job_Manager_Post_Types::PT_LISTING => [
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
			],
		];
	}

	/**
	 * Sets up the default WP Job Manager terms.
	 */
	private static function default_terms() {
		if ( 1 === intval( get_option( 'job_manager_installed_terms' ) ) ) {
			return;
		}

		$taxonomies = self::get_default_taxonomy_terms();
		foreach ( $taxonomies as $taxonomy => $terms ) {
			foreach ( $terms as $term => $meta ) {
				if ( ! get_term_by( 'slug', sanitize_title( $term ), $taxonomy ) ) {
					$tt_package = wp_insert_term( $term, $taxonomy );
					if ( is_array( $tt_package ) && isset( $tt_package['term_id'] ) && ! empty( $meta ) ) {
						foreach ( $meta as $meta_key => $meta_value ) {
							add_term_meta( $tt_package['term_id'], $meta_key, $meta_value );
						}
					}
				}
			}
		}

		update_option( 'job_manager_installed_terms', 1 );
	}

	/**
	 * Default taxonomy terms to set up in WP Job Manager.
	 *
	 * @return array Default taxonomy terms.
	 */
	private static function get_default_taxonomy_terms() {
		return [
			\WP_Job_Manager_Post_Types::TAX_LISTING_TYPE => [
				'Full Time'  => [
					'employment_type' => 'FULL_TIME',
				],
				'Part Time'  => [
					'employment_type' => 'PART_TIME',
				],
				'Temporary'  => [
					'employment_type' => 'TEMPORARY',
				],
				'Freelance'  => [
					'employment_type' => 'CONTRACTOR',
				],
				'Internship' => [
					'employment_type' => 'INTERN',
				],
			],
		];
	}

	/**
	 * Adds the employment type to default job types when updating from a previous WP Job Manager version.
	 */
	private static function add_employment_types() {
		$taxonomies = self::get_default_taxonomy_terms();
		$terms      = $taxonomies[ \WP_Job_Manager_Post_Types::TAX_LISTING_TYPE ];

		foreach ( $terms as $term => $meta ) {
			$term = get_term_by( 'slug', sanitize_title( $term ), \WP_Job_Manager_Post_Types::TAX_LISTING_TYPE );
			if ( $term ) {
				foreach ( $meta as $meta_key => $meta_value ) {
					if ( ! get_term_meta( (int) $term->term_id, $meta_key, true ) ) {
						add_term_meta( (int) $term->term_id, $meta_key, $meta_value );
					}
				}
			}
		}
	}
}
