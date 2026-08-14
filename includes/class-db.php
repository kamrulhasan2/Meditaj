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
}
