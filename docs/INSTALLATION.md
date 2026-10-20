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
   - Database tables with `$wpdb->prefix` are created or upgraded automatically.
   - Default roles (`vs_general_manager`, `vs_branch_manager`, `vs_employee`) are registered.
   - An independent application page titled **Verification System App** containing shortcode `[verification_system]` is automatically published or restored at `/verification-system`.
   - The application page uses a standalone theme-isolation canvas template that removes theme headers, footers, and sidebars.
