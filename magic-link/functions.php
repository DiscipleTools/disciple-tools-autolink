<?php

class Disciple_Tools_Autolink_Magic_Functions {

	private static $_instance = null;

	/**
	 * The magic link class serving this request.
	 *
	 * Controllers need the link's `parts` to build their own REST urls, and
	 * which link that is now depends on whether a user or a contact is using the
	 * app. Before contact links existed this was simply hardcoded to the user app.
	 *
	 * @var DT_Magic_Url_Base|null
	 */
	private static $app = null;

	public static function set_app( $app ) {
		self::$app = $app;
	}

	/**
	 * @return DT_Magic_Url_Base
	 */
	public static function app() {
		if ( self::$app === null ) {
			self::$app = Disciple_Tools_Autolink_Magic_User_App::instance();
		}

		return self::$app;
	}

	public function dt_magic_url_base_allowed_js( $allowed_js ) {
		// The theme's web component library. Autolink's components extend it,
		// so it has to survive the magic link script allow list.
		$allowed_js[] = 'web-components';
		$allowed_js[] = 'magic_link_scripts';
		$allowed_js[] = 'gen-template';
		$allowed_js[] = 'genApiTemplate';
		$allowed_js[] = 'genmapper';
		$allowed_js[] = "d3";
		$allowed_js[] = "dt_groups_wpApiGenmapper";
		$allowed_js[] = 'wp-i18n';
		$allowed_js[] = 'jquery';
		$allowed_js[] = 'jquery-ui-core';
		$allowed_js[] = 'dt_groups_script';
		$allowed_js[] = 'mapbox-search-widget';
		$allowed_js[] = 'mapbox-gl';
		$allowed_js[] = 'mapbox-cookie';
		$allowed_js[] = 'jquery-cookie';
		$allowed_js[] = 'mapbox-search-widget';
		$allowed_js[] = 'jquery-touch-punch';
		$allowed_js[] = 'portal-app-domenu-js';
		$allowed_js[] = 'google-search-widget';
		$allowed_js[] = 'shared-functions';

		return $allowed_js;
	}

	public function dt_magic_url_base_allowed_css( $allowed_css ) {
		$allowed_css[] = 'web-components-css';
		$allowed_css[] = 'magic_link_css';
		$allowed_css[] = "hint";
		$allowed_css[] = 'group-styles';
		$allowed_css[] = "styles";
		$allowed_css[] = 'chart-styles';
		$allowed_css[] = 'mapbox-gl-css';
		$allowed_css[] = 'portal-app-domenu-css';

		return $allowed_css;
	}

