<?php
/**
 * File containing the class WP_Job_Manager_Helper.
 *
 * @package wp-job-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper functions used in WP Job Manager regarding addons, licenses and renewals.
 *
 * @package wp-job-manager
 * @since   1.29.0
 */
class WP_Job_Manager_Helper {
	/**
	 * License messages to display to the user.
	 *
	 * @var array Messages when updating licenses.
	 */
	protected $license_messages = [];

	/**
	 * API object.
	 *
	 * @var WP_Job_Manager_Helper_API
	 */
	protected $api;

	/**
	 * Language Pack helper.
	 *
	 * @var WP_Job_Manager_Helper_Language_Packs
	 */
	private $language_pack_helper;

	/**
	 * Nonce generator.
	 *
	 * @var WP_Job_Manager_Helper_Nonce
	 */
	private WP_Job_Manager_Helper_Nonce $nonce;

	/**
	 * The single instance of the class.
	 *
	 * @var self
	 * @since  1.29.0
	 */
	private static $instance = null;

	/**
	 * True if the plugin cache has already been cleared.
	 *
	 * @var bool
	 * @since 1.29.1
	 */
	private static $cleared_plugin_cache = false;

	/**
	 * Allows for accessing single instance of class. Class should only be constructed once per call.
	 *
	 * @since  1.29.0
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Loads the class, runs on init.
	 */
	public function init() {
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/helper/class-wp-job-manager-helper-options.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/helper/class-wp-job-manager-helper-api.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/helper/class-wp-job-manager-helper-language-packs.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/helper/class-wp-job-manager-helper-nonce.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/helper/class-wp-job-manager-helper-renewals.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/helper/class-wp-job-manager-helper-rest-api.php';
		include_once JOB_MANAGER_PLUGIN_DIR . '/includes/helper/class-wp-job-manager-site-trust-token.php';

		$this->api   = WP_Job_Manager_Helper_API::instance();
		$this->nonce = new WP_Job_Manager_Helper_Nonce();

		( new WP_Job_Manager_Helper_REST_API( $this->nonce ) )->init();

		add_action( 'job_manager_helper_output', [ $this, 'license_output' ] );

		add_filter( 'job_manager_addon_core_version_check', [ $this, 'addon_core_version_check' ], 10, 2 );
		add_filter( 'extra_plugin_headers', [ $this, 'extra_headers' ] );
		add_filter( 'plugins_api', [ $this, 'plugins_api' ], 20, 3 );
		add_action( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_updates' ] );

		add_action( 'activated_plugin', [ $this, 'plugin_activated' ] );
		add_action( 'deactivated_plugin', [ $this, 'plugin_deactivated' ] );
		add_action( 'admin_init', [ $this, 'admin_init' ] );
	}

	/**
	 * Handle the deprecated method calls.
	 *
	 * @param string $name Method name.
	 * @param array  $arguments Method arguments.
	 *
	 * @throws \Exception When the method is not found.
	 * @return mixed
	 */
	public function __call( $name, $arguments ) {
		$deprecated_methods = [
			'has_licenced_products' => [
				'replacement' => [ $this, 'has_licensed_products' ],
				'version'     => '1.42.0',
			],
			'get_plugin_licence'    => [
				'replacement' => [ $this, 'get_plugin_license' ],
				'version'     => '1.42.0',
			],
			'licence_output'        => [
				'replacement' => [ $this, 'license_output' ],
				'version'     => '1.42.0',
			],
			'licence_error_notices' => [
				'replacement' => [ $this, 'maybe_add_license_error_notices' ],
				'version'     => '1.33.0',
			],
			'activate_licence'      => [
				'replacement' => [ $this, 'activate_license' ],
				'version'     => '1.42.0',
			],
			'deactivate_licence'    => [
				'replacement' => [ $this, 'deactivate_license' ],
				'version'     => '1.42.0',
			],
		];

		if ( isset( $deprecated_methods[ $name ] ) ) {
			$replacement = $deprecated_methods[ $name ]['replacement'];
			$version     = $deprecated_methods[ $name ]['version'];

			_deprecated_function( esc_html( $name ), esc_html( $version ), esc_html( 'WP_Job_Manager_Helper::' . $replacement[1] . '()' ) );

			return call_user_func_array( $replacement, $arguments );
		}

		throw new \Exception( 'Call to undefined method ' . __CLASS__ . '::' . $name . '()' );
	}

	/**
	 * Initializes admin-only actions.
	 */
	public function admin_init() {
		$this->load_language_pack_helper();
		add_action( 'plugin_action_links', [ $this, 'plugin_links' ], 10, 2 );
		$this->handle_admin_request();
		add_filter( 'wpjm_admin_notices', [ $this, 'maybe_add_license_error_notices' ] );
	}

	/**
	 * Load the language pack helper.
	 */
	private function load_language_pack_helper() {
		if ( $this->language_pack_helper ) {
			return;
		}

		$this->language_pack_helper = new WP_Job_Manager_Helper_Language_Packs( $this->get_plugin_versions(), $this->get_site_locales() );
	}

	/**
	 * Get the versions for the installed managed plugins, keyed with the plugin slug.
	 *
	 * @return string[]
	 */
	private function get_plugin_versions() {
		return array_filter(
			array_map(
				function( $plugin ) {
					return $plugin['Version'];
				},
				$this->get_installed_plugins( false, false )
			)
		);
	}

	/**
	 * Get the locales used in the site.
	 *
	 * @return string[]
	 */
	private function get_site_locales() {
		$locales = array_values( get_available_languages() );

		/** This action is documented in WordPress core's wp-includes/update.php */
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$locales = apply_filters( 'plugins_update_check_locales', $locales );
		$locales = array_unique( $locales );

		return $locales;
	}

	/**
	 * Handles special tasks on admin requests.
	 */
	private function handle_admin_request() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce should not be modified.
		if ( ! isset( $_GET['_wpjm_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_GET['_wpjm_nonce'] ), 'dismiss-wpjm-license-notice' ) ) {
			return;
		}

		$product_slug = isset( $_GET['dismiss-wpjm-license-notice'] ) ? sanitize_text_field( wp_unslash( $_GET['dismiss-wpjm-license-notice'] ) ) : false;

		if ( ! empty( $product_slug ) ) {
			$product_plugins = $this->get_installed_plugins( false, false );
			if ( isset( $product_plugins[ $product_slug ] ) ) {
				WP_Job_Manager_Helper_Options::update( $product_slug, 'hide_key_notice', true );
			}
		}
	}

	/**
	 * Tell the add-on when to check for and display and core WPJM version notices.
	 *
	 * @param bool   $do_check True if the add-on should do a core version check.
	 * @param string $minimum_required_core_version Minimum version the plugin is reporting it requires.
	 *
	 * @return bool
	 */
	public function addon_core_version_check( $do_check, $minimum_required_core_version = null ) {
		if ( ! is_admin() || ! did_action( 'admin_init' ) ) {
			return false;
		}

		// We only want to show the notices on the plugins page and main job listing admin page.
		$screen = get_current_screen();
		if ( null === $screen || ! in_array( $screen->id, [ 'plugins', 'edit-job_listing' ], true ) ) {
			return false;
		}

		$dev_version_loc = strpos( JOB_MANAGER_VERSION, '-dev' );
		if (
			false !== $dev_version_loc
			&& substr( JOB_MANAGER_VERSION, 0, $dev_version_loc ) === $minimum_required_core_version
		) {
			return false;
		}

		return $do_check;
	}

	/**
	 * Check for license managed WPJM addon plugin updates.
	 *
	 * @param object $check_for_updates_data
	 *
	 * @return object
	 */
	public function check_for_updates( $check_for_updates_data ) {
		$installed_plugins = $this->get_installed_plugins( false, true );
		if ( empty( $installed_plugins ) ) {
			return $check_for_updates_data;
		}

		$updates = $this->get_plugin_update_info( $installed_plugins );

		$notice_data = [];

		// Set version variables.
		foreach ( $installed_plugins as $filename => $plugin_data ) {
			$wpjmcom_plugin_slug = $plugin_data['_product_slug'];
			if ( ! isset( $updates[ $wpjmcom_plugin_slug ] ) ) {
				continue;
			}

			$response         = (object) $updates[ $wpjmcom_plugin_slug ];
			$response->plugin = $filename;

			// If there is a new version, modify the transient to reflect an update is available.
			if (
				! empty( $response->new_version )
				&& version_compare( $response->new_version, $plugin_data['Version'], '>' )
			) {
				$check_for_updates_data->response[ $plugin_data['_filename'] ] = $response;

				$notice_data[] = [
					'plugin'      => $response->plugin,
					'plugin_name' => $response->plugin_name,
					'new_version' => $response->new_version,
				];
			}
		}

		set_site_transient( 'wpjm_addon_updates_available', $notice_data );

		return $check_for_updates_data;
	}

	/**
	 * Prepare the plugin update data for the WPJobManager.com request.
	 *
	 * @param array $installed_plugins The array of installed WP Job Manager managed plugins.
	 *
	 * @return array
	 */
	private function get_plugin_update_package( $installed_plugins ) {
		$plugin_package = [];
		foreach ( $installed_plugins as $plugin ) {
			$plugin_slug = $plugin['_product_slug'];
			$license_key = $this->get_plugin_license( $plugin_slug );

			$plugin_package[ $plugin_slug ] = [
				'installed_version' => $plugin['Version'],
				'license_key'       => $license_key['license_key'] ? $license_key['license_key'] : '',
			];
		}
		ksort( $plugin_package );

		return $plugin_package;
	}

	/**
	 * Get the updates available from WPJobManager.com.
	 *
	 * @param array $installed_plugins The array of installed WP Job Manager managed plugins.
	 *
	 * @return array
	 */
	protected function get_plugin_update_info( $installed_plugins ) {
		$plugin_package = $this->get_plugin_update_package( $installed_plugins );
		$hash           = md5( wp_json_encode( $plugin_package ) );

		// Check the cache.
		$cache_key = 'wpjm_helper_updates';
		$data      = get_site_transient( $cache_key );
		if ( isset( $data['hash'], $data['plugins'] ) && $hash === $data['hash'] ) {
			return $data['plugins'];
		}

		$response = $this->api->bulk_update_check( $plugin_package );
		if ( ! $response || ! isset( $response['plugins'] ) || ! empty( $response['error_code'] ) ) {
			return false;
		}

		$this->handle_plugin_errors( $response );

		$cached_data            = [];
		$cached_data['plugins'] = $response['plugins'];
		$cached_data['hash']    = $hash;
		set_site_transient( $cache_key, $cached_data, 12 * HOUR_IN_SECONDS );

		return $response['plugins'];
	}

	/**
	 * Get the response on available plugin updates from WPJobManager.com.
	 *
	 * @param array $response The response from the bulk updates API endpoint.
	 */
	private function handle_plugin_errors( $response ) {
		// Handle the errors.
		$meta    = $response['meta'] ?? [];
		$plugins = $response['plugins'] ?? [];

		foreach ( $plugins as $plugin_slug => $plugin ) {
			// Set any errors that might have occurred.
			$replacements = $meta + [
				'plugin_name' => $plugin_data[ $plugin_slug ]['plugin_name'] ?? __( 'your WP Job Manager plugin', 'wp-job-manager' ),
				'plugin_url'  => $plugin['url'] ?? null,
			];

			$this->set_licensed_plugin_errors( $plugin_slug, $plugin['errors'], $replacements );
		}
	}

	/**
	 * Set the plugin license errors.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @param array  $error_keys The error keys.
	 * @param array  $replacements The replacements.
	 */
	private function set_licensed_plugin_errors( $plugin_slug, $error_keys, $replacements ) {
		$plugin_name = null;

		$fallback_url   = 'https://wpjobmanager.com/';
		$plugin_name    = $replacements['plugin_name'];
		$purchase_url   = $replacements['plugin_url'] ?? $fallback_url;
		$my_account_url = $replacements['my_account_url'] ?? $fallback_url;

		$errors = [];
		if ( in_array( 'no_activation', $error_keys, true ) ) {
			// translators: First placeholder is the plugin name, second placeholder is the My Account URL.
			$errors['no_activation'] = sprintf( __( '<strong>Error:</strong> The license for <strong>%1$s</strong> is not activated on this website and has been removed. Manage your activations on your <a href="%2$s" rel="noopener noreferrer" target="_blank">My Account page</a>.', 'wp-job-manager' ), $plugin_name, $my_account_url );

			$this->deactivate_license( $plugin_slug, true );
		} elseif ( in_array( 'invalid_license', $error_keys, true ) ) {
			// translators: First placeholder is the plugin name; second placeholder is the URL to purchase the plugin.
			$errors['invalid_license'] = sprintf( __( '<strong>Error:</strong> The license for <strong>%1$s</strong> is not valid and has been removed. <a href="%2$s" rel="noopener noreferrer" target="_blank">Purchase a new license</a> to receive updates and support.', 'wp-job-manager' ), $plugin_name, $purchase_url );

			$this->deactivate_license( $plugin_slug, true );
		} elseif ( in_array( 'expired_key', $error_keys, true ) ) {
			// translators: First placeholder is the plugin name, second placeholder is the My Account URL.
			$errors['expired_key'] = sprintf( __( '<strong>Error:</strong> The license for <strong>%1$s</strong> has expired. You must <a href="%2$s" rel="noopener noreferrer" target="_blank">renew your license</a> to receive updates and support.', 'wp-job-manager' ), $plugin_name, $my_account_url );
		} elseif ( in_array( 'expiring_soon', $error_keys, true ) ) {
			// translators: First placeholder is the plugin name, second placeholder is the My Account URL.
			$errors['expiring_soon'] = sprintf( __( '<strong>Error:</strong> The license for <strong>%1$s</strong> is expiring soon. Please <a href="%2$s" rel="noopener noreferrer" target="_blank">renew your license</a> to continue receiving updates and support.', 'wp-job-manager' ), $plugin_name, $my_account_url );
		}

		WP_Job_Manager_Helper_Options::update( $plugin_slug, 'errors', $errors );
	}

	/**
	 * Cleanup old things when WPJM license managed plugin is activated.
	 *
	 * @param string $plugin_filename
	 */
	public function plugin_activated( $plugin_filename ) {
		$plugins = $this->get_installed_plugins( false, false );
		foreach ( $plugins as $product_slug => $plugin_data ) {
			if ( $plugin_filename !== $plugin_data['_filename'] ) {
				continue;
			}

			WP_Job_Manager_Helper_Options::delete( $product_slug, 'hide_key_notice' );
			break;
		}
	}

	/**
	 * Deactivate license when WPJM license managed plugin is deactivated.
	 *
	 * @param string $plugin_filename
	 */
	public function plugin_deactivated( $plugin_filename ) {
		$plugins = $this->get_installed_plugins( false, false );
		foreach ( $plugins as $product_slug => $plugin_data ) {
			if ( $plugin_filename !== $plugin_data['_filename'] ) {
				continue;
			}
			$this->deactivate_license( $product_slug );
			break;
		}
	}

	/**
	 * Fetches the plugin information for WPJM plugins.
	 *
	 * @param false|object|array $response The result object or array. Default false.
	 * @param string             $action The type of information being requested from the Plugin Install API.
	 * @param object             $args Plugin API arguments.
	 *
	 * @return false|object|array
	 */
	public function plugins_api( $response, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $response;
		}

		if ( empty( $args->slug ) ) {
			return $response;
		}

		$plugin_info = $this->get_plugin_info( $args->slug );
		if ( $plugin_info ) {
			$response = (object) $plugin_info;
		}

		return $response;
	}

	/**
	 * Appends links to manage plugin license when managed.
	 *
	 * @param array  $actions
	 * @param string $plugin_filename
	 *
	 * @return array
	 */
	public function plugin_links( $actions, $plugin_filename ) {
		$plugin = $this->get_license_managed_plugin( $plugin_filename );
		if ( ! $plugin || ! current_user_can( 'update_plugins' ) ) {
			return $actions;
		}
		$product_slug = $plugin['_product_slug'];
		$license      = $this->get_plugin_license( $product_slug );
		$css_class    = '';
		if ( $license && ! empty( $license['license_key'] ) ) {
			if ( ! empty( $license['errors'] ) ) {
				$manage_license_label = __( 'Manage License (Requires Attention)', 'wp-job-manager' );
				$css_class            = 'wpjm-activate-license-link';
			} else {
				$manage_license_label = __( 'Manage License', 'wp-job-manager' );
			}
		} else {
			$manage_license_label = __( 'Activate License', 'wp-job-manager' );
			$css_class            = 'wpjm-activate-license-link';
		}
		$actions[] = '<a class="' . esc_attr( $css_class ) . '" href="' . esc_url( admin_url( 'edit.php?post_type=job_listing&page=job-manager-marketplace&section=helper' ) ) . '">' . esc_html( $manage_license_label ) . '</a>';

		return $actions;
	}

	/**
	 * Returns the plugin info for a licensed WPJM plugin.
	 *
	 * @param string $product_slug
	 *
	 * @return bool|object
	 */
	protected function get_plugin_info( $product_slug ) {
		if ( ! $this->is_product_installed( $product_slug ) ) {
			return false;
		}
		$args                   = $this->get_plugin_license( $product_slug );
		$args['api_product_id'] = $product_slug;

		$response = $this->api->plugin_information( $args );

		return $response;
	}

	/**
	 * Checks if a WPJM plugin is installed.
	 *
	 * @param string $product_slug
	 *
	 * @return bool
	 */
	public function is_product_installed( $product_slug ) {
		$product_plugins = $this->get_installed_plugins( false, false );

		return isset( $product_plugins[ $product_slug ] );
	}

	/**
	 * Returns true if there are licensed products being managed.
	 *
	 * @return bool
	 */
	public function has_licensed_products() {
		$product_plugins = $this->get_installed_plugins( false, false );

		return ! empty( $product_plugins );
	}

	/**
	 * Returns the plugin data for plugin with a `WPJM-Product` tag by plugin filename.
	 *
	 * @param string $plugin_filename
	 *
	 * @return bool|array
	 */
	protected function get_license_managed_plugin( $plugin_filename ) {
		$plugins = $this->get_installed_plugins( false, true );
		if ( isset( $plugins[ $plugin_filename ] ) ) {
			return $plugins[ $plugin_filename ];
		}

		return false;
	}

	/**
	 * Gets the license key and email for a WPJM managed plugin.
	 *
	 * @param string $product_slug
	 *
	 * @return array|bool
	 */
	public function get_plugin_license( $product_slug ) {
		$license_key      = WP_Job_Manager_Helper_Options::get( $product_slug, 'license_key' );
		$activation_email = WP_Job_Manager_Helper_Options::get( $product_slug, 'email' );
		$errors           = WP_Job_Manager_Helper_Options::get( $product_slug, 'errors' );

		return [
			'license_key' => $license_key,
			'email'       => $activation_email,
			'errors'      => $errors,
		];
	}

	/**
	 * Check if an official extension has an active license.
	 *
	 * @param string $product_slug
	 *
	 * @return bool
	 */
	public function has_plugin_license( $product_slug ) {
		$license = $this->get_plugin_license( $product_slug );

		return ! empty( $license['license_key'] );
	}

	/**
	 * Adds newly recognized data header in WordPress plugin files.
	 *
	 * @param array $headers
	 *
	 * @return array
	 */
	public function extra_headers( $headers ) {
		$headers[] = 'WPJM-Product';

		return $headers;
	}

	/**
	 * Returns list of installed WPJM plugins with managed licenses indexed by product ID.
	 *
	 * @since 1.42.0 Added required $keyed_by_filename parameter.
	 *
	 * @param bool $active_only Only return active plugins.
	 * @param bool $keyed_by_filename Key by plugin filename instead of product slug. Allows for multiple plugins with the same product slug.
	 *
	 * @return array
	 */
	public function get_installed_plugins( $active_only = true, $keyed_by_filename = null ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( null === $keyed_by_filename ) {
			_doing_it_wrong( __METHOD__, 'The $keyed_by_filename parameter is required.', '1.42.0' );
			$keyed_by_filename = false;
		}

		$clear_plugin_cache = ! function_exists( 'did_filter' ) || did_filter( 'extra_plugin_headers' );

		/**
		 * Clear the plugin cache on first request for installed WPJM add-on plugins. This happens in installations
		 * that get_plugins() is called before WPJM has a chance to register its custom plugin headers.
		 *
		 * @since 1.29.1
		 * @since 1.42.0 Only do this when get_plugins was called before this filter.
		 *
		 * @param bool $clear_plugin_cache True if we should clear the plugin cache.
		 */
		if ( ! self::$cleared_plugin_cache && apply_filters( 'job_manager_clear_plugin_cache', $clear_plugin_cache ) ) {
			// Reset the plugin cache on the first call. Some plugins prematurely hydrate the cache.
			wp_clean_plugins_cache( false );
			self::$cleared_plugin_cache = true;
		}

		$wpjm_plugins = [];
		$plugins      = get_plugins();

		foreach ( $plugins as $filename => $data ) {
			if ( empty( $data['WPJM-Product'] ) || ( true === $active_only && ! is_plugin_active( $filename ) ) ) {
				continue;
			}

			$data['_filename']     = $filename;
			$data['_product_slug'] = $data['WPJM-Product'];
			$data['_type']         = 'plugin';

			$key = $data['WPJM-Product'];
			if ( $keyed_by_filename ) {
				$key = $filename;
			}

			$wpjm_plugins[ $key ] = $data;
		}

		return $wpjm_plugins;
	}

	/**
	 * Outputs the license management.
	 */
	public function license_output() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Flow use only. Method does nonce check.
		if ( ! empty( $_POST ) ) {
			$this->handle_request();
		}
		$licensed_plugins = $this->get_installed_plugins( false, false );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- No need for nonce here.
		$search_term        = sanitize_text_field( wp_unslash( $_REQUEST['s'] ?? '' ) );
		$licensed_plugins   = $this->search_licensed_plugins( $licensed_plugins, $search_term );
		$active_plugins     = array_filter(
			$licensed_plugins,
			function( $product_slug ) {
				return $this->has_plugin_license( $product_slug );
			},
			ARRAY_FILTER_USE_KEY
		);
		$inactive_plugins   = array_filter(
			$licensed_plugins,
			function( $product_slug ) {
				return ! $this->has_plugin_license( $product_slug );
			},
			ARRAY_FILTER_USE_KEY
		);
		$show_bulk_activate = $this->show_bulk_activation_form( $licensed_plugins );
		include_once dirname( __FILE__ ) . '/views/html-licenses.php';
	}

