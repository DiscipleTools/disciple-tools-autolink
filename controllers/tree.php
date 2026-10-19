<?php

use jobs\DiscipleToolsAutolinkSaveTreeJob;

class Disciple_Tools_Autolink_Tree_Controller extends Disciple_Tools_Autolink_Controller {
	const NONCE = 'dt_autolink_tree';
	private $tree_chart = null;

	public function __construct() {
		parent::__construct();
		$this->functions  = Disciple_Tools_Autolink_Magic_Functions::instance();
		$this->tree_chart = Disciple_Tools_Autolink_Groups_Tree::instance();
	}

	public function show( $params = [] ) {
		$magic_link = Disciple_Tools_Autolink_Magic_Functions::app();
		$data       = $this->global_data();
		extract( $data );
		$action       = 'tree';
		$fetch_url    = '/wp-json/autolink/v1/' . $magic_link->parts['type'];
		$parts        = $magic_link->parts;
		$translations = [
			/* translators: %s: singular name of the group post type, e.g. "Church". */
			'tree_title'        => sprintf( __( '%s Tree', 'disciple-tools-autolink' ), $church_label ),
			/* translators: %s: plural name of the group post type, e.g. "Churches". */
			'unassigned_title'  => sprintf( __( 'Unassigned %s', 'disciple-tools-autolink' ), $churches_label ),
			/* translators: 1: singular name of the group post type, e.g. "Church". 2: the translated word "Tree". */
			'unassigned_tip'    => sprintf( __( 'Move these to the %1$s %2$s to assign them to a %1$s.', 'disciple-tools-autolink' ), $church_label, $tree_label ),
			'key_title'         => __( 'Key', 'disciple-tools-autolink' ),
			/* translators: %s: plural name of the group post type, e.g. "Churches". */
			'assigned_label'    => sprintf( __( '%s you are assigned', 'disciple-tools-autolink' ), $churches_label ),
			/* translators: %s: plural name of the group post type, e.g. "Churches". */
			'coached_label'     => sprintf( __( '%s assigned to those you coach', 'disciple-tools-autolink' ), $churches_label ),
			/* translators: %s: plural name of the group post type, e.g. "Churches". */
			'leading_label'     => sprintf( __( '%s you lead', 'disciple-tools-autolink' ), $churches_label ),
			'generation_label'  => __( 'Generation Number', 'disciple-tools-autolink' ),
			/* translators: %s: plural name of the group post type, e.g. "Churches". */
			'no_groups_message' => sprintf( __( 'No %s found.', 'disciple-tools-autolink' ), $churches_label ),
		];

		include __DIR__ . '/../templates/tree.php';
	}

	/**
	 * Re-parent a church in the tree.
	 *
	 * @param WP_REST_Request $request
	 * @param array $params
	 * @param Disciple_Tools_Autolink_Actor $actor
	 */
	public function process( WP_REST_Request $request, $params, $actor ) {
		global $wpdb;

		if ( ! isset( $params['data']['previous_parent'] ) ) {
			$params['data']['previous_parent'] = 'root';
		}
		if ( ( ! isset( $params['data']['new_parent'] ) || ( ! isset( $params['data']['self'] ) ) ) ) {
			return 'false';
		}

		// Both ends of the move have to be in reach. A contact link carries no
		// capabilities for DT_Posts to check, so the check is made here and
		// applies to the user link too.
		if ( ! $actor->can_edit_group( $params['data']['self'] ) ) {
			return 'reload';
		}

		$group     = DT_Posts::get_post( 'groups', $params['data']['self'], true, false );
		$new_group = null;

		if ( $params['data']['new_parent'] != 'root' ) {
			if ( ! $actor->can_edit_group( $params['data']['new_parent'] ) ) {
				return 'reload';
			}

			$new_group = DT_Posts::get_post( 'groups', $params['data']['new_parent'], true, false );
		}

		// Detaching a church to the top of the tree stays reserved for the
		// churches the person holds themselves, not those of people they coach.
		if ( ! $new_group && ! $actor->owns_group( $params['data']['self'] ) ) {
			return "reload";
		}

		$wpdb->query( "START TRANSACTION" );

		if ( $params['data']['previous_parent'] ) {
			$wpdb->query( $wpdb->prepare(
				"DELETE
                FROM $wpdb->p2p
                WHERE p2p_from = %s
                    AND p2p_to = %s
                    AND p2p_type = 'groups_to_groups'",
				$params['data']['self'],
				$params['data']['previous_parent']
			) );
		}


		if ( $params['data']['new_parent'] && $params['data']['new_parent'] !== 'root' ) {
			$response = $wpdb->query( $wpdb->prepare(
				"INSERT INTO $wpdb->p2p (p2p_from, p2p_to, p2p_type)
                    VALUES (%s, %s, 'groups_to_groups');
            ",
				$params['data']['self'],
				$params['data']['new_parent']
			) );


			if ( ! $response ) {
				$wpdb->query( "ROLLBACK" );

				return false;
			}
		}

		$wpdb->query( "COMMIT" );

		return true;
	}

	/**
	 * @param WP_REST_Request $request
	 * @param array $params
	 * @param Disciple_Tools_Autolink_Actor $actor
	 */
	public function data( WP_REST_Request $request, $params, $actor ) {
		echo wp_json_encode( $this->tree_chart->tree() );
	}
}
