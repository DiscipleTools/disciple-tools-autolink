<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly
require_once "tab-general.php";
require_once "tab-training.php";

/**
 * Class Disciple_Tools_Autolink_Menu
 */
class Disciple_Tools_Autolink_Menu {

	private static $_instance = null;
	public $token = 'disciple_tools_autolink';
    public $page_title = 'Autolink';
	private $controller = null;

	/**
	 * Constructor function.
	 * @access  public
	 * @since   0.1.0
	 */
	public function __construct() {

		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_enqueue_scripts' ] );

		$this->page_title = __( 'Autolink', 'disciple-tools-autolink' );
		$this->controller = new Disciple_Tools_Autolink_Admin_Controller();
	} // End instance()

	/**
	 * Disciple_Tools_Autolink_Menu Instance
	 *
	 * Ensures only one instance of Disciple_Tools_Autolink_Menu is loaded or can be loaded.
	 *
	 * @return Disciple_Tools_Autolink_Menu instance
	 * @since 0.1.0
	 * @static
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function admin_enqueue_scripts() {
		$plugin_url = plugins_url() . '/disciple-tools-autolink';

		wp_enqueue_script( 'magic_link_scripts', $plugin_url . '/dist/admin.js', [
			'jquery',
			'lodash',
		], filemtime( plugin_dir_path( __FILE__ ) . '../dist/admin.js' ), true );
	} // End __construct()

	/**
	 * Loads the subnav page
	 * @since 0.1
	 */
	public function register_menu() {
		$this->page_title = __( 'Autolink', 'disciple-tools-autolink' );

		add_submenu_page( 'dt_extensions', $this->page_title, $this->page_title, 'manage_dt', $this->token, [
			$this,
			'content'
		] );
	}

	/**
	 * Menu stub. Replaced when Disciple.Tools Theme fully loads.
	 */
	public function extensions_menu() {
	}

	/**
	 * Builds page contents
	 * @since 0.1
	 */
	public function content() {
		$this->controller->show( [
			'token' => $this->token,
		] );
	}
}

Disciple_Tools_Autolink_Menu::instance();
