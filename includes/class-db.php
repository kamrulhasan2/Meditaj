<?php
namespace Meditaj;

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
	const DB_VERSION_OPTION = 'meditaj_db_version';

	/**
	 * Current database schema version.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Get custom table names.
	 *
	 * @return array Map of table identifiers to full prefixed table names.
	 */
	public static function get_tables() {
		global $wpdb;
		return array(
			'doctors_meta' => $wpdb->prefix . 'meditaj_doctors_meta',
			'schedules'    => $wpdb->prefix . 'meditaj_schedules',
			'appointments' => $wpdb->prefix . 'meditaj_appointments',
			'reviews'      => $wpdb->prefix . 'meditaj_reviews',
			'transactions' => $wpdb->prefix . 'meditaj_transactions',
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

		// 1. wp_meditaj_doctors_meta
		$sql[] = "CREATE TABLE {$tables['doctors_meta']} (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  post_id bigint(20) NOT NULL,
  user_id bigint(20) NOT NULL,
  provider_type enum('doctor','medical_professional') NOT NULL,
  bmdc_license_no varchar(50) NOT NULL,
  degree varchar(255) NOT NULL,
  designation varchar(255) NOT NULL,
  consultation_fee decimal(10,2) NOT NULL,
  instant_call_fee decimal(10,2) NOT NULL,
  experience_years int(11) NOT NULL,
  is_online tinyint(1) DEFAULT 0 NOT NULL,
  avg_rating decimal(2,1) DEFAULT 0.0 NOT NULL,
  total_reviews int(11) DEFAULT 0 NOT NULL,
  verification_status enum('pending','approved','rejected') DEFAULT 'pending' NOT NULL,
  bank_account_name varchar(255) NOT NULL,
  bank_account_no varchar(100) NOT NULL,
  mobile_banking_type enum('bkash','nagad','rocket') NOT NULL,
  mobile_banking_no varchar(20) NOT NULL,
  certificate_files text NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY post_id (post_id),
  KEY user_id (user_id),
  KEY verification_status (verification_status),
  KEY is_online (is_online),
  KEY fee_verification (consultation_fee, verification_status)
) $charset_collate;";

		// 2. wp_meditaj_schedules
		$sql[] = "CREATE TABLE {$tables['schedules']} (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  doctor_id bigint(20) NOT NULL,
  day_of_week tinyint(4) NOT NULL,
  start_time time NOT NULL,
  end_time time NOT NULL,
  slot_duration_min int(11) NOT NULL,
  is_active tinyint(1) DEFAULT 1 NOT NULL,
  PRIMARY KEY  (id),
  KEY doctor_id (doctor_id)
) $charset_collate;";

		// 3. wp_meditaj_appointments
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
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY doctor_id (doctor_id),
  KEY patient_user_id (patient_user_id),
  KEY status (status),
  KEY doctor_slot (doctor_id, appointment_date, appointment_time)
) $charset_collate;";

		// 4. wp_meditaj_reviews
		$sql[] = "CREATE TABLE {$tables['reviews']} (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  appointment_id bigint(20) NOT NULL,
  doctor_id bigint(20) NOT NULL,
  patient_user_id bigint(20) NOT NULL,
  rating tinyint(4) NOT NULL,
  comment text NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY appointment_id (appointment_id),
  KEY doctor_id (doctor_id),
  KEY patient_user_id (patient_user_id)
) $charset_collate;";

		// 5. wp_meditaj_transactions
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
  KEY gateway_txn_id (gateway_txn_id)
) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( $sql as $query ) {
			dbDelta( $query );
		}

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
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

		// Check if dummy data already exists
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table_meta" );
		if ( $count > 0 ) {
			return;
		}

		// Ensure taxonomy and post type are registered
		if ( ! taxonomy_exists( 'specialty' ) ) {
			\Meditaj\CPT::register_taxonomies();
		}
		if ( ! post_type_exists( 'doctors' ) ) {
			\Meditaj\CPT::register_post_types();
		}

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
						'role'         => 'meditaj_doctor',
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
					$u->set_role( 'meditaj_doctor' );
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
						'degree'              => $doctor['degree'],
						'designation'         => $doctor['designation'],
						'consultation_fee'    => $doctor['consultation_fee'],
						'instant_call_fee'    => $doctor['instant_call_fee'],
						'experience_years'    => $doctor['experience_years'],
						'is_online'           => $doctor['is_online'],
						'verification_status' => $doctor['verification_status'],
						'bank_account_name'   => 'Test Bank Account',
						'bank_account_no'     => '1234567890',
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
						'%f',
						'%f',
						'%d',
						'%d',
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
	}
}

