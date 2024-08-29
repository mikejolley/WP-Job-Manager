<?php
/**
 * Content for job submission (`[submit_job_form]`) shortcode.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/job-submit.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     $$next_version$$
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $post, $job_manager;
$captcha_version = WP_Job_Manager\WP_Job_Manager_Recaptcha::instance()->get_recaptcha_version();

if ( job_manager_user_can_view_submit_job_listing( $post->ID ) ) : ?>
	<form action="<?php echo esc_url( $action ); ?>" method="post" id="submit-job-form" class="job-manager-form" enctype="multipart/form-data">

		<?php
		if ( isset( $resume_edit ) && $resume_edit ) {
			printf( '<p><strong>' . esc_html__( "You are editing an existing job. %s", 'wp-job-manager' ) . '</strong></p>', '<a href="?job_manager_form=submit-job&new=1&key=' . esc_attr( $resume_edit ) . '">' . esc_html__( 'Create A New Job', 'wp-job-manager' ) . '</a>' );
		}
		?>

		<?php do_action( 'submit_job_form_start' ); ?>

		<?php if ( apply_filters( 'submit_job_form_show_signin', true ) ) : ?>

			<?php get_job_manager_template( 'account-signin.php' ); ?>

		<?php endif; ?>

		<?php if ( job_manager_user_can_post_job() || job_manager_user_can_edit_job( $job_id ) ) : ?>

			<!-- Job Information Fields -->
			<?php do_action( 'submit_job_form_job_fields_start' ); ?>

			<?php foreach ( $job_fields as $key => $field ) : ?>
				<fieldset class="fieldset-<?php echo esc_attr( $key ); ?> fieldset-type-<?php echo esc_attr( $field['type'] ); ?>">
					<label for="<?php echo esc_attr( $key ); ?>"><?php echo wp_kses_post( $field['label'] ) . wp_kses_post( apply_filters( 'submit_job_form_required_label', $field['required'] ? '' : ' <small>' . __( '(optional)', 'wp-job-manager' ) . '</small>', $field ) ); ?></label>
					<div class="field <?php echo $field['required'] ? 'required-field' : ''; ?>">
						<?php get_job_manager_template( 'form-fields/' . $field['type'] . '-field.php', [ 'key' => $key, 'field' => $field ] ); ?>
					</div>
				</fieldset>
			<?php endforeach; ?>

			<?php do_action( 'submit_job_form_job_fields_end' ); ?>

			<!-- Company Information Fields -->
			<?php if ( $company_fields ) : ?>
				<h2><?php esc_html_e( 'Company Details', 'wp-job-manager' ); ?></h2>

				<?php do_action( 'submit_job_form_company_fields_start' ); ?>

				<?php foreach ( $company_fields as $key => $field ) : ?>
					<fieldset class="fieldset-<?php echo esc_attr( $key ); ?> fieldset-type-<?php echo esc_attr( $field['type'] ); ?>">
						<label for="<?php echo esc_attr( $key ); ?>"><?php echo wp_kses_post( $field['label'] ) . wp_kses_post( apply_filters( 'submit_job_form_required_label', $field['required'] ? '' : ' <small>' . __( '(optional)', 'wp-job-manager' ) . '</small>', $field ) ); ?></label>
						<div class="field <?php echo $field['required'] ? 'required-field' : ''; ?>">
							<?php get_job_manager_template( 'form-fields/' . $field['type'] . '-field.php', [ 'key' => $key, 'field' => $field ] ); ?>
						</div>
					</fieldset>
				<?php endforeach; ?>

				<?php do_action( 'submit_job_form_company_fields_end' ); ?>
			<?php endif; ?>

			<?php do_action( 'submit_job_form_end' ); ?>

			<p>
				<input type="hidden" name="job_manager_form" value="<?php echo esc_attr( $form ); ?>" />
				<input type="hidden" name="job_id" value="<?php echo esc_attr( $job_id ); ?>" />
				<input type="hidden" name="step" value="<?php echo esc_attr( $step ); ?>" />
				<input type="submit" name="submit_job" class="button"
				<?php if ('v3' === $captcha_version) echo 'onclick="jm_job_submit_click(event)"'; ?> value="<?php echo esc_attr($submit_button_text); ?>" />
				<?php
				if ( isset( $can_continue_later ) && $can_continue_later ) {
					echo '<input type="submit" name="save_draft" class="button secondary save_draft" value="' . esc_attr__( 'Save Draft', 'wp-job-manager' ) . '" formnovalidate />';
				}
				?>
				<span class="spinner" style="background-image: url(<?php echo esc_url( includes_url( 'images/spinner.gif' ) ); ?>);"></span>
			</p>

		<?php else : ?>

			<?php do_action( 'submit_job_form_disabled' ); ?>

		<?php endif; ?>
	</form>
<?php else : ?>
	<?php get_job_manager_template_part( 'access-denied', 'submit-job_listing' ); ?>
<?php endif; ?>