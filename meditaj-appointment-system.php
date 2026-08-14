<?php
/**
 * Plugin Name: Meditaj - Doctor Calling & Appointment System
 * Plugin URI:  https://example.com/meditaj
 * Description: Telemedicine and doctor appointment booking platform with Agora video integration and local payment options.
 * Version:     1.0.0
 * Author:      Antigravity
 * Text Domain: meditaj
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package Meditaj
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define constants.
define( 'MEDITAJ_VERSION', '1.0.0' );
define( 'MEDITAJ_PATH', plugin_dir_path( __FILE__ ) );
define( 'MEDITAJ_URL', plugin_dir_url( __FILE__ ) );

/**
 * Register Autoloader for Meditaj Namespaced Classes.
 * Maps e.g. \Meditaj\DB to includes/class-db.php.
 */
spl_autoload_register(
	function ( $class ) {
		$prefix   = 'Meditaj\\';
		$base_dir = MEDITAJ_PATH . 'includes/';

		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );

		// Convert PascalCase / camelCase to wordpress lowercase hyphenated filename.
		// e.g. RestControllerDoctors -> class-rest-controller-doctors.php
		$filename = 'class-' . strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $relative_class ) ) . '.php';

		$file = $base_dir . $filename;

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

// Register Activation and Deactivation Hooks.
register_activation_hook( __FILE__, 'meditaj_activate_plugin' );
register_deactivation_hook( __FILE__, 'meditaj_deactivate_plugin' );

/**
 * Plugin activation operations.
 */
function meditaj_activate_plugin() {
	// Create Database Tables.
	\Meditaj\DB::create_tables();

	// Setup Roles and Capabilities.
	\Meditaj\Roles::add_roles();

	// Register CPT and taxonomy definitions so rewrite rules flush properly.
	\Meditaj\CPT::register_post_types();
	\Meditaj\CPT::register_taxonomies();

	// Seed dummy doctors for testing.
	\Meditaj\DB::seed_dummy_doctors();

	flush_rewrite_rules();
}

/**
 * Plugin deactivation operations.
 */
function meditaj_deactivate_plugin() {
	// Tables and roles are kept intact to preserve data during update/deactivation.
	flush_rewrite_rules();
}

// Enqueue Admin Stylesheet.
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		// Only enqueue on Meditaj admin pages or doctor post edit screens.
		if ( false !== strpos( $hook, 'meditaj' ) || ( ( 'post.php' === $hook || 'post-new.php' === $hook ) && 'doctors' === get_post_type() ) ) {
			wp_enqueue_style( 'meditaj-admin-style', MEDITAJ_URL . 'assets/css/admin.css', array(), MEDITAJ_VERSION );
		}
	}
);

// Enqueue Frontend Stylesheet.
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'meditaj-style', MEDITAJ_URL . 'assets/css/style.css', array(), MEDITAJ_VERSION );
	}
);

// Auto-upgrade database schema if DB_VERSION changes.
add_action(
	'plugins_loaded',
	function () {
		if ( get_option( \Meditaj\DB::DB_VERSION_OPTION ) !== \Meditaj\DB::DB_VERSION ) {
			\Meditaj\DB::create_tables();
		}
	}
);

// Bootstrap Components.
\Meditaj\CPT::init();
\Meditaj\Roles::init();
\Meditaj\Shortcodes::init();
\Meditaj\AdminMenu::init();
\Meditaj\AdminDoctors::init();
\Meditaj\RestApi::init();


