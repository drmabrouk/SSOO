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
	 * Initialize shortcode and asset enqueue hooks.
	 */
	public static function init() {
		add_shortcode( 'verification_system', array( __CLASS__, 'render_app' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue application stylesheets and scripts.
	 */
	public static function enqueue_assets() {
		global $post;

		$app_page_id = get_option( 'vs_app_page_id', 0 );

		if ( ( $post && $post->ID === (int) $app_page_id ) || ( $post && has_shortcode( $post->post_content, 'verification_system' ) ) ) {
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
