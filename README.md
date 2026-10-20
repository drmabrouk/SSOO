# Verification System – Smart Attendance & Workforce Management

**Verification System** is a production-ready, standalone WordPress plugin that provides employee attendance, departure tracking, and workforce management using mobile-friendly authentication and verified workplace Wi-Fi connectivity.

## Project Structure

```text
SSOO/
├── verification-system/        # Production WordPress Plugin
│   ├── verification-system.php # Plugin Main File
│   ├── uninstall.php           # Safe Plugin Uninstallation Script
│   ├── includes/               # PHP Core Classes
│   └── assets/                 # CSS & JavaScript Assets
├── docs/                       # Technical Documentation
│   ├── API_DOCUMENTATION.md
│   ├── CONFIGURATION.md
│   ├── DATABASE_SCHEMA.md
│   ├── EXTERNAL_SERVICES.md
│   ├── INSTALLATION.md
│   ├── ROLES_PERMISSIONS.md
│   ├── TEST_REPORT.md
│   └── WIFI_INTEGRATION.md
├── tests/                      # CLI Automated Test Suite
│   └── run-tests.php
├── verification-system.zip     # Installable Plugin ZIP Deliverable
└── README.md
```

## Quick Installation
1. Download `verification-system.zip` from the repository root.
2. In WordPress Admin, navigate to **Plugins > Add New > Upload Plugin**.
3. Upload `verification-system.zip` and click **Activate**.
4. The plugin automatically creates and publishes the independent application page at `/verification-system`.

## Features
- **4 Distinct Application Roles**: System Administrator, General Manager, Branch Manager, and Employee.
- **WP Admin Isolation**: Non-administrators are redirected from `/wp-admin` to the single-page application canvas.
- **Mobile UAE Login**: Authentication via normalized UAE mobile numbers (`+971XXXXXXXXX`) and hashed SMS OTP/passwords.
- **Fail-Closed Wi-Fi Verification Engine**: Verifies connection against approved branch SSIDs/BSSIDs or Cisco Meraki API.
- **Asia/Dubai Timezone Clock**: Server timestamps in `Asia/Dubai` calculate shift lateness, overtime, and early departures.
- **Security Audit Logs**: Comprehensive action logs with automatic password and API key redaction.
