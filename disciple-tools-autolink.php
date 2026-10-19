<?php

/**
 * Plugin Name: Disciple.Tools - Autolink
 * Plugin URI: https://github.com/DiscipleTools/disciple-tools-autolink
 * Description: Provides a simplified interface for managing and visualizing your Disciple.Tools network. Share access with your downstream leaders to easily build your group tree.
 * Text Domain: disciple-tools-autolink
 * Domain Path: /languages
 * Version:  1.0.11
 * Author URI: https://www.eastwest.org/
 * GitHub Plugin URI: https://github.com/DiscipleTools/disciple-tools-autolink
 * Requires at least 4.7.0
 * (Requires 4.7+ because of the integration of the REST API at 4.7 and the security requirements of this milestone version.)
 * Tested up to: 5.6
 *
 * @package Disciple_Tools
 * @link    https://github.com/DiscipleTools
 * @license GPL-2.0 or later
 *          https://www.gnu.org/licenses/gpl-2.0.html
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Gets the instance of the `Disciple_Tools_Autolink` class.
 *
 * @return object|bool
 * @since  0.1
 * @access public
 */
function disciple_tools_autolink() {
	$disciple_tools_autolink_required_dt_theme_version = '1.35';
	$wp_theme                                          = wp_get_theme();
	$version                                           = $wp_theme->version;

	/*
	 * Check if the Disciple.Tools theme is loaded and is the latest required version
	 */
	$is_theme_dt = class_exists( "Disciple_Tools" );
	if ( $is_theme_dt && version_compare( $version, $disciple_tools_autolink_required_dt_theme_version, "<" ) ) {
		add_action( 'admin_notices', 'disciple_tools_autolink_hook_admin_notice' );
		add_action( 'wp_ajax_dismissed_notice_handler', 'dt_hook_ajax_notice_handler' );

		return false;
	}
	if ( ! $is_theme_dt ) {
		return false;
	}

	/**
	 * Load useful function from the theme
	 */
	if ( ! defined( 'DT_FUNCTIONS_READY' ) ) {
		require_once get_template_directory() . '/dt-core/global-functions.php';
	}

	return Disciple_Tools_Autolink::instance();
}

add_action( 'after_setup_theme', 'disciple_tools_autolink', 20 );

/**
 * Singleton class for setting up the plugin.
 *
 * @since  0.1
 * @access public
 */
class Disciple_Tools_Autolink {

	private static $_instance = null;

	private function __construct() {
		require_once __DIR__ . '/magic-link/actor.php';
		require_once __DIR__ . '/magic-link/contact-links.php';
		require_once __DIR__ . '/magic-link/functions.php';
		require_once __DIR__ . '/queries.php';
		require_once __DIR__ . '/controllers/controller.php';
		require_once __DIR__ . '/controllers/login.php';
		require_once __DIR__ . '/controllers/register.php';
		require_once __DIR__ . '/controllers/survey.php';
		require_once __DIR__ . '/controllers/app.php';
		require_once __DIR__ . '/controllers/genmap.php';
		require_once __DIR__ . '/controllers/group.php';
		require_once __DIR__ . '/controllers/tree.php';
		require_once __DIR__ . '/controllers/contacts.php';
		require_once __DIR__ . '/controllers/upgrade.php';
		require_once __DIR__ . '/controllers/field.php';
		require_once __DIR__ . '/controllers/admin.php';
		require_once __DIR__ . '/controllers/admin-general.php';
		require_once __DIR__ . '/controllers/training.php';
		require_once __DIR__ . '/controllers/language.php';
		require_once __DIR__ . '/charts/groups-tree.php';
		require_once __DIR__ . '/admin/settings.php';


		// Before the magic links below are built: the theme asks this filter from
		// inside their constructors.
		add_filter( 'dt_magic_link_continue', [ 'Disciple_Tools_Autolink_Contact_Links', 'magic_link_continue' ], 20, 2 );

		require_once __DIR__ . '/magic-link/app-routes.php';
		require_once __DIR__ . '/magic-link/contact-app.php';
		require_once __DIR__ . '/magic-link/user-app.php';
		// The contact app shares the user app's routing trait, so it loads after it.
		require_once __DIR__ . '/magic-link/contact-link-app.php';
		require_once __DIR__ . '/magic-link/login-app.php';

		if ( is_admin() ) {
			require_once 'admin/config-required-plugins.php'; // adds required plugins
			require_once 'admin/admin-menu-and-tabs.php'; // adds starter admin page and section for plugin
		}

		// Registered for every request, not just this app's own pages: the point is
		// that the activity log reads as a person on the record's Disciple.Tools
		// page too.
		add_filter( 'dt_format_post_activity', [ 'Disciple_Tools_Autolink_Actor', 'name_activity' ], 10, 2 );

		add_filter( 'desktop_navbar_menu_options', [ $this, 'menu_options' ], 999, 1 );
		add_filter( 'off_canvas_menu_options', [ $this, 'menu_options' ] );

		$this->i18n();
	}

	/**
	 * Loads the translation files.
	 *
	 * @return void
	 * @since  0.1
	 * @access public
	 */
	public function i18n() {
		$domain = 'disciple-tools-autolink';
		load_plugin_textdomain( $domain, false, trailingslashit( dirname( plugin_basename( __FILE__ ) ) ) . 'languages' );
	}

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Method that runs only when the plugin is activated.
	 *
	 * @return void
	 * @since  0.1
	 * @access public
	 */
	public static function activation() {
		// add elements here that need to fire on activation
	}

