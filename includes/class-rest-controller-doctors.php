<?php
/**
 * REST API Controller for Doctors.
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
 * Class RestControllerDoctors
 */
class RestControllerDoctors extends WP_REST_Controller {

	/**
	 * Doctors returned per page when the request does not say.
	 */
	const DEFAULT_PER_PAGE = 12;

	/**
	 * The most doctors one request may ask for.
	 */
	const MAX_PER_PAGE = 50;

	/**
	 * Transient name prefix for a doctor's cached patient count.
	 */
	const PATIENTS_COUNT_PREFIX = 'eg_care_patients_count_';

	/**
	 * How long a cached patient count stays good, in seconds.
	 */
	const PATIENTS_COUNT_TTL = 900;

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		$namespace = 'eg-care/v1';

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

		register_rest_route(
			$namespace,
			'/doctors/(?P<id>\d+)/schedule-days',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_doctor_schedule_days' ),
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
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );

		// Parse search and filters.
		$specialty_slug = $request->get_param( 'specialty' );
		$fee_min        = $request->get_param( 'fee_min' );
		$fee_max        = $request->get_param( 'fee_max' );
		$instant_only   = $request->get_param( 'instant_only' );
		$availability   = $request->get_param( 'availability' );
		$search         = $request->get_param( 'search' );

		// Base SQL structure. The FROM and WHERE are built once and used for
		// both the count and the page itself, so the total can never disagree
		// with what the rows say.
		$from = "FROM $table_meta m 
			JOIN {$wpdb->posts} p ON m.post_id = p.ID 
			WHERE p.post_type = 'doctors' 
				AND p.post_status = 'publish' 
				AND m.verification_status = 'approved'";

		$params = array();

		// 1. Specialty taxonomy filtering. Several slugs may be given, comma
		// separated: the list page offers specialty as a filter you can tick
		// more than one of rather than as a step you must pass through.
		$slugs = array_filter( array_map( 'trim', explode( ',', (string) $specialty_slug ) ) );

		if ( ! empty( $slugs ) ) {
			$post_ids = array();

			foreach ( $slugs as $slug ) {
				$term = get_term_by( 'slug', $slug, 'specialty' );
				if ( ! $term ) {
					continue;
				}
				$post_ids = array_merge( $post_ids, (array) get_objects_in_term( $term->term_id, 'specialty' ) );
			}

			$post_ids = array_values( array_unique( array_map( 'intval', $post_ids ) ) );

			if ( ! empty( $post_ids ) ) {
				$placeholders = implode( ',', $post_ids );
				$from        .= " AND p.ID IN ($placeholders)";
			} else {
				$from .= ' AND 1=0'; // Force zero result.
			}
		}

		// 2. Minimum consultation fee filter.
		if ( null !== $fee_min && '' !== $fee_min ) {
			$from    .= ' AND m.consultation_fee >= %f';
			$params[] = floatval( $fee_min );
		}

		// 3. Maximum consultation fee filter.
		if ( null !== $fee_max && '' !== $fee_max ) {
			$from    .= ' AND m.consultation_fee <= %f';
			$params[] = floatval( $fee_max );
		}

		// 4. Availability. 'online' is a doctor who can take a call right now;
		// 'scheduled' is one who has sitting hours to book ahead in. Ticking
		// both means either will do, which is why this is one OR rather than
		// two conditions. instant_only is the older spelling of 'online' and
		// still works.
		$wants = array_filter( array_map( 'trim', explode( ',', (string) $availability ) ) );

		if ( filter_var( $instant_only, FILTER_VALIDATE_BOOLEAN ) && ! in_array( 'online', $wants, true ) ) {
			$wants[] = 'online';
		}

		$wants = array_intersect( $wants, array( 'online', 'scheduled' ) );

		if ( ! empty( $wants ) ) {
			$table_schedules = \EGCare\DB::get_table( 'schedules' );
			$clauses         = array();

			if ( in_array( 'online', $wants, true ) ) {
				$clauses[] = 'm.is_online = 1';
			}

			if ( in_array( 'scheduled', $wants, true ) ) {
				// A doctor with no active schedule row cannot be booked ahead
				// at all, so "book later" means having one.
				$clauses[] = "EXISTS (SELECT 1 FROM $table_schedules s WHERE s.doctor_id = p.ID AND s.is_active = 1)";
			}

			$from .= ' AND (' . implode( ' OR ', $clauses ) . ')';
		}

		// 5. Keyword search.
		if ( ! empty( $search ) ) {
			$like_search = '%' . $wpdb->esc_like( $search ) . '%';
			$from       .= ' AND (p.post_title LIKE %s OR p.post_content LIKE %s OR m.degree LIKE %s)';
			$params[]    = $like_search;
			$params[]    = $like_search;
			$params[]    = $like_search;
		}

