<?php

/**
 * Turning a contact link into a real Disciple.Tools login.
 *
 * A contact link is a bearer key in a URL: whoever holds it is that contact.
 * That is what makes the chain easy to grow, and also what makes it worth
 * leaving behind - a link can be forwarded, sits in browser history, and cannot
 * be revoked for one person without revoking it for everyone. Taking a password
 * replaces it with something that can be signed out of and taken away.
 *
 * It secures one point in the chain, not the chain: everyone downstream still
 * holds a link of their own until they do the same.
 */
class Disciple_Tools_Autolink_Upgrade_Controller extends Disciple_Tools_Autolink_Controller {
	const NONCE = 'dt_autolink_upgrade';

	/**
	 * Show the form.
	 */
	public function show( $params = [] ) {
		$actor = $this->actor();

		// Someone already signed in has nothing to upgrade.
		if ( ! $this->available() ) {
			$this->functions->redirect_to_app();

			return;
		}

		$data = $this->global_data();
		// phpcs:ignore
		extract( $data );

		$action      = 'upgrade';
		$form_action = $this->functions->get_app_link() . '?action=upgrade';
		$nonce       = self::NONCE;
		$error       = $params['error'] ?? '';
		$username    = $params['username'] ?? '';
		$email       = $params['email'] ?? '';
		$cancel_url  = $this->functions->get_app_link();

		$translations = [
			'heading'           => __( 'Secure your access', 'disciple-tools-autolink' ),
			'help_text'         => __( 'Right now anyone with your link can open this app as you, and a link can stop working. Choose a username and password to keep long-term access of your own.', 'disciple-tools-autolink' ),
			'keeps_label'       => __( 'Your churches and the people you coach stay exactly as they are.', 'disciple-tools-autolink' ),
			'username_label'    => __( 'Username', 'disciple-tools-autolink' ),
			'email_label'       => __( 'Email', 'disciple-tools-autolink' ),
			'password_label'    => __( 'Password', 'disciple-tools-autolink' ),
			'confirm_label'     => __( 'Confirm password', 'disciple-tools-autolink' ),
			'submit_label'      => __( 'Create my login', 'disciple-tools-autolink' ),
			'cancel_label'      => __( 'Not now', 'disciple-tools-autolink' ),
			'revoke_notice'     => __( 'Once you have a login, your old link will stop working.', 'disciple-tools-autolink' ),
		];

		include __DIR__ . '/../templates/upgrade.php';
	}

	/**
	 * Whether this actor can take a login.
	 *
	 * @return bool
	 */
	public function available() {
		$actor = $this->actor();

		return $actor->is_contact()
			&& $actor->contact_id()
			&& ! Disciple_Tools_Autolink_Actor::contact_has_login( $actor->contact_id() );
	}

	/**
	 * Create the login and hand the records over to it.
	 */
	public function process( $params = [] ) {
		if ( ! $this->available() ) {
			$this->functions->redirect_to_app();

			return;
		}

		$nonce        = sanitize_key( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		$verify_nonce = $nonce && wp_verify_nonce( $nonce, self::NONCE );

		$username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ) );
		$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a password must survive verbatim.
		$password = (string) wp_unslash( $_POST['password'] ?? '' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$confirm  = (string) wp_unslash( $_POST['confirm_password'] ?? '' );

		$retry = [ 'username' => $username, 'email' => $email ];

		if ( ! $verify_nonce ) {
			$this->show( array_merge( $retry, [ 'error' => __( 'Unable to verify request. Please try again.', 'disciple-tools-autolink' ) ] ) );

			return;
		}

		if ( ! $username || ! $email || ! $password ) {
			$this->show( array_merge( $retry, [ 'error' => __( 'Please fill out all fields.', 'disciple-tools-autolink' ) ] ) );

			return;
		}

		if ( $password !== $confirm ) {
			$this->show( array_merge( $retry, [ 'error' => __( 'Passwords do not match.', 'disciple-tools-autolink' ) ] ) );

			return;
		}

		if ( ! is_email( $email ) ) {
			$this->show( array_merge( $retry, [ 'error' => __( 'Please enter a valid email address.', 'disciple-tools-autolink' ) ] ) );

			return;
		}

		$actor      = $this->actor();
		$contact_id = $actor->contact_id();

		// Claim the contact before Disciple_Tools_Users::user_register_hook() runs,
		// so it does not create a second contact record for the same person.
		//
		// The theme also supports posting `corresponds_to_contact_id`, but that
		// branch calls DT_Posts::update_post() with permission checks on. We are
		// mid-upgrade and still unauthenticated, so it comes back a WP_Error that
		// the hook then reads as an array - a fatal. Setting the user option is the
		// same signal by a route that needs no capabilities; the contact side of
		// the link is written afterwards by link_contact().
		$claim_contact = function ( $new_user_id ) use ( $contact_id ) {
			update_user_option( $new_user_id, 'corresponds_to_contact', $contact_id );
		};

		add_action( 'user_register', $claim_contact, 1 );
		$user_id = wp_create_user( $username, $password, $email );
		remove_action( 'user_register', $claim_contact, 1 );

		if ( is_wp_error( $user_id ) ) {
			$this->show( array_merge( $retry, [ 'error' => $user_id->get_error_message() ] ) );

			return;
		}

		$this->link_contact( $user_id, $contact_id );
		$this->hand_over_records( $user_id, $contact_id, $actor );
		$this->revoke_contact_link( $contact_id );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );
		Disciple_Tools_Autolink_Actor::set_user( $user_id );

