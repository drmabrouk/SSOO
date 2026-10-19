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

		// 3. Create or restore Main Application Page if missing
		self::ensure_app_page_exists();

		// 4. Set Default Options if missing
		self::set_default_options();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Ensure the single application page exists without duplicate creation.
	 */
	public static function ensure_app_page_exists() {
		$page_id = get_option( 'vs_app_page_id', 0 );

		if ( $page_id ) {
			$post = get_post( $page_id );
			if ( $post ) {
				// If trashed, restore it
				if ( 'trash' === $post->post_status ) {
					wp_untrash_post( $page_id );
				}
				return $page_id;
			}
		}

		// Search existing page by slug to prevent duplicates
		$existing_page = get_page_by_path( 'verification-system' );
		if ( $existing_page ) {
			if ( 'trash' === $existing_page->post_status ) {
				wp_untrash_post( $existing_page->ID );
			}
			update_option( 'vs_app_page_id', $existing_page->ID );
			return $existing_page->ID;
		}

		// Create dedicated SPA page
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
			return $new_page_id;
		}

		return 0;
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
