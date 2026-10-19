<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

/**
 * Everything the user magic link and the contact magic link do identically:
 * build the controllers, map URLs onto them, and expose the REST endpoints.
 *
 * The two links differ only in who they act as, which they settle by handing an
 * actor to Disciple_Tools_Autolink_Actor before any of this runs. Everything
 * below is deliberately identity free.
 */
trait Disciple_Tools_Autolink_App_Routes {
	public $functions;
	public $survey_controller;
	public $app_controller;
	public $genmap_controller;
	public $group_controller;
	public $tree_controller;
	public $login_controller;
	public $field_controller;
	public $training_controller;
	public $language_controller;
	public $contacts_controller;
	public $upgrade_controller;

	/**
	 * Build the controllers this app routes to.
	 */
	public function init_app() {
		$this->functions           = Disciple_Tools_Autolink_Magic_Functions::instance();
		$this->survey_controller   = new Disciple_Tools_Autolink_Survey_Controller();
		$this->app_controller      = new Disciple_Tools_Autolink_App_Controller();
		$this->genmap_controller   = new Disciple_Tools_Autolink_Genmap_Controller();
		$this->group_controller    = new Disciple_Tools_Autolink_Group_Controller();
		$this->tree_controller     = new Disciple_Tools_Autolink_Tree_Controller();
		$this->login_controller    = new Disciple_Tools_Autolink_Login_Controller();
		$this->field_controller    = new Disciple_Tools_Autolink_Field_Controller();
		$this->training_controller = new Disciple_Tools_Autolink_Training_Controller();
		$this->language_controller = new Disciple_Tools_Autolink_Language_Controller();
		$this->contacts_controller = new Disciple_Tools_Autolink_Contacts_Controller();
		$this->upgrade_controller   = new Disciple_Tools_Autolink_Upgrade_Controller();
		// Genmapper isn't loaded on every request
		$this->functions->init_genmapper();
	}

	/**
	 * Genmapper is an optional dependency. `&&` binds tighter than `||`, so the
	 * class_exists() check used to apply only to the genmap screen and every REST
	 * request loaded the chart - which fatals on a site without the plugin.
	 *
	 * Both magic links call this, but the chart registers its own routes and
	 * scripts on construction, so it is only ever built once per request.
	 */
	public function maybe_load_genmap() {
		static $loaded = false;

		if ( $loaded ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_GET['action'] ?? '' ) );

