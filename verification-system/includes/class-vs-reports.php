<?php
/**
 * Reporting Engine & Analytics
 *
 * @package VerificationSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Reports {

	/**
	 * Get attendance summary report dataset based on filters and role access.
	 *
	 * @param array $filters Query parameters (start_date, end_date, branch_id, employee_id).
	 * @return array Summary metrics and records.
	 */
	public static function get_attendance_report( $filters = array() ) {
		global $wpdb;

		$session_table  = $wpdb->prefix . 'vs_attendance_sessions';
		$emp_table      = $wpdb->prefix . 'vs_employees';
		$branch_table   = $wpdb->prefix . 'vs_branches';

		$user       = wp_get_current_user();
		$start_date = isset( $filters['start_date'] ) ? sanitize_text_field( $filters['start_date'] ) : date( 'Y-m-01' );
		$end_date   = isset( $filters['end_date'] ) ? sanitize_text_field( $filters['end_date'] ) : date( 'Y-m-d' );

		$where = $wpdb->prepare( "WHERE s.date BETWEEN %s AND %s", $start_date, $end_date );

		// Enforce Role Data Isolation
		if ( VS_Roles::is_branch_manager( $user ) ) {
			$emp   = VS_Auth::get_current_employee();
			$b_id  = $emp ? $emp->branch_id : 0;
			$where .= $wpdb->prepare( " AND s.branch_id = %d", $b_id );
		} elseif ( VS_Roles::is_employee( $user ) ) {
			$emp   = VS_Auth::get_current_employee();
			$e_id  = $emp ? $emp->id : 0;
			$where .= $wpdb->prepare( " AND s.employee_id = %d", $e_id );
		} elseif ( ! empty( $filters['branch_id'] ) && VS_Branch::can_manage_branch( $filters['branch_id'] ) ) {
			$where .= $wpdb->prepare( " AND s.branch_id = %d", absint( $filters['branch_id'] ) );
		}

		if ( ! empty( $filters['employee_id'] ) ) {
			$where .= $wpdb->prepare( " AND s.employee_id = %d", absint( $filters['employee_id'] ) );
		}

		$sql = "SELECT s.*, e.employee_code, e.full_name, e.department, b.name as branch_name
			FROM {$session_table} s
			LEFT JOIN {$emp_table} e ON s.employee_id = e.id
			LEFT JOIN {$branch_table} b ON s.branch_id = b.id
			{$where}
			ORDER BY s.date DESC, s.check_in_time DESC";

		$records = $wpdb->get_results( $sql );

		// Calculate Aggregate Metrics
		$total_sessions       = count( $records );
		$total_late           = 0;
		$total_early          = 0;
		$total_worked_minutes = 0;
		$total_overtime_mins  = 0;

		foreach ( $records as $rec ) {
			if ( $rec->is_late ) {
				$total_late++;
			}
			if ( $rec->is_early_departure ) {
				$total_early++;
			}
			$total_worked_minutes += $rec->total_worked_minutes;
			$total_overtime_mins  += $rec->overtime_minutes;
		}

		return array(
			'metrics' => array(
				'total_sessions'       => $total_sessions,
				'total_late'           => $total_late,
				'total_early'          => $total_early,
				'total_worked_hours'   => round( $total_worked_minutes / 60, 2 ),
				'total_overtime_hours' => round( $total_overtime_mins / 60, 2 ),
			),
			'records' => $records,
		);
	}

	/**
	 * Export attendance report to CSV format string.
	 *
	 * @param array $filters Query parameters.
	 * @return string CSV contents.
	 */
	public static function export_attendance_csv( $filters = array() ) {
		$data    = self::get_attendance_report( $filters );
		$records = $data['records'];

		$output = fopen( 'php://temp', 'r+' );
		fputcsv( $output, array( 'Date', 'Employee ID', 'Full Name', 'Branch', 'Department', 'Status', 'Check In', 'Check Out', 'Late (Mins)', 'Early Dep. (Mins)', 'Worked (Hours)', 'Overtime (Mins)' ) );

		foreach ( $records as $rec ) {
			fputcsv( $output, array(
				$rec->date,
				$rec->employee_code,
				$rec->full_name,
				$rec->branch_name,
				$rec->department,
				strtoupper( $rec->status ),
				$rec->check_in_time ? $rec->check_in_time : 'N/A',
				$rec->check_out_time ? $rec->check_out_time : 'N/A',
				$rec->lateness_minutes,
				$rec->early_departure_minutes,
				round( $rec->total_worked_minutes / 60, 2 ),
				$rec->overtime_minutes,
			) );
		}

		rewind( $output );
		$csv_data = stream_get_contents( $output );
		fclose( $output );

		return $csv_data;
	}

	/**
	 * Generate clean printable HTML report layout for conversion or browser printing.
	 *
	 * @param array $filters Query parameters.
	 * @return string HTML report markup.
	 */
	public static function render_printable_report( $filters = array() ) {
		$data       = self::get_attendance_report( $filters );
		$metrics    = $data['metrics'];
		$records    = $data['records'];
		$start_date = isset( $filters['start_date'] ) ? $filters['start_date'] : date( 'Y-m-01' );
		$end_date   = isset( $filters['end_date'] ) ? $filters['end_date'] : date( 'Y-m-d' );

		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="utf-8">
			<title>Attendance Report (<?php echo esc_html( $start_date . ' to ' . $end_date ); ?>)</title>
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; color: #111; margin: 20px; font-size: 13px; }
				.header { border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 20px; }
				.header h1 { margin: 0 0 6px 0; font-size: 20px; text-transform: uppercase; letter-spacing: 0.5px; }
				.header p { margin: 0; color: #737373; font-size: 12px; }
				.metrics-grid { display: flex; gap: 16px; margin-bottom: 24px; }
				.metric-card { flex: 1; background: #f7f7f7; border: 1px solid #e5e5e5; border-radius: 8px; padding: 12px 16px; }
				.metric-card .val { font-size: 22px; font-weight: 700; margin-top: 4px; color: #111; }
				.metric-card .lbl { font-size: 11px; text-transform: uppercase; color: #737373; letter-spacing: 0.5px; }
				table { width: 100%; border-collapse: collapse; margin-top: 10px; }
				th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e5e5e5; }
				th { background: #f7f7f7; font-weight: 600; color: #333; font-size: 11px; text-transform: uppercase; }
				.badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; background: #e5e5e5; }
			</style>
		</head>
		<body>
			<div class="header">
				<h1>Verification System – Attendance Report</h1>
				<p>Date Range: <?php echo esc_html( $start_date ); ?> – <?php echo esc_html( $end_date ); ?> | Timezone: Asia/Dubai</p>
			</div>

			<div class="metrics-grid">
				<div class="metric-card">
					<div class="lbl">Total Sessions</div>
					<div class="val"><?php echo esc_html( $metrics['total_sessions'] ); ?></div>
				</div>
				<div class="metric-card">
					<div class="lbl">Late Arrivals</div>
					<div class="val"><?php echo esc_html( $metrics['total_late'] ); ?></div>
				</div>
				<div class="metric-card">
					<div class="lbl">Early Departures</div>
					<div class="val"><?php echo esc_html( $metrics['total_early'] ); ?></div>
				</div>
				<div class="metric-card">
					<div class="lbl">Total Worked Hours</div>
					<div class="val"><?php echo esc_html( $metrics['total_worked_hours'] ); ?>h</div>
				</div>
			</div>

			<table>
				<thead>
					<tr>
						<th>Date</th>
						<th>Employee</th>
						<th>Branch</th>
						<th>Check In</th>
						<th>Check Out</th>
						<th>Status</th>
						<th>Worked (Hrs)</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $records as $row ) : ?>
						<tr>
							<td><?php echo esc_html( $row->date ); ?></td>
							<td><strong><?php echo esc_html( $row->full_name ); ?></strong> (<?php echo esc_html( $row->employee_code ); ?>)</td>
							<td><?php echo esc_html( $row->branch_name ); ?></td>
							<td><?php echo esc_html( $row->check_in_time ? date( 'H:i', strtotime( $row->check_in_time ) ) : '-' ); ?></td>
							<td><?php echo esc_html( $row->check_out_time ? date( 'H:i', strtotime( $row->check_out_time ) ) : '-' ); ?></td>
							<td><span class="badge"><?php echo esc_html( strtoupper( $row->status ) ); ?></span></td>
							<td><?php echo esc_html( round( $row->total_worked_minutes / 60, 2 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
}
