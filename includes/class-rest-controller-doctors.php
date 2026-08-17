<?php
/**
 * REST API Controller for Doctors.
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
 * Class RestControllerDoctors
 */
class RestControllerDoctors extends WP_REST_Controller {

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		$namespace = 'meditaj/v1';

		register_rest_route(
			$namespace,
			'/specialties',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_specialties' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			$namespace,
			'/doctors',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_doctors' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		register_rest_route(
			$namespace,
			'/doctors/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_doctor' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'id' => array(
							'validate_callback' => function( $param, $request, $key ) {
								return is_numeric( $param );
							},
						),
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/doctors/(?P<id>\d+)/slots',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_doctor_slots' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'id' => array(
							'validate_callback' => function( $param, $request, $key ) {
								return is_numeric( $param );
							},
						),
						'date' => array(
							'required'          => true,
							'validate_callback' => function( $param, $request, $key ) {
								return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $param );
							},
						),
					),
				),
			)
		);
	}

	/**
	 * Get all specialty terms with custom icons.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_specialties( $request ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'specialty',
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) ) {
			return new WP_REST_Response( array(), 500 );
		}

		$response_data = array();
		foreach ( $terms as $term ) {
			$icon_url = null;
			$icon_id  = get_term_meta( $term->term_id, 'specialty_icon_id', true );
			if ( $icon_id ) {
				$icon_url = wp_get_attachment_url( $icon_id );
				if ( ! $icon_url ) {
					$icon_url = null;
				}
			}

			$response_data[] = array(
				'id'       => intval( $term->term_id ),
				'name'     => $term->name,
				'slug'     => $term->slug,
				'icon_url' => $icon_url,
			);
		}

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Get approved doctors with query parameter filtering.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_doctors( $request ) {
		global $wpdb;
		$table_meta = \Meditaj\DB::get_table( 'doctors_meta' );

		// Parse search and filters.
		$specialty_slug = $request->get_param( 'specialty' );
		$fee_min        = $request->get_param( 'fee_min' );
		$fee_max        = $request->get_param( 'fee_max' );
		$instant_only   = $request->get_param( 'instant_only' );
		$search         = $request->get_param( 'search' );

		// Base SQL structure.
		$query = "SELECT m.*, p.ID as post_id 
			FROM $table_meta m 
			JOIN {$wpdb->posts} p ON m.post_id = p.ID 
			WHERE p.post_type = 'doctors' 
				AND p.post_status = 'publish' 
				AND m.verification_status = 'approved'";

		$params = array();

		// 1. Specialty taxonomy slug filtering.
		if ( ! empty( $specialty_slug ) ) {
			$term = get_term_by( 'slug', $specialty_slug, 'specialty' );
			if ( $term ) {
				$post_ids = get_objects_in_term( $term->term_id, 'specialty' );
				if ( ! empty( $post_ids ) ) {
					$placeholders = implode( ',', array_map( 'intval', $post_ids ) );
					$query       .= " AND p.ID IN ($placeholders)";
				} else {
					$query .= " AND 1=0"; // Force zero result.
				}
			} else {
				$query .= " AND 1=0"; // Force zero result.
			}
		}

		// 2. Minimum consultation fee filter.
		if ( null !== $fee_min && '' !== $fee_min ) {
			$query   .= ' AND m.consultation_fee >= %f';
			$params[] = floatval( $fee_min );
		}

		// 3. Maximum consultation fee filter.
		if ( null !== $fee_max && '' !== $fee_max ) {
			$query   .= ' AND m.consultation_fee <= %f';
			$params[] = floatval( $fee_max );
		}

		// 4. Instant call active (online only).
		if ( filter_var( $instant_only, FILTER_VALIDATE_BOOLEAN ) ) {
			$query .= ' AND m.is_online = 1';
		}

		// 5. Keyword search.
		if ( ! empty( $search ) ) {
			$like_search = '%' . $wpdb->esc_like( $search ) . '%';
			$query      .= ' AND (p.post_title LIKE %s OR p.post_content LIKE %s OR m.degree LIKE %s)';
			$params[]    = $like_search;
			$params[]    = $like_search;
			$params[]    = $like_search;
		}

		$query .= ' ORDER BY m.id DESC';

		if ( ! empty( $params ) ) {
			$results = $wpdb->get_results( $wpdb->prepare( $query, ...$params ) );
		} else {
			$results = $wpdb->get_results( $query );
		}

		$response_data = array();
		foreach ( $results as $row ) {
			$post = get_post( $row->post_id );
			if ( $post ) {
				$response_data[] = $this->format_doctor( $row, $post );
			}
		}

		return new WP_REST_Response( $response_data, 200 );
	}

	/**
	 * Get a single approved doctor profile by ID.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error Response object or WP_Error.
	 */
	public function get_doctor( $request ) {
		global $wpdb;
		$id         = intval( $request->get_param( 'id' ) );
		$table_meta = \Meditaj\DB::get_table( 'doctors_meta' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT m.* FROM $table_meta m 
				JOIN {$wpdb->posts} p ON m.post_id = p.ID 
				WHERE p.ID = %d AND p.post_type = 'doctors' AND p.post_status = 'publish' AND m.verification_status = 'approved'",
				$id
			)
		);

		if ( ! $row ) {
			return new WP_Error( 'meditaj_doctor_not_found', __( 'Approved doctor profile not found.', 'meditaj' ), array( 'status' => 404 ) );
		}

		$post = get_post( $id );
		return new WP_REST_Response( $this->format_doctor( $row, $post ), 200 );
	}

	/**
	 * Get slot availability for a doctor on a specific date.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error Response object or WP_Error.
	 */
	public function get_doctor_slots( $request ) {
		global $wpdb;
		$id         = intval( $request->get_param( 'id' ) );
		$date       = sanitize_text_field( $request->get_param( 'date' ) );
		$table_meta = \Meditaj\DB::get_table( 'doctors_meta' );

		// 1. Verify doctor exists and is approved.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT m.id FROM $table_meta m 
				JOIN {$wpdb->posts} p ON m.post_id = p.ID 
				WHERE p.ID = %d AND p.post_type = 'doctors' AND m.verification_status = 'approved'",
				$id
			)
		);

		if ( ! $exists ) {
			return new WP_Error( 'meditaj_doctor_not_found', __( 'Approved doctor profile not found.', 'meditaj' ), array( 'status' => 404 ) );
		}

		// 2. Perform slot calculation algorithm.
		$slots = $this->calculate_slots( $id, $date );

		return new WP_REST_Response( $slots, 200 );
	}

	/**
	 * Calculate slots for a doctor on a specific date.
	 *
	 * @param int    $doctor_id Doctor Post ID.
	 * @param string $date Date string in YYYY-MM-DD format.
	 * @return array List of slots.
	 */
	private function calculate_slots( $doctor_id, $date ) {
		global $wpdb;

		// 1. Get Day of Week (1 = Monday, ..., 7 = Sunday).
		$day_of_week = intval( date( 'N', strtotime( $date ) ) );

		// 2. Query Schedule Rules for this doctor on this day of week.
		$table_schedules = \Meditaj\DB::get_table( 'schedules' );
		$schedules       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT start_time, end_time, slot_duration_min, break_duration_min FROM $table_schedules WHERE doctor_id = %d AND day_of_week = %d AND is_active = 1",
				$doctor_id,
				$day_of_week
			)
		);

		if ( empty( $schedules ) ) {
			return array();
		}

		// 3. Generate candidate slots.
		$slots = array();
		foreach ( $schedules as $rule ) {
			$start = strtotime( $date . ' ' . $rule->start_time );
			$end   = strtotime( $date . ' ' . $rule->end_time );
			
			$duration_sec = intval( $rule->slot_duration_min ) * 60;
			$break_sec    = intval( isset( $rule->break_duration_min ) ? $rule->break_duration_min : 0 ) * 60;
			$total_step   = $duration_sec + $break_sec;

			if ( $duration_sec <= 0 ) {
				continue;
			}

			for ( $time = $start; $time + $duration_sec <= $end; $time += $total_step ) {
				$slots[] = date( 'H:i:s', $time );
			}
		}

		// Sort candidate slots.
		sort( $slots );
		$slots = array_unique( $slots );

		// 4. Query booked appointments for this doctor on this date.
		$table_appointments = \Meditaj\DB::get_table( 'appointments' );
		$appointments       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT appointment_time FROM $table_appointments WHERE doctor_id = %d AND appointment_date = %s AND status IN ('confirmed', 'ongoing', 'completed', 'pending_payment')",
				$doctor_id,
				$date
			)
		);

		$booked_times = array();
		foreach ( $appointments as $app ) {
			$booked_times[] = date( 'H:i:s', strtotime( $app->appointment_time ) );
		}

		// 5. Compare and build result.
		$result        = array();
		$now_timestamp = current_time( 'timestamp' ); // WordPress local time.

		foreach ( $slots as $slot_time ) {
			$is_booked = in_array( $slot_time, $booked_times, true );
			$available = ! $is_booked;

			// If the target date is today, check if slot is in the past.
			if ( $available && date( 'Y-m-d', strtotime( $date ) ) === date( 'Y-m-d', $now_timestamp ) ) {
				$slot_timestamp = strtotime( $date . ' ' . $slot_time );
				if ( $slot_timestamp < $now_timestamp ) {
					$available = false; // Slot has already passed today.
				}
			}

			$result[] = array(
				'time'      => $slot_time,
				'available' => $available,
			);
		}

		return $result;
	}

	/**
	 * Format doctor data structure for response.
	 *
	 * @param object   $row Database meta row.
	 * @param \WP_Post $post Post object.
	 * @return array Formatted response data.
	 */
	private function format_doctor( $row, $post ) {
		$specialties = array();
		$terms       = wp_get_post_terms( $post->ID, 'specialty' );
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				$specialties[] = $term->name;
			}
		}

		$photo_url = get_the_post_thumbnail_url( $post->ID, 'medium' );
		if ( ! $photo_url ) {
			$photo_url = null;
		}

		return array(
			'id'                  => intval( $post->ID ),
			'name'                => $post->post_title,
			'bio'                 => $post->post_content,
			'specialties'         => $specialties,
			'provider_type'       => $row->provider_type,
			'bmdc_license_no'     => $row->bmdc_license_no,
			'bmdc_expiry_date'    => $row->bmdc_expiry_date,
			'degree'              => $row->degree,
			'designation'         => $row->designation,
			'consultation_fee'    => floatval( $row->consultation_fee ),
			'instant_call_fee'    => floatval( $row->instant_call_fee ),
			'experience_years'    => intval( $row->experience_years ),
			'is_online'           => 1 === intval( $row->is_online ),
			'avg_rating'          => floatval( $row->avg_rating ),
			'total_reviews'       => intval( $row->total_reviews ),
			'photo_url'           => $photo_url,
			'nationality'         => $row->nationality,
			'organization'        => $row->organization,
			'follow_up_days'      => intval( $row->follow_up_days ),
			'follow_up_cost'      => floatval( $row->follow_up_cost ),
		);
	}
}
