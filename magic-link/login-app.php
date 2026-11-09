<?php
if ( !defined( 'ABSPATH' ) ) {
    exit;
} // Exit if accessed directly.

/**
 * Adds a non-object (neither post or user) magic link page.
 */
class Disciple_Tools_Autolink_Login_App extends DT_Magic_Url_Base
{
    public $magic = false;
    public $parts = false;
    public $page_title = 'Auto Link | Login';
    public $root = 'autolink';
    public $type = 'page';
    public $type_name = 'Auto Link';
    public static $token = 'autolink_login';
    public $functions;
    public $login_controller;
    public $register_controller;

    private static $_instance = null;
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    } // End instance()


    public function __construct() {
        parent::__construct();

        $url = dt_get_url_path( true );
        $this->functions = new Disciple_Tools_Autolink_Magic_Functions();
        $this->login_controller = new Disciple_Tools_Autolink_Login_Controller();
        $this->register_controller = new Disciple_Tools_Autolink_Register_Controller();

        if ( ( $this->root ) === $url ) {

            $this->magic = new DT_Magic_URL( $this->root );
            $this->parts = $this->magic->parse_url_parts();

            // register url and access
            add_action( "template_redirect", [ $this, 'theme_redirect' ] );
            add_filter('dt_blank_access', function () {
                return true;
            }, 100, 1);
            add_filter('dt_allow_non_login_access', function () {
                return true;
            }, 100, 1);
            add_filter('dt_override_header_meta', function () {
                return true;
            }, 100, 1);

            // header content
            add_filter( "dt_blank_title", [ $this, "page_tab_title" ] ); // adds basic title to browser tab
            add_action( 'wp_print_scripts', [ $this, 'print_scripts' ], 1500 ); // authorizes scripts
            add_action( 'wp_print_styles', [ $this, 'print_styles' ], 1500 ); // authorizes styles


            // page content
            add_action( 'dt_blank_head', [ $this, '_header' ] );
            add_action( 'dt_blank_footer', [ $this, '_footer' ] );

            add_action('dt_blank_body', function () {
                $this->ready();
                $this->routes();
            }); // body for no post key

            add_filter( 'dt_magic_url_base_allowed_css', [ $this->functions, 'dt_magic_url_base_allowed_css' ], 10, 1 );
            add_filter( 'dt_magic_url_base_allowed_js', [ $this->functions, 'dt_magic_url_base_allowed_js' ], 10, 1 );

            add_filter( 'login_errors', [ $this, 'login_errors' ] );
            remove_all_actions( 'wp_login_failed' );

            add_action( 'wp_enqueue_scripts', [ $this, 'wp_enqueue_scripts' ], 100 );
        }
    }

    public function ready() {
        if ( is_user_logged_in() ) {
            $this->functions->activate();
            $this->functions->add_session_leader();
            $this->functions->redirect_to_app();
        }
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

    public function routes() {
        $action = sanitize_text_field( wp_unslash( $_GET['action'] ?? '' ) );
        $type = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );

        if ( $type === 'GET' ) {
            switch ( $action ) {
                case 'login':
                    $this->login_controller->show();
                    break;
                case 'register':
                    $this->register_controller->show();
                    break;
                default:
                    $this->login_controller->show();
                    break;
            }
            return;
        }

        if ( $type === 'POST' ) {
            switch ( $action ) {
                case 'login':
                    $this->login_controller->process();
                    break;
                case 'register':
                    $this->register_controller->process();
                    break;
                default:
                    wp_redirect( $this->functions->get_link_url() );
            }
            return;
        }
    }

    /**
     * change the error message if it is invalid_username or incorrect password
     *
     * @param string $message Error string provided by WordPress
     * @return string $message Modified error string
     */
    public function login_errors( $message ){
        global $errors;
        if ( isset( $errors->errors['invalid_username'] ) || isset( $errors->errors['incorrect_password'] ) || isset( $errors->errors['invalid_email'] ) ) {
            $message = __( 'Error: Invalid username/password combination.', 'disciple-tools-autolink' );
        }
        return $message;
    }
}
Disciple_Tools_Autolink_Login_App::instance();
