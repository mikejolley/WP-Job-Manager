<?php
/**
 * File containing the class WP_Job_Manager_Helper_Nonce.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP_Job_Manager_Helper_Nonce
 */
class WP_Job_Manager_Helper_Nonce {

	/**
	 * The prefix for the custom nonce.
	 */
	private const PREFIX_NONCE = 'wpjm-custom-nonce-';

	/**
	 * The default expiration time for the custom nonce.
	 */
	private const DEFAULT_EXPIRE = MINUTE_IN_SECONDS;

	/**
	 * Creates a custom nonce for the given plugin slug.
	 *
	 * @param string $action The action name.
	 *
	 * @return string The custom nonce.
	 */
	public function create_custom_nonce( $action ) {
		$custom_nonce = wp_generate_password( 15, false );
		set_transient( self::PREFIX_NONCE . $action, $custom_nonce, self::DEFAULT_EXPIRE );

		return $custom_nonce;
	}

	/**
	 * Checks if the given nonce is valid for the given action.
	 *
	 * @param string $nonce  The nonce to check.
	 * @param string $action The action name.
	 *
	 * @return bool True if the nonce is valid, false otherwise.
	 */
	public function check_custom_nonce( $nonce, $action ) {
		$saved_nonce = get_transient( self::PREFIX_NONCE . $action );

		return ! empty( $saved_nonce ) && hash_equals( $saved_nonce, $nonce );
	}
}
