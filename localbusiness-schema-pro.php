<?php
/**
 * Plugin Name:       LocalBusiness Schema Pro
 * Plugin URI:        https://kitmobley.com/plugins/localbusiness-schema-pro/
 * Description:       LocalBusiness JSON-LD schema for service-area operators. Multi-location, trip and service catalog, seasonal availability — where Rank Math and Yoast end, this begins.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Kit Mobley
 * Author URI:        https://kitmobley.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       localbusiness-schema-pro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LSP_VERSION', '1.0.0' );
define( 'LSP_SLUG', 'localbusiness-schema-pro' );
define( 'LSP_FILE', __FILE__ );
define( 'LSP_DIR', plugin_dir_path( __FILE__ ) );
define( 'LSP_URL', plugin_dir_url( __FILE__ ) );
define( 'LSP_BASENAME', plugin_basename( __FILE__ ) );

define( 'LSP_CPT_LOCATION', 'lsp_location' );
define( 'LSP_CPT_SERVICE',  'lsp_service' );

if ( ! defined( 'LSP_LICENSE_API_BASE' ) ) {
	define( 'LSP_LICENSE_API_BASE', 'https://kitmobley.com/api' );
}

require_once LSP_DIR . 'includes/class-location.php';
require_once LSP_DIR . 'includes/class-service.php';
require_once LSP_DIR . 'includes/class-schema.php';
require_once LSP_DIR . 'includes/class-detector.php';
require_once LSP_DIR . 'includes/class-license.php';
require_once LSP_DIR . 'includes/class-updater.php';
require_once LSP_DIR . 'includes/class-admin.php';

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

add_filter( 'plugin_action_links_' . LSP_BASENAME, function ( $links ) {
	$link = '<a href="' . esc_url( admin_url( 'edit.php?post_type=' . LSP_CPT_LOCATION ) ) . '">' . esc_html__( 'Locations', 'localbusiness-schema-pro' ) . '</a>';
	array_unshift( $links, $link );
	return $links;
} );
