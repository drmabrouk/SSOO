<?php
/**
 * Plugin Activator
 *
 * @package VerificationSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Activator {

	/**
	 * Activate plugin.
	 */
	public static function activate() {
		// 1. Create DB Tables
		VS_DB::create_tables();

		// 2. Initialize Roles
		VS_Roles::init();

		// 3. Create Main Application Page if missing
		self::create_app_page();

		// 4. Set Default Options if missing
		self::set_default_options();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Create main application frontend page with [verification_system] shortcode.
	 */
	private static function create_app_page() {
		$page_id = get_option( 'vs_app_page_id', 0 );

		if ( $page_id && get_post( $page_id ) ) {
			return; // Page already exists
		}

		$page_data = array(
			'post_title'     => 'Verification System App',
			'post_name'      => 'verification-system',
			'post_content'   => '[verification_system]',
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'comment_status' => 'closed',
		);

		$new_page_id = wp_insert_post( $page_data );

		if ( $new_page_id && ! is_wp_error( $new_page_id ) ) {
			update_option( 'vs_app_page_id', $new_page_id );
		}
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options() {
		if ( false === get_option( 'vs_timezone' ) ) {
			update_option( 'vs_timezone', 'Asia/Dubai' );
		}
		if ( false === get_option( 'vs_country_code' ) ) {
			update_option( 'vs_country_code', '+971' );
		}
		if ( false === get_option( 'vs_currency' ) ) {
			update_option( 'vs_currency', 'AED' );
		}
		if ( false === get_option( 'vs_sms_provider' ) ) {
			update_option( 'vs_sms_provider', 'none' );
		}
		if ( false === get_option( 'vs_wifi_mode' ) ) {
			update_option( 'vs_wifi_mode', 'hmac_token' );
		}
	}
}
