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
}

