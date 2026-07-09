<?php
/**
 * License handling for the Pro tier.
 *
 * Options schema (wp_options key `lsp_license`):
 *   array{
 *     key: string,               // The license key user pasted
 *     status: string,             // active | invalid | expired | canceled | limit_reached | not_activated
 *     tier: ?string,              // "solo" | "agency"
 *     expires_at: ?string,        // ISO date string
 *     max_sites: ?int,
 *     activated_sites: string[],
 *     last_checked: ?int,         // UNIX timestamp of last remote check
 *     message: ?string
 *   }
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LSP_License {

	const OPTION_KEY = 'lsp_license';

	/**
	 * Read stored license state. Falls back to empty defaults.
	 *
	 * @return array
	 */
	public function get_state() {
		$defaults = array(
			'key'             => '',
			'status'          => 'inactive',
			'tier'            => null,
			'expires_at'      => null,
			'max_sites'       => null,
			'activated_sites' => array(),
			'last_checked'    => null,
			'message'         => null,
		);
		$state = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		return array_merge( $defaults, $state );
	}

	public function save_state( array $patch ) {
		$state = array_merge( $this->get_state(), $patch );
		update_option( self::OPTION_KEY, $state, false );
		return $state;
	}

	public function clear() {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * True if this install has an active Pro license.
	 */
	public function is_pro() {
		$s = $this->get_state();
		if ( 'active' !== $s['status'] ) return false;
		if ( ! empty( $s['expires_at'] ) ) {
			$expires_ts = strtotime( $s['expires_at'] );
			if ( $expires_ts && $expires_ts < time() ) {
				return false;
			}
		}
		return true;
	}

	public function tier() {
		$s = $this->get_state();
		return $s['tier'];
	}

	public function status_label() {
		$s = $this->get_state();
		$labels = array(
			'active'         => __( 'Active', 'localbusiness-schema-pro' ),
			'inactive'       => __( 'No license', 'localbusiness-schema-pro' ),
			'invalid'        => __( 'Invalid key', 'localbusiness-schema-pro' ),
			'expired'        => __( 'Expired', 'localbusiness-schema-pro' ),
			'canceled'       => __( 'Canceled', 'localbusiness-schema-pro' ),
			'limit_reached'  => __( 'Site limit reached', 'localbusiness-schema-pro' ),
			'not_activated'  => __( 'Not activated on this site', 'localbusiness-schema-pro' ),
		);
		return isset( $labels[ $s['status'] ] ) ? $labels[ $s['status'] ] : ucfirst( $s['status'] );
	}

	/**
	 * Activate a license key for this site.
	 *
	 * @param string $key
	 * @return array Updated state.
	 */
	public function activate( $key ) {
		$key      = strtoupper( trim( $key ) );
		$site_url = home_url( '/' );
		$response = $this->call_api( 'validate', array(
			'license_key' => $key,
			'plugin_slug' => LSP_SLUG,
			'site_url'    => $site_url,
			'action'      => 'activate',
		) );
		if ( is_wp_error( $response ) ) {
			return $this->save_state( array(
				'key'          => $key,
				'status'       => 'invalid',
				'message'      => $response->get_error_message(),
				'last_checked' => time(),
			) );
		}
		return $this->save_state( array(
			'key'             => $key,
			'status'          => $response['valid'] ? 'active' : ( isset( $response['status'] ) ? $response['status'] : 'invalid' ),
			'tier'            => isset( $response['tier'] ) ? $response['tier'] : null,
			'expires_at'      => isset( $response['expires_at'] ) ? $response['expires_at'] : null,
			'max_sites'       => isset( $response['max_sites'] ) ? (int) $response['max_sites'] : null,
			'activated_sites' => isset( $response['activated_sites'] ) ? array_values( (array) $response['activated_sites'] ) : array(),
			'message'         => isset( $response['message'] ) ? $response['message'] : null,
			'last_checked'    => time(),
		) );
	}

	/**
	 * Deactivate the site (remove from license's activated_sites) and clear local state.
	 */
	public function deactivate() {
		$state = $this->get_state();
		if ( ! empty( $state['key'] ) ) {
			$this->call_api( 'validate', array(
				'license_key' => $state['key'],
				'plugin_slug' => LSP_SLUG,
				'site_url'    => home_url( '/' ),
				'action'      => 'deactivate',
			) );
		}
		$this->clear();
	}

	/**
	 * Re-validate the current license against the server. Runs on cron.
	 */
	public function revalidate() {
		$state = $this->get_state();
		if ( empty( $state['key'] ) ) return;

		$response = $this->call_api( 'validate', array(
			'license_key' => $state['key'],
			'plugin_slug' => LSP_SLUG,
			'site_url'    => home_url( '/' ),
			'action'      => 'check',
		) );
		if ( is_wp_error( $response ) ) {
			// Network hiccup — leave state alone, we'll try next week.
			return;
		}
		$this->save_state( array(
			'status'          => $response['valid'] ? 'active' : ( isset( $response['status'] ) ? $response['status'] : 'invalid' ),
			'tier'            => isset( $response['tier'] ) ? $response['tier'] : $state['tier'],
			'expires_at'      => isset( $response['expires_at'] ) ? $response['expires_at'] : $state['expires_at'],
			'max_sites'       => isset( $response['max_sites'] ) ? (int) $response['max_sites'] : $state['max_sites'],
			'activated_sites' => isset( $response['activated_sites'] ) ? array_values( (array) $response['activated_sites'] ) : $state['activated_sites'],
			'message'         => isset( $response['message'] ) ? $response['message'] : null,
			'last_checked'    => time(),
		) );
	}

	/**
	 * POST to the kitmobley license API.
	 *
	 * @param string $action  Currently only "validate".
	 * @param array  $payload
	 * @return array|WP_Error
	 */
	private function call_api( $action, $payload ) {
		$endpoints = array(
			'validate' => '/plugin-license-validate',
		);
		if ( ! isset( $endpoints[ $action ] ) ) {
			return new WP_Error( 'sca_bad_action', 'Unknown license action' );
		}
		$url = LSP_LICENSE_API_BASE . $endpoints[ $action ];

		$res = wp_remote_post( $url, array(
			'timeout' => 15,
			'headers' => array( 'Content-Type' => 'application/json' ),
			'body'    => wp_json_encode( $payload ),
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'sca_bad_response', sprintf( __( 'License server returned an unexpected response (HTTP %d).', 'localbusiness-schema-pro' ), $code ) );
		}
		if ( $code >= 500 ) {
			return new WP_Error( 'sca_server_error', isset( $data['message'] ) ? $data['message'] : __( 'License server error. Try again later.', 'localbusiness-schema-pro' ) );
		}
		return $data;
	}
}
