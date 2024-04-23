<?php
/**
 * File containing the class WP_Job_Manager_Helper_API.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Helper_API
 */
class WP_Job_Manager_Helper_API {

	const API_BASE_URL = 'https://wpjobmanager.com';

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.29.0
	 */
	private static $instance = null;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.29.0
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Checks if there is an update for the plugin using the WPJobManager.com API.
	 *
	 * @deprecated 1.42.0 Use WP_Job_Manager_Helper_API::bulk_update_check() instead.
	 *
	 * @param array $args The arguments to pass to the endpoint.
	 * @return array|false The response, or false if the request failed.
	 */
	public function plugin_update_check( $args ) {
		_deprecated_function( __METHOD__, '1.42.0', 'WP_Job_Manager_Helper_API::bulk_update_check' );

		return false;
	}

	/**
	 * Check for plugin updates for several plugins.
	 *
	 * @param array $plugins Array of plugins keyed by slug with the associated license and installed version.
	 * @return array|false The response, or false if the request failed.
	 */
	public function bulk_update_check( array $plugins ) {
		return $this->request_endpoint(
			'/wp-json/wpjmcom-licensing/v1/updates',
			[
				'method' => 'POST',
				'body'   => wp_json_encode(
					[
						'site_url' => $this->get_site_url(),
						'plugins'  => $plugins,
					]
				),
			]
		);
	}

	/**
	 * Sends and receives data related to plugin information from the WPJobManager.com API.
	 *
	 * @param array $args  The arguments to pass to the endpoint.
	 * @return \stdClass|false The response, or false if the request failed.
	 */
	public function plugin_information( $args ) {
		$args = wp_parse_args( $args );
		$data = $this->request_endpoint(
			'/wp-json/wpjmcom-licensing/v1/plugin-information',
			[
				'method' => 'GET',
				'body'   => [
					'site_url'     => $this->get_site_url(),
					'license_key'  => $args['license_key'],
					'product_slug' => $args['api_product_id'],
				],
			]
		);

		if ( ! is_array( $data ) ) {
			return false;
		}
		$response               = new \stdClass();
		$response->name         = $data['name'];
		$slug                   = $data['slug'];
		$response->plugin       = $slug . '/' . $slug . '.php';
		$response->slug         = $slug;
		$response->version      = $data['version'];
		$response->last_updated = $data['last_updated'];
		$response->author       = $data['author'];
		$response->requires     = $data['requires'];
		$response->tested       = $data['tested'];
		$response->homepage     = $data['homepage'];

		// set sections.
		$response->sections = [
			'description' => $data['sections']['description'],
			'changelog'   => $data['sections']['changelog'],
		];

		$response->download_link = $data['download_link'];
		return $response;
	}

	/**
	 * Attempt to activate a plugin license.
	 *
	 * @param array $args The arguments to pass to the API.
	 * @return array|false JSON response or false if failed.
	 */
	public function activate( $args ) {
		$args         = wp_parse_args( $args );
		$product_slug = $args['api_product_id'];
		$response     = $this->bulk_activate( $args['license_key'], [ $product_slug ] );
		if ( false === $response || ! array_key_exists( $product_slug, $response ) ) {
			return false;
		}
		$item = $response[ $product_slug ];
		// Add keys used previously for backwards compatibility.
		if ( $item['success'] ) {
			return [
				'success'   => true,
				'activated' => true,
				'remaining' => $item['remaining_activations'],
			];
		}
		return [
			'error'      => $item['error_message'],
			'error_code' => $item['error_code'],
		];
	}

	/**
	 * Attempt to activate multiple WPJM products with a single license key.
	 *
	 * @param string $license_key The license key to activate.
	 * @param array  $product_slugs The slugs of the products to activate.
	 * @return array|false The response, or false if the request failed.
	 */
	public function bulk_activate( $license_key, $product_slugs ) {
		return $this->request_endpoint(
			'/wp-json/wpjmcom-licensing/v1/activate',
			[
				'method' => 'POST',
				'body'   => wp_json_encode(
					[
						'site_url'      => $this->get_site_url(),
						'license_key'   => $license_key,
						'product_slugs' => $product_slugs,
					]
				),
			]
		);
	}

	/**
	 * Attempt to deactivate a plugin license.
	 *
	 * @param array|string $args
	 * @return array|false JSON response or false if failed.
	 */
	public function deactivate( $args ) {
		$args     = wp_parse_args( $args );
		$response = $this->request_endpoint(
			'/wp-json/wpjmcom-licensing/v1/deactivate',
			[
				'method' => 'POST',
				'body'   => wp_json_encode(
					[
						'product_slug' => $args['api_product_id'],
						'license_key'  => $args['license_key'],
						'site_url'     => $this->get_site_url(),
					]
				),
			]
		);
		if ( ! is_array( $response ) || ! array_key_exists( 'success', $response ) ) {
			return false;
		}
		return $response;
	}

