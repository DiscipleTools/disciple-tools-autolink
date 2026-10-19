<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly.

/**
 * Class Disciple_Tools_Autolink_Groups_Tree
 */
class Disciple_Tools_Autolink_Groups_Tree {

	const UNNESTED_META_KEY = 'dt_autolink_unnested';
	private static $_instance = null;

	/**
	 * Disciple_Tools_Autolink_Groups_Tree constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'p2p_created_connection' ] );
		add_action( 'dt_autolink_group_created', [ $this, 'dt_autolink_group_created' ], 10, 2 );

		$action = sanitize_key( wp_unslash( $_GET['action'] ?? '' ) );
		if ( $action !== 'tree' ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 100 );
	}

	/**
	 * Return the instance of the class
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Enqueue scripts and styles
	 */
	public function wp_enqueue_scripts() {
		$plugin_url  = plugins_url() . '/disciple-tools-autolink';
		$plugin_path = WP_PLUGIN_DIR . '/disciple-tools-autolink';

		wp_register_script( 'jquery-touch-punch', '/wp-includes/js/jquery/jquery.ui.touch-punch.js' ); // @phpcs:ignore
		wp_enqueue_script( 'portal-app-domenu-js', $plugin_url . '/magic-link/jquery.domenu-0.100.77.min.js', [ 'jquery' ],
        filemtime( $plugin_path . '/magic-link/jquery.domenu-0.100.77.min.js' ), false );

		wp_enqueue_style( 'portal-app-domenu-css', $plugin_url . '/magic-link/jquery.domenu-0.100.77.css', [],
        filemtime( $plugin_path . '/magic-link/jquery.domenu-0.100.77.css' ) );
	}

	/**
	 * Add a meta to the group to indicate that it was created by autolink
	 * so we know to handle it differently during tree nesting
	 *
	 * @param array $group
	 */
	public function dt_autolink_group_created( $group ) {
		add_post_meta( $group['ID'], self::UNNESTED_META_KEY, true );
	}

	/**
	 * When a group is created, we don't need to nest it differently anymore
	 */
	public function p2p_created_connection( $connection_id ) {
		$connection = p2p_get_connection( $connection_id );
		if ( ! $connection ) {
			return;
		}
		if ( $connection->p2p_type == 'groups_to_groups' ) {
			$group_id                     = $connection->p2p_to;
			$is_newly_created_by_autolink = get_post_meta( $group_id, self::UNNESTED_META_KEY, true );
			if ( $is_newly_created_by_autolink ) {
				return;
			}
			delete_post_meta( $group_id, self::UNNESTED_META_KEY );
		}
	}

