<?php
/**
 * Location CPT — one post = one physical location or service-area business.
 *
 * Meta shape (all stored under the LSP_LOC_META_KEY option per-post):
 *   array{
 *     business_type: string,           // Schema.org type: LocalBusiness / Restaurant / Store / etc.
 *     street: string,
 *     city: string,
 *     region: string,                  // state/province code (e.g. FL)
 *     postal: string,
 *     country: string,                 // ISO-3166-1 alpha-2 (e.g. US)
 *     latitude: float|string,
 *     longitude: float|string,
 *     phone: string,
 *     email: string,
 *     url: string,
 *     price_range: string,             // e.g. "$", "$$", "$$$"
 *     image_url: string,               // optional external URL; featured image used otherwise
 *     same_as: string[],               // one URL per line in UI
 *     area_served: string[],           // v1 = list of Place names; v1.1 adds GeoShape polygons
 *     hours: array<int,array{open:string,close:string,closed:bool}>,  // 0=Sun ... 6=Sat
 *   }
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LSP_Location {

	const META_KEY = '_lsp_location';
	const NONCE    = 'lsp_location_nonce';

	/** @var string[] */
	public static function business_types() {
		return array(
			'LocalBusiness'                => __( 'LocalBusiness (generic)', 'localbusiness-schema' ),
			'ProfessionalService'          => __( 'ProfessionalService',      'localbusiness-schema' ),
			'AutomotiveBusiness'           => __( 'AutomotiveBusiness',       'localbusiness-schema' ),
			'Restaurant'                   => __( 'Restaurant',               'localbusiness-schema' ),
			'CafeOrCoffeeShop'             => __( 'CafeOrCoffeeShop',         'localbusiness-schema' ),
			'BarOrPub'                     => __( 'BarOrPub',                 'localbusiness-schema' ),
			'FoodEstablishment'            => __( 'FoodEstablishment',        'localbusiness-schema' ),
			'LodgingBusiness'              => __( 'LodgingBusiness',          'localbusiness-schema' ),
			'Hotel'                        => __( 'Hotel',                    'localbusiness-schema' ),
			'BedAndBreakfast'              => __( 'BedAndBreakfast',          'localbusiness-schema' ),
			'Store'                        => __( 'Store',                    'localbusiness-schema' ),
			'GroceryStore'                 => __( 'GroceryStore',             'localbusiness-schema' ),
			'ClothingStore'                => __( 'ClothingStore',            'localbusiness-schema' ),
			'HardwareStore'                => __( 'HardwareStore',            'localbusiness-schema' ),
			'Attorney'                     => __( 'Attorney',                 'localbusiness-schema' ),
			'Dentist'                      => __( 'Dentist',                  'localbusiness-schema' ),
			'MedicalBusiness'              => __( 'MedicalBusiness',          'localbusiness-schema' ),
			'HealthAndBeautyBusiness'      => __( 'HealthAndBeautyBusiness',  'localbusiness-schema' ),
			'HairSalon'                    => __( 'HairSalon',                'localbusiness-schema' ),
			'DaySpa'                       => __( 'DaySpa',                   'localbusiness-schema' ),
			'HomeAndConstructionBusiness'  => __( 'HomeAndConstructionBusiness', 'localbusiness-schema' ),
			'Plumber'                      => __( 'Plumber',                  'localbusiness-schema' ),
			'Electrician'                  => __( 'Electrician',              'localbusiness-schema' ),
			'HVACBusiness'                 => __( 'HVACBusiness',             'localbusiness-schema' ),
			'RoofingContractor'            => __( 'RoofingContractor',        'localbusiness-schema' ),
			'GeneralContractor'            => __( 'GeneralContractor',        'localbusiness-schema' ),
			'MovingCompany'                => __( 'MovingCompany',            'localbusiness-schema' ),
			'Locksmith'                    => __( 'Locksmith',                'localbusiness-schema' ),
			'HousePainter'                 => __( 'HousePainter',             'localbusiness-schema' ),
			'TravelAgency'                 => __( 'TravelAgency',             'localbusiness-schema' ),
			'RealEstateAgent'              => __( 'RealEstateAgent',          'localbusiness-schema' ),
			'SportsActivityLocation'       => __( 'SportsActivityLocation',   'localbusiness-schema' ),
			'EmergencyService'             => __( 'EmergencyService',         'localbusiness-schema' ),
			'FinancialService'             => __( 'FinancialService',         'localbusiness-schema' ),
			'LegalService'                 => __( 'LegalService',             'localbusiness-schema' ),
		);
	}

	public static function days_of_week() {
		return array(
			0 => 'Sunday',
			1 => 'Monday',
			2 => 'Tuesday',
			3 => 'Wednesday',
			4 => 'Thursday',
			5 => 'Friday',
			6 => 'Saturday',
		);
	}

	public function register_cpt() {
		$args = array(
			'label'               => __( 'Locations', 'localbusiness-schema' ),
			'labels'              => array(
				'name'          => __( 'Locations', 'localbusiness-schema' ),
				'singular_name' => __( 'Location',  'localbusiness-schema' ),
				'add_new_item'  => __( 'Add Location', 'localbusiness-schema' ),
				'edit_item'     => __( 'Edit Location', 'localbusiness-schema' ),
				'menu_name'     => __( 'Locations', 'localbusiness-schema' ),
				'search_items'  => __( 'Search Locations', 'localbusiness-schema' ),
				'not_found'     => __( 'No locations yet.', 'localbusiness-schema' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=' . LSP_CPT_LOCATION,
			'menu_icon'           => 'dashicons-location',
			'menu_position'       => 26,
			'supports'            => array( 'title', 'thumbnail' ),
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
		);
		register_post_type( LSP_CPT_LOCATION, $args );
	}

	public function register_metabox() {
		add_meta_box(
			'lsp_location_meta',
			__( 'Location details', 'localbusiness-schema' ),
			array( $this, 'render_metabox' ),
			LSP_CPT_LOCATION,
			'normal',
			'high'
		);
	}

	public function render_metabox( $post ) {
		wp_nonce_field( self::NONCE, self::NONCE );
		$meta = self::get_meta( $post->ID );

		$types = self::business_types();
		$days  = self::days_of_week();
		?>
		<style>
			.lsp-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 20px; }
			.lsp-meta label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #50575e; }
			.lsp-meta input[type=text], .lsp-meta input[type=email], .lsp-meta input[type=url], .lsp-meta select, .lsp-meta textarea { width: 100%; }
			.lsp-meta .full { grid-column: 1 / -1; }
			.lsp-meta textarea { min-height: 84px; font-family: ui-monospace, monospace; font-size: 12px; }
			.lsp-hours { border: 1px solid #dcdcde; border-radius: 3px; padding: 10px 14px; background: #fafafa; }
			.lsp-hours-row { display: grid; grid-template-columns: 90px 90px 90px auto; gap: 8px; align-items: center; margin-bottom: 6px; }
			.lsp-hours-row label.day { font-weight: 600; color: #1d2327; text-transform: none; letter-spacing: 0; margin: 0; }
			.lsp-hours-row input[type=time] { padding: 3px 6px; }
			.lsp-help { color: #50575e; font-size: 12px; margin: 4px 0 0; }
		</style>

		<div class="lsp-meta">

			<div>
				<label for="lsp-business-type">Schema type</label>
				<select name="lsp[business_type]" id="lsp-business-type">
					<?php foreach ( $types as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $meta['business_type'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="lsp-help">The most specific Schema.org LocalBusiness subtype that fits.</p>
			</div>

			<div>
				<label for="lsp-price-range">Price range</label>
				<input type="text" name="lsp[price_range]" id="lsp-price-range" value="<?php echo esc_attr( $meta['price_range'] ); ?>" placeholder="$$" />
				<p class="lsp-help">Google convention: $, $$, $$$, $$$$ — or a range like &quot;$50-$150&quot;.</p>
			</div>

			<div class="full"><hr /></div>

			<div>
				<label for="lsp-street">Street address</label>
				<input type="text" name="lsp[street]" id="lsp-street" value="<?php echo esc_attr( $meta['street'] ); ?>" />
			</div>
			<div>
				<label for="lsp-city">City</label>
				<input type="text" name="lsp[city]" id="lsp-city" value="<?php echo esc_attr( $meta['city'] ); ?>" />
			</div>
			<div>
				<label for="lsp-region">Region / State</label>
				<input type="text" name="lsp[region]" id="lsp-region" value="<?php echo esc_attr( $meta['region'] ); ?>" placeholder="FL" />
			</div>
			<div>
				<label for="lsp-postal">Postal code</label>
				<input type="text" name="lsp[postal]" id="lsp-postal" value="<?php echo esc_attr( $meta['postal'] ); ?>" />
			</div>
			<div>
				<label for="lsp-country">Country (ISO 3166-1 alpha-2)</label>
				<input type="text" name="lsp[country]" id="lsp-country" value="<?php echo esc_attr( $meta['country'] ); ?>" maxlength="2" placeholder="US" />
			</div>
			<div>
				<label for="lsp-latitude">Latitude</label>
				<input type="text" name="lsp[latitude]" id="lsp-latitude" value="<?php echo esc_attr( $meta['latitude'] ); ?>" placeholder="24.9247" />
			</div>
			<div>
				<label for="lsp-longitude">Longitude</label>
				<input type="text" name="lsp[longitude]" id="lsp-longitude" value="<?php echo esc_attr( $meta['longitude'] ); ?>" placeholder="-80.6311" />
			</div>

			<div class="full"><hr /></div>

			<div>
				<label for="lsp-phone">Phone (E.164)</label>
				<input type="text" name="lsp[phone]" id="lsp-phone" value="<?php echo esc_attr( $meta['phone'] ); ?>" placeholder="+19546829551" />
			</div>
			<div>
				<label for="lsp-email">Email</label>
				<input type="email" name="lsp[email]" id="lsp-email" value="<?php echo esc_attr( $meta['email'] ); ?>" />
			</div>
			<div>
				<label for="lsp-url">Public URL</label>
				<input type="url" name="lsp[url]" id="lsp-url" value="<?php echo esc_attr( $meta['url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>" />
				<p class="lsp-help">Defaults to your site home if left blank.</p>
			</div>
			<div>
				<label for="lsp-image-url">Image URL (optional)</label>
				<input type="url" name="lsp[image_url]" id="lsp-image-url" value="<?php echo esc_attr( $meta['image_url'] ); ?>" />
				<p class="lsp-help">Overrides the featured image if set.</p>
			</div>

			<div class="full"><hr /></div>

			<div class="full">
				<label for="lsp-same-as"><code>sameAs</code> URLs (one per line)</label>
				<textarea name="lsp[same_as]" id="lsp-same-as" rows="4"><?php echo esc_textarea( implode( "\n", (array) $meta['same_as'] ) ); ?></textarea>
				<p class="lsp-help">Google Business Profile, Facebook, Instagram, LinkedIn, Yelp, TripAdvisor, industry associations, etc.</p>
			</div>

			<div class="full">
				<label for="lsp-area-served"><code>areaServed</code> — cities / regions you serve (one per line)</label>
				<textarea name="lsp[area_served]" id="lsp-area-served" rows="4"><?php echo esc_textarea( implode( "\n", (array) $meta['area_served'] ) ); ?></textarea>
				<p class="lsp-help">Especially important for service-area businesses. v1.1 will add polygon drawing.</p>
			</div>

			<div class="full">
				<label>Opening hours</label>
				<div class="lsp-hours">
					<?php foreach ( $days as $d => $day_label ) :
						$row = isset( $meta['hours'][ $d ] ) ? $meta['hours'][ $d ] : array( 'open' => '', 'close' => '', 'closed' => 0 );
						?>
						<div class="lsp-hours-row">
							<label class="day"><?php echo esc_html( $day_label ); ?></label>
							<input type="time" name="lsp[hours][<?php echo esc_attr( $d ); ?>][open]"  value="<?php echo esc_attr( $row['open'] ); ?>" />
							<input type="time" name="lsp[hours][<?php echo esc_attr( $d ); ?>][close]" value="<?php echo esc_attr( $row['close'] ); ?>" />
							<label style="display:inline-flex;align-items:center;gap:6px;font-weight:400;text-transform:none;letter-spacing:0;">
								<input type="checkbox" name="lsp[hours][<?php echo esc_attr( $d ); ?>][closed]" value="1" <?php checked( ! empty( $row['closed'] ) ); ?> />
								<span>Closed</span>
							</label>
						</div>
					<?php endforeach; ?>
				</div>
				<p class="lsp-help">Leave open/close blank for a day and it will be omitted. Check &quot;Closed&quot; to emit an explicit closed-day statement.</p>
			</div>

		</div>
		<?php
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		// Unslashed here and fully sanitised field by field in sanitize_meta().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$in    = isset( $_POST['lsp'] ) && is_array( $_POST['lsp'] ) ? wp_unslash( $_POST['lsp'] ) : array();
		$meta  = self::sanitize_meta( $in );
		update_post_meta( $post_id, self::META_KEY, $meta );
	}

	public static function sanitize_meta( array $in ) {
		$types_allowed = array_keys( self::business_types() );

		$out = array(
			'business_type' => in_array( isset( $in['business_type'] ) ? $in['business_type'] : '', $types_allowed, true ) ? $in['business_type'] : 'LocalBusiness',
			'street'        => isset( $in['street'] )   ? sanitize_text_field( $in['street'] )   : '',
			'city'          => isset( $in['city'] )     ? sanitize_text_field( $in['city'] )     : '',
			'region'        => isset( $in['region'] )   ? sanitize_text_field( $in['region'] )   : '',
			'postal'        => isset( $in['postal'] )   ? sanitize_text_field( $in['postal'] )   : '',
			'country'       => isset( $in['country'] )  ? strtoupper( sanitize_text_field( substr( $in['country'], 0, 2 ) ) ) : '',
			'latitude'      => isset( $in['latitude'] ) ? self::sanitize_coord( $in['latitude'] )  : '',
			'longitude'     => isset( $in['longitude'] )? self::sanitize_coord( $in['longitude'] ) : '',
			'phone'         => isset( $in['phone'] )    ? sanitize_text_field( $in['phone'] )    : '',
			'email'         => isset( $in['email'] )    ? sanitize_email( $in['email'] )         : '',
			'url'           => isset( $in['url'] )      ? esc_url_raw( $in['url'] )              : '',
			'price_range'   => isset( $in['price_range'] ) ? sanitize_text_field( $in['price_range'] ) : '',
			'image_url'     => isset( $in['image_url'] )   ? esc_url_raw( $in['image_url'] )        : '',
			'same_as'       => self::sanitize_url_list( isset( $in['same_as'] ) ? $in['same_as'] : '' ),
			'area_served'   => self::sanitize_text_list( isset( $in['area_served'] ) ? $in['area_served'] : '' ),
			'hours'         => self::sanitize_hours( isset( $in['hours'] ) && is_array( $in['hours'] ) ? $in['hours'] : array() ),
		);
		return $out;
	}

	private static function sanitize_coord( $v ) {
		$v = trim( (string) $v );
		if ( '' === $v ) return '';
		if ( ! is_numeric( $v ) ) return '';
		return (string) (float) $v;
	}

	private static function sanitize_url_list( $raw ) {
		if ( is_array( $raw ) ) $raw = implode( "\n", $raw );
		$lines = preg_split( '/[\r\n]+/', (string) $raw );
		$out = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) continue;
			$u = esc_url_raw( $line );
			if ( $u ) $out[] = $u;
		}
		return array_values( array_unique( $out ) );
	}

	private static function sanitize_text_list( $raw ) {
		if ( is_array( $raw ) ) $raw = implode( "\n", $raw );
		$lines = preg_split( '/[\r\n]+/', (string) $raw );
		$out = array();
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) continue;
			$out[] = sanitize_text_field( $line );
		}
		return array_values( array_unique( $out ) );
	}

	private static function sanitize_hours( array $in ) {
		$out = array();
		foreach ( self::days_of_week() as $d => $_ ) {
			$row = isset( $in[ $d ] ) && is_array( $in[ $d ] ) ? $in[ $d ] : array();
			$open   = isset( $row['open'] )   ? preg_replace( '/[^0-9:]/', '', $row['open'] )   : '';
			$close  = isset( $row['close'] )  ? preg_replace( '/[^0-9:]/', '', $row['close'] )  : '';
			$closed = ! empty( $row['closed'] );
			$out[ $d ] = array( 'open' => $open, 'close' => $close, 'closed' => $closed ? 1 : 0 );
		}
		return $out;
	}

	public static function get_meta( $post_id ) {
		$defaults = array(
			'business_type' => 'LocalBusiness',
			'street'        => '',
			'city'          => '',
			'region'        => '',
			'postal'        => '',
			'country'       => '',
			'latitude'      => '',
			'longitude'     => '',
			'phone'         => '',
			'email'         => '',
			'url'           => '',
			'price_range'   => '',
			'image_url'     => '',
			'same_as'       => array(),
			'area_served'   => array(),
			'hours'         => array(),
		);
		$stored = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $stored ) ) $stored = array();
		return array_merge( $defaults, $stored );
	}

	/**
	 * All published Location IDs.
	 *
	 * @return int[]
	 */
	public static function all_ids() {
		$q = new WP_Query( array(
			'post_type'      => LSP_CPT_LOCATION,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		) );
		return array_map( 'intval', $q->posts );
	}
}
