<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

/**
 * Class Disciple_Tools_Autolink_Magic_User_App
 *
 * Autolink for someone with a Disciple.Tools login. The link is keyed to their
 * WP user, and the app runs inside their own session with their own
 * capabilities.
 *
 * @see Disciple_Tools_Autolink_Magic_Contact_Link_App for the same app served to a
 *      contact who has no login at all.
 */
class Disciple_Tools_Autolink_Magic_User_App extends DT_Magic_Url_Base {
	use Disciple_Tools_Autolink_App_Routes;

	private static $_instance = null;
	// Named for the Apps tile and the user's own app list, where Autolink's
	// links sit side by side. The browser tab still reads "Autolink" - see
	// page_tab_title().
	public $page_title = 'Autolink - User App';
	public $page_description = 'The Autolink app for someone with a Disciple.Tools login. Opening it asks them to sign in.';
	public $root = "autolink";
	public $type = 'app';
	public $post_type = 'user';
	public $show_bulk_send = false;
	public $show_app_tile = false;
	public $meta = [];
	private $meta_key = 'autolink-app'; // Allows for instance specific data.

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
			'contacts_only' => false,
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

		/**
		 * user_app and module section
		 */
		add_filter( 'dt_settings_apps_list', [ $this, 'dt_settings_apps_list' ], 10, 1 );
		add_filter( 'autolink_health_fields', 'autolink_health_fields', 10, 1 );
		add_filter( 'autolink_updatable_group_fields', [ $this, 'autolink_updatable_group_fields' ], 10, 1 );
		add_action( 'rest_api_init', [ $this, 'add_endpoints' ] );

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

		// if the user is not logged in, redirect to login page.
		if ( ! is_user_logged_in() ) {
			$this->functions->redirect_to_link();
		}

		// load if valid url
		wp_set_current_user( $this->parts['post_id'] );
		Disciple_Tools_Autolink_Actor::set_user( $this->parts['post_id'] );
		Disciple_Tools_Autolink_Magic_Functions::set_app( $this );
		add_filter( 'user_has_cap', [ $this, 'user_has_cap' ], 100, 3 );
		add_action( 'dt_blank_body', function () {
			$this->ready();
			$this->routes();
		} );
		add_filter( 'dt_magic_url_base_allowed_css', [ $this->functions, 'dt_magic_url_base_allowed_css' ], 10, 1 );
		add_filter( 'dt_magic_url_base_allowed_js', [ $this->functions, 'dt_magic_url_base_allowed_js' ], 10, 1 );
		add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 100 );
	} // End instance()

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function ready() {
		wp_set_current_user( $this->parts['post_id'] );
		Disciple_Tools_Autolink_Actor::set_user( $this->parts['post_id'] );
		Disciple_Tools_Autolink_Magic_Functions::set_app( $this );
		$this->functions->add_session_leader();
	}

	/**
	 * The actor a verified REST call is acting as.
	 *
	 * For a user link the magic key resolves to the user id itself, so the
	 * verified parts identify the actor without trusting the session.
	 *
	 * @param array $parts
	 *
	 * @return Disciple_Tools_Autolink_Actor
	 */
	public function rest_actor( $parts ) {
		Disciple_Tools_Autolink_Magic_Functions::set_app( $this );

		return Disciple_Tools_Autolink_Actor::set_user( (int) ( $parts['post_id'] ?? get_current_user_id() ) );
	}

	/**
	 * Builds magic link type settings payload:
	 * - key:               Unique magic link type key; which is usually composed of root, type and _magic_key suffix.
	 * - url_base:          URL path information to map with parent magic link type.
	 * - label:             Magic link type name.
	 * - description:       Magic link type description.
	 * - settings_display:  Boolean flag which determines if magic link type is to be listed within frontend user profile settings.
	 *
	 * @param array $apps_list
	 *
	 * @return mixed
	 */
	public function dt_settings_apps_list( $apps_list ) {
		$apps_list[ $this->meta_key ] = [
			'key'              => $this->meta_key,
			'url_base'         => $this->root . '/' . $this->type,
			'label'            => $this->page_title,
			'description'      => $this->page_description,
			'settings_display' => true
		];

		return $apps_list;
	}

	/**
	 * Handle the header style hook
	 *
	 * Register the mapbox search widget css
	 */
	public function header_style() {
		DT_Mapbox_API::mapbox_search_widget_css();
	}

	/**
	 * Make sure the user can do everything we need them to do during this request.
	 *
	 * @param array $allcaps Existing capabilities for the user
	 * @param string $caps Capabilities provided by map_meta_cap()
	 * @param array $args Arguments for current_user_can()
	 *
	 * @return array
	 * @see WP_User::has_cap() in wp-includes/capabilities.php
	 */
	public function user_has_cap( $allcaps, $caps, $args ) {
		$allcaps['view_any_contacts'] = true;

		return $allcaps;
	}

	public function autolink_updatable_group_fields( $fields ) {
		return array_merge( $this->autolink_health_fields( $fields ), [
			'health_metrics'
		] );
	}

	public function autolink_health_fields( $fields ) {
		return array_merge( [
			'member_count',
			'leader_count',
			'believer_count',
			'baptized_count',
			'baptized_in_group_count'
		], $fields );
	}
}

Disciple_Tools_Autolink_Magic_User_App::instance();
