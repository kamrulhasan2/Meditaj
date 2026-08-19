<?php
/**
 * REST API Controller for Doctor Dashboard.
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
 * Class RestControllerDoctorDashboard
 */
class RestControllerDoctorDashboard extends WP_REST_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$namespace = 'eg-care/v1';

		register_rest_route(
			$namespace,
			'/doctor/me/appointments',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_doctor_appointments' ),
					'permission_callback' => array( $this, 'check_doctor_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/doctor/me/slots',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_doctor_slots' ),
					'permission_callback' => array( $this, 'check_doctor_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_doctor_slots' ),
					'permission_callback' => array( $this, 'check_doctor_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/doctor/me/profile',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_doctor_profile' ),
					'permission_callback' => array( $this, 'check_doctor_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/doctor/me/stats',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_doctor_stats' ),
					'permission_callback' => array( $this, 'check_doctor_permission' ),
				),
			)
		);
	}

	/**
	 * Enforce doctor authentication and role check.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_doctor_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'You must be logged in.', 'eg-care' ), array( 'status' => 401 ) );
		}

		$doctor_id = $this->get_current_doctor_id();
		if ( 0 === $doctor_id ) {
			return new WP_Error( 'rest_forbidden', __( 'You do not have a doctor profile registered or approved on this platform.', 'eg-care' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Resolve current doctor CPT ID from WordPress User session.
	 *
	 * @return int
	 */
	private function get_current_doctor_id() {
		global $wpdb;
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return 0;
		}

		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );
		$doctor_id  = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $table_meta WHERE user_id = %d", $user_id ) );

		return $doctor_id ? intval( $doctor_id ) : 0;
	}

	/**
	 * Get appointments list for logged-in doctor.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_doctor_appointments() {
		global $wpdb;
		$doctor_id = $this->get_current_doctor_id();
		$table_appointments = \EGCare\DB::get_table( 'appointments' );

		// Query today's and upcoming appointments.
		$appointments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_appointments 
				WHERE doctor_id = %d AND status IN ('confirmed', 'ongoing', 'completed') 
				ORDER BY appointment_date ASC, appointment_time ASC",
				$doctor_id
			)
		);

		$today_date = current_time( 'Y-m-d' );
		$today_list = array();
		$upcoming_list = array();

		$site_timezone = wp_timezone();

		foreach ( $appointments as $app ) {
			// Absolute UTC start time for the appointment. The browser cannot work
			// this out from appointment_date/appointment_time on its own, because
			// those are wall-clock values in the site's timezone, not the viewer's.
			$app->starts_at = null;
			if ( ! empty( $app->appointment_date ) && ! empty( $app->appointment_time ) ) {
				try {
					$start          = new \DateTime( $app->appointment_date . ' ' . $app->appointment_time, $site_timezone );
					$app->starts_at = $start->getTimestamp();
				} catch ( \Exception $e ) {
					$app->starts_at = null;
				}
			}

			if ( $app->appointment_date === $today_date ) {
				$today_list[] = $app;
			} elseif ( $app->appointment_date > $today_date ) {
				$upcoming_list[] = $app;
			}
		}

		return new WP_REST_Response(
			array(
				'today'    => $today_list,
				'upcoming' => $upcoming_list,
			),
			200
		);
	}

	/**
	 * Get slots schedules rules for logged-in doctor.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_doctor_slots() {
		global $wpdb;
		$doctor_id = $this->get_current_doctor_id();
		$table_schedules = \EGCare\DB::get_table( 'schedules' );

		$slots = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_schedules WHERE doctor_id = %d ORDER BY day_of_week ASC, start_time ASC",
				$doctor_id
			)
		);

		return new WP_REST_Response( $slots, 200 );
	}

	/**
	 * Save/Overwrite weekly slots grid for doctor.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function save_doctor_slots( $request ) {
		global $wpdb;
		$doctor_id = $this->get_current_doctor_id();
		$slots     = $request->get_param( 'slots' ); // Expects array of { day_of_week, start_time, end_time, slot_duration_min }

		if ( ! is_array( $slots ) ) {
			return new WP_Error( 'rest_bad_request', __( 'Invalid slots parameter format.', 'eg-care' ), array( 'status' => 400 ) );
		}

		$table_schedules = \EGCare\DB::get_table( 'schedules' );

		// Execute deletion and insertion in transaction.
		$wpdb->query( 'START TRANSACTION' );

		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM $table_schedules WHERE doctor_id = %d", $doctor_id ) );
		if ( false === $deleted ) {
			$wpdb->query( 'ROLLBACK' );
			return new WP_Error( 'eg_care_db_error', __( 'Failed to clear old schedules.', 'eg-care' ), array( 'status' => 500 ) );
		}

		foreach ( $slots as $slot ) {
			if ( ! is_array( $slot ) ) {
				continue; // Not a slot object at all.
			}

			// Every field is optional on the wire; the validation below rejects
			// whatever is missing, rather than raising a notice reading it.
			$day      = isset( $slot['day_of_week'] ) ? intval( $slot['day_of_week'] ) : 0;
			$start    = isset( $slot['start_time'] ) ? sanitize_text_field( $slot['start_time'] ) : '';
			$end      = isset( $slot['end_time'] ) ? sanitize_text_field( $slot['end_time'] ) : '';
			$duration = isset( $slot['slot_duration_min'] ) ? intval( $slot['slot_duration_min'] ) : 0;
			$break    = isset( $slot['break_duration_min'] ) ? intval( $slot['break_duration_min'] ) : 0;
			$active   = isset( $slot['is_active'] ) ? ( intval( $slot['is_active'] ) ? 1 : 0 ) : 1;

			if ( $day < 1 || $day > 7 || empty( $start ) || empty( $end ) || $duration <= 0 ) {
				continue; // Skip malformed rows.
			}

			$inserted = $wpdb->insert(
				$table_schedules,
				array(
					'doctor_id'          => $doctor_id,
					'day_of_week'        => $day,
					'start_time'         => $start,
					'end_time'           => $end,
					'slot_duration_min'  => $duration,
					'break_duration_min' => $break,
					'is_active'          => $active,
				),
				array( '%d', '%d', '%s', '%s', '%d', '%d', '%d' )
			);

			if ( false === $inserted ) {
				$wpdb->query( 'ROLLBACK' );
				return new WP_Error( 'eg_care_db_error', __( 'Failed to record schedule rule in database.', 'eg-care' ), array( 'status' => 500 ) );
			}
		}

		$wpdb->query( 'COMMIT' );

		return new WP_REST_Response( array( 'status' => 'success' ), 200 );
	}

	/**
	 * Update doctor profile settings.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_doctor_profile( $request ) {
		global $wpdb;
		$doctor_id = $this->get_current_doctor_id();

		$bio         = wp_kses_post( $request->get_param( 'bio' ) );
		$fee         = floatval( $request->get_param( 'consultation_fee' ) );
		$instant_fee = floatval( $request->get_param( 'instant_call_fee' ) );
		$is_online   = $request->get_param( 'is_online' ) ? 1 : 0;
		$photo_id    = intval( $request->get_param( 'photo_id' ) );

		if ( $fee <= 0 ) {
			return new WP_Error( 'rest_bad_request', __( 'Consultation fee must be positive.', 'eg-care' ), array( 'status' => 400 ) );
		}

		// Update post content.
		wp_update_post(
			array(
				'ID'           => $doctor_id,
				'post_content' => $bio,
			)
		);

		// Update doctors_meta.
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );
		$updated = $wpdb->update(
			$table_meta,
			array(
				'consultation_fee' => $fee,
				'instant_call_fee' => $instant_fee,
				'is_online'        => $is_online,
			),
			array( 'post_id' => $doctor_id ),
			array( '%f', '%f', '%d' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'eg_care_db_error', __( 'Failed to save doctor metadata details.', 'eg-care' ), array( 'status' => 500 ) );
		}

		// Update thumbnail if passed.
		if ( ! empty( $photo_id ) ) {
			set_post_thumbnail( $doctor_id, $photo_id );
		}

		// Retrieve updated object.
		$updated_doctor = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_meta WHERE post_id = %d", $doctor_id ) );

		return new WP_REST_Response(
			array(
				'status'  => 'success',
				'is_online' => (bool) $updated_doctor->is_online,
			),
			200
		);
	}

	/**
	 * Get stats dashboard calculations.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_doctor_stats() {
		global $wpdb;
		$doctor_id = $this->get_current_doctor_id();
		$table_appointments = \EGCare\DB::get_table( 'appointments' );

		// Count total appointments.
		$total_appointments = intval(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(id) FROM $table_appointments WHERE doctor_id = %d AND status IN ('confirmed', 'ongoing', 'completed')",
					$doctor_id
				)
			)
		);

		// Calculate Gross Earnings.
		$gross_earnings = floatval(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(amount) FROM $table_appointments 
					WHERE doctor_id = %d AND payment_status = 'paid' AND status IN ('confirmed', 'ongoing', 'completed')",
					$doctor_id
				)
			)
		);

		// Configurable platform commission.
		$commission_pct = floatval( get_option( 'eg_care_commission_percentage', 15 ) );
		$commission_amt = $gross_earnings * ( $commission_pct / 100 );
		$net_earnings   = $gross_earnings - $commission_amt;

		return new WP_REST_Response(
			array(
				'total_appointments' => $total_appointments,
				'gross_earnings'     => $gross_earnings,
				'commission_pct'     => $commission_pct,
				'commission_amount'  => $commission_amt,
				'net_earnings'       => $net_earnings,
			),
			200
		);
	}
}
