<?php

require 'includes/admin/class-wp-job-manager-cpt.php';

class WP_Test_WP_Job_Manager_CPT extends WPJM_BaseTest {
	protected WP_Job_Manager_CPT $job_manager_cpt;

	public function setUp(): void {
		parent::setUp();

		// Ensure the hooks are set up.
		$this->job_manager_cpt = new WP_Job_Manager_CPT();
	}

	/**
	 * Ensure that filter_meta adds the correct filters to the query based on
	 * the URL parameters.
	 *
	 * @since 1.31.0
	 * @covers WP_Job_Manager_CPT::filter_meta
	 */
	public function test_filter_meta() {
		global $pagenow;

		// Create some listings.
		$listing_notfilled_notfeatured_id = $this->create_listing_with_meta(
			[
				'_filled'   => '0',
				'_featured' => '0',
			]
		);
		$listing_notfilled_featured_id    = $this->create_listing_with_meta(
			[
				'_filled'   => '0',
				'_featured' => '1',
			]
		);
		$listing_filled_notfeatured_id    = $this->create_listing_with_meta(
			[
				'_filled'   => '1',
				'_featured' => '0',
			]
		);
		$listing_filled_featured_id       = $this->create_listing_with_meta(
			[
				'_filled'   => '1',
				'_featured' => '1',
			]
		);

		// Simulate viewing the edit.php page.
		$pagenow = 'edit.php';

		// When no filters are given.
		$query = new WP_Query(
			[
				'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING,
				'fields'    => 'ids',
			]
		);
		$this->assertContains( $listing_notfilled_notfeatured_id, $query->posts );
		$this->assertContains( $listing_notfilled_featured_id, $query->posts );
		$this->assertContains( $listing_filled_notfeatured_id, $query->posts );
		$this->assertContains( $listing_filled_featured_id, $query->posts );

		// Filtering on Filled.
		$_GET['job_listing_filled'] = '1';
		$query                      = new WP_Query(
			[
				'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING,
				'fields'    => 'ids',
			]
		);
		$this->assertNotContains( $listing_notfilled_notfeatured_id, $query->posts );
		$this->assertNotContains( $listing_notfilled_featured_id, $query->posts );
		$this->assertContains( $listing_filled_notfeatured_id, $query->posts );
		$this->assertContains( $listing_filled_featured_id, $query->posts );

		// Filtering on Featured.
		$_GET['job_listing_filled']   = '';
		$_GET['job_listing_featured'] = '0';
		$query                        = new WP_Query(
			[
				'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING,
				'fields'    => 'ids',
			]
		);
		$this->assertContains( $listing_notfilled_notfeatured_id, $query->posts );
		$this->assertNotContains( $listing_notfilled_featured_id, $query->posts );
		$this->assertContains( $listing_filled_notfeatured_id, $query->posts );
		$this->assertNotContains( $listing_filled_featured_id, $query->posts );
	}

	/**
	 * Ensure that filter_meta adds the correct filters to the query only on
	 * edit.php page.
	 *
	 * @since 1.31.0
	 * @covers WP_Job_Manager_CPT::filter_meta
	 */
	public function test_filter_meta_only_on_edit() {
		global $pagenow;

		// Create some listings.
		$listing_id = $this->factory->post->create(
			[ 'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING ]
		);

		// Simulate viewing some other page.
		$pagenow = 'index.php';

		// Filter should do nothing.
		$_GET['job_listing_filled']   = '1';
		$_GET['job_listing_featured'] = '1';
		$query                        = new WP_Query(
			[
				'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING,
				'fields'    => 'ids',
			]
		);
		$this->assertContains( $listing_id, $query->posts );
	}

	/* Helper methods. */

	private function create_listing_with_meta( $meta ) {
		$id = $this->factory->post->create(
			[ 'post_type' => \WP_Job_Manager_Post_Types::PT_LISTING ]
		);

		foreach ( $meta as $meta_key => $meta_value ) {
			update_post_meta( $id, $meta_key, $meta_value );
		}

		return $id;
	}
}
