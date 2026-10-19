<?php

class Disciple_Tools_Autolink_Group_Controller extends Disciple_Tools_Autolink_Controller {
	const NONCE = 'dt_autolink_group';

	/**
	 * Show the edit group form
	 */
	public function edit( $params = [] ) {
		$group_id         = sanitize_key( wp_unslash( $_GET['post'] ?? "" ) );
		$params['action'] = $this->functions->get_edit_group_url();

		if ( ! $group_id || ! $this->actor()->can_edit_group( $group_id ) ) {
			$this->functions->redirect_to_app();
			exit;
		}
		$this->form( $params );
	}

	/**
	 * Show the edit/create group form
	 * Expects $params['action'] to be set
	 */
	private function form( $params = [] ) {
		if ( ! isset( $params['action'] ) ) {
			$this->functions->redirect_to_app();
			exit;
		}
		$group    = null;
		$group_id = sanitize_key( wp_unslash( $_GET['post'] ?? $params['post'] ?? null ) );
		if ( $group_id ) {
			if ( ! $this->actor()->can_edit_group( $group_id ) ) {
				$this->functions->redirect_to_app();
				exit;
			}
			$group = DT_Posts::get_post( 'groups', $group_id, true, false );
			if ( ! $group || is_wp_error( $group ) ) {
				$this->functions->redirect_to_app();
				exit;
			}
		}

		// Whichever magic link is serving this request - the user app or a
		// contact app - so the form posts back to the link the person arrived on.
		$magic_link = Disciple_Tools_Autolink_Magic_Functions::app();


		$group_fields = DT_Posts::get_post_settings( 'groups' )['fields'];
		$post_type    = get_post_type_object( 'groups' );
		$group_labels = get_post_type_labels( $post_type );
		$group        = $group ?? [];
		$contact_id   = $this->actor()->contact_id();
		if ( $group ) {
			/* translators: %s: singular name of the group post type, e.g. "Church". */
			$heading = sprintf( __( 'Edit %s', 'disciple-tools-autolink' ), $group_labels->singular_name );
		} else {
			/* translators: %s: singular name of the group post type, e.g. "Church". */
			$heading = sprintf( __( 'Create %s', 'disciple-tools-autolink' ), $group_labels->singular_name );
		}
		$name_label       = $group_fields['name']['name'];
		$name_placeholder = $group_fields['name']['name'];
		$start_date_label = $group_fields['start_date']['name'];
		$leaders_label    = $group_fields['leaders']['name'];
		$nonce            = self::NONCE;
		$action           = $params['action'];
		$cancel_url       = $this->functions->get_app_link();
		$cancel_label     = __( 'Cancel', 'disciple-tools-autolink' );
		$submit_label     = __( 'Save', 'disciple-tools-autolink' );
		$error            = $params['error'] ?? '';
		$name             = sanitize_text_field( wp_unslash( $params['name'] ?? "" ) );
		$leader_options   = $this->leader_options( $contact_id );

		// <dt-connection> takes { id, label } objects rather than bare ids, both for
		// its value and its options.
		$leader_values = $params['leaders'] ?? array_map( function ( $leader ) {
			return [
				'id'    => (int) $leader['ID'],
				'label' => $leader['post_title'] ?? '',
			];
		}, $group['leaders'] ?? [] );

		$leader_values = $this->leader_values( $leader_values, $leader_options );
		$parent_group_field_callback = '/wp-json/autolink/v1/' . $magic_link->parts['type'] . "?" . http_build_query( [
				'action' => 'parent_group_field',
				'parts'  => $magic_link->parts,
        ] );

		$show_location_field = DT_Mapbox_API::is_active_mapbox_key();

		if ( ! $name ) {
			$name = $group['name'] ?? '';
		}

		// phpcs:ignore
		$start_date = sanitize_text_field( wp_unslash( $_POST['start_date'] ?? "" ) );

		if ( ! $start_date ) {
			$start_date = $group['start_date'] ?? '';
		}

		if ( $start_date && is_array( $start_date ) ) {
			$start_date = $start_date ? dt_format_date( $start_date['timestamp'] ) : '';
		}

		include __DIR__ . '/../templates/group-form.php';
	}

