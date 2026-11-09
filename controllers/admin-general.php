<?php


class Disciple_Tools_Autolink_Admin_General_Controller extends Disciple_Tools_Autolink_Controller {

	/**
	 * The checkbox settings on this tab. An unchecked box posts nothing, so
	 * save() stores "0" for any of these that is missing.
	 */
	const CHECKBOXES = [
		'disciple_tools_autolink_show_in_menu',
		'disciple_tools_autolink_show_training',
		'disciple_tools_autolink_show_survey',
		'disciple_tools_autolink_allow_parent_group_selection',
		'disciple_tools_autolink_enable_contacts',
	];

	/**
	 * Show the admin tab
	 *
	 * @param array $params
	 *
	 * @return void
	 */
	public function show( $params = [] ): void {
		$old = [];
		foreach ( array_merge( self::CHECKBOXES, [
			'disciple_tools_autolink_contact_link_validity_amount',
			'disciple_tools_autolink_contact_link_validity_unit',
		] ) as $name ) {
			$old[ $name ] = $this->settings->get_option( $name );
		}

		$validity_units = [
			'hours'                                       => __( 'Hours', 'disciple-tools-autolink' ),
			'days'                                        => __( 'Days', 'disciple-tools-autolink' ),
			'weeks'                                       => __( 'Weeks', 'disciple-tools-autolink' ),
			'months'                                      => __( 'Months', 'disciple-tools-autolink' ),
			Disciple_Tools_Autolink_Contact_Links::NEVER => __( 'Never expires', 'disciple-tools-autolink' ),
		];
		$error          = $params['error'] ?? null;

		// Section headings follow whatever this site calls groups and contacts.
		$group_labels    = get_post_type_labels( get_post_type_object( 'groups' ) );
		$contact_labels  = get_post_type_labels( get_post_type_object( 'contacts' ) );
		$training_tab_url = admin_url( 'admin.php?page=disciple_tools_autolink&tab=training' );

		include __DIR__ . '/../templates/admin/general.php';
	}

	/**
	 * Save the admin  settings
	 *
	 * @param array $params
	 *
	 * @throws Exception
	 *
	 */
	public function save( $params = [] ): void {
		if ( ! isset( $_POST['dt_admin_form_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['dt_admin_form_nonce'] ) ), 'dt_admin_form' ) ) {
			return;
		}

		$post_vars = dt_recursive_sanitize_array( $_POST );

		foreach ( self::CHECKBOXES as $name ) {
			update_option( $name, ( isset( $post_vars[ $name ] ) && $post_vars[ $name ] === '1' ) ? "1" : "0" );
		}

		$validity_unit = $post_vars['disciple_tools_autolink_contact_link_validity_unit'] ?? 'weeks';
		if ( ! in_array( $validity_unit, array_merge( Disciple_Tools_Autolink_Contact_Links::UNITS, [ Disciple_Tools_Autolink_Contact_Links::NEVER ] ), true ) ) {
			$validity_unit = 'weeks';
		}
		update_option( 'disciple_tools_autolink_contact_link_validity_unit', $validity_unit );
		update_option( 'disciple_tools_autolink_contact_link_validity_amount', max( 1, absint( $post_vars['disciple_tools_autolink_contact_link_validity_amount'] ?? 1 ) ) );
	}
}
