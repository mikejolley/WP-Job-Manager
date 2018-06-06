<?php

require 'includes/class-wp-job-manager-data-exporter.php';

class WP_Job_Manager_Data_Exporter_Test extends WP_UnitTestCase {
	/**
	 * Setup user meta
	 *
	 * @param array $args
	 */
	private function setupUserMeta( $args ) {
		$user_id = $this->factory()->user->create(
			array(
				'user_login' => 'johndoe',
				'user_email' => 'johndoe@example.com',
				'role' => 'subscriber',
			)
		);

		if ( isset( $args['_company_logo' ] ) ) {
			$args['_company_logo'] = $this->factory()->post->create(
				array(
					'post_type' => 'attachment'
				)
			);
		}

		foreach ( $args as $key => $value ) {
			update_user_meta( $user_id, $key, $value );
		}
	}

	/**
	 * @dataProvider data_provider
	 */
	public function test_user_data_exporter( $args, $expected ) {
		$this->setupUserMeta( $args );
		$exporter = new WP_Job_Manager_Data_Exporter();

		$result = $exporter->user_data_exporter( 'johndoe@example.com' );

		$this->assertEqual( $expec, $result );
	}
}
