<?php
/**
 * Detector — best-effort check for existing SEO plugin LocalBusiness emission.
 * Only used when coexistence_mode = "defer_if_present".
 *
 * We can't inspect the OTHER plugin's rendered JSON-LD at wp_head-priority-5,
 * so this is heuristic: if Yoast Local SEO, Rank Math's Local Business module,
 * or AIOSEO's Local Business feature is active AND configured to emit, we
 * assume they'll emit and step aside.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LSP_Detector {

	public function incumbent_emits_localbusiness() {
		return $this->yoast_local_active()
			|| $this->rankmath_local_active()
			|| $this->aioseo_local_active();
	}

	private function yoast_local_active() {
		// Yoast Local SEO is a separate premium plugin: wpseo-local.
		if ( is_plugin_active( 'wpseo-local/local-seo.php' ) ) return true;
		if ( defined( 'WPSEO_LOCAL_VERSION' ) ) return true;
		return false;
	}

	private function rankmath_local_active() {
		// Rank Math ships Local Business as an in-plugin module; check if
		// it's turned on via their internal helper.
		if ( class_exists( 'RankMath\Helper' ) && method_exists( 'RankMath\Helper', 'is_module_active' ) ) {
			try {
				return (bool) call_user_func( array( 'RankMath\Helper', 'is_module_active' ), 'local-seo' );
			} catch ( \Throwable $e ) {
				return false;
			}
		}
		return false;
	}

	private function aioseo_local_active() {
		// AIOSEO Local Business is a Pro-tier addon; the constant appears when active.
		return defined( 'AIOSEO_LOCAL_BUSINESS_VERSION' );
	}

	private function is_plugin_active_shim( $slug ) {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return is_plugin_active( $slug );
	}
}

/**
 * Guard for is_plugin_active in themes / early boot — WP loads it only in admin.
 */
if ( ! function_exists( 'is_plugin_active' ) ) {
	function is_plugin_active( $plugin ) {
		if ( function_exists( '\is_plugin_active' ) ) {
			return \is_plugin_active( $plugin );
		}
		if ( file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
			return \is_plugin_active( $plugin );
		}
		return in_array( $plugin, (array) get_option( 'active_plugins', array() ), true );
	}
}
