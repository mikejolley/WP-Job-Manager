<?php
/**
 * Preview and settings for UI elements.
 *
 * @package wp-job-manager
 * @since 2.2.0
 */

namespace WP_Job_Manager\UI;

use WP_Job_Manager\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Preview and settings for UI elements.
 *
 * @access private
 *
 * @internal
 */
class UI_Settings {

	use Singleton;

	/**
	 * Instance constructor
	 */
	private function __construct() {
		add_shortcode( 'job_manager_ui_test', [ $this, 'preview_ui_elements' ] );

	}

	/**
	 * Output previews of UI elements in various scenarios.
	 *
	 * @access private
	 *
	 * @return string Generated HTML of UI elements.
	 */
	public function preview_ui_elements() {
		$elements = [];

		$modal         = new Modal_Dialog();
		$modal_content = Notice::render(
			[
				'title'   => 'Test Modal Dialog',
				'message' => 'This is a test dialog message.',
				'buttons' => [
					[
						'label' => 'Primary Button',
						'url'   => '/',
					],
				],
				'links'   => [
					[
						'label'   => 'Close',
						'onclick' => '{close}',
					],
				],
			]
		);

		$elements[] = '<h2>Modal Dialog</h2>';

		$elements[] = Notice::dialog(
			[
				'message' => 'Test opening a modal dialog from here.',
				'buttons' => [
					[
						'label'   => 'Open Modal',
						'onclick' => $modal->open(),
					],
				],
			]
		);

		$elements[] = $modal->render( $modal_content );

		$elements[] = '<h2>Notices</h2>';

		$elements[] = Notice::success( 'Notice rendered successfully.' );
		$elements[] = Notice::error( 'Invalid notice message.' );
		$elements[] = Notice::error(
			[
				'classes' => [ 'actions-right' ],
				'message' => 'A user account already exists for this e-mail.',
				'buttons' => [
					[
						'url'   => '/',
						'label' => 'Sign in',
					],
				],
			]
		);

		$elements[] = Notice::render(
			[
				'title'   => 'Job listing active',
				'icon'    => 'info',
				'classes' => [ 'color-strong', 'message-icon' ],
				'message' => 'Expires  in 25 days.',
			]
		);

		$elements[] = Notice::render(
			[
				'message' => 'This is a test notice message with a checkmark.',
				'icon'    => 'check',
			]
		);

		$elements[] = Notice::render(
			[
				'message' => 'This is a test notice message.',
			]
		);

		$elements[] = Notice::render(
			[
				'classes' => [ 'type-hint' ],
				'message' => 'Already have an account?',
				'buttons' => [
					[
						'label' => 'Sign In',
						'url'   => '/',
					],
				],
			]
		);

		$elements[] = Notice::dialog(
			[
				'message' => __( 'Sign in or create an account to manage your listings.', 'wp-job-manager' ),
				'buttons' => [
					[
						'url'   => '/',
						'label' => 'Sign in',
					],
					[
						'url'   => '/',
						'label' => 'Create Account',
					],
				],
			]
		);

		$elements[] = Notice::render(
			[
				'classes' => [ 'color-error' ],
				'message' => 'This is a test notice message with an alert icon and red colors.',
				'icon'    => 'alert',
			]
		);

		$elements[] = Notice::render(
			[
				'classes' => [ 'color-success' ],
				'message' => 'This is a super green test notice message.',
				'icon'    => 'check',
			]
		);

		$elements[] = Notice::render(
			[
				'classes' => [ 'color-info' ],
				'message' => 'This is an informational blue message.',
				'icon'    => 'info',
			]
		);

		$elements[] = Notice::render(
			[
				'title' => 'Custom SVG Icon',
				'icon'  => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
  <path stroke="#1E1E1E" stroke-width="1.5" d="M19.25 19.25v-9.5H4.75v9.5z"/>
  <path fill="#1E1E1E" fill-rule="evenodd" d="M13 19V9h-1.5v10H13Z" clip-rule="evenodd"/>
  <path stroke="#1E1E1E" stroke-width="1.5" d="M16.5 6.5c0 .97-.78 1.75-1.75 1.75H13V6.5a1.75 1.75 0 1 1 3.5 0ZM8 6.5c0 .97.78 1.75 1.75 1.75h1.75V6.5a1.75 1.75 0 1 0-3.5 0Z"/>
</svg>
',
			]
		);

		$elements[] = Notice::render(
			[
				'title'   => 'Test Notice Created',
				'icon'    => 'check',
				'message' => 'This is a test Job Manager notice. Should be large with a checkmark.',
				'buttons' => [
					[
						'label' => 'Primary Button',
						'url'   => '/',
					],
					[
						'label' => 'Secondary Button',
						'url'   => '/',
					],
				],
				'links'   => [
					[
						'label' => 'Action',
						'url'   => '/',
					],
					[
						'label' => 'Action',
						'url'   => '/',
					],
				],
			]
		);

		$elements[] = Notice::render(
			[
				'title'   => 'Test Notice Created',
				'message' => 'This is a test Job Manager notice. Should be large with a checkmark.',
				'buttons' => [
					[
						'label'   => 'Light Button',
						'url'     => '/',
						'primary' => false,
					],
				],
				'links'   => [
					[
						'label' => 'Action',
						'url'   => '/',
					],
				],
			]
		);

		$elements[] = Notice::render(
			[
				'title'   => 'Test Notice Created',
				'message' => 'This is a test Job Manager notice.',
				'links'   => [
					[
						'label' => 'Action',
						'url'   => '/',
					],
					[
						'label' => 'Action',
						'url'   => '/',
					],
				],
			]
		);

		$elements[] = Notice::render(
			[
				'title'   => 'Test Notice Created',
				'icon'    => 'check',
				'message' => 'This is a test Job Manager notice. Should be large with a checkmark.',
				'buttons' => [
					[
						'label' => 'Primary Button',
						'url'   => '/',
					],
				],
			]
		);

		$elements[] = Notice::render(
			[
				'icon'    => 'check',
				'message' => 'This is a test Job Manager notice, with an icon, message and an action.',
				'links'   => [
					[
						'label' => 'Action',
						'url'   => '/',
					],
				],
			]
		);

		$elements[] = Notice::render(
			[
				'icon'    => 'check',
				'classes' => [ 'alignwide' ],
				'message' => 'This is a wide Job Manager notice, with an icon, message and an action. Actions are on the side.',
				'links'   => [
					[
						'label' => 'Action',
						'url'   => '/',
					],
				],
			]
		);

		$elements[] = Notice::render(
			[
				'message' => 'This is a test Job Manager notice, with only a message and an action.',
				'links'   => [
					[
						'label' => 'Action',
						'url'   => '/',
					],
				],
			]
		);

		$elements[] = Notice::render(
			[
				'message' => 'This is a test Job Manager notice.',
				'buttons' => [
					[
						'label' => 'Primary Button',
						'url'   => '/',
					],
				],
			]
		);

		$elements[] = Notice::render(
			[
				'message' => 'This is a test Job Manager notice.',
				'buttons' => [
					[
						'label'   => 'Outlined Button',
						'url'     => '/',
						'primary' => false,
					],
				],
			]
		);

		return implode( '', $elements );

	}
}