		// Mint the user magic link so they land in their own app.
		$this->functions->activate();

		do_action( 'dt_autolink_contact_upgraded', $user_id, $contact_id );

		wp_redirect( $this->functions->get_app_link() );
	}

	/**
	 * Make sure the contact and the user point at each other.
	 *
	 * user_register_hook() normally does this from the posted
	 * corresponds_to_contact_id, but it is guarded by nonce checks and request
	 * shapes that are not ours, so the result is confirmed here rather than
	 * assumed.
	 *
	 * @param int $user_id
	 * @param int $contact_id
	 */
	private function link_contact( $user_id, $contact_id ) {
		if ( (int) get_post_meta( $contact_id, 'corresponds_to_user', true ) !== (int) $user_id ) {
			DT_Posts::update_post( 'contacts', $contact_id, [
				'corresponds_to_user' => $user_id,
				'type'                => 'user',
			], false, false );
		}

		if ( (int) get_user_option( 'corresponds_to_contact', $user_id ) !== (int) $contact_id ) {
			update_user_option( $user_id, 'corresponds_to_contact', $contact_id );
		}

		$contact = get_post( $contact_id );

		if ( $contact && $contact->post_title ) {
			wp_update_user( [
				'ID'           => $user_id,
				'display_name' => $contact->post_title,
			] );
		}
	}

	/**
	 * Move the records this person built onto their new account.
	 *
	 * Churches and people created through a contact link are assigned to the
	 * site's base user, because there was no user to assign them to. Now there is
	 * one, so the churches they lead and the people they coach become theirs -
	 * leaving alone anything a real person has already taken responsibility for.
	 *
	 * The people matter as much as the churches. Once signed in, whether they can
	 * edit someone is Disciple.Tools' own question - see
	 * Disciple_Tools_Autolink_Actor::can_edit_contact() - and coaching someone
	 * grants nothing there. Left with the base user, everyone they added through
	 * their link would lose Edit and Copy link the moment they upgraded.
	 *
	 * @param int $user_id
	 * @param int $contact_id
	 * @param Disciple_Tools_Autolink_Actor $actor the pre-upgrade actor, whose
	 *                                             church and coaching lists are
	 *                                             already loaded
	 */
	private function hand_over_records( $user_id, $contact_id, $actor ) {
		foreach ( $actor->own_groups() as $group ) {
			$this->hand_over( 'groups', (int) $group['ID'], $user_id );
		}

		foreach ( $actor->coached_contacts() as $coached ) {
			$this->hand_over( 'contacts', (int) $coached['ID'], $user_id );
		}
	}

	/**
	 * Assign one record to the new account, unless someone real already has it.
	 *
	 * @param string $post_type
	 * @param int $post_id
	 * @param int $user_id
	 */
	private function hand_over( $post_type, $post_id, $user_id ) {
		$base_user_id = (int) dt_get_base_user( true );
		$assigned_to  = get_post_meta( $post_id, 'assigned_to', true );
		$assigned_id  = $assigned_to ? (int) dt_get_user_id_from_assigned_to( $assigned_to ) : 0;

		if ( $assigned_id && $assigned_id !== $base_user_id ) {
			return;
		}

		DT_Posts::update_post( $post_type, $post_id, [
			'assigned_to' => 'user-' . $user_id,
		], false, false );
	}

	/**
	 * Retire the magic key.
	 *
	 * Any copy of the old link that has been forwarded or left in a browser would
	 * otherwise keep working - and would now resolve, through `assigned_to`, to
	 * everything the new account can reach. Securing this point in the chain means
	 * the bearer key stops being a way in.
	 *
	 * @param int $contact_id
	 */
	private function revoke_contact_link( $contact_id ) {
		delete_post_meta( $contact_id, 'autolink_contact_magic_key' );
	}
}
