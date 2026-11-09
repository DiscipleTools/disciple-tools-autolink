<?php

class Disciple_Tools_Autolink_Register_Controller extends Disciple_Tools_Autolink_Controller {

    /**
     * Process the register form
     */
    public function process( $params = [] ) {
        $nonce        = sanitize_key( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
        $verify_nonce = $nonce && wp_verify_nonce( $nonce, 'dt_autolink_register' );

        if ( ! $verify_nonce ) {
            return $this->show( [ 'error' => 'Unable to verify request.' ] );

            return;
        }

        $name             = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
        $username         = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
        $password         = sanitize_text_field( wp_unslash( $_POST['password'] ?? '' ) );
        $email            = sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) );
        $confirm_password = sanitize_text_field( wp_unslash( $_POST['confirm_password'] ?? '' ) );

        if ( ! $username || ! $password || ! $email ) {
            return $this->show( [
                'error' => 'Please fill out all fields.',
                'username' => $username,
                'email' => $email,
                'password' => $password
            ] );
        }

        if ( $confirm_password !== $password ) {
            return $this->show( [
                'error' => 'Passwords do not match',
                'username' => $username,
                'email' => $email,
                'password' => $password
            ] );
        }

        $user = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user ) ) {
            $error = $user->get_error_message();

            return $this->show( [ 'error' => $error ] );
        }

        $user_obj = get_user_by( 'id', $user );

        wp_set_current_user( $user );
        wp_set_auth_cookie( $user_obj->ID );

        if ( ! $user ) {
            return $this->show( [ 'error' => esc_html_e( 'An unexpected error has occurred.', 'disciple-tools-autolink' ) ] );
        }

        $this->functions->activate();
        $this->functions->add_session_leader();
        $this->functions->redirect_to_link();
    }

    /**
     * Show the register template
     */
    public function show( $params = [] ) {
        $logo_url    = $this->functions->fetch_logo();
        $form_action = add_query_arg( 'action', 'register', $this->functions->get_link_url() );
        $error       = $params['error'] ?? '';
        $username    = $params['username'] ?? '';
        $email       = $params['email'] ?? '';
        $password    = $params['password'] ?? '';

        include __DIR__ . '/../templates/register.php';
    }
}
