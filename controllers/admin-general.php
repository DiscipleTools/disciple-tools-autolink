<?php


class Disciple_Tools_Autolink_Admin_General_Controller extends Disciple_Tools_Autolink_Controller {

	/**
	 * Show the admin tab
	 *
	 * @param array $params
	 *
	 * @return void
	 */
	public function show( $params = [] ): void {
		$default_training_videos = $this->settings->default_for( 'disciple_tools_autolink_training_videos' );
		$old                     = [
			'disciple_tools_autolink_allow_parent_group_selection' => $this->settings->get_option( 'disciple_tools_autolink_allow_parent_group_selection' ),
			'disciple_tools_autolink_training_videos'              => $this->settings->get_option( 'disciple_tools_autolink_training_videos' ),
			'disciple_tools_autolink_show_in_menu'                 => $this->settings->get_option( 'disciple_tools_autolink_show_in_menu' ),
			'disciple_tools_autolink_show_survey'                  => $this->settings->get_option( 'disciple_tools_autolink_show_survey' ),
			'disciple_tools_autolink_show_training'                => $this->settings->get_option( 'disciple_tools_autolink_show_training' ),
			'disciple_tools_autolink_contact_link_validity_amount' => $this->settings->get_option( 'disciple_tools_autolink_contact_link_validity_amount' ),
			'disciple_tools_autolink_contact_link_validity_unit'   => $this->settings->get_option( 'disciple_tools_autolink_contact_link_validity_unit' ),
		];

		$validity_units = [
			'hours'                                       => __( 'Hours', 'disciple-tools-autolink' ),
			'days'                                        => __( 'Days', 'disciple-tools-autolink' ),
			'weeks'                                       => __( 'Weeks', 'disciple-tools-autolink' ),
			'months'                                      => __( 'Months', 'disciple-tools-autolink' ),
			Disciple_Tools_Autolink_Contact_Links::NEVER => __( 'Never expires', 'disciple-tools-autolink' ),
		];
		$error                   = $params['error'] ?? null;

		if ( ! $this->validate_videos( $old['disciple_tools_autolink_training_videos'] ) ) {
			$training_videos = $default_training_videos;
			update_option( 'disciple_tools_autolink_training_videos', $training_videos );
			$error = esc_attr( __( 'Training videos could not be loaded. Resetting to default content.', 'disciple-tools-autolink' ) );
		}

		$training_videos_translations = [
			'label'         => __( 'Training Videos', 'disciple-tools-autolink' ),
			'title'         => __( 'Title', 'disciple-tools-autolink' ),
			'embed'         => __( 'Embed', 'disciple-tools-autolink' ),
			'reset'         => __( 'Reset', 'disciple-tools-autolink' ),
			'add'           => __( 'Add', 'disciple-tools-autolink' ),
			'remove'        => __( 'Remove', 'disciple-tools-autolink' ),
			'resetConfirm'  => __( 'Are you sure you want to reset the training videos to default content?', 'disciple-tools-autolink' ),
			'removeConfirm' => __( 'Are you sure you want to remove this video?', 'disciple-tools-autolink' ),
			'up'            => __( 'Up', 'disciple-tools-autolink' ),
			'down'          => __( 'Down', 'disciple-tools-autolink' ),
		];

		$training_videos_url = $this->functions->get_training_url();

		include __DIR__ . '/../templates/admin/general.php';
	}

	private function validate_videos( $videos ): bool {
		if ( ! is_string( $videos ) ) {
			return false;
		}

		$videos = json_decode( $videos );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return false;
		}

		if ( ! is_array( $videos ) ) {
			return false;
		}

		// An empty list is valid: it is how an admin turns the training section off.
		if ( count( $videos ) === 0 ) {
			return true;
		}

		foreach ( $videos as $video ) {
			if ( ! isset( $video->title ) || ! isset( $video->embed ) ) {
				return false;
			}

			if ( ! is_string( $video->title ) || ! is_string( $video->embed ) ) {
				return false;
			}

			if ( ! str_contains( $video->embed, 'iframe' ) ) {
				return false;
			}
		}

		return true;
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

		if ( isset( $_POST['disciple_tools_autolink_training_videos'] ) ) {
			//Don't sanitize the training videos because we are saving HTML.
			//Strip control characters only - they break JSON decoding. Leave
			//high bytes alone so non-ASCII titles survive the round trip.
			// phpcs:ignore
			$training_videos = stripslashes( preg_replace( '/[\x00-\x1F]/', '', (string) $_POST['disciple_tools_autolink_training_videos'] ) );

			if ( ! $this->validate_videos( $training_videos ) ) {
				throw new Exception( esc_attr( __( 'Invalid training videos.', 'disciple-tools-autolink' ) ) );
			}
			update_option( 'disciple_tools_autolink_training_videos', $training_videos );
		}

		update_option( 'disciple_tools_autolink_allow_parent_group_selection', ( isset( $post_vars['disciple_tools_autolink_allow_parent_group_selection'] ) && $post_vars['disciple_tools_autolink_allow_parent_group_selection'] === '1' ) ? "1" : "0" );
		update_option( 'disciple_tools_autolink_show_in_menu', ( isset( $post_vars['disciple_tools_autolink_show_in_menu'] ) && $post_vars['disciple_tools_autolink_show_in_menu'] === '1' ) ? "1" : "0" );
		update_option( 'disciple_tools_autolink_show_survey', ( isset( $post_vars['disciple_tools_autolink_show_survey'] ) && $post_vars['disciple_tools_autolink_show_survey'] === '1' ) ? "1" : "0" );
		update_option( 'disciple_tools_autolink_show_training', ( isset( $post_vars['disciple_tools_autolink_show_training'] ) && $post_vars['disciple_tools_autolink_show_training'] === '1' ) ? "1" : "0" );

		$validity_unit = $post_vars['disciple_tools_autolink_contact_link_validity_unit'] ?? 'weeks';
		if ( ! in_array( $validity_unit, array_merge( Disciple_Tools_Autolink_Contact_Links::UNITS, [ Disciple_Tools_Autolink_Contact_Links::NEVER ] ), true ) ) {
			$validity_unit = 'weeks';
		}
		update_option( 'disciple_tools_autolink_contact_link_validity_unit', $validity_unit );
		update_option( 'disciple_tools_autolink_contact_link_validity_amount', max( 1, absint( $post_vars['disciple_tools_autolink_contact_link_validity_amount'] ?? 1 ) ) );
	}
}
