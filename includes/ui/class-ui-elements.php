<?php
/**
 * UI Elements.
 *
 * @package wp-job-manager
 * @since 2.2.0
 */

namespace WP_Job_Manager\UI;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Collection of rendering functions for various UI elements.
 *
 * @since 2.2.0
 *
 * @internal This API is still under development and subject to change.
 * @access private
 */
class UI_Elements {

	/**
	 * Generate HTML for a notice icon.
	 *
	 * @param string|null $icon Icon name or a safe SVG code.
	 * @param string      $label Accessible icon label.
	 *
	 * @return string Icon HTML.
	 */
	public static function icon( $icon, $label = '' ) {

		if ( empty( $icon ) ) {
			return '';
		}

		$html = '';

		$is_classname = preg_match( '/^[\w-]+$/i', $icon );
		$is_url       = preg_match( '/^url\(/i', $icon );
		$is_svg       = preg_match( '/^<svg/i', $icon );

		$html = '<span class="jm-ui-icon' . ( $is_svg ? ' jm-ui-icon--svg' : '' ) . '"';

		if ( $is_classname ) {
			$html .= ' data-icon="' . esc_attr( $icon ) . '"';
		}

		if ( $label ) {
			$html .= ' aria-label="' . esc_attr( $label ) . '"';
		}

		if ( $is_url ) {
			$html .= ' style="mask-image: ' . esc_attr( $icon ) . '"';
		}

		$html .= '>';

		if ( $is_svg ) {
			$html .= self::esc_svg( $icon );
		}

		$html .= '</span>';

		return $html;
	}

	/**
	 * Generate HTML for a button or action link.
	 *
	 * @param array  $args Button options.
	 * @param string $class Base classname.
	 *
	 * @return string Button HTML.
	 */
	public static function button( $args, $class ) {

		if ( empty( $args ) ) {
			return '';
		}

		$args = wp_parse_args(
			$args,
			[
				'label'   => '',
				'url'     => '',
				'onclick' => '',
				'class'   => '',
			]
		);

		if ( empty( $args['url'] ) ) {
			$args['url'] = '#';
		}

		$attrs = [
			'class' => implode( ' ', [ $class, $args['class'] ] ),
			'href'  => esc_url( $args['url'] ),
		];

		if ( ! empty( $args['onclick'] ) ) {
			$attrs['onclick'] = $args['onclick'];
			$attrs['role']    = 'button';
		}

		return '<a ' . self::html_attrs( $attrs ) . '><span>' . esc_html( $args['label'] ) . '</span></a>';
	}

	/**
	 * Generate HTML for a row of actions. Can contain button and/or link actions.
	 *
	 * Unless set otherwise, the first button will be styled as primary, and the rest as outline.
	 * Links will match button dimensions if buttons are also present.
	 *
	 * @param array $buttons Button definitions.
	 * @param array $links Link definitions.
	 *
	 * @return string Actions HTML.
	 */
	public static function actions( array $buttons = [], array $links = [] ) {

		$actions_html = [];

		if ( ! empty( $buttons ) ) {
			foreach ( $buttons as $index => $button ) {
				$primary        = $button['primary'] ?? ( 0 === $index );
				$class          = $primary ? 'jm-ui-button' : 'jm-ui-button--outline';
				$actions_html[] = self::button( $button, $class );
			}
		}

		if ( ! empty( $links ) ) {
			foreach ( $links as $link ) {
				$class          = $buttons ? 'jm-ui-button--link' : 'jm-ui-link';
				$actions_html[] = self::button( $link, $class );
			}
		}

		if ( empty( $actions_html ) ) {
			return '';
		}

		return '<div class="jm-ui-actions-row">' . implode( '', $actions_html ) . '</div>';

	}

	/**
	 * Generate an actions button with a dropdown menu.
	 *
	 * @param string $content Escaped HTML content.
	 *
	 * @return string Actions menu HTML.
	 */
	public static function actions_menu( $content ) {
		$label = __( 'Actions', 'wp-job-manager' );

		$close = esc_attr( 'event.currentTarget.contains(event.relatedTarget) || setTimeout(() => this.open = false, 100 )' );

		return <<<HTML
<details class="jm-ui-actions-menu" onfocusout="{$close}">
	<summary tabindex="0" class="jm-ui-action-menu__open-button jm-ui-button--icon"
		aria-label="{$label}">
		<span class="jm-ui-button__icon"></span>
	</summary>
	<div class="jm-ui-action-menu__content">
	{$content}
	</div>
</details>
HTML;

	}

	/**
	 * Generate HTML for a relative time string.
	 *
	 * @param string|\DateTimeInterface|int $time Time string, DateTime object, or timestamp.
	 * @param string                        $format_string Sprintf-compatible format string. Should contain a %s placeholder for the time.
	 *
	 * @return string
	 */
	public static function rel_time( $time, $format_string = '%s' ) {

		if ( is_string( $time ) ) {
			$timestamp = strtotime( $time );
		}

		if ( $time instanceof \DateTimeInterface ) {
			$timestamp = $time->getTimestamp();
		}

		if ( is_numeric( $time ) ) {
			$timestamp = $time;
		}

		if ( empty( $timestamp ) ) {
			return '';
		}

		$abs_time = date_i18n( get_option( 'date_format' ), $timestamp );
		$rel_time = human_time_diff( $timestamp );

		return '<time datetime="' . esc_attr( $abs_time ) . '" title="' . esc_attr( $abs_time ) . '">' . esc_html( sprintf( $format_string, $rel_time ) ) . '</time>';

	}

	/**
	 * Escape SVG code.
	 *
	 * TODO Extend rules to support more SVG tags and attributes.
	 *
	 * @param string $code SVG code.
	 *
	 * @return string Sanitized SVG code.
	 */
	private static function esc_svg( $code ) {
		$kses_defaults = wp_kses_allowed_html( 'post' );
		$svg_args      = [
			'svg'  => [
				'class'           => true,
				'aria-hidden'     => true,
				'aria-labelledby' => true,
				'role'            => true,
				'xmlns'           => true,
				'width'           => true,
				'height'          => true,
				'viewbox'         => true,
				'fill'            => true,
			],
			'path' => [
				'd'            => true,
				'fill'         => true,
				'fill-rule'    => true,
				'stroke-width' => true,
				'stroke'       => true,
				'clip-rule'    => true,
			],
		];

		$allowed_tags = array_merge( $kses_defaults, $svg_args );

		return wp_kses( $code, $allowed_tags );
	}

	/**
	 * Generate HTML attributes string. Escapes attributes.
	 *
	 * @param array $attributes Attributes array with key => value pairs.
	 *
	 * @return string HTML attributes string.
	 */
	private static function html_attrs( $attributes ) {
		$attributes_html = array_map(
			function( $key, $value ) {
				return esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
			},
			array_keys( $attributes ),
			array_values( $attributes )
		);

		return implode( ' ', $attributes_html );
	}
}