	/**
	 * Flush the WPCOM license for a given plugin.
	 *
	 * @param string $plugin_slug The plugin slug to flush the WPCOM license for.
	 * @param string $activation_url The activation URL for WPJMCOM to request from this blog with the license information.
	 * @return array|bool Pass through the API response from the licensing server, or false on error.
	 */
	public function flush_wpcom_license( string $plugin_slug, string $activation_url ) {
		// Get domain.
		$domain = wp_parse_url( $activation_url, PHP_URL_HOST );
		// Call activation service.
		$response = $this->request_endpoint(
			'/wp-json/wpjmcom-licensing/v1/flush-wpcom-activation',
			[
				'method'  => 'POST',
				'body'    => wp_json_encode(
					[
						'plugin_slug'    => $plugin_slug,
						'domain'         => $domain,
						'activation_url' => $activation_url,
					]
				),
				'timeout' => 60,
			]
		);
		if ( ! is_array( $response ) || ! array_key_exists( 'success', $response ) ) {
			return false;
		}
		return $response;
	}

	/**
	 * Make a license helper API request.
	 *
	 * @param array $args The arguments to pass to the API.
	 * @param bool  $return_error If we should return the error details or not.
	 *
	 * @return array|false The response as an array, or false if the request failed.
	 */
	protected function request( $args, $return_error = false ) {
		// These legacy endpoints are temporary. For now, translate `license_key` => `licence_key` at this point.
		if ( ! empty( $args['license_key'] ) ) {
			$args['licence_key'] = $args['license_key'];
			unset( $args['license_key'] );
		}

		$defaults = [
			'instance'       => $this->get_site_url(),
			'plugin_name'    => '',
			'version'        => '',
			'api_product_id' => '',
			'licence_key'    => '',
			'email'          => '',
		];

		$args     = wp_parse_args( $args, $defaults );
		$response = wp_safe_remote_get(
			self::get_wpjmcom_url() . '/?' . http_build_query( $args, '', '&' ),
			[
				'timeout' => 10,
				'headers' => [
					'Accept' => 'application/json',
				],
			]
		);

		return $this->decode_response( $response, $return_error );
	}

	/**
	 * Make a license helper API request to a WP REST API Endpoint.
	 *
	 * @param string $endpoint The endpoint to make the API request to.
	 * @param array  $args The arguments to pass to the request.
	 * @param bool   $return_error If we should return the error details or not.
	 *
	 * @return array|false The response, an error if $return_error is true, or false if the request failed and $return_error is false.
	 */
	protected function request_endpoint( $endpoint, $args, $return_error = false ) {
		$defaults = [
			'timeout' => 10,
			'headers' => [
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
			],
		];
		$args     = wp_parse_args( $args, $defaults );

		$response = wp_safe_remote_request(
			self::get_wpjmcom_url() . $endpoint,
			$args
		);

		return $this->decode_response( $response, $return_error );
	}

	/**
	 * Returns the site URL that is MU safe.
	 *
	 * @return string
	 */
	private function get_site_url() {
		if ( is_multisite() || is_network_admin() ) {
			return network_site_url();
		}
		return site_url();
	}

	/**
	 * Returns the URL to WPJobManager.com.
	 *
	 * @return string
	 */
	public static function get_wpjmcom_url() {
		if (
			defined( 'JOB_MANAGER_VERSION' )
			&& defined( 'JOB_MANAGER_DEV_API_BASE_URL' )
			&& '-dev' === substr( JOB_MANAGER_VERSION, -4 )
		) {
			return rtrim( JOB_MANAGER_DEV_API_BASE_URL, '/' );
		}
		return rtrim( self::API_BASE_URL, '/' );
	}

	/**
	 * Decode the response from the WPJobManager.com API.
	 *
	 * @param array|WP_Error $response The response, or an error.
	 * @param bool           $return_error If we should return the detailed error or not.
	 * @return array|false The response as an array, or false if the request failed and $return_error is false.
	 */
	protected function decode_response( $response, $return_error ) {
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( $return_error ) {
				if ( is_wp_error( $response ) ) {
					return [
						'error_code' => $response->get_error_code(),
						'error'      => $response->get_error_message(),
					];
				}
				return [
					'error_code' => wp_remote_retrieve_response_code( $response ),
					'error'      => 'Error code: ' . wp_remote_retrieve_response_code( $response ),
				];
			}
			return false;
		}

		$result = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( is_array( $result ) ) {
			return $result;
		}

		return false;
	}
}
