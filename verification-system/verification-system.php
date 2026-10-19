<?php
/**
 * Plugin Name: Verification System – Smart Attendance & Workforce Management
 * Plugin URI:  https://example.com/verification-system
 * Description: Smart Attendance & Workforce Management system using mobile-friendly authentication and verified workplace Wi-Fi connectivity.
 * Version:     1.0.0
 * Author:      Verification System Team
 * Author URI:  https://example.com
 * Text Domain: verification-system
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package VerificationSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'VS_VERSION', '1.0.0' );
define( 'VS_DB_VERSION', '1.0.0' );
define( 'VS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VS_PLUGIN_FILE', __FILE__ );
define( 'VS_DEFAULT_TIMEZONE', 'Asia/Dubai' );
define( 'VS_DEFAULT_COUNTRY_CODE', '+971' );
define( 'VS_DEFAULT_CURRENCY', 'AED' );

/**
 * Autoload Verification System classes.
 *
 * @param string $class_name Class name.
 */
spl_autoload_register( function( $class_name ) {
	if ( 0 !== strpos( $class_name, 'VS_' ) && 0 !== strpos( $class_name, 'VerificationSystem\\' ) ) {
		return;
	}

	$clean_class = str_replace( 'VerificationSystem\\', '', $class_name );
	$file_name   = 'class-' . strtolower( str_replace( '_', '-', $clean_class ) ) . '.php';
	$file_path   = VS_PLUGIN_DIR . 'includes/' . $file_name;

	if ( file_exists( $file_path ) ) {
		require_once $file_path;
	}
} );

/**
 * Main plugin class entry.
 */
class Verification_System {

	/**
	 * Single instance.
	 *
	 * @var Verification_System
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return Verification_System
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->define_timezone();
		$this->init_hooks();
	}

	/**
	 * Ensure timezone is set to Asia/Dubai by default if not set.
	 */
	private function define_timezone() {
		if ( function_exists( 'date_default_timezone_set' ) ) {
			date_default_timezone_set( VS_DEFAULT_TIMEZONE );
		}
	}

	/**
	 * Register plugin hooks.
	 */
	private function init_hooks() {
		// Activation & Deactivation hooks
		register_activation_hook( VS_PLUGIN_FILE, array( 'VS_Activator', 'activate' ) );
		register_deactivation_hook( VS_PLUGIN_FILE, array( 'VS_Deactivator', 'deactivate' ) );

		// Load core components on plugins_loaded
		add_action( 'plugins_loaded', array( $this, 'init_plugin' ) );
	}

	/**
	 * Initialize plugin logic.
	 */
	public function init_plugin() {
		// Initialize Roles & Permissions
		VS_Roles::init();

		// Initialize Admin Isolation
		VS_Admin_Isolation::init();

		// Initialize REST API
		VS_Rest_API::init();

		// Initialize Frontend Controller
		VS_Frontend::init();
	}
}

/**
 * Returns main instance.
 */
function VS() {
	return Verification_System::get_instance();
}

// Start the plugin.
VS();
