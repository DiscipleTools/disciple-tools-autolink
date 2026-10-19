<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

/**
 * Who the app is acting as for the current request.
 *
 * Autolink started out as a `user` magic link, so every query keyed straight off
 * get_current_user_id(). A contact magic link has no WP user behind it, so the
 * two identities - the WP user and the contact record - have to come apart:
 *
 * - user mode:    a real WP session. user_id() is the logged in user and
 *                 contact_id() is their corresponding contact record.
 * - contact mode: the visitor proved they hold a contact's magic key. user_id()
 *                 is 0 - they never get a WP session or any capability - and
 *                 contact_id() is the record from the link.
 *
 * Call sites ask this class for what they need rather than reaching for
 * get_current_user_id(), so the same controllers serve both links.
 */
class Disciple_Tools_Autolink_Actor {
	const MODE_USER = 'user';
	const MODE_CONTACT = 'contact';

	private static $current = null;

	private $mode;
	private $user_id;
	private $contact_id;
	private $contact_cache = null;
	private $coached_cache = null;

	/**
	 * Marks the activity log with the contact who made a change.
	 *
	 * @see sign_activity()
	 * @see name_activity()
	 */
	const ACTIVITY_PREFIX = 'autolink_contact_';
	private $allowed_groups_cache = null;
	private $own_groups_cache = null;
	private $related_contacts_cache = null;

	private function __construct( $mode, $user_id, $contact_id ) {
		$this->mode       = $mode;
		$this->user_id    = (int) $user_id;
		$this->contact_id = (int) $contact_id;
	}

	/**
	 * Act as a logged in WP user. Called by the user magic link.
	 *
	 * @param int $user_id
	 *
	 * @return self
	 */
	public static function set_user( $user_id ) {
		$user_id    = (int) $user_id;
		$contact_id = $user_id ? Disciple_Tools_Users::get_contact_for_user( $user_id ) : 0;

		self::$current = new self( self::MODE_USER, $user_id, $contact_id ? (int) $contact_id : 0 );

		return self::$current;
	}

	/**
	 * Act as a contact record with no WP session. Called by the contact magic link.
	 *
	 * @param int $contact_id
	 *
	 * @return self
	 */
	public static function set_contact( $contact_id ) {
		self::$current = new self( self::MODE_CONTACT, 0, (int) $contact_id );

		return self::$current;
	}

	/**
	 * The actor for this request.
	 *
	 * Falls back to the logged in user so anything that runs outside a magic link
	 * - the admin screens, WP cron, a direct controller call - keeps working.
	 *
	 * @return self
	 */
	public static function current() {
		if ( self::$current === null ) {
			return self::set_user( get_current_user_id() );
		}

		return self::$current;
	}

	public static function reset() {
		self::$current = null;
	}

	public function mode() {
		return $this->mode;
	}

	public function is_contact() {
		return $this->mode === self::MODE_CONTACT;
	}

	public function is_user() {
		return $this->mode === self::MODE_USER;
	}

	/**
	 * The WP user this request is authenticated as. 0 in contact mode.
	 *
	 * Use this for anything that is about the *session* - capabilities,
	 * authorship, user meta. For "whose records are these?" use contact_id() or
	 * query_groups() instead, which work in both modes.
	 *
	 * @return int
	 */
	public function user_id() {
		return $this->user_id;
	}

	public function contact_id() {
		return $this->contact_id;
	}

	/**
	 * The user the contact record happens to correspond to, if any.
	 *
	 * This is NOT an authenticated identity - in contact mode the visitor holds
	 * the contact's key, not that user's password, so it must never be used to
	 * grant anything. It exists only so a contact who also has a user account
	 * sees the same records through either link.
	 *
	 * @return int
	 */
	public function linked_user_id() {
		if ( $this->user_id ) {
			return $this->user_id;
		}

		if ( ! $this->contact_id ) {
			return 0;
		}

		return (int) get_post_meta( $this->contact_id, 'corresponds_to_user', true );
	}

	/**
	 * The contact record, fetched without a permission check.
	 *
	 * @return array|null
	 */
	public function contact() {
		if ( ! $this->contact_id ) {
			return null;
		}

		if ( $this->contact_cache === null ) {
			$contact             = DT_Posts::get_post( 'contacts', $this->contact_id, true, false );
			$this->contact_cache = is_wp_error( $contact ) ? null : $contact;
		}

		return $this->contact_cache;
	}

