# REST API Documentation – Verification System

Base URL: `/wp-json/vs/v1`

## Authentication Endpoints
- `POST /auth/login`: Authenticate via phone number and password.
- `POST /auth/send-otp`: Request 6-digit SMS OTP.
- `POST /auth/verify-otp`: Verify short-lived OTP code.
- `POST /auth/logout`: End session and invalidate auth cookies.

## Employee Dashboard
- `GET /dashboard`: Fetch employee profile, shift schedules, check-in status, and 7-day history.

## Attendance Actions
- `POST /attendance/check-in`: Record attendance check-in with Wi-Fi token payload and animated progress sequence.
- `POST /attendance/check-out`: Record attendance check-out and calculate worked hours.

## Management Endpoints
- `GET /employees`: List branch employees (Branch Manager / GM isolation applied).
- `POST /employees`: Create employee or branch manager account and WP user mapping.
- `POST /employees/{id}`: Update employee account.
- `POST /employees/{id}/reset-password`: Reset password for employee or branch manager.
- `POST /employees/import`: Bulk import employees via CSV.
- `GET /employees/export`: Export employee directory to CSV.
- `GET /branches`: List accessible branches.
- `POST /branches`: Create new branch.
- `GET /reports/attendance`: Retrieve attendance summary and record dataset.
- `GET /reports/export`: Download attendance CSV report.
- `GET /wifi/config`: Retrieve branch Wi-Fi settings.
- `POST /wifi/config`: Save branch Wi-Fi settings.
- `POST /wifi/test`: Test network controller connectivity diagnostics.
- `GET /audit-logs`: Audit trail records.
