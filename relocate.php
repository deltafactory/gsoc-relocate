<?php
/**
 * Relocate plugin.
 * 
 * A simple interface for changing WordPress site URLs while moving the site, 
 * and updating URL references within posts and attachments.
 * 
 * Plugin Name:	WP Relocate
 * Plugin URI:	http://gsoc.frederickding.com/
 * Description:	A simple interface for changing WordPress site URL options and updating URL references, such as would be needed before/after moving a WordPress installation.
 * Version:		1.0.0
 * Author:		Frederick Ding
 * Author URI:	http://www.frederickding.com/
 * 	
 * @package		WP Relocate
 */
if ( ! defined( 'WPINC' ) )
	die();

require_once (plugin_dir_path( __FILE__ ) . 'class-wp-relocate.php');

/**
 * Plugin functionality.
 *
 * Hooks relocate features into WordPress.
 *
 * @package WP Relocate
 * @author Frederick Ding <frederick@frederickding.com>
 * @license http://wordpress.org/about/license/ GPLv2 or later
 * @version 1.0.0
 */
class WP_Relocate_Plugin {

	/**
	 * Plugin version
	 *
	 * @var string
	 */
	protected $version = '1.0.0';

	/**
	 * A singleton instance of this class.
	 *
	 * @var WP_Relocate_Plugin
	 */
	protected static $instance = null;

	protected $menu_hook = '';

	/**
	 * Initializes plugin hooks, actions, filters.
	 *
	 * Use WP_Relocate_Plugin::get_instance to get a singleton instance of this
	 * class.
	 *
	 * @since 1.0.0
	 */
	private function __construct () {
		// add menu links in wp-admin
		add_action( 'admin_menu', array( 
				$this,
				'add_admin_menu' 
		) );
	}

	/**
	 * Singleton pattern.
	 *
	 * @since 1.0.0
	 */
	public static function get_instance () {
		if ( null == self::$instance )
			self::$instance = new self();
		
		return self::$instance;
	}

	/**
	 * Registers admin menus under Tools.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu () {
		$this->menu_hook = add_submenu_page( 'tools.php', 'WP Relocate', 
				'Relocate', 'manage_options', 'wp-relocate', '__return_false' );
		// we hooked onto tools.php?page=wp-relocate just so we can redirect
		// to the outside independent script
		add_action( 'load-' . $this->menu_hook, 
				array( 
						$this,
						'admin_page_redirect' 
				) );
	}

	/**
	 * Redirects admin user to the interface.php plugin script.
	 *
	 * @since 1.0.0
	 */
	public function admin_page_redirect () {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 
					__( 
							'You do not have sufficient permissions to access this page.' ) );
		}
		// designed like this so that any sort of core integration of this
		// functionality would require minimal adaptation
		if ( $_GET['page'] == 'wp-relocate' ) {
			wp_safe_redirect( plugins_url( 'interface.php', __FILE__ ) );
		}
		exit();
	}
}

// initialize
WP_Relocate_Plugin::get_instance();
