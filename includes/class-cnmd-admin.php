<?php
/**
 * "CN Directory" admin page.
 *
 * @package ChamberNationMemberDirectory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings screen.
 */
class CNMD_Admin {

	/**
	 * Menu slug.
	 */
	const PAGE = 'cn-directory';

	/**
	 * Settings group.
	 */
	const GROUP = 'cnmd_settings_group';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_post_cnmd_refresh', array( __CLASS__, 'handle_refresh' ) );
		add_action( 'update_option_' . CNMD_Settings::OPTION, array( __CLASS__, 'after_save' ), 10, 2 );
		add_action( 'add_option_' . CNMD_Settings::OPTION, array( __CLASS__, 'after_add' ), 10, 2 );
		add_filter( 'plugin_action_links_' . plugin_basename( CNMD_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Top-level menu.
	 */
	public static function add_menu() {
		add_menu_page(
			__( 'CN Directory', 'chamber-nation-member-directory' ),
			__( 'CN Directory', 'chamber-nation-member-directory' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' ),
			'dashicons-store',
			31
		);
	}

	/**
	 * Register the option.
	 */
	public static function register_setting() {
		register_setting(
			self::GROUP,
			CNMD_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => CNMD_Settings::defaults(),
			)
		);
	}

	/**
	 * Sanitize, auto-detecting the directory page when none is chosen.
	 *
	 * @param mixed $input Input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$clean = CNMD_Settings::sanitize( $input );
		if ( ! $clean['directory_page'] ) {
			$clean['directory_page'] = CNMD_Plugin::find_directory_page();
		}
		return $clean;
	}

	/**
	 * First save.
	 *
	 * @param string $option Option.
	 * @param array  $value  Value.
	 */
	public static function after_add( $option, $value ) {
		self::after_save( array(), $value );
	}

	/**
	 * React to changes: refresh data when the source changes, re-flush
	 * rewrite rules when member URLs change, and clear llms.txt.
	 *
	 * @param mixed $old Old value.
	 * @param mixed $new New value.
	 */
	public static function after_save( $old, $new ) {
		$old = wp_parse_args( is_array( $old ) ? $old : array(), CNMD_Settings::defaults() );
		$new = wp_parse_args( is_array( $new ) ? $new : array(), CNMD_Settings::defaults() );

		if ( (int) $old['directory_page'] !== (int) $new['directory_page'] || (int) $old['member_pages'] !== (int) $new['member_pages'] ) {
			update_option( 'cnmd_flush_rules', 1, false );
		}
		if ( '' !== $new['org_id'] && ! CNMD_Settings::same_source( $old, $new ) ) {
			CNMD_Store::refresh( CNMD_Settings::sanitize( $new ) );
		}
		CN_Llms_Txt::flush();
	}

	/**
	 * Media picker for the default image.
	 *
	 * @param string $hook Hook.
	 */
	public static function enqueue( $hook ) {
		if ( 'toplevel_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'cnmd-admin', CNMD_URL . 'assets/js/admin.js', array( 'jquery' ), CNMD_Plugin::asset_version( 'assets/js/admin.js' ), true );
		wp_localize_script(
			'cnmd-admin',
			'cnmdAdmin',
			array(
				'chooseTitle'  => __( 'Choose default image', 'chamber-nation-member-directory' ),
				'chooseButton' => __( 'Use this image', 'chamber-nation-member-directory' ),
			)
		);
	}

	/**
	 * Plugins screen link.
	 *
	 * @param string[] $links Links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'chamber-nation-member-directory' ) . '</a>' );
		return $links;
	}

	/**
	 * "Refresh now" handler.
	 */
	public static function handle_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'chamber-nation-member-directory' ) );
		}
		check_admin_referer( 'cnmd_refresh' );

		$settings = CNMD_Settings::sanitize( CNMD_Settings::get() );
		delete_transient( 'cnmd_lock_' . CNMD_Store::key( $settings ) );
		$result = CNMD_Store::refresh( $settings );

