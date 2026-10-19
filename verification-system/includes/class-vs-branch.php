<?php
/**
 * Branch Management & Data Isolation
 *
 * @package VerificationSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Branch {

	/**
	 * Create a new branch with timezone, address, contact, and network configuration.
	 *
	 * @param array $data Branch data fields.
	 * @return int|WP_Error Branch ID or WP_Error on failure.
	 */
	public static function create_branch( $data ) {
		global $wpdb;

		if ( ! current_user_can( 'administrator' ) && ! VS_Roles::is_general_manager() ) {
			return new WP_Error( 'permission_denied', __( 'Permission denied to create branches.', 'verification-system' ) );
		}

		$branch_code = isset( $data['branch_code'] ) ? sanitize_text_field( $data['branch_code'] ) : '';
		$name        = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';

		if ( empty( $branch_code ) || empty( $name ) ) {
			return new WP_Error( 'missing_fields', __( 'Branch code and branch name are required.', 'verification-system' ) );
		}

		$table = $wpdb->prefix . 'vs_branches';

		// Check duplicate branch code
		$existing = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE branch_code = %s LIMIT 1", $branch_code )
		);
		if ( $existing ) {
			return new WP_Error( 'duplicate_code', __( 'A branch with this branch code already exists.', 'verification-system' ) );
		}

		$insert_data = array(
			'branch_code'   => $branch_code,
			'name'          => $name,
			'address'       => isset( $data['address'] ) ? sanitize_textarea_field( $data['address'] ) : '',
			'contact_phone' => isset( $data['contact_phone'] ) ? sanitize_text_field( $data['contact_phone'] ) : '',
			'contact_email' => isset( $data['contact_email'] ) ? sanitize_email( $data['contact_email'] ) : '',
			'manager_id'    => isset( $data['manager_id'] ) ? absint( $data['manager_id'] ) : 0,
			'is_active'     => isset( $data['is_active'] ) ? absint( $data['is_active'] ) : 1,
			'created_at'    => current_time( 'mysql' ),
		);

		$wpdb->insert( $table, $insert_data );
		$branch_id = $wpdb->insert_id;

		if ( $branch_id ) {
			// Save optional initial Wi-Fi network configuration for the new branch if provided
			if ( ! empty( $data['wifi_ssid'] ) ) {
				$wifi_table = $wpdb->prefix . 'vs_wifi_configs';
				$wpdb->insert(
					$wifi_table,
					array(
						'branch_id'      => $branch_id,
						'config_name'    => $name . ' Approved Wi-Fi',
						'auth_type'      => isset( $data['wifi_auth_type'] ) ? sanitize_text_field( $data['wifi_auth_type'] ) : 'hmac_token',
						'ssid'           => sanitize_text_field( $data['wifi_ssid'] ),
						'allowed_bssids' => isset( $data['wifi_bssids'] ) ? sanitize_textarea_field( $data['wifi_bssids'] ) : '',
						'shared_secret'  => isset( $data['wifi_secret'] ) ? sanitize_text_field( $data['wifi_secret'] ) : '',
						'api_endpoint'   => isset( $data['wifi_endpoint'] ) ? sanitize_text_field( $data['wifi_endpoint'] ) : '',
						'api_key'        => isset( $data['wifi_key'] ) ? sanitize_text_field( $data['wifi_key'] ) : '',
						'is_active'      => 1,
						'created_at'     => current_time( 'mysql' ),
					)
				);
			}

			VS_Audit::log_event(
				get_current_user_id(),
				wp_get_current_user()->display_name,
				'manager',
				'create_branch',
				'branch',
				$branch_id,
				$branch_id,
				array( 'branch_code' => $branch_code, 'name' => $name )
			);
		}

		return $branch_id;
	}

	/**
	 * Update an existing branch.
	 *
	 * @param int   $branch_id Branch ID.
	 * @param array $data Branch fields.
	 * @return bool|WP_Error True on success or WP_Error.
	 */
	public static function update_branch( $branch_id, $data ) {
		global $wpdb;

		if ( ! self::can_manage_branch( $branch_id ) ) {
			return new WP_Error( 'permission_denied', __( 'Permission denied to update this branch.', 'verification-system' ) );
		}

		$table = $wpdb->prefix . 'vs_branches';
		$update_data = array();

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
		}
		if ( isset( $data['address'] ) ) {
			$update_data['address'] = sanitize_textarea_field( $data['address'] );
		}
		if ( isset( $data['contact_phone'] ) ) {
			$update_data['contact_phone'] = sanitize_text_field( $data['contact_phone'] );
		}
		if ( isset( $data['contact_email'] ) ) {
			$update_data['contact_email'] = sanitize_email( $data['contact_email'] );
		}
		if ( isset( $data['manager_id'] ) ) {
			$update_data['manager_id'] = absint( $data['manager_id'] );
		}
		if ( isset( $data['is_active'] ) ) {
			$update_data['is_active'] = absint( $data['is_active'] );
		}

		if ( ! empty( $update_data ) ) {
			$wpdb->update( $table, $update_data, array( 'id' => $branch_id ) );

			VS_Audit::log_event(
				get_current_user_id(),
				wp_get_current_user()->display_name,
				'manager',
				'update_branch',
				'branch',
				$branch_id,
				$branch_id,
				$update_data
			);
		}

		return true;
	}

	/**
	 * Get branch by ID.
	 *
	 * @param int $branch_id Branch ID.
	 * @return object|null Branch object or null.
	 */
	public static function get_branch( $branch_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'vs_branches';

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $branch_id )
		);
	}

	/**
	 * Get list of branches accessible to current user.
	 *
	 * @return array List of branch objects.
	 */
	public static function get_accessible_branches() {
		global $wpdb;

		$table = $wpdb->prefix . 'vs_branches';
		$user  = wp_get_current_user();

		if ( VS_Roles::is_system_admin( $user ) || VS_Roles::is_general_manager( $user ) ) {
			return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC" );
		}

		// Branch Manager: return assigned branch
		if ( VS_Roles::is_branch_manager( $user ) ) {
			$employee = VS_Auth::get_current_employee();
			if ( $employee && $employee->branch_id ) {
				return $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d ORDER BY name ASC", $employee->branch_id )
				);
			}
		}

		// Employee: return assigned branch
		$employee = VS_Auth::get_current_employee();
		if ( $employee && $employee->branch_id ) {
			return $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d ORDER BY name ASC", $employee->branch_id )
			);
		}

		return array();
	}

	/**
	 * Verify if current user can access/manage target branch.
	 *
	 * @param int $branch_id Branch ID.
	 * @return bool
	 */
	public static function can_manage_branch( $branch_id ) {
		$user = wp_get_current_user();

		if ( VS_Roles::is_system_admin( $user ) || VS_Roles::is_general_manager( $user ) ) {
			return true;
		}

		if ( VS_Roles::is_branch_manager( $user ) ) {
			$employee = VS_Auth::get_current_employee();
			return ( $employee && (int) $employee->branch_id === (int) $branch_id );
		}

		return false;
	}
}
