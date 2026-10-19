<?php
/**
 * Role & Capability Management
 *
 * @package VerificationSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Roles {

	/**
	 * Role slugs.
	 */
	const ROLE_SYS_ADMIN = 'vs_system_admin';
	const ROLE_GEN_MGR   = 'vs_general_manager';
	const ROLE_BR_MGR    = 'vs_branch_manager';
	const ROLE_EMPLOYEE  = 'vs_employee';

	/**
	 * Initialize custom roles.
	 */
	public static function init() {
		self::add_roles();
		self::add_capabilities_to_wp_admin();
	}

	/**
	 * Register application roles in WordPress.
	 */
	public static function add_roles() {
		// 1. General Manager
		add_role(
			self::ROLE_GEN_MGR,
			__( 'General Manager', 'verification-system' ),
			array(
				'read'                     => true,
				'vs_access_app'            => true,
				'vs_manage_all_branches'   => true,
				'vs_manage_employees'      => true,
				'vs_view_all_reports'      => true,
				'vs_approve_corrections'   => true,
				'vs_approve_leaves'        => true,
				'vs_export_reports'        => true,
			)
		);

		// 2. Branch Manager
		add_role(
			self::ROLE_BR_MGR,
			__( 'Branch Manager', 'verification-system' ),
			array(
				'read'                     => true,
				'vs_access_app'            => true,
				'vs_manage_assigned_branch'=> true,
				'vs_manage_employees'      => true,
				'vs_view_branch_reports'   => true,
				'vs_approve_corrections'   => true,
				'vs_approve_leaves'        => true,
				'vs_export_reports'        => true,
			)
		);

		// 3. Employee
		add_role(
			self::ROLE_EMPLOYEE,
			__( 'Employee', 'verification-system' ),
			array(
				'read'                     => true,
				'vs_access_app'            => true,
				'vs_mark_attendance'       => true,
				'vs_view_own_attendance'   => true,
				'vs_submit_correction'     => true,
				'vs_submit_leave'          => true,
			)
		);
	}

	/**
	 * Assign all VS capabilities to WordPress Administrator.
	 */
	public static function add_capabilities_to_wp_admin() {
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$capabilities = array(
				'vs_access_app',
				'vs_manage_system',
				'vs_manage_all_branches',
				'vs_manage_assigned_branch',
				'vs_manage_employees',
				'vs_view_all_reports',
				'vs_view_branch_reports',
				'vs_view_own_attendance',
				'vs_mark_attendance',
				'vs_approve_corrections',
				'vs_approve_leaves',
				'vs_export_reports',
				'vs_submit_correction',
				'vs_submit_leave',
				'vs_manage_wifi',
				'vs_view_audit_logs',
			);

			foreach ( $capabilities as $cap ) {
				$admin_role->add_cap( $cap );
			}
		}
	}

	/**
	 * Get role label.
	 *
	 * @param string $role_slug Role slug.
	 * @return string
	 */
	public static function get_role_label( $role_slug ) {
		$roles = array(
			'administrator'     => 'System Administrator',
			self::ROLE_SYS_ADMIN => 'System Administrator',
			self::ROLE_GEN_MGR   => 'General Manager',
			self::ROLE_BR_MGR    => 'Branch Manager',
			self::ROLE_EMPLOYEE  => 'Employee',
		);

		return isset( $roles[ $role_slug ] ) ? $roles[ $role_slug ] : $role_slug;
	}

	/**
	 * Check if current user is System Admin.
	 *
	 * @param WP_User|int $user User object or ID.
	 * @return bool
	 */
	public static function is_system_admin( $user = null ) {
		if ( ! $user ) {
			$user = wp_get_current_user();
		} elseif ( is_numeric( $user ) ) {
			$user = get_userdata( $user );
		}

		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		return user_can( $user, 'administrator' ) || user_can( $user, 'vs_manage_system' );
	}

	/**
	 * Check if current user is General Manager.
	 *
	 * @param WP_User|int $user User object or ID.
	 * @return bool
	 */
	public static function is_general_manager( $user = null ) {
		if ( ! $user ) {
			$user = wp_get_current_user();
		} elseif ( is_numeric( $user ) ) {
			$user = get_userdata( $user );
		}

		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		return in_array( self::ROLE_GEN_MGR, (array) $user->roles, true ) || self::is_system_admin( $user );
	}

	/**
	 * Check if current user is Branch Manager.
	 *
	 * @param WP_User|int $user User object or ID.
	 * @return bool
	 */
	public static function is_branch_manager( $user = null ) {
		if ( ! $user ) {
			$user = wp_get_current_user();
		} elseif ( is_numeric( $user ) ) {
			$user = get_userdata( $user );
		}

		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		return in_array( self::ROLE_BR_MGR, (array) $user->roles, true );
	}

	/**
	 * Check if user is an Employee.
	 *
	 * @param WP_User|int $user User object or ID.
	 * @return bool
	 */
	public static function is_employee( $user = null ) {
		if ( ! $user ) {
			$user = wp_get_current_user();
		} elseif ( is_numeric( $user ) ) {
			$user = get_userdata( $user );
		}

		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		return in_array( self::ROLE_EMPLOYEE, (array) $user->roles, true );
	}
}
