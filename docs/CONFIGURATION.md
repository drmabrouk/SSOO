# Configuration Guide – Verification System

## Default System Settings
- Timezone: `Asia/Dubai`
- Default Country Code: `+971` (UAE)
- Default Currency: `AED`
- Interface Language: English

## Initial Setup Workflow
1. **Application Page**: Published automatically at `/verification-system`. Displays the SPA canvas with a floating panel left sidebar.
2. **Branch Configuration**: Create organizational branches and assign Branch Managers.
3. **Branch-Specific Wi-Fi Network Setup**:
   - Configure SSID, multi-AP BSSID lists, shared secrets, or Cisco Meraki API parameters for each branch.
   - Run **Test Controller Connection** to verify network controller diagnostics.
4. **General Manager & Account Management**:
   - General Managers and System Admins can create and edit Branch Managers and Employees, reset passwords, and suspend accounts.
5. **Schedules & Policies**:
   - Define shift start/end times, grace period (default 15 mins), and late thresholds.
