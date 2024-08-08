<?php
/**
 * Plugin Name: WP Job Manager
 * Plugin URI: https://wpjobmanager.com/
 * Description: Manage job listings from the WordPress admin panel, and allow users to post jobs directly to your site.
 * Version: 2.4.0
 * Author: Automattic
 * Author URI: https://wpjobmanager.com/
 * Requires at least: 6.4
 * Tested up to: 6.6
 * Requires PHP: 7.4
 * Text Domain: wp-job-manager
 * Domain Path: /languages/
 * License: GPL2+
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'JOB_MANAGER_VERSION', '2.4.0' );
define( 'JOB_MANAGER_PLUGIN_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'JOB_MANAGER_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'JOB_MANAGER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'JOB_MANAGER_DATE_FORMAT_FALLBACK', 'F j, Y' );

require_once dirname( __FILE__ ) . '/wp-job-manager-autoload.php';
WP_Job_Manager_Autoload::init();
WP_Job_Manager_Autoload::register( 'WP_Job_Manager', JOB_MANAGER_PLUGIN_DIR . '/includes' );

require_once dirname( __FILE__ ) . '/includes/class-wp-job-manager-dependency-checker.php';
if ( ! WP_Job_Manager_Dependency_Checker::check_dependencies() ) {
	return;
}

require_once dirname( __FILE__ ) . '/includes/class-wp-job-manager.php';

/**
 * Main instance of WP Job Manager.
 *
 * Returns the main instance of WP Job Manager to prevent the need to use globals.
 *
 * @since  1.26
 * @return WP_Job_Manager
 */
function WPJM() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
	return WP_Job_Manager::instance();
}

$GLOBALS['job_manager'] = WPJM();

// Activation - works with symlinks.
register_activation_hook( basename( dirname( __FILE__ ) ) . '/' . basename( __FILE__ ), [ WPJM(), 'activate' ] );

// Cleanup on deactivation.
register_deactivation_hook( __FILE__, [ WPJM(), 'unschedule_cron_jobs' ] );
register_deactivation_hook( __FILE__, [ WPJM(), 'usage_tracking_cleanup' ] );
