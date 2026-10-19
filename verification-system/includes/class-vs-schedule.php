<?php
/**
 * Attendance Schedules & Policy Engine
 *
 * @package VerificationSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Schedule {

	/**
	 * Get schedule for employee or branch default.
	 *
	 * @param object $employee Employee record.
	 * @return object Schedule row or default structure.
	 */
	public static function get_schedule_for_employee( $employee ) {
		global $wpdb;

		$table = $wpdb->prefix . 'vs_schedules';

		// 1. Check if employee has a specific schedule_id
		if ( ! empty( $employee->schedule_id ) ) {
			$sched = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $employee->schedule_id )
			);
			if ( $sched ) {
				return $sched;
			}
		}

		// 2. Check if branch has a specific schedule
		if ( ! empty( $employee->branch_id ) ) {
			$sched = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE branch_id = %d LIMIT 1", $employee->branch_id )
			);
			if ( $sched ) {
				return $sched;
			}
		}

		// 3. Fallback to default schedule
		$sched = $wpdb->get_row( "SELECT * FROM {$table} WHERE is_default = 1 LIMIT 1" );
		if ( $sched ) {
			return $sched;
		}

		// Default hardcoded policy object
		return (object) array(
			'id'                      => 0,
			'schedule_name'           => 'Standard Shift (8:00 AM - 5:00 PM)',
			'working_days'            => 'Mon,Tue,Wed,Thu,Fri',
			'shift_start_time'        => '08:00:00',
			'shift_end_time'          => '17:00:00',
			'grace_period_minutes'    => 15,
			'late_threshold_minutes'  => 30,
			'early_departure_minutes' => 15,
			'break_duration_minutes'  => 60,
			'is_overnight'            => 0,
			'overtime_rate'           => 1.50,
		);
	}

	/**
	 * Calculate lateness, early departure, worked hours, and overtime.
	 *
	 * @param object $schedule Schedule object.
	 * @param string $check_in_datetime Y-m-d H:i:s
	 * @param string $check_out_datetime Y-m-d H:i:s
	 * @return array Calculated metrics.
	 */
	public static function calculate_attendance_metrics( $schedule, $check_in_datetime, $check_out_datetime = null ) {
		$check_in_ts  = strtotime( $check_in_datetime );
		$check_out_ts = $check_out_datetime ? strtotime( $check_out_datetime ) : null;

		$date_str         = date( 'Y-m-d', $check_in_ts );
		$shift_start_ts   = strtotime( $date_str . ' ' . $schedule->shift_start_time );
		$grace_cutoff_ts  = $shift_start_ts + ( $schedule->grace_period_minutes * 60 );
		$shift_end_ts     = strtotime( $date_str . ' ' . $schedule->shift_end_time );

		if ( $schedule->is_overnight && $shift_end_ts <= $shift_start_ts ) {
			$shift_end_ts += 86400; // Add 24 hours for overnight end
		}

		// Lateness calculation
		$is_late          = false;
		$lateness_minutes = 0;

		if ( $check_in_ts > $grace_cutoff_ts ) {
			$is_late          = true;
			$lateness_minutes = ceil( ( $check_in_ts - $shift_start_ts ) / 60 );
		}

		// Check-out metrics
		$is_early_departure      = false;
		$early_departure_minutes = 0;
		$total_worked_minutes    = 0;
		$overtime_minutes        = 0;

		if ( $check_out_ts ) {
			$raw_duration = max( 0, $check_out_ts - $check_in_ts );
			$raw_minutes  = floor( $raw_duration / 60 );

			// Subtract break duration if shift worked is longer than 5 hours
			$break_mins           = ( $raw_minutes >= 300 ) ? $schedule->break_duration_minutes : 0;
			$total_worked_minutes = max( 0, $raw_minutes - $break_mins );

			// Early departure
			if ( $check_out_ts < $shift_end_ts ) {
				$early_diff = ceil( ( $shift_end_ts - $check_out_ts ) / 60 );
				if ( $early_diff > $schedule->early_departure_minutes ) {
					$is_early_departure      = true;
					$early_departure_minutes = $early_diff;
				}
			}

			// Overtime
			if ( $check_out_ts > $shift_end_ts ) {
				$overtime_minutes = floor( ( $check_out_ts - $shift_end_ts ) / 60 );
			}
		}

		return array(
			'is_late'                 => $is_late ? 1 : 0,
			'lateness_minutes'        => $lateness_minutes,
			'is_early_departure'      => $is_early_departure ? 1 : 0,
			'early_departure_minutes' => $early_departure_minutes,
			'total_worked_minutes'    => $total_worked_minutes,
			'overtime_minutes'        => $overtime_minutes,
		);
	}
}
