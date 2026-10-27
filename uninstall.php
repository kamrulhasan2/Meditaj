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
require_once dirname( __FILE__ ) . '/includes/class-prescriptions.php';
require_once dirname( __FILE__ ) . '/includes/class-prescription-view.php';
require_once dirname( __FILE__ ) . '/includes/class-sms.php';

// Drop all custom tables.
\EGCare\DB::drop_tables();

// Remove custom doctor role.
\EGCare\Roles::remove_roles();

// Settings this plugin created. drop_tables() takes the tables themselves,
// including the prescriptions one, because it walks DB::get_tables().
$eg_care_options = array(
	\EGCare\Prescriptions::MEDICINE_OPTION,
	\EGCare\Sms::ENABLED_OPTION,
	\EGCare\Sms::ENDPOINT_OPTION,
	\EGCare\Sms::BODY_OPTION,
);

foreach ( $eg_care_options as $eg_care_option ) {
	delete_option( $eg_care_option );
}

// Every doctor's saved prescriptions, which live on their user record and
// would otherwise outlive the plugin.
delete_metadata( 'user', 0, \EGCare\Prescriptions::TEMPLATES_META, '', true );
delete_metadata( 'user', 0, \EGCare\PrescriptionView::SIGNATURE_META, '', true );

// Flush rewrite rules.
flush_rewrite_rules();
