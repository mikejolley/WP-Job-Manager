<?php
/**
 * UI Modal Dialog class.
 *
 * @package wp-job-manager
 * @since 2.2.0
 */

namespace WP_Job_Manager\UI;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Modal dialog component for the frontend.
 *
 * @since 2.2.0
 *
 * @internal This API is still under development and subject to change.
 * @access private
 */
class Modal_Dialog {

	/**
	 * Unique ID for the dialog.
	 *
	 * @var string
	 */
	private string $id;

	/**
	 * Options for the dialog.
	 *
	 * @var array
	 */
	private array $options;

	/**
	 * Create a new dialog instance.
	 *
	 * @param array $options {
	 *    Optional. Options for the dialog.
	 *
	 * @type string $id Unique ID for the dialog. Auto-generated if left empty.
	 * }
	 */
	public function __construct( $options = [] ) {
		$options = wp_parse_args(
			$options,
			[
				'id'    => null,
				'class' => null,
				'style' => null,
			]
		);

		$this->options = $options;
		$this->id      = $options['id'] ?? 'jm' . uniqid();
	}

	/**
	 * Return an onclick handler to open the dialog.
	 *
	 * @return string
	 */
	public function open() {
		return esc_attr( $this->id . '.showModal(); event.preventDefault();' );
	}

	/**
	 * Render the dialog with the given HTML content.
	 *
	 * @param string $content Escaped HTML content.
	 *
	 * @return string HTML element
	 */
	public function render( $content ) {
		$id          = $this->id;
		$close_label = __( 'Close', 'wp-job-manager' );

		$class   = esc_attr( $this->options['class'] ?? '' );
		$style   = esc_attr( $this->options['style'] ?? '' );
		$content = str_replace( '{close}', $id . '.close(); event.preventDefault();', $content );

		return <<<HTML
<dialog class="jm-dialog" id="{$id}">
	<div class="jm-dialog-open">
		<div class="jm-dialog-backdrop" onclick="{$id}.close()"></div>
		<div class="jm-dialog-modal {$class}" style="{$style}">
			<div class="jm-dialog-modal-container">
				<div class="jm-dialog-modal-content">{$content}</div>
				<a href="#" role="button" class="jm-ui-button--icon jm-dialog-close" onclick="{$id}.close();  event.preventDefault();" aria-label="{$close_label}"><span class="jm-ui-button__icon"></span></a>
			</div>
		</div>
	</div>
</dialog>
HTML;

	}

}
