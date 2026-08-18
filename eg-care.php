<?php
/**
 * Plugin Name: EG Care - Doctor Calling & Appointment System
 * Plugin URI:  https://github.com/kamrulhasan2/EG Care
 * Description: Telemedicine and doctor appointment booking platform with Agora video integration and local payment options.
 * Version:     1.0.1
 * Author:      Kamrul Hasan
 * Text Domain: eg-care
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package EGCare
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define constants.
define( 'EG_CARE_VERSION', '1.0.0' );
define( 'EG_CARE_PATH', plugin_dir_path( __FILE__ ) );
define( 'EG_CARE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Register Autoloader for EG Care Namespaced Classes.
 * Maps e.g. \EGCare\DB to includes/class-db.php.
 */
spl_autoload_register(
	function ( $class ) {
		$prefix   = 'EGCare\\';
		$base_dir = EG_CARE_PATH . 'includes/';

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

// Run dynamic database migrations on load if needed.
add_action( 'plugins_loaded', function() {
	global $wpdb;
	$table_schedules = \EGCare\DB::get_table( 'schedules' );

	if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_schedules ) ) === $table_schedules ) {
		$has_break_col = $wpdb->get_results( "SHOW COLUMNS FROM `$table_schedules` LIKE 'break_duration_min'" );
		if ( empty( $has_break_col ) ) {
			$wpdb->query( "ALTER TABLE `$table_schedules` ADD COLUMN `break_duration_min` int(11) DEFAULT 0 NOT NULL AFTER `slot_duration_min`" );
		}
	}
} );

// Register Activation and Deactivation Hooks.
register_activation_hook( __FILE__, 'eg_care_activate_plugin' );
register_deactivation_hook( __FILE__, 'eg_care_deactivate_plugin' );

/**
 * Plugin activation operations.
 */
function eg_care_activate_plugin() {
	// Create Database Tables.
	\EGCare\DB::create_tables();

	// Setup Roles and Capabilities.
	\EGCare\Roles::add_roles();

	// Register CPT and taxonomy definitions so rewrite rules flush properly.
	\EGCare\CPT::register_post_types();
	\EGCare\CPT::register_taxonomies();

	flush_rewrite_rules();
}

/**
 * Plugin deactivation operations.
 */
function eg_care_deactivate_plugin() {
	// Tables and roles are kept intact to preserve data during update/deactivation.
	flush_rewrite_rules();
}

// Enqueue Admin Stylesheet.
add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		// Only enqueue on EG Care admin pages or doctor post edit screens.
		if ( false !== strpos( $hook, 'eg-care' ) || ( ( 'post.php' === $hook || 'post-new.php' === $hook ) && 'doctors' === get_post_type() ) ) {
			wp_enqueue_style( 'eg-care-admin-style', EG_CARE_URL . 'assets/css/admin.css', array(), EG_CARE_VERSION );
		}
	}
);

// Enqueue Frontend Stylesheet.
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'eg-care-style', EG_CARE_URL . 'assets/css/style.css', array(), time() );
	}
);

// Auto-upgrade database schema if DB_VERSION changes.
add_action(
	'plugins_loaded',
	function () {
		if ( get_option( \EGCare\DB::DB_VERSION_OPTION ) !== \EGCare\DB::DB_VERSION ) {
			\EGCare\DB::create_tables();
		}
	}
);

// Bootstrap Components.
\EGCare\CPT::init();
\EGCare\Roles::init();
\EGCare\Shortcodes::init();
\EGCare\Cache::init();
\EGCare\AdminMenu::init();
\EGCare\AdminDoctors::init();
\EGCare\RestApi::init();
\EGCare\Cron::init();

// Register cleanup on deactivation.
register_deactivation_hook( __FILE__, array( '\EGCare\Cron', 'clear_schedule' ) );
