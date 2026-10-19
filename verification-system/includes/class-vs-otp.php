<?php
/**
 * SMS OTP Architecture & Abuse Protection
 *
 * @package VerificationSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_OTP {

	/**
	 * Expiry time in seconds (5 minutes).
	 */
	const OTP_EXPIRATION_SECONDS = 300;

	/**
	 * Max attempts allowed before lock.
	 */
	const MAX_ATTEMPTS = 3;

	/**
	 * Rate limit window in seconds (1 minute between OTP requests).
	 */
	const RATE_LIMIT_SECONDS = 60;

	/**
	 * Generate and send OTP to phone number if real provider configured.
	 *
	 * @param string $phone Raw or normalized phone.
	 * @return array Array with success status and message.
	 */
	public static function send_otp( $phone ) {
		$normalized = VS_Auth::normalize_phone( $phone );
		if ( ! $normalized ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid mobile phone number format.', 'verification-system' ),
			);
		}

		$employee = VS_Auth::get_employee_by_phone( $normalized );
		if ( ! $employee ) {
			return array(
				'success' => false,
				'message' => __( 'No active employee found with this mobile number.', 'verification-system' ),
			);
		}

		if ( 'active' !== $employee->status ) {
			return array(
				'success' => false,
				'message' => __( 'Employee account is suspended or inactive.', 'verification-system' ),
			);
		}

		// Check rate limiting
		if ( self::is_rate_limited( $normalized ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please wait before requesting another OTP.', 'verification-system' ),
			);
		}

		// Check configured SMS provider
		$sms_provider = get_option( 'vs_sms_provider', 'none' );
		if ( 'none' === $sms_provider || empty( $sms_provider ) ) {
			return array(
				'success' => false,
				'message' => __( 'SMS provider is not configured. Password login is required.', 'verification-system' ),
			);
		}

		// Generate random 6-digit OTP
		$otp_code = wp_rand( 100000, 999999 );
		$otp_hash = wp_hash_password( (string) $otp_code );

		// Dispatch via real SMS provider interface hook
		$sms_sent = apply_filters( 'vs_send_sms_otp', false, $normalized, $otp_code, $sms_provider );

		if ( ! $sms_sent ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to dispatch SMS through the configured SMS gateway provider.', 'verification-system' ),
			);
		}

		// Store OTP securely (hashed) in database
		self::store_otp_hash( $normalized, $otp_hash );

		return array(
			'success' => true,
			'message' => __( 'A one-time verification code has been sent to your mobile phone.', 'verification-system' ),
		);
	}

	/**
	 * Verify submitted OTP code.
	 *
	 * @param string $phone Raw or normalized phone.
	 * @param string $otp_code Submitted OTP string.
	 * @return WP_User|WP_Error WP_User on success, WP_Error on failure.
	 */
	public static function verify_otp( $phone, $otp_code ) {
		global $wpdb;

		$normalized = VS_Auth::normalize_phone( $phone );
		if ( ! $normalized ) {
			return new WP_Error( 'invalid_phone', __( 'Invalid UAE phone number format.', 'verification-system' ) );
		}

		$table = $wpdb->prefix . 'vs_otps';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE phone_number = %s ORDER BY id DESC LIMIT 1",
				$normalized
			)
		);

		if ( ! $row ) {
			return new WP_Error( 'no_otp_found', __( 'No active OTP request found for this phone number.', 'verification-system' ) );
		}

		// Check expiration
		if ( strtotime( $row->expires_at ) < time() ) {
			$wpdb->delete( $table, array( 'id' => $row->id ) );
			return new WP_Error( 'otp_expired', __( 'The verification code has expired. Please request a new one.', 'verification-system' ) );
		}

		// Check max attempts
		if ( $row->attempt_count >= self::MAX_ATTEMPTS ) {
			$wpdb->delete( $table, array( 'id' => $row->id ) );
			return new WP_Error( 'too_many_attempts', __( 'Too many incorrect attempts. Please request a new OTP.', 'verification-system' ) );
		}

		// Increment attempt count
		$wpdb->update(
			$table,
			array( 'attempt_count' => $row->attempt_count + 1 ),
			array( 'id' => $row->id )
		);

		// Verify hash
		if ( ! wp_check_password( (string) $otp_code, $row->otp_hash ) ) {
			return new WP_Error( 'invalid_otp', __( 'Invalid verification code.', 'verification-system' ) );
		}

		// OTP valid: delete record to prevent replay
		$wpdb->delete( $table, array( 'id' => $row->id ) );

		// Fetch employee and authenticate user
		$employee = VS_Auth::get_employee_by_phone( $normalized );
		if ( ! $employee ) {
			return new WP_Error( 'account_not_found', __( 'No employee record associated with this phone.', 'verification-system' ) );
		}

		$user = get_userdata( $employee->user_id );
		if ( ! $user ) {
			return new WP_Error( 'user_not_found', __( 'Associated WordPress user account not found.', 'verification-system' ) );
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		VS_Audit::log_event(
			$user->ID,
			$user->display_name,
			$employee->app_role,
			'login_success_otp',
			'employee',
			$employee->id,
			$employee->branch_id,
			array( 'phone' => $normalized )
		);

		return $user;
	}

	/**
	 * Store hashed OTP in database.
	 *
	 * @param string $phone Normalized phone.
	 * @param string $otp_hash Hashed OTP string.
	 */
	private static function store_otp_hash( $phone, $otp_hash ) {
		global $wpdb;

		$table      = $wpdb->prefix . 'vs_otps';
		$expires_at = date( 'Y-m-d H:i:s', time() + self::OTP_EXPIRATION_SECONDS );

		// Clear previous OTPs for this phone
		$wpdb->delete( $table, array( 'phone_number' => $phone ) );

		$wpdb->insert(
			$table,
			array(
				'phone_number'  => $phone,
				'otp_hash'      => $otp_hash,
				'attempt_count' => 0,
				'expires_at'    => $expires_at,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Check if phone number is rate limited.
	 *
	 * @param string $phone Normalized phone.
	 * @return bool
	 */
	private static function is_rate_limited( $phone ) {
		global $wpdb;

		$table = $wpdb->prefix . 'vs_otps';
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT created_at FROM {$table} WHERE phone_number = %s ORDER BY id DESC LIMIT 1",
				$phone
			)
		);

		if ( ! $row ) {
			return false;
		}

		$time_since_creation = time() - strtotime( $row->created_at );
		return $time_since_creation < self::RATE_LIMIT_SECONDS;
	}
}
