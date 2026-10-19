<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

/**
 * Issuing and expiring contact magic links.
 *
 * A contact link is a bearer key: whoever holds it is that contact. So a link is
 * only minted when someone asks to hand one over - pressing Copy link - and it
 * stops working after the validity set in the Autolink settings, a week unless an
 * admin says otherwise. Copying again extends the same link rather than replacing
 * it, so the copy someone sent yesterday keeps working.
 *
 * Expiry is stored in the shape the Bulk Magic Link Sender plugin reads and
 * writes (`<meta_key>_expiration`), so where that plugin is installed the Apps
 * tile shows when a link runs out and an admin can change it there. Autolink
 * enforces the expiry itself, though, because that plugin is optional.
 *
 * A key with no expiry record does not expire. That is how the rest of
 * Disciple.Tools reads such a key, and it covers links issued before this existed
 * and links sent through bulk send. Copying such a key gives it the configured
 * validity, though - see issue().
 */
class Disciple_Tools_Autolink_Contact_Links {
	const ROOT = 'autolink';
	const TYPE = 'contact';

	/**
	 * Units an admin may choose a validity in. Each is something strtotime()
	 * understands after `+<amount>`.
	 */
	const UNITS = [ 'hours', 'days', 'weeks', 'months' ];

	/**
	 * The validity setting's unit when links never expire.
	 */
	const NEVER = 'never';

	public static function meta_key() {
		return DT_Magic_URL::get_public_key_meta_key( self::ROOT, self::TYPE );
	}

	public static function expiration_meta_key() {
		return self::meta_key() . '_expiration';
	}

	/**
	 * How long a newly issued link lasts.
	 *
	 * @return array|null { amount: int, unit: string }, or null for never
	 */
	public static function validity() {
		$settings = new Disciple_Tools_Autolink_Settings();
		$unit     = (string) $settings->get_option( 'disciple_tools_autolink_contact_link_validity_unit' );
		$amount   = (int) $settings->get_option( 'disciple_tools_autolink_contact_link_validity_amount' );

		if ( $unit === self::NEVER ) {
			return null;
		}

		if ( ! in_array( $unit, self::UNITS, true ) || $amount < 1 ) {
			return [ 'amount' => 1, 'unit' => 'weeks' ];
		}

		return [ 'amount' => $amount, 'unit' => $unit ];
	}

	/**
	 * The expiry record for a contact's current key.
	 *
	 * @param int $contact_id
	 *
	 * @return array|null null when the current key has none
	 */
	private static function record( $contact_id ) {
		$record = get_post_meta( (int) $contact_id, self::expiration_meta_key(), true );

		if ( is_string( $record ) ) {
			$record = json_decode( $record, true );
		}

		if ( ! is_array( $record ) ) {
			return null;
		}

		// A record left behind by an earlier key - one reset from the Apps tile,
		// say - says nothing about the key there now.
		$hash = $record['current_hash'] ?? '';
		if ( $hash && $hash !== get_post_meta( (int) $contact_id, self::meta_key(), true ) ) {
			return null;
		}

		return $record;
	}

	/**
	 * When a contact's current key stops working.
	 *
	 * @param int $contact_id
	 *
	 * @return int|null a timestamp, or null when the key does not expire
	 */
	public static function expires_at( $contact_id ) {
		$record = self::record( $contact_id );

		if ( $record === null ) {
			return null;
		}

		$never = $record['links_never_expires'] ?? false;
		if ( $never === true || $never === 'true' ) {
			return null;
		}

		// Read the way the Bulk Magic Link Sender reads it, so the two agree.
		$base   = (int) ( $record['links_expire_within_base_ts'] ?? 0 );
		$amount = $record['links_expire_within_amount'] ?? '';
		$unit   = $record['links_expire_within_time_unit'] ?? '';

		if ( ! $base || ! $amount || ! $unit ) {
			return null;
		}

		$expires = strtotime( '+' . $amount . ' ' . $unit, $base );

		return $expires === false ? null : $expires;
	}

	/**
	 * An expiry, as the site writes dates and times.
	 *
	 * Formatted here rather than in the browser so that the app's own link and
	 * the contacts list say it the same way.
	 *
	 * @param int|null $timestamp
	 *
	 * @return string empty for a link that does not expire
	 */
	public static function format_expiry( $timestamp ) {
		if ( ! $timestamp ) {
			return '';
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $timestamp );
	}

	/**
	 * Whether an expiry is close enough to warn about.
	 *
	 * @param int|null $timestamp
	 *
	 * @return bool
	 */
	public static function expires_soon( $timestamp ) {
		return $timestamp && ( (int) $timestamp - time() ) < 2 * DAY_IN_SECONDS;
	}

	/**
	 * Whether a contact's current key has run out.
	 *
	 * @param int $contact_id
	 *
	 * @return bool
	 */
	public static function is_expired( $contact_id ) {
		$expires = self::expires_at( $contact_id );

		return $expires !== null && $expires < time();
	}

