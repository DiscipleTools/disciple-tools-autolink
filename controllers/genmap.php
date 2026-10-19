<?php

class Disciple_Tools_Autolink_Genmap_Controller extends Disciple_Tools_Autolink_Controller {
	public function show( $params = [] ) {
		if ( ! class_exists( 'DT_Genmapper_Groups_chart' ) ) {
			wp_redirect( $this->functions->get_app_link() );

			return;
		}

		$data = $this->global_data();
		extract( $data );
		$action    = 'genmap';
		$app_class = 'app--genmap';

		include __DIR__ . '/../templates/genmap.php';
	}

	/**
	 * Add a child church from the GenMap "+" button.
	 *
	 * The chart used to write through the theme's own `dt-posts/v2` API, which
	 * needs a login - so on a contact link every add came back 401, and the
	 * theme's 401 handler reloaded the page. It comes through the magic link
	 * instead, which means this, not a capability, is what decides the write.
	 *
	 * @param WP_REST_Request $request
	 * @param array $params
	 * @param Disciple_Tools_Autolink_Actor $actor
	 *
	 * @return array|WP_Error
	 */
	public function create_node( WP_REST_Request $request, $params, $actor ) {
		$parent_id = (int) ( $params['parent_id'] ?? 0 );

		if ( ! $parent_id || ! $this->can_add_under( $actor, $parent_id ) ) {
			return new WP_Error( __METHOD__, __( 'You cannot add a church here.', 'disciple-tools-autolink' ), [ 'status' => 403 ] );
		}

		$fields = [
			'title'         => __( 'New', 'disciple-tools-autolink' ),
			'group_type'    => 'group',
			'parent_groups' => [
				'values' => [
					[ 'value' => $parent_id ],
				],
			],
		];

		$assigned_to = $actor->assigned_to_for_new_records();
		if ( $assigned_to ) {
			$fields['assigned_to'] = $assigned_to;
		}

		// A contact reaches churches through `leaders`, and the base user it is
		// assigned to is not them. Without leading it, the church they just added
		// would be one they could not edit, and gone from their list on reload.
		// The church form does the same - it starts with the actor as leader.
		if ( $actor->is_contact() && $actor->contact_id() ) {
			$fields['leaders'] = [
				'values' => [
					[ 'value' => $actor->contact_id() ],
				],
			];
		}

		$group = DT_Posts::create_post( 'groups', $fields, true, false );

		if ( is_wp_error( $group ) ) {
			return $group;
		}

		do_action( 'dt_autolink_group_created', $group );

		return [
			'ID'    => $group['ID'],
			'title' => $group['title'] ?? $fields['title'],
		];
	}

	/**
	 * Save the GenMap edit dialog: name, status and type.
	 *
	 * @param WP_REST_Request $request
	 * @param array $params
	 * @param Disciple_Tools_Autolink_Actor $actor
	 *
	 * @return array|WP_Error
	 */
	public function update_node( WP_REST_Request $request, $params, $actor ) {
		$group_id = (int) ( $params['group_id'] ?? 0 );

		if ( ! $group_id || ! $this->can_edit_node( $actor, $group_id ) ) {
			return new WP_Error( __METHOD__, __( 'You cannot edit this church.', 'disciple-tools-autolink' ), [ 'status' => 403 ] );
		}

		$submitted = is_array( $params['fields'] ?? null ) ? $params['fields'] : [];
		$settings  = DT_Posts::get_post_field_settings( 'groups' );
		$fields    = [];

		if ( isset( $submitted['title'] ) && trim( $submitted['title'] ) !== '' ) {
			$fields['title'] = sanitize_text_field( $submitted['title'] );
		}

		// Only values the field actually offers - the dialog draws its radios
		// from the same settings, so anything else did not come from it.
		foreach ( [ 'group_status', 'group_type' ] as $key ) {
			if ( isset( $submitted[ $key ] ) && isset( $settings[ $key ]['default'][ $submitted[ $key ] ] ) ) {
				$fields[ $key ] = $submitted[ $key ];
			}
		}

		if ( empty( $fields ) ) {
			return new WP_Error( __METHOD__, __( 'Nothing to save.', 'disciple-tools-autolink' ), [ 'status' => 400 ] );
		}

		$group = DT_Posts::update_post( 'groups', $group_id, $fields, true, false );

		if ( is_wp_error( $group ) ) {
			return $group;
		}

		do_action( 'dt_autolink_group_updated', $group );

		return [ 'ID' => $group['ID'] ];
	}

	/**
	 * Whether the actor may add a church under a node on the map.
	 *
	 * The map draws every generation below the actor's churches, deeper than
	 * can_edit_group() reaches. A user brings Disciple.Tools' own permissions
	 * with them, and before this went through the magic link those were all the
	 * "+" button asked for - so a user keeps them. A contact has no such
	 * permissions and stays scoped to their own and their coachees' churches.
	 *
	 * @param Disciple_Tools_Autolink_Actor $actor
	 * @param int $parent_id
	 *
	 * @return bool
	 */
	private function can_add_under( $actor, $parent_id ) {
		if ( $actor->can_edit_group( $parent_id ) ) {
			return true;
		}

		return $actor->is_user()
			&& DT_Posts::can_create( 'groups' )
			&& DT_Posts::can_view( 'groups', (int) $parent_id );
	}

	/**
	 * Whether the actor may save the edit dialog for a node on the map.
	 *
	 * @see can_add_under() for why a user is not held to can_edit_group().
	 *
	 * @param Disciple_Tools_Autolink_Actor $actor
	 * @param int $group_id
	 *
	 * @return bool
	 */
	private function can_edit_node( $actor, $group_id ) {
		if ( $actor->can_edit_group( $group_id ) ) {
			return true;
		}

		return $actor->is_user() && DT_Posts::can_update( 'groups', (int) $group_id );
	}
}
