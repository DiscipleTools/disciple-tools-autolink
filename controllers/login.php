<?php

class Disciple_Tools_Autolink_Login_Controller extends Disciple_Tools_Autolink_Controller {
    public $root = 'autolink';

    /**
     * Process the login form
     */
    public function process( $params = [] ) {
        global $errors;
        $nonce        = sanitize_key( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
        $verify_nonce = $nonce && wp_verify_nonce( $nonce, 'dt_autolink_login' );

        if ( ! $verify_nonce ) {
            return $this->show( [ 'error' => 'Unable to verify request.' ] );
        }

        $username = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
        $password = sanitize_text_field( wp_unslash( $_POST['password'] ?? '' ) );

        $user = wp_authenticate( $username, $password );

        if ( is_wp_error( $user ) ) {
            //phpcs:ignore
            $errors = $user;
            $error  = $errors->get_error_message();
            $error  = apply_filters( 'login_errors', $error );

            //If the error links to lost password, inject the 3/3rds redirect
            $error = str_replace( '?action=lostpassword', '?action=lostpassword&amp;redirect_to=' . rawurlencode( $this->functions->get_link_url() ), $error );

            return $this->show( [ 'error' => $error, 'username' => $username, 'password' => $password ] );
        }

        wp_set_auth_cookie( $user->ID );

        if ( ! $user ) {
            return $this->show( [ 'error' => esc_html_e( 'An unexpected error has occurred.', 'disciple-tools-autolink' ) ] );
        }

        wp_set_current_user( $user->ID );

        $this->functions->activate();
        $this->functions->add_session_leader();
        $this->functions->redirect_to_link();
    }

    /**
     * Show the login template
     */
    public function show( $params = [] ) {
        $logo_url     = $this->functions->fetch_logo();
        $register_url = add_query_arg( 'action', 'register', $this->functions->get_link_url() );
        $form_action  = add_query_arg( 'action', 'login', $this->functions->get_link_url() );
        $username     = $params['username'] ?? '';
        $password     = $params['password'] ?? '';
        $reset_url    = wp_lostpassword_url( $this->functions->get_link_url() );
        $error        = $params['error'] ?? '';

        include __DIR__ . '/../templates/login.php';
    }

    public function logout( $params = [] ) {
        wp_logout();
        $this->functions->redirect_to_link();
        exit;
    }
}