		if ( ( dt_is_rest() || $action === 'genmap' ) && class_exists( 'DT_Genmapper_Metrics' ) ) {
			require_once __DIR__ . '/../charts/groups-genmap.php';

			if ( class_exists( 'Disciple_Tools_Autolink_Genmap' ) ) {
				new Disciple_Tools_Autolink_Genmap();
				$loaded = true;
			}
		}
	}

	/**
	 * Let a magic link visitor reach the genmap's own REST route.
	 *
	 * The theme refuses the REST API to anyone not logged in, and
	 * DT_Magic_Url_Base::authorize_url() only opens up the link's own
	 * `autolink/v1/<type>` namespace. The genmap chart registers elsewhere, under
	 * `dt/v1/autolink/group-tree`, so for a contact link - which has no session at
	 * all - that request came back 401. The theme's global ajaxComplete handler
	 * reloads the page on a 401, the reloaded page asked again, and the screen sat
	 * in a reload loop behind an "undefined" alert.
	 *
	 * Opening the route is safe because it carries no implicit trust: groups_tree()
	 * verifies the submitted parts against the stored key before deciding whose
	 * churches to return, and returns an empty tree when they do not match.
	 *
	 * @param bool $authorized
	 *
	 * @return bool
	 */
	public function authorize_genmap_rest_url( $authorized ) {
		if ( $authorized ) {
			return $authorized;
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( strpos( $uri, 'dt/v1/autolink/group-tree' ) === false ) {
			return $authorized;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['parts'] ) ) {
			return $authorized;
		}

		return true;
	}

	/**
	 * The browser tab title.
	 *
	 * page_title tells Autolink's links apart where they are listed together, so
	 * it names which link this is. The person using the app only needs to know
	 * which app they are in.
	 *
	 * @return string
	 */
	public function page_tab_title() {
		return 'Autolink';
	}

	/**
	 * Map routes to controllers
	 */
	public function routes() {
		$actor  = Disciple_Tools_Autolink_Actor::current();
		$action = sanitize_key( wp_unslash( $_GET['action'] ?? '' ) );
		$type   = strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) );

		if ( $type === 'GET' ) {
			switch ( $action ) {
				case 'survey':
					$this->survey_controller->show();
					break;
				case 'edit-group':
					$this->group_controller->edit();
					break;
				case 'create-group':
					$this->group_controller->create();
					break;
				case 'delete-group':
					$this->group_controller->delete();
					break;
				case 'genmap':
					$this->genmap_controller->show();
					break;
				case 'tree':
					$this->tree_controller->show();
					break;
				case 'contacts':
					$this->contacts_controller->show();
					break;
				case 'add-contact':
					$this->contacts_controller->create();
					break;
				case 'edit-contact':
					$this->contacts_controller->edit();
					break;
				case 'view-contact':
					$this->contacts_controller->view();
					break;
				case 'upgrade':
					$this->upgrade_controller->show();
					break;
				case 'logout':
					// A contact link has no session to end, so there is nothing
					// to log out of - send them back to their own app instead.
					if ( ! $actor->can_logout() ) {
						wp_redirect( $this->functions->get_app_link() );
						break;
					}
					$this->login_controller->logout();
					break;
				case 'training':
					// The controller redirects back to the app when training is disabled.
					$this->training_controller->show();
					break;
				case 'group':
					$this->group_controller->show();
					break;
				default:
					if ( ! $this->functions->survey_completed() && $this->functions->survey_enabled() ) {
						return wp_redirect( $this->functions->get_app_link() . '?action=survey' );
					}
					$this->app_controller->show();
					break;
			}

			return;
		}

		if ( $type === 'POST' ) {
			switch ( $action ) {
				case 'survey':
					$this->survey_controller->process();
					break;
				case 'create-group':
					$this->group_controller->store();
					break;
				case 'edit-group':
					$this->group_controller->update();
					break;
				case 'add-contact':
					$this->contacts_controller->store();
					break;
				case 'edit-contact':
					$this->contacts_controller->update();
					break;
				case 'upgrade':
					$this->upgrade_controller->process();
					break;
				default:
					wp_redirect( '/' . $this->root );
			}

			return;
		}
	}

	public function wp_enqueue_scripts() {
		$this->functions->wp_enqueue_scripts();
		wp_localize_script(
			'magic_link_scripts',
			'magic',
			[
				'parts'          => $this->parts,
				'rest_namespace' => $this->root . '/v1/' . $this->type,
			]
		);
	}

	/**
	 * Register REST Endpoints
	 *
	 * @link https://github.com/DiscipleTools/disciple-tools-theme/wiki/Site-to-Site-Link for outside of wordpress authentication
	 */
	public function add_endpoints() {
		$namespace = $this->root . '/v1';
		register_rest_route(
			$namespace,
			'/' . $this->type,
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'endpoint_get' ],
					'permission_callback' => [ $this, 'rest_permission' ],
				],
			]
		);
		register_rest_route(
			$namespace,
			'/' . $this->type,
			[
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'endpoint_post' ],
					'permission_callback' => [ $this, 'rest_permission' ],
				],
			]
		);
	}

	/**
	 * Gate the REST endpoints.
	 *
	 * The theme opens this namespace to visitors with no session, so the key
	 * check below is all that stands in front of it. A valid key is not enough
	 * on its own - see Disciple_Tools_Autolink_Actor::may_act_as() for what each
	 * link additionally requires.
	 *
	 * The meta key is pinned to this link's own. The theme looks the submitted
	 * public key up under whichever meta key the request names, and then only
	 * compares that key with itself - so without this, any postmeta pair would
	 * "verify" as a key and the caller could act as whatever record it found.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return bool
	 */
	public function rest_permission( WP_REST_Request $request ) {
		$magic = new DT_Magic_URL( $this->root );
		$parts = $magic->verify_rest_endpoint_permissions_on_post( $request, true );

		if ( empty( $parts ) ) {
			return false;
		}

		if ( ( $parts['meta_key'] ?? '' ) !== DT_Magic_URL::get_public_key_meta_key( $this->root, $this->type ) ) {
			return false;
		}

		return Disciple_Tools_Autolink_Actor::may_act_as( $parts );
	}

	public function endpoint_post( WP_REST_Request $request ) {
		$params = $request->get_params();
		$params = dt_recursive_sanitize_array( $params );

		if ( ! isset( $params['parts'], $params['action'] ) ) {
			return new WP_Error( __METHOD__, 'Missing parameters', [ 'status' => 400 ] );
		}

		// The permission callback has already proved the caller holds this
		// link's key, so the actor comes from the verified parts rather than
		// from whatever session happens to be attached to the request.
		$actor = $this->rest_actor( $params['parts'] );

		switch ( $params['action'] ) {
			case 'tree':
				return $this->tree_controller->data( $request, $params, $actor );
			case 'onItemDrop':
				return $this->tree_controller->process( $request, $params, $actor );
			case 'update_field':
				return $this->field_controller->update( $request, $params, $actor );
			case 'switch_language':
				return $this->language_controller->update( $request, $params, $actor );
			case 'contact_link':
				return $this->contacts_controller->link( $request, $params, $actor );
			case 'genmap_create_node':
				return $this->genmap_controller->create_node( $request, $params, $actor );
			case 'genmap_update_node':
				return $this->genmap_controller->update_node( $request, $params, $actor );
			default:
				return new WP_Error( __METHOD__, 'Invalid action', [ 'status' => 400 ] );
		}
	}

	/**
	 * Route REST endpoints by action
	 *
	 * @param WP_REST_Request $request
	 */
	public function endpoint_get( WP_REST_Request $request ) {
		$params = $request->get_params();
		if ( ! isset( $params['parts'], $params['action'] ) ) {
			return new WP_Error( __METHOD__, 'Missing parameters', [ 'status' => 400 ] );
		}

		$this->rest_actor( dt_recursive_sanitize_array( $params['parts'] ) );

		switch ( $params['action'] ) {
			case 'parent_group_field':
				return $this->group_controller->parent_group_field();
			case 'groups':
				return $this->group_controller->index();
			default:
				return new WP_Error( __METHOD__, 'Invalid action', [ 'status' => 400 ] );
		}
	}
}
