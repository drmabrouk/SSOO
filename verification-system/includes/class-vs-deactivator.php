<?php
/**
 * Plugin Deactivator
 *
 * @package VerificationSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Deactivator {

	/**
	 * Deactivate plugin.
	 */
	public static function deactivate() {
		// Flush rewrite rules on deactivation
		flush_rewrite_rules();

		// Note: Database tables and user data are intentionally preserved on normal deactivation
		// Safe uninstall is handled in uninstall.php if the user deletes the plugin.
	}
}