		// 6. Paging. A site with a few doctors never notices; one with three
		// hundred would otherwise send the lot on every keystroke in the
		// search box.
		$per_page = intval( $request->get_param( 'per_page' ) );
		$per_page = $per_page > 0 ? min( $per_page, self::MAX_PER_PAGE ) : self::DEFAULT_PER_PAGE;
		$page     = max( 1, intval( $request->get_param( 'page' ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) $from";
		$total     = empty( $params )
			? (int) $wpdb->get_var( $count_sql )
			: (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$params ) );

		$query        = "SELECT m.*, p.ID as post_id $from ORDER BY m.id DESC LIMIT %d OFFSET %d";
		$page_params  = $params;
		$page_params[] = $per_page;
		$page_params[] = $offset;

		$results = $wpdb->get_results( $wpdb->prepare( $query, ...$page_params ) );

		// One grouped query for the whole page rather than one per card.
		$patients = $this->patients_counts( wp_list_pluck( $results, 'post_id' ) );

		$response_data = array();
		foreach ( $results as $row ) {
			$post = get_post( $row->post_id );
			if ( $post ) {
				$id                = (int) $row->post_id;
				$response_data[]   = $this->format_doctor(
					$row,
					$post,
					isset( $patients[ $id ] ) ? $patients[ $id ] : 0
				);
			}
		}

		$response = new WP_REST_Response( $response_data, 200 );

		// The WordPress convention for a paged collection, so the body stays a
		// plain array and anything already reading it keeps working.
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', $per_page > 0 ? (int) ceil( $total / $per_page ) : 0 );

		return $response;
	}

