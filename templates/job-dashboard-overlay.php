<?php
/**
 * Job dashboard overlay.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     2.3.0
 *
 * @var WP_Post $job Array of job post results.
 */

use WP_Job_Manager\Job_Dashboard_Shortcode;
use WP_Job_Manager\UI\UI_Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$submit_job_form_page_id = get_option( 'job_manager_submit_job_form_page_id' );
?>

<div class="jm-job-overlay jm-dashboard">
	<div class="jm-job-overlay-header">
		<div class="">

			<div class="job_title" role="heading"><?php echo esc_html( get_the_title( $job ) ?? $job->ID ); ?></div>
			<?php Job_Dashboard_Shortcode::the_status( $job ); ?>
		</div>
		<div class="actions">
			<?php
			echo UI_Elements::button( [
				'url'   => get_permalink( $job->ID ),
				'label' => __( 'View', 'wp-job-manager' ),
			], 'jm-ui-button--link' );
			?>
		</div>
	</div>
	<div class="jm-job-overlay-content">
		<div class="jm-job-overlay-details-box">

			<div class="jm-ui-row" style="justify-content: space-between; align-items: flex-start">
				<div class="jm-ui-col">
					<?php Job_Dashboard_Shortcode::the_location( $job ); ?>
					<div class="jm-ui-row">
						<?php the_company_logo( 'thumbnail', '', $job ); ?>
						<?php echo esc_html( get_the_company_name( $job ) ); ?>
					</div>
				</div>
				<div class="jm-ui-col">
					<?php do_action( 'job_manager_job_dashboard_column_date', $job ); ?>
				</div>
			</div>

		</div>
		<?php do_action( 'job_manager_job_overlay_content', $job ); ?>

	</div>
	<div class="jm-job-overlay-footer"><?php do_action( 'job_manager_job_overlay_footer', $job ); ?></div>
</div>
