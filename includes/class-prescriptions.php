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
	 * Where an administrator keeps a starter list of medicines.
	 */
	const MEDICINE_OPTION = 'eg_care_common_medicines';

	/**
	 * Where a doctor's own saved prescriptions live, on their user record.
	 */
	const TEMPLATES_META = '_eg_care_rx_templates';

	/**
	 * How many a doctor may keep. Enough for the cases they see every week;
	 * past that a list stops being faster than typing.
	 */
	const MAX_TEMPLATES = 20;

	/**
	 * How far back to look when working out what this doctor prescribes.
	 */
	const HISTORY_DEPTH = 120;

	/**
	 * The dose, duration and instruction values offered in the form.
	 *
	 * Writing a dose out by hand thirty times a day is the part of this job a
	 * computer should be doing, so the common ones are one tap away. Filters
	 * are here because what is common differs by practice.
	 *
	 * @return array<string,string[]>
	 */
	public static function field_options() {
		return array(
			'dose' => apply_filters(
				'eg_care_prescription_dose_options',
				array( '1+0+1', '1+1+1', '0+0+1', '1+0+0', '0+1+0', '1/2+0+1/2', 'SOS' )
			),
			'duration' => apply_filters(
				'eg_care_prescription_duration_options',
				array( '3 days', '5 days', '7 days', '10 days', '14 days', '1 month', 'Continue' )
			),
			'instruction' => apply_filters(
				'eg_care_prescription_instruction_options',
				array( 'After meals', 'Before meals', 'At bedtime', 'Empty stomach', 'With water' )
			),
		);
	}

	/**
	 * The dose values shown as one-tap buttons in each row.
	 *
	 * A short list on purpose: a row of five buttons is faster than a row of
	 * fifteen, and the rest are still in the dropdown.
	 *
	 * @return string[]
	 */
	public static function dose_chips() {
		$doses = self::field_options()['dose'];

		return array_slice( $doses, 0, 5 );
	}

	/**
	 * The starter list an administrator typed into Settings.
	 *
	 * One medicine per line. Blank lines and duplicates are dropped so that a
	 * sloppy paste does not show up as a sloppy dropdown.
	 *
	 * @return string[]
	 */
	public static function common_medicines() {
		$raw = (string) get_option( self::MEDICINE_OPTION, '' );

		if ( '' === trim( $raw ) ) {
			return array();
		}

		$names = preg_split( '/\r\n|\r|\n/', $raw );
		$out   = array();
		$seen  = array();

		foreach ( (array) $names as $name ) {
			$name = self::cap( sanitize_text_field( $name ), self::MAX_MEDICINE );
			$key  = strtolower( $name );

			if ( '' === $name || isset( $seen[ $key ] ) ) {
				continue;
			}

			$seen[ $key ] = true;
			$out[]        = $name;
		}

		return $out;
	}

	/**
	 * What this doctor has actually prescribed before.
	 *
	 * The useful suggestion list is not a drug database - it is the twenty or
	 * so things this particular doctor writes most weeks, with the dose they
	 * usually write beside each. That needs no licence, no external service,
	 * and it gets better the more the doctor uses the form.
	 *
	 * Drafts are left out: a line someone started typing and abandoned is not
	 * a prescribing decision.
	 *
	 * @param int $doctor_id Doctor CPT post id.
	 * @return array<int,array{name:string,dose:string,duration:string,instruction:string,uses:int}>
	 */
	public static function doctor_medicine_history( $doctor_id ) {
		global $wpdb;

		$table = self::table();

		$rows = $wpdb->get_col( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare(
				"SELECT content FROM $table
				  WHERE doctor_id = %d AND status IN ( %s, %s )
			   ORDER BY id DESC
				  LIMIT %d",
				intval( $doctor_id ),
				self::STATUS_SUBMITTED,
				self::STATUS_SENT,
				self::HISTORY_DEPTH
			)
		);

		$tally = array();

		foreach ( (array) $rows as $json ) {
			$decoded = json_decode( (string) $json, true );

			if ( ! is_array( $decoded ) || empty( $decoded['medicines'] ) || ! is_array( $decoded['medicines'] ) ) {
				continue;
			}

			foreach ( $decoded['medicines'] as $medicine ) {
				if ( ! is_array( $medicine ) || empty( $medicine['name'] ) ) {
					continue;
				}

				$name = self::cap( sanitize_text_field( (string) $medicine['name'] ), self::MAX_MEDICINE );
				$key  = strtolower( $name );

				if ( '' === $name ) {
					continue;
				}

				if ( ! isset( $tally[ $key ] ) ) {
					$tally[ $key ] = array(
						'name'  => $name,
						'uses'  => 0,
						'dose'  => array(),
						'durat' => array(),
						'instr' => array(),
					);
				}

				$tally[ $key ]['uses']++;

				foreach ( array( 'dose' => 'dose', 'duration' => 'durat', 'instruction' => 'instr' ) as $field => $bucket ) {
					$value = isset( $medicine[ $field ] ) && is_scalar( $medicine[ $field ] ) ? trim( (string) $medicine[ $field ] ) : '';

					if ( '' === $value ) {
						continue;
					}

					if ( ! isset( $tally[ $key ][ $bucket ][ $value ] ) ) {
						$tally[ $key ][ $bucket ][ $value ] = 0;
					}

					$tally[ $key ][ $bucket ][ $value ]++;
				}
			}
		}

		// Most prescribed first, so the dropdown opens on what they reach for.
		uasort(
			$tally,
			static function ( $a, $b ) {
				return $b['uses'] <=> $a['uses'];
			}
		);

		$out = array();

		foreach ( $tally as $entry ) {
			$out[] = array(
				'name'        => $entry['name'],
				'dose'        => self::most_common( $entry['dose'] ),
				'duration'    => self::most_common( $entry['durat'] ),
				'instruction' => self::most_common( $entry['instr'] ),
				'uses'        => $entry['uses'],
			);
		}

		return $out;
	}

	/**
	 * The most frequent value in a tally, or an empty string.
	 *
	 * Ties go to the most recent, because the rows were read newest first and
	 * so that is the order the keys went in. The loop below is deliberate
	 * rather than an arsort(): sorting only resolves a tie this way on PHP 8
	 * and later, and a doctor's suggestions should not depend on which PHP
	 * the host happens to be running.
	 *
	 * @param array<string,int> $tally Value => count, newest value first.
	 * @return string
	 */
	private static function most_common( $tally ) {
		$best  = '';
		$count = 0;

		foreach ( $tally as $value => $uses ) {
			if ( $uses > $count ) {
				$best  = (string) $value;
				$count = $uses;
			}
		}

		return $best;
	}

	/**
	 * Everything the medicine box should offer this doctor.
	 *
	 * Their own history first, then anything from the administrator's list
	 * they have not used yet. Nothing is cached: the query is one indexed read
	 * of their last hundred-odd prescriptions, and a stale suggestion list is
	 * a worse bug than a cheap query.
	 *
	 * @param int $doctor_id Doctor CPT post id.
	 * @return array<int,array{name:string,dose:string,duration:string,instruction:string}>
	 */
	public static function suggestions( $doctor_id ) {
		$out  = array();
		$seen = array();

		foreach ( self::doctor_medicine_history( $doctor_id ) as $entry ) {
			$seen[ strtolower( $entry['name'] ) ] = true;

			$out[] = array(
				'name'        => $entry['name'],
				'dose'        => $entry['dose'],
				'duration'    => $entry['duration'],
				'instruction' => $entry['instruction'],
			);
		}

		foreach ( self::common_medicines() as $name ) {
			if ( isset( $seen[ strtolower( $name ) ] ) ) {
				continue;
			}

			$out[] = array(
				'name'        => $name,
				'dose'        => '',
				'duration'    => '',
				'instruction' => '',
			);
		}

		return $out;
	}

	/**
	 * A doctor's saved prescriptions.
	 *
	 * Kept on their user record rather than in a table: it is a short list,
	 * it belongs to one person, and nothing else ever queries it.
	 *
	 * @param int $user_id WordPress user id.
	 * @return array<int,array{id:string,name:string,content:array}>
	 */
	public static function get_templates( $user_id ) {
		$stored = get_user_meta( intval( $user_id ), self::TEMPLATES_META, true );

		if ( ! is_array( $stored ) ) {
			return array();
		}

		$out = array();

		foreach ( $stored as $template ) {
			if ( ! is_array( $template ) || empty( $template['id'] ) || empty( $template['name'] ) ) {
				continue;
			}

			$out[] = array(
				'id'      => sanitize_key( $template['id'] ),
				'name'    => self::cap( sanitize_text_field( $template['name'] ), 80 ),
				'content' => self::sanitize_content( isset( $template['content'] ) ? $template['content'] : array() ),
			);
		}

		return $out;
	}

	/**
	 * Save one, replacing any template of the same name.
	 *
	 * @param int    $user_id WordPress user id.
	 * @param string $name    What the doctor called it.
	 * @param array  $content Sanitized content.
	 * @return array|\WP_Error The stored template.
	 */
	public static function save_template( $user_id, $name, $content ) {
		$name = self::cap( sanitize_text_field( (string) $name ), 80 );

		if ( '' === $name ) {
			return new \WP_Error(
				'eg_care_template_name_required',
				__( 'Give the template a name you will recognise later.', 'eg-care' ),
				array( 'status' => 400 )
			);
		}

		if ( ! self::has_substance( $content ) ) {
			return new \WP_Error(
				'eg_care_template_empty',
				__( 'There is nothing in this prescription to save yet.', 'eg-care' ),
				array( 'status' => 400 )
			);
		}

		$templates = self::get_templates( $user_id );
		$key       = strtolower( $name );
		$replaced  = false;

		foreach ( $templates as $index => $template ) {
			if ( strtolower( $template['name'] ) === $key ) {
				$templates[ $index ]['content'] = $content;
				$saved                          = $templates[ $index ];
				$replaced                       = true;
				break;
			}
		}

		if ( ! $replaced ) {
			if ( count( $templates ) >= self::MAX_TEMPLATES ) {
				return new \WP_Error(
					'eg_care_template_limit',
					sprintf(
						/* translators: %d: how many templates are allowed. */
						__( 'You already have %d saved prescriptions. Delete one to make room.', 'eg-care' ),
						self::MAX_TEMPLATES
					),
					array( 'status' => 400 )
				);
			}

			$saved = array(
				'id'      => sanitize_key( uniqid( 'rx', false ) ),
				'name'    => $name,
				'content' => $content,
			);

			$templates[] = $saved;
		}

		update_user_meta( intval( $user_id ), self::TEMPLATES_META, $templates );

		return $saved;
	}

	/**
	 * Remove one.
	 *
	 * @param int    $user_id WordPress user id.
	 * @param string $id      Template id.
	 * @return bool Whether anything was removed.
	 */
	public static function delete_template( $user_id, $id ) {
		$id        = sanitize_key( $id );
		$templates = self::get_templates( $user_id );
		$kept      = array();

		foreach ( $templates as $template ) {
			if ( $template['id'] !== $id ) {
				$kept[] = $template;
			}
		}

		if ( count( $kept ) === count( $templates ) ) {
			return false;
		}

		update_user_meta( intval( $user_id ), self::TEMPLATES_META, $kept );

		return true;
	}

	/**
	 * The capability a reviewer needs.
	 *
	 * Administrators only for now. A dedicated moderator role would be a few
	 * lines in Roles plus a different string here, and nothing else would
	 * change, because every review screen asks this one question.
	 *
	 * @return bool
	 */
	public static function can_review() {
		/**
		 * Filters the capability required to review a prescription.
		 *
		 * @param string $capability Capability name.
		 */
		$capability = apply_filters( 'eg_care_prescription_review_capability', 'manage_options' );

		return current_user_can( $capability );
	}

	/**
	 * Whether a reviewer may act on a prescription in this status.
	 *
	 * Only one that has actually been handed over. A draft still belongs to
	 * the doctor, a returned one is back with them, and a sent one is the
	 * patient's record.
	 *
	 * @param string $status Status.
	 * @return bool
	 */
	public static function is_reviewable( $status ) {
		return self::STATUS_SUBMITTED === $status;
	}

	/**
	 * Release a prescription to the patient.
	 *
	 * The reviewer's corrections, if they made any, are saved as part of the
	 * same step - approving one thing and storing another is the failure mode
	 * worth designing out.
	 *
	 * @param int        $id      Prescription id.
	 * @param array|null $content Sanitized content to store first, or null to
	 *                            approve what the doctor wrote unchanged.
	 * @return true|\WP_Error
	 */
	public static function approve( $id, $content = null ) {
		global $wpdb;

		$row = self::get( $id );

		if ( ! $row ) {
			return new \WP_Error( 'eg_care_prescription_missing', __( 'That prescription no longer exists.', 'eg-care' ), array( 'status' => 404 ) );
		}

		if ( ! self::is_reviewable( $row->status ) ) {
			return new \WP_Error(
				'eg_care_prescription_not_reviewable',
				self::STATUS_SENT === $row->status
					? __( 'This prescription has already been sent to the patient.', 'eg-care' )
					: __( 'This prescription is not waiting for review.', 'eg-care' ),
				array( 'status' => 409 )
			);
		}

		$fields = array(
			'status'         => self::STATUS_SENT,
			'moderator_note' => null,
			'reviewed_by'    => get_current_user_id(),
			'reviewed_at'    => current_time( 'mysql' ),
			'sent_at'        => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
		);
		$formats = array( '%s', '%s', '%d', '%s', '%s', '%s' );

		if ( null !== $content ) {
			$encoded = wp_json_encode( $content );

			if ( false === $encoded ) {
				return new \WP_Error( 'eg_care_prescription_encode_failed', __( 'The prescription could not be saved.', 'eg-care' ), array( 'status' => 500 ) );
			}

			if ( ! self::has_substance( $content ) ) {
				return new \WP_Error(
					'eg_care_prescription_empty',
					__( 'A prescription needs a diagnosis or at least one medicine before it can be sent.', 'eg-care' ),
					array( 'status' => 400 )
				);
			}

			$fields['content'] = $encoded;
			$formats[]         = '%s';
		}

		$ok = $wpdb->update( self::table(), $fields, array( 'id' => intval( $row->id ) ), $formats, array( '%d' ) );

		if ( false === $ok ) {
			return new \WP_Error( 'eg_care_prescription_save_failed', __( 'The prescription could not be approved.', 'eg-care' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires when a prescription is released to the patient.
		 *
		 * Delivery - the printable page and the email - hangs off this, so
		 * nothing in the review screen needs to know how a patient is reached.
		 *
		 * @param int    $prescription_id Prescription id.
		 * @param object $row             The row as it was before approval.
		 */
		do_action( 'eg_care_prescription_sent', intval( $row->id ), $row );

		return true;
	}

	/**
	 * Hand a prescription back to the doctor with a reason.
	 *
	 * The note is required. "Rejected" with no reason gives the doctor nothing
	 * to act on and guarantees a second round trip.
	 *
	 * @param int    $id   Prescription id.
	 * @param string $note What needs changing.
	 * @return true|\WP_Error
	 */
	public static function send_back( $id, $note ) {
		global $wpdb;

		$row = self::get( $id );

		if ( ! $row ) {
			return new \WP_Error( 'eg_care_prescription_missing', __( 'That prescription no longer exists.', 'eg-care' ), array( 'status' => 404 ) );
		}

		if ( ! self::is_reviewable( $row->status ) ) {
			return new \WP_Error(
				'eg_care_prescription_not_reviewable',
				__( 'This prescription is not waiting for review.', 'eg-care' ),
				array( 'status' => 409 )
			);
		}

		$note = self::cap( sanitize_textarea_field( (string) $note ), self::MAX_FREETEXT );

		if ( '' === $note ) {
			return new \WP_Error(
				'eg_care_prescription_note_required',
				__( 'Tell the doctor what needs changing before sending it back.', 'eg-care' ),
				array( 'status' => 400 )
			);
		}

		$ok = $wpdb->update(
			self::table(),
			array(
				'status'         => self::STATUS_RETURNED,
				'moderator_note' => $note,
				'reviewed_by'    => get_current_user_id(),
				'reviewed_at'    => current_time( 'mysql' ),
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => intval( $row->id ) ),
			array( '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $ok ) {
			return new \WP_Error( 'eg_care_prescription_save_failed', __( 'The prescription could not be returned.', 'eg-care' ), array( 'status' => 500 ) );
		}

		/**
		 * Fires when a prescription goes back to the doctor.
		 *
		 * @param int    $prescription_id Prescription id.
		 * @param string $note            The reviewer's note.
		 */
		do_action( 'eg_care_prescription_returned', intval( $row->id ), $note );

		return true;
	}

	/**
	 * How many prescriptions sit in each status.
	 *
	 * One grouped query, used for the counts beside the review screen's view
	 * links.
	 *
	 * @return array<string,int>
	 */
	public static function status_counts() {
		global $wpdb;

		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT status, COUNT(id) AS total FROM $table GROUP BY status" ); // phpcs:ignore WordPress.DB.PreparedSQL

		$counts = array_fill_keys( self::statuses(), 0 );

		foreach ( (array) $rows as $row ) {
			if ( isset( $counts[ $row->status ] ) ) {
				$counts[ $row->status ] = intval( $row->total );
			}
		}

		return $counts;
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