	public function wp_enqueue_scripts() {
		$plugin_url   = plugins_url() . '/disciple-tools-autolink';
		$plugin_path  = WP_PLUGIN_DIR . '/disciple-tools-autolink';
		$post_type    = get_post_type_object( 'groups' );
		$group_labels = get_post_type_labels( $post_type );
		$actor        = Disciple_Tools_Autolink_Actor::current();

		// `web-components` is a dependency so the theme's library is defined
		// before our components try to extend it.
		wp_enqueue_script( 'magic_link_scripts', $plugin_url . '/dist/magic-link.js', [
			'jquery',
			'lodash',
			'web-components',
			// Versioned by the built bundle, not by the entry source file. Editing
			// any other module rebuilds dist/ without touching magic-link.js, so
			// keying off the source left browsers holding a stale bundle - new
			// markup from PHP against old behaviour from JavaScript.
		], filemtime( $plugin_path . '/dist/magic-link.js' ), true );

		wp_enqueue_script( 'lodash' );

		wp_localize_script(
			'magic_link_scripts',
			'app',
			[
				'map_key'       => DT_Mapbox_API::get_key(),
				'rest_base'     => esc_url( rest_url() ),
				'nonce'         => wp_create_nonce( 'wp_rest' ),
				'show_training' => $this->training_enabled(),
				'show_survey'   => $this->survey_enabled(),
				'show_logout'   => $actor->can_logout(),
				'is_contact'    => $actor->is_contact(),
				'languages'     => $this->available_languages(),
				'urls'         => [
					'root'           => esc_url_raw( trailingslashit( site_url() ) ),
					'home'           => esc_url_raw( trailingslashit( home_url() ) ),
					'current'        => esc_url_raw( dt_get_url_path( true ) ),
					'app'            => esc_url_raw( trailingslashit( $this->get_app_link() ) ),
					'link'           => esc_url_raw( trailingslashit( $this->get_link_url() ) ),
					'survey'         => esc_url_raw( $this->get_app_link() . '?action=survey' ),
					// A contact link has no session, so there is nothing to log out of.
					'logout'         => $actor->can_logout() ? $this->get_app_link() . '?action=logout' : '',
					'reset_password' => wp_lostpassword_url( $this->get_link_url() ),
					'training'       => esc_url_raw( $this->get_training_url() )
				],
				'translations' => [
					'add'                => __( 'Add Magic', 'disciple-tools-autolink' ),
					'dt_nav_label'       => __( 'Go to Disciple.Tools', 'disciple-tools-autolink' ),
					'survey_nav_label'   => __( 'Update Survey Answers', 'disciple-tools-autolink' ),
					'feedback_nav_label' => __( 'Give Feedback', 'disciple-tools-autolink' ),
					'logout_nav_label'   => __( 'Log Out', 'disciple-tools-autolink' ),
					'training_nav_label' => __( 'Training', 'disciple-tools-autolink' ),
					'toggle_menu'        => __( 'Toggle Menu', 'disciple-tools-autolink' ),
					'language_nav_label' => __( 'Language', 'disciple-tools-autolink' ),
					'user_greeting,'     => __( 'Hello,', 'disciple-tools-autolink' ),
					'coached_by'         => __( 'Coached by', 'disciple-tools-autolink' ),
					'my_link'            => __( 'My Link', 'disciple-tools-autolink' ),
					'coaching_links'     => __( 'Links for those you coach', 'disciple-tools-autolink' ),
					/* translators: %s: plural name of the group post type, e.g. "Churches". */
					'my_churches'        => sprintf( __( 'My %s', 'disciple-tools-autolink' ), $group_labels->name ),
					'close'              => __( 'Close', 'disciple-tools-autolink' ),
				]
			]
		);

		wp_enqueue_style( 'magic_link_css', $plugin_url . '/dist/magic-link.css', [], filemtime( $plugin_path . '/dist/magic-link.css' ) );
	}

	/**
	 * Get the magic link url
	 * @return string
	 */
	public function get_app_link() {
		return Disciple_Tools_Autolink_Actor::current()->app_link();
	}

	/**
	 * Get the magic link url
	 * @return string
	 */
	public function get_link_url() {
		return get_site_url( null, 'autolink' );
	}

	public function get_training_url() {
		return $this->get_app_link() . '?action=training';
	}

	/**
	 * Whether the training section is available.
	 *
	 * @return bool
	 */
	public function training_enabled() {
		$settings = new Disciple_Tools_Autolink_Settings();

		return $settings->training_enabled();
	}

	/**
	 * The languages the app can be switched to, flagged with the user's current one.
	 *
	 * Mirrors the theme's own profile language picker (`dt_language_select()`),
	 * so the list is whatever translations the theme ships.
	 *
	 * @param int|null $user_id
	 *
	 * @return array
	 */
	public function available_languages( $user_id = null ) {
		$user_locale = $user_id === null
			? Disciple_Tools_Autolink_Actor::current()->locale()
			: get_user_locale( $user_id );
		$languages   = [];

		foreach ( dt_get_available_languages() as $language ) {
			$code  = $language['language'] ?? '';
			$label = $language['native_name'] ?? ( $language['english_name'] ?? $code );

			if ( ! $code ) {
				continue;
			}

			$languages[] = [
				'code'     => $code,
				'label'    => trim( ( $language['flag'] ?? '' ) . ' ' . $label ),
				'selected' => $user_locale === $code,
			];
		}

		return $languages;
	}

	/**
	 * Whether the survey section is available.
	 *
	 * @return bool
	 */
	public function survey_enabled() {
		$settings = new Disciple_Tools_Autolink_Settings();

		return $settings->survey_enabled();
	}

	/**
	 * Activate the app if it's not already activated
	 */
	public function activate() {
		global $wpdb;

		$preference_key = 'autolink-app';
		$meta_key       = $wpdb->prefix . DT_Magic_URL::get_public_key_meta_key( 'autolink', 'app' );

		if ( ! $this->is_activated() ) {
			delete_user_meta( get_current_user_id(), $meta_key );
			delete_user_option( get_current_user_id(), $preference_key );

			add_user_meta( get_current_user_id(), $meta_key, DT_Magic_URL::create_unique_key() );
			Disciple_Tools_Users::app_switch( get_current_user_id(), $preference_key );
		}

		$preference_key = 'autolink-share';
		$meta_key       = $wpdb->prefix . DT_Magic_URL::get_public_key_meta_key( 'autolink', 'share' );

		if ( ! $this->is_activated() ) {
			delete_user_meta( get_current_user_id(), $meta_key );
			delete_user_option( get_current_user_id(), $preference_key );

			add_user_meta( get_current_user_id(), $meta_key, DT_Magic_URL::create_unique_key() );
			Disciple_Tools_Users::app_switch( get_current_user_id(), $preference_key );
		}
	}

