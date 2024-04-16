<?php

namespace WP_Job_Manager;

class WP_Test_Stats extends \WPJM_BaseTest {

	//setup
	public function setUp(): void {
		parent::setUp();
		update_option( Stats::OPTION_ENABLE_STATS, true );
		Stats::instance()->migrate_db();
	}

	public function tearDown(): void {
		parent::tearDown();

	}

	public function test_log_stat_creates_record() {
		$job_id = $this->factory->job_listing->create();

		Stats::instance()->log_stat( 'test_stat', [ 'post_id' => $job_id, 'count' => 1 ] );

		$stats = Stats::instance()->get_stats( null, $job_id );

		$this->assertNotEmpty( $stats );
		$this->assertEquals( 'test_stat', $stats[0]->name );

	}

	public function test_log_stat_exits_when_disabled() {
		update_option( Stats::OPTION_ENABLE_STATS, false );
		Stats::instance()->log_stat( 'test_stat', [ 'post_id' => 1, 'count' => 1 ] );

		$stats = Stats::instance()->get_stats( null, 1 );

		$this->assertEmpty( $stats );

	}

	public function test_log_stat_increases_count() {

		Stats::instance()->log_stat( 'test_stat', [ 'post_id' => 1, 'count' => 1 ] );
		Stats::instance()->log_stat( 'test_stat', [ 'post_id' => 1, 'count' => 1 ] );

		$stats = Stats::instance()->get_stats( 'test_stat', 1 );

		$this->assertNotEmpty( $stats );
		$this->assertEquals( 2, $stats[0]->count );

	}

	public function test_batch_log_stats_creates_records() {

		$stats = [
			[ 'name' => 'test_stat', 'post_id' => 1, 'count' => 1 ],
			[ 'name' => 'test_stat', 'post_id' => 2, 'count' => 2 ],
			[ 'name' => 'test_stat_2', 'post_id' => 1, 'count' => 1 ],
		];

		Stats::instance()->batch_log_stats( $stats );

		$stats = Stats::instance()->get_stats();

		$this->assertCount( 3, $stats );

	}

	public function test_batch_log_stats_increases_counts() {

		$stats = [
			[ 'name' => 'test_stat', 'post_id' => 1, 'count' => 1 ],
			[ 'name' => 'test_stat', 'post_id' => 1, 'count' => 1 ],
			[ 'name' => 'test_stat', 'post_id' => 2, 'count' => 2 ],
		];

		Stats::instance()->batch_log_stats( $stats );
		Stats::instance()->batch_log_stats( $stats );

		$stats = Stats::instance()->get_stats();

		$this->assertCount( 2, $stats );
		$this->assertEquals( 4, $stats[0]->count );
		$this->assertEquals( 4, $stats[1]->count );

	}

	public function test_delete_stats_deletes_post_stats() {

		$stats = [
			[ 'name' => 'test_stat', 'post_id' => 1, 'count' => 1 ],
			[ 'name' => 'test_stat', 'post_id' => 2, 'count' => 2 ],
			[ 'name' => 'test_stat_2', 'post_id' => 1, 'count' => 1 ],
		];

		Stats::instance()->batch_log_stats( $stats );

		Stats::instance()->delete_stats( 1 );

		$stats = Stats::instance()->get_stats( null, 1 );

		$this->assertEmpty( $stats );

	}

	public function test_job_listing_stats_counts_totals() {

		$job_id = $this->factory->job_listing->create();

		Stats::instance()->batch_log_stats( [
			[ 'name' => 'test_stat', 'post_id' => $job_id, 'count' => 1 ],
			[ 'name' => 'test_stat', 'post_id' => $job_id, 'count' => 1 ],
		] );

		$job_stats = new Job_Listing_Stats( $job_id );

		$total = $job_stats->get_event_total( 'test_stat' );

		$this->assertEquals( 2, $total );
	}

	public function test_job_listing_stats_counts_daily_stats() {

		$job_id = $this->factory->job_listing->create();

		Stats::instance()->batch_log_stats( [
			[ 'name' => 'test_stat', 'post_id' => $job_id, 'count' => 1, 'date' => '2020-01-01' ],
			[ 'name' => 'test_stat', 'post_id' => $job_id, 'count' => 1, 'date' => '2020-01-01' ],
			[ 'name' => 'test_stat', 'post_id' => $job_id, 'count' => 1, 'date' => '2020-01-02' ],
		] );

		$job_stats = new Job_Listing_Stats( $job_id, [
			new \DateTime( '2020-01-01' ),
			new \DateTime( '2020-01-02' ),
		] );

		$daily = $job_stats->get_event_daily( 'test_stat' );

		$this->assertEquals( [ '2020-01-01' => 2, '2020-01-02' => 1 ], $daily );

	}

	public function test_ajax_stats_logged() {

		Stats_Script::instance();

		$job_id = $this->factory->job_listing->create();

		$_POST = [
			'stats'       => json_encode( [
				[
					'post_id' => $job_id,
					'name'    => 'job_view',
				],
				[
					'post_id' => $job_id,
					'name'    => 'job_view_unique',
				],
			] ),
			'_ajax_nonce' => wp_create_nonce( 'ajax-nonce' ),
		];

		do_action( 'wp_ajax_job_manager_log_stat' );

		$stats = Stats::instance()->get_stats( null, $job_id );

		$this->assertEquals( [ 'job_view', 'job_view_unique' ], wp_list_pluck( $stats, 'name' ) );

	}

}
