# Wi-Fi Network Integration Architecture – Verification System

## Architecture Overview
Verification System requires server-side validation of connected workplace networks. Browser-supplied SSIDs or client-side JavaScript results are rejected as insufficient proof of presence.

## Supported Verification Modes

### 1. HMAC Signed Token Gateway (`hmac_token`)
- Network captive portals, RADIUS access points, or router microservices generate an HMAC token using `SHA-256`.
- **Payload Signature Formula**: `hash_hmac('sha256', employee_id + '|' + ssid + '|' + bssid + '|' + timestamp, shared_secret)`
- Supports multi-AP BSSID validation lists.
- **Expiration**: Tokens are valid for 180 seconds to prevent replay attacks.

### 2. Cisco Meraki Controller API Integration (`meraki`)
- Integrates directly with Cisco Meraki Enterprise Wireless Controllers via REST API.
- Query endpoint: `/clients/{client_ip}` with header `X-Cisco-Meraki-API-Key`.
- Verifies that the client IP is associated with the registered workplace SSID.

### 3. Fail-Closed Default Policy & Diagnostics
If network verification fails or is unavailable, attendance check-in is rejected by default. Authorized administrators can run diagnostics via `/wifi/test` to test controller connectivity.
