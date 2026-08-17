<?php
/**
 * EG Care Uninstall Template
 *
 * Drops custom tables and removes the custom doctor role on plugin deletion.
 *
 * @package EG Care
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Require files manually since the plugin is not loaded during uninstall.
require_once dirname( __FILE__ ) . '/includes/class-db.php';
require_once dirname( __FILE__ ) . '/includes/class-roles.php';

// Drop all custom tables.
\EGCare\DB::drop_tables();

// Remove custom doctor role.
\EGCare\Roles::remove_roles();

// Flush rewrite rules.
flush_rewrite_rules();
