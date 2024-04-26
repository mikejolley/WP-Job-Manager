<?php
/**
 * Frontend UI elements of Job Manager.
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
 * Frontend UI elements of Job Manager.
 *
 * @since 2.2.0
 *
 * @internal This API is still under development and subject to change.
 * @access private
 */
class UI {

	use Singleton;

	/**
	 * Whether the UI styles should be loaded the page.
	 *
	 * @var bool
	 */
	private bool $has_ui;

	/**
	 * An array of css variables to be enqueued with the styles.
	 *
	 * @var array
	 */
	private array $css_variables;

	/**
	 * Instance constructor
	 */
	private function __construct() {
		$this->has_ui        = false;
		$this->css_variables = [];
		add_action( 'init', [ $this, 'register_styles' ], 5 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ], 99 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_styles' ], 99 );
	}

	/**
	 * Register styles.
	 *
	 * @access private
	 */
	public function register_styles() {
		\WP_Job_Manager::register_style( 'wp-job-manager-ui', 'css/ui.css', [] );
		\WP_Job_Manager::register_script( 'wp-job-manager-ui-theme-support', 'js/ui-theme-support.js' );
	}

	/**
	 * Enqueue styles and inline CSS.
	 *
	 * @access private
	 */
	public function enqueue_styles() {
		if ( $this->has_ui || wp_style_is( 'wp-job-manager-ui', 'enqueued' ) ) {
			wp_enqueue_style( 'wp-job-manager-ui' );

			/**
			 * Filter whether to load the script that detects theme colors and styles for the plugin's UI elements.
			 */
			if ( apply_filters( 'job_manager_ui_theme_support_script', true ) ) {
				wp_enqueue_script( 'wp-job-manager-ui-theme-support' );
			}

			wp_add_inline_style( 'wp-job-manager-ui', $this->generate_inline_css() );

		}
	}

	/**
	 * Request the styles to be loaded for the page.
	 *
	 * @param array $css_variables An array of CSS variables to be enqueued: <variable_name> => <value>.
	 */
	public static function ensure_styles( array $css_variables = [] ) {
		self::instance()->has_ui        = true;
		self::instance()->css_variables = array_merge( self::instance()->css_variables, $css_variables );

		if ( did_action( 'wp_enqueue_scripts' ) ) {
			self::instance()->enqueue_styles();
		}
	}

	/**
	 * Generates CSS to be inlined with the UI styles.
	 *
	 * @return string The CSS.
	 */
	private function generate_inline_css() {

		$vars = $this->css_variables;

		/**
		 * Set the accent color for frontend components. Leave blank to auto-detect and use the link color.
		 *
		 * @param string|false $color CSS color definition.
		 *
		 * @since 2.3.0
		 */
		$vars['--jm-ui-accent-color'] = apply_filters( 'job_manager_ui_accent_color', $vars['--jm-ui-accent-color'] ?? false );

		$css = ':root {';

		foreach ( $vars as $name => $value ) {
			if ( empty( $value ) ) {
				continue;
			}
			$css .= esc_attr( $name ) . ': ' . esc_attr( $value ) . ';';
		}

		$css .= '}';

		return $css;
	}
}