	/**
	 * Search for the list of licensed plugins.
	 *
	 * @param array  $licensed_plugins The array of licensed plugins to filter.
	 * @param string $search_term The search term to filter by.
	 *
	 * @return array The filtered list of licensed plugins.
	 */
	private function search_licensed_plugins( $licensed_plugins, $search_term ) {
		if ( ! empty( $search_term ) ) {
			$search_term      = strtolower( $search_term );
			$licensed_plugins = array_filter(
				$licensed_plugins,
				function( $plugin ) use ( $search_term ) {
					return str_contains( strtolower( $plugin['Name'] ), $search_term ) || str_contains( strtolower( $plugin['Description'] ), $search_term ) || str_contains( strtolower( $plugin['Author'] ), $search_term );
				}
			);
		}

		return $licensed_plugins;
	}

	/**
	 * Return if we should show or not the bulk activation form.
	 *
	 * @param array $licensed_plugins The list of licensed plugins to handle.
	 *
	 * @return bool If we should show the bulk activation form or not.
	 */
	private function show_bulk_activation_form( $licensed_plugins ) {
		foreach ( array_keys( $licensed_plugins ) as $product_slug ) {
			$license = self::get_plugin_license( $product_slug );
			if ( empty( $license['license_key'] ) && apply_filters( 'wpjm_display_license_form_for_addon', true, $product_slug ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Outputs unset license key notices.
	 *
	 * @param array $notices Current notices.
	 *
	 * @return mixed
	 */
	public function maybe_add_license_error_notices( $notices ) {

		$plugins                 = $this->get_installed_plugins( false, false );
		$plugins_without_license = [];
		foreach ( $plugins as $product_slug => $plugin_data ) {
			$license = $this->get_plugin_license( $product_slug );
			if ( empty( $license['license_key'] ) || ! empty( $license['errors'] ) ) {
				$plugins_without_license[ $product_slug ] = [
					'plugin_data' => $plugin_data,
					'license'     => $license,
				];
			}
		}

		if ( ! empty( $plugins_without_license ) ) {
			$notices['wpjm_missing_license_notice'] = $this->get_license_issues_notice( $plugins_without_license );
		}

		return $notices;
	}

	/**
	 * Handles a request on the manage license key screen.
	 *
	 * @return void
	 */
	private function handle_request() {
		if (
			empty( $_POST['action'] )
			|| empty( $_POST['_wpnonce'] )
			|| ! check_admin_referer( 'wpjm-manage-license' )
		) {
			return;
		}
		if ( str_starts_with( sanitize_text_field( wp_unslash( $_POST['action'] ) ), 'bulk_' ) ) {
			$this->handle_bulk_request();
		} else {
			$this->handle_single_request();
		}
	}

	/**
	 * Handle a request for a single product on the manage license key screen.
	 *
	 * @return void
	 */
	private function handle_single_request() {
		$licensed_plugins = $this->get_installed_plugins( false, false );
		if (
			empty( $_POST['action'] )
			|| empty( $_POST['_wpnonce'] )
			|| ! check_admin_referer( 'wpjm-manage-license' )
			|| empty( $_POST['product_slug'] )
			|| ! isset( $licensed_plugins[ $_POST['product_slug'] ] )
		) {
			return;
		}
		$product_slug = sanitize_text_field( wp_unslash( $_POST['product_slug'] ) );
		switch ( $_POST['action'] ) {
			case 'activate':
				if ( empty( $_POST['license_key'] ) ) {
					$this->add_error( $product_slug, __( 'Please enter a valid license key in order to activate this plugin\'s license.', 'wp-job-manager' ) );
					break;
				}
				$license_key = sanitize_text_field( wp_unslash( $_POST['license_key'] ) );
				$this->activate_license( $product_slug, $license_key, '' );
				break;
			case 'deactivate':
				$this->deactivate_license( $product_slug, true );
				break;
			case 'flush-wpcom-license':
				$this->flush_wpcom_license( $product_slug );
				break;
		}
	}

	/**
	 * Handle a bulk request on the manage license key screen.
	 *
	 * @return void
	 */
	private function handle_bulk_request() {
		if (
			empty( $_POST['action'] )
			|| 'bulk_activate' !== $_POST['action']
			|| empty( $_POST['_wpnonce'] )
			|| ! check_admin_referer( 'wpjm-manage-license' )
			|| empty( $_POST['product_slugs'] )
			|| ! is_array( $_POST['product_slugs'] ) ) {
			return;
		}
		$product_slugs = array_map( 'sanitize_text_field', wp_unslash( $_POST['product_slugs'] ) );
		if ( empty( $_POST['license_key'] ) ) {
			foreach ( $product_slugs as $product_slug ) {
				$this->add_error( $product_slug, __( 'Please enter a valid license key in order to activate the licenses of the plugins.', 'wp-job-manager' ) );
			}

			return;
		}
		$license_key = sanitize_text_field( wp_unslash( $_POST['license_key'] ) );
		$this->bulk_activate_license( $license_key, $product_slugs );
	}

	/**
	 * Activate multiple WPJM add-on plugins with a single license key.
	 *
	 * @param string   $license_key The license key to activate.
	 * @param string[] $product_slugs The product slugs to activate.
	 *
	 * @return void
	 */
	public function bulk_activate_license( $license_key, $product_slugs ) {
		$response = $this->api->bulk_activate(
			$license_key,
			$product_slugs
		);
		if ( false === $response ) {
			// If response is false, the request failed, then we can consider that we returned the same error message
			// for every product slug.
			$response = array_fill_keys(
				$product_slugs,
				[
					'error_code'    => 'connection_error',
					'error_message' => __( 'There was an error activating your license key. Please try again later.', 'wp-job-manager' ),
				]
			);
		}
		$error_messages       = array_column( $response, 'error_message' );
		$skip_invalid_product = true;
		// We only handle bulk errors if there are multiple products to activate, and if all products returned the same
		// error.
		if ( 1 < count( $product_slugs ) && count( $product_slugs ) === count( $error_messages ) ) {
			// If there's an error for each product, then we want to show the errors for invalid products too.
			$skip_invalid_product  = false;
			$error_messages_unique = array_unique( $error_messages );
			// Now, if we ONLY HAVE one kind of error, then we just print it once, in the bulk form.
			if ( 1 === count( $error_messages_unique ) ) {
				$this->add_error( 'bulk-activate', $error_messages_unique[0] );

				return;
			}
		}
		foreach ( $product_slugs as $product_slug ) {
			$result = $response && isset( $response[ $product_slug ] ) ? $response[ $product_slug ] : false;
			if ( $skip_invalid_product && false !== $result && ! $result['success'] && 'invalid_product' === $result['error_code'] ) {
				continue;
			}
			$this->handle_product_activation_response( $result, $product_slug, $license_key, false );
		}
	}

	/**
	 * Activate a license key for a WPJM add-on plugin.
	 *
	 * @param string $product_slug The slug of the product to activate.
	 * @param string $license_key The license key to activate.
	 * @param string $email The e-mail associated with the license. Optional (and actually not used).
	 */
	public function activate_license( $product_slug, $license_key, $email = '' ) {
		$response = $this->api->activate(
			[
				'api_product_id' => $product_slug,
				'license_key'    => $license_key,
				'email'          => $email,
			]
		);

		$this->handle_product_activation_response( $response, $product_slug, $license_key );
	}

	/**
	 * Deactivate a license key for a WPJM add-on plugin.
	 *
	 * @param string $product_slug
	 * @param bool   $silently Whether to add a notice.
	 */
	private function deactivate_license( $product_slug, $silently = false ) {
		$license = $this->get_plugin_license( $product_slug );
		if ( empty( $license['license_key'] ) ) {
			$this->add_error( $product_slug, __( 'license is not active.', 'wp-job-manager' ) );

			return;
		}
		$response = $this->api->deactivate(
			[
				'api_product_id' => $product_slug,
				'license_key'    => $license['license_key'],
				'email'          => $license['email'],
			]
		);

		if ( false === $response ) {
			$this->add_error( $product_slug, __( 'There was an error while deactivating the plugin.', 'wp-job-manager' ) );

			return;
		}

		WP_Job_Manager_Helper_Options::set_previous_license_key( $product_slug, $license['license_key'] );
		WP_Job_Manager_Helper_Options::delete( $product_slug, 'license_key' );
		WP_Job_Manager_Helper_Options::delete( $product_slug, 'email' );
		WP_Job_Manager_Helper_Options::delete( $product_slug, 'errors' );
		WP_Job_Manager_Helper_Options::delete( $product_slug, 'hide_key_notice' );
		wp_clean_plugins_cache( true );
		delete_site_transient( 'wpjm_helper_updates' );

		if ( $silently ) {
			$this->add_success( $product_slug, __( 'Plugin license has been deactivated.', 'wp-job-manager' ) );
		}

		self::log_event(
			'license_deactivated',
			[
				'slug' => $product_slug,
			]
		);
	}

	/**
	 * Flush the WPCOM license for a given product slug, pinging WPJMCOM to sync the license.
	 *
	 * @param string $product_slug The slug of the product being processed.
	 */
	public function flush_wpcom_license( $product_slug ) {
		$custom_nonce   = $this->nonce->create_custom_nonce( 'receive-license-' . $product_slug );
		$activation_url = rest_url( '/wpjm-internal/v1/licensing/receive-wpcom-license-key' );
		$activation_url = add_query_arg( 'custom_nonce', $custom_nonce, $activation_url );

		$remote = $this->api->flush_wpcom_license( $product_slug, $activation_url );
		if (
			false !== $remote &&
			true === $remote['success']
		) {
			$this->add_success( $product_slug, __( 'The license has been synced properly.', 'wp-job-manager' ) );
			// Clear the options cache to make the product appear in the correct place.
			wp_cache_delete( 'alloptions', 'options' );
			return;
		}
		$this->add_error( $product_slug, __( 'There was an error while syncing the license.', 'wp-job-manager' ) );
	}

	/**
	 * Add an error message.
	 *
	 * @param string $product_slug The plugin slug.
	 * @param string $message Your error message.
	 */
	private function add_error( $product_slug, $message ) {
		$this->add_message( 'error', $product_slug, $message );
	}

	/**
	 * Add a success message.
	 *
	 * @param string $product_slug The plugin slug.
	 * @param string $message Your error message.
	 */
	private function add_success( $product_slug, $message ) {
		$this->add_message( 'success', $product_slug, $message );
	}

	/**
	 * Add a message.
	 *
	 * @param string $type Message type.
	 * @param string $product_slug The plugin slug.
	 * @param string $message Your error message.
	 */
	private function add_message( $type, $product_slug, $message ) {
		if ( ! isset( $this->license_messages[ $product_slug ] ) ) {
			$this->license_messages[ $product_slug ] = [];
		}
		$this->license_messages[ $product_slug ][] = [
			'type'    => $type,
			'message' => $message,
		];
	}

	/**
	 * Get a plugin's license messages.
	 *
	 * @param string $product_slug The plugin slug.
	 *
	 * @return array
	 */
	public function get_messages( $product_slug ) {
		if ( ! isset( $this->license_messages[ $product_slug ] ) ) {
			$this->license_messages[ $product_slug ] = [];
		}

		return $this->license_messages[ $product_slug ];
	}

	/**
	 * Thin wrapper for WP_Job_Manager_Usage_Tracking::log_event().
	 *
	 * @param string $event_name The name of the event, without the `wpjm` prefix.
	 * @param array  $properties The event properties to be sent.
	 */
	private function log_event( $event_name, $properties = [] ) {
		if ( ! class_exists( 'WP_Job_Manager_Usage_Tracking' ) ) {
			return;
		}

		WP_Job_Manager_Usage_Tracking::log_event( $event_name, $properties );
	}

	/**
	 * Handle the response of the product activation API on WPJobManager.com.
	 *
	 * @param array|boolean $response The response to handle.
	 * @param string        $product_slug The slug of the product.
	 * @param string        $license_key The license key being activated.
	 * @param boolean       $show_success_message Whether to show a success message or not.
	 *
	 * @return void
	 */
	private function handle_product_activation_response( $response, $product_slug, $license_key, $show_success_message = true ) {
		$error = false;
		if ( ! isset( $response['error_message'] ) && isset( $response['error'] ) ) {
			$response['error_message'] = $response['error'];
		}
		if ( ! isset( $item['activated'] ) && isset( $response['success'] ) ) {
			$response['activated'] = $response['success'];
		}
		if ( false === $response ) {
			$error = 'connection_failed';
			$this->add_error( $product_slug, __( 'Connection failed to the License Key API server - possible server issue.', 'wp-job-manager' ) );
		} elseif ( isset( $response['error_code'] ) && isset( $response['error_message'] ) ) {
			$error = $response['error_code'];
			$this->add_error( $product_slug, $response['error_message'] );
		} elseif ( ! empty( $response['activated'] ) ) {
			WP_Job_Manager_Helper_Options::update( $product_slug, 'license_key', $license_key );
			WP_Job_Manager_Helper_Options::delete( $product_slug, 'errors' );
			WP_Job_Manager_Helper_Options::delete( $product_slug, 'hide_key_notice' );
			if ( $show_success_message ) {
				$this->add_success( $product_slug, __( 'Plugin license has been activated.', 'wp-job-manager' ) );
			}
		} else {
			$error = 'unknown';
			$this->add_error( $product_slug, __( 'An unknown error occurred while attempting to activate the license', 'wp-job-manager' ) );
		}

		$event_properties = [ 'slug' => $product_slug ];
		if ( false !== $error ) {
			$event_properties['error'] = $error;
			self::log_event( 'license_activation_error', $event_properties );
		} else {
			self::log_event( 'license_activated', $event_properties );

			// Clear the update cache so we can get the packages.
			delete_site_transient( 'wpjm_helper_updates' );
			wp_clean_plugins_cache( true );
		}
	}


	/**
	 * Add a notice to the admin if a license key is missing or invalid.
	 *
	 * @param array $plugins The plugins needing license review.
	 *
	 * @return array Notice data.
	 */
	private function get_license_issues_notice( $plugins ) {

		$manage_url = admin_url( 'edit.php?post_type=job_listing&page=job-manager-marketplace&section=helper' );

		return [
			'level'         => 'warning',
			'dismissible'   => true,
			'icon'          => false,
			'conditions'    => [
				[
					'type'         => 'user_cap',
					'capabilities' => [ 'update_plugins' ],
				],
			],
			'heading'       => _n( 'Job Manager: License required', 'Job Manager: Licenses required', count( $plugins ), 'wp-job-manager' ),
			'message'       => sprintf(
				wp_kses_post(
				// translators: %1$s is the URL to the license key page.
					__( '<a href="%1$s">Please add or review your license keys</a> to get updates for the following extensions:', 'wp-job-manager' )
				),
				esc_url( $manage_url ),
			),
			'actions'       => [
				[
					'primary' => true,
					'label'   => __( 'Manage Licenses', 'wp-job-manager' ),
					'url'     => esc_url( $manage_url ),
				],
				[
					'primary' => false,
					'label'   => __( 'My Account', 'wp-job-manager' ),
					'url'     => esc_url( 'https://wpjobmanager.com/my-account' ),
					'target'  => '_blank',
				],
			],
			'extra_details' => join(
				"\n",
				array_map(
					function( $plugin ) {
						$icon = WP_Job_Manager_Addons::instance()->get_icon( $plugin['plugin_data']['PluginURI'] );

						return '<div class="wpjm-addon-update-notice-info">
					<img class="wpjm-addon-update-notice-info__icon" src="' . esc_url( $icon ) . '" />
					<div class="wpjm-addon-update-notice-info__name">' . esc_html( $plugin['plugin_data']['Name'] ) . '</div>
				</div>';
					},
					$plugins
				)
			),
		];
	}
}

WP_Job_Manager_Helper::instance()->init();
