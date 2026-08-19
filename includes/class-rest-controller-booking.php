<?php
/**
 * REST API Controller for Bookings.
 *
 * @package EG Care
 */

namespace EGCare;

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
	 * How long an unanswered ring keeps sounding, in seconds.
	 */
	const RING_TTL = 90;

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$namespace = 'eg-care/v1';

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

		register_rest_route(
			$namespace,
			'/video/token',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'generate_video_token' ),
					'permission_callback' => array( $this, 'check_logged_in_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/calls/incoming',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_incoming_call' ),
					'permission_callback' => array( $this, 'check_logged_in_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/appointments/(?P<id>\d+)/complete',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'complete_appointment' ),
					'permission_callback' => array( $this, 'check_logged_in_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/appointments/(?P<id>\d+)/reviews',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_review' ),
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
			return new WP_Error( 'rest_forbidden', __( 'You must be logged in to access this endpoint.', 'eg-care' ), array( 'status' => 401 ) );
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
			return new WP_Error( 'rest_bad_request', __( 'Required fields are missing.', 'eg-care' ), array( 'status' => 400 ) );
		}

		if ( ! in_array( $booking_type, array( 'instant', 'scheduled' ), true ) ) {
			return new WP_Error( 'rest_bad_request', __( 'Invalid booking type.', 'eg-care' ), array( 'status' => 400 ) );
		}

		// Verify target doctor is active and approved.
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );
		$doctor     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT m.*, p.post_title FROM $table_meta m 
				JOIN {$wpdb->posts} p ON m.post_id = p.ID 
				WHERE p.ID = %d AND p.post_type = 'doctors' AND m.verification_status = 'approved'",
				$doctor_id
			)
		);

		if ( ! $doctor ) {
			return new WP_Error( 'rest_not_found', __( 'Selected doctor profile not found or not approved.', 'eg-care' ), array( 'status' => 404 ) );
		}

		// 2. Wrap block in transaction for race condition protection.
		$wpdb->query( 'START TRANSACTION' );

		$table_appointments = \EGCare\DB::get_table( 'appointments' );

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
			return new WP_Error( 'eg_care_slot_conflict', __( 'This consultation slot has already been booked or is pending payment.', 'eg-care' ), array( 'status' => 409 ) );
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
			return new WP_Error( 'eg_care_db_error', __( 'Failed to record appointment details in DB.', 'eg-care' ), array( 'status' => 500 ) );
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
		$table_appointments = \EGCare\DB::get_table( 'appointments' );

		$appointment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_appointments WHERE id = %d",
				$id
			)
		);

		if ( ! $appointment ) {
			return new WP_Error( 'rest_not_found', __( 'Appointment request not found.', 'eg-care' ), array( 'status' => 404 ) );
		}

		// Enforce ownership / authorization checks: Patient owner or admin only.
		$current_user_id = get_current_user_id();
		if ( intval( $appointment->patient_user_id ) !== $current_user_id && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to view this booking.', 'eg-care' ), array( 'status' => 403 ) );
		}

		return new WP_REST_Response( $appointment, 200 );
	}

	/**
	 * Generate an Agora RTC token for a confirmed appointment call window.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function generate_video_token( $request ) {
		global $wpdb;
		$appointment_id = intval( $request->get_param( 'appointment_id' ) );
		if ( ! $appointment_id ) {
			return new WP_Error( 'rest_bad_request', __( 'Missing appointment_id parameter.', 'eg-care' ), array( 'status' => 400 ) );
		}

		$table_appointments = \EGCare\DB::get_table( 'appointments' );
		$table_meta         = \EGCare\DB::get_table( 'doctors_meta' );

		// Retrieve appointment details and doctor user ID.
		$appointment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.*, d.user_id as doctor_user_id 
				 FROM $table_appointments a 
				 JOIN $table_meta d ON a.doctor_id = d.post_id 
				 WHERE a.id = %d",
				$appointment_id
			)
		);

		if ( ! $appointment ) {
			return new WP_Error( 'rest_not_found', __( 'Appointment not found.', 'eg-care' ), array( 'status' => 404 ) );
		}

		// Security Check: requester must be the doctor_user_id or patient_user_id.
		$current_user_id = get_current_user_id();
		$is_doctor       = ( intval( $current_user_id ) === intval( $appointment->doctor_user_id ) );
		$is_patient      = ( intval( $current_user_id ) === intval( $appointment->patient_user_id ) );

		if ( ! $is_doctor && ! $is_patient ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to access this call.', 'eg-care' ), array( 'status' => 403 ) );
		}

		// Validate status is 'confirmed' or 'ongoing'.
		if ( 'confirmed' !== $appointment->status && 'ongoing' !== $appointment->status ) {
			return new WP_Error( 'rest_forbidden', __( 'Video calling is only allowed for confirmed appointments.', 'eg-care' ), array( 'status' => 403 ) );
		}

		// Validate time window (except for instant call which starts immediately).
		if ( 'scheduled' === $appointment->appointment_type ) {
			$appointment_timestamp = strtotime( $appointment->appointment_date . ' ' . $appointment->appointment_time );
			$now_timestamp         = current_time( 'timestamp' );

			// Call allowed 15 mins before to 60 mins after scheduled start time.
			$window_start = $appointment_timestamp - ( 15 * 60 );
			$window_end   = $appointment_timestamp + ( 60 * 60 );

			if ( $now_timestamp < $window_start ) {
				return new WP_Error( 'rest_forbidden', __( 'The call window has not opened yet. You can join 15 minutes before the start time.', 'eg-care' ), array( 'status' => 403 ) );
			}

			if ( $now_timestamp > $window_end ) {
				return new WP_Error( 'rest_forbidden', __( 'The call window has closed.', 'eg-care' ), array( 'status' => 403 ) );
			}
		}

		// Ensure video_room_id is generated.
		$video_room_id = $appointment->video_room_id;
		if ( empty( $video_room_id ) ) {
			$video_room_id = 'room_' . $appointment->id . '_' . md5( $appointment->id . AUTH_KEY );
			$wpdb->update(
				$table_appointments,
				array( 'video_room_id' => $video_room_id ),
				array( 'id' => $appointment->id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		// Generate Agora Token.
		$token = AgoraToken::generate_token( $video_room_id, 0, 1, 3600 );

		if ( ! $token ) {
			return new WP_Error( 'rest_internal_error', __( 'Failed to generate video credentials. Ensure App Credentials are configured in settings.', 'eg-care' ), array( 'status' => 500 ) );
		}

		// Someone has stepped into the room, so let the other party's dashboard ring.
		$this->start_ringing( $appointment, $current_user_id );

		return new WP_REST_Response(
			array(
				'token'        => $token,
				'app_id'       => get_option( 'eg_care_agora_app_id', '' ),
				'channel_name' => $video_room_id,
				'uid'          => 0,
				'is_doctor'    => $is_doctor,
				'doctor_name'  => get_the_title( $appointment->doctor_id ),
				'patient_name' => $appointment->family_member_name ? $appointment->family_member_name : wp_get_current_user()->display_name,
			),
			200
		);
	}

	/**
	 * Mark an appointment status as completed.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function complete_appointment( $request ) {
		global $wpdb;
		$id = intval( $request->get_param( 'id' ) );

		$table_appointments = \EGCare\DB::get_table( 'appointments' );
		$table_meta         = \EGCare\DB::get_table( 'doctors_meta' );

		// Retrieve appointment details and doctor user ID.
		$appointment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.*, d.user_id as doctor_user_id 
				 FROM $table_appointments a 
				 JOIN $table_meta d ON a.doctor_id = d.post_id 
				 WHERE a.id = %d",
				$id
			)
		);

		if ( ! $appointment ) {
			return new WP_Error( 'rest_not_found', __( 'Appointment not found.', 'eg-care' ), array( 'status' => 404 ) );
		}

		// Security Check: requester must be the doctor_user_id or patient_user_id.
		$current_user_id = get_current_user_id();
		$is_doctor       = ( intval( $current_user_id ) === intval( $appointment->doctor_user_id ) );
		$is_patient      = ( intval( $current_user_id ) === intval( $appointment->patient_user_id ) );

		if ( ! $is_doctor && ! $is_patient ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have permission to modify this appointment.', 'eg-care' ), array( 'status' => 403 ) );
		}

		// State Validation: appointment must be paid and currently confirmed/ongoing.
		if ( 'paid' !== $appointment->payment_status ) {
			return new WP_Error( 'rest_bad_request', __( 'Cannot complete an unpaid appointment.', 'eg-care' ), array( 'status' => 400 ) );
		}

		if ( ! in_array( $appointment->status, array( 'confirmed', 'ongoing' ), true ) ) {
			return new WP_Error( 'rest_bad_request', __( 'Only confirmed or ongoing appointments can be marked as completed.', 'eg-care' ), array( 'status' => 400 ) );
		}

		// Update appointment status to 'completed'.
		$wpdb->update(
			$table_appointments,
			array( 'status' => 'completed' ),
			array( 'id' => $appointment->id ),
			array( '%s' ),
			array( '%d' )
		);

		// Nothing left to ring about.
		$this->stop_ringing( $appointment );

		return new WP_REST_Response( array( 'status' => 'success', 'appointment_status' => 'completed' ), 200 );
	}

	/**
	 * Transient key holding the call waiting for a given user.
	 *
	 * @param int $user_id User ID.
	 * @return string Transient key.
	 */
	private function ring_key( $user_id ) {
		return 'eg_care_incoming_call_' . (int) $user_id;
	}

	/**
	 * Flag an incoming call for whichever party is not already in the room.
	 *
	 * Deliberately a short-lived transient rather than a table: a ring is only
	 * meaningful for a minute or two, and keying it by recipient means the poll
	 * below is a single cache read when nobody is calling.
	 *
	 * @param object $appointment Appointment row joined with the doctor's user_id.
	 * @param int    $caller_id   User who just asked for a token.
	 */
	private function start_ringing( $appointment, $caller_id ) {
		$caller_id       = (int) $caller_id;
		$doctor_user_id  = (int) $appointment->doctor_user_id;
		$patient_user_id = (int) $appointment->patient_user_id;

		// The caller is in the room now, so nothing is waiting for them.
		delete_transient( $this->ring_key( $caller_id ) );

		$callee_id = ( $caller_id === $doctor_user_id ) ? $patient_user_id : $doctor_user_id;

		if ( ! $callee_id || $callee_id === $caller_id ) {
			return;
		}

		set_transient(
			$this->ring_key( $callee_id ),
			array(
				'appointment_id' => (int) $appointment->id,
				'from'           => $caller_id,
				'at'             => time(),
			),
			self::RING_TTL
		);
	}

	/**
	 * Clear any ring left over for either party.
	 *
	 * @param object $appointment Appointment row joined with the doctor's user_id.
	 */
	private function stop_ringing( $appointment ) {
		delete_transient( $this->ring_key( $appointment->doctor_user_id ) );
		delete_transient( $this->ring_key( $appointment->patient_user_id ) );
	}

	/**
	 * Report whether a call is waiting for the current user.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response
	 */
	public function get_incoming_call( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$ring    = get_transient( $this->ring_key( $user_id ) );

		if ( ! is_array( $ring ) || empty( $ring['appointment_id'] ) ) {
			return new WP_REST_Response( array( 'ringing' => false ), 200 );
		}

		$table_appointments = \EGCare\DB::get_table( 'appointments' );
		$table_meta         = \EGCare\DB::get_table( 'doctors_meta' );

		$appointment = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT a.*, d.user_id as doctor_user_id
				 FROM $table_appointments a
				 JOIN $table_meta d ON a.doctor_id = d.post_id
				 WHERE a.id = %d",
				(int) $ring['appointment_id']
			)
		);

		$is_doctor  = $appointment && (int) $appointment->doctor_user_id === $user_id;
		$is_patient = $appointment && (int) $appointment->patient_user_id === $user_id;

		// Only ring while the call could actually be joined, and only for someone
		// who is party to it.
		if ( ! $appointment
			|| ( ! $is_doctor && ! $is_patient )
			|| ! in_array( $appointment->status, array( 'confirmed', 'ongoing' ), true ) ) {
			delete_transient( $this->ring_key( $user_id ) );

			return new WP_REST_Response( array( 'ringing' => false ), 200 );
		}

		if ( $is_doctor ) {
			$from_name = $appointment->family_member_name ? $appointment->family_member_name : __( 'Your patient', 'eg-care' );
		} else {
			$from_name = get_the_title( $appointment->doctor_id );
		}

		return new WP_REST_Response(
			array(
				'ringing'        => true,
				'appointment_id' => (int) $appointment->id,
				'from_name'      => $from_name,
				'started_at'     => (int) $ring['at'],
			),
			200
		);
	}

	/**
	 * Submit a review for a completed appointment.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit_review( $request ) {
		global $wpdb;
		$appointment_id = intval( $request['id'] );
		$params         = $request->get_json_params();
		$rating         = isset( $params['rating'] ) ? intval( $params['rating'] ) : 0;
		$comment        = isset( $params['comment'] ) ? sanitize_textarea_field( $params['comment'] ) : '';

		if ( $rating < 1 || $rating > 5 ) {
			return new \WP_Error( 'invalid_rating', __( 'Rating must be between 1 and 5.', 'eg-care' ), array( 'status' => 400 ) );
		}

		$table_appointments = DB::get_table( 'appointments' );
		$table_reviews      = DB::get_table( 'reviews' );
		$table_doctors_meta = DB::get_table( 'doctors_meta' );

		// Fetch appointment
		$appointment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_appointments WHERE id = %d", $appointment_id ) );
		if ( ! $appointment ) {
			return new \WP_Error( 'not_found', __( 'Appointment not found.', 'eg-care' ), array( 'status' => 404 ) );
		}

		// Security: must be the patient who booked this
		$current_user_id = get_current_user_id();
		if ( intval( $current_user_id ) !== intval( $appointment->patient_user_id ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'You do not have permission to review this appointment.', 'eg-care' ), array( 'status' => 403 ) );
		}

		// Verify appointment is completed
		if ( 'completed' !== $appointment->status ) {
			return new \WP_Error( 'invalid_status', __( 'You can only review completed appointments.', 'eg-care' ), array( 'status' => 400 ) );
		}

		// Check if a review already exists
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_reviews WHERE appointment_id = %d", $appointment_id ) );
		if ( $exists ) {
			return new \WP_Error( 'review_exists', __( 'You have already reviewed this appointment.', 'eg-care' ), array( 'status' => 400 ) );
		}

		// Insert Review
		$inserted = $wpdb->insert(
			$table_reviews,
			array(
				'appointment_id'  => $appointment_id,
				'doctor_id'       => $appointment->doctor_id,
				'patient_user_id' => $current_user_id,
				'rating'          => $rating,
				'comment'         => $comment,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			// The unique index caught a second review that slipped past the check
			// above, which a double submit can do.
			return new \WP_Error( 'review_exists', __( 'You have already reviewed this appointment.', 'eg-care' ), array( 'status' => 400 ) );
		}

		// Recalculate Average Rating and Total Reviews
		$doctor_id = $appointment->doctor_id;
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(id) as total, AVG(rating) as avg_val FROM $table_reviews WHERE doctor_id = %d",
				$doctor_id
			)
		);

		$total_reviews = intval( $stats->total );
		$avg_rating    = floatval( $stats->avg_val );

		// Update Doctor meta row
		$wpdb->update(
			$table_doctors_meta,
			array(
				'avg_rating'    => $avg_rating,
				'total_reviews' => $total_reviews,
			),
			array( 'post_id' => $doctor_id ),
			array( '%f', '%d' ),
			array( '%d' )
		);

		return new \WP_REST_Response( array( 'status' => 'success', 'avg_rating' => $avg_rating, 'total_reviews' => $total_reviews ), 200 );
	}
}
