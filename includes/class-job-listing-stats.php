<?php
/**
 * File containing the class WP_Job_Manager_Stats
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * This class is responsible for initializing all aspects of stats for wpjm.
 */
class Job_Listing_Stats {

	const VIEW              = 'job_view';
	const VIEW_UNIQUE       = 'job_view_unique';
	const SEARCH_IMPRESSION = 'job_search_impression';
	const APPLY_CLICK       = 'job_apply_click';

	/**
	 * Job listing post ID.
	 *
	 * @var mixed
	 */
	private $job_id;

	/**
	 * Start date of the period queried.
	 *
	 * @var string
	 */
	private $start_date;

	/**
	 * End date of the period queried.
	 *
	 * @var string
	 */
	private $end_date;

	/**
	 * Stats for a single job listing.
	 *
	 * @since 2.3.0
	 *
	 * @param int                  $job_id
	 * @param \DateTimeInterface[] $date_range Array of start and end date. Defaults to a range from the job's publishing date to the current day.
	 */
	public function __construct( $job_id, $date_range = [] ) {

		$this->job_id     = $job_id;
		$this->start_date = ( $date_range[0] ?? get_post_datetime( $job_id ) )->format( 'Y-m-d' );
		$this->end_date   = ( $date_range[1] ?? new \DateTime() )->format( 'Y-m-d' );
	}

	/**
	 * Get total counts for an event.
	 *
	 * @param string $event
	 *
	 * @return int
	 */
	public function get_event_total( $event ) {

		global $wpdb;

		$cache_key = 'wpjm_stats_sum_' . $event . '_' . $this->job_id;
		$sum       = wp_cache_get( $cache_key, Stats::CACHE_GROUP );

		if ( false !== $sum ) {
			return $sum;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$sum = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(count) FROM {$wpdb->wpjm_stats}
              WHERE post_id = %d AND name = %s AND date BETWEEN %s AND %s",
				$this->job_id,
				$event,
				$this->start_date,
				$this->end_date,
			)
		);

		$sum = is_numeric( $sum ) ? (int) $sum : 0;

		wp_cache_set( $cache_key, $sum, Stats::CACHE_GROUP, HOUR_IN_SECONDS );

		return $sum;
	}

	/**
	 * Get daily breakdown of stats for an event.
	 *
	 * @param string $event
	 *
	 * @return array
	 */
	public function get_event_daily( $event ) {
		global $wpdb;

		$cache_key = 'wpjm_stats_daily_' . $event . '_' . $this->job_id;
		$views     = wp_cache_get( $cache_key, Stats::CACHE_GROUP );

		if ( false !== $views ) {
			return $views;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$views = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT date, count FROM {$wpdb->wpjm_stats}
		  WHERE post_id = %d AND name = %s AND date BETWEEN %s AND %s
		  ORDER BY date ASC",
				$this->job_id,
				$event,
				$this->start_date,
				$this->end_date,
			),
			OBJECT_K
		);

		$views = array_map( fn( $view ) => (int) $view->count, $views );

		wp_cache_set( $cache_key, $views, Stats::CACHE_GROUP, strtotime( 'tomorrow' ) - time() );

		return $views;

	}

}