	/**
	 * Reduce a submitted `leaders` value to a plain list of ids.
	 *
	 * <dt-connection> posts each selection as a { id, label } object and flags the
	 * ones the user removed rather than dropping them. An id that is not numeric is
	 * a name the user typed (`allowAdd`), which the caller turns into a contact.
	 *
	 * @param array $leaders
	 *
	 * @return array
	 */
	private function submitted_leader_ids( $leaders ) {
		$ids = [];

		foreach ( $leaders as $leader ) {
			if ( is_array( $leader ) ) {
				if ( ! empty( $leader['delete'] ) && $leader['delete'] !== 'false' ) {
					continue;
				}

				$leader = $leader['id'] ?? null;
			}

			if ( $leader !== null && $leader !== '' ) {
				$ids[] = $leader;
			}
		}

		return $ids;
	}

	/**
	 * The people the form offers as leaders: the actor and everyone in their
	 * coaching downline.
	 *
	 * process() checks submissions against the same list, so the offer is also
	 * the limit - a crafted request cannot name anyone the form would not.
	 *
	 * @param int $contact_id the actor's contact record
	 *
	 * @return array { id, label } options for <dt-connection>
	 */
	private function leader_options( $contact_id ) {
		$contacts = array_filter( [ DT_Posts::get_post( 'contacts', $contact_id, true, false ) ], function ( $contact ) {
			return $contact && ! is_wp_error( $contact );
		} );
		$coaching = dt_autolink_queries()->tree( 'coaching', [
			'check_health' => false,
			'id'           => $contact_id,
		] );
		// Ids must be integers, not numeric strings: dt-connection's remove handler
		// parses the clicked id to a number and compares it with `===`, so a string
		// id can never be matched and the chip's "x" silently does nothing.
		$options = array_values( array_map( function ( $contact ) {
			return [
				'id'    => (int) $contact['ID'],
				'label' => $contact['name'],
			];
		}, $contacts ) );
		foreach ( $coaching as $coached ) {
			$options[] = [
				'id'    => (int) $coached['id'],
				'label' => $coached['name'],
			];
		}

		return $options;
	}

	/**
	 * The churches the parent field offers for a set of leaders: the ones those
	 * leaders belong to, and the ones their coaches belong to.
	 *
	 * @param int[] $leader_ids leaders already checked against this actor
	 *
	 * @return array group connections, each with `ID` and `post_title`
	 */
	private function parent_group_candidates( $leader_ids ) {
		$leaders = [];

		foreach ( $leader_ids as $leader_id ) {
			$leader = DT_Posts::get_post( 'contacts', (int) $leader_id, false, false );

			if ( $leader && ! is_wp_error( $leader ) ) {
				$leaders[] = $leader;
			}
		}

		$groups = [];

		foreach ( $leaders as $leader ) {
			array_push( $groups, ...$leader['groups'] ?? [] );
		}

		if ( count( $leaders ) ) {
			$coached_by = DT_Posts::list_posts( 'contacts', [
				'coaching' => array_values( array_map( function ( $leader ) {
					return $leader['ID'];
				}, $leaders ) ),
			], false );

			foreach ( is_wp_error( $coached_by ) ? [] : $coached_by['posts'] as $leader ) {
				array_push( $groups, ...$leader['groups'] ?? [] );
			}
		}

		return array_unique( $groups, SORT_REGULAR );
	}

	/**
	 * Shape a list of leaders for <dt-connection>.
	 *
	 * The value can arrive three ways: from the group's own `leaders` connection,
	 * from a submitted form being re-rendered after an error, or from
	 * `create()` seeding the current user. The last two can be bare ids, so look
	 * their labels up in the option list.
	 *
	 * @param array $leaders
	 * @param array $options
	 *
	 * @return array
	 */
	private function leader_values( $leaders, $options ) {
		$labels = wp_list_pluck( $options, 'label', 'id' );

		return array_values( array_filter( array_map( function ( $leader ) use ( $labels ) {
			if ( is_array( $leader ) ) {
				$id    = (string) ( $leader['id'] ?? '' );
				$label = (string) ( $leader['label'] ?? '' );
			} else {
				$id    = (string) $leader;
				$label = '';
			}

			if ( $id === '' ) {
				return null;
			}

			return [
				// An id typed in by the user (allowAdd) stays a string; a real
				// contact id has to be an int, see the note on $leader_options.
				'id'    => is_numeric( $id ) ? (int) $id : $id,
				'label' => $label !== '' ? $label : ( $labels[ $id ] ?? $id ),
			];
		}, $leaders ) ) );
	}

