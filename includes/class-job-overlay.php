<?php
/**
 * File containing the class Job_Overlay.
 *
 * @package wp-job-manager
 */

namespace WP_Job_Manager;

use WP_Job_Manager\UI\Modal_Dialog;
use WP_Job_Manager\UI\Notice;
use WP_Job_Manager\UI\UI;
use WP_Job_Manager\UI\UI_Elements;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Job details overlay.
 *
 * @since 2.3.0
 */
class Job_Overlay {

	use Singleton;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'job_manager_ajax_job_dashboard_overlay', [ $this, 'ajax_job_overlay' ] );
		add_action( 'wp_ajax_job_dashboard_overlay', [ $this, 'ajax_job_overlay' ] );
		add_action( 'current_screen', [ $this, 'init_admin_dashboard_overlay' ], 10 );
		add_action( 'job_manager_job_overlay_footer', [ $this, 'output_footer_actions' ], 10 );

	}

	/**
	 * Render the job dashboard overlay content for an AJAX request.
	 */
	public function ajax_job_overlay() {

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce check.
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_REQUEST['_wpnonce'] ), 'job_dashboard_overlay' ) ) {
			wp_send_json_error(
				Notice::error(
					[
						'message' => __( 'Invalid request.', 'wp-job-manager' ),
						'classes' => [ 'type-dialog' ],
					]
				)
			);

			return;
		}

		$job_id = isset( $_REQUEST['job_id'] ) ? absint( $_REQUEST['job_id'] ) : null;

		$job = $job_id ? get_post( $job_id ) : null;

		$shortcode = Job_Dashboard_Shortcode::instance();

		if ( ! $shortcode->can_manage_job( $job ) ) {
			wp_send_json_error(
				Notice::error(
					[
						'message' => __( 'Invalid Job ID.', 'wp-job-manager' ),
						'classes' => [ 'type-dialog' ],
					]
				)
			);

			return;
		}

		$content = $this->render_job_overlay( $job );

		wp_send_json_success( $content );

	}

	/**
	 * Output the modal element.
	 */
	public function output_modal_element() {

		wp_enqueue_style( 'wp-job-manager-job-dashboard' );
		wp_enqueue_script( 'wp-job-manager-job-dashboard' );

		$overlay = new Modal_Dialog(
			[
				'id'    => 'jmDashboardOverlay',
				'class' => 'jm-dashboard__overlay',
			]
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Modal_Dialog class.
		echo $overlay->render( '' );
	}

	/**
	 * Get the job overlay content.
	 *
	 * @param \WP_Post $job
	 *
	 * @return string
	 */
	private function render_job_overlay( $job ) {

		ob_start();

		get_job_manager_template(
			'job-dashboard-overlay.php',
			[
				'job' => $job,
			]
		);

		$content = ob_get_clean();

		return $content;
	}

	/**
	 * Load and output overlay dependencies on the job listings screen.
	 *
	 * @output Modal HTML.
	 *
	 * @return void
	 */
	public function init_admin_dashboard_overlay() {

		if ( ! Stats::is_enabled() ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || 'edit-job_listing' !== $screen->id ) {
			return;
		}

		$this->init_dashboard_overlay();
		add_action( 'admin_footer', [ $this, 'output_modal_element' ] );
	}

	/**
	 * Load scripts and output HTML skeleton for the job dashboard overlay.
	 *
	 * @output Modal HTML.
	 *
	 * @return void
	 */
	public function init_dashboard_overlay() {

		UI::ensure_styles();
		\WP_Job_Manager::register_script( 'wp-job-manager-job-dashboard', 'js/job-dashboard.js', null, true );
		\WP_Job_Manager::register_style( 'wp-job-manager-job-dashboard', 'css/job-dashboard.css', [ 'wp-job-manager-ui' ] );

		$endpoint = is_admin()
			? add_query_arg( [ 'action' => 'job_dashboard_overlay' ], admin_url( 'admin-ajax.php' ) )
			: \WP_Job_Manager_Ajax::get_endpoint( 'job_dashboard_overlay' );
		$endpoint = wp_nonce_url( $endpoint, 'job_dashboard_overlay' );

		wp_localize_script(
			'wp-job-manager-job-dashboard',
			'job_manager_job_dashboard',
			[
				'i18nConfirmDelete' => esc_html__( 'Are you sure you want to delete this listing?', 'wp-job-manager' ),
				'overlayEndpoint'   => $endpoint,
				'statsEnabled'      => \WP_Job_Manager\Stats::is_enabled(),
			]
		);
	}

	/**
	 * Output the job actions in the overlay footer.
	 *
	 * @param \WP_Post $job
	 */
	public function output_footer_actions( $job ) {

		if ( is_admin() ) {
			return;
		}

		$job_actions = Job_Dashboard_Shortcode::instance()->get_job_actions( $job );

		$buttons = [];
		$actions = [];
		if ( ! empty( $job_actions ) ) {
			$primary = Job_Dashboard_Shortcode::get_primary_action( $job, $job_actions );

			if ( $primary ) {
				$buttons[] = [
					'label'   => $primary['label'],
					'url'     => $primary['url'],
					'class'   => 'job-dashboard-action-' . esc_attr( $primary['name'] ),
					'primary' => false,
				];
			}

			foreach ( $job_actions as $action ) {
				if ( ! empty( $primary ) && $primary['name'] === $action['name'] ) {
					continue;
				}
				$actions[] = [
					'label' => $action['label'],
					'url'   => $action['url'],
					'class' => 'job-dashboard-action-' . esc_attr( $action['name'] ),
				];
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in UI classes.
		echo UI_Elements::actions( $buttons, $actions );

	}


}
