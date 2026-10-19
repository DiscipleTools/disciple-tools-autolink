<?php

class Disciple_Tools_Autolink_App_Controller extends Disciple_Tools_Autolink_Controller {
	/**
	 * Show the app template
	 */
	public function show( $params = [] ) {
		$data = $this->global_data();
		extract( $data );

		$post_type          = get_post_type_object( 'groups' );
		$group_labels       = get_post_type_labels( $post_type );
		$delete_group_nonce = wp_create_nonce( 'dt_autolink_delete_group' );
		$action             = '';
		$limit              = 10;
		$churches           = $this->actor()->query_groups( [
			'limit' => $limit,
			'sort'  => '-post_date'
		] );

		//Apply WP formatting to all date fields.
		$churches['posts'] = array_map( function ( $church ) {
			foreach ( $church as $key => $value ) {
				if ( is_array( $value ) && isset( $value['timestamp'] ) ) {
					$church[ $key ]['formatted'] = dt_format_date( $value['timestamp'], get_option( 'date_format' ) );
				}
			}

			return $church;
		}, $churches['posts'] ?? [] );
		$churches['total'] = $churches['total'] ?? 0;
		$translations      = [
			/* translators: %s: plural name of the group post type, e.g. "Churches". */
			'groups_heading'       => sprintf( __( 'My %s', 'disciple-tools-autolink' ), $group_labels->name ),
			'start_date_label'     => __( 'Church Start Date', 'disciple-tools-autolink' ),
			/* translators: %s: singular name of the group post type, e.g. "Church". */
			'view_group'           => sprintf( __( 'View %s', 'disciple-tools-autolink' ), $group_labels->singular_name ),
			/* translators: %s: singular name of the group post type, e.g. "Church". */
			'delete_group'         => sprintf( __( 'Delete %s', 'disciple-tools-autolink' ), $group_labels->singular_name ),
			/* translators: %s: lowercased singular name of the group post type, e.g. "church". */
			'delete_group_confirm' => sprintf( __( 'Are you sure you want to delete this %s?', 'disciple-tools-autolink' ), strtolower( $group_labels->singular_name ) ),
			/* translators: %s: singular name of the group post type, e.g. "Church". */
			'edit_group'           => sprintf( __( 'Edit %s', 'disciple-tools-autolink' ), $group_labels->singular_name ),
			'more'                 => __( 'More', 'disciple-tools-autolink' )
		];
		$links             = [
			'delete_group' => $this->functions->get_app_link() . '?action=delete-group&_wpnonce=' . $delete_group_nonce,
			'edit_group'   => $this->functions->get_app_link() . '?action=edit-group',
			'create_group' => $this->functions->get_app_link() . '?action=create-group'
		];

		// The view screen frames the record's own Disciple.Tools page, which needs
		// a login. Someone holding a magic link has none, so they are not offered
		// it - the menu simply has one item fewer. An absent link is the signal;
		// see renderGroup() in churches.js.
		if ( ! $this->actor()->is_contact() ) {
			$links['view_group'] = $this->functions->get_app_link() . '?action=group';
		}

		$error = $params['error'] ?? false;

		if ( is_wp_error( $churches ) ) {
			$churches = [];
		}

		$group_fields                = DT_Posts::get_post_field_settings( 'groups' );
		$church_fields               = [
			'health_metrics' => $group_fields['health_metrics']['default'] ?? [],
		];
		$church_health_field         = $church_fields['health_metrics'];
		$allowed_church_count_fields = [
			'member_count',
			'leader_count',
			'believer_count',
			'baptized_count',
			'baptized_in_group_count'
		];
		$church_count_fields         = [];

		foreach ( $allowed_church_count_fields as $field ) {
			//Fields can be registered or deregistered by plugins,so check and make sure it exists
			if ( isset( $group_fields[ $field ] ) && ( ! isset( $group_fields[ $field ]['hidden'] ) || ! $group_fields[ $field ]['hidden'] ) ) {
				$church_count_fields[ $field ] = $group_fields[ $field ];
			}
		}

		include __DIR__ . '/../templates/app.php';
	}
}
