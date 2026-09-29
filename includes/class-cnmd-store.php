<?php
/**
 * Background snapshot of all members.
 *
 * The API can't page, so the full member list is fetched on a schedule
 * (never while a visitor waits, except the very first time) and stored in a
 * non-autoloaded option. Browsing, A–Z, pagination and member pages read
 * from it. Member slugs are kept stable across refreshes, and removed
 * members are remembered so their pages can return 410 Gone.
 *
 * @package ChamberNationMemberDirectory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Snapshot storage and refresh.
 */
class CNMD_Store {

	/**
	 * Cron hook that refreshes stale snapshots.
	 */
	const CRON_HOOK = 'cnmd_refresh_snapshots';

	/**
	 * Option listing snapshot sources in use.
	 */
	const SOURCES_OPTION = 'cnmd_sources';

	/**
	 * How long removed members keep returning 410.
	 */
	const GONE_TTL = 180 * DAY_IN_SECONDS;

	/**
	 * Per-request cache of loaded snapshots.
	 *
	 * @var array
	 */
	private static $loaded = array();

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- 15 minutes is intended.
		add_action( self::CRON_HOOK, array( __CLASS__, 'cron_refresh' ) );
		add_action( 'cnmd_refresh_source', array( __CLASS__, 'refresh_source' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'ensure_cron' ) );
	}

	/**
	 * Add a 15-minute cron schedule.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function cron_schedules( $schedules ) {
		$schedules['cnmd_15min'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes (CN Directory)', 'chamber-nation-member-directory' ),
		);
		return $schedules;
	}

	/**
	 * Make sure the refresh event is scheduled.
	 */
	public static function ensure_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'cnmd_15min', self::CRON_HOOK );
		}
	}

	/**
	 * Unschedule on deactivation.
	 */
	public static function clear_cron() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Snapshot key for a data source.
	 *
	 * @param array $settings Settings.
	 * @return string
	 */
	public static function key( array $settings ) {
		return md5( $settings['server'] . '|' . $settings['org_id'] );
	}

	/**
	 * Load the snapshot for a source. Triggers a background refresh when it
	 * is stale, and a one-time synchronous fetch when none exists yet.
	 *
	 * @param array $settings Settings.
	 * @return array|WP_Error Snapshot: time, members, slugs, by_slug, gone.
	 */
	public static function get( array $settings ) {
		if ( '' === $settings['org_id'] ) {
			return new WP_Error( 'cnmd_no_org', __( 'No Organization ID is configured. Set it under CN Directory in the dashboard.', 'chamber-nation-member-directory' ) );
		}

		$key = self::key( $settings );
		if ( isset( self::$loaded[ $key ] ) ) {
			return self::$loaded[ $key ];
		}

		self::remember_source( $settings );
		$snap = get_option( 'cnmd_snap_' . $key );

		if ( ! is_array( $snap ) || empty( $snap['members'] ) ) {
			$result = self::refresh( $settings );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$snap = get_option( 'cnmd_snap_' . $key );
			if ( ! is_array( $snap ) ) {
				return new WP_Error( 'cnmd_loading', __( 'The member directory is being prepared. Please check back in a minute.', 'chamber-nation-member-directory' ) );
			}
		} elseif ( time() - (int) $snap['time'] > 3 * self::interval( $settings ) ) {
			// WP-Cron seems idle; refresh in the background without blocking.
			self::schedule_async( $settings );
		}

		$snap['by_slug']      = array_flip( $snap['slugs'] );
		self::$loaded[ $key ] = $snap;
		return $snap;
	}

	/**
	 * Fetch all members and save the snapshot.
	 *
	 * @param array $settings Settings.
	 * @return true|WP_Error
	 */
	public static function refresh( array $settings ) {
		$key  = self::key( $settings );
		$lock = 'cnmd_lock_' . $key;
		if ( get_transient( $lock ) ) {
			return new WP_Error( 'cnmd_loading', __( 'The member directory is being prepared. Please check back in a minute.', 'chamber-nation-member-directory' ) );
		}
		set_transient( $lock, 1, 3 * MINUTE_IN_SECONDS );

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$api     = new CNMD_API( $settings );
		$members = $api->fetch_all();

		if ( is_wp_error( $members ) || ! $members ) {
			delete_transient( $lock );
			$error = is_wp_error( $members ) ? $members : new WP_Error( 'cnmd_empty', __( 'The directory API returned no members.', 'chamber-nation-member-directory' ) );
			self::set_status( $key, array( 'error' => $error->get_error_message() ) );
			return $error;
		}

		$old   = get_option( 'cnmd_snap_' . $key );
		$slugs = is_array( $old ) && isset( $old['slugs'] ) ? $old['slugs'] : array();
		$gone  = is_array( $old ) && isset( $old['gone'] ) ? $old['gone'] : array();
		$now   = time();

		// Members that disappeared keep their slug in the "gone" list.
		foreach ( $slugs as $id => $slug ) {
			if ( ! isset( $members[ $id ] ) ) {
				$gone[ $slug ] = $now;
				unset( $slugs[ $id ] );
			}
		}

		// New members get a unique slug; existing ones keep theirs.
		$taken = array_flip( $slugs );
		ksort( $members, SORT_STRING );
		foreach ( $members as $id => $member ) {
			if ( isset( $slugs[ $id ] ) ) {
				continue;
			}
			$base = sanitize_title( $member['name'] );
			$base = '' !== $base ? $base : 'member-' . $id;
			$slug = $base;
			if ( isset( $taken[ $slug ] ) && $member['city'] ) {
				$slug = $base . '-' . sanitize_title( $member['city'] );
			}
			for ( $n = 2; isset( $taken[ $slug ] ); $n++ ) {
				$slug = $base . '-' . $n;
			}
			$slugs[ $id ]   = $slug;
			$taken[ $slug ] = $id;
		}

		// A returning member is no longer gone; prune old tombstones.
		foreach ( $gone as $slug => $time ) {
			if ( isset( $taken[ $slug ] ) || $now - $time > self::GONE_TTL ) {
				unset( $gone[ $slug ] );
			}
		}

		$snap = array(
			'time'    => $now,
			'members' => $members,
			'slugs'   => $slugs,
			'gone'    => $gone,
		);

		update_option( 'cnmd_snap_' . $key, $snap, false );
		unset( self::$loaded[ $key ] );
		delete_transient( $lock );
		CN_Llms_Txt::flush();
		self::set_status(
			$key,
			array(
				'time'  => $now,
				'count' => count( $members ),
				'error' => '',
			)
		);

		do_action( 'cnmd_snapshot_refreshed', $settings, $snap );
		return true;
	}

	/**
	 * Refresh interval in seconds.
	 *
	 * @param array $settings Settings.
	 * @return int
	 */
	public static function interval( array $settings ) {
		return max( 15, (int) $settings['refresh_minutes'] ) * MINUTE_IN_SECONDS;
	}

	/**
	 * Cron: refresh each recently used source whose snapshot is due.
	 */
	public static function cron_refresh() {
		$sources = get_option( self::SOURCES_OPTION, array() );
		$saved   = CNMD_Settings::sanitize( CNMD_Settings::get() );
		$changed = false;

		foreach ( (array) $sources as $key => $source ) {
			if ( time() - (int) $source['used'] > 14 * DAY_IN_SECONDS && self::key( $saved ) !== $key ) {
				unset( $sources[ $key ] );
				$changed = true;
				continue;
			}
			$settings = $saved;
			$settings['org_id'] = $source['org_id'];
			$settings['server'] = $source['server'];

			$snap = get_option( 'cnmd_snap_' . $key );
			$age  = is_array( $snap ) ? time() - (int) $snap['time'] : PHP_INT_MAX;
			if ( $age >= self::interval( $settings ) - MINUTE_IN_SECONDS ) {
				self::refresh( $settings );
			}
		}

		if ( $changed ) {
			update_option( self::SOURCES_OPTION, $sources, false );
		}
	}

	/**
	 * Background single refresh (from schedule_async()).
	 *
	 * @param string $org_id Org ID.
	 * @param string $server Server key.
	 */
	public static function refresh_source( $org_id, $server ) {
		$settings           = CNMD_Settings::sanitize( CNMD_Settings::get() );
		$settings['org_id'] = (string) $org_id;
		$settings['server'] = (string) $server;
		self::refresh( $settings );
	}

	/**
	 * Queue a non-blocking refresh.
	 *
	 * @param array $settings Settings.
	 */
	public static function schedule_async( array $settings ) {
		$args = array( $settings['org_id'], $settings['server'] );
		if ( ! wp_next_scheduled( 'cnmd_refresh_source', $args ) ) {
			wp_schedule_single_event( time(), 'cnmd_refresh_source', $args );
			spawn_cron();
		}
	}

	/**
	 * Remember which sources are in use so cron refreshes them.
	 *
	 * @param array $settings Settings.
	 */
	private static function remember_source( array $settings ) {
		$key     = self::key( $settings );
		$sources = get_option( self::SOURCES_OPTION, array() );
		if ( ! isset( $sources[ $key ] ) || time() - (int) $sources[ $key ]['used'] > DAY_IN_SECONDS ) {
			$sources[ $key ] = array(
				'org_id' => $settings['org_id'],
				'server' => $settings['server'],
				'used'   => time(),
			);
			update_option( self::SOURCES_OPTION, $sources, false );
		}
	}

	/**
	 * Save refresh status for the admin screen.
	 *
	 * @param string $key    Snapshot key.
	 * @param array  $status Status fields.
	 */
	private static function set_status( $key, array $status ) {
		$all         = get_option( 'cnmd_status', array() );
		$all[ $key ] = array_merge( isset( $all[ $key ] ) ? $all[ $key ] : array(), $status, array( 'checked' => time() ) );
		update_option( 'cnmd_status', $all, false );
	}

	/**
	 * Refresh status for a source.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function status( array $settings ) {
		$all = get_option( 'cnmd_status', array() );
		$key = self::key( $settings );
		return isset( $all[ $key ] ) ? $all[ $key ] : array();
	}

	/**
	 * Member slug, or ''.
	 *
	 * @param array  $snap Snapshot.
	 * @param string $id   Member ID.
	 * @return string
	 */
	public static function slug( $snap, $id ) {
		return is_array( $snap ) && isset( $snap['slugs'][ $id ] ) ? $snap['slugs'][ $id ] : '';
	}
}