	/**
	 * Name to greet the person by.
	 *
	 * @return string
	 */
	public function display_name() {
		if ( $this->is_user() && $this->user_id ) {
			return dt_get_user_display_name( $this->user_id );
		}

		$contact = $this->contact();

		return $contact['title'] ?? $contact['name'] ?? '';
	}

	/**
	 * How to find "my churches".
	 *
	 * Two handles, not one. A user owns groups through `assigned_to`; a contact is
	 * attached through `leaders`, which is what Autolink sets on every church it
	 * creates. Matching on either means the list does not change shape when
	 * someone upgrades their contact to a login - the churches they created
	 * without one are still theirs afterwards.
	 *
	 * @return array one criteria set, to be OR'd - see query_groups()
	 */
	public function group_filter() {
		$filter  = [];
		$user_id = $this->linked_user_id();

		if ( $user_id ) {
			$filter['assigned_to'] = [ $user_id ];
		}

		if ( $this->contact_id ) {
			$filter['leaders'] = [ $this->contact_id ];
		}

		return $filter;
	}

	/**
	 * The same question, asked about someone else's contact record.
	 *
	 * Used when walking the coaching tree, where a coached contact may or may not
	 * have a user account of their own. Before contact links existed the tree
	 * skipped anyone without one, so their churches never showed up.
	 *
	 * @param array|int $contact a contact record or id
	 *
	 * @return array
	 */
	public static function group_filter_for_contact( $contact ) {
		if ( is_array( $contact ) ) {
			$contact_id = (int) ( $contact['ID'] ?? 0 );
			$user_id    = (int) ( $contact['corresponds_to_user'] ?? 0 );
		} else {
			$contact_id = (int) $contact;
			$user_id    = (int) get_post_meta( $contact_id, 'corresponds_to_user', true );
		}

		$filter = [];

		if ( $user_id ) {
			$filter['assigned_to'] = [ $user_id ];
		}

		if ( $contact_id ) {
			$filter['leaders'] = [ $contact_id ];
		}

		return $filter;
	}

	/**
	 * This actor's churches.
	 *
	 * @param array $args list_posts args - limit, offset, sort and so on
	 *
	 * @return array { posts: array, total: int }
	 */
	public function query_groups( $args = [] ) {
		return self::run_query( 'groups', $this->group_filter(), $args );
	}

	/**
	 * Someone else's churches.
	 *
	 * @param array|int $contact
	 * @param array $args
	 *
	 * @return array { posts: array, total: int }
	 */
	public static function query_groups_for_contact( $contact, $args = [] ) {
		return self::run_query( 'groups', self::group_filter_for_contact( $contact ), $args );
	}

	/**
	 * Run a list query whose criteria are alternatives rather than requirements.
	 *
	 * Named keys in a DT query are ANDed together, but a numerically indexed entry
	 * is recursed into with the operator flipped - so a nested array of criteria
	 * at the top level is an OR group. The theme uses the same shape for its own
	 * permission clause.
	 *
	 * Sorting, paging and totals stay in SQL where they belong.
	 *
	 * @see Disciple_Tools_Posts::fields_to_sql()
	 *
	 * @param string $post_type
	 * @param array $any_of criteria to match any one of
	 * @param array $args other list_posts args
	 *
	 * @return array { posts: array, total: int }
	 */
	private static function run_query( $post_type, $any_of, $args = [] ) {
		if ( empty( $any_of ) ) {
			return [ 'posts' => [], 'total' => 0 ];
		}

		$query   = $args;
		$query[] = $any_of;

		$response = DT_Posts::list_posts( $post_type, $query, false );

		if ( is_wp_error( $response ) ) {
			return [ 'posts' => [], 'total' => 0 ];
		}

		return [
			'posts' => $response['posts'] ?? [],
			'total' => $response['total'] ?? 0,
		];
	}

