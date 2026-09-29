<?php
/**
 * Landing view: horizontal search bar, A–Z and the category grid.
 *
 * Override by copying to {theme}/cn-directory/categories.php.
 *
 * @package ChamberNationMemberDirectory
 *
 * @var string         $uid        Instance id.
 * @var array          $settings   Settings.
 * @var array          $state      Request state.
 * @var array          $categories ID => name.
 * @var CNMD_Directory $directory  Helpers.
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="<?php echo esc_attr( $uid ); ?>" class="cnmd-root mms_directory_wrapper" data-geolocate="<?php echo $settings['geolocate'] ? 'yes' : 'no'; ?>">
	<div class="card">
		<div class="card-body">
			<form class="cnmd-filter" method="get" action="<?php echo esc_url( $directory->form_action() ); ?>" role="search">
				<div class="row g-2">
					<div class="col-lg-4">
						<label class="visually-hidden" for="<?php echo esc_attr( $uid ); ?>-search"><?php esc_html_e( 'Business name or keyword', 'chamber-nation-member-directory' ); ?></label>
						<input id="<?php echo esc_attr( $uid ); ?>-search" class="form-control bg-light border-0" placeholder="<?php esc_attr_e( 'Search for a Business Name or Keyword Here', 'chamber-nation-member-directory' ); ?>" type="text" maxlength="50" name="<?php echo esc_attr( CNMD_Directory::QV['search'] ); ?>" value="">
					</div>
					<?php if ( ! $settings['disable_radius_filter'] ) : ?>
						<div class="col-lg-2">
							<label class="visually-hidden" for="<?php echo esc_attr( $uid ); ?>-city"><?php esc_html_e( 'City', 'chamber-nation-member-directory' ); ?></label>
							<input class="form-control cnmd-geo-city" id="<?php echo esc_attr( $uid ); ?>-city" name="<?php echo esc_attr( CNMD_Directory::QV['city'] ); ?>" placeholder="<?php esc_attr_e( 'City', 'chamber-nation-member-directory' ); ?>" autocomplete="address-level2">
						</div>
						<div class="col-lg-1">
							<label class="visually-hidden" for="<?php echo esc_attr( $uid ); ?>-zip"><?php esc_html_e( 'ZIP', 'chamber-nation-member-directory' ); ?></label>
							<input class="form-control cnmd-geo-zip" id="<?php echo esc_attr( $uid ); ?>-zip" name="<?php echo esc_attr( CNMD_Directory::QV['zip'] ); ?>" placeholder="<?php esc_attr_e( 'ZIP', 'chamber-nation-member-directory' ); ?>" inputmode="numeric" autocomplete="postal-code">
						</div>
						<div class="col-lg-1">
							<label class="visually-hidden" for="<?php echo esc_attr( $uid ); ?>-dist"><?php esc_html_e( 'Distance (miles)', 'chamber-nation-member-directory' ); ?></label>
							<select id="<?php echo esc_attr( $uid ); ?>-dist" class="form-select" name="<?php echo esc_attr( CNMD_Directory::QV['dist'] ); ?>">
								<?php foreach ( CNMD_Directory::DISTANCES as $cnmd_d ) : ?>
									<option value="<?php echo esc_attr( $cnmd_d ); ?>" <?php selected( '75', $cnmd_d ); ?>><?php echo esc_html( $cnmd_d ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
						<div class="col-lg-1"><button type="button" class="btn btn-light w-100 cnmd-geo-btn" hidden><?php esc_html_e( 'Use my location', 'chamber-nation-member-directory' ); ?></button></div>
					<?php endif; ?>
					<div class="col-12 col-lg-3 ms-auto"><input type="submit" value="<?php esc_attr_e( 'Search', 'chamber-nation-member-directory' ); ?>" class="btn btn-primary w-100 h-100 m-0"></div>
				</div>
			</form>
		</div>
	</div>

	<?php if ( ! $settings['hide_alpha_until_filtered'] ) : ?>
		<nav aria-label="<?php esc_attr_e( 'Browse by first letter', 'chamber-nation-member-directory' ); ?>">
			<ul class="w-100 mt-2 pagination alphabet justify-content-center notranslate">
				<?php foreach ( $directory->alphabet() as $cnmd_a ) : ?>
					<li class="page-item"><a href="<?php echo esc_url( $cnmd_a['url'] ); ?>" class="alpha text-uppercase page-link"><?php echo esc_html( $cnmd_a['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>

	<?php if ( $categories ) : ?>
		<div class="pb-5"><span class="mt-5 divider-center"><?php esc_html_e( 'OR CHOOSE A CATEGORY BELOW', 'chamber-nation-member-directory' ); ?></span></div>
		<h2 class="visually-hidden"><?php esc_html_e( 'Member categories', 'chamber-nation-member-directory' ); ?></h2>
		<div class="cnmd-category-grid pt-2">
			<?php foreach ( $directory->category_groups( $categories ) as $cnmd_letter => $cnmd_group ) : ?>
				<div class="cnmd-category-group pb-3">
					<h3 class="h6 pb-1 border-bottom"><?php echo esc_html( $cnmd_letter ); ?></h3>
					<ul class="list-unstyled">
						<?php foreach ( $cnmd_group as $cnmd_cat ) : ?>
							<li><a href="<?php echo esc_url( $cnmd_cat['url'] ); ?>"><?php echo esc_html( $cnmd_cat['name'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
