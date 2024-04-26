<?php
/**
 * Autoload plugin classes.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoload plugin classes.
 *
 * @since 2.3.0
 */
class WP_Job_Manager_Autoload {

	/**
	 * Namespace -> directory mappings.
	 *
	 * @var array
	 */
	private static $autoload_map = [];

	/**
	 * Add the autoloader.
	 */
	public static function init() {
		spl_autoload_register( [ self::class, 'autoload' ] );
	}

	/**
	 * Register a new plugin with a class prefix and directory to autoload.
	 *
	 * @param string $namespace Root namespace. Should start with WP_Job_Manager_.
	 * @param string $dir Directory to autoload.
	 */
	public static function register( $namespace, $dir ) {
		self::$autoload_map[ $namespace ] = $dir;
	}

	/**
	 * Autoload plugin classes.
	 *
	 * @access private
	 *
	 * @param string $class_name Class name.
	 */
	public static function autoload( $class_name ) {

		if ( ! str_starts_with( $class_name, 'WP_Job_Manager' ) || ! str_contains( $class_name, '\\' ) ) {
			return;
		}

		[ $namespace, $file_name ] = explode( '\\', $class_name, 2 );

		if ( empty( $namespace ) || empty( $file_name ) || empty( self::$autoload_map[ $namespace ] ) ) {
			return;
		}

		$root_dir = self::$autoload_map[ $namespace ];

		$file_name = strtolower( $file_name );
		$dirs      = explode( '\\', $file_name );
		$file_name = array_pop( $dirs );
		$file_name = str_replace( '_', '-', $file_name );

		$file_dir = implode( '/', [ $root_dir, ...$dirs ] );

		$file_paths = [
			'class-' . $file_name . '.php',
			'trait-' . $file_name . '.php',
		];

		foreach ( $file_paths as $file_path ) {
			$file_path = $file_dir . '/' . $file_path;
			if ( file_exists( $file_path ) ) {
				require $file_path;
				return;
			}
		}

	}

}
