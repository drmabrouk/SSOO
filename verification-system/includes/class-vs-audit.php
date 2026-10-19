<?php
/**
 * Audit Logging System
 *
 * @package VerificationSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Audit {

	/**
	 * Log a security, system, or operational event.
	 *
	 * @param int    $user_id User ID performing the action.
	 * @param string $actor_name Actor full name.
	 * @param string $actor_role Actor application role.
	 * @param string $action Action slug.
	 * @param string $target_type Target entity type.
	 * @param int    $target_id Target entity ID.
	 * @param int    $branch_id Branch ID.
	 * @param array  $details Additional detail array (passwords/keys automatically stripped).
	 */
	public static function log_event( $user_id, $actor_name, $actor_role, $action, $target_type = '', $target_id = 0, $branch_id = 0, $details = array() ) {
		global $wpdb;

		$table = $wpdb->prefix . 'vs_audit_logs';

		// Sanitize details and filter sensitive values
		$clean_details = self::sanitize_details( $details );

		$ip         = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';

		$wpdb->insert(
			$table,
			array(
				'user_id'     => absint( $user_id ),
				'actor_name'  => sanitize_text_field( $actor_name ),
				'actor_role'  => sanitize_text_field( $actor_role ),
				'action'      => sanitize_text_field( $action ),
				'target_type' => sanitize_text_field( $target_type ),
				'target_id'   => absint( $target_id ),
				'branch_id'   => absint( $branch_id ),
				'ip_address'  => $ip,
				'user_agent'  => $user_agent,
				'details'     => wp_json_encode( $clean_details ),
				'created_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Remove secret fields like passwords, OTPs, or API secrets before writing log.
	 */
	private static function sanitize_details( $details ) {
		if ( ! is_array( $details ) ) {
			return array();
		}

		$sensitive_keys = array( 'password', 'user_pass', 'otp', 'otp_code', 'secret', 'shared_secret', 'api_key', 'token' );

		foreach ( $details as $key => $value ) {
			if ( in_array( strtolower( $key ), $sensitive_keys, true ) ) {
				$details[ $key ] = '***REDACTED***';
			} elseif ( is_array( $value ) ) {
				$details[ $key ] = self::sanitize_details( $value );
			}
		}

		return $details;
	}

	/**
	 * Get audit logs using single $wpdb->prepare call to avoid double-prepare formatting bugs.
	 *
	 * @param array $args Query filters.
	 * @return array Array of audit log rows.
	 */
	public static function get_logs( $args = array() ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'vs_audit_logs';
		$user   = wp_get_current_user();

		$where  = 'WHERE 1=1';
		$params = array();

		// Branch Manager isolation
		if ( VS_Roles::is_branch_manager( $user ) ) {
			$employee = VS_Auth::get_current_employee();
			$b_id     = $employee ? $employee->branch_id : 0;
			$where   .= ' AND branch_id = %d';
			$params[] = $b_id;
		} elseif ( ! VS_Roles::is_system_admin( $user ) && ! VS_Roles::is_general_manager( $user ) ) {
			// Employee can only see their own logs
			$where   .= ' AND user_id = %d';
			$params[] = $user->ID;
		}

		if ( ! empty( $args['branch_id'] ) && VS_Branch::can_manage_branch( $args['branch_id'] ) ) {
			$where   .= ' AND branch_id = %d';
			$params[] = absint( $args['branch_id'] );
		}

		if ( ! empty( $args['action'] ) ) {
			$where   .= ' AND action = %s';
			$params[] = sanitize_text_field( $args['action'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$search_term = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where      .= ' AND (actor_name LIKE %s OR action LIKE %s OR ip_address LIKE %s)';
			$params[]    = $search_term;
			$params[]    = $search_term;
			$params[]    = $search_term;
		}

		if ( ! empty( $args['start_date'] ) && ! empty( $args['end_date'] ) ) {
			$where   .= ' AND created_at BETWEEN %s AND %s';
			$params[] = sanitize_text_field( $args['start_date'] ) . ' 00:00:00';
			$params[] = sanitize_text_field( $args['end_date'] ) . ' 23:59:59';
		}

		$limit    = isset( $args['limit'] ) ? absint( $args['limit'] ) : 50;
		$offset   = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;
		$params[] = $limit;
		$params[] = $offset;

		$sql = "SELECT * FROM {$table} {$where} ORDER BY id DESC LIMIT %d OFFSET %d";

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}
}