	/**
	 * Reduce submitted locations to the keys DT stores.
	 *
	 * <dt-location-map> passes the geocoder's response through, so a selection can
	 * carry a whole provider payload (Google predictions bring a nested `raw`
	 * object). DT_Mapping_Module::validate_location_grid_meta() ignores anything it
	 * does not recognise, so drop it here rather than posting it.
	 *
	 * @param array $locations
	 *
	 * @return array
	 */
	private function clean_location_values( $locations ) {
		$allowed = [
			'grid_meta_id',
			'postmeta_id_location_grid',
			'grid_id',
			'lng',
			'lat',
			'level',
			'source',
			'label',
		];

		$values = [];

		foreach ( $locations as $location ) {
			if ( ! is_array( $location ) ) {
				continue;
			}

			$value = array_intersect_key( $location, array_flip( $allowed ) );

			if ( ! empty( $value ) ) {
				$values[] = dt_recursive_sanitize_array( $value );
			}
		}

		return $values;
	}

	/**
	 * Ajax callback to get the parent group field.
	 * Renders when the leaders change.
	 * @return false|void
	 */
	public function parent_group_field() {
		$group_fields = DT_Posts::get_post_settings( 'groups' )['fields'];
		$post_type    = get_post_type_object( 'groups' );
		$group_labels = get_post_type_labels( $post_type );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$leaders_ids  = $this->submitted_leader_ids( dt_recursive_sanitize_array( $_GET['leaders'] ?? [] ) );

		// Filter out new leaders, which have no id to look up yet, and anyone
		// this actor has no business reading. A contact link carries no
		// capabilities, so without this the endpoint would happily report the
		// churches of any contact id a caller cared to submit.
		$actor      = $this->actor();
		$leader_ids = array_filter( $leaders_ids, function ( $leader ) use ( $actor ) {
			return is_numeric( $leader ) && $leader > 0 && $actor->can_edit_contact( $leader );
		} );


		$groups = $this->parent_group_candidates( $leader_ids );

		$id                          = sanitize_text_field( wp_unslash( $_GET['id'] ?? '' ) );
		$group                        = $id ? DT_Posts::get_post( 'groups', $id, true, false ) : null;
		$default_parent_group         = count( $groups ) ? $groups[0]['ID'] ?? null : null;
		$allow_parent_group_selection = $this->settings->get_option( 'disciple_tools_autolink_allow_parent_group_selection' );
		$allow_parent_group_selection = $allow_parent_group_selection === '1' || $allow_parent_group_selection === true;


		$parent_group_options = array_map( function ( $group ) {
			return [
				'id'    => (string) $group['ID'],
				'label' => $group['post_title'],
			];
		}, $groups );

		$parent_group_options = array_filter( $parent_group_options, function ( $group ) use ( $id ) {
			return ! $id || (string) $group['id'] !== (string) $id;
		});

		if ( ! count( $parent_group_options ) ) {
			return false;
		}

		array_unshift( $parent_group_options, [
			'id'    => '',
			/* translators: %s: lowercased singular name of the group post type, e.g. "church". */
			'label' => sprintf( __( 'Select a %s...', 'disciple-tools-autolink' ), strtolower( $group_labels->singular_name ) ),
		] );

		$parent_group = $default_parent_group;

		if ( $group ) {
			$parent_group = count( $group['parent_groups'] ) ? $group['parent_groups'][0]["ID"] : '';
		}

		/* translators: %s: singular name of the group post type, e.g. "Church". */
		$parent_group_label = sprintf( __( 'Parent %s', 'disciple-tools-autolink' ), $group_labels->singular_name );

		include __DIR__ . '/../templates/parts/parent-group-field.php';
	}

