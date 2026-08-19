<?php
namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles database schema creation and updates.
 */
class DB {
	/**
	 * Schema version option name.
	 */
	const DB_VERSION_OPTION = 'eg_care_db_version';

	/**
	 * Current database schema version.
	 */
	const DB_VERSION = '1.3.0';

	/**
	 * Get custom table names.
	 *
	 * @return array Map of table identifiers to full prefixed table names.
	 */
	public static function get_tables() {
		global $wpdb;
		return array(
			'doctors_meta' => $wpdb->prefix . 'eg_care_doctors_meta',
			'schedules'    => $wpdb->prefix . 'eg_care_schedules',
			'appointments' => $wpdb->prefix . 'eg_care_appointments',
			'reviews'      => $wpdb->prefix . 'eg_care_reviews',
			'transactions' => $wpdb->prefix . 'eg_care_transactions',
		);
	}

	/**
	 * Get a specific table name.
	 *
	 * @param string $table_key Table key.
	 * @return string Full prefixed table name.
	 */
	public static function get_table( $table_key ) {
		$tables = self::get_tables();
		return isset( $tables[ $table_key ] ) ? $tables[ $table_key ] : '';
	}

	/**
	 * Create or update custom tables using dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$tables          = self::get_tables();

		$sql = array();

		// 1. wp_eg_care_doctors_meta
		$sql[] = "CREATE TABLE {$tables['doctors_meta']} (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  post_id bigint(20) NOT NULL,
  user_id bigint(20) NOT NULL,
  provider_type varchar(50) DEFAULT 'doctor' NOT NULL,
  bmdc_license_no varchar(50) NOT NULL,
  bmdc_expiry_date date DEFAULT NULL,
  degree varchar(255) NOT NULL,
  designation varchar(255) NOT NULL,
  consultation_fee decimal(10,2) NOT NULL,
  instant_call_fee decimal(10,2) NOT NULL,
  experience_years int(11) NOT NULL,
  is_online tinyint(1) DEFAULT 0 NOT NULL,
  avg_rating decimal(2,1) DEFAULT 0.0 NOT NULL,
  total_reviews int(11) DEFAULT 0 NOT NULL,
  verification_status enum('pending','approved','rejected') DEFAULT 'pending' NOT NULL,
  mobile varchar(20) DEFAULT NULL,
  nid varchar(50) DEFAULT NULL,
  nationality varchar(100) DEFAULT NULL,
  organization varchar(255) DEFAULT NULL,
  follow_up_days int(11) DEFAULT 0 NOT NULL,
  follow_up_cost decimal(10,2) DEFAULT 0.00 NOT NULL,
  bank_account_name varchar(255) DEFAULT NULL,
  bank_account_no varchar(100) DEFAULT NULL,
  bank_branch_name varchar(255) DEFAULT NULL,
  bank_routing_number varchar(50) DEFAULT NULL,
  mobile_banking_type enum('bkash','nagad','rocket') DEFAULT NULL,
  mobile_banking_no varchar(20) DEFAULT NULL,
  certificate_files text NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY post_id (post_id),
  KEY user_id (user_id),
  KEY verification_status (verification_status),
  KEY is_online (is_online),
  KEY fee_verification (consultation_fee, verification_status)
) $charset_collate;";

		// 2. wp_eg_care_schedules
		$sql[] = "CREATE TABLE {$tables['schedules']} (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  doctor_id bigint(20) NOT NULL,
  day_of_week tinyint(4) NOT NULL,
  start_time time NOT NULL,
  end_time time NOT NULL,
  slot_duration_min int(11) NOT NULL,
  break_duration_min int(11) DEFAULT 0 NOT NULL,
  is_active tinyint(1) DEFAULT 1 NOT NULL,
  PRIMARY KEY  (id),
  KEY doctor_id (doctor_id)
) $charset_collate;";

		// 3. wp_eg_care_appointments
		$sql[] = "CREATE TABLE {$tables['appointments']} (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  doctor_id bigint(20) NOT NULL,
  patient_user_id bigint(20) DEFAULT NULL,
  family_member_name varchar(255) DEFAULT NULL,
  family_member_age int(11) DEFAULT NULL,
  family_member_relation varchar(100) DEFAULT NULL,
  appointment_type enum('instant','scheduled') NOT NULL,
  appointment_date date DEFAULT NULL,
  appointment_time time DEFAULT NULL,
  status enum('pending_payment','confirmed','ongoing','completed','cancelled','no_show') DEFAULT 'pending_payment' NOT NULL,
  payment_status enum('unpaid','paid','refunded') DEFAULT 'unpaid' NOT NULL,
  payment_method enum('bkash','nagad','sslcommerz','manual') DEFAULT NULL,
  transaction_id varchar(100) DEFAULT NULL,
  amount decimal(10,2) NOT NULL,
  video_room_id varchar(100) DEFAULT NULL,
  video_token_patient text DEFAULT NULL,
  video_token_doctor text DEFAULT NULL,
  uploaded_files text DEFAULT NULL,
  symptom_notes text DEFAULT NULL,
  reminder_sent_at datetime DEFAULT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY doctor_id (doctor_id),
  KEY patient_user_id (patient_user_id),
  KEY status (status),
  KEY doctor_slot (doctor_id, appointment_date, appointment_time)
) $charset_collate;";

		// 4. wp_eg_care_reviews
		$sql[] = "CREATE TABLE {$tables['reviews']} (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  appointment_id bigint(20) NOT NULL,
  doctor_id bigint(20) NOT NULL,
  patient_user_id bigint(20) NOT NULL,
  rating tinyint(4) NOT NULL,
  comment text NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY appointment_id (appointment_id),
  KEY doctor_id (doctor_id),
  KEY patient_user_id (patient_user_id)
) $charset_collate;";

		// 5. wp_eg_care_transactions
		$sql[] = "CREATE TABLE {$tables['transactions']} (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  appointment_id bigint(20) NOT NULL,
  doctor_id bigint(20) NOT NULL,
  type enum('booking_payment','doctor_payout','refund') NOT NULL,
  gateway varchar(50) NOT NULL,
  gateway_txn_id varchar(100) NOT NULL,
  amount decimal(10,2) NOT NULL,
  status enum('pending','success','failed') NOT NULL,
  raw_payload longtext NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY appointment_id (appointment_id),
  KEY doctor_id (doctor_id),
  KEY gateway_txn_id (gateway_txn_id),
  KEY status (status),
  KEY created_at (created_at)
) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Reshape indexes first. dbDelta only ever adds indexes, so if it met a
		// name that already exists it would emit a duplicate-key error and leave
		// the old definition in place.
		self::upgrade_indexes( $tables );

		foreach ( $sql as $query ) {
			dbDelta( $query );
		}

		// Self-healing: Check if break_duration_min column exists, if not, add it.
		$schedules_table = $tables['schedules'];
		$has_break_col   = $wpdb->get_results( "SHOW COLUMNS FROM `$schedules_table` LIKE 'break_duration_min'" );
		if ( empty( $has_break_col ) ) {
			$wpdb->query( "ALTER TABLE `$schedules_table` ADD COLUMN `break_duration_min` int(11) DEFAULT 0 NOT NULL AFTER `slot_duration_min`" );
		}

		// dbDelta will not relax a NOT NULL column, so these four need a hand.
		// Reading the layout once and altering only what is still NOT NULL beats
		// four unconditional ALTERs, each of which locks the table.
		$nullable_columns = array(
			'bank_account_name'   => 'varchar(255) DEFAULT NULL',
			'bank_account_no'     => 'varchar(100) DEFAULT NULL',
			'mobile_banking_type' => "enum('bkash','nagad','rocket') DEFAULT NULL",
			'mobile_banking_no'   => 'varchar(20) DEFAULT NULL',
		);

		$meta_table    = $tables['doctors_meta'];
		$meta_columns  = $wpdb->get_results( "SHOW COLUMNS FROM `$meta_table`" );
		$accepts_null  = array();

		foreach ( (array) $meta_columns as $meta_column ) {
			$accepts_null[ $meta_column->Field ] = ( 'YES' === $meta_column->Null );
		}

		foreach ( $nullable_columns as $column_name => $definition ) {
			if ( isset( $accepts_null[ $column_name ] ) && ! $accepts_null[ $column_name ] ) {
				$wpdb->query( "ALTER TABLE `$meta_table` MODIFY $column_name $definition" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
		}

		self::migrate_reminder_flags( $tables['appointments'] );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Whether a table is already present.
	 *
	 * @param string $table Prefixed table name.
	 * @return bool
	 */
	private static function table_exists( $table ) {
		global $wpdb;

		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	/**
	 * Index names on a table mapped to whether each one is unique.
	 *
	 * @param string $table Prefixed table name.
	 * @return array Map of index name to bool.
	 */
	private static function get_indexes( $table ) {
		global $wpdb;

		$indexes = array();

		foreach ( (array) $wpdb->get_results( "SHOW INDEX FROM `$table`" ) as $index ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$indexes[ $index->Key_name ] = ( '0' === (string) $index->Non_unique );
		}

		return $indexes;
	}

	/**
	 * Bring existing installations' indexes up to the current definition.
	 *
	 * @param array $tables Map of table identifiers to prefixed names.
	 */
	private static function upgrade_indexes( $tables ) {
		global $wpdb;

		// One review per appointment. The application checked for an existing one
		// before inserting, but two requests could pass that check together and
		// both write, skewing the doctor's average.
		$reviews = $tables['reviews'];

		if ( self::table_exists( $reviews ) ) {
			$indexes = self::get_indexes( $reviews );

			if ( empty( $indexes['appointment_id'] ) ) {
				// A unique index cannot be added while duplicates exist, so keep the
				// earliest review for each appointment and drop the rest.
				$removed = $wpdb->query(
					"DELETE dupe FROM `$reviews` dupe
					 JOIN `$reviews` keeper
					   ON dupe.appointment_id = keeper.appointment_id
					  AND dupe.id > keeper.id"
				); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				if ( isset( $indexes['appointment_id'] ) ) {
					$wpdb->query( "ALTER TABLE `$reviews` DROP INDEX appointment_id" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}

				$wpdb->query( "ALTER TABLE `$reviews` ADD UNIQUE KEY appointment_id (appointment_id)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

				if ( $removed ) {
					self::recalculate_doctor_ratings( $tables['doctors_meta'], $reviews );
				}
			}
		}

		// Reporting filters on status and orders by created_at.
		$transactions = $tables['transactions'];

		if ( self::table_exists( $transactions ) ) {
			$indexes = self::get_indexes( $transactions );

			foreach ( array( 'status', 'created_at' ) as $column ) {
				if ( ! isset( $indexes[ $column ] ) ) {
					$wpdb->query( "ALTER TABLE `$transactions` ADD KEY $column ($column)" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				}
			}
		}
	}

	/**
	 * Recompute every doctor's rating from the reviews that remain.
	 *
	 * @param string $doctors_meta_table Prefixed doctors meta table name.
	 * @param string $reviews_table      Prefixed reviews table name.
	 */
	private static function recalculate_doctor_ratings( $doctors_meta_table, $reviews_table ) {
		global $wpdb;

		$wpdb->query(
			"UPDATE `$doctors_meta_table` m
			 JOIN (
			     SELECT doctor_id, COUNT(id) AS total, AVG(rating) AS avg_val
			     FROM `$reviews_table`
			     GROUP BY doctor_id
			 ) r ON r.doctor_id = m.post_id
			 SET m.avg_rating = r.avg_val, m.total_reviews = r.total"
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Move reminder bookkeeping off wp_options and onto the appointment row.
	 *
	 * Each sent reminder used to leave an autoloaded eg_care_reminder_sent_{id}
	 * option behind, so wp_options grew by one autoloaded row per appointment
	 * and every page view carried the lot.
	 *
	 * @param string $appointments_table Prefixed appointments table name.
	 */
	private static function migrate_reminder_flags( $appointments_table ) {
		global $wpdb;

		// Carry the old flags over so nobody gets a reminder twice.
		$wpdb->query(
			"UPDATE `$appointments_table` a
			 JOIN `{$wpdb->options}` o ON o.option_name = CONCAT( 'eg_care_reminder_sent_', a.id )
			 SET a.reminder_sent_at = a.updated_at
			 WHERE a.reminder_sent_at IS NULL"
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s",
				$wpdb->esc_like( 'eg_care_reminder_sent_' ) . '%'
			)
		);
	}

	/**
	 * Drop all custom tables on uninstall.
	 */
	public static function drop_tables() {
		global $wpdb;
		$tables = self::get_tables();

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS $table" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		delete_option( self::DB_VERSION_OPTION );
	}

	/**
	 * Seed dummy doctors for testing.
	 */
	public static function seed_dummy_doctors() {
		global $wpdb;
		$table_meta = self::get_table( 'doctors_meta' );
		$now        = current_time( 'mysql' );

		// Ensure taxonomy and post type are registered
		if ( ! taxonomy_exists( 'specialty' ) ) {
			\EGCare\CPT::register_taxonomies();
		}
		if ( ! post_type_exists( 'doctors' ) ) {
			\EGCare\CPT::register_post_types();
		}

		// Check if dummy doctor meta already exists
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_meta" );
		if ( 0 === intval( $count ) ) {

		// Define specialties
		$specialties   = array( 'Cardiology', 'Neurology', 'Pediatrics' );
		$specialty_ids = array();
		foreach ( $specialties as $spec ) {
			$term = term_exists( $spec, 'specialty' );
			if ( ! $term ) {
				$term = wp_insert_term( $spec, 'specialty' );
			}
			if ( ! is_wp_error( $term ) ) {
				$specialty_ids[ $spec ] = isset( $term['term_id'] ) ? $term['term_id'] : $term;
			}
		}

		// Define dummy doctors data
		$dummy_data = array(
			array(
				'username'            => 'doctor_cardio',
				'email'               => 'cardio@example.com',
				'display_name'        => 'Dr. Anisur Rahman',
				'specialty'           => 'Cardiology',
				'bio'                 => 'Senior Cardiologist with over 15 years of experience in cardiovascular diseases, angioplasty, and pacemaker implantations.',
				'provider_type'       => 'doctor',
				'bmdc_license_no'     => 'A-12345',
				'degree'              => 'MBBS, FCPS (Cardiology)',
				'designation'         => 'Senior Cardiologist',
				'consultation_fee'    => 1000.00,
				'instant_call_fee'    => 1200.00,
				'experience_years'    => 15,
				'is_online'           => 1,
				'verification_status' => 'approved',
				'mobile_banking_type' => 'bkash',
				'mobile_banking_no'   => '01712345678',
			),
			array(
				'username'            => 'doctor_neuro',
				'email'               => 'neuro@example.com',
				'display_name'        => 'Dr. Nusrat Jahan',
				'specialty'           => 'Neurology',
				'bio'                 => 'Consultant Neurologist specializing in stroke management, brain disorders, epilepsy, and neuromuscular diseases.',
				'provider_type'       => 'doctor',
				'bmdc_license_no'     => 'A-67890',
				'degree'              => 'MBBS, MD (Neurology)',
				'designation'         => 'Consultant Neurologist',
				'consultation_fee'    => 1500.00,
				'instant_call_fee'    => 1800.00,
				'experience_years'    => 10,
				'is_online'           => 0,
				'verification_status' => 'approved',
				'mobile_banking_type' => 'nagad',
				'mobile_banking_no'   => '01812345678',
			),
			array(
				'username'            => 'doctor_pedia',
				'email'               => 'pedia@example.com',
				'display_name'        => 'Dr. Tanvir Hasan',
				'specialty'           => 'Pediatrics',
				'bio'                 => 'Associate Professor of Pediatrics. Specialized in neonatology, child growth monitoring, and pediatric critical care.',
				'provider_type'       => 'doctor',
				'bmdc_license_no'     => 'A-54321',
				'degree'              => 'MBBS, MD (Pediatrics)',
				'designation'         => 'Associate Professor',
				'consultation_fee'    => 800.00,
				'instant_call_fee'    => 1000.00,
				'experience_years'    => 8,
				'is_online'           => 1,
				'verification_status' => 'pending',
				'mobile_banking_type' => 'rocket',
				'mobile_banking_no'   => '01912345678',
			),
		);

		$now = current_time( 'mysql' );

		foreach ( $dummy_data as $doctor ) {
			// Check if user exists
			$user_id = username_exists( $doctor['username'] );
			if ( ! $user_id && false === email_exists( $doctor['email'] ) ) {
				$user_id = wp_create_user( $doctor['username'], 'password123', $doctor['email'] );
				if ( is_wp_error( $user_id ) ) {
					continue;
				}
				// Set display name and role
				wp_update_user(
					array(
						'ID'           => $user_id,
						'display_name' => $doctor['display_name'],
						'role'         => 'eg_care_doctor',
					)
				);
			} else {
				// Re-fetch user ID if they existed
				if ( ! $user_id ) {
					$user    = get_user_by( 'email', $doctor['email'] );
					$user_id = $user ? $user->ID : 0;
				}
				if ( $user_id ) {
					// Ensure they have the correct role
					$u = new \WP_User( $user_id );
					$u->set_role( 'eg_care_doctor' );
				}
			}

			if ( ! $user_id ) {
				continue;
			}

			// Create CPT doctors post
			$existing_post = get_posts(
				array(
					'post_type'   => 'doctors',
					'author'      => $user_id,
					'post_status' => 'any',
					'numberposts' => 1,
				)
			);

			if ( empty( $existing_post ) ) {
				$post_id = wp_insert_post(
					array(
						'post_title'   => $doctor['display_name'],
						'post_content' => $doctor['bio'],
						'post_status'  => 'publish',
						'post_type'    => 'doctors',
						'post_author'  => $user_id,
					)
				);
			} else {
				$post_id = $existing_post[0]->ID;
			}

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			// Associate Specialty term
			if ( isset( $specialty_ids[ $doctor['specialty'] ] ) ) {
				wp_set_object_terms( $post_id, (int) $specialty_ids[ $doctor['specialty'] ], 'specialty' );
			}

			// Check if metadata row already exists in table
			$meta_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_meta WHERE post_id = %d", $post_id ) );

			if ( ! $meta_id ) {
				$wpdb->insert(
					$table_meta,
					array(
						'post_id'             => $post_id,
						'user_id'             => $user_id,
						'provider_type'       => $doctor['provider_type'],
						'bmdc_license_no'     => $doctor['bmdc_license_no'],
						'bmdc_expiry_date'    => '2028-12-31',
						'degree'              => $doctor['degree'],
						'designation'         => $doctor['designation'],
						'consultation_fee'    => $doctor['consultation_fee'],
						'instant_call_fee'    => $doctor['instant_call_fee'],
						'experience_years'    => $doctor['experience_years'],
						'is_online'           => $doctor['is_online'],
						'verification_status' => $doctor['verification_status'],
						'mobile'              => '01711112222',
						'nid'                 => '123456789012',
						'nationality'         => 'Bangladeshi',
						'organization'        => 'Dhaka Medical College',
						'follow_up_days'      => 7,
						'follow_up_cost'      => 200.00,
						'bank_account_name'   => 'Dr. Test Account',
						'bank_account_no'     => '1234567890',
						'bank_branch_name'    => 'Dhanmondi Branch',
						'bank_routing_number' => '123456789',
						'mobile_banking_type' => $doctor['mobile_banking_type'],
						'mobile_banking_no'   => $doctor['mobile_banking_no'],
						'certificate_files'   => wp_json_encode( array() ),
						'created_at'          => $now,
					),
					array(
						'%d',
						'%d',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%f',
						'%f',
						'%d',
						'%d',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%d',
						'%f',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
						'%s',
					)
				);
			}
		}
		} // End of if ( 0 === intval( $count ) ).

		// Seed dummy schedules if empty.
		$table_schedules = self::get_table( 'schedules' );
		$schedules_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_schedules" );
		if ( 0 === intval( $schedules_count ) ) {
			// Find our doctor post IDs by post titles.
			$doctor_posts = get_posts(
				array(
					'post_type'   => 'doctors',
					'post_status' => 'any',
					'numberposts' => -1,
				)
			);

			foreach ( $doctor_posts as $post ) {
				$doc_id = $post->ID;
				if ( false !== strpos( $post->post_title, 'Anisur' ) ) {
					// Seed schedule: Monday (1) 09:00 - 12:00 (30 min).
					$wpdb->insert(
						$table_schedules,
						array(
							'doctor_id'         => $doc_id,
							'day_of_week'       => 1, // Monday.
							'start_time'        => '09:00:00',
							'end_time'          => '12:00:00',
							'slot_duration_min' => 30,
							'is_active'         => 1,
						)
					);
					// Seed schedule: Wednesday (3) 14:00 - 17:00 (30 min).
					$wpdb->insert(
						$table_schedules,
						array(
							'doctor_id'         => $doc_id,
							'day_of_week'       => 3, // Wednesday.
							'start_time'        => '14:00:00',
							'end_time'          => '17:00:00',
							'slot_duration_min' => 30,
							'is_active'         => 1,
						)
					);
				} elseif ( false !== strpos( $post->post_title, 'Nusrat' ) ) {
					// Seed schedule: Tuesday (2) 10:00 - 13:00 (30 min).
					$wpdb->insert(
						$table_schedules,
						array(
							'doctor_id'         => $doc_id,
							'day_of_week'       => 2, // Tuesday.
							'start_time'        => '10:00:00',
							'end_time'          => '13:00:00',
							'slot_duration_min' => 30,
							'is_active'         => 1,
						)
					);
				}
			}
		}

		// Seed dummy appointment if empty (to test slot subtraction).
		$table_appointments = self::get_table( 'appointments' );
		$appointments_count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_appointments" );
		if ( 0 === intval( $appointments_count ) ) {
			// Find Dr. Anisur.
			$anisur = get_page_by_title( 'Dr. Anisur Rahman', OBJECT, 'doctors' );
			if ( $anisur ) {
				// Seed an appointment for Dr. Anisur on next Monday at 09:30:00.
				$next_monday = wp_date( 'Y-m-d', strtotime( 'next monday' ) );
				$wpdb->insert(
					$table_appointments,
					array(
						'doctor_id'        => $anisur->ID,
						'patient_user_id'  => 2, // Dummy patient.
						'appointment_type' => 'scheduled',
						'appointment_date' => $next_monday,
						'appointment_time' => '09:30:00',
						'status'           => 'confirmed',
						'payment_status'   => 'paid',
						'amount'           => 1000.00,
						'created_at'       => $now,
						'updated_at'       => $now,
					)
				);
			}
		}
	}
}