	/**
	 * Who a record created by this actor should belong to.
	 *
	 * A contact link runs with no WP user, and DT stamps `assigned_to` with
	 * whoever is logged in - which came out as `user-0`, leaving the record owned
	 * by nobody and absent from every DT list view. The site's base user is the
	 * catch-all account meant for exactly this, so new records land there and
	 * stay visible until someone takes them on.
	 *
	 * In user mode this returns null and DT's own default - the logged in user -
	 * stands.
	 *
	 * @return string|null an `assigned_to` value, or null to leave it to DT
	 */
	public function assigned_to_for_new_records() {
		// A user is a real person Disciple.Tools can hold responsible, so what they
		// create is theirs. Disciple.Tools does not assume this on create - it
		// leaves assigned_to empty - so it is said here.
		if ( ! $this->is_contact() ) {
			$user_id = $this->user_id();

			return $user_id ? 'user-' . $user_id : null;
		}

		$base_user_id = dt_get_base_user( true );

		return $base_user_id ? 'user-' . $base_user_id : null;
	}

	/**
	 * The contacts this actor coaches.
	 *
	 * @return array
	 */
	public function coached_contacts() {
		if ( ! $this->contact_id ) {
			return [];
		}

		if ( $this->coached_cache === null ) {
			$response            = DT_Posts::list_posts( 'contacts', [
				'coached_by' => [ $this->contact_id ],
				'limit'      => 1000,
			], false );
			$this->coached_cache = is_wp_error( $response ) ? [] : ( $response['posts'] ?? [] );
		}

		return $this->coached_cache;
	}

	/**
	 * The people around this actor.
	 *
	 * Four ways to belong on the list, matched as alternatives in one query: the
	 * actor coaches them, they are assigned to the actor, or they lead or belong
	 * to one of the actor's churches. The first two are the same two handles their
	 * churches use - a user carries people through `assigned_to`, a contact
	 * through `coached_by` - so the list does not empty out when someone takes a
	 * login, or fill up only then.
	 *
	 * The last two are matched from the contact side (`group_leader`, `groups`)
	 * rather than by walking the actor's churches, so each record comes back
	 * carrying its own full set of church connections. That is what lets the card
	 * say where someone leads or belongs even when the actor has no part in that
	 * church.
	 *
	 * This is a reading list, deliberately wider than coached_contacts(), which
	 * stays the narrower set that decides what may be written.
	 *
	 * @return array
	 */
	public function related_contacts() {
		if ( $this->related_contacts_cache !== null ) {
			return $this->related_contacts_cache;
		}

		$filter  = [];
		$user_id = $this->linked_user_id();

		if ( $user_id ) {
			$filter['assigned_to'] = [ $user_id ];
		}

		if ( $this->contact_id ) {
			$filter['coached_by'] = [ $this->contact_id ];
		}

		$group_ids = array_map( 'intval', wp_list_pluck( $this->own_groups(), 'ID' ) );

		if ( $group_ids ) {
			$filter['group_leader'] = $group_ids;
			$filter['groups']       = $group_ids;
		}

		$this->related_contacts_cache = self::run_query( 'contacts', $filter, [ 'limit' => 1000 ] )['posts'];

		return $this->related_contacts_cache;
	}

	/**
	 * The groups this actor owns.
	 *
	 * @return array
	 */
	public function own_groups() {
		if ( $this->own_groups_cache === null ) {
			$this->own_groups_cache = $this->query_groups( [ 'limit' => 1000 ] )['posts'];
		}

		return $this->own_groups_cache;
	}

	/**
	 * Every group this actor may read or write: their own, plus those of the
	 * people they coach.
	 *
	 * In user mode this is the same set the app has always shown, because
	 * DT_Posts enforced it through the user's capabilities. In contact mode there
	 * is no user and so no capability to enforce, which is exactly why it has to
	 * be stated explicitly here - without it a contact link would be able to
	 * write to any group on the site.
	 *
	 * @return int[]
	 */
	public function allowed_group_ids() {
		if ( $this->allowed_groups_cache !== null ) {
			return $this->allowed_groups_cache;
		}

		$ids = [];

		foreach ( $this->own_groups() as $group ) {
			$ids[] = (int) $group['ID'];
		}

		foreach ( $this->coached_contacts() as $coached ) {
			foreach ( self::query_groups_for_contact( $coached, [ 'limit' => 1000 ] )['posts'] as $group ) {
				$ids[] = (int) $group['ID'];
			}
		}

		$this->allowed_groups_cache = array_values( array_unique( $ids ) );

		return $this->allowed_groups_cache;
	}

	/**
	 * Whether this actor may write to a group.
	 *
	 * @param int $group_id
	 *
	 * @return bool
	 */
	public function can_edit_group( $group_id ) {
		return in_array( (int) $group_id, $this->allowed_group_ids(), true );
	}

