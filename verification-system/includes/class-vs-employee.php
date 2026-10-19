<?php
/**
 * Employee Management & CSV Import/Export
 *
 * @package VerificationSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Employee {

	/**
	 * Create employee account and WP User mapping.
	 *
	 * @param array $data Employee parameters.
	 * @return int|WP_Error Employee ID or WP_Error.
	 */
	public static function create_employee( $data ) {
		global $wpdb;

		$full_name     = isset( $data['full_name'] ) ? sanitize_text_field( $data['full_name'] ) : '';
		$phone_number  = isset( $data['phone_number'] ) ? sanitize_text_field( $data['phone_number'] ) : '';
		$employee_code = isset( $data['employee_code'] ) ? sanitize_text_field( $data['employee_code'] ) : '';
		$email         = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
		$branch_id     = isset( $data['branch_id'] ) ? absint( $data['branch_id'] ) : 0;
		$app_role      = isset( $data['app_role'] ) ? sanitize_text_field( $data['app_role'] ) : VS_Roles::ROLE_EMPLOYEE;
		$password      = isset( $data['password'] ) && ! empty( $data['password'] ) ? $data['password'] : wp_generate_password( 12, true );

		if ( empty( $full_name ) || empty( $phone_number ) || empty( $employee_code ) || empty( $branch_id ) ) {
			return new WP_Error( 'missing_fields', __( 'Full name, employee ID, phone number, and branch are required.', 'verification-system' ) );
		}

		if ( ! VS_Branch::can_manage_branch( $branch_id ) ) {
			return new WP_Error( 'permission_denied', __( 'You do not have authorization to create employees in this branch.', 'verification-system' ) );
		}

		$normalized_phone = VS_Auth::normalize_phone( $phone_number );
		if ( ! $normalized_phone ) {
			return new WP_Error( 'invalid_phone', __( 'Invalid UAE phone number format.', 'verification-system' ) );
		}

		$table = $wpdb->prefix . 'vs_employees';

		// Check uniqueness
		$existing_code = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE employee_code = %s LIMIT 1", $employee_code )
		);
		if ( $existing_code ) {
			return new WP_Error( 'duplicate_code', __( 'An employee with this Employee ID already exists.', 'verification-system' ) );
		}

		$existing_phone = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE phone_number = %s LIMIT 1", $normalized_phone )
		);
		if ( $existing_phone ) {
			return new WP_Error( 'duplicate_phone', __( 'An employee with this phone number already exists.', 'verification-system' ) );
		}

		// Create WordPress User
		$username = 'emp_' . preg_replace( '/\D/', '', $normalized_phone );
		if ( empty( $email ) ) {
			$email = $username . '@vs-system.local';
		}

		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			// If username exists, try generating with employee code
			$username = 'emp_' . strtolower( sanitize_user( $employee_code ) );
			$user_id  = wp_create_user( $username, $password, $email );
			if ( is_wp_error( $user_id ) ) {
				return $user_id;
			}
		}

		// Set WP User Role & Display Name
		$user = get_user_by( 'id', $user_id );
		$user->set_role( $app_role );
		wp_update_user( array(
			'ID'           => $user_id,
			'display_name' => $full_name,
			'first_name'   => $full_name,
		) );

		// Insert Employee Record
		$insert_data = array(
			'user_id'       => $user_id,
			'employee_code' => $employee_code,
			'full_name'     => $full_name,
			'phone_number'  => $normalized_phone,
			'email'         => $email,
			'app_role'      => $app_role,
			'branch_id'     => $branch_id,
			'department'    => isset( $data['department'] ) ? sanitize_text_field( $data['department'] ) : '',
			'job_title'     => isset( $data['job_title'] ) ? sanitize_text_field( $data['job_title'] ) : '',
			'status'        => isset( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'active',
			'start_date'    => isset( $data['start_date'] ) ? sanitize_text_field( $data['start_date'] ) : current_time( 'Y-m-d' ),
			'schedule_id'   => isset( $data['schedule_id'] ) ? absint( $data['schedule_id'] ) : 0,
			'created_at'    => current_time( 'mysql' ),
		);

		$wpdb->insert( $table, $insert_data );
		$employee_id = $wpdb->insert_id;

		VS_Audit::log_event(
			get_current_user_id(),
			wp_get_current_user()->display_name,
			'manager',
			'create_employee',
			'employee',
			$employee_id,
			$branch_id,
			array( 'employee_code' => $employee_code, 'name' => $full_name, 'role' => $app_role )
		);

		return $employee_id;
	}

	/**
	 * Update employee profile.
	 *
	 * @param int   $employee_id Employee ID.
	 * @param array $data Parameters.
	 * @return bool|WP_Error True or error.
	 */
	public static function update_employee( $employee_id, $data ) {
		global $wpdb;

		$employee = self::get_employee( $employee_id );
		if ( ! $employee ) {
			return new WP_Error( 'not_found', __( 'Employee record not found.', 'verification-system' ) );
		}

		if ( ! VS_Branch::can_manage_branch( $employee->branch_id ) ) {
			return new WP_Error( 'permission_denied', __( 'Permission denied to update this employee.', 'verification-system' ) );
		}

		$table       = $wpdb->prefix . 'vs_employees';
		$update_data = array();

		if ( isset( $data['full_name'] ) ) {
			$update_data['full_name'] = sanitize_text_field( $data['full_name'] );
		}
		if ( isset( $data['email'] ) ) {
			$update_data['email'] = sanitize_email( $data['email'] );
		}
		if ( isset( $data['department'] ) ) {
			$update_data['department'] = sanitize_text_field( $data['department'] );
		}
		if ( isset( $data['job_title'] ) ) {
			$update_data['job_title'] = sanitize_text_field( $data['job_title'] );
		}
		if ( isset( $data['status'] ) ) {
			$update_data['status'] = sanitize_text_field( $data['status'] );
		}
		if ( isset( $data['app_role'] ) ) {
			$update_data['app_role'] = sanitize_text_field( $data['app_role'] );
			// Update WP User role
			if ( $employee->user_id ) {
				$user = get_user_by( 'id', $employee->user_id );
				if ( $user ) {
					$user->set_role( $update_data['app_role'] );
				}
			}
		}
		if ( isset( $data['branch_id'] ) ) {
			$new_branch = absint( $data['branch_id'] );
			if ( VS_Branch::can_manage_branch( $new_branch ) ) {
				$update_data['branch_id'] = $new_branch;
			}
		}

		if ( ! empty( $data['password'] ) && $employee->user_id ) {
			wp_set_password( $data['password'], $employee->user_id );
		}

		if ( ! empty( $update_data ) ) {
			$wpdb->update( $table, $update_data, array( 'id' => $employee_id ) );

			VS_Audit::log_event(
				get_current_user_id(),
				wp_get_current_user()->display_name,
				'manager',
				'update_employee',
				'employee',
				$employee_id,
				$employee->branch_id,
				$update_data
			);
		}

		return true;
	}

	/**
	 * Get employee by ID.
	 *
	 * @param int $employee_id Employee ID.
	 * @return object|null Record.
	 */
	public static function get_employee( $employee_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vs_employees';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $employee_id )
		);
	}

	/**
	 * Import employees from CSV file buffer or file path.
	 *
	 * @param string $file_path Path to uploaded CSV file.
	 * @param int    $default_branch_id Default branch ID if not in CSV.
	 * @return array Results summary (imported, skipped, errors).
	 */
	public static function import_from_csv( $file_path, $default_branch_id = 0 ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'CSV file cannot be opened.', 'verification-system' ),
			);
		}

		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to read CSV stream.', 'verification-system' ),
			);
		}

		$header    = fgetcsv( $handle );
		$imported  = 0;
		$skipped   = 0;
		$row_index = 1;
		$errors    = array();

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$row_index++;
			if ( empty( $row ) || empty( array_filter( $row ) ) ) {
				continue;
			}

			// Map CSV fields: Employee ID, Full Name, Phone Number, Email, Department, Job Title, Branch ID
			$emp_code  = isset( $row[0] ) ? trim( $row[0] ) : '';
			$full_name = isset( $row[1] ) ? trim( $row[1] ) : '';
			$phone     = isset( $row[2] ) ? trim( $row[2] ) : '';
			$email     = isset( $row[3] ) ? trim( $row[3] ) : '';
			$dept      = isset( $row[4] ) ? trim( $row[4] ) : '';
			$title     = isset( $row[5] ) ? trim( $row[5] ) : '';
			$branch_id = isset( $row[6] ) && is_numeric( $row[6] ) ? absint( $row[6] ) : $default_branch_id;

			$res = self::create_employee( array(
				'employee_code' => $emp_code,
				'full_name'     => $full_name,
				'phone_number'  => $phone,
				'email'         => $email,
				'department'    => $dept,
				'job_title'     => $title,
				'branch_id'     => $branch_id,
			) );

			if ( is_wp_error( $res ) ) {
				$skipped++;
				$errors[] = sprintf( __( 'Row %d (%s): %s', 'verification-system' ), $row_index, $full_name, $res->get_error_message() );
			} else {
				$imported++;
			}
		}

		fclose( $handle );

		return array(
			'success'  => true,
			'imported' => $imported,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}

	/**
	 * Export employees to CSV string.
	 *
	 * @param int $branch_id Branch ID filter (0 for all accessible).
	 * @return string CSV text content.
	 */
	public static function export_to_csv( $branch_id = 0 ) {
		global $wpdb;

		$emp_table    = $wpdb->prefix . 'vs_employees';
		$branch_table = $wpdb->prefix . 'vs_branches';

		$user = wp_get_current_user();
		$where = "WHERE 1=1";

		if ( $branch_id > 0 ) {
			if ( ! VS_Branch::can_manage_branch( $branch_id ) ) {
				return '';
			}
			$where .= $wpdb->prepare( " AND e.branch_id = %d", $branch_id );
		} elseif ( VS_Roles::is_branch_manager( $user ) ) {
			$emp = VS_Auth::get_current_employee();
			$b_id = $emp ? $emp->branch_id : 0;
			$where .= $wpdb->prepare( " AND e.branch_id = %d", $b_id );
		}

		$query = "SELECT e.*, b.name as branch_name
			FROM {$emp_table} e
			LEFT JOIN {$branch_table} b ON e.branch_id = b.id
			{$where}
			ORDER BY e.id DESC";

		$employees = $wpdb->get_results( $query );

		$output = fopen( 'php://temp', 'r+' );
		fputcsv( $output, array( 'Employee ID', 'Full Name', 'Phone Number', 'Email', 'Role', 'Branch Name', 'Department', 'Job Title', 'Status', 'Start Date' ) );

		foreach ( $employees as $emp ) {
			fputcsv( $output, array(
				$emp->employee_code,
				$emp->full_name,
				$emp->phone_number,
				$emp->email,
				VS_Roles::get_role_label( $emp->app_role ),
				$emp->branch_name,
				$emp->department,
				$emp->job_title,
				ucfirst( $emp->status ),
				$emp->start_date,
			) );
		}

		rewind( $output );
		$csv_data = stream_get_contents( $output );
		fclose( $output );

		return $csv_data;
	}
}