	/**
	 * How many different patients each of these doctors has seen.
	 *
	 * Counted from completed appointments only: a booking that was cancelled or
	 * never paid for is not a patient the doctor has treated. Guests booking
	 * without an account share patient_user_id 0, so they are left out rather
	 * than counted as one person.
	 *
	 * The figure changes slowly and is read on every card, so it is cached per
	 * doctor and only the misses reach the database, in a single grouped query.
	 *
	 * @param int[] $doctor_ids Doctor post IDs.
	 * @return array Map of doctor ID to patient count.
	 */
	private function patients_counts( array $doctor_ids ) {
		global $wpdb;

		$doctor_ids = array_values( array_unique( array_map( 'intval', $doctor_ids ) ) );
		$counts     = array();
		$missing    = array();

		foreach ( $doctor_ids as $id ) {
			if ( $id <= 0 ) {
				continue;
			}

			$cached = get_transient( self::PATIENTS_COUNT_PREFIX . $id );

			if ( false === $cached ) {
				$missing[] = $id;
			} else {
				$counts[ $id ] = (int) $cached;
			}
		}

		if ( empty( $missing ) ) {
			return $counts;
		}

		$table_appointments = \EGCare\DB::get_table( 'appointments' );
		$placeholders       = implode( ',', array_fill( 0, count( $missing ), '%d' ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT doctor_id, COUNT(DISTINCT patient_user_id) AS total
				FROM $table_appointments
				WHERE doctor_id IN ($placeholders)
					AND status = 'completed'
					AND patient_user_id > 0
				GROUP BY doctor_id",
				...$missing
			)
		);

		$fresh = array();
		foreach ( (array) $rows as $row ) {
			$fresh[ (int) $row->doctor_id ] = (int) $row->total;
		}

		// A doctor with no completed appointments returns no row at all, and
		// that zero deserves caching too or it is queried again every time.
		foreach ( $missing as $id ) {
			$counts[ $id ] = isset( $fresh[ $id ] ) ? $fresh[ $id ] : 0;
			set_transient( self::PATIENTS_COUNT_PREFIX . $id, $counts[ $id ], self::PATIENTS_COUNT_TTL );
		}

		return $counts;
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
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT m.* FROM $table_meta m 
				JOIN {$wpdb->posts} p ON m.post_id = p.ID 
				WHERE p.ID = %d AND p.post_type = 'doctors' AND p.post_status = 'publish' AND m.verification_status = 'approved'",
				$id
			)
		);

		if ( ! $row ) {
			return new WP_Error( 'eg_care_doctor_not_found', __( 'Approved doctor profile not found.', 'eg-care' ), array( 'status' => 404 ) );
		}

		$post     = get_post( $id );
		$patients = $this->patients_counts( array( $id ) );

		return new WP_REST_Response(
			$this->format_doctor( $row, $post, isset( $patients[ $id ] ) ? $patients[ $id ] : 0 ),
			200
		);
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
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );

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
			return new WP_Error( 'eg_care_doctor_not_found', __( 'Approved doctor profile not found.', 'eg-care' ), array( 'status' => 404 ) );
		}

		// 2. Perform slot calculation algorithm.
		$day = $this->calculate_slots( $id, $date );

		// The response carries the doctor's sitting hours for the day next to
		// the slots themselves, so the booking screen can print "Available
		// Time: 6:00 AM - 11:55 PM" without a second request.
		return new WP_REST_Response(
			array(
				'date'       => $date,
				'start_time' => $day['start_time'],
				'end_time'   => $day['end_time'],
				'slots'      => $day['slots'],
			),
			200
		);
	}

	/**
	 * Get the weekdays a doctor sits on, so the date strip can grey out the rest.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error Response object or WP_Error.
	 */
	public function get_doctor_schedule_days( $request ) {
		global $wpdb;
		$id         = intval( $request->get_param( 'id' ) );
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT m.id FROM $table_meta m 
				JOIN {$wpdb->posts} p ON m.post_id = p.ID 
				WHERE p.ID = %d AND p.post_type = 'doctors' AND m.verification_status = 'approved'",
				$id
			)
		);

		if ( ! $exists ) {
			return new WP_Error( 'eg_care_doctor_not_found', __( 'Approved doctor profile not found.', 'eg-care' ), array( 'status' => 404 ) );
		}

		$table_schedules = \EGCare\DB::get_table( 'schedules' );
		$rows            = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT day_of_week FROM $table_schedules WHERE doctor_id = %d AND is_active = 1",
				$id
			)
		);

		$days = array_map( 'intval', (array) $rows );
		sort( $days );

		// ISO weekdays: 1 = Monday ... 7 = Sunday, matching mysql2date( 'N' ).
		return new WP_REST_Response( array( 'days' => array_values( $days ) ), 200 );
	}

	/**
	 * Calculate slots for a doctor on a specific date.
	 *
	 * @param int    $doctor_id Doctor Post ID.
	 * @param string $date Date string in YYYY-MM-DD format.
	 * @return array {
	 *     @type string|null $start_time Earliest sitting time that day, H:i:s.
	 *     @type string|null $end_time   Latest sitting time that day, H:i:s.
	 *     @type array       $slots      List of slots, each with time and available.
	 * }
	 */
	private function calculate_slots( $doctor_id, $date ) {
		global $wpdb;

		// 1. Get Day of Week (1 = Monday, ..., 7 = Sunday).
		$day_of_week = intval( mysql2date( 'N', $date, false ) );

		// 2. Query Schedule Rules for this doctor on this day of week.
		$table_schedules = \EGCare\DB::get_table( 'schedules' );
		$schedules       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT start_time, end_time, slot_duration_min, break_duration_min FROM $table_schedules WHERE doctor_id = %d AND day_of_week = %d AND is_active = 1",
				$doctor_id,
				$day_of_week
			)
		);

		$empty = array(
			'start_time' => null,
			'end_time'   => null,
			'slots'      => array(),
		);

		if ( empty( $schedules ) ) {
			return $empty;
		}

		// 3. Generate candidate slots, and note the outer bounds of the day.
		$slots      = array();
		$day_start  = null;
		$day_end    = null;
		foreach ( $schedules as $rule ) {
			$start = strtotime( $date . ' ' . $rule->start_time );
			$end   = strtotime( $date . ' ' . $rule->end_time );
			
			$duration_sec = intval( $rule->slot_duration_min ) * 60;
			$break_sec    = intval( isset( $rule->break_duration_min ) ? $rule->break_duration_min : 0 ) * 60;
			$total_step   = $duration_sec + $break_sec;

			if ( $duration_sec <= 0 ) {
				continue;
			}

			// A doctor can have several rules for one day (a morning and an
			// evening sitting, say). The range shown to the patient spans them
			// all, so take the earliest start and the latest end.
			$rule_start = gmdate( 'H:i:s', $start );
			$rule_end   = gmdate( 'H:i:s', $end );
			if ( null === $day_start || $rule_start < $day_start ) {
				$day_start = $rule_start;
			}
			if ( null === $day_end || $rule_end > $day_end ) {
				$day_end = $rule_end;
			}

			for ( $time = $start; $time + $duration_sec <= $end; $time += $total_step ) {
				$slots[] = gmdate( 'H:i:s', $time );
			}
		}

		// Sort candidate slots.
		sort( $slots );
		$slots = array_unique( $slots );

		// 4. Query booked appointments for this doctor on this date.
		$table_appointments = \EGCare\DB::get_table( 'appointments' );
		$appointments       = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT appointment_time FROM $table_appointments WHERE doctor_id = %d AND appointment_date = %s AND status IN ('confirmed', 'ongoing', 'completed', 'pending_payment')",
				$doctor_id,
				$date
			)
		);

		$booked_times = array();
		foreach ( $appointments as $app ) {
			$booked_times[] = mysql2date( 'H:i:s', $app->appointment_time, false );
		}

		// 5. Compare and build result.
		$result        = array();
		$now_timestamp = current_time( 'timestamp' ); // WordPress local time.

		foreach ( $slots as $slot_time ) {
			$is_booked = in_array( $slot_time, $booked_times, true );
			$available = ! $is_booked;

			// If the target date is today, check if slot is in the past.
			if ( $available && $date === current_time( 'Y-m-d' ) ) {
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

		return array(
			'start_time' => $day_start,
			'end_time'   => $day_end,
			'slots'      => $result,
		);
	}

	/**
	 * Format doctor data structure for response.
	 *
	 * @param object   $row Database meta row.
	 * @param \WP_Post $post Post object.
	 * @param int      $patients_count How many different patients this doctor has seen.
	 * @return array Formatted response data.
	 */
	private function format_doctor( $row, $post, $patients_count = 0 ) {
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
			// The doctor's own page, so a card can link to it and the profile
			// can be shared or opened in a new tab.
			'permalink'           => get_permalink( $post ),
			'patients_count'      => intval( $patients_count ),
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
