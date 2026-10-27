<?php
/**
 * Prescriptions - the record a doctor writes after a consultation.
 *
 * A prescription is never handed to the patient by the doctor. It is written
 * here, held until a moderator has read it, and only then released. That one
 * rule is what this class exists to enforce, so the status lives in the
 * database rather than in whichever screen happens to be asking.
 *
 *   draft      the doctor is still writing; auto-saved, nobody else sees it
 *   submitted  waiting for a moderator
 *   returned   a moderator sent it back with a note; the doctor can edit again
 *   sent       approved and released to the patient
 *
 * Only `sent` is visible to a patient. Phase 1 fills in draft and submitted;
 * the two after them are written by the review screen that comes next, and the
 * columns are here already so there is no second migration to run later.
 *
 * @package EG Care
 */

namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Prescriptions
 */
class Prescriptions {

	/**
	 * Status values. These are the enum in the table; nothing else is valid.
	 */
	const STATUS_DRAFT     = 'draft';
	const STATUS_SUBMITTED = 'submitted';
	const STATUS_RETURNED  = 'returned';
	const STATUS_SENT      = 'sent';

	/**
	 * How many medicine rows one prescription may carry.
	 *
	 * Generous for real use and small enough that a broken client cannot post
	 * a megabyte of rows.
	 */
	const MAX_MEDICINES = 30;

	/**
	 * Field length caps, in characters.
	 */
	const MAX_FREETEXT    = 2000;
	const MAX_MEDICINE    = 200;
	const MAX_SHORT_FIELD = 100;
	const MAX_INSTRUCTION = 300;

	/**
	 * The prescriptions table.
	 *
	 * @return string
	 */
	public static function table() {
		return \EGCare\DB::get_table( 'prescriptions' );
	}

	/**
	 * Every status, for validation.
	 *
	 * @return string[]
	 */
	public static function statuses() {
		return array(
			self::STATUS_DRAFT,
			self::STATUS_SUBMITTED,
			self::STATUS_RETURNED,
			self::STATUS_SENT,
		);
	}

	/**
	 * Whether the doctor may still change a prescription in this status.
	 *
	 * Once it is submitted the doctor is out of it - a moderator is reading it
	 * - and once sent it is the patient's record and never changes in place.
	 *
	 * @param string $status Status to test.
	 * @return bool
	 */
	public static function is_editable( $status ) {
		return in_array( $status, array( self::STATUS_DRAFT, self::STATUS_RETURNED ), true );
	}

	/**
	 * Whether the patient may see a prescription in this status.
	 *
	 * The whole feature rests on this answering false for everything but
	 * `sent`, so it is one function and every screen asks it.
	 *
	 * @param string $status Status to test.
	 * @return bool
	 */
	public static function is_visible_to_patient( $status ) {
		return self::STATUS_SENT === $status;
	}

	/**
	 * Which appointment statuses a prescription may be written against.
	 *
	 * `ongoing` is deliberate: a doctor typing while the call is still running
	 * is the normal case, not an edge case.
	 *
	 * @return string[]
	 */
	public static function writable_appointment_statuses() {
		return array( 'ongoing', 'completed' );
	}

	/**
	 * An empty prescription, so the form and the database agree on the shape.
	 *
	 * @return array
	 */
	public static function empty_content() {
		return array(
			'complaints'    => '',
			'diagnosis'     => '',
			'medicines'     => array(),
			'advice'        => '',
			'followup_date' => '',
		);
	}

	/**
	 * Clean whatever arrived from the browser into the stored shape.
	 *
	 * Unknown keys are dropped rather than carried along, so a client cannot
	 * smuggle fields into the record by inventing them.
	 *
	 * @param mixed $raw Decoded JSON, or an array.
	 * @return array
	 */
	public static function sanitize_content( $raw ) {
		$clean = self::empty_content();

		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}

		if ( ! is_array( $raw ) ) {
			return $clean;
		}

		foreach ( array( 'complaints', 'diagnosis', 'advice' ) as $field ) {
			if ( isset( $raw[ $field ] ) && is_scalar( $raw[ $field ] ) ) {
				$clean[ $field ] = self::cap(
					sanitize_textarea_field( (string) $raw[ $field ] ),
					self::MAX_FREETEXT
				);
			}
		}

		if ( isset( $raw['followup_date'] ) && is_scalar( $raw['followup_date'] ) ) {
			$clean['followup_date'] = self::clean_date( (string) $raw['followup_date'] );
		}

