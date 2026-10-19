<?php

/**
 * The people around this leader: those they coach, and the leaders and members
 * of the churches they hold.
 */
class Disciple_Tools_Autolink_Contacts_Controller extends Disciple_Tools_Autolink_Controller {
	const NONCE = 'dt_autolink_contact';
	const EDIT_NONCE = 'dt_autolink_contact_edit';


	/**
	 * Show the contacts template
	 */
	public function show( $params = [] ) {
		$data = $this->global_data();
		// phpcs:ignore
		extract( $data );

		$action    = 'contacts';
		$contacts   = $this->gather_contacts();
		$milestones = $this->milestones();

		$translations = [
			// Whatever this site calls contacts - some rename them to "Disciples".
			'heading'   => $contacts_label,
			/* translators: %s: lowercased plural name of the group post type, e.g. "churches". */
			'help_text' => sprintf( __( 'The people you coach or are assigned, and the leaders and members of your %s.', 'disciple-tools-autolink' ), strtolower( $churches_label ) ),
			/* translators: %s: singular name of the contact post type, e.g. "Contact". */
			'add'       => sprintf( __( 'Add %s', 'disciple-tools-autolink' ), $contact_label ),
			// One label whichever kind of link it is. What differs is where the
			// link goes, which is our problem rather than theirs.
			'has_login' => __( 'Has a Disciple.Tools login', 'disciple-tools-autolink' ),
			'copy_link' => __( 'Copy link', 'disciple-tools-autolink' ),
			'copied'    => __( 'Copied', 'disciple-tools-autolink' ),
			'copy_failed' => __( 'Could not copy', 'disciple-tools-autolink' ),
			/* translators: %s: the date and time a magic link stops working. */
			'link_expires' => __( 'Link works until %s', 'disciple-tools-autolink' ),
			'link_expired' => __( 'Link expired. Copy it to send a new one.', 'disciple-tools-autolink' ),
			/* translators: %s: singular name of the contact post type, e.g. "Contact". */
			'edit'      => sprintf( __( 'Edit %s', 'disciple-tools-autolink' ), $contact_label ),
			/* translators: %s: singular name of the contact post type, e.g. "Contact". */
			'view'      => sprintf( __( 'View %s', 'disciple-tools-autolink' ), $contact_label ),
			// Names the milestone row. What each mark means is carried by a check
			// or a cross rather than by words - see renderMilestone() - so that it
			// needs no translation and cannot lag behind one.
			'milestones' => $milestones['label'],
			/* translators: 1: lowercased plural name of the contact post type. 2: lowercased plural name of the group post type. */
			'empty'     => sprintf(
				__( 'No %1$s yet. People you coach, and the leaders and members of your %2$s, will appear here.', 'disciple-tools-autolink' ),
				strtolower( $contacts_label ),
				strtolower( $churches_label )
			),
		];

		$add_contact_link  = $this->functions->get_app_link() . '?action=add-contact';
		$relationship_icons = $this->relationship_icons();

		include __DIR__ . '/../templates/contacts.php';
	}

	/**
	 * Show the form for adding a contact.
	 */
	public function create( $params = [] ) {
		$data = $this->global_data();
		// phpcs:ignore
		extract( $data );

		$action       = 'contacts';
		$form_action  = $this->functions->get_app_link() . '?action=add-contact';
		$nonce        = self::NONCE;
		$error        = $params['error'] ?? '';
		$name         = $params['name'] ?? '';
		$cancel_url   = $this->functions->get_app_link() . '?action=contacts';
		$translations = [
			/* translators: %s: singular name of the contact post type, e.g. "Contact". */
			'heading'      => sprintf( __( 'Add a %s', 'disciple-tools-autolink' ), strtolower( $contact_label ) ),
			'help_text'    => __( 'They will be added to the people you coach, and get their own link into this app.', 'disciple-tools-autolink' ),
			'name_label'   => __( 'Name', 'disciple-tools-autolink' ),
			'submit_label' => __( 'Add', 'disciple-tools-autolink' ),
			'cancel_label' => __( 'Cancel', 'disciple-tools-autolink' ),
		];

		include __DIR__ . '/../templates/contact-form.php';
	}

