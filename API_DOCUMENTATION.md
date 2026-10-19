# REST API Documentation – Verification System

Base URL: `/wp-json/vs/v1`

## Authentication Endpoints
- `POST /auth/login`: Authenticate via phone number and password.
- `POST /auth/send-otp`: Request 6-digit SMS OTP (requires real SMS provider filter hook).
- `POST /auth/verify-otp`: Verify short-lived OTP code.
- `POST /auth/logout`: End session.

## Employee Dashboard
- `GET /dashboard`: Fetch employee profile, shift schedules, check-in status, and 7-day history.

## Attendance Actions
- `POST /attendance/check-in`: Record attendance check-in with Wi-Fi token payload.
- `POST /attendance/check-out`: Record attendance check-out and calculate worked hours.

## Management Endpoints
- `GET /employees`: List branch employees (Branch Manager isolation applied).
- `POST /employees`: Create employee and WP user mapping.
- `POST /employees/import`: Bulk import employees via CSV.
- `GET /employees/export`: Export employee directory to CSV.
- `GET /branches`: List accessible branches.
- `POST /branches`: Create new branch.
- `GET /reports/attendance`: Retrieve attendance summary and record dataset.
- `GET /reports/export`: Download attendance CSV report.
- `GET /audit-logs`: Audit trail records.
