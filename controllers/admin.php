<?php

class Disciple_Tools_Autolink_Admin_Controller extends Disciple_Tools_Autolink_Controller {
	public function show( $params = [] ) {
		if ( ! current_user_can( 'manage_dt' ) ) { // manage dt is a permission that is specific to Disciple.Tools and allows admins, strategists and dispatchers into the wp-admin
			wp_die( 'You do not have sufficient permissions to access this page.' );
		}

		$tabs = [
			'general'  => __( 'General', 'disciple-tools-autolink' ),
			'training' => __( 'Training Videos', 'disciple-tools-autolink' ),
		];

		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		if ( ! isset( $tabs[ $current_tab ] ) ) {
			$current_tab = 'general';
		}

		$link       = 'admin.php?page=disciple_tools_autolink&tab=';
		$page_title = __( 'Autolink Settings', 'disciple-tools-autolink' );

		switch ( $current_tab ) {
			case 'training':
				$tab = new Disciple_Tools_Autolink_Tab_Training();
				break;
			default:
				$tab = new Disciple_Tools_Autolink_Tab_General();
				break;
		}

		include __DIR__ . '/../templates/admin/settings.php';
	}
}
