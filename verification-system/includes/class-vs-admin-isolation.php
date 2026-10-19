<?php
/**
 * Strict WordPress Administration Isolation
 *
 * @package VerificationSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Admin_Isolation {

	/**
	 * Initialize isolation hooks.
	 */
	public static function init() {
		// Hide admin bar for non-administrators
		add_action( 'after_setup_theme', array( __CLASS__, 'hide_admin_bar' ) );

		// Redirect non-administrators trying to access /wp-admin
		add_action( 'admin_init', array( __CLASS__, 'restrict_admin_access' ) );

		// Prevent access to WP login page for logged-in non-admins if navigating manually
		add_action( 'login_init', array( __CLASS__, 'handle_login_redirect' ) );
	}

	/**
	 * Hide WordPress Toolbar for General Managers, Branch Managers, and Employees.
	 */
	public static function hide_admin_bar() {
		if ( ! current_user_can( 'administrator' ) ) {
			show_admin_bar( false );
		}
	}

	/**
	 * Redirect non-administrators away from wp-admin to the independent app page.
	 */
	public static function restrict_admin_access() {
		// Allow AJAX requests
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return;
		}

		// Allow WordPress administrators
		if ( current_user_can( 'administrator' ) ) {
			return;
		}

		// Redirect all other users to the independent application page
		$app_page_id = get_option( 'vs_app_page_id', 0 );
		$app_url     = $app_page_id ? get_permalink( $app_page_id ) : home_url( '/verification-system' );

		wp_safe_redirect( $app_url );
		exit;
	}

	/**
	 * Redirect logged-in users away from wp-login.php to application page unless logging out.
	 */
	public static function handle_login_redirect() {
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( 'logout' === $action ) {
			return;
		}

		if ( is_user_logged_in() && ! current_user_can( 'administrator' ) ) {
			$app_page_id = get_option( 'vs_app_page_id', 0 );
			$app_url     = $app_page_id ? get_permalink( $app_page_id ) : home_url( '/verification-system' );

			wp_safe_redirect( $app_url );
			exit;
		}
	}
}
