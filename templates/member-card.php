<?php
/**
 * One member card in the list.
 *
 * Override by copying to {theme}/cn-directory/member-card.php.
 *
 * @package ChamberNationMemberDirectory
 *
 * @var array  $member   Member.
 * @var string $url      Member page URL on this site, or ''.
 * @var array  $settings Settings.
 */

defined( 'ABSPATH' ) || exit;

$cnmd_logo     = $member['logo'] ? $member['logo'] : $settings['default_image'];
$cnmd_about    = CNMD_Links::about( $member, $settings );
$cnmd_external = CNMD_Links::external_profile( $member, $settings );
$cnmd_view     = $url ? $url : $cnmd_external;
$cnmd_badges   = CNMD_Links::badges( $member, $settings );
$cnmd_address  = $settings['hide_address'] ? '' : CNMD_Links::address_line( $member );
$cnmd_show_br  = $member['branches'] && ! $settings['hide_branches'];
$cnmd_show_de  = $member['designations'] && ! $settings['hide_designations'];
$cnmd_has_btns = ( $member['phone'] && ! $settings['hide_phone_h'] ) || ( $member['website'] && ! $settings['hide_website'] ) || $cnmd_view;
?>
<article class="card member_card mb-3" id="m_<?php echo esc_attr( $member['id'] ); ?>">
	<div class="card-body">
		<div class="row text-center text-lg-start">
			<div class="col-lg-3 text-center logo_holder">
				<?php if ( $cnmd_logo ) : ?>
					<?php if ( $url ) : ?><a href="<?php echo esc_url( $url ); ?>" tabindex="-1" aria-hidden="true"><?php endif; ?>
					<img class="mb-2 mb-lg-0 member_logo" src="<?php echo esc_url( $cnmd_logo ); ?>" alt="<?php echo esc_attr( $member['logo'] ? $member['name'] : '' ); ?>" loading="lazy" decoding="async">
					<?php if ( $url ) : ?></a><?php endif; ?>
				<?php endif; ?>
			</div>

			<div class="col mb-2 mb-lg-0">
				<?php if ( $member['cats'] && ! $settings['hide_primary_category'] ) : ?>
					<div class="member_branch_designation d-flex align-items-start pb-2 mb-2 text-uppercase"><?php echo esc_html( $member['cats'][0] ); ?></div>
				<?php endif; ?>

				<h3 class="member_name">
					<?php if ( $url ) : ?>
						<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $member['name'] ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $member['name'] ); ?>
					<?php endif; ?>
				</h3>

				<?php if ( $cnmd_show_br || $cnmd_show_de ) : ?>
					<div class="member_branch_designation d-flex align-items-start pb-2 mb-2 border-bottom">
						<?php if ( $cnmd_show_br ) : ?>
							<div class="me-3"><?php echo esc_html( implode( ', ', $member['branches'] ) ); ?></div>
						<?php endif; ?>
						<?php if ( $cnmd_show_br && $cnmd_show_de ) : ?>
							<div class="me-3" aria-hidden="true">//</div>
						<?php endif; ?>
						<?php if ( $cnmd_show_de ) : ?>
							<div><?php echo esc_html( implode( ', ', $member['designations'] ) ); ?></div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $cnmd_about ) : ?>
					<div class="member_about">
						<?php if ( mb_strlen( $cnmd_about ) > 200 ) : ?>
							<small><?php echo esc_html( rtrim( mb_substr( str_replace( "\n", ' ', $cnmd_about ), 0, 200 ) ) ); ?>… <button type="button" class="read-more-link" data-name="<?php echo esc_attr( $member['name'] ); ?>"><?php esc_html_e( 'Read More', 'chamber-nation-member-directory' ); ?></button></small>
							<div class="cnmd-about-full" hidden><?php echo esc_html( $cnmd_about ); ?></div>
						<?php else : ?>
							<small><?php echo esc_html( $cnmd_about ); ?></small>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $member['rep'] && ! $settings['hide_name_position'] ) : ?>
					<div class="member_representative mt-3 mb-2 d-flex align-items-center w-100">
						<svg class="me-2" xmlns="http://www.w3.org/2000/svg" height="1em" viewBox="0 0 448 512" aria-hidden="true"><path d="M304 128a80 80 0 1 0 -160 0 80 80 0 1 0 160 0zM96 128a128 128 0 1 1 256 0A128 128 0 1 1 96 128zM49.3 464H398.7c-8.9-63.3-63.3-112-129-112H178.3c-65.7 0-120.1 48.7-129 112zM0 482.3C0 383.8 79.8 304 178.3 304h91.4C368.2 304 448 383.8 448 482.3c0 16.4-13.3 29.7-29.7 29.7H29.7C13.3 512 0 498.7 0 482.3z"/></svg>
						<?php echo esc_html( $member['rep'] . ( $member['position'] ? ' - ' . $member['position'] : '' ) ); ?>
					</div>
				<?php endif; ?>

				<?php if ( $member['email'] && ! $settings['hide_email'] ) : ?>
					<div class="member_email d-flex align-items-center mt-2 mb-2">
						<a href="mailto:<?php echo esc_attr( antispambot( $member['email'] ) ); ?>"><?php echo esc_html( antispambot( $member['email'] ) ); ?></a>
					</div>
				<?php endif; ?>

				<?php if ( $cnmd_address ) : ?>
					<div class="member_address my-2 d-flex align-items-center w-100">
						<a href="<?php echo esc_url( CNMD_Links::maps_url( $member ) ); ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none d-flex align-items-center">
							<svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A31.493 31.493 0 0 1 8 14.58a31.481 31.481 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94zM8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10z"/><path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/></svg>
							<span class="ms-2"><?php echo esc_html( $cnmd_address ); ?></span>
						</a>
					</div>
				<?php endif; ?>

				<?php if ( $member['texting_keyword'] && $member['texting_number'] ) : ?>
					<div class="member_texting my-2 d-flex align-items-center w-100">
						<div>
							<?php
							printf(
								/* translators: 1: keyword, 2: number */
								esc_html__( 'Text %1$s to %2$s', 'chamber-nation-member-directory' ),
								'<strong class="member_texting_keyword">' . esc_html( $member['texting_keyword'] ) . '</strong>',
								'<strong class="member_texting_number">' . esc_html( $member['texting_number'] ) . '</strong>'
							);
							?>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( $cnmd_has_btns && $cnmd_about ) : ?>
					<hr class="my-3">
				<?php endif; ?>

				<?php if ( $member['phone'] && ! $settings['hide_phone_h'] ) : ?>
					<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $member['phone'] ) ); ?>" class="mb-2 btn btn-sm me-1 btn-soft-primary">
						<svg class="me-2" xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path fill-rule="evenodd" d="M1.885.511a1.745 1.745 0 0 1 2.61.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.678.678 0 0 0 .178.643l2.457 2.457a.678.678 0 0 0 .644.178l2.189-.547a1.745 1.745 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.634 18.634 0 0 1-7.01-4.42 18.634 18.634 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877L1.885.511z"/></svg>
						<?php echo esc_html( $member['phone'] ); ?>
					</a>
				<?php endif; ?>

				<?php if ( $member['website'] && ! $settings['hide_website'] ) : ?>
					<a href="<?php echo esc_url( $member['website'] ); ?>" target="_blank" rel="noopener" class="mb-2 btn btn-sm btn-soft-primary me-1"><?php esc_html_e( 'Website', 'chamber-nation-member-directory' ); ?></a>
				<?php endif; ?>

				<?php if ( $member['contact_form'] && ! $settings['hide_contact_form'] ) : ?>
					<a href="<?php echo esc_url( $member['contact_form'] ); ?>" target="_blank" rel="noopener nofollow" class="mb-2 btn btn-sm me-1 btn-soft-primary">
						<svg class="me-2" xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4Zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2Zm13 2.383-4.708 2.825L15 11.105V5.383Zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741ZM1 11.105l4.708-2.897L1 5.383v5.722Z"/></svg>
						<?php esc_html_e( 'Contact', 'chamber-nation-member-directory' ); ?>
					</a>
				<?php endif; ?>

				<?php if ( $cnmd_view ) : ?>
					<a href="<?php echo esc_url( $cnmd_view ); ?>" class="mb-2 btn btn-sm me-1 btn-primary text-white"<?php echo $url ? '' : ' target="_blank" rel="noopener"'; ?>><?php esc_html_e( 'View Information', 'chamber-nation-member-directory' ); ?></a>
				<?php endif; ?>
			</div>

			<?php if ( $cnmd_badges ) : ?>
				<div class="col-md-2 text-center badge-column">
					<?php foreach ( $cnmd_badges as $cnmd_badge ) : ?>
						<img class="branch_logo mb-2" src="<?php echo esc_url( $cnmd_badge['url'] ); ?>" alt="<?php echo esc_attr( $cnmd_badge['name'] ); ?>" title="<?php echo esc_attr( $cnmd_badge['name'] ); ?>" loading="lazy">
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</article>
