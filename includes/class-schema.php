<?php
/**
 * Schema generator — builds LocalBusiness JSON-LD from Location meta.
 *
 * On Pro tier, attaches hasOfferCatalog with each Location's Services.
 *
 * Injection logic (wp_head):
 *   - Homepage: primary Location (from settings)
 *   - Singular of any post_type: per-post override meta `_lsp_location_id` if set
 *   - Singular of Location CPT: that Location itself
 *   - Everything else: skip
 *
 * If coexistence_mode is "defer_if_present" and the detector finds Yoast/
 * Rank Math LocalBusiness already emitted, we skip. Otherwise emit alongside
 * with our own @id namespace so the two graphs don't collide.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LSP_Schema {

	public function maybe_inject() {
		$settings = wp_parse_args( get_option( 'lsp_settings', array() ), array(
			'primary_location_id' => 0,
			'inject_on_home'      => 1,
			'inject_on_singular'  => 1,
			'coexistence_mode'    => 'alongside',
		) );

		$location_id = $this->resolve_context_location( $settings );
		if ( ! $location_id ) return;

		// Coexistence check (deferred: needs upstream buffer to see other plugin
		// output; for now we run at low priority (wp_head, prio 5) so we emit
		// before other SEO plugins and rely on our unique @id.)
		if ( 'defer_if_present' === $settings['coexistence_mode'] ) {
			// Best-effort: check whether Yoast or Rank Math is active AND set to
			// emit LocalBusiness. We can't inspect their output at this point in
			// the request lifecycle, so we defer if either plugin is loaded.
			if ( ( new LSP_Detector() )->incumbent_emits_localbusiness() ) {
				return;
			}
		}

		$payload = $this->build_for_location( $location_id );
		if ( empty( $payload ) ) return;

		echo "\n<!-- LocalBusiness Schema Pro -->\n";
		echo '<script type="application/ld+json">' . wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	}

	private function resolve_context_location( $settings ) {
		if ( is_front_page() || is_home() ) {
			return ! empty( $settings['inject_on_home'] ) ? (int) $settings['primary_location_id'] : 0;
		}
		if ( is_singular( LSP_CPT_LOCATION ) ) {
			return (int) get_queried_object_id();
		}
		if ( is_singular() && ! empty( $settings['inject_on_singular'] ) ) {
			$override = (int) get_post_meta( get_queried_object_id(), '_lsp_location_id', true );
			if ( $override ) return $override;
			return (int) $settings['primary_location_id'];
		}
		return 0;
	}

	/**
	 * Build the JSON-LD payload for one Location.
	 * Returns null-equivalent (empty array) if the Location is invalid.
	 *
	 * @param int $location_id
	 * @return array
	 */
	public function build_for_location( $location_id ) {
		$post = get_post( $location_id );
		if ( ! $post || LSP_CPT_LOCATION !== $post->post_type || 'publish' !== $post->post_status ) {
			return array();
		}
		$meta = LSP_Location::get_meta( $location_id );

		$type = ! empty( $meta['business_type'] ) ? $meta['business_type'] : 'LocalBusiness';
		$id   = home_url( '/#lsp-location-' . $location_id );

		$node = array(
			'@context' => 'https://schema.org',
			'@type'    => $type,
			'@id'      => $id,
			'name'     => get_the_title( $location_id ),
		);

		$url = ! empty( $meta['url'] ) ? $meta['url'] : home_url( '/' );
		$node['url'] = $url;

		if ( $meta['phone'] )       $node['telephone']  = $meta['phone'];
		if ( $meta['email'] )       $node['email']      = $meta['email'];
		if ( $meta['price_range'] ) $node['priceRange'] = $meta['price_range'];

		$address = $this->build_address( $meta );
		if ( ! empty( $address ) ) $node['address'] = $address;

		$geo = $this->build_geo( $meta );
		if ( ! empty( $geo ) ) $node['geo'] = $geo;

		$hours = $this->build_hours( $meta );
		if ( ! empty( $hours ) ) $node['openingHoursSpecification'] = $hours;

		if ( ! empty( $meta['same_as'] ) ) {
			$node['sameAs'] = array_values( array_unique( $meta['same_as'] ) );
		}

		$area = $this->build_area_served( $meta );
		if ( ! empty( $area ) ) $node['areaServed'] = $area;

		$image = $this->build_image( $meta, $location_id );
		if ( $image ) $node['image'] = $image;

		// Pro: attach Service catalog.
		if ( ( new LSP_License() )->is_pro() ) {
			$catalog = $this->build_offer_catalog( $location_id );
			if ( ! empty( $catalog ) ) $node['hasOfferCatalog'] = $catalog;
		}

		return $node;
	}

	private function build_address( $meta ) {
		$fields = array( 'street', 'city', 'region', 'postal', 'country' );
		$any = false;
		foreach ( $fields as $f ) {
			if ( ! empty( $meta[ $f ] ) ) { $any = true; break; }
		}
		if ( ! $any ) return null;
		$a = array( '@type' => 'PostalAddress' );
		if ( $meta['street'] )  $a['streetAddress']   = $meta['street'];
		if ( $meta['city'] )    $a['addressLocality'] = $meta['city'];
		if ( $meta['region'] )  $a['addressRegion']   = $meta['region'];
		if ( $meta['postal'] )  $a['postalCode']      = $meta['postal'];
		if ( $meta['country'] ) $a['addressCountry']  = $meta['country'];
		return $a;
	}

	private function build_geo( $meta ) {
		if ( '' === $meta['latitude'] || '' === $meta['longitude'] ) return null;
		return array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => (float) $meta['latitude'],
			'longitude' => (float) $meta['longitude'],
		);
	}

	/**
	 * Convert hours grid into openingHoursSpecification.
	 * We emit one spec per continuous day-of-week group with identical hours.
	 */
	private function build_hours( $meta ) {
		if ( empty( $meta['hours'] ) || ! is_array( $meta['hours'] ) ) return array();
		$days = LSP_Location::days_of_week();
		$specs = array();
		foreach ( $days as $d => $day_name ) {
			$row = isset( $meta['hours'][ $d ] ) ? $meta['hours'][ $d ] : null;
			if ( ! $row ) continue;
			if ( ! empty( $row['closed'] ) ) {
				$specs[] = array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => $day_name,
					'opens'     => '00:00',
					'closes'    => '00:00',
				);
				continue;
			}
			if ( '' === $row['open'] || '' === $row['close'] ) continue;
			$specs[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => $day_name,
				'opens'     => $row['open'],
				'closes'    => $row['close'],
			);
		}
		return $specs;
	}

	/**
	 * areaServed: v1 emits a list of Place names. Structure per Google's
	 * guidance for LocalBusiness with service areas — cleaner than raw strings.
	 */
	private function build_area_served( $meta ) {
		if ( empty( $meta['area_served'] ) ) return array();
		$out = array();
		foreach ( (array) $meta['area_served'] as $name ) {
			$name = trim( $name );
			if ( '' === $name ) continue;
			$out[] = array( '@type' => 'Place', 'name' => $name );
		}
		return $out;
	}

	private function build_image( $meta, $location_id ) {
		if ( ! empty( $meta['image_url'] ) ) return $meta['image_url'];
		$thumb_id = get_post_thumbnail_id( $location_id );
		if ( $thumb_id ) {
			$src = wp_get_attachment_image_src( $thumb_id, 'full' );
			if ( $src && ! empty( $src[0] ) ) return $src[0];
		}
		return '';
	}

	private function build_offer_catalog( $location_id ) {
		$service_ids = LSP_Service::ids_for_location( $location_id );
		if ( empty( $service_ids ) ) return null;
		$items = array();
		foreach ( $service_ids as $sid ) {
			$s = get_post( $sid );
			if ( ! $s || 'publish' !== $s->post_status ) continue;
			$sm = LSP_Service::get_meta( $sid );

			$item_offered = array(
				'@type' => $sm['service_type'] ?: 'Service',
				'name'  => get_the_title( $sid ),
			);
			$body = wp_strip_all_tags( (string) $s->post_content );
			if ( '' !== $body ) $item_offered['description'] = wp_trim_words( $body, 60 );
			if ( ! empty( $sm['duration'] ) ) $item_offered['duration'] = $sm['duration'];
			if ( ! empty( $sm['image_url'] ) ) {
				$item_offered['image'] = $sm['image_url'];
			} else {
				$thumb = get_post_thumbnail_id( $sid );
				if ( $thumb ) {
					$src = wp_get_attachment_image_src( $thumb, 'full' );
					if ( $src && ! empty( $src[0] ) ) $item_offered['image'] = $src[0];
				}
			}

			$offer = array( '@type' => 'Offer' );
			if ( '' !== $sm['price'] && null !== $sm['price'] ) {
				$offer['price']         = (string) $sm['price'];
				$offer['priceCurrency'] = $sm['price_currency'] ?: 'USD';
			}
			if ( ! empty( $sm['availability'] ) ) {
				$offer['availability'] = 'https://schema.org/' . $sm['availability'];
			}
			if ( ! empty( $sm['valid_from'] ) )    $offer['validFrom']    = $sm['valid_from'];
			if ( ! empty( $sm['valid_through'] ) ) $offer['validThrough'] = $sm['valid_through'];
			if ( ! empty( $sm['price_unit'] ) )    $offer['eligibleTransactionVolume'] = array( '@type' => 'UnitPriceSpecification', 'unitCode' => $sm['price_unit'] );

			$offer['itemOffered'] = $item_offered;

			$items[] = array(
				'@type'    => 'OfferCatalog',
				'name'     => get_the_title( $sid ),
				'itemListElement' => array( $offer ),
			);
		}
		if ( empty( $items ) ) return null;

		return array(
			'@type'           => 'OfferCatalog',
			/* translators: %s: the location or business name. */
			'name'            => sprintf( __( 'Services offered by %s', 'localbusiness-schema' ), get_the_title( $location_id ) ),
			'itemListElement' => $items,
		);
	}
}
