<?php
/**
 * Member overview page. The external profile is the source of truth and is
 * linked prominently.
 *
 * Override by copying to {theme}/cn-directory/member-page.php.
 *
 * @package ChamberNationMemberDirectory
 *
 * @var array   $member        Member.
 * @var array   $settings      Settings.
 * @var string  $external      External profile URL.
 * @var string  $about         Description text.
 * @var array[] $badges        Badge images.
 * @var string  $directory_url Directory page URL.
 * @var array[] $breadcrumbs   Breadcrumbs.
 */

defined( 'ABSPATH' ) || exit;

$cnmd_logo    = $member['logo'] ? $member['logo'] : $settings['default_image'];
$cnmd_address = $settings['hide_address'] ? '' : CNMD_Links::address_line( $member );
$cnmd_social  = array(
	'facebook'  => 'Facebook',
	'instagram' => 'Instagram',
	'x'         => 'X',
	'twitter'   => 'X',
	'linkedin'  => 'LinkedIn',
	'youtube'   => 'YouTube',
	'tiktok'    => 'TikTok',
	'pinterest' => 'Pinterest',
);
?>
<div class="cnmd-root cnmd-member-page">
	<nav class="cnmd-breadcrumbs mb-3" aria-label="<?php esc_attr_e( 'Breadcrumb', 'chamber-nation-member-directory' ); ?>">
		<a href="<?php echo esc_url( $directory_url ); ?>">&larr; <?php echo esc_html( $breadcrumbs[0]['name'] ); ?></a>
	</nav>

	<article class="card member_card">
		<div class="card-body p-4">
			<div class="row g-4 align-items-start">
				<?php if ( $cnmd_logo ) : ?>
					<div class="col-md-4 text-center">
						<img class="member_logo img-fluid" src="<?php echo esc_url( $cnmd_logo ); ?>" alt="<?php echo esc_attr( $member['logo'] ? $member['name'] : '' ); ?>">
					</div>
				<?php endif; ?>

				<div class="col">
					<?php if ( $member['cats'] && ! $settings['hide_primary_category'] ) : ?>
						<div class="member_branch_designation text-uppercase mb-2"><?php echo esc_html( implode( ' · ', $member['cats'] ) ); ?></div>
					<?php endif; ?>


					<?php if ( $member['branches'] && ! $settings['hide_branches'] ) : ?>
						<p class="member_branch_designation mb-1"><strong><?php esc_html_e( 'Military branch:', 'chamber-nation-member-directory' ); ?></strong> <?php echo esc_html( implode( ', ', $member['branches'] ) ); ?></p>
					<?php endif; ?>
					<?php if ( $member['designations'] && ! $settings['hide_designations'] ) : ?>
						<p class="member_branch_designation mb-1"><strong><?php esc_html_e( 'Designations:', 'chamber-nation-member-directory' ); ?></strong> <?php echo esc_html( implode( ', ', $member['designations'] ) ); ?></p>
					<?php endif; ?>

					<ul class="list-unstyled cnmd-contact mt-3 mb-4">
						<?php if ( $cnmd_address ) : ?>
							<li class="member_address mb-2">
								<strong><?php esc_html_e( 'Address:', 'chamber-nation-member-directory' ); ?></strong>
								<a href="<?php echo esc_url( CNMD_Links::maps_url( $member ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $cnmd_address ); ?></a>
							</li>
						<?php endif; ?>
						<?php if ( $member['phone'] && ! $settings['hide_phone_h'] ) : ?>
							<li class="mb-2"><strong><?php esc_html_e( 'Phone:', 'chamber-nation-member-directory' ); ?></strong> <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $member['phone'] ) ); ?>"><?php echo esc_html( $member['phone'] ); ?></a></li>
						<?php endif; ?>
						<?php if ( $member['email'] && ! $settings['hide_email'] ) : ?>
							<li class="member_email mb-2"><strong><?php esc_html_e( 'Email:', 'chamber-nation-member-directory' ); ?></strong> <a href="mailto:<?php echo esc_attr( antispambot( $member['email'] ) ); ?>"><?php echo esc_html( antispambot( $member['email'] ) ); ?></a></li>
						<?php endif; ?>
						<?php if ( $member['website'] && ! $settings['hide_website'] ) : ?>
							<li class="mb-2"><strong><?php esc_html_e( 'Website:', 'chamber-nation-member-directory' ); ?></strong> <a href="<?php echo esc_url( $member['website'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( preg_replace( '#^https?://(www\.)?#i', '', untrailingslashit( $member['website'] ) ) ); ?></a></li>
						<?php endif; ?>
						<?php if ( $member['texting_keyword'] && $member['texting_number'] ) : ?>
							<li class="member_texting mb-2">
								<?php
								printf(
									/* translators: 1: keyword, 2: number */
									esc_html__( 'Text %1$s to %2$s', 'chamber-nation-member-directory' ),
									'<strong>' . esc_html( $member['texting_keyword'] ) . '</strong>',
									'<strong>' . esc_html( $member['texting_number'] ) . '</strong>'
								);
								?>
							</li>
						<?php endif; ?>
						<?php if ( $member['rep'] && ! $settings['hide_name_position'] ) : ?>
							<li class="member_representative mb-2"><strong><?php esc_html_e( 'Contact:', 'chamber-nation-member-directory' ); ?></strong> <?php echo esc_html( $member['rep'] . ( $member['position'] ? ' - ' . $member['position'] : '' ) ); ?></li>
						<?php endif; ?>
					</ul>

					<?php if ( $member['social'] ) : ?>
						<p class="cnmd-social mb-4">
							<?php foreach ( $member['social'] as $cnmd_network => $cnmd_link ) : ?>
								<a class="btn btn-sm btn-soft-primary me-1 mb-1" href="<?php echo esc_url( $cnmd_link ); ?>" target="_blank" rel="noopener"><?php echo esc_html( isset( $cnmd_social[ $cnmd_network ] ) ? $cnmd_social[ $cnmd_network ] : ucfirst( $cnmd_network ) ); ?></a>
							<?php endforeach; ?>
						</p>
					<?php endif; ?>

					<div class="cnmd-actions">
						<a class="btn btn-primary text-white me-2 mb-2" href="<?php echo esc_url( $external ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View Full Profile', 'chamber-nation-member-directory' ); ?> <span aria-hidden="true">↗</span></a>
						<?php if ( $member['contact_form'] && ! $settings['hide_contact_form'] ) : ?>
							<a class="btn btn-soft-primary me-2 mb-2" href="<?php echo esc_url( $member['contact_form'] ); ?>" target="_blank" rel="noopener nofollow"><?php esc_html_e( 'Contact', 'chamber-nation-member-directory' ); ?></a>
						<?php endif; ?>
					</div>
					<p class="small text-muted mb-0"><?php esc_html_e( 'The full profile, with photos, offers and the latest details, opens in a new tab.', 'chamber-nation-member-directory' ); ?></p>
				</div>

				<?php if ( $badges ) : ?>
					<div class="col-md-2 text-center badge-column">
						<?php foreach ( $badges as $cnmd_badge ) : ?>
							<img class="branch_logo mb-2" src="<?php echo esc_url( $cnmd_badge['url'] ); ?>" alt="<?php echo esc_attr( $cnmd_badge['name'] ); ?>" title="<?php echo esc_attr( $cnmd_badge['name'] ); ?>">
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $about ) : ?>
				<section class="member_about mt-4 pt-3 border-top">
					<h3 class="h5"><?php
						/* translators: %s: business name */
						echo esc_html( sprintf( __( 'About %s', 'chamber-nation-member-directory' ), $member['name'] ) );
					?></h3>
					<?php foreach ( array_filter( explode( "\n", $about ) ) as $cnmd_paragraph ) : ?>
						<p><?php echo esc_html( $cnmd_paragraph ); ?></p>
					<?php endforeach; ?>
				</section>
			<?php endif; ?>
		</div>
	</article>
</div>
