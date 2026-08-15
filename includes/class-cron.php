<?php
namespace Meditaj;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Cron {
	/**
	 * Initialize the cron hooks.
	 */
	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_custom_intervals' ) );
		add_action( 'wp', array( __CLASS__, 'schedule_jobs' ) );
		add_action( 'meditaj_appointment_reminder_cron', array( __CLASS__, 'run_reminders' ) );
	}

	/**
	 * Add custom 10-minute interval to WordPress cron schedules.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public static function add_custom_intervals( $schedules ) {
		$schedules['meditaj_every_ten_minutes'] = array(
			'interval' => 10 * MINUTE_IN_SECONDS,
			'display'  => esc_html__( 'Every 10 Minutes', 'meditaj' ),
		);
		return $schedules;
	}

	/**
	 * Schedule the reminder event if not already scheduled.
	 */
	public static function schedule_jobs() {
		if ( ! wp_next_scheduled( 'meditaj_appointment_reminder_cron' ) ) {
			wp_schedule_event( time(), 'meditaj_every_ten_minutes', 'meditaj_appointment_reminder_cron' );
		}
	}

	/**
	 * Run the reminder calculations and dispatch emails.
	 */
	public static function run_reminders() {
		global $wpdb;
		$table_appointments = DB::get_table( 'appointments' );

		// Localized timestamps
		$now_ts = current_time( 'timestamp' );
		$today_str = date( 'Y-m-d', $now_ts );
		
		// Remind for appointments starting in the next 35 minutes
		$max_time_ts = $now_ts + ( 35 * MINUTE_IN_SECONDS );
		$min_time_ts = $now_ts - ( 5 * MINUTE_IN_SECONDS ); // buffer window
		
		$min_time_str = date( 'H:i:s', $min_time_ts );
		$max_time_str = date( 'H:i:s', $max_time_ts );

		// Get all confirmed scheduled bookings within the time window.
		$appointments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_appointments 
				 WHERE status = 'confirmed' 
				   AND appointment_type = 'scheduled'
				   AND appointment_date = %s
				   AND appointment_time BETWEEN %s AND %s",
				$today_str,
				$min_time_str,
				$max_time_str
			)
		);

		if ( empty( $appointments ) ) {
			return;
		}

		// Dispatch reminder emails if not already sent
		foreach ( $appointments as $appt ) {
			$option_key = 'meditaj_reminder_sent_' . $appt->id;
			$already_sent = get_option( $option_key );

			if ( ! $already_sent ) {
				Notifications::send_appointment_reminder_emails( $appt->id );
				update_option( $option_key, 1 );
			}
		}
	}

	/**
	 * Clear scheduled cron jobs on plugin deactivation.
	 */
	public static function clear_schedule() {
		wp_clear_scheduled_hook( 'meditaj_appointment_reminder_cron' );
	}
}
