<?php
/**
 * Turning the wall-clock strings in the database into timestamps, on purpose.
 *
 * @package EG Care
 */

namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Appointments, schedules and slots are all stored as wall-clock strings:
 * appointment_date 'Y-m-d' and appointment_time 'H:i:s', with no zone attached.
 * Reading one back means deciding which clock it belongs to, and the plugin has
 * two different answers depending on the question:
 *
 *   stamp() - "has this slot passed yet?" The string is a moment in the
 *             surgery's day, so it has to be read in the site's timezone and
 *             compared against time().
 *
 *   wall()  - "what time is 30 minutes after 10:00?" Nothing here is a real
 *             moment; it is arithmetic on a clock face, and the answer is
 *             rendered straight back out with gmdate().
 *
 * Both used to be plain strtotime(), which reads the string in whatever
 * timezone PHP happens to be set to. WordPress pins that to UTC, so the sums
 * came out right - but only by way of that one assumption, held in a global
 * any other plugin is free to change with date_default_timezone_set(). Saying
 * which clock is meant costs a line and removes the assumption.
 */
class Time {

	/**
	 * Real Unix timestamp for a wall-clock date and time in the site's timezone.
	 *
	 * Use this whenever the answer is compared against time() - a slot that has
	 * passed, a call window that has opened.
	 *
	 * @param string $date Date as Y-m-d.
	 * @param string $time Time as H:i:s. Defaults to midnight.
	 * @return int|false Timestamp, or false if the strings do not parse.
	 */
	public static function stamp( $date, $time = '00:00:00' ) {
		$date = trim( (string) $date );
		$time = trim( (string) $time );

		if ( '' === $date ) {
			return false;
		}

		if ( '' === $time ) {
			$time = '00:00:00';
		}

		try {
			$moment = new \DateTimeImmutable( $date . ' ' . $time, wp_timezone() );
		} catch ( \Exception $e ) {
			return false;
		}

		return $moment->getTimestamp();
	}

	/**
	 * Timestamp for doing arithmetic on a clock face.
	 *
	 * Fixed to UTC so that gmdate() renders the result as the same wall clock
	 * that went in, whatever timezone the site or PHP is set to. The number
	 * itself is meaningless as a moment in time - never compare it to time().
	 *
	 * @param string $date Date as Y-m-d.
	 * @param string $time Time as H:i:s. Defaults to midnight.
	 * @return int|false Timestamp, or false if the strings do not parse.
	 */
	public static function wall( $date, $time = '00:00:00' ) {
		$date = trim( (string) $date );
		$time = trim( (string) $time );

		if ( '' === $date ) {
			return false;
		}

		if ( '' === $time ) {
			$time = '00:00:00';
		}

		try {
			$moment = new \DateTimeImmutable( $date . ' ' . $time, new \DateTimeZone( 'UTC' ) );
		} catch ( \Exception $e ) {
			return false;
		}

		return $moment->getTimestamp();
	}
}
