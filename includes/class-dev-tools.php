<?php
/**
 * Tools for testing and development.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tools for testing and development.
 *
 * @internal
 */
class Dev_Tools {

	/**
	 * Initialize the class, add WP-CLI commands.
	 */
	public static function init() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'jm dev stats generate', [ self::class, 'generate_stat_data' ] );
		}
	}

	/**
	 * Generate stats for a job.
	 *
	 * ## OPTIONS
	 *
	 * <job_id>
	 * : The ID of the job to generate stats for.
	 *
	 * ## EXAMPLES
	 *
	 *     wp jm dev stats generate 123
	 *
	 * @when after_wp_load
	 *
	 * @param array $args
	 */
	public static function generate_stat_data( $args ) {
		$job_id = absint( $args[0] );

		$job = get_post( $job_id );

		if ( ! $job_id || ! $job || 'job_listing' !== $job->post_type ) {
			\WP_CLI::error( 'Invalid job ID' );
		}

		$stats = Stats::instance();

		$start_date = get_post_datetime( $job );

		$days = $start_date->diff( new \DateTime() )->days;

		$trend = 1;
		$views = 0;

		$log = '';

		$stats->delete_stats( $job_id );

		$records = [];

		for ( $i = 0; $i <= $days + 1; $i++ ) {
			$views        = (int) max( 0, $views + $trend * wp_rand( 0, 1000 ) );
			$trend        = wp_rand( 0, 10 ) / 10 - 0.5;
			$unique_views = wp_rand( (int) ( $views * 0.3 ), (int) ( $views * 0.8 ) );
			$impressions  = wp_rand( (int) ( $views * 1.6 ), (int) ( $views * 2.6 ) );
			$date         = $start_date->modify( "+{$i} days" )->format( 'Y-m-d' );

			$log .= $views . ' ';

			$records[] = [
				'name'    => Job_Listing_Stats::VIEW,
				'post_id' => $job_id,
				'count'   => $views,
				'date'    => $date,
			];

			$records[] = [
				'name'    => Job_Listing_Stats::VIEW_UNIQUE,
				'post_id' => $job_id,
				'count'   => $unique_views,
				'date'    => $date,
			];

			$records[] = [
				'name'    => Job_Listing_Stats::SEARCH_IMPRESSION,
				'post_id' => $job_id,
				'count'   => $impressions,
				'date'    => $date,
			];

		}

		$stats->batch_log_stats( $records );

		\WP_CLI::log( \WP_CLI::colorize( 'Stats generated from %C' . $start_date->format( 'Y-m-d' ) . '%n for %C' . $days . ' days%n: ' ) . $log );

	}
}