	/**
	 * Whether a group is this actor's own, rather than one reached through
	 * someone they coach.
	 *
	 * @param int $group_id
	 *
	 * @return bool
	 */
	public function owns_group( $group_id ) {
		$group_id = (int) $group_id;

		foreach ( $this->own_groups() as $group ) {
			if ( (int) $group['ID'] === $group_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether this actor may write to a contact.
	 *
	 * Their own record and the records of those they coach.
	 *
	 * @param int $contact_id
	 *
	 * @return bool
	 */
	public function can_edit_contact( $contact_id ) {
		$contact_id = (int) $contact_id;

		if ( ! $contact_id ) {
			return false;
		}

		if ( $contact_id === $this->contact_id ) {
			return true;
		}

		// A user brings Disciple.Tools' own permissions with them, so ask it
		// rather than keeping a second, narrower answer here. It covers being
		// assigned the record, having it shared, and whatever an administrator has
		// granted the role - and it answers "no" for records they cannot reach,
		// which the list does not filter on its own.
		if ( ! $this->is_contact() ) {
			return DT_Posts::can_update( 'contacts', $contact_id );
		}

		// A contact has no Disciple.Tools user for those permissions to hang off,
		// so in contact mode the only other people they can edit are the ones they
		// are coaching.
		foreach ( $this->coached_contacts() as $coached ) {
			if ( (int) $coached['ID'] === $contact_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The link back to this actor's own app.
	 *
	 * @return string
	 */
	public function app_link() {
		if ( $this->is_contact() ) {
			return self::contact_app_link( $this->contact_id );
		}

		$app_public_key = get_user_option( DT_Magic_URL::get_public_key_meta_key( 'autolink', 'app' ), $this->user_id );

		return DT_Magic_URL::get_link_url( 'autolink', 'app', $app_public_key );
	}

	/**
	 * A contact's existing app link.
	 *
	 * This never mints one. A key is a bearer credential, so it is only issued
	 * when someone asks to hand it over - see
	 * Disciple_Tools_Autolink_Contact_Links::issue(). In contact mode the actor's
	 * own key exists by definition: it is how they got here.
	 *
	 * @param int $contact_id
	 *
	 * @return string empty when the contact has no key
	 */
	public static function contact_app_link( $contact_id ) {
		$key = $contact_id ? get_post_meta( (int) $contact_id, Disciple_Tools_Autolink_Contact_Links::meta_key(), true ) : '';

		if ( ! $key ) {
			return '';
		}

		return DT_Magic_URL::get_link_url( 'autolink', 'contact', $key );
	}

	/**
	 * Whether a contact record has a Disciple.Tools login of its own.
	 *
	 * @param int $contact_id
	 *
	 * @return bool
	 */
	public static function contact_has_login( $contact_id ) {
		return (bool) get_post_meta( (int) $contact_id, 'corresponds_to_user', true );
	}

	/**
	 * Whether the holder of a verified key may act through it.
	 *
	 * Verifying the key only proves which record a link belongs to. What that is
	 * worth depends on the link:
	 *
	 * - `app`:     the user link is an address, not a credential. The page has
	 *              always sent anyone not signed in to the login screen, so its
	 *              REST calls ask the same - the session must be the user the key
	 *              names.
	 * - `contact`: the key is the credential, but only for a contact without a
	 *              login, and only until it expires. One with a login resolves
	 *              through `assigned_to` to everything their account holds, so
	 *              they sign in instead.
	 *
	 * Any other type - the share link above all, which is handed out on purpose -
	 * acts as nobody.
	 *
	 * @param array $parts verified magic link parts: `type` and `post_id`
	 *
	 * @return bool
	 */
	public static function may_act_as( $parts ) {
		$post_id = (int) ( $parts['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return false;
		}

		switch ( $parts['type'] ?? '' ) {
			case 'app':
				return is_user_logged_in() && get_current_user_id() === $post_id;
			case 'contact':
				return ! self::contact_has_login( $post_id )
					&& ! Disciple_Tools_Autolink_Contact_Links::is_expired( $post_id );
			default:
				return false;
		}
	}

	/**
	 * Whether there is a session to end.
	 *
	 * @return bool
	 */
	public function can_logout() {
		return $this->is_user();
	}

	/**
	 * The locale this actor reads the app in.
	 *
	 * @return string
	 */
	public function locale() {
		if ( $this->is_user() && $this->user_id ) {
			return get_user_locale( $this->user_id );
		}

		$locale = get_post_meta( $this->contact_id, 'dt_autolink_locale', true );

		if ( $locale ) {
			return $locale;
		}

		return determine_locale();
	}

	/**
	 * Persist a language choice.
	 *
	 * In contact mode there is no user to carry a locale, so it goes on the
	 * contact record as `dt_autolink_locale`. The contact link reads it back
	 * before anything else when picking the page's language - see
	 * Disciple_Tools_Autolink_Magic_Contact_Link_App::fetch_incoming_contact_lang().
	 *
	 * It is deliberately kept out of the `languages` field. That field records
	 * the languages a person speaks, as language codes, not the WP locale they
	 * read this app in.
	 *
	 * @param string $locale
	 *
	 * @return true|WP_Error
	 */
	public function set_locale( $locale ) {
		if ( $this->is_user() && $this->user_id ) {
			return Disciple_Tools_Users::update_user_locale( $this->user_id, $locale );
		}

		if ( ! $this->contact_id ) {
			return new WP_Error( __METHOD__, __( 'No record to save the language to.', 'disciple-tools-autolink' ) );
		}

		update_post_meta( $this->contact_id, 'dt_autolink_locale', $locale );

		return true;
	}

	/**
	 * A survey answer.
	 *
	 * Answers are written to the contact record - see the survey controller - so
	 * they are read back from there in both modes. User mode used to read them
	 * from user meta, which is not where they were ever saved.
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function survey_answer( $name ) {
		if ( ! $this->contact_id ) {
			return '';
		}

		return get_post_meta( $this->contact_id, $name, true );
	}

	/**
	 * Stamp this request as the work of one contact, for the activity log.
	 *
	 * Disciple.Tools writes the first capability the acting user holds into the
	 * log's user_caps column, and renders a bare "magic_link" as "Magic Link
	 * Submission" - true, but it never says who, so every change made through
	 * every contact link reads the same. Putting the contact first is what lets
	 * the log name a person; name_activity() reads it back out.
	 *
	 * The magic_link capability stays on behind it, for everything that tests for
	 * it. Neither is a real role, so this only changes what the log records, not
	 * what the request is allowed to do.
	 *
	 * @param int $contact_id
	 *
	 * @return void
	 */
	public static function sign_activity( $contact_id ) {
		$user = wp_get_current_user();

		if ( ! $user || ! $contact_id ) {
			return;
		}

		$user->caps = [ self::ACTIVITY_PREFIX . (int) $contact_id => true ];
		$user->add_cap( 'magic_link' );
	}

	/**
	 * Name the contact behind an activity log entry.
	 *
	 * Filters `dt_format_post_activity`, so it reads on the record's own
	 * Disciple.Tools page rather than only inside this app.
	 *
	 * Entries written before this existed carry a plain `magic_link` and are left
	 * alone - there is nothing recorded in them to recover a name from.
	 *
	 * @param array $activity
	 * @param object $row the raw activity row
	 *
	 * @return array
	 */
	public static function name_activity( $activity, $row ) {
		$caps = $row->user_caps ?? '';

		if ( strpos( $caps, self::ACTIVITY_PREFIX ) !== 0 ) {
			return $activity;
		}

		$contact_id = (int) substr( $caps, strlen( self::ACTIVITY_PREFIX ) );
		$name       = $contact_id ? get_the_title( $contact_id ) : '';

		$activity['name'] = $name
			/* translators: %s: the name of the contact who made the change. */
			? sprintf( __( '%s (via Autolink)', 'disciple-tools-autolink' ), $name )
			: __( 'Autolink', 'disciple-tools-autolink' );

		// Disciple.Tools labels anything created with nobody signed in as the work
		// of a site link - see hooks_new_post() - which is not what happened. The
		// phrase it writes is a bare literal, never translated, so matching it is
		// safe in any locale; and only rows this app signed are touched, so real
		// site link activity is left alone.
		if ( strpos( (string) ( $activity['object_note'] ?? '' ), 'Created with site link: ' ) === 0 ) {
			$activity['object_note'] = $name
				/* translators: %s: the name of the contact who created the record. */
				? sprintf( __( 'Created through Autolink by %s', 'disciple-tools-autolink' ), $name )
				: __( 'Created through Autolink', 'disciple-tools-autolink' );
		}

		return $activity;
	}
}
