<?php
namespace Meditaj;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles custom roles and capabilities.
 */
class Roles {
	/**
	 * Custom doctor role name.
	 */
	const DOCTOR_ROLE = 'meditaj_doctor';

	/**
	 * Register the custom doctor role and capabilities.
	 */
	public static function add_roles() {
		add_role(
			self::DOCTOR_ROLE,
			__( 'Meditaj Doctor', 'meditaj' ),
			array(
				'read'                            => true,
				'meditaj_manage_own_appointments' => true,
				'meditaj_manage_own_slots'        => true,
			)
		);
	}

	/**
	 * Remove the custom doctor role.
	 */
	public static function remove_roles() {
		remove_role( self::DOCTOR_ROLE );
	}
}
