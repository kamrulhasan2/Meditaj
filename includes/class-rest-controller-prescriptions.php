<?php
/**
 * REST API Controller for the doctor's prescriptions.
 *
 * Everything here is scoped to the signed-in doctor's own appointments. A
 * doctor cannot read or write a prescription for a consultation that is not
 * theirs, and the check is on the appointment row rather than on anything the
 * browser sends.
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
 * Class RestControllerPrescriptions
 */
class RestControllerPrescriptions extends WP_REST_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$namespace = 'eg-care/v1';

		register_rest_route(
			$namespace,
			'/doctor/me/prescriptions',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_consultations' ),
					'permission_callback' => array( $this, 'check_doctor_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/doctor/me/prescriptions/(?P<appointment_id>\d+)',
			array(
				'args' => array(
					'appointment_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_prescription' ),
					'permission_callback' => array( $this, 'check_doctor_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_prescription' ),
					'permission_callback' => array( $this, 'check_doctor_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/doctor/me/prescriptions/(?P<appointment_id>\d+)/submit',
			array(
				'args' => array(
					'appointment_id' => array(
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'submit_prescription' ),
					'permission_callback' => array( $this, 'check_doctor_permission' ),
				),
			)
		);
	}

	/**
	 * Signed in, and has an approved doctor profile.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_doctor_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'You must be logged in.', 'eg-care' ), array( 'status' => 401 ) );
		}

		if ( 0 === $this->current_doctor_id() ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have a doctor profile on this platform.', 'eg-care' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * The doctor CPT id behind the current session.
	 *
	 * The same lookup the doctor dashboard controller does. It is six lines of
	 * SQL and repeating it here keeps this controller from reaching into
	 * another one's privates.
	 *
	 * @return int
	 */
	private function current_doctor_id() {
		global $wpdb;

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return 0;
		}

		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );
		$doctor_id  = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $table_meta WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $doctor_id ? intval( $doctor_id ) : 0;
	}

	/**
	 * Who the consultation was for.
	 *
	 * A booking made for a family member carries that person's name; one the
	 * account holder made for themselves carries nothing, and the patient is
	 * the account.
	 *
	 * @param object $appointment Appointment row.
	 * @return string
	 */
	private function patient_name( $appointment ) {
		if ( ! empty( $appointment->family_member_name ) ) {
			return $appointment->family_member_name;
		}

		if ( ! empty( $appointment->patient_user_id ) ) {
			$user = get_userdata( intval( $appointment->patient_user_id ) );

			if ( $user ) {
				return $user->display_name;
			}
		}

		return __( 'Patient', 'eg-care' );
	}

	/**
	 * One row for the doctor's list.
	 *
	 * @param object      $appointment  Appointment row.
	 * @param object|null $prescription Prescription row, if any.
	 * @return array
	 */
	private function shape_row( $appointment, $prescription ) {
		$row = array(
			'appointment_id'   => intval( $appointment->id ),
			'patient_name'     => $this->patient_name( $appointment ),
			'relation'         => $appointment->family_member_relation ? $appointment->family_member_relation : '',
			'age'              => $appointment->family_member_age ? intval( $appointment->family_member_age ) : null,
			'appointment_type' => $appointment->appointment_type,
			'appointment_date' => $appointment->appointment_date,
			'appointment_time' => $appointment->appointment_time,
			'symptom_notes'    => $appointment->symptom_notes ? $appointment->symptom_notes : '',
			'prescription'     => null,
		);

		// Whatever the patient attached when booking. The doctor needs it on
		// the same screen as the prescription, not a tab away.
		$row['reports'] = array();

		foreach ( \EGCare\SecureUploads::attachment_ids( $appointment->uploaded_files ) as $attachment_id ) {
			$row['reports'][] = array(
				'name' => get_the_title( $attachment_id ),
				'url'  => \EGCare\SecureUploads::get_report_url( $appointment->id, $attachment_id ),
			);
		}

		if ( $prescription ) {
			$row['prescription'] = array(
				'id'             => intval( $prescription->id ),
				'status'         => $prescription->status,
				'status_label'   => \EGCare\Prescriptions::status_label( $prescription->status ),
				'editable'       => \EGCare\Prescriptions::is_editable( $prescription->status ),
				'moderator_note' => $prescription->moderator_note ? $prescription->moderator_note : '',
				'updated_at'     => $prescription->updated_at,
			);
		}

		return $row;
	}

	/**
	 * Consultations this doctor may write a prescription for.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_consultations() {
		global $wpdb;

		$doctor_id = $this->current_doctor_id();

		$appointments_table  = \EGCare\DB::get_table( 'appointments' );
		$prescriptions_table = \EGCare\Prescriptions::table();

		$writable     = \EGCare\Prescriptions::writable_appointment_statuses();
		$placeholders = implode( ', ', array_fill( 0, count( $writable ), '%s' ) );

		$params = array_merge( array( $doctor_id ), $writable );

		$appointments = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT * FROM $appointments_table
				  WHERE doctor_id = %d
				    AND status IN ( $placeholders )
				    AND payment_status = 'paid'
			   ORDER BY COALESCE( appointment_date, DATE( created_at ) ) DESC,
			            COALESCE( appointment_time, TIME( created_at ) ) DESC,
			            id DESC
				  LIMIT 100",
				$params
			)
		);

		if ( ! $appointments ) {
			return new WP_REST_Response( array( 'consultations' => array() ), 200 );
		}

		// One query for every prescription in the batch rather than one per
		// appointment.
		$ids          = wp_list_pluck( $appointments, 'id' );
		$id_list      = implode( ', ', array_map( 'intval', $ids ) );
		$by_appointment = array();

		$rows = $wpdb->get_results( "SELECT * FROM $prescriptions_table WHERE appointment_id IN ( $id_list ) ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL

		foreach ( (array) $rows as $row ) {
			$by_appointment[ intval( $row->appointment_id ) ] = $row;
		}

		$out = array();

		foreach ( $appointments as $appointment ) {
			$existing = isset( $by_appointment[ intval( $appointment->id ) ] )
				? $by_appointment[ intval( $appointment->id ) ]
				: null;

			$out[] = $this->shape_row( $appointment, $existing );
		}

		return new WP_REST_Response( array( 'consultations' => $out ), 200 );
	}

	/**
	 * The prescription for one consultation, draft included.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_prescription( $request ) {
		$appointment_id = absint( $request['appointment_id'] );
		$appointment    = \EGCare\Prescriptions::writable_appointment( $appointment_id, $this->current_doctor_id() );

		if ( ! $appointment ) {
			return $this->not_yours();
		}

		$existing = \EGCare\Prescriptions::get_for_appointment( $appointment_id );

		$payload                 = $this->shape_row( $appointment, $existing );
		$payload['content']      = \EGCare\Prescriptions::content_of( $existing );
		$payload['max_medicines'] = \EGCare\Prescriptions::MAX_MEDICINES;

		return new WP_REST_Response( $payload, 200 );
	}

	/**
	 * Save the draft. Called by auto-save as well as by the Save button, so it
	 * has to be cheap and it has to be idempotent.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_prescription( $request ) {
		$appointment_id = absint( $request['appointment_id'] );
		$appointment    = \EGCare\Prescriptions::writable_appointment( $appointment_id, $this->current_doctor_id() );

		if ( ! $appointment ) {
			return $this->not_yours();
		}

		$content = \EGCare\Prescriptions::sanitize_content( $request->get_param( 'content' ) );
		$saved   = \EGCare\Prescriptions::save_draft( $appointment, $content );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		return new WP_REST_Response(
			array(
				'saved'        => true,
				'id'           => $saved['id'],
				'status'       => $saved['status'],
				'status_label' => \EGCare\Prescriptions::status_label( $saved['status'] ),
				'content'      => $content,
				'can_submit'   => \EGCare\Prescriptions::has_substance( $content ),
			),
			200
		);
	}

	/**
	 * Save whatever is on screen, then hand it to the reviewers.
	 *
	 * Saving first means the doctor cannot submit one thing and have another
	 * stored because an auto-save was still in flight.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function submit_prescription( $request ) {
		$appointment_id = absint( $request['appointment_id'] );
		$appointment    = \EGCare\Prescriptions::writable_appointment( $appointment_id, $this->current_doctor_id() );

		if ( ! $appointment ) {
			return $this->not_yours();
		}

		if ( null !== $request->get_param( 'content' ) ) {
			$content = \EGCare\Prescriptions::sanitize_content( $request->get_param( 'content' ) );
			$saved   = \EGCare\Prescriptions::save_draft( $appointment, $content );

			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}

		$existing = \EGCare\Prescriptions::get_for_appointment( $appointment_id );

		if ( ! $existing ) {
			return new WP_Error(
				'eg_care_prescription_missing',
				__( 'Write the prescription before submitting it.', 'eg-care' ),
				array( 'status' => 400 )
			);
		}

		$submitted = \EGCare\Prescriptions::submit( $existing->id );

		if ( is_wp_error( $submitted ) ) {
			return $submitted;
		}

		return new WP_REST_Response(
			array(
				'submitted'    => true,
				'id'           => intval( $existing->id ),
				'status'       => \EGCare\Prescriptions::STATUS_SUBMITTED,
				'status_label' => \EGCare\Prescriptions::status_label( \EGCare\Prescriptions::STATUS_SUBMITTED ),
			),
			200
		);
	}

	/**
	 * The one refusal this controller gives, worded so it does not reveal
	 * whether the appointment exists at all.
	 *
	 * @return \WP_Error
	 */
	private function not_yours() {
		return new WP_Error(
			'rest_forbidden',
			__( 'That consultation is not one of yours, or it is not ready for a prescription yet.', 'eg-care' ),
			array( 'status' => 403 )
		);
	}
}
