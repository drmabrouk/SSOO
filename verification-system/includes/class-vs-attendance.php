<?php
/**
 * Attendance State Machine & Session Engine
 *
 * @package VerificationSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Attendance {

	/**
	 * Process Employee Check-In.
	 *
	 * @param object $employee Employee record.
	 * @param array  $wifi_payload Wi-Fi payload data.
	 * @param bool   $exception_override Manager override flag.
	 * @return array|WP_Error Check-in session details or error.
	 */
	public static function check_in( $employee, $wifi_payload = array(), $exception_override = false ) {
		global $wpdb;

		if ( 'active' !== $employee->status ) {
			return new WP_Error( 'inactive_account', __( 'Your employee account is inactive or suspended.', 'verification-system' ) );
		}

		$today = current_time( 'Y-m-d' );
		$now   = current_time( 'mysql' );

		$session_table = $wpdb->prefix . 'vs_attendance_sessions';
		$event_table   = $wpdb->prefix . 'vs_attendance_events';

		// Check for existing open session or completed session today
		$existing_session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$session_table} WHERE employee_id = %d AND date = %s LIMIT 1",
				$employee->id,
				$today
			)
		);

		if ( $existing_session ) {
			if ( 'completed' === $existing_session->status ) {
				return new WP_Error( 'already_completed', __( 'You have already checked out for today. Attendance for today is completed.', 'verification-system' ) );
			}
			if ( 'open' === $existing_session->status ) {
				return new WP_Error( 'already_checked_in', __( 'You are already checked in. Please check out before initiating a new check-in.', 'verification-system' ) );
			}
		}

		// Perform Workplace Wi-Fi Verification
		$wifi_result = VS_WiFi::verify_connection( $employee, $wifi_payload, $exception_override );

		if ( ! $wifi_result['success'] ) {
			return new WP_Error(
				'wifi_verification_failed',
				sprintf( __( 'Wi-Fi Verification Failed: %s', 'verification-system' ), $wifi_result['message'] ),
				array( 'code' => $wifi_result['code'] )
			);
		}

		// Fetch schedule policy and calculate lateness
		$schedule = VS_Schedule::get_schedule_for_employee( $employee );
		$metrics  = VS_Schedule::calculate_attendance_metrics( $schedule, $now );

		// Atomic insert of open attendance session
		$wpdb->query( 'START TRANSACTION' );

		$insert_session = $wpdb->insert(
			$session_table,
			array(
				'employee_id'         => $employee->id,
				'branch_id'           => $employee->branch_id,
				'date'                => $today,
				'status'              => 'open',
				'check_in_time'       => $now,
				'check_in_verified'   => 1,
				'is_late'             => $metrics['is_late'],
				'lateness_minutes'    => $metrics['lateness_minutes'],
				'created_at'          => $now,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s' )
		);

		$session_id = $wpdb->insert_id;

		if ( ! $session_id ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'db_error', __( 'Failed to record check-in session.', 'verification-system' ) );
		}

		// Insert Attendance Event Log
		$ssid  = isset( $wifi_payload['ssid'] ) ? sanitize_text_field( $wifi_payload['ssid'] ) : '';
		$bssid = isset( $wifi_payload['bssid'] ) ? sanitize_text_field( $wifi_payload['bssid'] ) : '';
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';

		$wpdb->insert(
			$event_table,
			array(
				'session_id'          => $session_id,
				'employee_id'         => $employee->id,
				'branch_id'           => $employee->branch_id,
				'event_type'          => 'CHECK_IN',
				'event_time'          => $now,
				'wifi_ssid'           => $ssid,
				'wifi_bssid'          => $bssid,
				'client_ip'           => $ip,
				'verified'            => 1,
				'verification_method' => $exception_override ? 'admin_override' : 'wifi',
				'created_at'          => $now,
			)
		);

		$wpdb->query( 'COMMIT' );

		VS_Audit::log_event(
			get_current_user_id(),
			$employee->full_name,
			$employee->app_role,
			'check_in',
			'attendance_session',
			$session_id,
			$employee->branch_id,
			array( 'check_in_time' => $now, 'is_late' => $metrics['is_late'] )
		);

		return array(
			'success'          => true,
			'session_id'       => $session_id,
			'status'           => 'open',
			'check_in_time'    => $now,
			'is_late'          => (bool) $metrics['is_late'],
			'lateness_minutes' => $metrics['lateness_minutes'],
			'message'          => __( 'Check-in recorded successfully.', 'verification-system' ),
		);
	}

	/**
	 * Process Employee Check-Out.
	 *
	 * @param object $employee Employee record.
	 * @param array  $wifi_payload Wi-Fi payload data.
	 * @param bool   $exception_override Manager override flag.
	 * @return array|WP_Error Check-out session details or error.
	 */
	public static function check_out( $employee, $wifi_payload = array(), $exception_override = false ) {
		global $wpdb;

		$today = current_time( 'Y-m-d' );
		$now   = current_time( 'mysql' );

		$session_table = $wpdb->prefix . 'vs_attendance_sessions';
		$event_table   = $wpdb->prefix . 'vs_attendance_events';

		// Find active open session
		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$session_table} WHERE employee_id = %d AND status = 'open' ORDER BY id DESC LIMIT 1",
				$employee->id
			)
		);

		if ( ! $session ) {
			return new WP_Error( 'no_open_session', __( 'No open check-in session found for you today.', 'verification-system' ) );
		}

		// Perform Workplace Wi-Fi Verification
		$wifi_result = VS_WiFi::verify_connection( $employee, $wifi_payload, $exception_override );

		if ( ! $wifi_result['success'] ) {
			return new WP_Error(
				'wifi_verification_failed',
				sprintf( __( 'Wi-Fi Verification Failed: %s', 'verification-system' ), $wifi_result['message'] ),
				array( 'code' => $wifi_result['code'] )
			);
		}

		// Calculate total duration and metrics
		$schedule = VS_Schedule::get_schedule_for_employee( $employee );
		$metrics  = VS_Schedule::calculate_attendance_metrics( $schedule, $session->check_in_time, $now );

		$wpdb->query( 'START TRANSACTION' );

		// Update Session
		$updated = $wpdb->update(
			$session_table,
			array(
				'status'                  => 'completed',
				'check_out_time'          => $now,
				'check_out_verified'      => 1,
				'total_worked_minutes'    => $metrics['total_worked_minutes'],
				'overtime_minutes'        => $metrics['overtime_minutes'],
				'is_early_departure'      => $metrics['is_early_departure'],
				'early_departure_minutes' => $metrics['early_departure_minutes'],
				'updated_at'              => $now,
			),
			array( 'id' => $session->id ),
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'db_error', __( 'Failed to update attendance session.', 'verification-system' ) );
		}

		// Insert Check-out event
		$ssid  = isset( $wifi_payload['ssid'] ) ? sanitize_text_field( $wifi_payload['ssid'] ) : '';
		$bssid = isset( $wifi_payload['bssid'] ) ? sanitize_text_field( $wifi_payload['bssid'] ) : '';
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';

		$wpdb->insert(
			$event_table,
			array(
				'session_id'          => $session->id,
				'employee_id'         => $employee->id,
				'branch_id'           => $employee->branch_id,
				'event_type'          => 'CHECK_OUT',
				'event_time'          => $now,
				'wifi_ssid'           => $ssid,
				'wifi_bssid'          => $bssid,
				'client_ip'           => $ip,
				'verified'            => 1,
				'verification_method' => $exception_override ? 'admin_override' : 'wifi',
				'created_at'          => $now,
			)
		);

		$wpdb->query( 'COMMIT' );

		VS_Audit::log_event(
			get_current_user_id(),
			$employee->full_name,
			$employee->app_role,
			'check_out',
			'attendance_session',
			$session->id,
			$employee->branch_id,
			array( 'check_out_time' => $now, 'total_worked_minutes' => $metrics['total_worked_minutes'] )
		);

		return array(
			'success'                 => true,
			'session_id'              => $session->id,
			'status'                  => 'completed',
			'check_in_time'           => $session->check_in_time,
			'check_out_time'          => $now,
			'total_worked_minutes'    => $metrics['total_worked_minutes'],
			'overtime_minutes'        => $metrics['overtime_minutes'],
			'is_early_departure'      => (bool) $metrics['is_early_departure'],
			'early_departure_minutes' => $metrics['early_departure_minutes'],
			'message'                 => __( 'Check-out recorded successfully.', 'verification-system' ),
		);
	}

	/**
	 * Get current attendance status for an employee.
	 *
	 * @param int $employee_id Employee ID.
	 * @return array Attendance state array.
	 */
	public static function get_today_status( $employee_id ) {
		global $wpdb;

		$today = current_time( 'Y-m-d' );
		$table = $wpdb->prefix . 'vs_attendance_sessions';

		$session = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE employee_id = %d AND date = %s ORDER BY id DESC LIMIT 1",
				$employee_id,
				$today
			)
		);

		if ( ! $session ) {
			return array(
				'state'         => 'NOT_CHECKED_IN',
				'can_check_in'  => true,
				'can_check_out' => false,
				'session'       => null,
			);
		}

		if ( 'open' === $session->status ) {
			return array(
				'state'         => 'CHECKED_IN',
				'can_check_in'  => false,
				'can_check_out' => true,
				'session'       => $session,
			);
		}

		return array(
			'state'         => 'CHECKED_OUT',
			'can_check_in'  => false,
			'can_check_out' => false,
			'session'       => $session,
		);
	}
}
