<?php
/**
 * Authentication Engine & UAE Phone Normalization
 *
 * @package VerificationSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Auth {

	/**
	 * Default country code.
	 */
	const DEFAULT_COUNTRY_CODE = '+971';

	/**
	 * Normalize mobile phone number to canonical international format (+971XXXXXXXXX).
	 *
	 * @param string $phone Raw phone input.
	 * @return string|false Normalized phone string or false if invalid.
	 */
	public static function normalize_phone( $phone ) {
		if ( empty( $phone ) ) {
			return false;
		}

		// Remove all non-digit characters except leading plus
		$phone = trim( $phone );
		$has_plus = ( 0 === strpos( $phone, '+' ) );
		$digits   = preg_replace( '/\D/', '', $phone );

		if ( empty( $digits ) ) {
			return false;
		}

		// Handle standard UAE local numbers starting with '05' or '5'
		if ( ! $has_plus ) {
			if ( 0 === strpos( $digits, '971' ) && strlen( $digits ) === 12 ) {
				return '+' . $digits;
			}
			if ( 0 === strpos( $digits, '05' ) && strlen( $digits ) === 10 ) {
				return '+971' . substr( $digits, 1 );
			}
			if ( 0 === strpos( $digits, '5' ) && strlen( $digits ) === 9 ) {
				return '+971' . $digits;
			}
		} else {
			// Had '+'
			if ( 0 === strpos( $digits, '971' ) && strlen( $digits ) === 12 ) {
				return '+' . $digits;
			}
			// General international format check
			if ( strlen( $digits ) >= 8 && strlen( $digits ) <= 15 ) {
				return '+' . $digits;
			}
		}

		// Fallback for UAE numbers if 9 digits provided
		if ( strlen( $digits ) === 9 && 0 === strpos( $digits, '5' ) ) {
			return '+971' . $digits;
		}

		return false;
	}

	/**
	 * Find Employee record by phone number.
	 *
	 * @param string $phone Raw or normalized phone.
	 * @return object|null Employee row object or null.
	 */
	public static function get_employee_by_phone( $phone ) {
		global $wpdb;

		$normalized = self::normalize_phone( $phone );
		if ( ! $normalized ) {
			return null;
		}

		$table = $wpdb->prefix . 'vs_employees';
		// Search both normalized and raw digits
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE phone_number = %s OR phone_number = %s LIMIT 1",
				$normalized,
				$phone
			)
		);

		return $row ? $row : null;
	}

	/**
	 * Authenticate user by phone number and password.
	 *
	 * @param string $phone Raw phone input.
	 * @param string $password Password.
	 * @return WP_User|WP_Error WP_User on success, WP_Error on failure.
	 */
	public static function authenticate_by_phone_and_password( $phone, $password ) {
		if ( empty( $phone ) || empty( $password ) ) {
			return new WP_Error( 'missing_credentials', __( 'Phone number and password are required.', 'verification-system' ) );
		}

		$normalized = self::normalize_phone( $phone );
		if ( ! $normalized ) {
			return new WP_Error( 'invalid_phone', __( 'Invalid UAE phone number format.', 'verification-system' ) );
		}

		$employee = self::get_employee_by_phone( $normalized );
		if ( ! $employee ) {
			return new WP_Error( 'account_not_found', __( 'No account found with this mobile number.', 'verification-system' ) );
		}

		if ( 'active' !== $employee->status ) {
			return new WP_Error( 'account_inactive', __( 'Your employee account is suspended or inactive.', 'verification-system' ) );
		}

		$user = get_userdata( $employee->user_id );
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', __( 'User account mapping is invalid.', 'verification-system' ) );
		}

		// Check password
		if ( ! wp_check_password( $password, $user->user_pass, $user->ID ) ) {
			VS_Audit::log_event(
				$user->ID,
				$user->display_name,
				$employee->app_role,
				'login_failed',
				'employee',
				$employee->id,
				$employee->branch_id,
				array( 'reason' => 'Invalid password', 'phone' => $normalized )
			);
			return new WP_Error( 'invalid_password', __( 'Incorrect password.', 'verification-system' ) );
		}

		// Set authentication session
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		VS_Audit::log_event(
			$user->ID,
			$user->display_name,
			$employee->app_role,
			'login_success',
			'employee',
			$employee->id,
			$employee->branch_id,
			array( 'method' => 'password', 'phone' => $normalized )
		);

		return $user;
	}

	/**
	 * Log out current user and clear session.
	 */
	public static function logout() {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$user = get_userdata( $user_id );
			VS_Audit::log_event(
				$user_id,
				$user ? $user->display_name : 'User',
				'user',
				'logout',
				'user',
				$user_id,
				0,
				array( 'message' => 'Logged out successfully' )
			);
		}
		wp_logout();
	}

	/**
	 * Get current authenticated employee details.
	 *
	 * @return object|null Employee record or null.
	 */
	public static function get_current_employee() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return null;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'vs_employees';

		$employee = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", $user_id )
		);

		if ( ! $employee && current_user_can( 'administrator' ) ) {
			// Virtual employee object for system admin
			return (object) array(
				'id'            => 0,
				'user_id'       => $user_id,
				'employee_code' => 'ADMIN',
				'full_name'     => 'System Administrator',
				'phone_number'  => '+971000000000',
				'email'         => wp_get_current_user()->user_email,
				'app_role'      => 'vs_system_admin',
				'branch_id'     => 0,
				'department'    => 'Administration',
				'job_title'     => 'System Administrator',
				'status'        => 'active',
			);
		}

		return $employee;
	}
}
