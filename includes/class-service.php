<?php
/**
 * Service CPT — Pro-tier only.
 *
 * One post = one Service or TouristTrip attached to a Location. Emitted
 * as an entry in the location's `hasOfferCatalog`.
 *
 * Meta shape:
 *   array{
 *     service_type: string,          // Schema type: Service | TouristTrip | etc.
 *     location_id: int,              // Parent Location post ID
 *     description: string,
 *     price: string,                 // decimal string
 *     price_currency: string,        // ISO 4217 (default USD)
 *     price_unit: string,            // "" | "PER_HOUR" | "PER_TRIP" | "PER_DAY" | "PER_PERSON"
 *     availability: string,          // schema.org ItemAvailability enum
 *     valid_from: string,            // YYYY-MM-DD
 *     valid_through: string,         // YYYY-MM-DD
 *     duration: string,              // ISO 8601 (e.g. PT4H)
 *     image_url: string,
 *   }
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LSP_Service {

	const META_KEY = '_lsp_service';
	const NONCE    = 'lsp_service_nonce';

	public static function service_types() {
		return array(
			'Service'         => __( 'Service (generic)', 'localbusiness-schema-pro' ),
			'TouristTrip'     => __( 'TouristTrip',       'localbusiness-schema-pro' ),
			'HomeAndConstructionService' => __( 'Home / Construction Service', 'localbusiness-schema-pro' ),
			'FinancialProduct'=> __( 'FinancialProduct',  'localbusiness-schema-pro' ),
			'FoodService'     => __( 'FoodService',       'localbusiness-schema-pro' ),
			'Course'          => __( 'Course',            'localbusiness-schema-pro' ),
		);
	}

	public static function availability_options() {
		return array(
			'InStock'          => __( 'InStock (default)', 'localbusiness-schema-pro' ),
			'LimitedAvailability' => __( 'LimitedAvailability', 'localbusiness-schema-pro' ),
			'PreOrder'         => __( 'PreOrder', 'localbusiness-schema-pro' ),
			'SoldOut'          => __( 'SoldOut', 'localbusiness-schema-pro' ),
			'Discontinued'     => __( 'Discontinued', 'localbusiness-schema-pro' ),
		);
	}

	public static function price_unit_options() {
		return array(
			''            => __( '(none — flat price)', 'localbusiness-schema-pro' ),
			'PER_HOUR'    => __( 'per hour', 'localbusiness-schema-pro' ),
			'PER_TRIP'    => __( 'per trip', 'localbusiness-schema-pro' ),
			'PER_DAY'     => __( 'per day', 'localbusiness-schema-pro' ),
			'PER_PERSON'  => __( 'per person', 'localbusiness-schema-pro' ),
			'PER_SESSION' => __( 'per session', 'localbusiness-schema-pro' ),
		);
	}

	public function register_cpt() {
		$args = array(
			'labels' => array(
				'name'          => __( 'Services / Trips', 'localbusiness-schema-pro' ),
				'singular_name' => __( 'Service / Trip', 'localbusiness-schema-pro' ),
				'add_new_item'  => __( 'Add Service / Trip', 'localbusiness-schema-pro' ),
				'edit_item'     => __( 'Edit Service / Trip', 'localbusiness-schema-pro' ),
				'menu_name'     => __( 'Services / Trips', 'localbusiness-schema-pro' ),
				'search_items'  => __( 'Search Services', 'localbusiness-schema-pro' ),
				'not_found'     => __( 'No services yet.', 'localbusiness-schema-pro' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=' . LSP_CPT_LOCATION,
			'menu_icon'           => 'dashicons-tag',
			'supports'            => array( 'title', 'editor', 'thumbnail' ),
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
		);
		register_post_type( LSP_CPT_SERVICE, $args );
	}

	public function register_metabox() {
		add_meta_box(
			'lsp_service_meta',
			__( 'Service / Trip details', 'localbusiness-schema-pro' ),
			array( $this, 'render_metabox' ),
			LSP_CPT_SERVICE,
			'normal',
			'high'
		);
	}

	public function render_metabox( $post ) {
		$license = new LSP_License();
		if ( ! $license->is_pro() ) {
			echo '<div class="notice notice-warning inline" style="margin:0;padding:12px 16px;"><p>';
			echo esc_html__( 'Services / Trips require a Pro license.', 'localbusiness-schema-pro' );
			echo ' <a href="https://kitmobley.com/plugins/' . esc_attr( LSP_SLUG ) . '/#pricing" target="_blank" rel="noopener">' . esc_html__( 'Get Pro', 'localbusiness-schema-pro' ) . '</a>';
			echo '</p></div>';
			return;
		}

		wp_nonce_field( self::NONCE, self::NONCE );
		$meta = self::get_meta( $post->ID );

		$locations = LSP_Location::all_ids();
		?>
		<style>
			.lsp-service { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 20px; }
			.lsp-service label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #50575e; }
			.lsp-service input[type=text], .lsp-service input[type=number], .lsp-service input[type=date], .lsp-service select, .lsp-service textarea { width: 100%; }
			.lsp-service .full { grid-column: 1 / -1; }
			.lsp-service textarea { min-height: 60px; }
		</style>
		<div class="lsp-service">
			<div>
				<label for="lsp-service-type">Schema type</label>
				<select name="lsp_service[service_type]" id="lsp-service-type">
					<?php foreach ( self::service_types() as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $meta['service_type'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="lsp-service-location">Location (provider)</label>
				<select name="lsp_service[location_id]" id="lsp-service-location">
					<option value="0">&mdash;</option>
					<?php foreach ( $locations as $lid ) : ?>
						<option value="<?php echo (int) $lid; ?>" <?php selected( (int) $meta['location_id'], (int) $lid ); ?>>
							<?php echo esc_html( get_the_title( $lid ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div>
				<label for="lsp-service-price">Price</label>
				<input type="number" step="0.01" min="0" name="lsp_service[price]" id="lsp-service-price" value="<?php echo esc_attr( $meta['price'] ); ?>" />
			</div>
			<div>
				<label for="lsp-service-currency">Currency (ISO 4217)</label>
				<input type="text" maxlength="3" name="lsp_service[price_currency]" id="lsp-service-currency" value="<?php echo esc_attr( $meta['price_currency'] ); ?>" placeholder="USD" />
			</div>

			<div>
				<label for="lsp-service-unit">Price unit</label>
				<select name="lsp_service[price_unit]" id="lsp-service-unit">
					<?php foreach ( self::price_unit_options() as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $meta['price_unit'], $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div>
				<label for="lsp-service-availability">Availability</label>
				<select name="lsp_service[availability]" id="lsp-service-availability">
					<?php foreach ( self::availability_options() as $val => $label ) : ?>
						<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $meta['availability'], $val ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div>
				<label for="lsp-service-valid-from">Valid from</label>
				<input type="date" name="lsp_service[valid_from]" id="lsp-service-valid-from" value="<?php echo esc_attr( $meta['valid_from'] ); ?>" />
			</div>
			<div>
				<label for="lsp-service-valid-through">Valid through</label>
				<input type="date" name="lsp_service[valid_through]" id="lsp-service-valid-through" value="<?php echo esc_attr( $meta['valid_through'] ); ?>" />
			</div>

			<div>
				<label for="lsp-service-duration">Duration (ISO 8601)</label>
				<input type="text" name="lsp_service[duration]" id="lsp-service-duration" value="<?php echo esc_attr( $meta['duration'] ); ?>" placeholder="PT4H" />
			</div>
			<div>
				<label for="lsp-service-image-url">Image URL (optional)</label>
				<input type="url" name="lsp_service[image_url]" id="lsp-service-image-url" value="<?php echo esc_attr( $meta['image_url'] ); ?>" />
			</div>
		</div>
		<?php
	}

	public static function save_meta( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( $_POST[ self::NONCE ], self::NONCE ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		if ( ! ( new LSP_License() )->is_pro() ) return;

		$in = isset( $_POST['lsp_service'] ) && is_array( $_POST['lsp_service'] ) ? wp_unslash( $_POST['lsp_service'] ) : array();
		update_post_meta( $post_id, self::META_KEY, self::sanitize_meta( $in ) );
	}

	public static function sanitize_meta( array $in ) {
		$service_types  = array_keys( self::service_types() );
		$avail          = array_keys( self::availability_options() );
		$units          = array_keys( self::price_unit_options() );

		return array(
			'service_type'   => in_array( isset( $in['service_type'] ) ? $in['service_type'] : '', $service_types, true ) ? $in['service_type'] : 'Service',
			'location_id'    => isset( $in['location_id'] ) ? (int) $in['location_id'] : 0,
			'price'          => isset( $in['price'] ) ? preg_replace( '/[^0-9.]/', '', $in['price'] ) : '',
			'price_currency' => isset( $in['price_currency'] ) ? strtoupper( sanitize_text_field( substr( $in['price_currency'], 0, 3 ) ) ) : 'USD',
			'price_unit'     => in_array( isset( $in['price_unit'] ) ? $in['price_unit'] : '', $units, true ) ? $in['price_unit'] : '',
			'availability'   => in_array( isset( $in['availability'] ) ? $in['availability'] : '', $avail, true ) ? $in['availability'] : 'InStock',
			'valid_from'     => isset( $in['valid_from'] )    ? self::sanitize_date( $in['valid_from'] )    : '',
			'valid_through'  => isset( $in['valid_through'] ) ? self::sanitize_date( $in['valid_through'] ) : '',
			'duration'       => isset( $in['duration'] ) ? sanitize_text_field( $in['duration'] ) : '',
			'image_url'      => isset( $in['image_url'] ) ? esc_url_raw( $in['image_url'] ) : '',
		);
	}

	private static function sanitize_date( $v ) {
		$v = trim( (string) $v );
		if ( '' === $v ) return '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ) return '';
		return $v;
	}

	public static function get_meta( $post_id ) {
		$defaults = array(
			'service_type'   => 'Service',
			'location_id'    => 0,
			'price'          => '',
			'price_currency' => 'USD',
			'price_unit'     => '',
			'availability'   => 'InStock',
			'valid_from'     => '',
			'valid_through'  => '',
			'duration'       => '',
			'image_url'      => '',
		);
		$stored = get_post_meta( $post_id, self::META_KEY, true );
		if ( ! is_array( $stored ) ) $stored = array();
		return array_merge( $defaults, $stored );
	}

	/**
	 * All Service IDs for a given Location (published).
	 *
	 * @param int $location_id
	 * @return int[]
	 */
	public static function ids_for_location( $location_id ) {
		if ( ! $location_id ) return array();
		$q = new WP_Query( array(
			'post_type'      => LSP_CPT_SERVICE,
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'   => self::META_KEY,
					'value' => '"location_id";i:' . (int) $location_id . ';',
					'compare' => 'LIKE',
				),
			),
			'orderby' => 'menu_order title',
			'order'   => 'ASC',
		) );
		return array_map( 'intval', $q->posts );
	}
}
