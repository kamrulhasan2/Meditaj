<?php
/**
 * REST API Controller for Bookings.
 *
 * @package Meditaj
 */

namespace Meditaj;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class RestControllerBooking
 */
class RestControllerBooking extends WP_REST_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$namespace = 'meditaj/v1';

		register_rest_route(
			$namespace,
			'/appointments',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_appointment' ),
					'permission_callback' => array( $this, 'check_logged_in_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/appointments/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_appointment' ),
					'permission_callback' => array( $this, 'check_logged_in_permission' ),
				),
			)
		);
	}

	/**
	 * Ensure the requesting user is logged in.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_logged_in_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'You must be logged in to access this endpoint.', 'meditaj' ), array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * Create a new appointment booking.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_appointment( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();

		// Parse body parameters.
		$doctor_id    = intval( $request->get_param( 'doctor_id' ) );
		$booking_type = sanitize_text_field( $request->get_param( 'booking_type' ) ); // 'instant' or 'scheduled'
		$date         = sanitize_text_field( $request->get_param( 'date' ) );         // YYYY-MM-DD
		$time         = sanitize_text_field( $request->get_param( 'time' ) );         // HH:MM:SS
		$relation     = sanitize_text_field( $request->get_param( 'patient_relation' ) ); // 'self', 'father', etc.
		$pat_name     = sanitize_text_field( $request->get_param( 'patient_name' ) );
		$pat_age      = $request->get_param( 'patient_age' );
		$notes        = wp_kses_post( $request->get_param( 'notes' ) );
		$files        = $request->get_param( 'files' );

		// 1. Validation.
		if ( empty( $doctor_id ) || empty( $booking_type ) || empty( $date ) || empty( $time ) || empty( $relation ) ) {
			return new WP_Error( 'rest_bad_request', __( 'Required fields are missing.', 'meditaj' ), array( 'status' => 400 ) );
		}

		if ( ! in_array( $booking_type, array( 'instant', 'scheduled' ), true ) ) {
			return new WP_Error( 'rest_bad_request', __( 'Invalid booking type.', 'meditaj' ), array( 'status' => 400 ) );
		}

		// Verify target doctor is active and approved.
		$table_meta = \Meditaj\DB::get_table( 'doctors_meta' );
		$doctor     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT m.*, p.post_title FROM $table_meta m 
				JOIN {$wpdb->posts} p ON m.post_id = p.ID 
				WHERE p.ID = %d AND p.post_type = 'doctors' AND m.verification_status = 'approved'",
				$doctor_id
			)
		);

		if ( ! $doctor ) {
			return new WP_Error( 'rest_not_found', __( 'Selected doctor profile not found or not approved.', 'meditaj' ), array( 'status' => 404 ) );
		}

		// 2. Wrap block in transaction for race condition protection.
		$wpdb->query( 'START TRANSACTION' );

		$table_appointments = \Meditaj\DB::get_table( 'appointments' );

		// Check lock for the exact slot.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table_appointments 
				WHERE doctor_id = %d AND appointment_date = %s AND appointment_time = %s 
					AND status IN ('confirmed', 'ongoing', 'completed', 'pending_payment') 
				FOR UPDATE",
				$doctor_id,
				$date,
				$time
			)
		);

		if ( $exists ) {
			// Rollback and exit on race condition conflict.
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'meditaj_slot_conflict', __( 'This consultation slot has already been booked or is pending payment.', 'meditaj' ), array( 'status' => 409 ) );
		}

		// Calculate Fees.
		$fee = 'instant' === $booking_type ? floatval( $doctor->instant_call_fee ) : floatval( $doctor->consultation_fee );
		$tax = $fee * 0.05;
		$total_amount = $fee + $tax;

		// Insert new appointment.
		$now      = current_time( 'mysql' );
		$inserted = $wpdb->insert(
			$table_appointments,
			array(
				'doctor_id'              => $doctor_id,
				'patient_user_id'        => $user_id,
				'family_member_name'     => 'self' === $relation ? wp_get_current_user()->display_name : $pat_name,
				'family_member_age'      => 'self' === $relation ? null : intval( $pat_age ),
				'family_member_relation' => $relation,
				'appointment_type'       => $booking_type,
				'appointment_date'       => $date,
				'appointment_time'       => $time,
				'status'                 => 'pending_payment',
				'payment_status'         => 'unpaid',
				'amount'                 => $total_amount,
				'uploaded_files'         => wp_json_encode( ! empty( $files ) ? $files : array() ),
				'symptom_notes'          => $notes,
				'created_at'             => $now,
				'updated_at'             => $now,
			),
			array(
				'%d',
				'%d',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%f',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( false === $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'meditaj_db_error', __( 'Failed to record appointment details in DB.', 'meditaj' ), array( 'status' => 500 ) );
		}

		$appointment_id = $wpdb->insert_id;

		// Commit transaction.
		$wpdb->query( 'COMMIT' );

		return new WP_REST_Response(
			array(
				'appointment_id' => $appointment_id,
				'amount'         => $total_amount,
			),
			201
		);
	}

	/**
	 * Get an appointment details.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_appointment( $request ) {
		global $wpdb;
		$id                 = intval( $request->get_param( 'id' ) );
		$table_appointments = \Meditaj\DB::get_table( 'appointments' );

		$appointment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_appointments WHERE id = %d",
				$id
			)
		);

		if ( ! $appointment ) {
			return new WP_Error( 'rest_not_found', __( 'Appointment request not found.', 'meditaj' ), array( 'status' => 404 ) );
		}

		// Enforce ownership / authorization checks: Patient owner or admin only.
		$current_user_id = get_current_user_id();
		if ( intval( $appointment->patient_user_id ) !== $current_user_id && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view this booking.', 'meditaj' ), array( 'status' => 403 ) );
		}

		return new WP_REST_Response( $appointment, 200 );
	}
}
