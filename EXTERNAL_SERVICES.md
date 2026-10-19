# External Services & Integration Documentation – Verification System

## Optional External Gateway Integrations

1. **SMS Gateway Provider** (Optional for OTP Sign In)
   - *Supported Gateway*: Any standard HTTP/REST SMS Gateway (e.g., Twilio, Unifonic, Etisalat SMS API).
   - *Integration Hook*: `vs_send_sms_otp` filter in PHP.
   - *Note*: If no SMS provider is configured, password-based login is used. The plugin never pretends an SMS was sent without a real configured provider.

2. **Cisco Meraki Enterprise Wireless Controller API** (Optional for Meraki Wi-Fi Mode)
   - *Requirement*: Cisco Meraki Dashboard API Key and Endpoint URL.
   - *Note*: HMAC Signed Token mode requires no external API subscriptions and works with local network portals.
