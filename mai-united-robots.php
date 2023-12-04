<?php

/**
 * Plugin Name:     Mai United Robots
 * Plugin URI:      https://maitowne.com
 * Description:     A custom endpoint to receive data from United Robots.
 * Version:         0.1.0
 *
 * Author:          BizBudding
 * Author URI:      https://bizbudding.com
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Main Mai_United_Robots_Plugin Class.
 *
 * @since 0.1.0
 */
final class Mai_United_Robots_Plugin {
	/**
	 * @var   Mai_United_Robots_Plugin The one true Mai_Lists
	 * @since 0.1.0
	 */
	private static $instance;

	/**
	 * Main Mai_United_Robots_Plugin Instance.
	 *
	 * Insures that only one instance of Mai_United_Robots_Plugin exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since   0.1.0
	 * @static  var array $instance
	 * @uses    Mai_United_Robots_Plugin::setup_constants() Setup the constants needed.
	 * @uses    Mai_United_Robots_Plugin::includes() Include the required files.
	 * @uses    Mai_United_Robots_Plugin::hooks() Activate, deactivate, etc.
	 * @see     Mai_United_Robots_Plugin()
	 * @return  object | Mai_United_Robots_Plugin The one true Mai_United_Robots_Plugin
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			// Setup the setup.
			self::$instance = new Mai_United_Robots_Plugin;
			// Methods.
			self::$instance->setup_constants();
			self::$instance->includes();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	/**
	 * Throw error on object clone.
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @since   0.1.0
	 * @access  protected
	 * @return  void
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'mai-united-robots' ), '1.0' );
	}

	/**
	 * Disable unserializing of the class.
	 *
	 * @since   0.1.0
	 * @access  protected
	 * @return  void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'mai-united-robots' ), '1.0' );
	}

	/**
	 * Setup plugin constants.
	 *
	 * @access  private
	 * @since   0.1.0
	 * @return  void
	 */
	private function setup_constants() {
		// Plugin version.
		if ( ! defined( 'MAI_UNITED_ROBOTS_VERSION' ) ) {
			define( 'MAI_UNITED_ROBOTS_VERSION', '0.1.0' );
		}

		// Plugin Folder Path.
		if ( ! defined( 'MAI_UNITED_ROBOTS_PLUGIN_DIR' ) ) {
			define( 'MAI_UNITED_ROBOTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}
	}

	/**
	 * Include required files.
	 *
	 * @access  private
	 * @since   0.1.0
	 * @return  void
	 */
	private function includes() {
		// Include vendor libraries.
		require_once __DIR__ . '/vendor/autoload.php';

		// Includes.
		foreach ( glob( MAI_UNITED_ROBOTS_PLUGIN_DIR . 'includes/*.php' ) as $file ) { include $file; }

		// Register the autoloader.
		spl_autoload_register( [ $this, 'autoload' ] );
	}

	/**
	 * Autoload classes.
	 *
	 * @since 0.1.0
	 *
	 * @param string $class The class name.
	 *
	 * @return void
	 */
	private function autoload( $class ) {
		// If string doesn't start with 'Mai_United_Robots_'.
		if ( 0 !== strpos( $class, 'Mai_United_Robots_' ) ) {
			return;
		}

		$name = str_replace( 'Mai_United_Robots_', '', $class );
		$name = strtolower( str_replace( '_', '-', $name ) );
		$file = MAI_UNITED_ROBOTS_PLUGIN_DIR . 'classes/class-' . $name . '.php';

		if ( file_exists( $file ) ) {
			include $file;
		}
	}

	/**
	 * Run the hooks.
	 *
	 * @since 0.1.0
	 * @return void
	 */
	public function hooks() {
		// Load classes.
		$endpoints = new Mai_United_Robots_Endpoints;
	}

}

/**
 * The main function for that returns Mai_United_Robots_Plugin
 *
 * The main function responsible for returning the one true Mai_United_Robots_Plugin
 * Instance to functions everywhere.
 *
 * @since 0.1.0
 *
 * @return object|Mai_United_Robots_Plugin The one true Mai_United_Robots_Plugin Instance.
 */
function mai_united_robots_plugin() {
	return Mai_United_Robots_Plugin::instance();
}

// Get Mai_United_Robots_Plugin Running.
mai_united_robots_plugin();
