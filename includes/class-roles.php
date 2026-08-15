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
	 * Initialize hook listeners.
	 */
	public static function init() {
		add_filter( 'wp_authenticate_user', array( __CLASS__, 'restrict_unapproved_doctors_login' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'ensure_doctor_capabilities' ) );
	}

	/**
	 * Ensure doctor roles have upload_files capability dynamically.
	 */
	public static function ensure_doctor_capabilities() {
		$role = get_role( self::DOCTOR_ROLE );
		if ( $role && ! $role->has_cap( 'upload_files' ) ) {
			$role->add_cap( 'upload_files' );
		}
	}

	/**
	 * Register the custom doctor role and capabilities.
	 */
	public static function add_roles() {
		add_role(
			self::DOCTOR_ROLE,
			__( 'Meditaj Doctor', 'meditaj' ),
			array(
				'read'                            => true,
				'upload_files'                    => true,
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

	/**
	 * Prevent pending and rejected doctors from accessing their accounts.
	 *
	 * @param \WP_User|\WP_Error $user     User object or WP_Error.
	 * @param string             $password User password.
	 * @return \WP_User|\WP_Error WP_User or WP_Error blocker.
	 */
	public static function restrict_unapproved_doctors_login( $user, $password ) {
		if ( is_wp_error( $user ) ) {
			return $user;
		}

		if ( in_array( self::DOCTOR_ROLE, $user->roles, true ) ) {
			global $wpdb;
			$table_meta = \Meditaj\DB::get_table( 'doctors_meta' );

			$status = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT verification_status FROM $table_meta WHERE user_id = %d",
					$user->ID
				)
			);

			if ( 'approved' !== $status ) {
				if ( 'rejected' === $status ) {
					return new \WP_Error(
						'meditaj_doctor_rejected',
						__( '<strong>ERROR</strong>: Your doctor account has been rejected. Please contact support.', 'meditaj' )
					);
				}
				// Default to pending if record missing or pending.
				return new \WP_Error(
					'meditaj_doctor_pending',
					__( '<strong>ERROR</strong>: Your doctor account is pending verification. You will be notified via email once approved.', 'meditaj' )
				);
			}
		}

		return $user;
	}
}
