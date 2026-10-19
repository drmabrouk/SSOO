<?php
/**
 * REST API Controller
 *
 * @package VerificationSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Rest_API {

	/**
	 * Namespace for REST routes.
	 */
	const NAMESPACE = 'vs/v1';

	/**
	 * Register API routes.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register endpoints.
	 */
	public static function register_routes() {
		// Auth endpoints
		register_rest_route( self::NAMESPACE, '/auth/login', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'api_login' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NAMESPACE, '/auth/send-otp', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'api_send_otp' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NAMESPACE, '/auth/verify-otp', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'api_verify_otp' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NAMESPACE, '/auth/logout', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'api_logout' ),
			'permission_callback' => array( __CLASS__, 'check_logged_in' ),
		) );

		// Employee Dashboard data
		register_rest_route( self::NAMESPACE, '/dashboard', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_dashboard' ),
			'permission_callback' => array( __CLASS__, 'check_logged_in' ),
		) );

		// Attendance actions
		register_rest_route( self::NAMESPACE, '/attendance/check-in', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'check_in' ),
			'permission_callback' => array( __CLASS__, 'check_logged_in' ),
		) );

		register_rest_route( self::NAMESPACE, '/attendance/check-out', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'check_out' ),
			'permission_callback' => array( __CLASS__, 'check_logged_in' ),
		) );

		// Employees CRUD
		register_rest_route( self::NAMESPACE, '/employees', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_employees' ),
				'permission_callback' => array( __CLASS__, 'check_manager_permission' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_employee' ),
				'permission_callback' => array( __CLASS__, 'check_manager_permission' ),
			)
		) );

		register_rest_route( self::NAMESPACE, '/employees/import', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'import_employees' ),
			'permission_callback' => array( __CLASS__, 'check_manager_permission' ),
		) );

		register_rest_route( self::NAMESPACE, '/employees/export', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'export_employees' ),
			'permission_callback' => array( __CLASS__, 'check_manager_permission' ),
		) );

		// Branches CRUD
		register_rest_route( self::NAMESPACE, '/branches', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_branches' ),
				'permission_callback' => array( __CLASS__, 'check_logged_in' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_branch' ),
				'permission_callback' => array( __CLASS__, 'check_manager_permission' ),
			)
		) );

		// Reports
		register_rest_route( self::NAMESPACE, '/reports/attendance', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_reports' ),
			'permission_callback' => array( __CLASS__, 'check_logged_in' ),
		) );

		register_rest_route( self::NAMESPACE, '/reports/export', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'export_report_csv' ),
			'permission_callback' => array( __CLASS__, 'check_logged_in' ),
		) );

		// Wi-Fi Configuration endpoint
		register_rest_route( self::NAMESPACE, '/wifi/config', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_wifi_config' ),
				'permission_callback' => array( __CLASS__, 'check_manager_permission' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'save_wifi_config' ),
				'permission_callback' => array( __CLASS__, 'check_admin_permission' ),
			)
		) );

		// Audit Logs
		register_rest_route( self::NAMESPACE, '/audit-logs', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_audit_logs' ),
			'permission_callback' => array( __CLASS__, 'check_manager_permission' ),
		) );
	}

	public static function check_logged_in() {
		return is_user_logged_in();
	}

	public static function check_manager_permission() {
		return is_user_logged_in() && ( current_user_can( 'administrator' ) || VS_Roles::is_general_manager() || VS_Roles::is_branch_manager() );
	}

	public static function check_admin_permission() {
		return is_user_logged_in() && ( current_user_can( 'administrator' ) || VS_Roles::is_general_manager() );
	}

	public static function api_login( WP_REST_Request $request ) {
		$phone    = sanitize_text_field( $request->get_param( 'phone' ) );
		$password = $request->get_param( 'password' );

		$res = VS_Auth::authenticate_by_phone_and_password( $phone, $password );

		if ( is_wp_error( $res ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $res->get_error_message() ), 400 );
		}

		$employee = VS_Auth::get_current_employee();

		return new WP_REST_Response( array(
			'success'  => true,
			'message'  => __( 'Login successful.', 'verification-system' ),
			'user'     => array(
				'id'            => $res->ID,
				'name'          => $res->display_name,
				'role'          => $employee ? $employee->app_role : 'vs_employee',
				'employee_code' => $employee ? $employee->employee_code : '',
			),
		), 200 );
	}

	public static function api_send_otp( WP_REST_Request $request ) {
		$phone = sanitize_text_field( $request->get_param( 'phone' ) );
		$res   = VS_OTP::send_otp( $phone );

		$status_code = $res['success'] ? 200 : 400;
		return new WP_REST_Response( $res, $status_code );
	}

	public static function api_verify_otp( WP_REST_Request $request ) {
		$phone = sanitize_text_field( $request->get_param( 'phone' ) );
		$otp   = sanitize_text_field( $request->get_param( 'otp' ) );

		$res = VS_OTP::verify_otp( $phone, $otp );

		if ( is_wp_error( $res ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $res->get_error_message() ), 400 );
		}

		$employee = VS_Auth::get_current_employee();

		return new WP_REST_Response( array(
			'success' => true,
			'message' => __( 'OTP verification successful.', 'verification-system' ),
			'user'    => array(
				'id'   => $res->ID,
				'name' => $res->display_name,
				'role' => $employee ? $employee->app_role : 'vs_employee',
			),
		), 200 );
	}

	public static function api_logout() {
		VS_Auth::logout();
		return new WP_REST_Response( array( 'success' => true, 'message' => __( 'Logged out.', 'verification-system' ) ), 200 );
	}

	public static function get_dashboard() {
		$employee = VS_Auth::get_current_employee();
		if ( ! $employee ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Employee profile not found.', 'verification-system' ) ), 404 );
		}

		$branch  = VS_Branch::get_branch( $employee->branch_id );
		$status  = VS_Attendance::get_today_status( $employee->id );
		$sched   = VS_Schedule::get_schedule_for_employee( $employee );
		$history = VS_Reports::get_attendance_report( array( 'employee_id' => $employee->id, 'start_date' => date( 'Y-m-d', strtotime( '-7 days' ) ) ) );

		return new WP_REST_Response( array(
			'success'  => true,
			'employee' => array(
				'id'            => $employee->id,
				'full_name'     => $employee->full_name,
				'employee_code' => $employee->employee_code,
				'app_role'      => $employee->app_role,
				'branch_name'   => $branch ? $branch->name : 'Unassigned Branch',
			),
			'server_time' => current_time( 'Y-m-d H:i:s' ),
			'schedule'    => array(
				'start_time' => $sched->shift_start_time,
				'end_time'   => $sched->shift_end_time,
			),
			'attendance'  => $status,
			'history'     => $history['records'],
		), 200 );
	}

	public static function check_in( WP_REST_Request $request ) {
		$employee = VS_Auth::get_current_employee();
		if ( ! $employee ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Employee record missing.', 'verification-system' ) ), 400 );
		}

		$wifi_payload = array(
			'ssid'      => sanitize_text_field( $request->get_param( 'ssid' ) ),
			'bssid'     => sanitize_text_field( $request->get_param( 'bssid' ) ),
			'token'     => sanitize_text_field( $request->get_param( 'token' ) ),
			'timestamp' => absint( $request->get_param( 'timestamp' ) ),
			'client_ip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '',
		);

		$override = (bool) $request->get_param( 'exception_override' );

		$res = VS_Attendance::check_in( $employee, $wifi_payload, $override );

		if ( is_wp_error( $res ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $res->get_error_message() ), 400 );
		}

		return new WP_REST_Response( $res, 200 );
	}

	public static function check_out( WP_REST_Request $request ) {
		$employee = VS_Auth::get_current_employee();
		if ( ! $employee ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Employee record missing.', 'verification-system' ) ), 400 );
		}

		$wifi_payload = array(
			'ssid'      => sanitize_text_field( $request->get_param( 'ssid' ) ),
			'bssid'     => sanitize_text_field( $request->get_param( 'bssid' ) ),
			'token'     => sanitize_text_field( $request->get_param( 'token' ) ),
			'timestamp' => absint( $request->get_param( 'timestamp' ) ),
			'client_ip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '',
		);

		$override = (bool) $request->get_param( 'exception_override' );

		$res = VS_Attendance::check_out( $employee, $wifi_payload, $override );

		if ( is_wp_error( $res ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $res->get_error_message() ), 400 );
		}

		return new WP_REST_Response( $res, 200 );
	}

	public static function get_employees( WP_REST_Request $request ) {
		global $wpdb;
		$emp_table    = $wpdb->prefix . 'vs_employees';
		$branch_table = $wpdb->prefix . 'vs_branches';

		$user  = wp_get_current_user();
		$where = "WHERE 1=1";

		if ( VS_Roles::is_branch_manager( $user ) ) {
			$emp   = VS_Auth::get_current_employee();
			$b_id  = $emp ? $emp->branch_id : 0;
			$where .= $wpdb->prepare( " AND e.branch_id = %d", $b_id );
		}

		$employees = $wpdb->get_results( "SELECT e.*, b.name as branch_name FROM {$emp_table} e LEFT JOIN {$branch_table} b ON e.branch_id = b.id {$where} ORDER BY e.id DESC" );

		return new WP_REST_Response( array( 'success' => true, 'employees' => $employees ), 200 );
	}

	public static function create_employee( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		$res  = VS_Employee::create_employee( $data );

		if ( is_wp_error( $res ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $res->get_error_message() ), 400 );
		}

		return new WP_REST_Response( array( 'success' => true, 'employee_id' => $res, 'message' => __( 'Employee account created successfully.', 'verification-system' ) ), 200 );
	}

	public static function import_employees( WP_REST_Request $request ) {
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'No CSV file uploaded.', 'verification-system' ) ), 400 );
		}

		$branch_id = absint( $request->get_param( 'branch_id' ) );
		$res       = VS_Employee::import_from_csv( $files['file']['tmp_name'], $branch_id );

		return new WP_REST_Response( $res, $res['success'] ? 200 : 400 );
	}

	public static function export_employees() {
		$csv_content = VS_Employee::export_to_csv();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=employees-' . date( 'Y-m-d' ) . '.csv' );
		echo $csv_content;
		exit;
	}

	public static function get_branches() {
		$branches = VS_Branch::get_accessible_branches();
		return new WP_REST_Response( array( 'success' => true, 'branches' => $branches ), 200 );
	}

	public static function create_branch( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		$res  = VS_Branch::create_branch( $data );

		if ( is_wp_error( $res ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => $res->get_error_message() ), 400 );
		}

		return new WP_REST_Response( array( 'success' => true, 'branch_id' => $res, 'message' => __( 'Branch created successfully.', 'verification-system' ) ), 200 );
	}

	public static function get_reports( WP_REST_Request $request ) {
		$filters = array(
			'start_date'  => sanitize_text_field( $request->get_param( 'start_date' ) ),
			'end_date'    => sanitize_text_field( $request->get_param( 'end_date' ) ),
			'branch_id'   => absint( $request->get_param( 'branch_id' ) ),
			'employee_id' => absint( $request->get_param( 'employee_id' ) ),
		);

		$report = VS_Reports::get_attendance_report( $filters );
		return new WP_REST_Response( array( 'success' => true, 'data' => $report ), 200 );
	}

	public static function export_report_csv( WP_REST_Request $request ) {
		$filters = array(
			'start_date'  => sanitize_text_field( $request->get_param( 'start_date' ) ),
			'end_date'    => sanitize_text_field( $request->get_param( 'end_date' ) ),
			'branch_id'   => absint( $request->get_param( 'branch_id' ) ),
			'employee_id' => absint( $request->get_param( 'employee_id' ) ),
		);

		$csv_data = VS_Reports::export_attendance_csv( $filters );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=attendance-report-' . date( 'Y-m-d' ) . '.csv' );
		echo $csv_data;
		exit;
	}

	public static function get_wifi_config( WP_REST_Request $request ) {
		global $wpdb;
		$branch_id    = absint( $request->get_param( 'branch_id' ) );
		$config_table = $wpdb->prefix . 'vs_wifi_configs';

		$config = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$config_table} WHERE branch_id = %d LIMIT 1", $branch_id )
		);

		return new WP_REST_Response( array( 'success' => true, 'config' => $config ), 200 );
	}

	public static function save_wifi_config( WP_REST_Request $request ) {
		global $wpdb;
		$params       = $request->get_json_params();
		$branch_id    = absint( $params['branch_id'] );
		$config_table = $wpdb->prefix . 'vs_wifi_configs';

		if ( empty( $branch_id ) ) {
			return new WP_REST_Response( array( 'success' => false, 'message' => __( 'Branch ID is required.', 'verification-system' ) ), 400 );
		}

		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$config_table} WHERE branch_id = %d LIMIT 1", $branch_id ) );

		$save_data = array(
			'branch_id'     => $branch_id,
			'config_name'   => sanitize_text_field( $params['config_name'] ),
			'auth_type'     => sanitize_text_field( $params['auth_type'] ),
			'ssid'          => sanitize_text_field( $params['ssid'] ),
			'allowed_bssids'=> sanitize_textarea_field( $params['allowed_bssids'] ),
			'shared_secret' => sanitize_text_field( $params['shared_secret'] ),
			'api_endpoint'  => sanitize_text_field( $params['api_endpoint'] ),
			'api_key'       => sanitize_text_field( $params['api_key'] ),
			'is_active'     => absint( $params['is_active'] ),
		);

		if ( $existing ) {
			$wpdb->update( $config_table, $save_data, array( 'id' => $existing ) );
		} else {
			$save_data['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $config_table, $save_data );
		}

		return new WP_REST_Response( array( 'success' => true, 'message' => __( 'Wi-Fi configuration saved successfully.', 'verification-system' ) ), 200 );
	}

	public static function get_audit_logs( WP_REST_Request $request ) {
		$args = array(
			'branch_id' => absint( $request->get_param( 'branch_id' ) ),
			'action'    => sanitize_text_field( $request->get_param( 'action' ) ),
			'limit'     => absint( $request->get_param( 'limit' ) ? $request->get_param( 'limit' ) : 50 ),
		);

		$logs = VS_Audit::get_logs( $args );
		return new WP_REST_Response( array( 'success' => true, 'logs' => $logs ), 200 );
	}
}
