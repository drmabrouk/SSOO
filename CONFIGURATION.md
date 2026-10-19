# Configuration Guide – Verification System

## Default System Settings
- Timezone: `Asia/Dubai`
- Default Country Code: `+971` (UAE)
- Default Currency: `AED`
- Interface Language: English

## Initial Setup Workflow
1. **Branch Configuration**: Create organizational branches (e.g., Dubai Main Branch, Abu Dhabi Campus).
2. **Wi-Fi Network Setup**:
   - Set up the Wi-Fi authentication mode (`hmac_token` or `meraki`).
   - Define the approved Workplace SSID and Shared Secret Key.
3. **Employee Management**:
   - Add employees manually or import in bulk using CSV format (`Employee ID, Full Name, Phone Number, Email, Department, Job Title, Branch ID`).
4. **Schedules & Policies**:
   - Define shift start/end times, grace period (default 15 mins), and late thresholds.
