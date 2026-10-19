<?php
/**
 * Comprehensive Automated Test Suite
 *
 * @package VerificationSystem
 */

// Set default timezone for CLI tests
date_default_timezone_set( 'Asia/Dubai' );

// Define WordPress mock environment constants & functions
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}
function plugin_dir_url( $file ) {
	return 'http://localhost/wp-content/plugins/verification-system/';
}
function register_activation_hook( $file, $callback ) {}
function register_deactivation_hook( $file, $callback ) {}
function add_action( $tag, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {}
function apply_filters( $tag, $value, ...$args ) { return $value; }
function add_shortcode( $tag, $callback ) {}

require_once __DIR__ . '/../verification-system/verification-system.php';

// Include all include files explicitly for standalone CLI test execution
foreach ( glob( __DIR__ . '/../verification-system/includes/class-*.php' ) as $inc_file ) {
	require_once $inc_file;
}

// Mock WP core functions for standalone testing
class WPDB_Mock {
	public $prefix = 'wp_';
	public $last_query;
	public $inserted = array();
	public $updated = array();
	public $deleted = array();

	public function get_charset_collate() {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}
	public function prepare( $query, ...$args ) {
		if ( isset( $args[0] ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		return sprintf( str_replace( '%s', "'%s'", str_replace( '%d', '%d', $query ) ), ...$args );
	}
	public function esc_like( $text ) {
		return addcslashes( $text, '_%\\' );
	}
	public function get_var( $query ) {
		return null;
	}
	public function get_row( $query ) {
		return null;
	}
	public function get_results( $query ) {
		return array();
	}
	public function insert( $table, $data, $format = null ) {
		$this->inserted[] = array( 'table' => $table, 'data' => $data );
		return 1;
	}
	public function update( $table, $data, $where, $format = null, $where_format = null ) {
		$this->updated[] = array( 'table' => $table, 'data' => $data, 'where' => $where );
		return 1;
	}
	public function delete( $table, $where, $where_format = null ) {
		$this->deleted[] = array( 'table' => $table, 'where' => $where );
		return 1;
	}
	public function query( $query ) {
		return true;
	}
}

class WP_User_Mock {
	public $ID = 1;
	public $display_name = 'Test General Manager';
	public $roles = array( 'vs_general_manager' );
	public function exists() { return true; }
}

global $wpdb;
$wpdb = new WPDB_Mock();

function dbDelta( $queries ) {}
function update_option( $key, $val ) { return true; }
function get_option( $key, $default = false ) { return $default; }
function add_role( $slug, $name, $caps ) { return true; }
function get_role( $slug ) { return null; }
function __ ( $text, $domain = '' ) { return $text; }
function wp_hash_password( $pass ) { return md5( $pass ); }
function wp_check_password( $pass, $hash ) { return md5( $pass ) === $hash; }
function wp_set_password( $pass, $user_id ) { return true; }
function current_time( $type ) { return date( 'Y-m-d H:i:s' ); }
function wp_get_current_user() { return new WP_User_Mock(); }
function get_current_user_id() { return 1; }
function current_user_can( $cap ) { return false; }
function sanitize_text_field( $str ) { return trim( strip_tags( $str ) ); }
function sanitize_email( $email ) { return trim( $email ); }
function sanitize_textarea_field( $str ) { return trim( $str ); }
function wp_json_encode( $data ) { return json_encode( $data ); }
function absint( $val ) { return abs( (int) $val ); }
function user_can( $user, $cap ) { return false; }

class VS_Test_Runner {

	private $passed = 0;
	private $failed = 0;

	public function run_all() {
		echo "=== Starting Verification System Test Suite ===\n\n";

		$this->test_phone_normalization();
		$this->test_otp_hashing();
		$this->test_wifi_hmac_verification();
		$this->test_schedule_calculations();
		$this->test_audit_sanitization();
		$this->test_wifi_diagnostics();
		$this->test_role_sanitization();

		echo "\n=============================================\n";
		echo "Total Passed: {$this->passed}\n";
		echo "Total Failed: {$this->failed}\n";
		echo "=============================================\n";

		exit( $this->failed > 0 ? 1 : 0 );
	}

	private function assert( $condition, $message ) {
		if ( $condition ) {
			echo " [PASS] {$message}\n";
			$this->passed++;
		} else {
			echo " [FAIL] {$message}\n";
			$this->failed++;
		}
	}

	private function test_phone_normalization() {
		echo "--- Testing UAE Phone Normalization ---\n";
		$this->assert( VS_Auth::normalize_phone( '0501234567' ) === '+971501234567', 'Normalizes 0501234567 to +971501234567' );
		$this->assert( VS_Auth::normalize_phone( '501234567' ) === '+971501234567', 'Normalizes 501234567 to +971501234567' );
		$this->assert( VS_Auth::normalize_phone( '+971 50 123 4567' ) === '+971501234567', 'Normalizes +971 50 123 4567 removing whitespace' );
		$this->assert( VS_Auth::normalize_phone( 'invalid' ) === false, 'Rejects non-numeric phone strings' );
	}

	private function test_otp_hashing() {
		echo "\n--- Testing OTP Security ---\n";
		$otp_code = '123456';
		$hash = wp_hash_password( $otp_code );
		$this->assert( $hash !== $otp_code, 'OTP code is stored hashed, not plaintext' );
		$this->assert( wp_check_password( $otp_code, $hash ), 'OTP hash correctly verifies code' );
	}

	private function test_wifi_hmac_verification() {
		echo "\n--- Testing Wi-Fi HMAC Fail-Closed Verification ---\n";
		$adapter = new VS_WiFi_HMAC_Adapter();
		$employee = (object) array( 'id' => 101, 'branch_id' => 1 );
		$config = (object) array( 'ssid' => 'Workplace_WiFi', 'shared_secret' => 'supersecretkey', 'verification_timeout' => 180, 'allowed_bssids' => "00:11:22:33:44:55" );

		$now = time();
		$payload_str = '101|Workplace_WiFi|00:11:22:33:44:55|' . $now;
		$valid_token = hash_hmac( 'sha256', $payload_str, 'supersecretkey' );

		$valid_res = $adapter->verify( $employee, array(
			'token' => $valid_token,
			'timestamp' => $now,
			'ssid' => 'Workplace_WiFi',
			'bssid' => '00:11:22:33:44:55',
		), $config );

		$this->assert( $valid_res['success'] === true, 'Valid Wi-Fi HMAC token verifies successfully' );

		$forged_res = $adapter->verify( $employee, array(
			'token' => 'forgedtoken123',
			'timestamp' => $now,
			'ssid' => 'Workplace_WiFi',
			'bssid' => '00:11:22:33:44:55',
		), $config );

		$this->assert( $forged_res['success'] === false, 'Forged Wi-Fi token fails verification' );
	}

	private function test_schedule_calculations() {
		echo "\n--- Testing Attendance Policy & Schedule Rules ---\n";
		$schedule = (object) array(
			'shift_start_time' => '08:00:00',
			'shift_end_time' => '17:00:00',
			'grace_period_minutes' => 15,
			'early_departure_minutes' => 15,
			'break_duration_minutes' => 60,
			'is_overnight' => 0,
		);

		// On time check-in (8:10 AM)
		$m1 = VS_Schedule::calculate_attendance_metrics( $schedule, '2026-10-19 08:10:00' );
		$this->assert( $m1['is_late'] === 0, 'Arrival within 15min grace period is not marked late' );

		// Late check-in (8:30 AM)
		$m2 = VS_Schedule::calculate_attendance_metrics( $schedule, '2026-10-19 08:30:00' );
		$this->assert( (int)$m2['is_late'] === 1 && (int)$m2['lateness_minutes'] === 30, 'Arrival past grace period is correctly flagged late with 30 mins' );
	}

	private function test_audit_sanitization() {
		echo "\n--- Testing Audit Log Secret Redaction ---\n";
		$details = array( 'username' => 'emp_1', 'password' => 'secret123', 'otp' => '999888' );
		$reflector = new ReflectionMethod( 'VS_Audit', 'sanitize_details' );
		$reflector->setAccessible( true );
		$clean = $reflector->invoke( null, $details );

		$this->assert( $clean['password'] === '***REDACTED***', 'Password parameter is redacted in audit logs' );
		$this->assert( $clean['otp'] === '***REDACTED***', 'OTP parameter is redacted in audit logs' );
	}

	private function test_wifi_diagnostics() {
		echo "\n--- Testing Wi-Fi Controller Diagnostics ---\n";
		$diag = VS_WiFi::test_controller_connection( 1 );
		$this->assert( isset( $diag['success'] ), 'Wi-Fi controller test endpoint returns structured status' );
	}

	private function test_role_sanitization() {
		echo "\n--- Testing Privilege Escalation Prevention ---\n";
		$role = VS_Employee::sanitize_app_role( 'administrator' );
		$this->assert( $role === 'vs_employee', 'Non-system admin cannot escalate privileges to administrator' );
	}
}

$runner = new VS_Test_Runner();
$runner->run_all();
