<?php
/**
 * File containing the class WP_Job_Manager\Stats
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collect and retrieve job statistics.
 *
 * @since 2.3.0
 */
class Stats {
	use Singleton;

	/**
	 * Cache group for stat queries.
	 */
	const CACHE_GROUP = 'wpjm_stats';

	/**
	 * Setting key for enabling stats.
	 */
	const OPTION_ENABLE_STATS = 'job_manager_stats_enable';

	private const TABLE = 'wpjm_stats';

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Do initialization of all the things needed for stats.
	 */
	public function init() {

		$this->init_wpdb_alias();

		if ( ! self::is_enabled() ) {
			return;
		}

		Stats_Dashboard::instance();
		Stats_Script::instance();
	}

	/**
	 * Initialize the alias for the stats table on the wpdb object.
	 *
	 * @return void
	 */
	private function init_wpdb_alias() {
		global $wpdb;
		if ( isset( $wpdb->wpjm_stats ) ) {
			return;
		}
		$wpdb->wpjm_stats = $wpdb->prefix . self::TABLE;
		$wpdb->tables[]   = self::TABLE;
	}

	/**
	 * Migrate the stats table to the latest version.
	 *
	 * @return void
	 */
	public function migrate_db() {
		global $wpdb;
		$collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		\dbDelta(
			[
				"CREATE TABLE {$wpdb->wpjm_stats} (
				`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				`date` date NOT NULL,
				`post_id` bigint(20) DEFAULT NULL,
				`name` varchar(50) NOT NULL,
				`group` varchar(50) DEFAULT '',
				`count` bigint(20) unsigned not null default 1,
				PRIMARY KEY (`id`),
				UNIQUE INDEX `idx_wpjm_stats_name_date_group_post_id`  (`name`, `date`, `group`, `post_id`)
			) {$collate}",
			]
		);
	}

	/**
	 * Check if collecting and showing statistics are enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) get_option( self::OPTION_ENABLE_STATS, false );
	}

	/**
	 * Log a stat into the db.
	 *
	 * @param string $name The stat name.
	 * @param array  $args {
	 * Optional args for this stat.
	 *
	 * @type string  $group The group this stat belongs to.
	 * @type int     $post_id The post_id this stat belongs to.
	 * @type int     $count The amount to increment the stat by.
	 * @type string  $date Date in YYYY-MM-DD format.
	 * }
	 *
	 * @return bool
	 */
	public function log_stat( string $name, array $args = [] ) {

		if ( ! self::is_enabled() ) {
			return false;
		}

		return $this->batch_log_stats(
			[
				array_merge(
					[ 'name' => $name ],
					$args
				),
			]
		);
	}

	/**
	 * Log a stat for multiple posts in one query.
	 *
	 * @param array[] $stats {
	 * Array of stats to log, with the following fields.
	 *
	 * @type string   $name The stat name.
	 * @type int      $post_id Post ids to log the stat for.
	 * @type string   $group Additional data (eg keyword) for the stat.
	 * @type int      $count The amount to increment the stat by.
	 * @type string   $date Date in YYYY-MM-DD format.
	 * }
	 *
	 * @return bool
	 */
	public function batch_log_stats( array $stats ) {

		if ( ! self::is_enabled() ) {
			return false;
		}
		$stats = array_map( [ $this, 'parse_stats' ], $stats );
		$stats = array_filter( $stats );

		if ( empty( $stats ) ) {
			return false;
		}

		global $wpdb;

		$values = [];

		foreach ( $stats as $stat ) {
			$values[] = $wpdb->prepare( '(%s, %s, %s, %d, %d)', $stat['name'], $stat['date'], $stat['group'], $stat['post_id'], $stat['count'] );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching,
		$result = $wpdb->query(
			"INSERT INTO {$wpdb->wpjm_stats} " .
			'(`name`, `date`, `group`, `post_id`, `count` )' .
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'VALUES ' . implode( ', ', $values ) .
			'ON DUPLICATE KEY UPDATE `count` = `count` + VALUES(`count`)',
		);

		if ( false === $result ) {
			return false;
		}

		return true;
	}

	/**
	 * Process and validate stat details.
	 *
	 * @param array $args {
	 * Stat data.
	 *
	 * @type string $name The stat name.
	 * @type string $group Additional data (eg keyword) for the stat.
	 * @type int    $post_id The post_id this stat belongs to.
	 * @type int    $count The amount to increment the stat by.
	 * @type string $date Date in YYYY-MM-DD format.
	 * }
	 *
	 * @return array|false
	 */
	private function parse_stats( $args ) {
		$args = wp_parse_args(
			$args,
			[
				'name'    => '',
				'group'   => '',
				'post_id' => 0,
				'count'   => 1,
				'date'    => gmdate( 'Y-m-d' ),
			]
		);

		$args['post_id'] = absint( $args['post_id'] );

		if (
			empty( $args['name'] ) ||
			strlen( $args['name'] ) > 50 ||
			strlen( $args['group'] ) > 50 ||
			empty( $args['post_id'] ) ||
			! is_integer( $args['count'] ) ) {
			return false;
		}

		return $args;
	}

	/**
	 * Delete all stats for a given job.
	 *
	 * @param int $post_id
	 */
	public function delete_stats( $post_id ) {
		global $wpdb;
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->wpjm_stats} WHERE post_id = %d", $post_id ) );
	}

	/**
	 * Delete all stats for a given job.
	 *
	 * @param string $stat_name
	 * @param int    $post_id
	 * @param null   $date
	 *
	 * @return array
	 */
	public function get_stats( $stat_name = '', $post_id = null, $date = null ) {
		global $wpdb;

		$query  = "SELECT * FROM {$wpdb->wpjm_stats} WHERE 1=1 ";
		$params = [];

		if ( ! empty( $stat_name ) ) {
			$query   .= ' AND name = %s';
			$params[] = $stat_name;
		}

		if ( ! empty( $post_id ) ) {
			$query   .= ' AND post_id = %d';
			$params[] = $post_id;
		}

		if ( ! empty( $date ) ) {
			$query   .= ' AND date = %s';
			$params[] = $date;
		}

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic query.
			$query = $wpdb->prepare( $query, $params );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.
		return $wpdb->get_results( $query );
	}
}
