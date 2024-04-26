<?php
/**
 * Shows the `recaptcha` form field on job listing forms.
 *
 * This template can be overridden by copying it to yourtheme/job_manager/form-fields/recaptcha-v3-field.php.
 *
 * @see         https://wpjobmanager.com/document/template-overrides/
 * @author      Automattic
 * @package     wp-job-manager
 * @category    Template
 * @version     2.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
$site_key = get_option( 'job_manager_recaptcha_site_key' );

if ( doing_action( 'submit_resume_form_resume_fields_end' ) ) {
	$form = 'submit_resume';
} elseif ( doing_action( 'job_application_form_fields_end' ) ) {
	$form = 'job-manager-application-form';
} else {
	$form = 'submit_job';
}
?>
<script>
	let form = document.getElementById( 'submit-job-form' ) ||
			document.getElementById( 'submit-resume-form' ) ||
			document.querySelector( '.job-manager-application-form' );

	function jm_job_submit_click(e) {
		e.preventDefault();
		grecaptcha.ready(function() {
			grecaptcha.execute( decodeURIComponent( '<?php echo rawurlencode( (string) $site_key ) ?>' ), { action: 'submit' } ).then( function( token ) {
				document.getElementById( 'g-recaptcha-response' ).value = token;
				form.submit();
			});
		});
	}
</script>

<input type="hidden" name="g-recaptcha-response" id="g-recaptcha-response">
<input type="hidden" name="<?php echo esc_attr( $form ) ?>" value="true">
