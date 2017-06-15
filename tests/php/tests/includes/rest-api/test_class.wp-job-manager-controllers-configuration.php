<?php

class WP_Test_WP_Job_Manager_Controllers_Configuration extends WPJM_REST_TestCase {

	/**
	 * @var int
	 */
	private $admin_id;
	/**
	 * @var int
	 */
	private $default_user_id;

	function setUp() {
		parent::setUp();
		$admin = get_user_by( 'email', 'rest_api_admin_user@test.com' );
		if ( false === $admin ){
			$this->admin_id = wp_create_user(
				'rest_api_admin_user',
				'rest_api_admin_user',
				'rest_api_admin_user@test.com' );
			$admin = get_user_by( 'ID', $this->admin_id );
			$admin->set_role( 'administrator' );
		}

		$this->default_user_id = get_current_user_id();
		$this->login_as_admin();
	}

	function login_as_admin() {
		return $this->login_as( $this->admin_id );
	}

	function login_as( $user_id ) {
		wp_set_current_user( $user_id );
		return $this;
	}

	function test_get_succeed_when_user_not_admin() {
		$this->login_as( $this->default_user_id );
		$response = $this->get( '/wpjm/v1/configuration' );
		$this->assertResponseStatus( $response, 200 );
	}

	function test_get_index_response() {
		$this->login_as( $this->default_user_id );
		$response = $this->get( '/wpjm/v1/configuration' );
		$this->assertResponseStatus( $response, 200 );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'run_page_setup', $data );
		$this->assertInternalType( 'bool', $data['run_page_setup'] );
	}

	function test_get_show_response_succeed_when_valid_key() {
		$this->login_as( $this->default_user_id );
		$response = $this->get( '/wpjm/v1/configuration/run_page_setup' );
		$this->assertResponseStatus( $response, 200 );
		$data = $response->get_data();
		$this->assertInternalType( 'bool', $data );
	}

	function test_get_show_response_not_found_when_valid_key() {
		$this->login_as( $this->default_user_id );
		$response = $this->get( '/wpjm/v1/configuration/invalid' );
		$this->assertResponseStatus( $response, 404 );
	}

	function test_delete_not_found() {
		$response = $this->delete( '/wpjm/v1/configuration/run_page_setup' );
		$this->assertResponseStatus( $response, 404 );
	}

	function test_post_created_key_value_from_request_body() {
		$response = $this->post( '/wpjm/v1/configuration/run_page_setup', 'true' );
		$this->assertResponseStatus( $response, 201 );
	}

	function test_post_created_key_value_from_value_param() {
		$response = $this->post( '/wpjm/v1/configuration/run_page_setup', array(
			'value' => true,
		) );
		$this->assertResponseStatus( $response, 201 );
	}

	function test_put_ok_key_value_from_value_param() {
		$response = $this->put( '/wpjm/v1/configuration/run_page_setup', array(
			'value' => true,
		) );
		$this->assertResponseStatus( $response, 200 );
	}

	function test_put_updates_key_value_from_value_param() {
		$value = $this->environment()
			->model( 'WP_Job_Manager_Models_Configuration' )
			->get_data_store()->get_entity( '' )
			->get( 'run_page_setup' );
		$response = $this->put( '/wpjm/v1/configuration/run_page_setup', array(
			'value' => ! $value,
		) );
		$this->assertResponseStatus( $response, 200 );
		$model = $this->environment()
			->model( 'WP_Job_Manager_Models_Configuration' )
			->get_data_store()->get_entity( '' );
		$this->assertNotEquals( $value, $model->get( 'run_page_setup' ) );
	}

	function test_post_response_status_requires_admin() {
		$this->login_as( $this->default_user_id );

		$response = $this->put( '/wpjm/v1/configuration/run_page_setup', array(
			'value' => false,
		) );

		$this->assertResponseStatus( $response, 403 );
	}
}
