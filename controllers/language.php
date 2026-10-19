<?php

class Disciple_Tools_Autolink_Language_Controller extends Disciple_Tools_Autolink_Controller {
	/**
	 * Switch the actor's locale.
	 *
	 * The app is rendered server side and the theme filters `locale` with the
	 * actor's own locale on the front end, so persisting it here is all that is
	 * needed - the caller reloads to see the new language. Where it is persisted
	 * differs by mode: a user carries a locale, a contact does not.
	 *
	 * @param WP_REST_Request $request
	 * @param array $params
	 * @param Disciple_Tools_Autolink_Actor $actor
	 */
	public function update( WP_REST_Request $request, $params, $actor ) {
		$body   = $request->get_json_params();
		$locale = sanitize_text_field( wp_unslash( $body['locale'] ?? '' ) );

		if ( ! $locale ) {
			wp_send_json_error( [ 'message' => __( 'No language was given.', 'disciple-tools-autolink' ) ] );
		}

		// Only accept a locale the site actually ships, so a crafted request
		// can't write an arbitrary string onto the user.
		if ( ! in_array( $locale, wp_list_pluck( dt_get_available_languages(), 'language' ), true ) ) {
			wp_send_json_error( [ 'message' => __( 'That language is not available.', 'disciple-tools-autolink' ) ] );
		}

		$result = $actor->set_locale( $locale );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		wp_send_json_success( [ 'locale' => $locale ] );
	}
}