	/**
	 * Create a contact.
	 *
	 * This is the answer to "how does someone new join the chain" - rather than a
	 * link anyone can use to create records, the person doing the coaching adds
	 * them here and hands over the link that comes back. Creation stays behind a
	 * key that already belongs to someone.
	 */
	public function store( $params = [] ) {
		$nonce        = sanitize_key( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		$verify_nonce = $nonce && wp_verify_nonce( $nonce, self::NONCE );
		$name         = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$actor        = $this->actor();

		if ( ! $verify_nonce ) {
			$this->create( [ 'error' => __( 'Unable to verify request. Please try again.', 'disciple-tools-autolink' ) ] );

			return;
		}

		if ( ! $name ) {
			$this->create( [ 'error' => __( 'Please enter a name.', 'disciple-tools-autolink' ) ] );

			return;
		}

		if ( ! $actor->contact_id() ) {
			$this->create( [ 'error' => __( 'Your record could not be found.', 'disciple-tools-autolink' ), 'name' => $name ] );

			return;
		}

		$fields = [
			'name'           => $name,
			'coached_by'     => [
				'values' => [
					[ 'value' => $actor->contact_id() ],
				],
			],
			// Said out loud rather than left to Disciple.Tools, which picks the
			// status from whether anyone is signed in: "Active" for a user, "New
			// Contact" for nobody - see dt_post_create_fields() in the contacts
			// module. A contact link signs nobody in, so the same act of adding
			// someone you coach was landing in two different states depending on
			// which link you held. Nothing created here is an unattended sign-up;
			// a leader added this person on purpose.
			'overall_status' => 'active',
		];

		$assigned_to = $actor->assigned_to_for_new_records();
		if ( $assigned_to ) {
			$fields['assigned_to'] = $assigned_to;
		}

		$contact = DT_Posts::create_post( 'contacts', $fields, true, false );

		if ( is_wp_error( $contact ) ) {
			$this->create( [ 'error' => $contact->get_error_message(), 'name' => $name ] );

			return;
		}

		// No link is minted here. One is issued, with an expiry, when someone
		// presses Copy link - see link().
		wp_publish_post( $contact['ID'] );

		wp_redirect( $this->functions->get_app_link() . '?action=contacts' );
	}

	/**
	 * Show the form for editing one person.
	 *
	 * Name and faith milestones only. The card shows a good deal more than that -
	 * who they lead, who coaches them - but those are relationships held on other
	 * records, and editing them from here would mean reaching into churches this
	 * person may have no business touching.
	 */
	public function edit( $params = [] ) {
		// `post`, not `id`: the magic link reads `id` out of the query string for
		// its own routing, and a link carrying one is rejected as invalid before
		// it ever reaches us. The group edit screen names it the same way.
		$contact_id = (int) sanitize_key( wp_unslash( $_GET['post'] ?? '' ) );
		$actor      = $this->actor();

		if ( ! $contact_id || ! $actor->can_edit_contact( $contact_id ) ) {
			$this->functions->redirect_to_app();

			return;
		}

		$contact = DT_Posts::get_post( 'contacts', $contact_id, true, false );

		if ( is_wp_error( $contact ) ) {
			$this->functions->redirect_to_app();

			return;
		}

		$this->form( [
			'contact' => $contact,
			// On the way back from a failed save these carry what was typed, so
			// nobody has to do it twice.
			'name'    => $params['name'] ?? ( $contact['title'] ?? '' ),
			'reached' => $params['reached'] ?? array_map( 'strval', array_values( (array) ( $contact['milestones'] ?? [] ) ) ),
			'error'   => $params['error'] ?? '',
		] );
	}

	/**
	 * Frame this person's own Disciple.Tools record.
	 *
	 * Reading the record means reading the Disciple.Tools page, which needs a
	 * login - so this is for user actors only, and the menu does not offer it to
	 * anyone else. Mirrors the group view screen.
	 */
	public function view( $params = [] ) {
		$contact_id = (int) sanitize_key( wp_unslash( $_GET['post'] ?? '' ) );
		$actor      = $this->actor();
		$back_link  = $this->functions->get_app_link();
		$back_label = __( 'Back to AutoLink', 'disciple-tools-autolink' );

		if ( $actor->is_contact() || ! $contact_id || ! $actor->can_edit_contact( $contact_id ) ) {
			$this->functions->redirect_to_app();

			return;
		}

		$contact = DT_Posts::get_post( 'contacts', $contact_id, true, false );

		if ( is_wp_error( $contact ) ) {
			$this->functions->redirect_to_app();

			return;
		}

		$src = get_the_permalink( $contact['ID'] );

		include __DIR__ . '/../templates/frame.php';
	}

	/**
	 * Save an edit.
	 */
	public function update( $params = [] ) {
		$contact_id   = (int) sanitize_key( wp_unslash( $_GET['post'] ?? '' ) );
		$actor        = $this->actor();
		$nonce        = sanitize_key( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		$verify_nonce = $nonce && wp_verify_nonce( $nonce, self::EDIT_NONCE );
		$name         = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$submitted    = isset( $_POST['milestones'] )
			? array_map( 'sanitize_key', (array) wp_unslash( $_POST['milestones'] ) )
			: null;
		$milestones   = $this->posted_milestones( $submitted );

		// Checked before the nonce, because a failed nonce renders the form again
		// and there is no form to render for a record they cannot open.
		if ( ! $contact_id || ! $actor->can_edit_contact( $contact_id ) ) {
			$this->functions->redirect_to_app();

			return;
		}

		$reached = $milestones === null ? null : $this->reached_from( $milestones );

		if ( ! $verify_nonce ) {
			$this->edit( [
				'error'   => __( 'Unable to verify request. Please try again.', 'disciple-tools-autolink' ),
				'name'    => $name,
				'reached' => $reached,
			] );

			return;
		}

		if ( ! $name ) {
			$this->edit( [
				'error'   => __( 'Please enter a name.', 'disciple-tools-autolink' ),
				'name'    => $name,
				'reached' => $reached,
			] );

			return;
		}

		$fields = [ 'name' => $name ];

		if ( $milestones !== null ) {
			$fields['milestones'] = $milestones;
		}

		$updated = DT_Posts::update_post( 'contacts', $contact_id, $fields, true, false );

		if ( is_wp_error( $updated ) ) {
			$this->edit( [
				'error'   => $updated->get_error_message(),
				'name'    => $name,
				'reached' => $reached,
			] );

			return;
		}

		wp_redirect( $this->functions->get_app_link() . '?action=contacts' );
	}

	/**
	 * Issue a contact's link, for the Copy link button.
	 *
	 * Links are minted here rather than when the list is drawn, so each one runs
	 * from the moment it is handed over and lasts the validity set in the
	 * Autolink settings.
	 *
	 * @param WP_REST_Request $request
	 * @param array $params
	 * @param Disciple_Tools_Autolink_Actor $actor
	 *
	 * @return array|WP_Error
	 */
	public function link( WP_REST_Request $request, $params, $actor ) {
		$contact_id = (int) ( $params['contact_id'] ?? 0 );

		// Never your own. Copying extends a live link, so a holder who could issue
		// their own would keep it alive forever and expiry would limit nothing.
		// Renewal comes from the person who handed it over.
		if ( ! $contact_id || $contact_id === $actor->contact_id() || ! $actor->can_edit_contact( $contact_id ) ) {
			return new WP_Error( __METHOD__, __( 'You cannot share a link for this person.', 'disciple-tools-autolink' ), [ 'status' => 403 ] );
		}

		$link = Disciple_Tools_Autolink_Contact_Links::issue( $contact_id );

		if ( ! $link ) {
			return new WP_Error( __METHOD__, __( 'This person signs in with their own login.', 'disciple-tools-autolink' ), [ 'status' => 400 ] );
		}

		$expires = Disciple_Tools_Autolink_Contact_Links::expires_at( $contact_id );

		return [
			'link'          => $link,
			'expires'       => $expires,
			'expires_label' => Disciple_Tools_Autolink_Contact_Links::format_expiry( $expires ),
		];
	}

	/**
	 * Render the edit form.
	 *
	 * @param array $params
	 */
	private function form( $params ) {
		$data = $this->global_data();
		// phpcs:ignore
		extract( $data );

		$action      = 'contacts';
		$contact     = $params['contact'];
		$contact_id  = (int) $contact['ID'];
		$name        = $params['name'];
		$reached     = $params['reached'];
		$error       = $params['error'];
		$nonce       = self::EDIT_NONCE;
		$form_action = $this->functions->get_app_link() . '?action=edit-contact&post=' . $contact_id;
		$cancel_url  = $this->functions->get_app_link() . '?action=contacts';
		$milestones  = $this->milestones();

		// dt-multi-select-button-group names its options `id`.
		$milestone_options = array_map( function ( $milestone ) {
			return [
				'id'    => $milestone['key'],
				'label' => $milestone['label'],
				'icon'  => $milestone['icon'],
			];
		}, $milestones['options'] );

		$translations = [
			/* translators: %s: singular name of the contact post type, e.g. "Contact". */
			'heading'      => sprintf( __( 'Edit %s', 'disciple-tools-autolink' ), strtolower( $contact_label ) ),
			'name_label'   => __( 'Name', 'disciple-tools-autolink' ),
			'milestones'   => $milestones['label'],
			'submit_label' => __( 'Save', 'disciple-tools-autolink' ),
			'cancel_label' => __( 'Cancel', 'disciple-tools-autolink' ),
		];

		include __DIR__ . '/../templates/contact-edit-form.php';
	}

	/**
	 * The milestone changes that were submitted, shaped for DT_Posts.
	 *
	 * dt-multi-select-button-group posts the whole set it is holding, marking a
	 * milestone that was switched off with a leading "-", which is the same shape
	 * DT_Posts wants - so this is mostly a matter of checking that each key is one
	 * we actually offered, rather than one someone appended to the request.
	 *
	 * @param string[]|null $submitted what the form sent, unslashed and sanitised
	 *
	 * @return array|null null when the field was not part of the form at all,
	 *                    which is not the same as "every milestone was cleared".
	 */
	private function posted_milestones( $submitted ) {
		if ( $submitted === null ) {
			return null;
		}

		$known  = wp_list_pluck( $this->milestones()['options'], 'key' );
		$values = [];

		foreach ( $submitted as $entry ) {
			$delete = strpos( $entry, '-' ) === 0;
			$key    = $delete ? substr( $entry, 1 ) : $entry;

			if ( ! in_array( $key, $known, true ) ) {
				continue;
			}

			$value = [ 'value' => $key ];

			if ( $delete ) {
				$value['delete'] = true;
			}

			$values[] = $value;
		}

		return [ 'values' => $values ];
	}

	/**
	 * The milestones that would be set, were the submitted changes to be saved.
	 *
	 * Used to redraw the form after a failed save still showing what was chosen.
	 *
	 * @param array $milestones as posted_milestones() returns them
	 *
	 * @return string[]
	 */
	private function reached_from( $milestones ) {
		$reached = [];

		foreach ( $milestones['values'] as $value ) {
			if ( empty( $value['delete'] ) ) {
				$reached[] = $value['value'];
			}
		}

		return $reached;
	}

	/**
	 * Gather the contacts, deduplicated, with how each one is connected.
	 *
	 * Everyone arrives from one query - see Actor::related_contacts() - and every
	 * connection is then read off their own record. Reading the church
	 * relationships from the person rather than from the actor's churches is what
	 * makes the card complete: it can say that someone leads a church the actor
	 * has no part in, which walking the actor's own churches could never show.
	 *
	 * Someone can be connected several ways at once, so connections are collected
	 * per person and grouped by kind - one "Member" badge naming three churches
	 * rather than three rows.
	 *
	 * @return array
	 */
	private function gather_contacts() {
		$actor      = $this->actor();
		$contact_id = $actor->contact_id();
		$contacts  = [];

		foreach ( $actor->related_contacts() as $contact ) {
			$id = (int) $contact['ID'];

			$this->add_contact( $contacts, $id, $contact['title'] ?? $contact['name'] ?? '', $contact['milestones'] ?? [] );

			foreach ( $this->contact_relationships( $contact, $actor ) as $type ) {
				$this->add_relationship( $contacts, $id, $type );
			}

			foreach ( $contact['group_leader'] ?? [] as $church ) {
				$this->add_relationship( $contacts, $id, 'leader', $church['ID'], $church['post_title'] ?? '' );
			}

			foreach ( $contact['groups'] ?? [] as $church ) {
				$this->add_relationship( $contacts, $id, 'member', $church['ID'], $church['post_title'] ?? '' );
			}
		}

		// The person using the app is on their own churches as a leader and a
		// member; they are not their own contact.
		unset( $contacts[ $contact_id ] );

		$general_link = $this->functions->get_link_url();
		$edit_link    = $this->functions->get_app_link() . '?action=edit-contact&post=';
		// Only a user can open the record itself - see view(). An absent link is
		// what tells the card not to offer it.
		$view_link    = $actor->is_contact() ? '' : $this->functions->get_app_link() . '?action=view-contact&post=';

		foreach ( $contacts as $id => $contact_row ) {
			$has_login = Disciple_Tools_Autolink_Actor::contact_has_login( $id );

			$contacts[ $id ]['has_login']     = $has_login;
			$contacts[ $id ]['can_edit']      = $actor->can_edit_contact( $id );
			// Someone with their own Disciple.Tools login signs in rather than
			// following a key - handing their coach a magic key into that account
			// would hand over whatever the account can reach. They get the ordinary
			// sign-in address instead, which is still useful to pass on.
			//
			// Everyone else's link is a key that is issued only when Copy link is
			// pressed, and only to someone who may edit them: the list is wider than
			// that, and a link is the whole app as that person.
			$contacts[ $id ]['link']          = $has_login ? $general_link : '';
			$contacts[ $id ]['can_link']      = ! $has_login && $contacts[ $id ]['can_edit'];
			// Only whether a link already handed out has lapsed - the card says so
			// beside the button that renews it. The date itself is shown once a
			// link is copied, when it is the thing being sent.
			$contacts[ $id ]['link_expired']  = $contacts[ $id ]['can_link']
				&& get_post_meta( $id, Disciple_Tools_Autolink_Contact_Links::meta_key(), true )
				&& Disciple_Tools_Autolink_Contact_Links::is_expired( $id );
			$contacts[ $id ]['edit_link']     = $contacts[ $id ]['can_edit'] ? $edit_link . $id : '';
			$contacts[ $id ]['view_link']     = ( $view_link && $contacts[ $id ]['can_edit'] ) ? $view_link . $id : '';
			$contacts[ $id ]['relationships'] = $this->shape_relationships( $contact_row['relationships'] );
		}

		uasort( $contacts, function ( $a, $b ) {
			return strcasecmp( $a['name'], $b['name'] );
		} );

		return array_values( $contacts );
	}

	/**
	 * Which kinds of connection this person has to the actor.
	 *
	 * @param array $contact
	 * @param Disciple_Tools_Autolink_Actor $actor
	 *
	 * @return string[]
	 */
	private function contact_relationships( $contact, $actor ) {
		$types      = [];
		$contact_id = $actor->contact_id();
		$user_id    = $actor->linked_user_id();

		foreach ( $contact['coached_by'] ?? [] as $coach ) {
			if ( (int) ( $coach['ID'] ?? 0 ) === $contact_id ) {
				$types[] = 'coaching';
				break;
			}
		}

		if ( $user_id && (int) ( $contact['assigned_to']['id'] ?? 0 ) === $user_id ) {
			$types[] = 'assigned';
		}

		return $types;
	}

	/**
	 * Record one person.
	 *
	 * @param array $contacts
	 * @param int $id
	 * @param string $name
	 * @param array $milestones the faith milestones they have reached
	 */
	private function add_contact( &$contacts, $id, $name, $milestones = [] ) {
		$id = (int) $id;

		if ( ! $id ) {
			return;
		}

		if ( ! isset( $contacts[ $id ] ) ) {
			$contacts[ $id ] = [
				'id'            => $id,
				'name'          => $name,
				'milestones'    => array_map( 'strval', array_values( (array) $milestones ) ),
				'relationships' => [],
			];
		}

		if ( ! $contacts[ $id ]['name'] && $name ) {
			$contacts[ $id ]['name'] = $name;
		}
	}

	/**
	 * Note one kind of connection, keeping the churches it came from.
	 *
	 * Churches are keyed by id rather than collected as a plain list, so that the
	 * leader and member sets can be compared without two churches that happen to
	 * share a name being treated as one.
	 *
	 * @param array $contacts
	 * @param int $id
	 * @param string $type
	 * @param int $church_id where the connection is through a church
	 * @param string $church_name
	 */
	private function add_relationship( &$contacts, $id, $type, $church_id = 0, $church_name = '' ) {
		$id = (int) $id;

		if ( ! isset( $contacts[ $id ] ) ) {
			return;
		}

		if ( ! isset( $contacts[ $id ]['relationships'][ $type ] ) ) {
			$contacts[ $id ]['relationships'][ $type ] = [];
		}

		if ( $church_id ) {
			$contacts[ $id ]['relationships'][ $type ][ (int) $church_id ] = $church_name;
		}
	}

	/**
	 * Turn the collected connections into badges, plus the lines underneath them.
	 *
	 * The badge says what kind of connection it is; the line below names the
	 * churches it came from. The names are on the card rather than in a tooltip -
	 * there is no hover on a phone, so anything only reachable that way is not
	 * there at all.
	 *
	 * Ordered deliberately: the direct relationships first, then the ones that
	 * reach this person through a church.
	 *
	 * @param array $relationships type => list of church names
	 *
	 * @return array
	 */
	private function shape_relationships( $relationships ) {
		$labels = [
			'coaching' => __( 'Coaching', 'disciple-tools-autolink' ),
			'assigned' => __( 'Assigned', 'disciple-tools-autolink' ),
			'leader'   => __( 'Leader', 'disciple-tools-autolink' ),
			'member'   => __( 'Member', 'disciple-tools-autolink' ),
		];

		$with_churches = [
			/* translators: %s: a list of church names. */
			'leader' => __( 'Leads %s', 'disciple-tools-autolink' ),
			/* translators: %s: a list of church names. */
			'member' => __( 'Member of %s', 'disciple-tools-autolink' ),
		];

		// Leading a church means being a member of it too, and DT records both, so
		// an unfiltered card says the same thing twice. The leader line is the one
		// that carries information, so a church someone leads drops out of their
		// member list - and if that empties it, the member badge goes with it.
		if ( ! empty( $relationships['leader'] ) && ! empty( $relationships['member'] ) ) {
			$relationships['member'] = array_diff_key( $relationships['member'], $relationships['leader'] );
		}

		$badges = [];

		foreach ( array_keys( $labels ) as $type ) {
			if ( ! isset( $relationships[ $type ] ) ) {
				continue;
			}

			$churches = $relationships[ $type ];
			$detail   = '';

			if ( isset( $with_churches[ $type ] ) ) {
				if ( empty( $churches ) ) {
					continue;
				}

				$detail = sprintf( $with_churches[ $type ], implode( ', ', $churches ) );
			}

			$badges[] = [
				'type'   => $type,
				'label'  => $labels[ $type ],
				'detail' => $detail,
			];
		}

		return $badges;
	}

	/**
	 * The icon for each kind of connection, taken from the Disciple.Tools field it
	 * stands for rather than picked by us, so that customising the field's icon
	 * customises this too.
	 *
	 * The two direct relationships read from contact fields. The two that reach a
	 * person through a church read from the church count fields instead - the same
	 * marks the Group Health tab already uses for leaders and members, so one
	 * symbol means one thing across both tabs. That is a deliberate trade: an
	 * admin who restyles the *contact* "Leader of Group" icon will not see it
	 * here. Point this filter at the contact fields to swap that back.
	 *
	 * There is no field behind "has a login" - corresponds_to_user is hidden and
	 * carries no icon - so that one mark stays ours, in the component.
	 *
	 * @return array
	 */
	private function relationship_icons() {
		$sources = apply_filters( 'dt_autolink_contact_relationship_icons', [
			'coaching' => [ 'contacts', 'coaching' ],      // "Is Coaching"
			'assigned' => [ 'contacts', 'assigned_to' ],   // "Assigned"
			'leader'   => [ 'groups', 'leader_count' ],    // as per the Group Health row
			'member'   => [ 'groups', 'member_count' ],    // as per the Group Health row
		] );

		$settings = [];
		$icons    = [];

		foreach ( $sources as $type => $source ) {
			list( $post_type, $field_key ) = $source;

			if ( ! isset( $settings[ $post_type ] ) ) {
				$settings[ $post_type ] = DT_Posts::get_post_field_settings( $post_type );
			}

			$field = $settings[ $post_type ][ $field_key ] ?? [];

			$icons[ $type ] = array_merge( self::field_icon( $field ), [
				'name' => $field['name'] ?? '',
			] );
		}

		return $icons;
	}

	/**
	 * The faith milestones, in the order the field declares them, each with the
	 * icon that option carries.
	 *
	 * Every milestone, not only the ones reached: the row is a progress strip, and
	 * a milestone shown dim says something that a missing icon cannot. Which ones a
	 * given person has reached travels with them - see gather_contacts().
	 *
	 * An admin who hides the field, or deletes one of its options, has said they do
	 * not use it; both drop out here.
	 *
	 * @return array{label: string, options: array}
	 */
	private function milestones() {
		$field = DT_Posts::get_post_field_settings( 'contacts' )['milestones'] ?? [];

		if ( empty( $field ) || ! empty( $field['hidden'] ) ) {
			return [ 'label' => '', 'options' => [] ];
		}

		$options = [];

		foreach ( $field['default'] ?? [] as $key => $option ) {
			if ( ! empty( $option['deleted'] ) ) {
				continue;
			}

			$options[] = array_merge( [
				'key'   => $key,
				'label' => $option['label'] ?? $key,
			], self::field_icon( $option ) );
		}

		return [
			'label'   => $field['name'] ?? '',
			'options' => $options,
		];
	}

	/**
	 * The icon to draw for a field, or for one of its options.
	 *
	 * Disciple.Tools keeps these two ways, and the admin screen picks between them
	 * by what was typed: anything starting with "mdi" is stored as `font-icon`, and
	 * everything else as `icon`, an image address. Both shapes reach us, so both
	 * have to come out the other side.
	 *
	 * @param array $source a field's settings, or one option's
	 *
	 * @return array{icon: string, font_icon: string}
	 */
	private static function field_icon( $source ) {
		return [
			'icon'      => $source['icon'] ?? '',
			'font_icon' => self::icon_name( $source['font-icon'] ?? '' ),
		];
	}

	/**
	 * A Material Design Icons class written the way <dt-icon> names icons.
	 *
	 * Disciple.Tools stores font icons as the stylesheet class - "mdi mdi-hand-heart"
	 * - while the app draws them through <dt-icon>, which names the same set
	 * "mdi:hand-heart". Translating here rather than in the component means one
	 * shape arrives in the browser however the icon was stored, and the component
	 * never has to know Disciple.Tools wrote it.
	 *
	 * A value already written that way is left alone. A set name with no icon after
	 * it ("mdi") names nothing, and comes back empty so the caller can fall back.
	 *
	 * @param string $font_icon
	 *
	 * @return string
	 */
	private static function icon_name( $font_icon ) {
		$font_icon = trim( (string) $font_icon );

		if ( ! $font_icon || strpos( $font_icon, ':' ) !== false ) {
			return $font_icon;
		}

		$classes = preg_split( '/\s+/', $font_icon );
		$name    = (string) array_pop( $classes );

		if ( strpos( $name, 'mdi-' ) !== 0 ) {
			return '';
		}

		return 'mdi:' . substr( $name, 4 );
	}
}