	/**
	 * Show the create group form
	 */
	public function create( $params = [] ) {
		$group_id         = sanitize_key( wp_unslash( $_GET['post'] ?? "" ) );
		$params['action'] = $this->functions->get_create_group_url();

		// Default the person using the app as the leader
		$params['leaders'] = [
			(string) $this->actor()->contact_id()
		];

		if ( $group_id ) {
			$this->functions->redirect_to_app();
			exit;
		}
		$this->form( $params );
	}

	/**
	 * Delete a group
	 */
	public function delete( $params = [] ) {
		$app_controller = new Disciple_Tools_Autolink_App_Controller();
		$nonce          = sanitize_key( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
		$verify_nonce   = $nonce && wp_verify_nonce( $nonce, 'dt_autolink_delete_group' );
		$group_id       = sanitize_text_field( wp_unslash( $_GET['post'] ?? '' ) );

		if ( ! $verify_nonce ) {
			$app_controller->show( [ 'error' => __( 'Unauthorized action. Please refresh the page and try again.', 'disciple-tools-autolink' ) ] );

			return;
		}

		$group_id = (int) $group_id;

		if ( ! $this->actor()->can_edit_group( $group_id ) ) {
			$app_controller->show( [ 'error' => __( 'Unauthorized action. Please refresh the page and try again.', 'disciple-tools-autolink' ) ] );

			return;
		}

		$result = DT_Posts::delete_post( 'groups', $group_id, false );

		if ( is_wp_error( $result ) ) {
			$app_controller->show( [ 'error' => $result->get_error_message() ] );
		}

		$app_controller->show();
	}

	/**
	 * Show the DT group in an iframe
	 */
	public function show( $params = [] ) {
		$post_id    = sanitize_key( wp_unslash( $_GET['post'] ?? '' ) );
		$back_link  = $this->functions->get_app_link();
		$back_label = __( 'Back to AutoLink', 'disciple-tools-autolink' );

		// This screen frames the record's own Disciple.Tools page, which needs a
		// login. A contact holding a magic link does not have one and would only
		// see the login screen inside the frame, so send them to the edit form -
		// the same information, rendered by the app itself.
		if ( $this->actor()->is_contact() ) {
			wp_redirect( $this->functions->get_edit_group_url() . '&post=' . rawurlencode( $post_id ) );

			return;
		}

		if ( ! $post_id || ! $back_link || ! $this->actor()->can_edit_group( $post_id ) ) {
			$this->functions->redirect_to_app();

			return;
		}

		$group = DT_Posts::get_post( 'groups', $post_id, true, false );

		if ( is_wp_error( $group ) ) {
			$this->functions->redirect_to_app();

			return;
		}

		$src = get_the_permalink( $group['ID'] );

		include __DIR__ . '/../templates/frame.php';
	}

	public function update( $params = [] ) {
		$nonce            = sanitize_key( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		$verify_nonce     = $nonce && wp_verify_nonce( $nonce, self::NONCE );
		$id               = sanitize_key( wp_unslash( $_POST['id'] ?? '' ) );
		$action           = $this->functions->get_edit_group_url();
		$params['action'] = $action;
		$params['post']   = $id;

		if ( ! $verify_nonce ) {
			$this->form( [
				'error'  => 'Invalid request',
				'action' => $action
			] );

			return;
		}

		if ( ! $id ) {
			wp_redirect( $this->functions->get_app_link() );

			return;
		}

		$this->process( $params );
	}

	/**
	 * Process the edit/create group form
	 */
	private function process( $params = [] ) {

		$nonce        = sanitize_key( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		$verify_nonce = $nonce && wp_verify_nonce( $nonce, self::NONCE );

		$id           = sanitize_key( wp_unslash( $_POST['id'] ?? '' ) );
		$name         = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$start_date   = strtotime( sanitize_text_field( wp_unslash( $_POST['start_date'] ?? '' ) ) );
		// <dt-location-map> is a form associated custom element: it submits its own
		// value under its field name as a JSON array of location objects
		// ( label / lat / lng / level / grid_id ), which is the shape
		// DT_Mapping_Module::validate_location_grid_meta() expects.
		// The raw value is a JSON string; sanitizing before decoding would mangle it,
		// so it is decoded first and then sanitized recursively. A crafted request can
		// post it as an array, which json_decode() rejects with a TypeError, so only a
		// string is ever decoded - anything else is treated as "not submitted".
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw_location = wp_unslash( $_POST['location_grid_meta'] ?? null );
		$location     = is_string( $raw_location ) ? json_decode( $raw_location, true ) : null;

		// An untouched <dt-location-map> posts `null`, while one the user has emptied
		// posts `[]`. Only the second is an instruction to clear the field, so anything
		// that is not an array leaves the existing locations alone.
		$has_location = is_array( $location );
		$location     = $has_location ? $this->clean_location_values( $location ) : [];
		$leaders      = dt_recursive_sanitize_array( $_POST['leaders'] ?? [] );

		$leaders      = $this->submitted_leader_ids( $leaders );
		$contact_id   = $this->actor()->contact_id();
		$parent_group = sanitize_text_field( wp_unslash( $_POST['parent_group'] ?? 0 ) );
		$action       = $params['action'];

		$get_params = [
			'action'  => $action,
			'name'    => $name,
			'leaders' => $leaders,
		];

		if ( ! $verify_nonce || ! $name ) {
			$this->form( array_merge( $get_params, [
				'error' => 'Invalid request',
				'post'  => $id,
			] ) );

			return;
		}

		$actor        = $this->actor();
		$unauthorized = __( 'Unauthorized action. Please refresh the page and try again.', 'disciple-tools-autolink' );
		$group        = null;

		// form() guards the screen; this guards the write, which also arrives
		// from store()/update() and so has to stand on its own. It runs before
		// anything is written, so a refused edit leaves no new contacts behind.
		if ( $id ) {
			if ( ! $actor->can_edit_group( $id ) ) {
				$this->form( array_merge( $get_params, [
					'error' => $unauthorized,
					'post'  => (int) $id,
				] ) );

				return;
			}

			$group = DT_Posts::get_post( 'groups', (int) $id, true, false );
			$group = is_wp_error( $group ) ? null : $group;
		}

		// Leaders and the parent church are written with permission checks off, so
		// a contact link - which has no capabilities at all - could otherwise
		// attach any contact or church on the site. Both are held to what the form
		// offers. Whoever already leads this church stays, so an edit never drops
		// a co-leader the actor did not add themselves.
		$existing_leaders = array_map( 'intval', wp_list_pluck( $group['leaders'] ?? [], 'ID' ) );
		$allowed_leaders  = array_merge( wp_list_pluck( $this->leader_options( $contact_id ), 'id' ), $existing_leaders );
		$leaders          = array_values( array_filter( $leaders, function ( $leader ) use ( $allowed_leaders, $actor ) {
			// A typed name, created below as someone this actor coaches.
			if ( ! is_numeric( $leader ) ) {
				return true;
			}

			$leader = (int) $leader;

			return in_array( $leader, $allowed_leaders, true ) || $actor->can_edit_contact( $leader );
		} ) );

		$parent_group = (int) $parent_group;

		if ( $parent_group ) {
			$existing_parent = (int) ( $group['parent_groups'][0]['ID'] ?? 0 );
			$known_leaders   = array_filter( $leaders, 'is_numeric' );
			$candidates      = array_map( 'intval', wp_list_pluck( $this->parent_group_candidates( $known_leaders ), 'ID' ) );
			$allowed_parent  = $parent_group !== (int) $id && (
				$parent_group === $existing_parent
				|| in_array( $parent_group, $candidates, true )
				|| $actor->can_edit_group( $parent_group )
			);

			if ( ! $allowed_parent ) {
				$this->form( array_merge( $get_params, [
					'error' => $unauthorized,
					'post'  => $id,
				] ) );

				return;
			}
		}

		foreach ( $leaders as $idx => $value ) {
			if ( ! is_numeric( $value ) ) {
				$title           = $value;
				$new_contact_fields = [
					'name'       => $title,
					'coached_by' => [
						"values" => [
							[ "value" => $contact_id ]
						]
					]
				];

				$assigned_to = $this->actor()->assigned_to_for_new_records();
				if ( $assigned_to ) {
					$new_contact_fields['assigned_to'] = $assigned_to;
				}

				$contact         = DT_Posts::create_post( 'contacts', $new_contact_fields, true, false );
				$leaders[ $idx ] = $contact['ID'];
				// Their link is issued when the coach copies it from the contacts
				// list, not here, so it runs from when it is handed over.
				wp_publish_post( $contact['ID'] );
			}
		}

		// Leaders with a negative number need to be removed.
		$leaders = array_reduce( $leaders, function ( $leaders, $leader_id ) {
			$leader_id = (int) $leader_id;
			if ( $leader_id > 0 ) {
				$leaders[] = [ 'value' => $leader_id ];
			}

			return $leaders;
		}, [] );

		$fields = [
			"title"         => $name,
			"leaders"       => [
				"force_values" => true,
				"values"       => $leaders
			],
			"members"       => [
				"force_values" => true,
				"values"       => $leaders
			],
			"parent_groups" => [
				"force_values" => true,
				"values"       => $parent_group ? [
					[ "value" => $parent_group ]
				] : []
			],
			"start_date"    => $start_date,
		];

		// Only touch the field when the form actually carried it, so a form rendered
		// without the location field leaves existing locations alone - while a form
		// that carried it can also clear the last one.
		if ( $has_location ) {
			$fields['location_grid_meta'] = [
				'force_values' => true,
				'values'       => $location
			];
		}

		if ( $id ) {
			$group = DT_Posts::update_post( 'groups', (int) $id, $fields, false, false );
			if ( is_wp_error( $group ) ) {
				$this->form( array_merge( $get_params, [
					'error' => $group->get_error_message(),
					'post'  => (int) $id,
				] ) );

				return;
			}
			do_action( 'dt_autolink_group_updated', $group );
		} else {
			// A contact link has no logged in user for DT to stamp on the record,
			// so it would land on `user-0` - owned by nobody and missing from every
			// DT list view. The site's base user is the catch-all for exactly this.
			$assigned_to = $this->actor()->assigned_to_for_new_records();
			if ( $assigned_to ) {
				$fields['assigned_to'] = $assigned_to;
			}

			$group = DT_Posts::create_post( 'groups', $fields, false, false );
			if ( is_wp_error( $group ) ) {
				$this->form( array_merge( $get_params, [
					'error' => $group->get_error_message()
				] ) );

				return;
			}
			do_action( 'dt_autolink_group_created', $group );
		}

		if ( is_wp_error( $group ) ) {
			$this->form( array_merge( $get_params, [
				'error' => $group->get_error_message()
			] ) );

			return;
		}

		wp_redirect( $this->functions->get_app_link() );
	}

	public function store( $params = [] ) {
		$nonce            = sanitize_key( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		$verify_nonce     = $nonce && wp_verify_nonce( $nonce, self::NONCE );
		$id               = sanitize_key( wp_unslash( $_POST['id'] ?? '' ) );
		$params['action'] = $this->functions->get_create_group_url();

		if ( ! $verify_nonce || $id ) {
			$this->form( [
				'error' => 'Invalid request'
			] );

			return;
		}

		$this->process( $params );
	}

	public function index( $params = [] ) {
		$nonce        = sanitize_key( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
		$verify_nonce = $nonce && wp_verify_nonce( $nonce, 'wp_rest' );

		if ( ! $verify_nonce ) {
			return new WP_REST_Response( __( 'Unauthorized', 'disciple-tools-autolink' ), 401 );
		}

		$limit  = sanitize_key( wp_unslash( $_GET['limit'] ?? 10 ) );
		$offset = sanitize_key( wp_unslash( $_GET['offset'] ?? 0 ) );

		$result = $this->actor()->query_groups( [
			'limit'  => $limit,
			'offset' => $offset,
			'sort'   => '-last_modified'
		] );


		if ( ! $result ) {
			return new WP_REST_Response( __( 'Could not fetch groups', 'disciple-tools-autolink' ), 400 );
		}

		$result['posts'] = array_map( function ( $church ) {
			foreach ( $church as $key => $value ) {
				if ( is_array( $value ) && isset( $value['timestamp'] ) ) {
					$church[ $key ]['formatted'] = dt_format_date( $value['timestamp'], get_option( 'date_format' ) );
				}
			}

			return $church;
		}, $result['posts'] ?? [] );
		$result['total'] = $result['total'] ?? 0;

		return $result;
	}
}
