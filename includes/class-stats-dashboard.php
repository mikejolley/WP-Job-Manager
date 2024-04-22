<?php
/**
 * File containing the class Stats_Dashboard.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

/**
 * Job listing stats for the jobs dashboard.
 *
 * @since 2.3.0
 */
class Stats_Dashboard {

	use Singleton;

	private const COLUMN_NAME = 'stats';

	/**
	 * Max number of days to display in the daily stats chart.
	 */
	private const DAYS_PER_PAGE = 180;

	/**
	 * Constructor.
	 */
	private function __construct() {

		if ( ! Stats::is_enabled() ) {
			return;
		}

		add_filter( 'job_manager_job_dashboard_columns', [ $this, 'add_stats_column' ] );
		add_action( 'job_manager_job_dashboard_column_' . self::COLUMN_NAME, [ $this, 'render_stats_column' ] );

		add_filter( 'manage_edit-job_listing_columns', [ $this, 'add_stats_column' ] );
		add_action( 'manage_job_listing_posts_custom_column', [ $this, 'maybe_render_admin_stats_column' ], 2 );

		add_action( 'job_manager_job_overlay_content', [ $this, 'output_job_stats' ], 12 );
	}

	/**
	 * Add a new column to the job dashboards.
	 *
	 * @param array $columns
	 *
	 * @return array
	 */
	public function add_stats_column( $columns ) {
		$columns[ self::COLUMN_NAME ] = __( 'Views', 'wp-job-manager' );

		return $columns;
	}

	/**
	 * Output the stats column content.
	 *
	 * @param \WP_Post $job
	 */
	public function render_stats_column( $job ) {
		$stats       = new Job_Listing_Stats( $job->ID );
		$views       = $stats->get_event_total( Job_Listing_Stats::VIEW );
		$impressions = $stats->get_event_total( Job_Listing_Stats::SEARCH_IMPRESSION );

		// translators: %1d is the number of page views.
		$views_str = '<div>' . sprintf( _n( '%1d view', '%1d views', $views, 'wp-job-manager' ), $views ) . '</div>';
		// translators: %1d is the number of impressions.
		$views_str .= '<small>' . sprintf( _n( '%1d impression', '%1d impressions', $impressions, 'wp-job-manager' ), $impressions ) . '</small>';

		echo wp_kses_post( $views_str );
	}

	/**
	 * Output stats column for the job listing post type admin screen.
	 *
	 * @param string $column
	 */
	public function maybe_render_admin_stats_column( $column ) {
		global $post;

		if ( self::COLUMN_NAME === $column && ! empty( $post->ID ) ) {
			echo '<a href="#' . esc_attr( $post->ID ) . ' ">';
			$this->render_stats_column( $post );
			echo '</a>';
		}
	}

	/**
	 * Output job analytics section.
	 *
	 * @param \WP_Post $job
	 */
	public function output_job_stats( $job ) {

		$stat_summaries = $this->get_stat_summaries( $job );

		$chart = $this->get_daily_stats_chart( $job );

		get_job_manager_template(
			'job-stats.php',
			[
				'stats' => $stat_summaries,
				'chart' => $chart,
			]
		);
	}

	/**
	 * Get data for the daily stats chart for a job.
	 *
	 * @param \WP_Post $job
	 *
	 * @return array
	 */
	private function get_daily_stats_chart( \WP_Post $job ): array {

		$start_date = get_post_datetime( $job );

		if ( ! $start_date ) {
			return [];
		}

		$past_days = $start_date->diff( new \DateTime() )->days + 1;

		if ( $past_days > self::DAYS_PER_PAGE ) {
			$start_date = $start_date->modify( '+' . ( $past_days - self::DAYS_PER_PAGE ) . ' day' );
		}

		$job_stats = new Job_Listing_Stats( $job->ID, $start_date ? [ $start_date ] : [] );

		$daily_views       = $job_stats->get_event_daily( Job_Listing_Stats::VIEW );
		$daily_uniques     = $job_stats->get_event_daily( Job_Listing_Stats::VIEW_UNIQUE );
		$daily_impressions = $job_stats->get_event_daily( Job_Listing_Stats::SEARCH_IMPRESSION );

		$max_views  = ! empty( $daily_views ) ? max( $daily_views ) : 100;
		$resolution = $max_views < 1000 ? 100 : 1000;
		$max        = max( ceil( $max_views / $resolution ) * $resolution, 100 );
		$by_day     = [];

		foreach ( $daily_views as $date => $views ) {
			$by_day[ $date ] = [
				'date'        => $date,
				'views'       => $views,
				'uniques'     => $daily_uniques[ $date ] ?? 0,
				'impressions' => $daily_impressions[ $date ] ?? 0,
				'class'       => '',
			];
		}

		$end_date = \WP_Job_Manager_Post_Types::instance()->get_job_expiration( $job );

		if ( ! $end_date ) {
			$end_date = $start_date->modify( '+1 month' );
		}

		$all_days = $start_date->diff( $end_date )->days + 1;

		$all_days = min( $all_days, self::DAYS_PER_PAGE );

		$today = ( new \DateTime() )->format( 'Y-m-d' );

		for ( $i = 0; $i < $all_days; $i++ ) {
			$date = $start_date->modify( '+' . $i . ' day' )->format( 'Y-m-d' );
			if ( empty( $by_day[ $date ] ) ) {
				$by_day[ $date ] = [
					'date'        => $date,
					'views'       => 0,
					'uniques'     => 0,
					'impressions' => 0,
					'class'       => 'future-day',
				];
			}
		}

		if ( ! empty( $by_day[ $today ] ) ) {
			$by_day[ $today ]['class'] = 'today';
		}

		ksort( $by_day );

		$date_format = apply_filters( 'job_manager_get_dashboard_date_format', 'M d, Y' );
		$timezone    = new \DateTimeZone( wp_timezone_string() );

		$by_day_formatted = [];
		foreach ( $by_day as $date => $data ) {
			$date_obj                              = new \DateTime( $date, $timezone );
			$date_fmt                              = wp_date( $date_format, $date_obj->getTimestamp(), $timezone );
			$by_day_formatted[ $date_fmt ]         = $by_day[ $date ];
			$by_day_formatted[ $date_fmt ]['date'] = $date_fmt;
		}

		$chart = [
			'values'   => $by_day_formatted,
			'max'      => $max,
			'y-labels' => [ $max / 2, $max ],
		];
		/**
		 * Filter the job daily stat data, displayed as a chart in the job overlay.
		 *
		 * @param array    $stats Stat definition.
		 * @param \WP_Post $job Job post object.
		 */
		return apply_filters( 'job_manager_job_stats_chart', $chart, $job );
	}

