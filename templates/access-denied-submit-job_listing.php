<?php
/**
 * Message to display when access is denied to a submit a job listing.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/access-denied-submit-job_listing.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @since       $$next_version$$
 * @version     $$next_version$$
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<p class="job-manager-error"><?php esc_html_e( 'Sorry, you do not have permission to submit a job listing.', 'wp-job-manager' ); ?></p>
