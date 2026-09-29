<?php
/**
 * Directory list view (search sidebar + results + pagination).
 *
 * Override by copying to {theme}/cn-directory/directory.php.
 *
 * @package ChamberNationMemberDirectory
 *
 * @var string         $uid        Instance id.
 * @var array          $settings   Settings.
 * @var array          $state      Request state.
 * @var array          $categories ID => name.
 * @var CNMD_Directory $directory  Helpers.
 * @var array[]        $items      Each: array( member, url ).
 * @var int            $total      Matching members.
 * @var int            $pages      Total pages.
 * @var string         $error      Error message.
 * @var array[]        $chips      Active filter chips.
 * @var array[]        $pagination Pagination links.
 * @var bool           $show_alpha Show the A–Z bar.
 * @var string         $back_url   "Back to Main" URL or ''.
 */

defined( 'ABSPATH' ) || exit;

$cnmd_first = $total ? ( $state['page'] - 1 ) * $settings['per_page'] + 1 : 0;
$cnmd_last  = min( $total, $state['page'] * $settings['per_page'] );

ob_start();
?>
<h2 class="visually-hidden"><?php echo esc_html( $directory->list_title( $categories ) ); ?></h2>
<div class="h-100 cnmd-results">
	<?php if ( $error ) : ?>
		<div class="card"><div class="card-body text-center p-5"><?php echo esc_html( $error ); ?></div></div>
	<?php elseif ( ! $items ) : ?>
		<div class="card"><div class="card-body text-center p-5"><?php esc_html_e( 'No Businesses Found', 'chamber-nation-member-directory' ); ?></div></div>
	<?php else : ?>
		<?php
		foreach ( $items as $cnmd_item ) {
			$cnmd_card = CNMD_Directory::load_template(
				'member-card',
				array(
					'member'   => $cnmd_item['member'],
					'url'      => $cnmd_item['url'],
					'settings' => $settings,
				)
			);
			echo $cnmd_card; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The card template escapes its own output.
		}
		?>
	<?php endif; ?>