	public function is_activated() {
		global $wpdb;
		$preference_key = 'autolink-app';
		$meta_key       = $wpdb->prefix . DT_Magic_URL::get_public_key_meta_key( 'autolink', 'app' );
		$public         = get_user_meta( get_current_user_id(), $meta_key, true );
		$secret         = get_user_option( $preference_key );

		if ( $public === '' || $public === false || $public === '0' || $secret === '' || $secret === false || $secret === '0' ) {
			return false;
		}

		return true;
	}

	public function get_create_group_url() {
		return $this->get_app_link() . '?action=create-group';
	}

	public function get_edit_group_url() {
		return $this->get_app_link() . '?action=edit-group';
	}

	public function redirect_to_app() {
		wp_redirect( $this->get_app_link() );
		exit;
	}

	public function redirect_to_link() {
		wp_redirect( $this->get_link_url() );
		exit;
	}

	public function is_json( $string ) {
		json_decode( $string );

		return ( json_last_error() == JSON_ERROR_NONE );
	}

	public function add_session_leader() {
		if ( ! isset( $_COOKIE['dt_autolink_leader_id'] ) ) {
			return;
		}
		$leader_id = sanitize_text_field( wp_unslash( (string) $_COOKIE['dt_autolink_leader_id'] ) ) ?? null;
		if ( ! $leader_id ) {
			return;
		}

		$contact        = Disciple_Tools_Users::get_contact_for_user( get_current_user_id() );
		$contact_record = DT_Posts::get_post( 'contacts', $contact, true, false );
		$leader         = DT_Posts::get_post( 'contacts', $leader_id, true, false );

		if ( ! count( $contact_record['coached_by'] ) ) {
			$fields = [
				"coached_by"  => [
					"values"       => [
						[ "value" => $leader_id ],
					],
					"force_values" => false
				],
				'assigned_to' => (string) $leader['corresponds_to_user']
			];

			DT_Posts::update_post( 'contacts', $contact, $fields, true, false );
		}

		if ( isset( $_COOKIE['dt_autolink_leader_id'] ) ) {
			unset( $_COOKIE['dt_autolink_leader_id'] );
			setcookie( 'dt_autolink_leader_id', '', time() - 3600, '/' );
		}
	}

	public function survey_completed() {
		$survey     = $this->survey();
		$contact_id = Disciple_Tools_Autolink_Actor::current()->contact_id();

		if ( ! $contact_id ) {
			return true;
		}

		$post_meta = get_post_meta( $contact_id );
		foreach ( $survey as $question ) {
			if ( ! isset( $post_meta[ $question['name'] ] ) ) {
				return false;
			}
		}

		return true;
	}

	public function survey(): array {
		$post_type    = get_post_type_object( 'groups' );
		$group_labels = get_post_type_labels( $post_type );

		$survey = apply_filters( 'dt_autolink_survey', [
			[
				'name'  => 'dt_autolink_number_of_leaders_coached',
				'label' => __( 'How many leaders are you coaching?', 'disciple-tools-autolink' )
			],
			[
				'name'  => 'dt_autolink_number_of_churches_led',
				/* translators: %s: lowercased plural name of the group post type, e.g. "churches". */
				'label' => sprintf( __( 'How many %s are you leading?', 'disciple-tools-autolink' ), strtolower( $group_labels->name ) ),

			]
		] );
		if ( ! is_array( $survey ) ) {
			return [];
		}

		return $survey;
	}