	/**
	 * Get the tree of groups
	 */
	public function tree() {
		$title_list          = [];
		$pre_tree            = [];
		$unassigned_group_id = 'u';

		$actor   = Disciple_Tools_Autolink_Actor::current();
		$contact = $actor->contact();

		if ( ! $contact ) {
			return [];
		}

		$meta = [
			'assigned' => [],
			'coaching' => [],
			'parents'  => [],
			'titles'   => [],
		];

		$allowed_group_ids = [];

		$groups = $actor->own_groups();

		$coaching = $actor->coached_contacts();

		foreach ( $groups as $group ) {
			$allowed_group_ids[]              = (int) $group['ID'];
			$groups[]                         = $group;
			$meta['assigned'][ $group['ID'] ] = true;
			$meta['leading'][ $group['ID'] ]  = array_filter( $group['leaders'], function ( $leader ) use ( $contact ) {
				return $leader['ID'] == $contact['ID'];
			} );
			$meta['coaching'][ $group['ID'] ] = false;
			$meta['titles'][ $group['ID'] ]   = $group['name'];
		}

		foreach ( $coaching as $coached ) {
			// A coached contact may have no Disciple.Tools login of their own -
			// that is the whole point of contact links - so their churches are
			// found by whichever handle they do have.
			$coached_groups = Disciple_Tools_Autolink_Actor::query_groups_for_contact( $coached, [
				'limit' => 1000,
			] )['posts'];

			foreach ( $coached_groups as $coached_group ) {
				// A church this actor and someone they coach both lead is the
				// actor's own - the usual case, since adding a new leader on the
				// church form makes them someone the actor coaches. Left to run on,
				// this would refile it as a coaching church: not draggable, and
				// under "Unassigned" when it has no parent.
				if ( ! empty( $meta['assigned'][ $coached_group['ID'] ] ) ) {
					continue;
				}

				$allowed_group_ids[]                    = (int) $coached_group['ID'];
				$groups[]                                 = $coached_group;
				$meta['leading'][ $coached_group['ID'] ]  = array_filter( $coached_group['leaders'], function ( $leader ) use ( $contact ) {
					return $leader['ID'] == $contact['ID'];
				} );
				$meta['assigned'][ $coached_group['ID'] ] = false;
				$meta['coaching'][ $coached_group['ID'] ] = true;
				$meta['titles'][ $coached_group['ID'] ]   = $coached_group['name'];
			}
		}

		$meta['groups'] = $allowed_group_ids;

		foreach ( $groups as $p ) {
			$first_root_group = null;
			foreach ( $pre_tree as $pre_group_id => $pre_parent_group_id ) {
				if ( ! $pre_parent_group_id ) {
					$first_root_group = $pre_group_id;
					break;
				}
			}


			$parents = [];
			foreach ( $p['parent_groups'] as $parent_group ) {
				$parents[] = $parent_group['ID'];
			}

			$has_parent          = ! empty( $parents );
			$has_allowed_parent  = $has_parent && array_filter( $parents, function ( $parent ) use ( $allowed_group_ids ) {
					return in_array( $parent, $allowed_group_ids );
			} );
			$contact_is_assigned = $meta['assigned'][ $p['ID'] ] ?? false;


			if ( isset( $p['child_groups'] ) && ! empty( $p['child_groups'] ) ) {
				foreach ( $p['child_groups'] as $children ) {
					$pre_tree[ $children['ID'] ] = $p['ID'];
				}
			}

			if ( ! $has_allowed_parent && $contact_is_assigned ) {
				$pre_tree[ $p['ID'] ] = null;
			} elseif ( ! $has_allowed_parent ) {
				if ( ! $has_parent ) {
					$pre_tree[ $p['ID'] ] = $unassigned_group_id;
				} else {
					$pre_tree[ $p['ID'] ] = null;
				}
			}

			$title                  = $p['name'];
			$title_list[ $p['ID'] ] = $title;

			$meta['parents'][ $p['ID'] ] = $has_allowed_parent;

			if ( isset( $pre_tree[ $p['ID'] ] ) && is_null( $pre_tree[ $p['ID'] ] ) && is_null( $first_root_group ) ) {
				$first_root_group = $p['ID'];
			}
		}

		if ( array_search( $unassigned_group_id, $pre_tree ) ) {
			$meta['groups'][]                         = $unassigned_group_id;
			$pre_tree[ $unassigned_group_id ]         = null;
			$meta['assigned'][ $unassigned_group_id ] = false;
			$meta['coaching'][ $unassigned_group_id ] = false;
			$meta['parents'][ $unassigned_group_id ]  = false;
			$meta['titles'][ $unassigned_group_id ]   = 'Unassigned';
		}

		$tree = $this->parse_tree( $pre_tree, $meta, null );

		if ( is_null( $tree ) ) {
			$tree = [];
		}

		return [
			'tree' => $tree
		];
	}

	/**
	 * Parse the tree
	 *
	 * @param array $tree
	 * @param array $meta
	 * @param string $root
	 *
	 * @return array|null
	 */
	private function parse_tree( $tree, $meta, $root = null ) {
		$return = [];
		# Traverse the tree and search for direct children of the root
		foreach ( $tree as $child => $parent ) {
			# A direct child is found
			if ( $parent == $root && in_array( $child, $meta['groups'] ) ) {
				# Remove item from tree (we don't need to traverse this again)
				unset( $tree[ $child ] );
				# Append the child into result array and parse its children
				$return[] = [
					'id'         => $child,
					'title'      => $child,
					'name'       => $meta['titles'][ $child ] ?? 'No Name',
					'children'   => $this->parse_tree( $tree, $meta, $child ),
					'assigned'   => $meta['assigned'][ $child ] ?? false,
					'has_parent' => $meta['parents'][ $child ] ?? false,
					"leading"    => $meta['leading'][ $child ] ?? false,
					"coaching"   => $meta['coaching'][ $child ] ?? false
				];
			}
		}

		return empty( $return ) ? null : $return;
	}
}

Disciple_Tools_Autolink_Groups_Tree::instance();