	/**
	 * A link for a contact that is valid for at least the configured validity
	 * from now.
	 *
	 * - no key, or an expired one: a new key, valid from now.
	 * - a live key:                the same key, extended - never shortened, so an
	 *                              admin's longer expiry set on the tile stands.
	 * - a key with no record:      the same key, given the validity from now.
	 *                              Copying is a deliberate hand over, and a
	 *                              link that never expires is a choice an admin
	 *                              makes explicitly, not a leftover.
	 *
	 * Contacts with a login of their own are never given one - see
	 * Disciple_Tools_Autolink_Actor::may_act_as().
	 *
	 * @param int $contact_id
	 * @param bool $extend false to leave a live key's expiry alone
	 *
	 * @return string the link, or empty when none may be issued
	 */
	public static function issue( $contact_id, $extend = true ) {
		$contact_id = (int) $contact_id;

		if ( ! $contact_id || Disciple_Tools_Autolink_Actor::contact_has_login( $contact_id ) ) {
			return '';
		}

		$key   = get_post_meta( $contact_id, self::meta_key(), true );
		$fresh = ! $key || self::is_expired( $contact_id );

		if ( $fresh ) {
			$key = dt_create_unique_key();
			update_post_meta( $contact_id, self::meta_key(), $key );
		}

		if ( $fresh || $extend ) {
			self::stamp( $contact_id, $key, $fresh );
		}

		if ( $fresh ) {
			self::forget_cached_record( $contact_id );
		}

		return DT_Magic_URL::get_link_url( self::ROOT, self::TYPE, $key );
	}

	/**
	 * Drop the copy of a contact that DT_Posts::get_post() holds for this request.
	 *
	 * The cache is keyed per user and is not cleared by writing post meta. The
	 * Apps tile reads the key out of that copy, so on a record fetched earlier in
	 * the page - before a key was minted here - it saw no key, minted one of its
	 * own over ours and left our expiry record pointing at a key that no longer
	 * exists. The link then never expired.
	 *
	 * @param int $contact_id
	 */
	private static function forget_cached_record( $contact_id ) {
		wp_cache_delete( 'post_' . get_current_user_id() . '_' . (int) $contact_id );
	}

	/**
	 * Write the expiry for a key.
	 *
	 * @param int $contact_id
	 * @param string $key
	 * @param bool $fresh whether the key was just minted
	 */
	private static function stamp( $contact_id, $key, $fresh ) {
		$validity = self::validity();
		$current  = self::expires_at( $contact_id );

		if ( $validity === null ) {
			$record = [
				'links_expire_within_base_ts'   => '',
				'links_expire_on_ts'            => '',
				'links_expire_on_ts_formatted'  => __( 'Never', 'disciple-tools-autolink' ),
				'links_expire_within_amount'    => '',
				'links_expire_within_time_unit' => '',
				'links_never_expires'           => true,
				'current_hash'                  => $key,
			];
		} else {
			$now     = time();
			$expires = strtotime( '+' . $validity['amount'] . ' ' . $validity['unit'], $now );

			// A live key keeps what it has when that is longer: an expiry past
			// the new one, or a "never" an admin chose on the Apps tile. A key
			// with no record at all gets the validity.
			if ( ! $fresh && self::record( $contact_id ) !== null && ( $current === null || $current >= $expires ) ) {
				return;
			}

			$record = [
				'links_expire_within_base_ts'   => $now,
				'links_expire_on_ts'            => $expires,
				'links_expire_on_ts_formatted'  => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $expires ),
				'links_expire_within_amount'    => $validity['amount'],
				'links_expire_within_time_unit' => $validity['unit'],
				'links_never_expires'           => false,
				'current_hash'                  => $key,
			];
		}

		update_post_meta( $contact_id, self::expiration_meta_key(), $record );
	}

	/**
	 * Turn away an expired contact link at the door.
	 *
	 * Filters `dt_magic_link_continue`, which the theme asks before serving any
	 * magic link page and answers "no" to with its own expired link screen.
	 *
	 * @param bool $continue
	 * @param array $parts
	 *
	 * @return bool
	 */
	public static function magic_link_continue( $continue, $parts ) {
		if ( ! $continue ) {
			return $continue;
		}

		if ( ( $parts['root'] ?? '' ) !== self::ROOT || ( $parts['type'] ?? '' ) !== self::TYPE ) {
			return $continue;
		}

		return ! self::is_expired( (int) ( $parts['post_id'] ?? 0 ) );
	}

	/**
	 * Give a contact on the Apps tile a link that works.
	 *
	 * The theme mints a key for any app it lists that has none - with no expiry
	 * - and shows an expired one as it stands. Issuing first means the tile shows
	 * a live link on the configured validity, without stretching one that is
	 * already live.
	 *
	 * @param int $contact_id
	 */
	public static function prepare_for_tile( $contact_id ) {
		self::issue( $contact_id, false );
	}
}