	public function shared_app_data() {
		$data         = [];
		$post_type    = get_post_type_object( 'groups' );
		$group_labels = get_post_type_labels( $post_type );

		$data['logo_url']             = $this->fetch_logo();
		$data['greeting']             = __( 'Hello,', 'disciple-tools-autolink' );
		$data['user_name']            = dt_get_user_display_name( get_current_user_id() );
		$data['app_url']              = $this->get_app_link();
		$data['coached_by_label']     = __( 'Coached by', 'disciple-tools-autolink' );
		$data['link_heading']         = __( 'My Link', 'disciple-tools-autolink' );
		$data['share_link_help_text'] = __( 'Copy this link and share it with people you are coaching.', 'disciple-tools-autolink' );
		/* translators: %s: plural name of the group post type, e.g. "Churches". */
		$data['churches_heading']     = sprintf( __( 'My %s', 'disciple-tools-autolink' ), $group_labels->name );
		$data['share_link']           = $this->get_share_link();
		$data['group_fields']         = DT_Posts::get_post_field_settings( 'groups' );
		$data['create_church_link']   = $this->get_app_link() . '?action=create-group';
		$data['contact']              = Disciple_Tools_Users::get_contact_for_user( get_current_user_id() );
		$data['coach']                = null;
		$data['coach_name']           = '';
		/* translators: %s: singular name of the group post type, e.g. "Church". */
		$data['view_church_label']    = sprintf( __( 'View %s', 'disciple-tools-autolink' ), $group_labels->singular_name );
		$data['churches']             = [];
		/* translators: %s: singular name of the group post type, e.g. "Church". */
		$data['church_health_label']  = sprintf( __( '%s Health', 'disciple-tools-autolink' ), $group_labels->singular_name );
		$data['tree_label']           = __( 'Tree', 'disciple-tools-autolink' );
		$data['genmap_label']         = __( 'GenMap', 'disciple-tools-autolink' );

		if ( $data['contact'] ) {
			$result          = null;
			$data['contact'] = DT_Posts::get_post( 'contacts', $data['contact'], false, false );
			if ( ! is_wp_error( $result ) ) {
				$data['contact'] = $result;
			}
			$posts_response = $data['churches'] = DT_Posts::list_posts( 'groups', [
				'assigned_to' => [ get_current_user_id() ],
				'orderby'     => 'modified',
				'order'       => 'DESC',
			], false );
			if ( is_wp_error( $result ) ) {
				$data['churches'] = $posts_response['posts'] ?? [];
			} else {
				$data['churches'] = [];
			}
		}

		if ( $data['contact'] && count( $data['contact']['coached_by'] ) ) {
			$coach = $data['contact']['coached_by'][0] ?? null;
			if ( $coach ) {
				$coach = DT_Posts::get_post( 'contacts', $coach['ID'], false, false );
				if ( is_wp_error( $coach ) ) {
					$coach = '';
				}
				$coach_name = $coach['name'] ?? '';
			}
			$data['coach'] = $coach;
		}

		return $data;
	}

	public function fetch_logo() {
		$logo_url        = $dt_nav_tabs['admin']['site']['icon'] ?? plugin_dir_url( __FILE__ ) . '/images/logo-color.png';
		$custom_logo_url = get_option( 'custom_logo_url' );
		if ( ! empty( $custom_logo_url ) ) {
			$logo_url = $custom_logo_url;
		}

		return $logo_url;
	}

	/**
	 * Get the share link url
	 * @return string
	 */
	public function get_share_link() {
		$actor      = Disciple_Tools_Autolink_Actor::current();
		$contact_id = $actor->contact_id();

		if ( ! $contact_id ) {
			return '';
		}

		// A contact with no login has nothing to invite people into: the share
		// link sends them to register a Disciple.Tools account. Hand them their
		// own app link instead, so they can get back to it on another device.
		if ( $actor->is_contact() ) {
			return $actor->app_link();
		}

		return DT_Magic_URL::get_link_url_for_post(
			'contacts',
			$contact_id,
			'autolink',
			'share'
		);
	}

	public function init_genmapper() {
		if ( function_exists( 'dt_genmapper_metrics' ) ) {
			dt_genmapper_metrics();
			DT_genmapper_Metrics::instance();
		}
	}

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function coaching_tree( $contact_id, &$tree = [] ) {
		$contacts = DT_Posts::list_posts( 'contacts', [
			'coached_by' => [ (int) $contact_id ],
			"limit"      => 1000,
		], false )['posts'];

		foreach ( $contacts as $contact ) {
			//Check against infinite loop
			$is_in_tree = array_search( $contact['ID'], array_column( $tree, 'ID' ) );
			if ( $is_in_tree !== false ) {
				continue;
			}
			$this->coaching_tree( $contact['ID'], $tree );
			$tree[] = $contact;
		}

		return $tree;
	}
}