</div>
<div class="row align-items-center mt-2">
	<div class="col-md-6 cnmd-count">
		<?php if ( $total ) : ?>
			<small>
				<?php
				/* translators: 1: first shown, 2: last shown, 3: total */
				echo esc_html( sprintf( __( 'Showing %1$s–%2$s of %3$s members', 'chamber-nation-member-directory' ), number_format_i18n( $cnmd_first ), number_format_i18n( $cnmd_last ), number_format_i18n( $total ) ) );
				?>
			</small>
		<?php endif; ?>
	</div>
	<div class="col-md-6">
		<?php if ( $pagination ) : ?>
			<nav aria-label="<?php esc_attr_e( 'Directory pages', 'chamber-nation-member-directory' ); ?>">
				<ul class="pagination justify-content-end">
					<?php foreach ( $pagination as $cnmd_link ) : ?>
						<li class="page-item<?php echo $cnmd_link['current'] ? ' active' : ''; ?><?php echo $cnmd_link['disabled'] ? ' disabled' : ''; ?>"<?php echo $cnmd_link['current'] ? ' aria-current="page"' : ''; ?>>
							<?php if ( $cnmd_link['url'] ) : ?>
								<a class="page-link" href="<?php echo esc_url( $cnmd_link['url'] ); ?>"<?php echo $cnmd_link['rel'] ? ' rel="' . esc_attr( $cnmd_link['rel'] ) . '"' : ''; ?>><?php echo esc_html( $cnmd_link['label'] ); ?></a>
							<?php else : ?>
								<span class="page-link"><?php echo esc_html( $cnmd_link['label'] ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
	</div>
</div>
<?php
$cnmd_results = ob_get_clean();
?>
<div id="<?php echo esc_attr( $uid ); ?>" class="cnmd-root mms_directory_wrapper" data-geolocate="<?php echo $settings['geolocate'] ? 'yes' : 'no'; ?>">

	<?php if ( $back_url ) : ?>
		<div class="text-end mb-2"><a class="btn btn-primary btn-sm text-white" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Back to Main', 'chamber-nation-member-directory' ); ?></a></div>
	<?php endif; ?>

	<?php if ( $state['locked'] ) : ?>
		<?php echo $cnmd_results; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>
	<?php else : ?>
		<?php if ( $chips ) : ?>
			<div class="p-2 text-end search_content mb-2">
				<small><?php esc_html_e( 'You searched:', 'chamber-nation-member-directory' ); ?></small>
				<?php foreach ( $chips as $cnmd_chip ) : ?>
					<a class="badge border text-bg-light me-2" href="<?php echo esc_url( $cnmd_chip['url'] ); ?>" rel="nofollow">
						<?php echo esc_html( $cnmd_chip['label'] ); ?> <sup aria-hidden="true">×</sup><span class="visually-hidden"><?php esc_html_e( '(remove)', 'chamber-nation-member-directory' ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="row">
			<div class="col-md-4">
				<div class="card mb-2">
					<div class="card-body">
						<form class="cnmd-filter" method="get" action="<?php echo esc_url( $directory->form_action() ); ?>" role="search">
							<label class="visually-hidden" for="<?php echo esc_attr( $uid ); ?>-search"><?php esc_html_e( 'Business name or keyword', 'chamber-nation-member-directory' ); ?></label>
							<input id="<?php echo esc_attr( $uid ); ?>-search" class="form-control mb-2" placeholder="<?php esc_attr_e( 'Business Name or Keyword', 'chamber-nation-member-directory' ); ?>" type="text" maxlength="50" name="<?php echo esc_attr( CNMD_Directory::QV['search'] ); ?>" value="<?php echo esc_attr( $state['search'] ); ?>">

							<label class="visually-hidden" for="<?php echo esc_attr( $uid ); ?>-cat"><?php esc_html_e( 'Category', 'chamber-nation-member-directory' ); ?></label>
							<select id="<?php echo esc_attr( $uid ); ?>-cat" class="form-select mb-3" name="<?php echo esc_attr( CNMD_Directory::QV['cat'] ); ?>">
								<option value=""><?php esc_html_e( 'Show All Categories', 'chamber-nation-member-directory' ); ?></option>
								<?php foreach ( $categories as $cnmd_id => $cnmd_name ) : ?>
									<option value="<?php echo esc_attr( $cnmd_id ); ?>" <?php selected( $state['cat'], (string) $cnmd_id ); ?>><?php echo esc_html( $cnmd_name ); ?></option>
								<?php endforeach; ?>
							</select>

							<?php if ( ! $settings['disable_radius_filter'] ) : ?>
								<div class="mb-2">
									<label for="<?php echo esc_attr( $uid ); ?>-city" class="form-label"><?php esc_html_e( 'City', 'chamber-nation-member-directory' ); ?></label>
									<input type="text" id="<?php echo esc_attr( $uid ); ?>-city" class="form-control cnmd-geo-city" autocomplete="address-level2" name="<?php echo esc_attr( CNMD_Directory::QV['city'] ); ?>" value="<?php echo esc_attr( $state['city'] ); ?>">
								</div>
								<div class="mb-2">
									<label for="<?php echo esc_attr( $uid ); ?>-zip" class="form-label"><?php esc_html_e( 'ZIP', 'chamber-nation-member-directory' ); ?></label>
									<input type="text" id="<?php echo esc_attr( $uid ); ?>-zip" class="form-control cnmd-geo-zip" inputmode="numeric" autocomplete="postal-code" name="<?php echo esc_attr( CNMD_Directory::QV['zip'] ); ?>" value="<?php echo esc_attr( $state['zip'] ); ?>">
								</div>
								<div class="mb-3">
									<label for="<?php echo esc_attr( $uid ); ?>-dist" class="form-label"><?php esc_html_e( 'Distance (miles)', 'chamber-nation-member-directory' ); ?></label>
									<select id="<?php echo esc_attr( $uid ); ?>-dist" class="form-select" name="<?php echo esc_attr( CNMD_Directory::QV['dist'] ); ?>">
										<?php foreach ( CNMD_Directory::DISTANCES as $cnmd_d ) : ?>
											<option value="<?php echo esc_attr( $cnmd_d ); ?>" <?php selected( $state['dist'], $cnmd_d ); ?>><?php echo esc_html( $cnmd_d ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<button type="button" class="btn btn-light w-100 mb-2 cnmd-geo-btn" hidden><?php esc_html_e( 'Use my location', 'chamber-nation-member-directory' ); ?></button>
							<?php endif; ?>

							<?php if ( '' !== $state['ttd'] && $state['ttd'] !== $settings['things_to_do'] ) : ?>
								<input type="hidden" name="<?php echo esc_attr( CNMD_Directory::QV['ttd'] ); ?>" value="<?php echo esc_attr( $state['ttd'] ); ?>">
							<?php endif; ?>

							<input type="submit" value="<?php esc_attr_e( 'Search', 'chamber-nation-member-directory' ); ?>" class="w-100 btn btn-primary">
						</form>
					</div>
				</div>

				<?php if ( $show_alpha ) : ?>
					<div class="card">
						<div class="card-body">
							<nav aria-label="<?php esc_attr_e( 'Browse by first letter', 'chamber-nation-member-directory' ); ?>">
								<ul class="mt-2 pagination alphabet justify-content-center notranslate">
									<?php foreach ( $directory->alphabet() as $cnmd_a ) : ?>
										<li class="page-item<?php echo $cnmd_a['active'] ? ' active' : ''; ?>"><a href="<?php echo esc_url( $cnmd_a['url'] ); ?>" class="alpha text-uppercase page-link<?php echo $cnmd_a['active'] ? ' active' : ''; ?>"<?php echo $cnmd_a['active'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $cnmd_a['label'] ); ?></a></li>
									<?php endforeach; ?>
								</ul>
							</nav>
						</div>
					</div>
				<?php endif; ?>
			</div>

			<div class="col-md-8">
				<?php echo $cnmd_results; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>
			</div>
		</div>
	<?php endif; ?>

	<dialog class="cnmd-dialog" aria-labelledby="<?php echo esc_attr( $uid ); ?>-about-title" data-about-label="<?php esc_attr_e( 'About', 'chamber-nation-member-directory' ); ?>">
		<div class="cnmd-dialog__head">
			<h5 class="cnmd-dialog__title" id="<?php echo esc_attr( $uid ); ?>-about-title"></h5>
			<button type="button" class="btn-close cnmd-dialog__close" aria-label="<?php esc_attr_e( 'Close', 'chamber-nation-member-directory' ); ?>"></button>
		</div>
		<div class="cnmd-dialog__body"></div>
		<div class="cnmd-dialog__foot">
			<button type="button" class="btn btn-secondary cnmd-dialog__close"><?php esc_html_e( 'Close', 'chamber-nation-member-directory' ); ?></button>
		</div>
	</dialog>
</div>
