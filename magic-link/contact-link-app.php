<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

/**
 * Class Disciple_Tools_Autolink_Magic_Contact_Link_App
 *
 * The whole Autolink app, served to a contact who has no Disciple.Tools login.
 *
 * The user app at `autolink/app/<key>` runs inside a real WP session. This one,
 * at `autolink/contact/<key>`, runs with no session at all: holding the key
 * proves which contact record the visitor is, and nothing more. There is no
 * capability grant - every record the app reads or writes is checked against
 * the actor's own churches and the churches of the people they coach.
 *
 * @see Disciple_Tools_Autolink_Magic_User_App
 * @see Disciple_Tools_Autolink_Actor
 */
class Disciple_Tools_Autolink_Magic_Contact_Link_App extends DT_Magic_Url_Base {
	use Disciple_Tools_Autolink_App_Routes;

	private static $_instance = null;
	// Named for the Apps tile, where Autolink's links sit side by side. The
	// browser tab still reads "Autolink" - see page_tab_title().
	public $page_title = 'Autolink - Contact App';
	public $page_description = 'The Autolink app for a contact with no login. Anyone holding this link can use the app as this contact.';
	public $root = 'autolink';
	public $type = 'contact';
	public $type_name = 'Autolink App';
	public $post_type = 'contacts';
	public $show_bulk_send = true;
	public $show_app_tile = true; // show this magic link in the Apps tile on the contact record
	public $meta = [];

	/**
	 * A contact has no user locale to read, so fall back to the languages on the
	 * record. `?lang=` still wins, as it does everywhere else.
	 */
	public $translatable = [ 'query', 'contact' ];

	private $meta_key = 'autolink-contact';