	/**
	 * Method that runs only when the plugin is deactivated.
	 *
	 * @return void
	 * @since  0.1
	 * @access public
	 */
	public static function deactivation() {
		// add functions here that need to happen on deactivation
		delete_option( 'dismissed-disciple-tools-autolink' );
	}

	/**
	 * Filters the array of row meta for each/specific plugin in the Plugins list table.
	 * Appends additional links below each/specific plugin on the plugins page.
	 */
	public function plugin_description_links( $links_array, $plugin_file_name, $plugin_data, $status ) {
		if ( strpos( $plugin_file_name, basename( __FILE__ ) ) ) {
			// You can still use `array_unshift()` to add links at the beginning.

			$links_array[] = '<a href="https://disciple.tools">Disciple.Tools Community</a>';
			$links_array[] = '<a href="https://www.eastwest.org">EastWest</a>';
			$links_array[] = '<a href="https://codezone.io">CodeZone</a>';
		}

		return $links_array;
	}

	/**
	 *  Add menu options to the navbar
	 */
	public function menu_options( $menu_options ) {

		$settings = new Disciple_Tools_Autolink_Settings();

		if ( $settings->get_option( 'disciple_tools_autolink_show_in_menu' ) ) {
			$menu_options[] = [
				'label' => __( 'Autolink', 'disciple-tools-autolink' ),
				'link'  => site_url( '/autolink' )
			];
		}

		return $menu_options;
	}

	/**
	 * Magic method to output a string if trying to use the object as a string.
	 *
	 * @return string
	 * @since  0.1
	 * @access public
	 */
	public function __toString() {
		return 'disciple-tools-autolink';
	}

	/**
	 * Magic method to keep the object from being cloned.
	 *
	 * @return void
	 * @since  0.1
	 * @access public
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, 'Whoah, partner!', '0.1' );
	}

	/**
	 * Magic method to keep the object from being unserialized.
	 *
	 * @return void
	 * @since  0.1
	 * @access public
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, 'Whoah, partner!', '0.1' );
	}

	/**
	 * Magic method to prevent a fatal error when calling a method that doesn't exist.
	 *
	 * @param string $method
	 * @param array $args
	 *
	 * @return null
	 * @since  0.1
	 * @access public
	 */
	public function __call( $method = '', $args = [] ) {
		_doing_it_wrong( "disciple_tools_autolink::" . esc_html( $method ), 'Method does not exist.', '0.1' );
		unset( $method, $args );

		return null;
	}
}


// Register activation hook.
register_activation_hook( __FILE__, [ 'Disciple_Tools_Autolink', 'activation' ] );
register_deactivation_hook( __FILE__, [ 'Disciple_Tools_Autolink', 'deactivation' ] );


if ( ! function_exists( 'disciple_tools_autolink_hook_admin_notice' ) ) {
	function disciple_tools_autolink_hook_admin_notice() {
		global $disciple_tools_autolink_required_dt_theme_version;
		$wp_theme        = wp_get_theme();
		$current_version = $wp_theme->version;
		$message         = "'Disciple.Tools - Autolink' plugin requires 'Disciple.Tools' theme to work. Please activate 'Disciple.Tools' theme or make sure it is latest version.";
		if ( $wp_theme->get_template() === "disciple-tools-theme" ) {
			$message .= ' ' . sprintf( esc_html( 'Current Disciple.Tools version: %1$s, required version: %2$s' ), esc_html( $current_version ), esc_html( $disciple_tools_autolink_required_dt_theme_version ) );
		}
		// Check if it's been dismissed...
		if ( ! get_option( 'dismissed-disciple-tools-autolink', false ) ) { ?>
            <div class="notice notice-error notice-disciple-tools-autolink is-dismissible"
                 data-notice="disciple-tools-autolink">
                <p><?php echo esc_html( $message ); ?></p>
            </div>
            <script>
                jQuery(function ($) {
                    $(document).on('click', '.notice-disciple-tools-autolink .notice-dismiss', function () {
                        $.ajax(ajaxurl, {
                            type: 'POST',
                            data: {
                                action: 'dismissed_notice_handler',
                                type: 'disciple-tools-autolink',
                                security: '<?php echo esc_html( wp_create_nonce( 'wp_rest_dismiss' ) ) ?>'
                            }
                        })
                    });
                });
            </script>
		<?php }
	}
}

/**
 * AJAX handler to store the state of dismissible notices.
 */
if ( ! function_exists( "dt_hook_ajax_notice_handler" ) ) {
	function dt_hook_ajax_notice_handler() {
		check_ajax_referer( 'wp_rest_dismiss', 'security' );
		if ( isset( $_POST["type"] ) ) {
			$type = sanitize_text_field( wp_unslash( $_POST["type"] ) );
			update_option( 'dismissed-' . $type, true );
		}
	}
}


add_action( 'plugins_loaded', function () {
	if ( ( is_admin() && ! ( is_multisite() && class_exists( "DT_Multisite" ) ) ) || wp_doing_cron() ) {
		// Check for plugin updates
		if ( ! class_exists( 'Puc_v4_Factory' ) ) {
			if ( file_exists( get_template_directory() . '/dt-core/libraries/plugin-update-checker/plugin-update-checker.php' ) ) {
				require get_template_directory() . '/dt-core/libraries/plugin-update-checker/plugin-update-checker.php';
			}
		}
		if ( class_exists( 'Puc_v4_Factory' ) ) {
			Puc_v4_Factory::buildUpdateChecker(
				'https://raw.githubusercontent.com/DiscipleTools/disciple-tools-autolink/master/version-control.json',
				__FILE__,
				'disciple-tools-autolink'
			);
		}
	}
} );
