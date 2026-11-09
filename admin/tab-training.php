<?php

/**
 * Class Disciple_Tools_Autolink_Tab_Training
 */
class Disciple_Tools_Autolink_Tab_Training {

	private $controller = null;

	public function __construct() {
		$this->controller = new Disciple_Tools_Autolink_Admin_Training_Controller();
	}

	public function content() {
		$params = [];
		// phpcs:ignore
		if ( isset( $_POST['dt_admin_form_nonce'] ) ) {
			try {
				$this->controller->save();
			} catch ( Exception $e ) {
				$params['error'] = $e->getMessage();
			}
		}

		return $this->controller->show( $params );
	}
}
