# Installation Guide – Verification System

## Requirements
- WordPress 6.0 or higher
- PHP 8.2 or PHP 8.4
- MySQL 5.7+ or MariaDB 10.3+

## Installation Steps
1. Log in to your WordPress Administration Panel.
2. Navigate to **Plugins > Add New**.
3. Click **Upload Plugin** at the top of the page.
4. Select `verification-system.zip` from your computer and click **Install Now**.
5. Once uploaded, click **Activate Plugin**.
6. On activation:
   - Plugin database tables with `$wpdb->prefix` are created automatically.
   - The default roles (`vs_general_manager`, `vs_branch_manager`, `vs_employee`) are registered.
   - An independent application page titled **Verification System App** containing shortcode `[verification_system]` is automatically published at `/verification-system`.
