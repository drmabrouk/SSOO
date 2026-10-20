# Database Schema Documentation – Verification System

The plugin creates 11 custom database tables prefixed with `$wpdb->prefix`:

1. `vs_branches`: Stores branch details, codes, contact info, manager ID, and active status.
2. `vs_employees`: Stores employee profiles, unique employee codes, normalized phone numbers, WP user mapping, and roles (`vs_employee`, `vs_branch_manager`, `vs_general_manager`).
3. `vs_schedules`: Configures shift start/end times, grace periods, late thresholds, break durations, and overtime rates.
4. `vs_attendance_sessions`: Open and completed attendance sessions with Check In / Check Out timestamps, lateness, and worked hours.
5. `vs_attendance_events`: Event log of check-in/out events including BSSID, client IP, and verification method.
6. `vs_wifi_configs`: Branch Wi-Fi settings, SSIDs, multi-AP BSSID lists, shared secrets, Cisco Meraki API endpoints, and `last_verification` timestamps.
7. `vs_wifi_logs`: Audit history of Wi-Fi verification successes and failures with failure reasons.
8. `vs_audit_logs`: Detailed operational and security audit log with redacted password/token parameters.
9. `vs_leave_requests`: Employee leave applications and manager approval workflow.
10. `vs_corrections`: Attendance correction requests submitted by employees.
11. `vs_otps`: Hashed short-lived SMS OTP codes with attempt counters and expiry dates.
