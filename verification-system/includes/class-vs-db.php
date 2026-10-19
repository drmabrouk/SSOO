<?php
/**
 * Database Schema Manager for Verification System.
 *
 * @package VerificationSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_DB {

	/**
	 * Database version.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Get table names with WP prefix.
	 *
	 * @return array
	 */
	public static function get_table_names() {
		global $wpdb;
		$prefix = $wpdb->prefix;

		return array(
			'branches'            => $prefix . 'vs_branches',
			'employees'           => $prefix . 'vs_employees',
			'schedules'           => $prefix . 'vs_schedules',
			'attendance_sessions' => $prefix . 'vs_attendance_sessions',
			'attendance_events'   => $prefix . 'vs_attendance_events',
			'wifi_configs'        => $prefix . 'vs_wifi_configs',
			'wifi_logs'           => $prefix . 'vs_wifi_logs',
			'audit_logs'          => $prefix . 'vs_audit_logs',
			'leave_requests'      => $prefix . 'vs_leave_requests',
			'corrections'         => $prefix . 'vs_corrections',
			'otps'                => $prefix . 'vs_otps',
		);
	}

	/**
	 * Create or update database tables.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$tables          = self::get_table_names();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = array();

		// Branches Table
		$sql[] = "CREATE TABLE {$tables['branches']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_code varchar(50) NOT NULL,
			name varchar(191) NOT NULL,
			address text NULL,
			contact_phone varchar(50) NULL,
			contact_email varchar(100) NULL,
			manager_id bigint(20) UNSIGNED DEFAULT 0,
			wifi_config_id bigint(20) UNSIGNED DEFAULT 0,
			schedule_id bigint(20) UNSIGNED DEFAULT 0,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY branch_code (branch_code),
			KEY is_active (is_active)
		) $charset_collate;";

		// Employees Table
		$sql[] = "CREATE TABLE {$tables['employees']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			employee_code varchar(50) NOT NULL,
			full_name varchar(191) NOT NULL,
			phone_number varchar(50) NOT NULL,
			email varchar(100) NULL,
			app_role varchar(50) NOT NULL DEFAULT 'vs_employee',
			branch_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			department varchar(100) NULL,
			job_title varchar(100) NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			start_date date NULL,
			schedule_id bigint(20) UNSIGNED DEFAULT 0,
			device_token varchar(191) NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY employee_code (employee_code),
			UNIQUE KEY phone_number (phone_number),
			KEY user_id (user_id),
			KEY branch_id (branch_id),
			KEY status (status)
		) $charset_collate;";

		// Schedules Table
		$sql[] = "CREATE TABLE {$tables['schedules']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id bigint(20) UNSIGNED DEFAULT 0,
			schedule_name varchar(100) NOT NULL,
			working_days varchar(100) NOT NULL DEFAULT 'Mon,Tue,Wed,Thu,Fri',
			shift_start_time time NOT NULL DEFAULT '08:00:00',
			shift_end_time time NOT NULL DEFAULT '17:00:00',
			grace_period_minutes int(11) NOT NULL DEFAULT 15,
			late_threshold_minutes int(11) NOT NULL DEFAULT 30,
			early_departure_minutes int(11) NOT NULL DEFAULT 15,
			break_duration_minutes int(11) NOT NULL DEFAULT 60,
			is_overnight tinyint(1) NOT NULL DEFAULT 0,
			overtime_rate decimal(5,2) NOT NULL DEFAULT 1.50,
			is_default tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY (id),
			KEY branch_id (branch_id)
		) $charset_collate;";

		// Attendance Sessions Table
		$sql[] = "CREATE TABLE {$tables['attendance_sessions']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			employee_id bigint(20) UNSIGNED NOT NULL,
			branch_id bigint(20) UNSIGNED NOT NULL,
			date date NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			check_in_time datetime NULL,
			check_out_time datetime NULL,
			check_in_wifi_id bigint(20) UNSIGNED DEFAULT 0,
			check_out_wifi_id bigint(20) UNSIGNED DEFAULT 0,
			check_in_verified tinyint(1) NOT NULL DEFAULT 0,
			check_out_verified tinyint(1) NOT NULL DEFAULT 0,
			total_worked_minutes int(11) NOT NULL DEFAULT 0,
			overtime_minutes int(11) NOT NULL DEFAULT 0,
			lateness_minutes int(11) NOT NULL DEFAULT 0,
			early_departure_minutes int(11) NOT NULL DEFAULT 0,
			is_late tinyint(1) NOT NULL DEFAULT 0,
			is_early_departure tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY (id),
			KEY employee_date (employee_id, date),
			KEY branch_date (branch_id, date),
			KEY status (status)
		) $charset_collate;";

		// Attendance Events Table
		$sql[] = "CREATE TABLE {$tables['attendance_events']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id bigint(20) UNSIGNED NOT NULL,
			employee_id bigint(20) UNSIGNED NOT NULL,
			branch_id bigint(20) UNSIGNED NOT NULL,
			event_type varchar(30) NOT NULL,
			event_time datetime NOT NULL,
			wifi_ssid varchar(100) NULL,
			wifi_bssid varchar(50) NULL,
			client_ip varchar(45) NULL,
			verified tinyint(1) NOT NULL DEFAULT 0,
			verification_method varchar(50) NOT NULL DEFAULT 'wifi',
			notes text NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY (id),
			KEY session_id (session_id),
			KEY employee_id (employee_id),
			KEY event_type (event_type)
		) $charset_collate;";

		// Wi-Fi Configs Table
		$sql[] = "CREATE TABLE {$tables['wifi_configs']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id bigint(20) UNSIGNED NOT NULL,
			config_name varchar(100) NOT NULL,
			auth_type varchar(50) NOT NULL DEFAULT 'hmac_token',
			ssid varchar(100) NOT NULL,
			allowed_bssids text NULL,
			shared_secret varchar(255) NULL,
			api_endpoint varchar(255) NULL,
			api_key varchar(255) NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY (id),
			KEY branch_id (branch_id),
			KEY is_active (is_active)
		) $charset_collate;";

		// Wi-Fi Logs Table
		$sql[] = "CREATE TABLE {$tables['wifi_logs']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			employee_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			branch_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			ssid varchar(100) NULL,
			bssid varchar(50) NULL,
			client_ip varchar(45) NULL,
			verification_status varchar(30) NOT NULL,
			failure_reason text NULL,
			raw_payload text NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY (id),
			KEY employee_id (employee_id),
			KEY branch_id (branch_id),
			KEY verification_status (verification_status)
		) $charset_collate;";

		// Audit Logs Table
		$sql[] = "CREATE TABLE {$tables['audit_logs']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			actor_name varchar(191) NOT NULL DEFAULT 'System',
			actor_role varchar(50) NOT NULL DEFAULT 'guest',
			action varchar(100) NOT NULL,
			target_type varchar(50) NULL,
			target_id bigint(20) UNSIGNED DEFAULT 0,
			branch_id bigint(20) UNSIGNED DEFAULT 0,
			ip_address varchar(45) NULL,
			user_agent text NULL,
			details longtext NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY action (action),
			KEY branch_id (branch_id),
			KEY created_at (created_at)
		) $charset_collate;";

		// Leave Requests Table
		$sql[] = "CREATE TABLE {$tables['leave_requests']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			employee_id bigint(20) UNSIGNED NOT NULL,
			branch_id bigint(20) UNSIGNED NOT NULL,
			leave_type varchar(50) NOT NULL,
			start_date date NOT NULL,
			end_date date NOT NULL,
			reason text NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			approved_by bigint(20) UNSIGNED DEFAULT 0,
			comments text NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY (id),
			KEY employee_id (employee_id),
			KEY branch_id (branch_id),
			KEY status (status)
		) $charset_collate;";

		// Corrections Table
		$sql[] = "CREATE TABLE {$tables['corrections']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id bigint(20) UNSIGNED DEFAULT 0,
			employee_id bigint(20) UNSIGNED NOT NULL,
			branch_id bigint(20) UNSIGNED NOT NULL,
			requested_check_in datetime NULL,
			requested_check_out datetime NULL,
			reason text NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			reviewed_by bigint(20) UNSIGNED DEFAULT 0,
			comments text NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY (id),
			KEY employee_id (employee_id),
			KEY branch_id (branch_id),
			KEY status (status)
		) $charset_collate;";

		// OTPs Table
		$sql[] = "CREATE TABLE {$tables['otps']} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			phone_number varchar(50) NOT NULL,
			otp_hash varchar(255) NOT NULL,
			attempt_count int(11) NOT NULL DEFAULT 0,
			expires_at datetime NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY (id),
			KEY phone_number (phone_number),
			KEY expires_at (expires_at)
		) $charset_collate;";

		foreach ( $sql as $query ) {
			dbDelta( $query );
		}

		update_option( 'vs_db_version', self::DB_VERSION );
	}
}
