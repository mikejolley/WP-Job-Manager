<?php
/**
 * WP.com Marketplace licensing integration for premium core addons.
 *
 * @package wp-job-manager
 */

/**
 * Configure license configuration for WP Job Manager when purchased from WP.com Marketplace.
 *
 * @param bool|WP_Error $result     The result of the licensing configuration.
 * @param array         $payload    The payload receivced from WPJobManager.com back-end API.
 * @param string        $event_type The event type that triggers this filter.
 *
 * @return bool
 */
function wpjm_dotcom_marketplace_configure_license_for_wp_job_manager_addon( $result, $payload, $event_type ) {
	if ( 'provision_license' !== $event_type ) {
		return $result;
	}

	$helper = WP_Job_Manager_Helper::instance();
	$helper->activate_license( $payload['wpjm_product_slug'], $payload['license_code'], $payload['email_address'] );

	$messages = $helper->get_messages( $payload['wpjm_product_slug'] );

	$errors = array_filter(
		$messages,
		function ( $message ) {
			return 'error' === $message['type'];
		}
	);

	if ( ! empty( $errors ) ) {
		return new \WP_Error( 'error', 'An error has occurred while installing ' . $payload['wpjm_product_slug'], $errors );
	}

	return $result;
}

const WPJM_WPCOM_PRODUCTS = [
	'wp-job-manager-applications',
	'wp-job-manager-resumes',
	'wp-job-manager-simple-paid-listings',
	'wp-job-manager-wc-paid-listings',
	'wp-job-manager-tags',
	'wp-job-manager-bookmarks',
	'wp-job-manager-alerts',
	'wp-job-manager-application-deadline',
	'wp-job-manager-embeddable-job-widget',
];

foreach ( WPJM_WPCOM_PRODUCTS as $wpjm_wpcom_product ) {
	add_filter( 'wpcom_marketplace_webhook_response_' . $wpjm_wpcom_product, 'wpjm_dotcom_marketplace_configure_license_for_wp_job_manager_addon', 10, 3 );
}

/**
 * Hide the license form on the licenses page for addons that are purchased from WP.com.
 *
 * @param bool   $status
 * @param string $product_slug
 *
 * @return false|mixed
 */
function wpjm_hide_addon_license_form_for_purchases_on_wpcom( $status, $product_slug ) {
	$subscriptions = get_option( 'wpcom_active_subscriptions', [] );
	if ( isset( $subscriptions[ $product_slug ] ) ) {
		return false;
	}

	return $status;
}

add_filter( 'wpjm_display_license_form_for_addon', 'wpjm_hide_addon_license_form_for_purchases_on_wpcom', 10, 2 );

/**
 * Display a notice after the license form for each addon.
 *
 * @param string $product_slug
 *
 * @return false|void
 */
function wpjm_display_managed_by_wpcom_notice_for_addon( $product_slug ) {
	$subscriptions = get_option( 'wpcom_active_subscriptions', [] );
	if ( ! isset( $subscriptions[ $product_slug ] ) ) {
		return;
	}

	$helper      = WP_Job_Manager_Helper::instance();
	$has_license = $helper->has_plugin_license( $product_slug );

	?>
	<form method="post" class="plugin-license-form">
		<?php wp_nonce_field( 'wpjm-manage-license' ); ?>
		<input type="hidden" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_action" name="action" value="flush-wpcom-license"/>
		<input type="hidden" id="<?php echo esc_attr( sanitize_title( $product_slug ) ); ?>_plugin" name="product_slug" value="<?php echo esc_attr( $product_slug ); ?>"/>

		<?php if ( $has_license ) : ?>
			<img src="<?php echo esc_url( JOB_MANAGER_PLUGIN_URL . '/assets/images/icons/checkmark-icon.svg' ); ?>" class='plugin-license-checkmark' aria-hidden='true' alt='<?php esc_attr_e( 'Plugin is activated', 'wp-job-manager' ); ?>'/>
			<label class="plugin-license-label"><?php esc_html_e( 'The license for this add-on is automatically managed by WordPress.com.', 'wp-job-manager' ); ?></label>
		<?php else : ?>
			<label class="plugin-license-label"><?php esc_html_e( 'The license is not activated. Click on the following button to activate it now.', 'wp-job-manager' ); ?></label>
			<input type="submit" class="button plugin-license-button" name="submit" value="<?php esc_attr_e( 'Activate License', 'wp-job-manager' ); ?>" />
		<?php endif; ?>
	</form>
	<?php
}

add_action( 'wpjm_manage_license_page_after_license_form', 'wpjm_display_managed_by_wpcom_notice_for_addon' );