		if ( isset( $raw['medicines'] ) && is_array( $raw['medicines'] ) ) {
			foreach ( $raw['medicines'] as $row ) {
				if ( count( $clean['medicines'] ) >= self::MAX_MEDICINES ) {
					break;
				}

				if ( ! is_array( $row ) ) {
					continue;
				}

				$name = isset( $row['name'] ) && is_scalar( $row['name'] )
					? self::cap( sanitize_text_field( (string) $row['name'] ), self::MAX_MEDICINE )
					: '';

				// A row with no medicine on it is a row the doctor started and
				// left; it is not part of the prescription.
				if ( '' === $name ) {
					continue;
				}

				$clean['medicines'][] = array(
					'name'        => $name,
					'dose'        => self::field( $row, 'dose', self::MAX_SHORT_FIELD ),
					'duration'    => self::field( $row, 'duration', self::MAX_SHORT_FIELD ),
					'instruction' => self::field( $row, 'instruction', self::MAX_INSTRUCTION ),
				);
			}
		}

		return $clean;
	}

	/**
	 * One sanitized, length-capped string field out of a row.
	 *
	 * @param array  $row   Source row.
	 * @param string $key   Key to read.
	 * @param int    $limit Character cap.
	 * @return string
	 */
	private static function field( $row, $key, $limit ) {
		if ( ! isset( $row[ $key ] ) || ! is_scalar( $row[ $key ] ) ) {
			return '';
		}

		return self::cap( sanitize_text_field( (string) $row[ $key ] ), $limit );
	}

	/**
	 * Trim a string to a character count, counting characters and not bytes.
	 *
	 * @param string $value Value.
	 * @param int    $limit Characters.
	 * @return string
	 */
	private static function cap( $value, $limit ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $limit );
		}

		return substr( $value, 0, $limit );
	}

	/**
	 * A real Y-m-d date, or an empty string.
	 *
	 * @param string $value Candidate.
	 * @return string
	 */
	private static function clean_date( $value ) {
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		$parts = explode( '-', $value );

		if ( 3 !== count( $parts ) ) {
			return '';
		}

		list( $year, $month, $day ) = array_map( 'intval', $parts );

		if ( ! checkdate( $month, $day, $year ) ) {
			return '';
		}

		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}

	/**
	 * Whether a prescription has enough on it to be worth a moderator's time.
	 *
	 * A diagnosis or at least one medicine. Advice alone is not a
	 * prescription, and an empty one submitted by a misclick wastes a review.
	 *
	 * @param array $content Sanitized content.
	 * @return bool
	 */
	public static function has_substance( $content ) {
		if ( ! empty( $content['medicines'] ) ) {
			return true;
		}

		return isset( $content['diagnosis'] ) && '' !== trim( (string) $content['diagnosis'] );
	}

	/**
	 * Read a prescription row by id.
	 *
	 * @param int $id Row id.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;

		$table = self::table();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", intval( $id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * The prescription attached to an appointment, if there is one.
	 *
	 * Phase 1 keeps one row per appointment. The query orders anyway, so that
	 * revisions can be added later without every caller changing.
	 *
	 * @param int $appointment_id Appointment id.
	 * @return object|null
	 */
	public static function get_for_appointment( $appointment_id ) {
		global $wpdb;

		$table = self::table();

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT * FROM $table WHERE appointment_id = %d ORDER BY id DESC LIMIT 1",
				intval( $appointment_id )
			)
		);
	}

	/**
	 * Decode a stored row's content back into the array shape.
	 *
	 * @param object|null $row Prescription row.
	 * @return array
	 */
	public static function content_of( $row ) {
		if ( ! $row || ! isset( $row->content ) ) {
			return self::empty_content();
		}

		$decoded = json_decode( (string) $row->content, true );

		// Re-sanitizing on the way out costs little and means a row written by
		// an older version of this code still comes back in today's shape.
		return self::sanitize_content( $decoded );
	}

	/**
	 * Whether this doctor owns this appointment, and it is in a state that
	 * accepts a prescription.
	 *
	 * @param int $appointment_id Appointment id.
	 * @param int $doctor_id      Doctor CPT post id.
	 * @return object|null The appointment row, or null.
	 */
	public static function writable_appointment( $appointment_id, $doctor_id ) {
		global $wpdb;

		$table = \EGCare\DB::get_table( 'appointments' );

		$appointment = $wpdb->get_row( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT * FROM $table WHERE id = %d AND doctor_id = %d",
				intval( $appointment_id ),
				intval( $doctor_id )
			)
		);

		if ( ! $appointment ) {
			return null;
		}

		if ( ! in_array( $appointment->status, self::writable_appointment_statuses(), true ) ) {
			return null;
		}

		return $appointment;
	}

	/**
	 * Create or update the draft for an appointment.
	 *
	 * @param object $appointment Appointment row, already checked.
	 * @param array  $content     Sanitized content.
	 * @return array{id:int,status:string}|\WP_Error
	 */
	public static function save_draft( $appointment, $content ) {
		global $wpdb;

		$table    = self::table();
		$existing = self::get_for_appointment( $appointment->id );
		$now      = current_time( 'mysql' );

		if ( $existing && ! self::is_editable( $existing->status ) ) {
			return new \WP_Error(
				'eg_care_prescription_locked',
				self::STATUS_SENT === $existing->status
					? __( 'This prescription has already been sent to the patient and cannot be changed.', 'eg-care' )
					: __( 'This prescription is with the reviewer and cannot be changed right now.', 'eg-care' ),
				array( 'status' => 409 )
			);
		}

		$encoded = wp_json_encode( $content );

		if ( false === $encoded ) {
			return new \WP_Error(
				'eg_care_prescription_encode_failed',
				__( 'The prescription could not be saved. Please try again.', 'eg-care' ),
				array( 'status' => 500 )
			);
		}

		if ( $existing ) {
			$ok = $wpdb->update(
				$table,
				array(
					'content'    => $encoded,
					'updated_at' => $now,
				),
				array( 'id' => intval( $existing->id ) ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			if ( false === $ok ) {
				return new \WP_Error( 'eg_care_prescription_save_failed', __( 'The prescription could not be saved.', 'eg-care' ), array( 'status' => 500 ) );
			}

			return array(
				'id'     => intval( $existing->id ),
				'status' => $existing->status,
			);
		}

		$ok = $wpdb->insert(
			$table,
			array(
				'appointment_id'  => intval( $appointment->id ),
				'doctor_id'       => intval( $appointment->doctor_id ),
				'patient_user_id' => $appointment->patient_user_id ? intval( $appointment->patient_user_id ) : null,
				'status'          => self::STATUS_DRAFT,
				'content'         => $encoded,
				'created_at'      => $now,
				'updated_at'      => $now,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			return new \WP_Error( 'eg_care_prescription_save_failed', __( 'The prescription could not be saved.', 'eg-care' ), array( 'status' => 500 ) );
		}

		return array(
			'id'     => intval( $wpdb->insert_id ),
			'status' => self::STATUS_DRAFT,
		);
	}

	/**
	 * Hand a draft to the reviewers.
	 *
	 * @param int $id Prescription id.
	 * @return true|\WP_Error
	 */
	public static function submit( $id ) {
		global $wpdb;

		$row = self::get( $id );

		if ( ! $row ) {
			return new \WP_Error( 'eg_care_prescription_missing', __( 'That prescription no longer exists.', 'eg-care' ), array( 'status' => 404 ) );
		}

		if ( ! self::is_editable( $row->status ) ) {
			return new \WP_Error(
				'eg_care_prescription_locked',
				__( 'This prescription has already been submitted.', 'eg-care' ),
				array( 'status' => 409 )
			);
		}

		if ( ! self::has_substance( self::content_of( $row ) ) ) {
			return new \WP_Error(
				'eg_care_prescription_empty',
				__( 'Add a diagnosis or at least one medicine before submitting.', 'eg-care' ),
				array( 'status' => 400 )
			);
		}

		$ok = $wpdb->update(
			self::table(),
			array(
				'status'         => self::STATUS_SUBMITTED,
				'moderator_note' => null,
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => intval( $row->id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $ok ) {
			return new \WP_Error( 'eg_care_prescription_save_failed', __( 'The prescription could not be submitted.', 'eg-care' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires once a prescription is waiting for review.
		 *
		 * The review screen and its notifications hang off this in the next
		 * phase, so nothing here needs to know they exist.
		 *
		 * @param int    $prescription_id Prescription id.
		 * @param object $row             The row as it was before submission.
		 */
		do_action( 'eg_care_prescription_submitted', intval( $row->id ), $row );

		return true;
	}

	/**
	 * A short label for a status, for the doctor's screen.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = array(
			self::STATUS_DRAFT     => __( 'Draft', 'eg-care' ),
			self::STATUS_SUBMITTED => __( 'Waiting for review', 'eg-care' ),
			self::STATUS_RETURNED  => __( 'Returned for changes', 'eg-care' ),
			self::STATUS_SENT      => __( 'Sent to patient', 'eg-care' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}
}
