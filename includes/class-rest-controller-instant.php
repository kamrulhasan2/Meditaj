<?php
/**
 * REST API for instant consultation requests.
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
 * A patient asking for whoever is free, rather than for a named doctor.
 *
 * The request goes into an open queue. Every doctor who is on instant duty and
 * online sees it, and the first to accept gets it; accepting is what creates
 * the appointment, so the ordinary payment and video-call machinery takes over
 * from there and none of it had to learn about queues.
 *
 * Payment comes after the claim, not before. The alternative - charge on
 * submit - would mean refunding every request nobody answered, and there is no
 * refund path in this plugin to lean on.
 */
class RestControllerInstant extends WP_REST_Controller {

	/**
	 * How long a request stays in the queue before it goes stale, in minutes.
	 */
	const REQUEST_TTL_MINUTES = 15;

	/**
	 * Longest symptom description we store, in characters.
	 */
	const MAX_SYMPTOMS = 2000;

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$namespace = 'eg-care/v1';

		register_rest_route(
			$namespace,
			'/instant-requests',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_request' ),
					'permission_callback' => array( $this, 'check_logged_in_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/instant-requests/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_request' ),
					'permission_callback' => array( $this, 'check_logged_in_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'cancel_request' ),
					'permission_callback' => array( $this, 'check_logged_in_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/instant-requests/(?P<id>\d+)/claim',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'claim_request' ),
					'permission_callback' => array( $this, 'check_doctor_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/instant-availability',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_availability' ),
					'permission_callback' => array( $this, 'check_logged_in_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/doctor/me/instant-requests',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_open_requests' ),
					'permission_callback' => array( $this, 'check_doctor_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/doctor/me/instant-duty',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_duty' ),
					'permission_callback' => array( $this, 'check_doctor_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_duty' ),
					'permission_callback' => array( $this, 'check_doctor_permission' ),
				),
			)
		);
	}

	/**
	 * Anyone logged in may act as a patient here.
	 *
	 * @return true|WP_Error
	 */
	public function check_logged_in_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'You must be logged in to ask for a doctor.', 'eg-care' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Only an approved doctor may see or claim the queue.
	 *
	 * @return true|WP_Error
	 */
	public function check_doctor_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'You must be logged in.', 'eg-care' ), array( 'status' => 401 ) );
		}

		if ( 0 === $this->current_doctor_id() ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have an approved doctor profile on this platform.', 'eg-care' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * The logged-in user's approved doctor post ID, or 0.
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

		$doctor_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT m.post_id FROM $table_meta m
				JOIN {$wpdb->posts} p ON m.post_id = p.ID
				WHERE m.user_id = %d AND p.post_status = 'publish'
					AND m.verification_status = 'approved'",
				$user_id
			)
		);

		return $doctor_id ? (int) $doctor_id : 0;
	}

	/**
	 * Who could answer right now, and what it will cost.
	 *
	 * Real numbers rather than reassuring ones: the count is the doctors
	 * actually on instant duty and online this minute, so a patient can see
	 * whether asking is worth it before they type anything.
	 *
	 * @return WP_REST_Response
	 */
	public function get_availability() {
		global $wpdb;

		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );

		$rows = $wpdb->get_results(
			"SELECT m.post_id FROM $table_meta m
			JOIN {$wpdb->posts} p ON m.post_id = p.ID
			WHERE p.post_type = 'doctors' AND p.post_status = 'publish'
				AND m.verification_status = 'approved'
				AND m.accepts_instant_requests = 1 AND m.is_online = 1
			ORDER BY m.avg_rating DESC, m.experience_years DESC"
		);

		$doctors = array();

		// Only the first few are pictured; the count below is the real total.
		foreach ( array_slice( (array) $rows, 0, 8 ) as $row ) {
			$post = get_post( (int) $row->post_id );

			if ( ! $post ) {
				continue;
			}

			$doctors[] = array(
				'id'        => (int) $post->ID,
				'name'      => $post->post_title,
				'photo_url' => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ?: null,
			);
		}

		$breakdown = \EGCare\Fees::breakdown( \EGCare\Fees::instant_request_fee() );

		return new WP_REST_Response(
			array(
				'doctors_on_duty' => count( (array) $rows ),
				'doctors'         => $doctors,
				'wait_minutes'    => self::REQUEST_TTL_MINUTES,
				'breakdown'       => $breakdown,
			),
			200
		);
	}

	/**
	 * A specialty slug the patient tagged the request with, or null.
	 *
	 * Checked against the real taxonomy rather than stored as typed. The tag is
	 * triage information for whoever answers - it does not route the request,
	 * because an instant request goes to whoever is free by definition.
	 *
	 * @param mixed $candidate Slug from the request.
	 * @return string|null
	 */
	private function clean_specialty( $candidate ) {
		$slug = sanitize_title( (string) $candidate );

		if ( '' === $slug ) {
			return null;
		}

		$term = get_term_by( 'slug', $slug, 'specialty' );

		return $term ? $term->slug : null;
	}

	/**
	 * Put a request into the queue.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_request( $request ) {
		global $wpdb;

		$user_id = get_current_user_id();
		$table   = \EGCare\DB::get_table( 'instant_requests' );

		// One live request at a time. Two open requests would mean two doctors
		// each answering the same person, and the patient paying twice.
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table
				WHERE patient_user_id = %d AND status = 'pending' AND expires_at > %s
				ORDER BY id DESC LIMIT 1",
				$user_id,
				current_time( 'mysql' )
			)
		);

		if ( $existing ) {
			return new WP_REST_Response( $this->shape( $existing ), 200 );
		}

		$contact = RestControllerBooking::normalize_contact_number( $request->get_param( 'contact_number' ) );

		if ( is_wp_error( $contact ) ) {
			return $contact;
		}

		$symptoms = trim( (string) $request->get_param( 'symptoms' ) );

		if ( '' === $symptoms ) {
			return new WP_Error(
				'eg_care_symptoms_required',
				__( 'Describe the problem, so the doctor knows what they are answering.', 'eg-care' ),
				array( 'status' => 400 )
			);
		}

		$symptoms = sanitize_textarea_field( mb_substr( $symptoms, 0, self::MAX_SYMPTOMS ) );

		$patient = $this->resolve_patient( intval( $request->get_param( 'family_member_id' ) ), $user_id );

		if ( is_wp_error( $patient ) ) {
			return $patient;
		}

		// The service charge applies here exactly as it does to a booked
		// consultation. Charging the bare fee for one and fee-plus-charge for
		// the other would make the same setting mean two different things.
		$breakdown = \EGCare\Fees::breakdown( \EGCare\Fees::instant_request_fee() );

		$now = current_time( 'mysql' );

		// expires_at is compared against current_time('mysql') later, so both
		// ends stay on the site's wall clock. Time::wall() and gmdate() are the
		// UTC-fixed pair that adds minutes to it without PHP's ambient timezone
		// getting a say.
		$expires = gmdate(
			'Y-m-d H:i:s',
			\EGCare\Time::wall( substr( $now, 0, 10 ), substr( $now, 11, 8 ) ) + ( self::REQUEST_TTL_MINUTES * MINUTE_IN_SECONDS )
		);

		$inserted = $wpdb->insert(
			$table,
			array(
				'patient_user_id'  => $user_id,
				'family_member_id' => $patient['id'],
				'patient_name'     => $patient['name'],
				'patient_age'      => $patient['age'],
				'patient_relation' => $patient['relation'],
				'contact_number'   => $contact,
				'symptoms'         => $symptoms,
				'specialty_slug'   => $this->clean_specialty( $request->get_param( 'specialty' ) ),
				'amount'           => $breakdown['total'],
				'status'           => 'pending',
				'expires_at'       => $expires,
				'created_at'       => $now,
				'updated_at'       => $now,
			),
			array( '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error( 'eg_care_db_error', __( 'Your request could not be sent. Please try again.', 'eg-care' ), array( 'status' => 500 ) );
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $wpdb->insert_id ) );

		return new WP_REST_Response( $this->shape( $row ), 201 );
	}

	/**
	 * Work out who the request is for.
	 *
	 * @param int $member_id Saved family member id, 0 for the account holder.
	 * @param int $user_id   Requesting user.
	 * @return array|WP_Error {id, name, age, relation}
	 */
	private function resolve_patient( $member_id, $user_id ) {
		if ( $member_id > 0 ) {
			$member = FamilyMembers::find_owned( $member_id, $user_id );

			if ( ! $member ) {
				return new WP_Error( 'eg_care_family_not_found', __( 'That person is not on your list.', 'eg-care' ), array( 'status' => 404 ) );
			}

			return array(
				'id'       => (int) $member->id,
				'name'     => $member->name,
				'age'      => FamilyMembers::age_from_dob( $member->date_of_birth ),
				'relation' => $member->relation,
			);
		}

		return array(
			'id'       => null,
			'name'     => wp_get_current_user()->display_name,
			'age'      => null,
			'relation' => 'self',
		);
	}

	/**
	 * Where a request has got to.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_request( $request ) {
		$row = $this->find_owned( intval( $request->get_param( 'id' ) ), get_current_user_id() );

		if ( is_wp_error( $row ) ) {
			return $row;
		}

		return new WP_REST_Response( $this->shape( $this->expire_if_stale( $row ) ), 200 );
	}

	/**
	 * Withdraw a request nobody has answered yet.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function cancel_request( $request ) {
		global $wpdb;

		$row = $this->find_owned( intval( $request->get_param( 'id' ) ), get_current_user_id() );

		if ( is_wp_error( $row ) ) {
			return $row;
		}

		if ( 'claimed' === $row->status ) {
			return new WP_Error(
				'eg_care_already_claimed',
				__( 'A doctor has already accepted this request.', 'eg-care' ),
				array( 'status' => 409 )
			);
		}

		$table = \EGCare\DB::get_table( 'instant_requests' );

		$wpdb->update(
			$table,
			array(
				'status'     => 'cancelled',
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'id'     => (int) $row->id,
				'status' => 'pending',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		$row->status = 'cancelled';

		return new WP_REST_Response( $this->shape( $row ), 200 );
	}

	/**
	 * The queue, as the doctor on duty sees it.
	 *
	 * @return WP_REST_Response
	 */
	public function get_open_requests() {
		global $wpdb;

		$table = \EGCare\DB::get_table( 'instant_requests' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table
				WHERE status = 'pending' AND expires_at > %s
				ORDER BY created_at ASC
				LIMIT 50",
				current_time( 'mysql' )
			)
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[] = $this->shape_for_doctor( $row );
		}

		return new WP_REST_Response( $out, 200 );
	}

	/**
	 * Take a request off the queue and turn it into an appointment.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function claim_request( $request ) {
		global $wpdb;

		$doctor_id = $this->current_doctor_id();
		$id        = intval( $request->get_param( 'id' ) );
		$table     = \EGCare\DB::get_table( 'instant_requests' );
		$now       = current_time( 'mysql' );

		if ( ! $this->is_on_duty( $doctor_id ) ) {
			return new WP_Error(
				'eg_care_not_on_duty',
				__( 'Turn on instant duty before accepting requests.', 'eg-care' ),
				array( 'status' => 403 )
			);
		}

		// The claim itself: one conditional UPDATE, so two doctors pressing
		// Accept at the same moment cannot both win. Whoever the database lets
		// through changed a row; the other changed none and is told so.
		$claimed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $table
				SET status = 'claimed', doctor_id = %d, claimed_at = %s, updated_at = %s
				WHERE id = %d AND status = 'pending' AND expires_at > %s",
				$doctor_id,
				$now,
				$now,
				$id,
				$now
			)
		);

		if ( ! $claimed ) {
			return new WP_Error(
				'eg_care_request_gone',
				__( 'That request is no longer waiting - someone else took it, or it timed out.', 'eg-care' ),
				array( 'status' => 409 )
			);
		}

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );

		$appointment_id = $this->create_appointment_for( $row, $doctor_id, $now );

		if ( is_wp_error( $appointment_id ) ) {
			// Put it back in the queue rather than leave it claimed with no
			// appointment behind it - that would strand the patient on a
			// waiting screen forever.
			$wpdb->update(
				$table,
				array(
					'status'     => 'pending',
					'doctor_id'  => null,
					'claimed_at' => null,
					'updated_at' => $now,
				),
				array( 'id' => $id ),
				array( '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);

			return $appointment_id;
		}

		$wpdb->update(
			$table,
			array(
				'appointment_id' => $appointment_id,
				'updated_at'     => $now,
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		$row->appointment_id = $appointment_id;
		$row->status         = 'claimed';
		$row->doctor_id      = $doctor_id;

		return new WP_REST_Response( $this->shape_for_doctor( $row ), 200 );
	}

	/**
	 * Create the appointment a claimed request becomes.
	 *
	 * Unpaid on purpose: the patient pays once they know who answered, through
	 * the same checkout every other booking uses.
	 *
	 * @param object $row       Request row.
	 * @param int    $doctor_id Claiming doctor.
	 * @param string $now       Current time, mysql format.
	 * @return int|WP_Error Appointment ID.
	 */
	private function create_appointment_for( $row, $doctor_id, $now ) {
		global $wpdb;

		$table_appointments = \EGCare\DB::get_table( 'appointments' );

		$inserted = $wpdb->insert(
			$table_appointments,
			array(
				'doctor_id'              => $doctor_id,
				'patient_user_id'        => (int) $row->patient_user_id,
				'family_member_id'       => $row->family_member_id ? (int) $row->family_member_id : null,
				'family_member_name'     => $row->patient_name,
				'family_member_age'      => $row->patient_age,
				'family_member_relation' => $row->patient_relation,
				'contact_number'         => $row->contact_number,
				'appointment_type'       => 'instant',
				'appointment_date'       => substr( $now, 0, 10 ),
				'appointment_time'       => substr( $now, 11, 8 ),
				'status'                 => 'pending_payment',
				'payment_status'         => 'unpaid',
				'amount'                 => (float) $row->amount,
				'uploaded_files'         => wp_json_encode( array() ),
				'symptom_notes'          => $row->symptoms,
				'instant_request_id'     => (int) $row->id,
				'created_at'             => $now,
				'updated_at'             => $now,
			),
			array( '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new WP_Error(
				'eg_care_db_error',
				__( 'The appointment could not be created for this request.', 'eg-care' ),
				array( 'status' => 500 )
			);
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Whether this doctor is taking instant requests right now.
	 *
	 * Both halves matter: a doctor who left the switch on but went offline is
	 * not there to answer, and one who is online but never opted in did not ask
	 * for general patients.
	 *
	 * @param int $doctor_id Doctor post ID.
	 * @return bool
	 */
	private function is_on_duty( $doctor_id ) {
		global $wpdb;

		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table_meta
				WHERE post_id = %d AND accepts_instant_requests = 1 AND is_online = 1",
				$doctor_id
			)
		);
	}

	/**
	 * Report whether the doctor is on instant duty.
	 *
	 * @return WP_REST_Response
	 */
	public function get_duty() {
		global $wpdb;

		$doctor_id  = $this->current_doctor_id();
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT accepts_instant_requests, is_online FROM $table_meta WHERE post_id = %d",
				$doctor_id
			)
		);

		return new WP_REST_Response(
			array(
				'accepts_instant_requests' => $row ? 1 === (int) $row->accepts_instant_requests : false,
				'is_online'                => $row ? 1 === (int) $row->is_online : false,
				'fee'                      => \EGCare\Fees::instant_request_fee(),
			),
			200
		);
	}

	/**
	 * Turn instant duty on or off.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function set_duty( $request ) {
		global $wpdb;

		$doctor_id  = $this->current_doctor_id();
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );
		$on         = filter_var( $request->get_param( 'accepts_instant_requests' ), FILTER_VALIDATE_BOOLEAN );

		$wpdb->update(
			$table_meta,
			array( 'accepts_instant_requests' => $on ? 1 : 0 ),
			array( 'post_id' => $doctor_id ),
			array( '%d' ),
			array( '%d' )
		);

		return $this->get_duty();
	}

	/**
	 * Load a request, refusing anyone but the patient who made it.
	 *
	 * @param int $id      Request ID.
	 * @param int $user_id Asking user.
	 * @return object|WP_Error
	 */
	private function find_owned( $id, $user_id ) {
		global $wpdb;

		$table = \EGCare\DB::get_table( 'instant_requests' );

		// The owner check is in the query rather than an if afterwards, so a
		// wrong id and someone else's id are the same answer: not found.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d AND patient_user_id = %d",
				$id,
				$user_id
			)
		);

		if ( ! $row ) {
			return new WP_Error( 'rest_not_found', __( 'That request was not found.', 'eg-care' ), array( 'status' => 404 ) );
		}

		return $row;
	}

	/**
	 * Mark a request expired once its window has passed.
	 *
	 * Done on read rather than on a schedule: the patient's own screen is
	 * asking anyway, and a queue this small does not need a cron job to tidy it.
	 *
	 * @param object $row Request row.
	 * @return object The row, possibly with a new status.
	 */
	private function expire_if_stale( $row ) {
		global $wpdb;

		if ( 'pending' !== $row->status ) {
			return $row;
		}

		$now = current_time( 'mysql' );

		if ( strtotime( $row->expires_at ) > strtotime( $now ) ) {
			return $row;
		}

		$table = \EGCare\DB::get_table( 'instant_requests' );

		$wpdb->update(
			$table,
			array(
				'status'     => 'expired',
				'updated_at' => $now,
			),
			array(
				'id'     => (int) $row->id,
				'status' => 'pending',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);

		$row->status = 'expired';

		return $row;
	}

	/**
	 * The request as its own patient sees it.
	 *
	 * @param object $row Request row.
	 * @return array
	 */
	private function shape( $row ) {
		$doctor = null;

		if ( $row->doctor_id ) {
			$post = get_post( (int) $row->doctor_id );
			if ( $post ) {
				$doctor = array(
					'id'        => (int) $row->doctor_id,
					'name'      => $post->post_title,
					'photo_url' => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ?: null,
				);
			}
		}

		return array(
			'id'             => (int) $row->id,
			'status'         => $row->status,
			'patient_name'   => $row->patient_name,
			'contact_number' => $row->contact_number,
			'symptoms'       => $row->symptoms,
			'specialty'      => $row->specialty_slug ? $row->specialty_slug : null,
			'amount'         => (float) $row->amount,
			// The lines behind the total, so the summary the patient agreed to
			// and the amount they are charged come from one calculation.
			'breakdown'      => \EGCare\Fees::breakdown( \EGCare\Fees::instant_request_fee() ),
			'doctor'         => $doctor,
			'appointment_id' => $row->appointment_id ? (int) $row->appointment_id : null,
			'expires_at'     => $row->expires_at,
			'seconds_left'   => max( 0, strtotime( $row->expires_at ) - strtotime( current_time( 'mysql' ) ) ),
			'created_at'     => $row->created_at,
		);
	}

	/**
	 * The readable name of a tagged specialty, or null.
	 *
	 * @param string|null $slug Stored slug.
	 * @return string|null
	 */
	private function specialty_label( $slug ) {
		if ( ! $slug ) {
			return null;
		}

		$term = get_term_by( 'slug', $slug, 'specialty' );

		// A term deleted since the request was made leaves the slug behind
		// rather than an empty chip.
		return $term ? $term->name : $slug;
	}

	/**
	 * The request as a doctor deciding whether to take it sees it.
	 *
	 * The contact number is left out until the request is theirs: a queue every
	 * on-duty doctor can read is no place for a patient's phone number.
	 *
	 * @param object $row Request row.
	 * @return array
	 */
	private function shape_for_doctor( $row ) {
		$mine = $row->doctor_id && (int) $row->doctor_id === $this->current_doctor_id();

		$out = array(
			'id'             => (int) $row->id,
			'status'         => $row->status,
			'patient_name'   => $row->patient_name,
			'patient_age'    => null !== $row->patient_age ? (int) $row->patient_age : null,
			'symptoms'       => $row->symptoms,
			'specialty'      => $this->specialty_label( $row->specialty_slug ),
			'amount'         => (float) $row->amount,
			'appointment_id' => $row->appointment_id ? (int) $row->appointment_id : null,
			'waiting_since'  => $row->created_at,
			'seconds_left'   => max( 0, strtotime( $row->expires_at ) - strtotime( current_time( 'mysql' ) ) ),
		);

		if ( $mine ) {
			$out['contact_number'] = $row->contact_number;
		}

		return $out;
	}
}
