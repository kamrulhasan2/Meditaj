<?php
namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The people a patient books consultations for.
 *
 * Every method here takes the owning user id and filters on it in SQL rather
 * than loading a row and checking afterwards, so a guessed id can never reach
 * another account's records.
 */
class FamilyMembers {

	/**
	 * Relations a member may be saved under.
	 */
	const RELATIONS = array( 'self', 'father', 'mother', 'spouse', 'son', 'daughter', 'brother', 'sister', 'other' );

	/**
	 * Accepted gender values. Empty is allowed and stored as NULL.
	 */
	const GENDERS = array( 'male', 'female', 'other' );

	/**
	 * Oldest date of birth accepted, guarding against typos like 0190-01-01.
	 */
	const MAX_AGE_YEARS = 120;

	/**
	 * Table name.
	 *
	 * @return string
	 */
	private static function table() {
		return DB::get_table( 'family_members' );
	}

	/**
	 * Every member belonging to a user, self first, then alphabetically.
	 *
	 * @param int $user_id Owner.
	 * @return array List of shaped rows.
	 */
	public static function all_for_user( $user_id ) {
		global $wpdb;

		$table = self::table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM $table WHERE user_id = %d ORDER BY ( relation = 'self' ) DESC, name ASC",
				$user_id
			)
		);

		return array_map( array( __CLASS__, 'shape' ), (array) $rows );
	}

	/**
	 * One member, but only if this user owns it.
	 *
	 * @param int $id      Member id.
	 * @param int $user_id Owner.
	 * @return object|null Raw row, or null when it is missing or not theirs.
	 */
	public static function find_owned( $id, $user_id ) {
		global $wpdb;

		$table = self::table();

		return $wpdb->get_row(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM $table WHERE id = %d AND user_id = %d",
				$id,
				$user_id
			)
		);
	}

	/**
	 * Validate and normalise incoming member fields.
	 *
	 * @param array $input Raw values.
	 * @return array|\WP_Error Clean values ready for the database.
	 */
	public static function sanitize( array $input ) {
		$name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';
		$name = trim( $name );

		if ( '' === $name ) {
			return new \WP_Error( 'eg_care_family_name', __( 'A name is required.', 'eg-care' ), array( 'status' => 400 ) );
		}

		$relation = isset( $input['relation'] ) ? sanitize_key( $input['relation'] ) : '';

		if ( ! in_array( $relation, self::RELATIONS, true ) ) {
			return new \WP_Error( 'eg_care_family_relation', __( 'Choose how this person is related to you.', 'eg-care' ), array( 'status' => 400 ) );
		}

		$dob = isset( $input['date_of_birth'] ) ? sanitize_text_field( $input['date_of_birth'] ) : '';
		$dob = '' === $dob ? null : $dob;

		if ( null !== $dob ) {
			// The leading '!' zeroes the fields the format does not set. Without
			// it createFromFormat() fills in the current clock time, and a baby
			// born this morning reads as a future date against midnight today.
			$parsed = \DateTime::createFromFormat( '!Y-m-d', $dob );

			if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $dob ) {
				return new \WP_Error( 'eg_care_family_dob', __( 'Date of birth must look like YYYY-MM-DD.', 'eg-care' ), array( 'status' => 400 ) );
			}

			$today = new \DateTime( current_time( 'Y-m-d' ) );

			if ( $parsed > $today ) {
				return new \WP_Error( 'eg_care_family_dob', __( 'Date of birth cannot be in the future.', 'eg-care' ), array( 'status' => 400 ) );
			}

			if ( $parsed->diff( $today )->y > self::MAX_AGE_YEARS ) {
				return new \WP_Error( 'eg_care_family_dob', __( 'Please check the date of birth.', 'eg-care' ), array( 'status' => 400 ) );
			}
		}

		$gender = isset( $input['gender'] ) ? sanitize_key( $input['gender'] ) : '';
		$gender = in_array( $gender, self::GENDERS, true ) ? $gender : null;

		$phone = isset( $input['phone'] ) ? sanitize_text_field( $input['phone'] ) : '';
		$phone = '' === trim( $phone ) ? null : trim( $phone );

		$email = isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '';

		if ( '' !== $email && ! is_email( $email ) ) {
			return new \WP_Error( 'eg_care_family_email', __( 'That email address does not look right.', 'eg-care' ), array( 'status' => 400 ) );
		}

		return array(
			'name'          => $name,
			'relation'      => $relation,
			'date_of_birth' => $dob,
			'gender'        => $gender,
			'phone'         => $phone,
			'email'         => '' === $email ? null : $email,
		);
	}

	/**
	 * Create a member.
	 *
	 * @param int   $user_id Owner.
	 * @param array $fields  Output of sanitize().
	 * @return int|\WP_Error New id.
	 */
	public static function create( $user_id, array $fields ) {
		global $wpdb;

		// One 'self' per account: it is the patient themselves, and a second one
		// would make the booking form ambiguous.
		if ( 'self' === $fields['relation'] && self::has_self( $user_id ) ) {
			return new \WP_Error( 'eg_care_family_self_exists', __( 'You already have a "Self" entry. Edit that one instead.', 'eg-care' ), array( 'status' => 409 ) );
		}

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			self::table(),
			array_merge(
				$fields,
				array(
					'user_id'    => $user_id,
					'created_at' => $now,
					'updated_at' => $now,
				)
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( ! $inserted ) {
			return new \WP_Error( 'eg_care_family_insert', __( 'Could not save this person. Please try again.', 'eg-care' ), array( 'status' => 500 ) );
		}

		return intval( $wpdb->insert_id );
	}

	/**
	 * Update a member the user owns.
	 *
	 * @param int   $id      Member id.
	 * @param int   $user_id Owner.
	 * @param array $fields  Output of sanitize().
	 * @return true|\WP_Error
	 */
	public static function update( $id, $user_id, array $fields ) {
		global $wpdb;

		$existing = self::find_owned( $id, $user_id );

		if ( ! $existing ) {
			return new \WP_Error( 'eg_care_family_not_found', __( 'That person is not on your list.', 'eg-care' ), array( 'status' => 404 ) );
		}

		if ( 'self' === $fields['relation'] && 'self' !== $existing->relation && self::has_self( $user_id ) ) {
			return new \WP_Error( 'eg_care_family_self_exists', __( 'You already have a "Self" entry.', 'eg-care' ), array( 'status' => 409 ) );
		}

		$updated = $wpdb->update(
			self::table(),
			array_merge( $fields, array( 'updated_at' => current_time( 'mysql' ) ) ),
			array(
				'id'      => $id,
				'user_id' => $user_id,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d', '%d' )
		);

		if ( false === $updated ) {
			return new \WP_Error( 'eg_care_family_update', __( 'Could not save the changes. Please try again.', 'eg-care' ), array( 'status' => 500 ) );
		}

		return true;
	}

	/**
	 * Delete a member the user owns.
	 *
	 * Past appointments keep the name, age and relation that were copied onto
	 * them at booking time, so removing someone here never rewrites history.
	 *
	 * @param int $id      Member id.
	 * @param int $user_id Owner.
	 * @return true|\WP_Error
	 */
	public static function delete( $id, $user_id ) {
		global $wpdb;

		$deleted = $wpdb->delete(
			self::table(),
			array(
				'id'      => $id,
				'user_id' => $user_id,
			),
			array( '%d', '%d' )
		);

		if ( ! $deleted ) {
			return new \WP_Error( 'eg_care_family_not_found', __( 'That person is not on your list.', 'eg-care' ), array( 'status' => 404 ) );
		}

		return true;
	}

	/**
	 * Whether the user already has a 'self' row.
	 *
	 * @param int $user_id Owner.
	 * @return bool
	 */
	private static function has_self( $user_id ) {
		global $wpdb;

		$table = self::table();

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT id FROM $table WHERE user_id = %d AND relation = 'self' LIMIT 1",
				$user_id
			)
		);
	}

	/**
	 * Whole years between a date of birth and today.
	 *
	 * @param string|null $dob Date of birth, Y-m-d.
	 * @return int|null Age, or null when no date is stored.
	 */
	public static function age_from_dob( $dob ) {
		if ( empty( $dob ) || '0000-00-00' === $dob ) {
			return null;
		}

		try {
			$birth = new \DateTime( $dob );
			$today = new \DateTime( current_time( 'Y-m-d' ) );
		} catch ( \Exception $e ) {
			return null;
		}

		if ( $birth > $today ) {
			return null;
		}

		return $birth->diff( $today )->y;
	}

	/**
	 * Shape a row for the REST response.
	 *
	 * @param object $row Database row.
	 * @return array
	 */
	public static function shape( $row ) {
		return array(
			'id'            => intval( $row->id ),
			'name'          => $row->name,
			'relation'      => $row->relation,
			'date_of_birth' => $row->date_of_birth,
			'age'           => self::age_from_dob( $row->date_of_birth ),
			'gender'        => $row->gender,
			'phone'         => $row->phone,
			'email'         => $row->email,
		);
	}
}
