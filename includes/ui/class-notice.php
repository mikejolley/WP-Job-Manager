<?php
/**
 * Notice component for the frontend.
 *
 * @package wp-job-manager
 * @since 2.2.0
 */

namespace WP_Job_Manager\UI;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Notice component for the frontend.
 *
 * @since 2.2.0
 *
 * @internal This API is still under development and subject to change.
 * @access private
 */
class Notice {

	/**
	 * A success message.
	 *
	 * @param string|array $args A message, or an array of options accepted by Notice::render.
	 */
	public static function success( $args ) {
		$args = is_array( $args ) ? $args : [ 'message' => $args ];

		return self::render(
			array_merge(
				[ 'icon' => 'check-circle' ],
				$args
			)
		);
	}

	/**
	 * An error message.
	 *
	 * @param string|array $args A message, or an array of options accepted by Notice::render.
	 */
	public static function error( $args ) {
		$args = is_array( $args ) ? $args : [ 'message' => $args ];

		return self::render(
			array_merge(
				[ 'icon' => 'alert' ],
				$args,
				[
					'classes' => array_merge( [ 'color-error' ], $args['classes'] ?? [] ),
				]
			)
		);
	}

	/**
	 * A permanent informational message.
	 *
	 * @param string|array $args A message, or an array of options accepted by Notice::render.
	 */
	public static function hint( $args ) {
		$args = is_array( $args ) ? $args : [ 'message' => $args ];

		return self::render(
			array_merge(
				$args,
				[
					'classes' => array_merge( [ 'type-hint' ], $args['classes'] ?? [] ),
				],
			)
		);
	}

	/**
	 * A permanent message that requires action to proceed.
	 *
	 * @param string|array $args
	 */
	public static function dialog( $args ) {
		$args = is_array( $args ) ? $args : [ 'message' => $args ];

		return self::render(
			array_merge(
				$args,
				[
					'classes' => array_merge( [ 'type-dialog' ], $args['classes'] ?? [] ),
				]
			)
		);
	}

	/**
	 * Notice element.
	 *
	 * @param array $options {
	 *    Options for the notice.
	 *
	 * @type string $id Unique ID of the notice.
	 * @type string $title Optional title.
	 * @type string $icon Notice icon. Should be a supported icon name, or safe SVG code.
	 * @type string $message Main notice message.
	 * @type string $html Raw HTML content. User input must be escaped by the caller function.
	 * @type array  $buttons Action links styled as buttons.
	 * @type array  $links Action links.
	 * @type array  $classes Additional classes for the notice. Possible options:
	 *      color-error:     Error-style coloring.
	 *      color-success:   Green highlight.
	 *      color-info:      Blue highlight.
	 *      color-strong:    Stronger border opacity highlight.
	 *      type-hint:       Variation for a persistent hint.
	 *      message-icon:    Show the icon next to the message instead of the title.
	 *      alignwide:       Wide notice, with actions on the side.
	 * }
	 *
	 * @return string HTML.
	 */
	public static function render( $options ) {

		UI::ensure_styles();

		$options = wp_parse_args(
			$options,
			[
				'id'      => '',
				'title'   => '',
				'icon'    => '',
				'message' => '',
				'html'    => '',
				'buttons' => [],
				'links'   => [],
				'classes' => [],
			]
		);

		$actions = UI_Elements::actions( $options['buttons'], $options['links'] );

		$icon = UI_Elements::icon( $options['icon'] );

		ob_start();

		$template_options = [
			'options'      => $options,
			'title'        => $options['title'],
			'classes'      => $options['classes'] ?? [],
			'message'      => $options['message'],
			'content_html' => $options['html'],
			'icon_html'    => $icon,
			'actions_html' => $actions,
		];

		get_job_manager_template(
			'notice.php',
			$template_options
		);

		$notice_html = ob_get_clean();

		/**
		 * Filters notices. Return false to disable the notice.
		 *
		 * @since 2.2.0
		 *
		 * @param string $notice_html Generated HTML for the notice.
		 * @param array  $options Notice template options.
		 */
		$notice_html = apply_filters( 'wpjm_notice', $notice_html, $template_options );

		if ( ! empty( $options['id'] ) ) {
			/**
			 * Filters an individual notice. Return false to disable the notice.
			 *
			 * @since 2.2.0
			 *
			 * @param string $notice_html Generated HTML for the notice.
			 * @param array  $options Notice template options.
			 */
			$notice_html = apply_filters( 'wpjm_notice_' . $options['id'], $notice_html, $template_options );
		}

		return is_string( $notice_html ) ? $notice_html : '';
	}

}
