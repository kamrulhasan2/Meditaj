<?php
namespace EGCare;

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
		add_action( 'eg_care_appointment_reminder_cron', array( __CLASS__, 'run_reminders' ) );
		add_action( 'eg_care_expire_pending_cron', array( __CLASS__, 'expire_pending_payments' ) );
	}

	/**
	 * Add custom 10-minute interval to WordPress cron schedules.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public static function add_custom_intervals( $schedules ) {
		$schedules['eg_care_every_ten_minutes'] = array(
			'interval' => 10 * MINUTE_IN_SECONDS,
			'display'  => esc_html__( 'Every 10 Minutes', 'eg-care' ),
		);
		return $schedules;
	}

	/**
	 * Schedule the reminder event if not already scheduled.
	 */
	public static function schedule_jobs() {
		if ( ! wp_next_scheduled( 'eg_care_appointment_reminder_cron' ) ) {
			wp_schedule_event( time(), 'eg_care_every_ten_minutes', 'eg_care_appointment_reminder_cron' );
		}

		if ( ! wp_next_scheduled( 'eg_care_expire_pending_cron' ) ) {
			wp_schedule_event( time(), 'eg_care_every_ten_minutes', 'eg_care_expire_pending_cron' );
		}
	}

	/**
	 * How long a slot is held while the patient is away paying, in seconds.
	 *
	 * Long enough that a slow bKash or card payment never loses the slot it
	 * has already been charged for; short enough that an abandoned attempt
	 * does not cost the doctor an hour of their day.
	 *
	 * @return int Seconds.
	 */
	public static function pending_payment_ttl() {
		$ttl = (int) apply_filters( 'eg_care_pending_payment_ttl', 30 * MINUTE_IN_SECONDS );

		// A zero or negative window would cancel bookings the instant they are
		// created, before the patient ever reaches the gateway.
		return $ttl > 0 ? $ttl : 30 * MINUTE_IN_SECONDS;
	}

	/**
	 * Release slots held by payments that were never completed.
	 *
	 * calculate_slots() treats 'pending_payment' as booked, which is right - the
	 * slot must be held while the patient is at the gateway. Nothing released
	 * that hold again, so every abandoned payment took a slot out of the
	 * doctor's calendar permanently. On tech.meditaj.com eight of them had
	 * accumulated, the oldest over a week old, one of them sitting on a real
	 * scheduled slot.
	 *
	 * Three guards, because cancelling a booking somebody has actually paid for
	 * would be far worse than leaving a stale one behind:
	 *
	 *   - payment_status must not already be 'paid'
	 *   - no successful transaction row may exist for the appointment, which
	 *     catches an IPN that landed while the appointment row lagged behind
	 *   - the appointment must be older than the hold window
	 *
	 * All of it lives in one UPDATE, so there is no read-then-write gap for a
	 * payment to slip through, and re-running it is harmless.
	 *
	 * 'cancelled' rather than an 'expired' status of its own: the status column
	 * is an ENUM and 'expired' is not one of its values.
	 */
	public static function expire_pending_payments() {
		global $wpdb;

		$appointments = DB::get_table( 'appointments' );
		$transactions = DB::get_table( 'transactions' );

		// created_at is written with current_time('mysql'), so the cutoff has to
		// be the site's wall clock too. Same idiom as run_reminders() above:
		// current_time('timestamp') is already shifted into site time, and
		// gmdate() renders it without depending on PHP's ambient timezone.
		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - self::pending_payment_ttl() );
		$now    = current_time( 'mysql' );

		$expired = $wpdb->query(
			$wpdb->prepare(
				"UPDATE $appointments a
				    LEFT JOIN $transactions t
				           ON t.appointment_id = a.id
				          AND t.status = 'success'
				   SET a.status = 'cancelled',
				       a.updated_at = %s
				 WHERE a.status = 'pending_payment'
				   AND a.payment_status <> 'paid'
				   AND a.created_at < %s
				   AND t.id IS NULL",
				$now,
				$cutoff
			)
		);

		if ( $expired ) {
			/**
			 * Fires after abandoned bookings have released their slots.
			 *
			 * @param int $expired How many appointments were cancelled.
			 */
			do_action( 'eg_care_pending_payments_expired', (int) $expired );
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
		$today_str = current_time( 'Y-m-d' );
		
		// Remind for appointments starting in the next 35 minutes
		$max_time_ts = $now_ts + ( 35 * MINUTE_IN_SECONDS );
		$min_time_ts = $now_ts - ( 5 * MINUTE_IN_SECONDS ); // buffer window
		
		// $now_ts is current_time('timestamp'), already shifted into site time, so
		// gmdate() renders it as the site's wall clock without depending on PHP's
		// ambient timezone the way date() did.
		$min_time_str = gmdate( 'H:i:s', $min_time_ts );
		$max_time_str = gmdate( 'H:i:s', $max_time_ts );

		// Get all confirmed scheduled bookings within the time window.
		$appointments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_appointments 
				 WHERE status = 'confirmed' 
				   AND appointment_type = 'scheduled'
				   AND appointment_date = %s
				   AND appointment_time BETWEEN %s AND %s
				   AND reminder_sent_at IS NULL",
				$today_str,
				$min_time_str,
				$max_time_str
			)
		);

		if ( empty( $appointments ) ) {
			return;
		}

		// Claim each appointment before sending. The UPDATE only matches a row
		// whose reminder is still unsent, so two overlapping cron runs cannot
		// both mail the same patient.
		$now = current_time( 'mysql' );

		foreach ( $appointments as $appt ) {
			$claimed = $wpdb->query(
				$wpdb->prepare(
					"UPDATE $table_appointments SET reminder_sent_at = %s WHERE id = %d AND reminder_sent_at IS NULL",
					$now,
					$appt->id
				)
			);

			if ( $claimed ) {
				Notifications::send_appointment_reminder_emails( $appt->id );
			}
		}
	}

	/**
	 * Clear scheduled cron jobs on plugin deactivation.
	 */
	public static function clear_schedule() {
		wp_clear_scheduled_hook( 'eg_care_appointment_reminder_cron' );
		wp_clear_scheduled_hook( 'eg_care_expire_pending_cron' );
	}
}
