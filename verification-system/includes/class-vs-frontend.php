<?php
/**
 * Frontend Controller & View Renderer
 *
 * @package VerificationSystem
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VS_Frontend {

	/**
	 * Initialize shortcode, asset enqueue, and theme isolation hooks.
	 */
	public static function init() {
		add_shortcode( 'verification_system', array( __CLASS__, 'render_app' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'template_include', array( __CLASS__, 'isolate_app_template' ), 99 );
	}

	/**
	 * Intercept page rendering for the dedicated application page to isolate it from theme layout.
	 *
	 * @param string $template Standard template path.
	 * @return string Isolated template path or original template.
	 */
	public static function isolate_app_template( $template ) {
		if ( is_admin() ) {
			return $template;
		}

		$app_page_id = get_option( 'vs_app_page_id', 0 );
		$current_id  = get_queried_object_id();

		if ( ( $app_page_id && (int) $current_id === (int) $app_page_id ) || ( is_page() && has_shortcode( get_post_field( 'post_content', $current_id ), 'verification_system' ) ) ) {
			// Render clean HTML canvas isolated from theme headers, footers, sidebars
			self::render_canvas_page();
			exit;
		}

		return $template;
	}

	/**
	 * Render clean standalone canvas HTML page for the independent application.
	 */
	private static function render_canvas_page() {
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
			<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> – Verification System</title>
			<?php wp_head(); ?>
			<style>
				html, body {
					margin: 0;
					padding: 0;
					width: 100%;
					height: 100%;
					background-color: #f7f7f7;
					overflow-x: hidden;
				}
				#wpadminbar { display: none !important; }
				html { margin-top: 0 !important; }
			</style>
		</head>
		<body class="vs-standalone-canvas">
			<?php echo do_shortcode( '[verification_system]' ); ?>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
	}

	/**
	 * Enqueue application stylesheets and scripts.
	 */
	public static function enqueue_assets() {
		$app_page_id = get_option( 'vs_app_page_id', 0 );
		$current_id  = get_queried_object_id();

		if ( ( $app_page_id && (int) $current_id === (int) $app_page_id ) || ( is_page() && has_shortcode( get_post_field( 'post_content', $current_id ), 'verification_system' ) ) ) {
			wp_enqueue_style(
				'vs-app-css',
				VS_PLUGIN_URL . 'assets/css/app.css',
				array(),
				VS_VERSION
			);

			wp_enqueue_script(
				'vs-app-js',
				VS_PLUGIN_URL . 'assets/js/app.js',
				array(),
				VS_VERSION,
				true
			);

			$employee = VS_Auth::get_current_employee();

			wp_localize_script( 'vs-app-js', 'VS_CONFIG', array(
				'api_root'     => esc_url_raw( rest_url( 'vs/v1' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'is_logged_in' => is_user_logged_in(),
				'user'         => is_user_logged_in() ? array(
					'id'            => get_current_user_id(),
					'name'          => wp_get_current_user()->display_name,
					'role'          => $employee ? $employee->app_role : ( current_user_can( 'administrator' ) ? 'vs_system_admin' : 'vs_employee' ),
					'employee_code' => $employee ? $employee->employee_code : '',
					'branch_id'     => $employee ? $employee->branch_id : 0,
				) : null,
			) );
		}
	}

	/**
	 * Render application root container markup.
	 *
	 * @return string HTML shortcode output.
	 */
	public static function render_app() {
		ob_start();
		?>
		<div id="vs-app-container" class="vs-app-root">
			<!-- Application Loading Spinner -->
			<div id="vs-app-loader" class="vs-loader-screen">
				<div class="vs-spinner"></div>
				<p>Loading Verification System...</p>
			</div>

			<!-- Dynamic SPA Mount Point -->
			<div id="vs-app-viewport" class="vs-viewport hidden"></div>
		</div>
		<?php
		return ob_get_clean();
	}
}
