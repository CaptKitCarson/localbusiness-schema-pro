<?php
/**
 * Plugin Name:       LocalBusiness Schema
 * Plugin URI:        https://kitmobley.com/plugins/localbusiness-schema-pro/
 * Description:       LocalBusiness JSON-LD schema for service-area operators. Multi-location, trip and service catalog, seasonal availability. Where Rank Math and Yoast end, this begins.
 * Version:           1.0.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Kit Mobley
 * Author URI:        https://kitmobley.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       localbusiness-schema
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LSP_VERSION', '1.0.1' );
define( 'LSP_SLUG', 'localbusiness-schema' );
define( 'LSP_FILE', __FILE__ );
define( 'LSP_DIR', plugin_dir_path( __FILE__ ) );
define( 'LSP_URL', plugin_dir_url( __FILE__ ) );
define( 'LSP_BASENAME', plugin_basename( __FILE__ ) );

define( 'LSP_CPT_LOCATION', 'lsp_location' );
define( 'LSP_CPT_SERVICE',  'lsp_service' );

// The marketing page slug is deliberately independent of the plugin slug. The
// plugin is "localbusiness-schema" for WordPress.org; the sales page predates
// that rename and still lives at /plugins/localbusiness-schema-pro/. Deriving
// this from LSP_SLUG would 404 every upgrade link.
if ( ! defined( 'LSP_PRO_URL' ) ) {
	define( 'LSP_PRO_URL', 'https://kitmobley.com/plugins/localbusiness-schema-pro/#pricing' );
}

if ( ! defined( 'LSP_LICENSE_API_BASE' ) ) {
	define( 'LSP_LICENSE_API_BASE', 'https://kitmobley.com/api' );
}

// Shared kitmobley/wp-plugin-core library (bundled at build time).
require_once LSP_DIR . 'includes/vendor/kitmobley-core/src/Config.php';
require_once LSP_DIR . 'includes/vendor/kitmobley-core/src/License.php';
require_once LSP_DIR . 'includes/vendor/kitmobley-core/src/Updater.php';
require_once LSP_DIR . 'includes/vendor/kitmobley-core/src/LicenseUI.php';

require_once LSP_DIR . 'includes/class-location.php';
require_once LSP_DIR . 'includes/class-service.php';
require_once LSP_DIR . 'includes/class-schema.php';
require_once LSP_DIR . 'includes/class-detector.php';
require_once LSP_DIR . 'includes/class-license.php';
require_once LSP_DIR . 'includes/class-updater.php';
require_once LSP_DIR . 'includes/class-admin.php';

function lsp_plugin_config() {
	static $cfg = null;
	if ( null === $cfg ) {
		$cfg = new \KitMobley\PluginCore\Config( array(
			'slug'          => LSP_SLUG,
			'version'       => LSP_VERSION,
			'basename'      => LSP_BASENAME,
			'text_domain'   => 'localbusiness-schema',
			'api_base'      => LSP_LICENSE_API_BASE,
			'option_key'    => 'lsp_license',
			'transient_key' => 'lsp_update_manifest',
			'ajax_prefix'   => 'lsp',
			'pro_url'       => LSP_PRO_URL,
		) );
	}
	return $cfg;
}

register_activation_hook( __FILE__, function () {
	if ( false === get_option( 'lsp_settings' ) ) {
		add_option( 'lsp_settings', array(
			'primary_location_id' => 0,
			'inject_on_home'      => 1,
			'inject_on_singular'  => 1,
			'coexistence_mode'    => 'alongside', // 'alongside' | 'defer_if_present'
		) );
	}
	if ( ! wp_next_scheduled( 'lsp_license_revalidate' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', 'lsp_license_revalidate' );
	}
	// Ensure rewrite rules for CPTs.
	( new LSP_Location() )->register_cpt();
	( new LSP_Service() )->register_cpt();
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	$ts = wp_next_scheduled( 'lsp_license_revalidate' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'lsp_license_revalidate' );
	}
	flush_rewrite_rules();
} );

add_action( 'lsp_license_revalidate', function () {
	( new LSP_License() )->revalidate();
} );

add_action( 'init', function () {
	( new LSP_Location() )->register_cpt();
	( new LSP_Service() )->register_cpt();
	( new LSP_Updater() )->register();
} );

add_action( 'add_meta_boxes', function () {
	( new LSP_Location() )->register_metabox();
	( new LSP_Service() )->register_metabox();
} );

add_action( 'save_post_' . LSP_CPT_LOCATION, array( 'LSP_Location', 'save_meta' ), 10, 2 );
add_action( 'save_post_' . LSP_CPT_SERVICE,  array( 'LSP_Service',  'save_meta' ), 10, 2 );

add_action( 'wp_head', function () {
	( new LSP_Schema() )->maybe_inject();
}, 5 );

add_action( 'init', function () {
	if ( is_admin() ) {
		new LSP_Admin();
	}
} );

// The free-tier Location cap is a licensing limit, not an admin-screen nicety,
// so it registers for every request. LSP_Admin is only instantiated under
// is_admin(); registering this there meant REST and WP-CLI publishes skipped it.
add_filter( 'wp_insert_post_data', array( 'LSP_Admin', 'enforce_free_cap' ), 10, 2 );

add_filter( 'plugin_action_links_' . LSP_BASENAME, function ( $links ) {
	$link = '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . LSP_CPT_LOCATION ) ) . '">' . esc_html__( 'Locations', 'localbusiness-schema' ) . '</a>';
	array_unshift( $links, $link );
	return $links;
} );
