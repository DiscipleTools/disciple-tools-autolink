<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.


/**
 * Class Disciple_Tools_Autolink_Magic_Link
 */
class Disciple_Tools_Autolink_Magic_Contact_App extends DT_Magic_Url_Base
{
    public $magic = false;
    public $parts = false;
    public $page_title = 'Autolink - Share Link';
    public $page_description = 'Invite people to Autolink. Anyone who registers through this link is coached by this contact.';
    public $root = "autolink";
    public $type = 'share';
    public $post_type = 'contacts';
    private $meta_key = 'autolink-share';
    public $show_bulk_send = false;
    public $show_app_tile = true; // show this magic link in the Apps tile on the post record
    public $functions;

    private static $_instance = null;
    public $meta = []; // Allows for instance specific data.

    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()

    public function __construct() {
        /**
         * Specify metadata structure, specific to the processing of current
         * magic link type.
         *
         * - meta:              Magic link plugin related data.
         *      - app_type:     Flag indicating type to be processed by magic link plugin.
         *      - post_type     Magic link type post type.
         *      - contacts_only:    Boolean flag indicating how magic link type user assignments are to be handled within magic link plugin.
         *                          If True, lookup field to be provided within plugin for contacts only searching.
         *                          If false, Dropdown option to be provided for user, team or group selection.
         *      - fields:       List of fields to be displayed within magic link frontend form.
         */
        $this->functions = Disciple_Tools_Autolink_Magic_Functions::instance();
        $this->meta = [
            'app_type'      => 'magic_link',
            'post_type'     => $this->post_type,
            'contacts_only' => true,
            'fields'        => [
                [
                    'id'    => 'name',
                    'label' => 'Name'
                ]
            ]
        ];

        $this->meta_key = $this->root . '_' . $this->type . '_magic_key';
        parent::__construct();

        /**
         * post type and module section
         */
        //        add_action( 'dt_details_additional_section', [ $this, 'dt_details_additional_section' ], 30, 2 );
        //        add_filter( 'dt_details_additional_tiles', [ $this, 'dt_details_additional_tiles' ], 10, 2 );
        //    add_action( 'rest_api_init', [ $this, 'add_endpoints' ] );


        /**
         * tests if other URL
         */
        $url = dt_get_url_path();
        $expected_url = $this->root . '/' . $this->type;
        if ( strpos( $url, $expected_url ) === false ) {
            return;
        }

        /**
         * tests magic link parts are registered and have valid elements
         */
        if ( !$this->check_parts_match() ) {
            return;
        }

        // load if valid url
        add_action( 'dt_blank_body', [ $this, 'ready' ] ); // body for no post key
        add_filter( 'dt_magic_url_base_allowed_css', [ $this->functions, 'dt_magic_url_base_allowed_css' ], 10, 1 );
        add_filter( 'dt_magic_url_base_allowed_js', [ $this->functions, 'dt_magic_url_base_allowed_js' ], 10, 1 );
        add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 100 );
    }

    // get the user id from the contact record and redirect to the user magic link
    public function ready() {
        $leader = DT_Posts::get_post( $this->post_type, $this->parts['post_id'], true, false );

        if ( !isset( $_COOKIE['dt_autolink_leader_id'] ) ) {
            setcookie( 'dt_autolink_leader_id', $leader['ID'], time() + ( 86400 * 30 ), "/" );
        }

        $this->functions->redirect_to_link();
    }

    public function wp_enqueue_scripts() {
        $this->functions->wp_enqueue_scripts();
        wp_localize_script(
            'magic_link_scripts',
            'magic',
            [
                'parts' => $this->parts,
                'rest_namespace' => $this->root . '/v1/' . $this->type,
            ]
        );
    }
}
Disciple_Tools_Autolink_Magic_Contact_App::instance();
