<?php
/**
 * File containing the class \WP_Job_Manager\Admin\Release_Notice.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin notice about changes and new features introduced in the latest release.
 * Update this class for each release to highlight new features or changes.
 */
class Release_Notice {
	public const NOTICE_ID = 'release_notice_2_3_0';

	/**
	 * Set up notice.
	 */
	public static function init() {
		add_filter( 'wpjm_admin_notices', [ __CLASS__, 'add_release_notice' ] );
		add_action( 'job_manager_action_enable_stats', [ self::class, 'enable_feature' ] );
	}

	/**
	 * Enable the highlighted feature.
	 *
	 * @return void
	 */
	public static function enable_feature() {
		\WP_Job_Manager_Settings::instance()->set_setting( \WP_Job_Manager\Stats::OPTION_ENABLE_STATS, '1' );
	}

	/**
	 * Add a release notice for the 2.3.0 release.
	 *
	 * @param array $notices
	 *
	 * @return array
	 */
	public static function add_release_notice( $notices ) {

		// Make sure to update the version number in the notice ID when changing this notice for a new release.
		$action_url                 = \WP_Job_Manager_Admin_Notices::get_action_url( 'enable_stats', self::NOTICE_ID );
		$notices[ self::NOTICE_ID ] = [
			'type'          => 'site-wide',
			'label'         => 'New',
			'heading'       => 'Job Statistics',
			'message'       => '<div>' . __(
				'
<p>Collect analytics about site visitors for each job listing. Display the detailed statistics in the refreshed jobs dashboard.</p>
<ul>
	<li>Tracks page views and unique visitors, search impressions and apply button clicks.</li>
	<li>Adds a new overlay to the employer dashboard with aggregated statistics and a daily breakdown chart.</li>
	<li>Integrates with Job Alerts, Applications, and Bookmarks extensions.</li>
	<li>GDPR-compliant, with no personal user information collected.</li>
</ul>
',
				'wp-job-manager'
			) . '</div>',
			'actions'       => [
				[
					'label'   => __( 'Enable', 'wp-job-manager' ),
					'url'     => $action_url,
					'primary' => true,
				],
				[
					'label'   => __( 'Dismiss', 'wp-job-manager' ),
					'url'     => \WP_Job_Manager_Admin_Notices::get_dismiss_url( self::NOTICE_ID ),
					'primary' => false,
				],
				[
					'label' => __( 'See what\'s new in 2.3', 'wp-job-manager' ),
					'url'   => 'https://wpjobmanager.com/2024/04/29/new-in-2-3-job-statistics/',
					'class' => 'is-link',
				],
			],
			'icon'          => false,
			'level'         => 'landing',
			'image'         => 'https://wpjobmanager.com/wp-content/uploads/2024/03/jm-230-release.png',
			'dismissible'   => false,
			'extra_details' => '',
			'conditions'    => [
				[
					'type'    => 'screens',
					'screens' => [ 'edit-job_listing' ],
				],
			],
		];

		return $notices;
	}

}
