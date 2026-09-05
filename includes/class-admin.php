<?php
/**
 * Admin surface — a top-level "Local Schema" page with tabs for License,
 * Settings, and quick links to the Location / Service CPT screens.
 *
 * Also injects a Pro badge in the Locations list view when a user is at
 * the free-tier location cap.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LSP_Admin {

	const FREE_LOCATION_CAP = 1;

	public function __construct() {
		add_action( 'admin_menu',                     array( $this, 'register_menu' ) );
		add_action( 'admin_init',                     array( $this, 'register_settings' ) );
		add_action( 'admin_notices',                  array( $this, 'maybe_cap_notice' ) );
		add_action( 'wp_ajax_lsp_license_save',       array( $this, 'ajax_license_save' ) );
		add_action( 'wp_ajax_lsp_license_remove',     array( $this, 'ajax_license_remove' ) );
		add_action( 'admin_enqueue_scripts',          array( $this, 'enqueue_assets' ) );

		// Add a per-post location override on singular editable post types
		// (post, page). Charter/service sites can point specific posts at a
		// specific Location for their JSON-LD.
		add_action( 'add_meta_boxes', array( $this, 'register_override_metabox' ) );
		add_action( 'save_post',      array( $this, 'save_override_metabox' ), 10, 2 );

	}

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=' . LSP_CPT_LOCATION,
			__( 'License', 'localbusiness-schema' ),
			__( 'License', 'localbusiness-schema' ),
			'manage_options',
			'lsp-license',
			array( $this, 'render_license_page' )
		);
		add_submenu_page(
			'edit.php?post_type=' . LSP_CPT_LOCATION,
			__( 'Settings', 'localbusiness-schema' ),
			__( 'Settings', 'localbusiness-schema' ),
			'manage_options',
			'lsp-settings',
			array( $this, 'render_settings_page' )
		);
	}

	public function register_settings() {
		register_setting( 'lsp_settings_group', 'lsp_settings', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_settings' ),
			'default'           => array(
				'primary_location_id' => 0,
				'inject_on_home'      => 1,
				'inject_on_singular'  => 1,
				'coexistence_mode'    => 'alongside',
			),
		) );
	}

	public function sanitize_settings( $input ) {
		$modes = array( 'alongside', 'defer_if_present' );
		return array(
			'primary_location_id' => isset( $input['primary_location_id'] ) ? max( 0, (int) $input['primary_location_id'] ) : 0,
			'inject_on_home'      => ! empty( $input['inject_on_home'] ) ? 1 : 0,
			'inject_on_singular'  => ! empty( $input['inject_on_singular'] ) ? 1 : 0,
			'coexistence_mode'    => isset( $input['coexistence_mode'] ) && in_array( $input['coexistence_mode'], $modes, true ) ? $input['coexistence_mode'] : 'alongside',
		);
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( (string) $hook, 'lsp' ) === false && get_post_type() !== LSP_CPT_LOCATION && get_post_type() !== LSP_CPT_SERVICE ) {
			return;
		}
		wp_enqueue_style(
			'lsp-admin',
			LSP_URL . 'assets/admin.css',
			array(),
			LSP_VERSION
		);
	}

	public function render_license_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'localbusiness-schema' ) );
		}
		$license = new LSP_License();
		$state = $license->get_state();
		$is_pro = $license->is_pro();
		$expires_label = '';
		if ( ! empty( $state['expires_at'] ) ) {
			$ts = strtotime( $state['expires_at'] );
			if ( $ts ) $expires_label = date_i18n( get_option( 'date_format' ), $ts );
		}
		?>
		<div class="wrap lsp-wrap">
			<h1><?php esc_html_e( 'LocalBusiness Schema Pro — License', 'localbusiness-schema' ); ?>
				<?php if ( $is_pro ) : ?>
					<span class="lsp-pro-badge">PRO · <?php echo esc_html( ucfirst( (string) $license->tier() ) ); ?></span>
				<?php endif; ?>
			</h1>

			<?php if ( $is_pro ) : ?>
				<div class="lsp-license-card lsp-license-active">
					<h2><?php esc_html_e( 'Pro is active', 'localbusiness-schema' ); ?></h2>
					<dl class="lsp-license-meta">
						<dt>License key</dt>
						<dd><code><?php echo esc_html( $state['key'] ); ?></code></dd>
						<dt>Tier</dt>
						<dd><?php echo esc_html( ucfirst( (string) $state['tier'] ) ); ?></dd>
						<dt>Sites used</dt>
						<dd><?php echo esc_html( count( (array) $state['activated_sites'] ) . ' / ' . (int) $state['max_sites'] ); ?></dd>
						<?php if ( $expires_label ) : ?>
							<dt>Renews</dt>
							<dd><?php echo esc_html( $expires_label ); ?></dd>
						<?php endif; ?>
					</dl>
					<p>
						<button type="button" class="button button-secondary" id="lsp-license-remove"><?php esc_html_e( 'Deactivate this site', 'localbusiness-schema' ); ?></button>
					</p>
				</div>
			<?php else : ?>
				<div class="lsp-license-card">
					<h2><?php esc_html_e( 'Activate Pro', 'localbusiness-schema' ); ?></h2>
					<p><?php esc_html_e( 'Paste your license key from the purchase email. Pro unlocks multi-location, service/trip catalog, and automatic plugin updates.', 'localbusiness-schema' ); ?></p>
					<?php if ( ! empty( $state['message'] ) && 'inactive' !== $state['status'] ) : ?>
						<div class="notice notice-error inline"><p><?php echo esc_html( $state['message'] ); ?></p></div>
					<?php endif; ?>
					<p>
						<label for="lsp-license-key"><strong>License key</strong></label><br />
						<input type="text" id="lsp-license-key" class="regular-text" placeholder="LSP-XXXX-XXXX-XXXX-XXXX" autocomplete="off" />
					</p>
					<p>
						<button type="button" class="button button-primary" id="lsp-license-activate"><?php esc_html_e( 'Activate', 'localbusiness-schema' ); ?></button>
						<a href="<?php echo esc_url( LSP_PRO_URL ); ?>" target="_blank" rel="noopener" class="button button-secondary"><?php esc_html_e( 'Get a license', 'localbusiness-schema' ); ?> &rarr;</a>
					</p>
				</div>
			<?php endif; ?>
		</div>

		<script>
		(function(){
			var nonce = <?php echo wp_json_encode( wp_create_nonce( 'lsp_license' ) ); ?>;
			var ajax  = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
			document.addEventListener('click', function(e){
				if (e.target && e.target.id === 'lsp-license-activate') {
					var key = document.getElementById('lsp-license-key').value;
					if (!key) return;
					e.target.disabled = true; e.target.textContent = 'Activating…';
					var body = new URLSearchParams({ action: 'lsp_license_save', nonce: nonce, license_key: key });
					fetch(ajax, { method:'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: body })
						.then(function(r){ return r.json(); })
						.then(function(r){
							if (r && r.success) { window.location.reload(); }
							else { e.target.disabled = false; e.target.textContent = 'Activate'; alert((r && r.data && r.data.message) || 'Activation failed.'); }
						})
						.catch(function(){ e.target.disabled = false; e.target.textContent = 'Activate'; alert('Network error.'); });
				}
				if (e.target && e.target.id === 'lsp-license-remove') {
					if (!confirm('Deactivate this site from your license?')) return;
					e.target.disabled = true;
					var body = new URLSearchParams({ action: 'lsp_license_remove', nonce: nonce });
					fetch(ajax, { method:'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'}, body: body }).finally(function(){ window.location.reload(); });
				}
			});
		})();
		</script>
		<?php
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'localbusiness-schema' ) );
		}
		$s = wp_parse_args( get_option( 'lsp_settings', array() ), array(
			'primary_location_id' => 0,
			'inject_on_home'      => 1,
			'inject_on_singular'  => 1,
			'coexistence_mode'    => 'alongside',
		) );
		$locations = LSP_Location::all_ids();
		?>
		<div class="wrap lsp-wrap">
			<h1><?php esc_html_e( 'LocalBusiness Schema Pro — Settings', 'localbusiness-schema' ); ?></h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'lsp_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="lsp_primary_location_id"><?php esc_html_e( 'Primary location', 'localbusiness-schema' ); ?></label></th>
						<td>
							<select name="lsp_settings[primary_location_id]" id="lsp_primary_location_id">
								<option value="0">— <?php esc_html_e( 'Select a location', 'localbusiness-schema' ); ?> —</option>
								<?php foreach ( $locations as $lid ) : ?>
									<option value="<?php echo (int) $lid; ?>" <?php selected( (int) $s['primary_location_id'], (int) $lid ); ?>>
										<?php echo esc_html( get_the_title( $lid ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'The location whose schema is emitted on the homepage.', 'localbusiness-schema' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Auto-inject', 'localbusiness-schema' ); ?></th>
						<td>
							<label><input type="checkbox" name="lsp_settings[inject_on_home]" value="1" <?php checked( $s['inject_on_home'] ); ?> /> <?php esc_html_e( 'Homepage', 'localbusiness-schema' ); ?></label><br />
							<label><input type="checkbox" name="lsp_settings[inject_on_singular]" value="1" <?php checked( $s['inject_on_singular'] ); ?> /> <?php esc_html_e( 'Singular posts and pages (with optional per-post location override)', 'localbusiness-schema' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="lsp_coexistence_mode"><?php esc_html_e( 'When Yoast / Rank Math / AIOSEO Local is active', 'localbusiness-schema' ); ?></label></th>
						<td>
							<select name="lsp_settings[coexistence_mode]" id="lsp_coexistence_mode">
								<option value="alongside" <?php selected( $s['coexistence_mode'], 'alongside' ); ?>><?php esc_html_e( 'Emit alongside (@id-namespaced, safe)', 'localbusiness-schema' ); ?></option>
								<option value="defer_if_present" <?php selected( $s['coexistence_mode'], 'defer_if_present' ); ?>><?php esc_html_e( 'Defer — do not emit if incumbent is active', 'localbusiness-schema' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Alongside is the default and safe: our @id namespace never collides with the other plugin\'s.', 'localbusiness-schema' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save Settings', 'localbusiness-schema' ) ); ?>
			</form>
		</div>
		<?php
	}

	public function maybe_cap_notice() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-' . LSP_CPT_LOCATION !== $screen->id ) return;
		$license = new LSP_License();
		if ( $license->is_pro() ) return;
		$total = wp_count_posts( LSP_CPT_LOCATION );
		$active = (int) ( ( $total->publish ?? 0 ) + ( $total->draft ?? 0 ) );
		if ( $active <= self::FREE_LOCATION_CAP ) return;
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Free tier limits Locations to 1. Extra locations save as drafts and are ignored until you upgrade to Pro.', 'localbusiness-schema' );
		echo ' <a href="' . esc_url( LSP_PRO_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Get Pro', 'localbusiness-schema' ) . ' →</a>';
		echo '</p></div>';
	}

	/**
	 * If we're on free tier and this Location would push us over the cap when
	 * published, force it to draft.
	 *
	 * Registered from the main plugin file rather than this constructor, and
	 * static so it does not need an LSP_Admin instance. LSP_Admin is only
	 * constructed under is_admin(), so hanging this filter off it meant the cap
	 * was a wp-admin guard rather than an enforced limit: publishing a Location
	 * over REST or WP-CLI bypassed it entirely.
	 */
	public static function enforce_free_cap( $data, $postarr ) {
		if ( LSP_CPT_LOCATION !== ( $data['post_type'] ?? '' ) ) return $data;
		if ( 'publish' !== ( $data['post_status'] ?? '' ) ) return $data;
		$license = new LSP_License();
		if ( $license->is_pro() ) return $data;
		$this_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		$q = new WP_Query( array(
			'post_type'      => LSP_CPT_LOCATION,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'post__not_in'   => $this_id ? array( $this_id ) : array(),
		) );
		if ( count( $q->posts ) >= self::FREE_LOCATION_CAP ) {
			$data['post_status'] = 'draft';
		}
		return $data;
	}

	public function register_override_metabox() {
		foreach ( array( 'post', 'page' ) as $pt ) {
			add_meta_box(
				'lsp_location_override',
				__( 'LocalBusiness Schema', 'localbusiness-schema' ),
				array( $this, 'render_override_metabox' ),
				$pt,
				'side',
				'default'
			);
		}
	}

	public function render_override_metabox( $post ) {
		wp_nonce_field( 'lsp_override_nonce', 'lsp_override_nonce' );
		$current = (int) get_post_meta( $post->ID, '_lsp_location_id', true );
		$locations = LSP_Location::all_ids();
		?>
		<label for="lsp_location_id"><?php esc_html_e( 'Emit location for this post', 'localbusiness-schema' ); ?></label>
		<select name="lsp_location_id" id="lsp_location_id" style="width:100%;margin-top:6px;">
			<option value="0"><?php esc_html_e( 'Use primary (from settings)', 'localbusiness-schema' ); ?></option>
			<?php foreach ( $locations as $lid ) : ?>
				<option value="<?php echo (int) $lid; ?>" <?php selected( $current, (int) $lid ); ?>>
					<?php echo esc_html( get_the_title( $lid ) ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function save_override_metabox( $post_id, $post ) {
		if ( ! isset( $_POST['lsp_override_nonce'] ) || ! wp_verify_nonce( $_POST['lsp_override_nonce'], 'lsp_override_nonce' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;
		if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) return;
		$val = isset( $_POST['lsp_location_id'] ) ? (int) $_POST['lsp_location_id'] : 0;
		if ( $val > 0 ) update_post_meta( $post_id, '_lsp_location_id', $val );
		else delete_post_meta( $post_id, '_lsp_location_id' );
	}

	// ── AJAX ────────────────────────────────────────────────────────

	public function ajax_license_save() {
		check_ajax_referer( 'lsp_license', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		$key = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		if ( empty( $key ) ) wp_send_json_error( array( 'message' => 'License key required' ), 400 );
		$state = ( new LSP_License() )->activate( $key );
		if ( 'active' === $state['status'] ) {
			// Absent in the WordPress.org build, which ships no self-hosted updater.
			if ( class_exists( 'LSP_Updater' ) ) ( new LSP_Updater() )->bust_cache();
			wp_send_json_success( array( 'state' => $state, 'message' => 'License activated.' ) );
		}
		wp_send_json_error( array( 'state' => $state, 'message' => ! empty( $state['message'] ) ? $state['message'] : 'Activation failed.' ), 200 );
	}

	public function ajax_license_remove() {
		check_ajax_referer( 'lsp_license', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
		}
		( new LSP_License() )->deactivate();
		if ( class_exists( 'LSP_Updater' ) ) ( new LSP_Updater() )->bust_cache();
		wp_send_json_success( array( 'message' => 'Site deactivated.' ) );
	}
}