		// Clear search and category caches too.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Bulk cache clear on explicit admin action.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_cnmd_q_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_cnmd_q_' ) . '%',
				$wpdb->esc_like( '_transient_cnmd_cats_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_cnmd_cats_' ) . '%'
			)
		);

		wp_safe_redirect( add_query_arg( 'cnmd_refreshed', is_wp_error( $result ) ? 'error' : 'ok', admin_url( 'admin.php?page=' . self::PAGE ) ) );
		exit;
	}

	/**
	 * Render the page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s   = CNMD_Settings::get();
		$api = new CNMD_API( CNMD_Settings::sanitize( $s ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only flag after a nonce-checked redirect.
		$refreshed = isset( $_GET['cnmd_refreshed'] ) ? sanitize_key( wp_unslash( $_GET['cnmd_refreshed'] ) ) : '';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'CN Directory', 'chamber-nation-member-directory' ); ?></h1>

			<?php if ( 'ok' === $refreshed ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Member data refreshed.', 'chamber-nation-member-directory' ); ?></p></div>
			<?php elseif ( 'error' === $refreshed ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Refresh failed. See the status box for details.', 'chamber-nation-member-directory' ); ?></p></div>
			<?php endif; ?>

			<div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap">
				<form action="options.php" method="post" style="flex:1 1 640px;min-width:0">
					<?php settings_fields( self::GROUP ); ?>

					<h2><?php esc_html_e( 'Connection', 'chamber-nation-member-directory' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						self::text_row( $s, 'org_id', __( 'Organization ID', 'chamber-nation-member-directory' ), __( 'Your chamber\'s organization code, e.g. HEND.', 'chamber-nation-member-directory' ) );
						self::select_row( $s, 'server', __( 'Platform', 'chamber-nation-member-directory' ), CNMD_Settings::servers() );
						self::select_row( $s, 'refresh_minutes', __( 'Refresh member data', 'chamber-nation-member-directory' ), CNMD_Settings::refresh_choices(), __( 'How often the full member list is re-downloaded in the background. Keyword, category and location searches always query the API live (cached 15 minutes).', 'chamber-nation-member-directory' ) );
						?>
					</table>

					<h2><?php esc_html_e( 'Pages & SEO', 'chamber-nation-member-directory' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="cnmd_directory_page"><?php esc_html_e( 'Directory page', 'chamber-nation-member-directory' ); ?></label></th>
							<td>
								<?php
								wp_dropdown_pages(
									array(
										'name'              => esc_attr( CNMD_Settings::OPTION . '[directory_page]' ),
										'id'                => 'cnmd_directory_page',
										'selected'          => (int) $s['directory_page'],
										'show_option_none'  => esc_html__( '— Detect automatically —', 'chamber-nation-member-directory' ),
										'option_none_value' => '0',
									)
								);
								?>
								<p class="description"><?php esc_html_e( 'The page with the [cn_directory] shortcode. Member pages live under it, e.g. /directory/business-name/.', 'chamber-nation-member-directory' ); ?></p>
							</td>
						</tr>
						<?php
						self::checkbox_row( $s, 'member_pages', __( 'Give each member its own page on this site (recommended for SEO)', 'chamber-nation-member-directory' ) );
						self::checkbox_row( $s, 'sitemap', __( 'Add member pages to an XML sitemap', 'chamber-nation-member-directory' ) );
						self::checkbox_row( $s, 'llms_txt', __( 'Add the directory to /llms.txt for AI assistants', 'chamber-nation-member-directory' ) );
						self::text_row( $s, 'default_country', __( 'Country code', 'chamber-nation-member-directory' ), __( 'Two-letter country for structured data, e.g. US.', 'chamber-nation-member-directory' ) );
						?>
					</table>

					<h2><?php esc_html_e( 'Layout', 'chamber-nation-member-directory' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						self::select_row(
							$s,
							'initial_view',
							__( 'Initial view', 'chamber-nation-member-directory' ),
							array(
								'list'       => __( 'Member list with search sidebar', 'chamber-nation-member-directory' ),
								'categories' => __( 'Search bar and category grid', 'chamber-nation-member-directory' ),
							)
						);
						self::select_row(
							$s,
							'sorting',
							__( 'Sort members', 'chamber-nation-member-directory' ),
							array(
								'random'         => __( 'Random (reshuffled daily)', 'chamber-nation-member-directory' ),
								'alphabetically' => __( 'Alphabetically', 'chamber-nation-member-directory' ),
							)
						);
						self::number_row( $s, 'per_page', __( 'Members per page', 'chamber-nation-member-directory' ), 3, 60 );
						self::select_row(
							$s,
							'directory_type',
							__( 'Directory type', 'chamber-nation-member-directory' ),
							array(
								'all'    => __( 'All categories', 'chamber-nation-member-directory' ),
								'single' => __( 'Single category', 'chamber-nation-member-directory' ),
							)
						);
						self::category_row( $s, $api );
						self::select_row(
							$s,
							'about_field',
							__( 'Description', 'chamber-nation-member-directory' ),
							array(
								'yes'  => __( 'About Us', 'chamber-nation-member-directory' ),
								'no'   => __( 'Profile', 'chamber-nation-member-directory' ),
								'none' => __( 'Don\'t show', 'chamber-nation-member-directory' ),
							)
						);
						self::image_row( $s );
						self::select_row(
							$s,
							'bootstrap',
							__( 'Bootstrap CSS', 'chamber-nation-member-directory' ),
							array(
								'auto'   => __( 'Auto: load only if the theme doesn\'t already', 'chamber-nation-member-directory' ),
								'always' => __( 'Always load', 'chamber-nation-member-directory' ),
								'never'  => __( 'Never load (theme provides Bootstrap)', 'chamber-nation-member-directory' ),
							),
							__( 'The directory uses Bootstrap 5 styles, loaded from docu.team when needed.', 'chamber-nation-member-directory' )
						);
						?>
					</table>

					<h2><?php esc_html_e( 'Hide fields', 'chamber-nation-member-directory' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						$hide = array(
							'hide_primary_category' => __( 'Category', 'chamber-nation-member-directory' ),
							'hide_name_position'    => __( 'Representative name & position', 'chamber-nation-member-directory' ),
							'hide_email'            => __( 'Email', 'chamber-nation-member-directory' ),
							'hide_phone_h'          => __( 'Phone', 'chamber-nation-member-directory' ),
							'hide_address'          => __( 'Address', 'chamber-nation-member-directory' ),
							'hide_website'          => __( 'Website', 'chamber-nation-member-directory' ),
							'hide_contact_form'     => __( 'Contact button', 'chamber-nation-member-directory' ),
							'hide_branches'         => __( 'Military branches', 'chamber-nation-member-directory' ),
							'hide_designations'     => __( 'Designations', 'chamber-nation-member-directory' ),
						);
						foreach ( $hide as $key => $label ) {
							/* translators: %s: field name */
							self::checkbox_row( $s, $key, sprintf( __( 'Hide %s', 'chamber-nation-member-directory' ), $label ) );
						}
						?>
					</table>

					<h2><?php esc_html_e( 'Search', 'chamber-nation-member-directory' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						self::checkbox_row( $s, 'disable_radius_filter', __( 'Hide the City / ZIP / distance search', 'chamber-nation-member-directory' ) );
						self::checkbox_row( $s, 'geolocate', __( 'Pre-fill City and ZIP from the visitor\'s location (asks for permission)', 'chamber-nation-member-directory' ) );
						self::checkbox_row( $s, 'hide_alpha_until_filtered', __( 'Hide the A–Z bar until a search or filter is used', 'chamber-nation-member-directory' ) );
						self::text_row( $s, 'things_to_do', __( 'Things to do filter', 'chamber-nation-member-directory' ), __( 'Optional default "things to do" filter sent to the API.', 'chamber-nation-member-directory' ) );
						self::text_row( $s, 'exclude', __( 'Exclude member types', 'chamber-nation-member-directory' ), __( 'Comma-separated; members whose type contains any of these are hidden, e.g. Trade, Associate.', 'chamber-nation-member-directory' ) );
						?>
					</table>

					<h2><?php esc_html_e( 'External profile links', 'chamber-nation-member-directory' ); ?></h2>
					<table class="form-table" role="presentation">
						<?php
						self::select_row(
							$s,
							'landing_url',
							__( '"View Full Profile" goes to', 'chamber-nation-member-directory' ),
							array(
								'mlp'      => __( 'Member landing page (default)', 'chamber-nation-member-directory' ),
								'docuteam' => __( 'DocuTeam landing page', 'chamber-nation-member-directory' ),
							)
						);
						self::text_row( $s, 'docuteam_cname', __( 'DocuTeam domain', 'chamber-nation-member-directory' ), __( 'Used for DocuTeam landing pages, e.g. https://docu.team.', 'chamber-nation-member-directory' ), 'url' );
						?>
					</table>

					<?php submit_button(); ?>
				</form>

				<div style="flex:0 1 340px">
					<?php self::render_sidebar( $s ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Sidebar.
	 *
	 * @param array $s Settings.
	 */
	private static function render_sidebar( array $s ) {
		$settings = CNMD_Settings::sanitize( $s );
		$status   = CNMD_Store::status( $settings );
		$snap     = '' !== $s['org_id'] ? get_option( 'cnmd_snap_' . CNMD_Store::key( $settings ) ) : null;
		?>
		<div class="card">
			<h2 class="title"><?php esc_html_e( 'Add the directory', 'chamber-nation-member-directory' ); ?></h2>
			<p><?php esc_html_e( 'Place this shortcode on a page (a Shortcode block works in the block editor):', 'chamber-nation-member-directory' ); ?></p>
			<p><code>[<?php echo esc_html( CNMD_Plugin::SHORTCODE ); ?>]</code></p>
			<p><?php esc_html_e( 'Any setting can be overridden per page, for example:', 'chamber-nation-member-directory' ); ?></p>
			<p><code>[<?php echo esc_html( CNMD_Plugin::SHORTCODE ); ?> directory_type="single" single_cat_id="59879"]</code></p>
		</div>

		<div class="card">
			<h2 class="title"><?php esc_html_e( 'Member data', 'chamber-nation-member-directory' ); ?></h2>
			<?php if ( '' === $s['org_id'] ) : ?>
				<p><?php esc_html_e( 'Enter your Organization ID and save to connect.', 'chamber-nation-member-directory' ); ?></p>
			<?php else : ?>
				<?php if ( is_array( $snap ) ) : ?>
					<p style="color:#008a20">
						<?php
						printf(
							/* translators: 1: number of members, 2: time ago */
							esc_html__( '%1$s members, updated %2$s ago.', 'chamber-nation-member-directory' ),
							esc_html( number_format_i18n( count( $snap['members'] ) ) ),
							esc_html( human_time_diff( (int) $snap['time'] ) )
						);
						?>
					</p>
				<?php else : ?>
					<p><?php esc_html_e( 'No member data downloaded yet.', 'chamber-nation-member-directory' ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $status['error'] ) ) : ?>
					<p style="color:#b32d2e">
						<?php
						/* translators: %s: error message */
						printf( esc_html__( 'Last refresh failed: %s', 'chamber-nation-member-directory' ), esc_html( $status['error'] ) );
						?>
					</p>
				<?php endif; ?>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<input type="hidden" name="action" value="cnmd_refresh" />
					<?php wp_nonce_field( 'cnmd_refresh' ); ?>
					<?php submit_button( __( 'Refresh now', 'chamber-nation-member-directory' ), 'secondary', 'submit', false ); ?>
				</form>
				<p class="description">
					<?php esc_html_e( 'Refreshes run in the background with WP-Cron, which needs site visits. On low-traffic sites, add a server cron job that loads wp-cron.php every 15 minutes.', 'chamber-nation-member-directory' ); ?>
				</p>
			<?php endif; ?>
		</div>

		<div class="card">
			<h2 class="title"><?php esc_html_e( 'For search engines & AI', 'chamber-nation-member-directory' ); ?></h2>
			<ul style="list-style:disc;padding-left:1.2em">
				<?php if ( CNMD_Sitemap::enabled() ) : ?>
					<li><a href="<?php echo esc_url( CNMD_Sitemap::url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Member sitemap', 'chamber-nation-member-directory' ); ?></a></li>
				<?php endif; ?>
				<?php if ( $s['llms_txt'] ) : ?>
					<li><a href="<?php echo esc_url( CN_Llms_Txt::url( 'index' ) ); ?>" target="_blank" rel="noopener">llms.txt</a> · <a href="<?php echo esc_url( CN_Llms_Txt::url( 'full' ) ); ?>" target="_blank" rel="noopener">llms-full.txt</a></li>
				<?php endif; ?>
			</ul>
		</div>
		<?php
	}

	/**
	 * Field name.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function name( $key ) {
		return CNMD_Settings::OPTION . '[' . $key . ']';
	}

	/**
	 * Text row.
	 *
	 * @param array  $s     Settings.
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $help  Help.
	 * @param string $type  Type.
	 */
	private static function text_row( array $s, $key, $label, $help = '', $type = 'text' ) {
		?>
		<tr>
			<th scope="row"><label for="cnmd_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="<?php echo esc_attr( $type ); ?>" class="regular-text" id="cnmd_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::name( $key ) ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" />
				<?php if ( $help ) : ?>
					<p class="description"><?php echo esc_html( $help ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Number row.
	 *
	 * @param array  $s     Settings.
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param int    $min   Min.
	 * @param int    $max   Max.
	 */
	private static function number_row( array $s, $key, $label, $min, $max ) {
		?>
		<tr>
			<th scope="row"><label for="cnmd_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td><input type="number" class="small-text" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" id="cnmd_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::name( $key ) ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" /></td>
		</tr>
		<?php
	}

	/**
	 * Select row.
	 *
	 * @param array  $s       Settings.
	 * @param string $key     Key.
	 * @param string $label   Label.
	 * @param array  $options Options.
	 * @param string $help    Help.
	 */
	private static function select_row( array $s, $key, $label, array $options, $help = '' ) {
		?>
		<tr>
			<th scope="row"><label for="cnmd_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="cnmd_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::name( $key ) ); ?>">
					<?php foreach ( $options as $value => $text ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( (string) $s[ $key ], (string) $value ); ?>><?php echo esc_html( $text ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( $help ) : ?>
					<p class="description"><?php echo esc_html( $help ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Checkbox row.
	 *
	 * @param array  $s     Settings.
	 * @param string $key   Key.
	 * @param string $label Label.
	 */
	private static function checkbox_row( array $s, $key, $label ) {
		?>
		<tr>
			<th scope="row"></th>
			<td><label><input type="checkbox" name="<?php echo esc_attr( self::name( $key ) ); ?>" value="1" <?php checked( (int) $s[ $key ], 1 ); ?> /> <?php echo esc_html( $label ); ?></label></td>
		</tr>
		<?php
	}

	/**
	 * Single-category select.
	 *
	 * @param array    $s   Settings.
	 * @param CNMD_API $api API.
	 */
	private static function category_row( array $s, CNMD_API $api ) {
		$categories = '' !== $s['org_id'] ? $api->categories() : new WP_Error( 'cnmd_no_org', '' );
		?>
		<tr>
			<th scope="row"><label for="cnmd_single_cat_id"><?php esc_html_e( 'Single category', 'chamber-nation-member-directory' ); ?></label></th>
			<td>
				<?php if ( is_wp_error( $categories ) ) : ?>
					<input type="text" class="small-text" id="cnmd_single_cat_id" name="<?php echo esc_attr( self::name( 'single_cat_id' ) ); ?>" value="<?php echo esc_attr( $s['single_cat_id'] ); ?>" />
				<?php else : ?>
					<select id="cnmd_single_cat_id" name="<?php echo esc_attr( self::name( 'single_cat_id' ) ); ?>">
						<option value=""><?php esc_html_e( '— None —', 'chamber-nation-member-directory' ); ?></option>
						<?php foreach ( $categories as $id => $name ) : ?>
							<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $s['single_cat_id'], (string) $id ); ?>><?php echo esc_html( $name ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'Used when Directory type is "Single category".', 'chamber-nation-member-directory' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Default image picker.
	 *
	 * @param array $s Settings.
	 */
	private static function image_row( array $s ) {
		?>
		<tr>
			<th scope="row"><label for="cnmd_default_image"><?php esc_html_e( 'Default logo', 'chamber-nation-member-directory' ); ?></label></th>
			<td>
				<input type="url" class="regular-text" id="cnmd_default_image" name="<?php echo esc_attr( self::name( 'default_image' ) ); ?>" value="<?php echo esc_attr( $s['default_image'] ); ?>" />
				<button type="button" class="button" id="cnmd_default_image_pick"><?php esc_html_e( 'Choose image', 'chamber-nation-member-directory' ); ?></button>
				<p class="description"><?php esc_html_e( 'Shown for members without a logo. Leave blank to show no image.', 'chamber-nation-member-directory' ); ?></p>
			</td>
		</tr>
		<?php
	}
}