	/**
	 * Get summaries grouped into sections for various stats.
	 *
	 * @param \WP_Post $job
	 *
	 * @return mixed
	 */
	private function get_stat_summaries( \WP_Post $job ) {
		$job_stats = new Job_Listing_Stats( $job->ID );

		$views              = $job_stats->get_event_total( Job_Listing_Stats::VIEW );
		$views_unique       = $job_stats->get_event_total( Job_Listing_Stats::VIEW_UNIQUE );
		$views_repeat       = $views - $views_unique;
		$search_impressions = $job_stats->get_event_total( Job_Listing_Stats::SEARCH_IMPRESSION );
		$search_clicks      = $search_impressions ? $views_unique / $search_impressions * 100 : 0;

		/**
		 * Filter the job stat summaries, displayed in the job overlay.
		 *
		 * @param array    $stats Stat definition.
		 * @param \WP_Post $job Job post object.
		 */
		$stats = apply_filters(
			'job_manager_job_stats_summary',
			[
				'views'       => [
					'title'  => __( 'Total Views', 'wp-job-manager' ),
					'stats'  => [
						[
							'icon'  => 'color-page-view',
							'label' => __( 'Page views', 'wp-job-manager' ),
							'value' => $views,
						],
						[
							'icon'  => 'color-unique-view',
							'label' => __( 'Unique visitors', 'wp-job-manager' ),
							'value' => $views_unique,
						],
					],
					'column' => 1,
				],
				'impressions' => [
					'title'  => __( 'Impressions', 'wp-job-manager' ),
					'help'   => __( 'How many times the listing was seen in search results.', 'wp-job-manager' ),
					'stats'  => [
						[
							'icon'  => 'search',
							'label' => __( 'Search impressions', 'wp-job-manager' ),
							'value' => $search_impressions,
						],
					],
					'column' => 1,
				],
				'interest'    => [
					'title'  => __( 'Interest', 'wp-job-manager' ),
					'stats'  => [
						[
							'icon'    => 'search',
							'label'   => __( 'Search click-through rate', 'wp-job-manager' ),
							'percent' => $search_clicks,
						],
						[
							'icon'  => 'cursor',
							'label' => __( 'Apply clicks', 'wp-job-manager' ),
							'value' => $job_stats->get_event_total( Job_Listing_Stats::APPLY_CLICK ),
						],
						[
							'icon'  => 'url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'24\' height=\'24\' fill=\'none\' viewBox=\'0 0 24 24\'%3e%3cg fill=\'black\'%3e%3cpath d=\'M16.6 7.4a6.5 6.5 0 0 0-9.17-.02L8.7 8.66l-3.9.36.36-3.9 1.2 1.2a8 8 0 1 1-2.3 6.72l1.49-.2A6.5 6.5 0 1 0 16.6 7.4Z\'/%3e%3cpath d=\'m12 7-1 5c0 .37.2.7.51.87l4.13 2.76-2.74-4.11L12 7Z\'/%3e%3c/g%3e%3c/svg%3e")',
							'label' => __( 'Repeat Views', 'wp-job-manager' ),
							'value' => $views_repeat,
						],
					],
					'column' => 2,
				],
			],
			$job
		);

		$stat_columns = array_reduce(
			$stats,
			fn( $columns, $section ) => array_merge_recursive(
				$columns,
				[ 'column-' . $section['column'] => [ $section ] ]
			),
			[]
		);

		return $stat_columns;
	}
}
