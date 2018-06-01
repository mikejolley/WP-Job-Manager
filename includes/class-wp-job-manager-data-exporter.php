<?php
/**
 * Defines a class to handle the user data export
 *
 * @package wp-job-manager
 * @since 1.31.1
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! class_exists( 'WP_Job_Manager_Data_Exporter' ) ) {
	/**
	 * Handles the user data export.
	 *
	 * @package
	 * @since
	 */
	class WP_Job_Manager_Data_Exporter {
		/**
		 * Data exporter
		 *
		 * @param string $email_address
		 * @return array
		 */
		public function user_data_exporter( $email_address ) {
			$export_items = array();
			$user_meta_keys = array(
				'_company_logo',
				'_company_name',
				'_company_website',
				'_company_tagline',
				'_company_twitter',
				'_company_video',
			);

			$user_id = get_user_by( 'email', $email_address );

			foreach ( $user_meta_keys as $user_meta_key ) {	
				$user_meta = get_user_meta( $user_id, $user_meta_key, true );

				if ( empty( $user_meta ) ) {
					continue;
				}
				
				if ( '_company_logo' === $user_meta_key) {
					$user_meta  = wp_get_attachment_url( $user_meta );
				}

				$user_data_to_export = array(
					'name'	 => __( $user_meta_key ),
					'value'	 => $user_meta,
				);

				$export_items[] = array(
					'group_id'		 => 'wpjm-user-data',
					'group_label'	 => __( 'WP Job Manager User Data' ),
					'item_id'		 => "wpjm-user-{$user_id}-{$user_meta_key}",
					'data'			 => $user_data_to_export,
				);
			}

			return array(
				'data' =>$export_items,
				'done' => true,
			);
		}
	}
}