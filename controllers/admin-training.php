<?php


class Disciple_Tools_Autolink_Admin_Training_Controller extends Disciple_Tools_Autolink_Controller {

	/**
	 * Show the training videos tab
	 *
	 * @param array $params
	 *
	 * @return void
	 */
	public function show( $params = [] ): void {
		$default_training_videos = $this->settings->default_for( 'disciple_tools_autolink_training_videos' );
		$training_videos         = $this->settings->get_option( 'disciple_tools_autolink_training_videos' );
		$error                   = $params['error'] ?? null;

		if ( ! $this->validate_videos( $training_videos ) ) {
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
		$general_tab_url     = admin_url( 'admin.php?page=disciple_tools_autolink&tab=general' );

		include __DIR__ . '/../templates/admin/training.php';
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
	 * Save the training videos
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

		if ( ! isset( $_POST['disciple_tools_autolink_training_videos'] ) ) {
			return;
		}

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
}
