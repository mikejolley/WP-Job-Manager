<?php
/**
 * File containing the class WP_Job_Manager\Stats_Script
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add and handle frontend script to collect stats.
 *
 * @since 2.3.0
 */
class Stats_Script {
	use Singleton;

	/**
	 * Run any hooks related to stats.
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'wp_ajax_job_manager_log_stat', [ $this, 'ajax_log_stat' ] );
		add_action( 'wp_ajax_nopriv_job_manager_log_stat', [ $this, 'ajax_log_stat' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_stats_scripts' ] );
	}

	/**
	 * Log multiple stats in one go. Triggered in an ajax call.
	 *
	 * @return bool
	 */
	public function ajax_log_stat() {
		if ( ! wp_doing_ajax() ) {
			return false;
		}

		$post_data = stripslashes_deep( $_POST );

		if ( ! isset( $post_data['_ajax_nonce'] ) || ! wp_verify_nonce( $post_data['_ajax_nonce'], 'ajax-nonce' ) ) {
			return false;
		}

		$stats_json = $post_data['stats'] ?? '[]';
		$stats      = json_decode( $stats_json, true );

		if ( empty( $stats ) ) {
			return false;
		}

		$errors           = [];
		$registered_stats = $this->get_registered_stat_names();

		$stats = array_filter(
			$stats,
			function( $stat ) use ( $registered_stats ) {
				return in_array( $stat['name'], $registered_stats, true );
			}
		);

		return Stats::instance()->batch_log_stats( $stats );
	}

	/**
	 * Get stat names.
	 *
	 * @return int[]|string[]
	 */
	private function get_registered_stat_names() {
		return array_keys( $this->get_registered_stats() );
	}

	/**
	 * Register any frontend scripts for job listings.
	 *
	 * @access private
	 */
	public function maybe_enqueue_stats_scripts() {

		\WP_Job_Manager::register_script(
			'wp-job-manager-stats',
			'js/wpjm-stats.js',
			[
				'wp-dom-ready',
				'wp-hooks',
			],
			true
		);

		global $post;

		if ( is_wpjm_job_listing() ) {
			$this->enqueue_stats_script( 'listing', $post->ID );
		}

		if ( $this->page_has_jobs_shortcode( $post ) ) {
			$this->enqueue_stats_script( 'jobs', $post->ID );
		}

	}

	/**
	 * Register scripts for given screen.
	 *
	 * @param string $page Which page.
	 * @param int    $post_id Which id.
	 *
	 * @return void
	 */
	private function enqueue_stats_script( $page = 'listing', $post_id = 0 ) {

		$script_data = [
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
			'ajaxNonce' => wp_create_nonce( 'ajax-nonce' ),
			'postId'    => $post_id,
			'stats'     => $this->get_stats_for_ajax( $post_id, $page ),
		];

		wp_enqueue_script( 'wp-job-manager-stats' );
		wp_localize_script(
			'wp-job-manager-stats',
			'job_manager_stats',
			$script_data
		);

	}

	/**
	 * Get all the registered stats.
	 *
	 * @return array
	 */
	private function get_registered_stats() {
		return (array) apply_filters(
			'wpjm_get_registered_stats',
			[
				Job_Listing_Stats::VIEW              => [
					'type'   => 'action',
					'action' => 'page-load',
					'page'   => 'listing',
				],
				Job_Listing_Stats::VIEW_UNIQUE       => [
					'type'   => 'action',
					'action' => 'page-load',
					'unique' => true,
					'page'   => 'listing',
				],
				Job_Listing_Stats::APPLY_CLICK       => [
					'type'   => 'domEvent',
					'args'   => [
						'element' => 'input.application_button',
						'event'   => 'click',
					],
					'unique' => true,
					'page'   => 'listing',
				],
				'search_view'                        => [
					'type'   => 'action',
					'action' => 'page-load',
					'page'   => 'jobs',
				],
				'search_view_unique'                 => [
					'type'   => 'action',
					'action' => 'page-load',
					'page'   => 'jobs',
					'unique' => true,
				],
				Job_Listing_Stats::SEARCH_IMPRESSION => [
					'type' => 'impression',
					'args' => [
						'container' => 'ul.job_listings',
						'item'      => 'li.job_listing',
					],
					'page' => 'jobs',
				],
			]
		);
	}

	/**
	 * Determine what stats should be added to the kind of page the user is viewing.
	 *
	 * @param int    $post_id Optional post id.
	 * @param string $page The page in question.
	 *
	 * @return array
	 */
	private function get_stats_for_ajax( $post_id = 0, $page = 'listing' ) {
		$ajax_stats = [];
		foreach ( $this->get_registered_stats() as $stat_name => $stat_data ) {
			if ( $page !== $stat_data['page'] ) {
				continue;
			}

			$stat_ajax = [
				'name'    => $stat_name,
				'post_id' => $post_id,
				'type'    => $stat_data['type'] ?? '',
				'action'  => $stat_data['action'] ?? '',
				'args'    => $stat_data['args'] ?? '',
			];

			if ( ! empty( $stat_data['unique'] ) ) {
				$unique_callback         = $stat_data['unique_callback'] ?? [ $this, 'get_post_id_unique_key' ];
				$stat_ajax['unique_key'] = call_user_func( $unique_callback, $stat_name, $post_id );
			}

			$ajax_stats[] = $stat_ajax;
		}

		return $ajax_stats;
	}

	/**
	 * Derive unique key by post id.
	 *
	 * @access private
	 *
	 * @param string $stat_name Name.
	 * @param int    $post_id Post id.
	 *
	 * @return string
	 */
	public function get_post_id_unique_key( $stat_name, $post_id ) {
		return $stat_name . '_' . $post_id;
	}

	/**
	 * Any page containing a job shortcode is eligible.
	 *
	 * @param \WP_Post $post The post.
	 *
	 * @return bool
	 */
	private function page_has_jobs_shortcode( $post ) {
		return $post && has_shortcode( $post->post_content, 'jobs' );
	}

}