	public function __construct() {
		/**
		 * Specify metadata structure, specific to the processing of current
		 * magic link type.
		 *
		 * - meta:              Magic link plugin related data.
		 *      - app_type:     Flag indicating type to be processed by magic link plugin.
		 *      - post_type     Magic link type post type.
		 *      - contacts_only:    Boolean flag indicating how magic link type user assignments are to be handled within magic link plugin.
		 *                          If True, lookup field to be provided within plugin for contacts only searching.
		 *                          If false, Dropdown option to be provided for user, team or group selection.
		 *      - fields:       List of fields to be displayed within magic link frontend form.
		 */
		$this->meta = [
			'app_type'      => 'magic_link',
			'post_type'     => $this->post_type,
			'contacts_only' => true,
			'fields'        => [
				[
					'id'    => 'name',
					'label' => 'Name'
				]
			]
		];

		$this->meta_key = $this->root . '_' . $this->type . '_magic_key';

		parent::__construct();

		$this->init_app();
		$this->maybe_load_genmap();

		// The updatable field whitelist is registered globally by the user app,
		// so both links share one list.
		add_action( 'rest_api_init', [ $this, 'add_endpoints' ] );
		// Registered before the url check below, because the request this opens up
		// is a REST call whose url never matches this link's own path.
		add_filter( 'dt_allow_rest_access', [ $this, 'authorize_genmap_rest_url' ], 10, 1 );
		// Narrow the Apps tile to one Autolink link per contact. The theme renders
		// the tile at priority 110, so the registry is reshaped just around it.
		add_action( 'dt_details_additional_section', [ $this, 'begin_apps_tile' ], 109, 2 );
		add_action( 'dt_details_additional_section', [ $this, 'end_apps_tile' ], 111, 2 );

		/**
		 * tests if other URL
		 */
		$url         = dt_get_url_path();
		$current_url = $this->root . '/' . $this->type;

		if ( strpos( $url, $current_url ) === false ) {
			return;
		}

		/**
		 * tests magic link parts are registered and have valid elements
		 */
		if ( ! $this->check_parts_match() ) {
			return;
		}

		// A contact with a login of their own signs in. Their key - from an old
		// tile, a bulk send, or one minted before the login existed - would
		// otherwise reach everything their account is assigned.
		if ( Disciple_Tools_Autolink_Actor::contact_has_login( $this->parts['post_id'] ) ) {
			$this->functions->redirect_to_link();
		}

		$this->become_contact();

		add_action( 'dt_blank_body', function () {
			$this->ready();
			$this->routes();
		} );
		add_filter( 'dt_magic_url_base_allowed_css', [ $this->functions, 'dt_magic_url_base_allowed_css' ], 10, 1 );
		add_filter( 'dt_magic_url_base_allowed_js', [ $this->functions, 'dt_magic_url_base_allowed_js' ], 10, 1 );
		add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 100 );
	}

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Act as the contact the link belongs to.
	 *
	 * The visitor stays logged out. The anonymous WP_User is given the
	 * `magic_link` cap and the contact's name purely so anything DT writes on
	 * their behalf - comments, activity rows - is attributed to a person rather
	 * than to nobody. It carries no Disciple.Tools capability, which is why
	 * every DT_Posts call made for this actor passes `$check_permissions = false`
	 * and is scoped by Disciple_Tools_Autolink_Actor instead.
	 */
	private function become_contact() {
		$contact_id = (int) $this->parts['post_id'];
		$actor      = Disciple_Tools_Autolink_Actor::set_contact( $contact_id );
		Disciple_Tools_Autolink_Magic_Functions::set_app( $this );

		if ( is_user_logged_in() ) {
			return;
		}

		wp_set_current_user( 0 );
		$current_user = wp_get_current_user();
		Disciple_Tools_Autolink_Actor::sign_activity( $contact_id );
		$current_user->display_name = $actor->display_name();
	}

	public function ready() {
		$this->become_contact();
	}

	/**
	 * The actor a verified REST call is acting as.
	 *
	 * The permission callback has already matched the submitted key to this
	 * post id, so it - not the session - decides whose data the call may touch.
	 *
	 * @param array $parts
	 *
	 * @return Disciple_Tools_Autolink_Actor
	 */
	public function rest_actor( $parts ) {
		Disciple_Tools_Autolink_Magic_Functions::set_app( $this );
		$actor = Disciple_Tools_Autolink_Actor::set_contact( (int) ( $parts['post_id'] ?? 0 ) );

		if ( ! is_user_logged_in() ) {
			wp_set_current_user( 0 );
			$current_user = wp_get_current_user();
			Disciple_Tools_Autolink_Actor::sign_activity( (int) ( $parts['post_id'] ?? 0 ) );
			$current_user->display_name = $actor->display_name();
		}

		return $actor;
	}

	/**
	 * The locale to serve a contact the page in.
	 *
	 * The language they chose in the app comes first. Only when they have not
	 * chosen one does the theme fall back to the spoken languages on the record.
	 *
	 * @see Disciple_Tools_Autolink_Actor::set_locale()
	 *
	 * @param array $parts
	 *
	 * @return string
	 */
	public function fetch_incoming_contact_lang( $parts = [] ): string {
		$contact_id = (int) ( $parts['post_id'] ?? 0 );
		$locale     = $contact_id ? (string) get_post_meta( $contact_id, 'dt_autolink_locale', true ) : '';

		if ( $locale && in_array( $locale, wp_list_pluck( dt_get_available_languages(), 'language' ), true ) ) {
			return $locale;
		}

		return parent::fetch_incoming_contact_lang( $parts );
	}

	/**
	 * Start reshaping the magic link registry for the Apps tile.
	 *
	 * @param string $section
	 * @param string $post_type
	 */
	public function begin_apps_tile( $section, $post_type ) {
		if ( $section === 'apps' && $post_type === $this->post_type ) {
			add_filter( 'dt_magic_url_register_types', [ $this, 'one_autolink_app' ], 1000, 1 );

			// The tile is drawn for anyone who can view the record, but the link
			// is the whole app as this contact - it writes to their churches and
			// the people they coach. Only someone who could make those edits
			// themselves is handed it, and only for them is a key minted.
			if ( $this->can_hand_over( get_the_ID() ) ) {
				// Left to itself the theme would mint a key that never expires.
				Disciple_Tools_Autolink_Contact_Links::prepare_for_tile( get_the_ID() );
			}
		}
	}

	/**
	 * Whether the person viewing a contact may be given that contact's link.
	 *
	 * @param int $contact_id
	 *
	 * @return bool
	 */
	private function can_hand_over( $contact_id ) {
		return $contact_id && DT_Posts::can_update( $this->post_type, (int) $contact_id );
	}

	/**
	 * Put the registry back once the tile is drawn, so nothing else - REST key
	 * checks, bulk send - ever sees the narrowed version.
	 */
	public function end_apps_tile() {
		remove_filter( 'dt_magic_url_register_types', [ $this, 'one_autolink_app' ], 1000 );
	}

	/**
	 * One Autolink row on a contact's Apps tile, not three.
	 *
	 * Autolink registers three links, and a contact record could show all of
	 * them: the share link, this contact link, and - for administrators, on a
	 * contact with a login - the user link. They cannot become one URL, since
	 * each carries a different key to a different route, but only one of them is
	 * ever the right thing to hand this person:
	 *
	 * - no login: this contact link, which is the whole app - and so only for
	 *             a viewer who may edit the contact. Anyone else sees none.
	 * - a login:  the share link. It signs them in to their own app, and anyone
	 *             who registers through it lands under them. The contact link is
	 *             withheld - see may_act_as() - and the user link only ever leads
	 *             to the same sign in.
	 *
	 * @param array $types
	 *
	 * @return array
	 */
	public function one_autolink_app( $types ) {
		if ( empty( $types[ $this->root ] ) ) {
			return $types;
		}

		$has_login = Disciple_Tools_Autolink_Actor::contact_has_login( get_the_ID() );

		if ( isset( $types[ $this->root ][ $this->type ] ) ) {
			// Hidden rather than shown empty: the theme mints a key for any app
			// it lists, so listing it at all would hand one over.
			$types[ $this->root ][ $this->type ]['show_app_tile'] = ! $has_login && $this->can_hand_over( get_the_ID() );
		}

		if ( isset( $types[ $this->root ]['share'] ) ) {
			$types[ $this->root ]['share']['show_app_tile'] = $has_login;
		}

		// The theme lists user links on a user's contact for administrators
		// regardless of show_app_tile, so the only way to leave it off is to leave
		// it out.
		unset( $types[ $this->root ]['app'] );

		return $types;
	}

	/**
	 * Handle the header style hook
	 *
	 * Register the mapbox search widget css
	 */
	public function header_style() {
		DT_Mapbox_API::mapbox_search_widget_css();
	}
}

Disciple_Tools_Autolink_Magic_Contact_Link_App::instance();
