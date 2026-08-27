<?php
namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Single source of truth for money.
 *
 * Both numbers below used to be written out by hand wherever they were needed -
 * the 5% service charge lived in the REST controller, in booking.js and in the
 * checkout template, and an instant call whose fee was left at 0 was booked for
 * nothing. Anything that needs a fee now asks this class.
 */
class Fees {

	/**
	 * Option holding the percentage added to the patient's bill.
	 */
	const SERVICE_CHARGE_OPTION = 'eg_care_service_charge_percentage';

	/**
	 * Percentage used when the option has never been saved.
	 */
	const SERVICE_CHARGE_DEFAULT = 5.0;

	/**
	 * Percentage the platform adds on top of the consultation fee, paid by the
	 * patient.
	 *
	 * Not to be confused with eg_care_commission_percentage, which is the cut
	 * taken out of the doctor's earnings at payout time. They are separate
	 * numbers that happen to both be percentages: one is charged to the patient,
	 * the other is withheld from the doctor.
	 *
	 * @return float Percentage between 0 and 100.
	 */
	public static function service_charge_percentage() {
		$value = get_option( self::SERVICE_CHARGE_OPTION, self::SERVICE_CHARGE_DEFAULT );
		$value = is_numeric( $value ) ? floatval( $value ) : self::SERVICE_CHARGE_DEFAULT;

		// A negative or absurd percentage would silently produce a wrong bill,
		// so clamp rather than trust whatever is in the options table.
		return max( 0.0, min( 100.0, $value ) );
	}

	/**
	 * The fee actually charged for a booking.
	 *
	 * An instant call falls back to the scheduled consultation fee when the
	 * doctor's instant fee is missing or zero. The admin form marks the instant
	 * fee required but accepts 0, and a 0 there used to mean the patient booked
	 * a live call for free.
	 *
	 * @param object $doctor       Row from the doctors_meta table.
	 * @param string $booking_type 'instant' or 'scheduled'.
	 * @return float Fee in BDT.
	 */
	public static function consultation_fee( $doctor, $booking_type ) {
		$scheduled = isset( $doctor->consultation_fee ) ? floatval( $doctor->consultation_fee ) : 0.0;

		if ( 'instant' !== $booking_type ) {
			return max( 0.0, $scheduled );
		}

		$instant = isset( $doctor->instant_call_fee ) ? floatval( $doctor->instant_call_fee ) : 0.0;

		return $instant > 0 ? $instant : max( 0.0, $scheduled );
	}

	/**
	 * Split a fee into the lines the patient sees.
	 *
	 * Rounding happens once, here, so the server total and the total the
	 * checkout summary printed can never disagree by a paisa.
	 *
	 * @param float $fee Consultation fee.
	 * @return array{fee:float,service_charge:float,total:float,percentage:float}
	 */
	public static function breakdown( $fee ) {
		$fee        = round( max( 0.0, floatval( $fee ) ), 2 );
		$percentage = self::service_charge_percentage();
		$charge     = round( $fee * ( $percentage / 100 ), 2 );

		return array(
			'fee'            => $fee,
			'service_charge' => $charge,
			'total'          => round( $fee + $charge, 2 ),
			'percentage'     => $percentage,
		);
	}
}
