<?php

abstract class Disciple_Tools_Autolink_Controller {
	public static $_instance;
	public $functions;
	public $settings;

	public function __construct() {
		$this->settings  = new Disciple_Tools_Autolink_Settings();
		$this->functions = Disciple_Tools_Autolink_Magic_Functions::instance();
	}

	/**
	 * Who this request is acting as - a logged in user, or a contact holding a
	 * magic link. Everything below asks the actor rather than the WP session, so
	 * the same controllers serve both links.
	 *
	 * @return Disciple_Tools_Autolink_Actor
	 */
	public function actor() {
		return Disciple_Tools_Autolink_Actor::current();
	}

	/**
	 * Global data is shared between many views in the app.
	 */
	public function global_data() {
		$actor        = $this->actor();
		$data         = [];
		$post_type      = get_post_type_object( 'groups' );
		$group_labels   = get_post_type_labels( $post_type );
		$contact_labels = get_post_type_labels( get_post_type_object( 'contacts' ) );

		$data['logo_url']             = $this->functions->fetch_logo();
		$data['greeting']             = __( 'Hello,', 'disciple-tools-autolink' );
		$data['user_name']            = $actor->display_name();
		$data['app_url']              = $this->functions->get_app_link();
		$data['coached_by_label']     = __( 'Coached by', 'disciple-tools-autolink' );
		$data['link_heading']         = __( 'My Link', 'disciple-tools-autolink' );
		$data['share_link_help_text'] = $actor->is_contact()
			? __( 'This is your own link. Save it so you can get back to this app.', 'disciple-tools-autolink' )
			: __( 'Copy this link and share it with people you are coaching.', 'disciple-tools-autolink' );
		/* translators: %s: plural name of the group post type, e.g. "Churches". */
		$data['churches_heading']     = sprintf( __( 'My %s', 'disciple-tools-autolink' ), $group_labels->name );
		$data['share_link']           = $this->functions->get_share_link();
		$data['group_fields']         = DT_Posts::get_post_field_settings( 'groups' );
		$data['create_group_link']    = $this->functions->get_create_group_url();
		$data['edit_group_link']      = $this->functions->get_edit_group_url();
		$data['contact']              = $actor->contact();
		$data['contact_id']           = $actor->contact_id();
		$data['is_contact']           = $actor->is_contact();
		$data['show_logout']          = $actor->can_logout();
		$data['coach']                = null;
		$data['coach_name']           = '';
		/* translators: %s: singular name of the group post type, e.g. "Church". */
		$data['view_church_label']    = sprintf( __( 'View %s', 'disciple-tools-autolink' ), $group_labels->singular_name );
		$data['churches']             = [];
		/* translators: %s: singular name of the group post type, e.g. "Church". */
		$data['church_health_label']  = sprintf( __( '%s Health', 'disciple-tools-autolink' ), $group_labels->singular_name );
		$data['tree_label']           = __( 'Tree', 'disciple-tools-autolink' );
		$data['genmap_label']         = __( 'GenMap', 'disciple-tools-autolink' );
		// The tab reads whatever this site calls the contacts post type, the way
		// the churches tab reads the groups one. Sites that rename contacts to
		// something like "Disciples" get that name here without a code change.
		$data['contact_label']        = $contact_labels->singular_name;
		$data['contacts_label']       = $contact_labels->name;
		// A contact with no login can trade their bearer link for a password.
		$data['can_upgrade']          = $actor->is_contact()
			&& $actor->contact_id()
			&& ! Disciple_Tools_Autolink_Actor::contact_has_login( $actor->contact_id() );
		$data['upgrade_url']          = $this->functions->get_app_link() . '?action=upgrade';
		$data['upgrade_label']        = __( 'Secure your access', 'disciple-tools-autolink' );
		$data['upgrade_help_text']    = __( 'Create a username and password so you are not relying on this link.', 'disciple-tools-autolink' );
		// When the link a contact is using runs out. It is their only way in, and
		// it stops without warning, so it is said up front - with what to do
		// about it in the same place.
		$link_expires                 = $actor->is_contact() ? Disciple_Tools_Autolink_Contact_Links::expires_at( $actor->contact_id() ) : null;
		$data['link_expires_soon']    = Disciple_Tools_Autolink_Contact_Links::expires_soon( $link_expires );
		$data['link_expiry_text']     = $link_expires
			/* translators: %s: the date and time a magic link stops working. */
			? sprintf( __( 'This link works until %s.', 'disciple-tools-autolink' ), Disciple_Tools_Autolink_Contact_Links::format_expiry( $link_expires ) )
			: '';
		$data['link_renew_text']      = $data['can_upgrade']
			? __( 'Ask your coach to renew it, or create a login so you are not relying on a link.', 'disciple-tools-autolink' )
			: __( 'Ask your coach to renew it.', 'disciple-tools-autolink' );
		$data['training-label']       = __( 'Training', 'disciple-tools-autolink' );
		$data['training-link']        = $this->functions->get_training_url();
		$data['show_training']        = $this->settings->training_enabled();
		$data['show_survey']          = $this->settings->survey_enabled();
		$data['church_label']         = $group_labels->singular_name;
		$data['churches_label']       = $group_labels->name;

		if ( $data['contact'] ) {
			$data['churches'] = $actor->own_groups();
		}

		if ( $data['contact'] && count( $data['contact']['coached_by'] ?? [] ) ) {
			$coach = $data['contact']['coached_by'][0] ?? null;

			if ( $coach ) {
				$coach = DT_Posts::get_post( 'contacts', $coach['ID'], true, false );

				if ( ! is_wp_error( $coach ) ) {
					$data['coach']      = $coach;
					$data['coach_name'] = $coach['title'] ?? $coach['name'] ?? '';
				}
			}
		}

		return $data;
	}
}
