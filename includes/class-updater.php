<?php
/**
 * Updater — hooks into WP's plugin update system, polls kitmobley.com
 * for the latest version manifest, and injects update info into the
 * standard WP admin update UI.
 *
 * Also handles the "View details" popup by responding to the
 * plugins_api filter with a synthesized info blob.
 *
 * Server-side truth lives at KITMOBLEY /api/plugin-updates. Response cached
 * as a transient for 12 hours to keep site pageload / admin traffic light.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LSP_Updater {

	const TRANSIENT_KEY = 'lsp_update_manifest';
	const TRANSIENT_TTL = 12 * HOUR_IN_SECONDS;

	public function register() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'normalize_source_folder' ), 10, 4 );
	}

	/**
	 * Inject our update entry into WP's update-check transient.
	 *
	 * @param object|false $transient
	 * @return object|false
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) $transient = new stdClass();
		if ( ! isset( $transient->response ) ) $transient->response = array();

		$manifest = $this->fetch_manifest();
		if ( ! $manifest ) return $transient;

		$current = LSP_VERSION;
		$latest  = isset( $manifest['version'] ) ? $manifest['version'] : $current;

		if ( version_compare( $latest, $current, '>' ) ) {
			$transient->response[ LSP_BASENAME ] = (object) array(
				'slug'        => LSP_SLUG,
				'plugin'      => LSP_BASENAME,
				'new_version' => $latest,
				'url'         => isset( $manifest['homepage'] ) ? $manifest['homepage'] : 'https://kitmobley.com/plugins/' . LSP_SLUG . '/',
				'package'     => isset( $manifest['package_url'] ) ? $manifest['package_url'] : '',
				'requires'    => isset( $manifest['requires'] ) ? $manifest['requires'] : '5.8',
				'tested'      => isset( $manifest['tested'] ) ? $manifest['tested'] : '6.7',
				'requires_php'=> isset( $manifest['requires_php'] ) ? $manifest['requires_php'] : '7.4',
			);
		} else {
			if ( ! isset( $transient->no_update ) ) $transient->no_update = array();
			$transient->no_update[ LSP_BASENAME ] = (object) array(
				'slug'        => LSP_SLUG,
				'plugin'      => LSP_BASENAME,
				'new_version' => $current,
				'url'         => isset( $manifest['homepage'] ) ? $manifest['homepage'] : '',
				'package'     => '',
				'requires'    => isset( $manifest['requires'] ) ? $manifest['requires'] : '5.8',
				'tested'      => isset( $manifest['tested'] ) ? $manifest['tested'] : '6.7',
				'requires_php'=> isset( $manifest['requires_php'] ) ? $manifest['requires_php'] : '7.4',
			);
		}
		return $transient;
	}

	/**
	 * Answer the "View details" popup so users don't get sent to wp.org.
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) return $result;
		if ( ! isset( $args->slug ) || LSP_SLUG !== $args->slug ) return $result;

		$manifest = $this->fetch_manifest();
		$latest   = $manifest && isset( $manifest['version'] ) ? $manifest['version'] : LSP_VERSION;

		return (object) array(
			'name'         => 'Schema Conflict Auditor',
			'slug'         => LSP_SLUG,
			'version'      => $latest,
			'author'       => '<a href="https://kitmobley.com">Kit Mobley</a>',
			'homepage'     => 'https://kitmobley.com/plugins/' . LSP_SLUG . '/',
			'requires'     => $manifest && isset( $manifest['requires'] ) ? $manifest['requires'] : '5.8',
			'tested'       => $manifest && isset( $manifest['tested'] ) ? $manifest['tested'] : '6.7',
			'requires_php' => $manifest && isset( $manifest['requires_php'] ) ? $manifest['requires_php'] : '7.4',
			'download_link'=> $manifest && isset( $manifest['package_url'] ) ? $manifest['package_url'] : '',
			'sections'     => array(
				'description' => 'Scans your WordPress site for duplicate and conflicting JSON-LD schema. Pro adds an auto-merger and priority support. See kitmobley.com/plugins/localbusiness-schema-pro/ for full details.',
			),
		);
	}

	/**
	 * Fetch (with transient cache) the update manifest from kitmobley.
	 *
	 * @return array|null
	 */
	public function fetch_manifest() {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( is_array( $cached ) ) return $cached;

		$license  = ( new LSP_License() )->get_state();
		$args     = array(
			'slug'        => LSP_SLUG,
			'version'     => LSP_VERSION,
			'site_url'    => home_url( '/' ),
			'license_key' => ! empty( $license['key'] ) ? $license['key'] : '',
		);
		$url = LSP_LICENSE_API_BASE . '/plugin-updates?' . http_build_query( $args );

		$res = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $res ) ) return null;
		if ( 200 !== wp_remote_retrieve_response_code( $res ) ) return null;

		$body = wp_remote_retrieve_body( $res );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) return null;

		set_transient( self::TRANSIENT_KEY, $data, self::TRANSIENT_TTL );
		return $data;
	}

	public function bust_cache() {
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Ensure the extracted update ZIP's folder name matches our plugin slug.
	 * If Kit hosts the ZIP with a versioned filename (localbusiness-schema-pro-1.2.0.zip),
	 * WP may extract to a suffix'd folder and fail to overwrite the old install.
	 */
	public function normalize_source_folder( $source, $remote_source, $upgrader, $extra = null ) {
		if ( ! is_string( $source ) ) return $source;
		$expected = trailingslashit( $remote_source ) . LSP_SLUG;
		if ( trailingslashit( $source ) === trailingslashit( $expected ) ) return $source;
		// Only rename if we're clearly the source (folder starts with our slug).
		$basename = basename( untrailingslashit( $source ) );
		if ( 0 !== strpos( $basename, LSP_SLUG ) ) return $source;
		if ( @rename( untrailingslashit( $source ), $expected ) ) {
			return trailingslashit( $expected );
		}
		return $source;
	}
}
