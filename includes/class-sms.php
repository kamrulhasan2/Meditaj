<?php
/**
 * Sending an SMS, without being tied to one gateway.
 *
 * In Bangladesh a text message reaches a patient far more reliably than an
 * email does, and every phone that booked a consultation left its number
 * behind. What this plugin cannot do is pick the gateway: the site may be on
 * Alpha SMS, SSL Wireless, MIM, bulksmsbd or a reseller, and they all take
 * roughly the same request with different parameter names.
 *
 * So the site owner pastes their gateway's own URL into Settings and marks
 * the two moving parts with {number} and {message}. No vendor is hard-coded
 * here, and nobody has to wait for this plugin to add support for theirs.
 *
 * @package EG Care
 */

namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Sms
 */
class Sms {

	/**
	 * Settings.
	 */
	const ENABLED_OPTION  = 'eg_care_sms_enabled';
	const ENDPOINT_OPTION = 'eg_care_sms_endpoint';
	const BODY_OPTION     = 'eg_care_sms_body';

	/**
	 * Whether texting is switched on and actually configured.
	 *
	 * Both, because a half-finished setting should behave like "off" rather
	 * than like a broken send on every approval.
	 *
	 * @return bool
	 */
	public static function enabled() {
		if ( '1' !== (string) get_option( self::ENABLED_OPTION, '0' ) ) {
			return false;
		}

		return '' !== trim( (string) get_option( self::ENDPOINT_OPTION, '' ) );
	}

	/**
	 * Put a Bangladeshi mobile number into the form a gateway expects.
	 *
	 * Patients type their number every way there is: 01712345678,
	 * +8801712345678, 8801712345678, with spaces and dashes. Gateways want one
	 * of those and reject the rest, so everything is normalised to the
	 * international form without a plus.
	 *
	 * Anything that is not a plausible Bangladeshi mobile comes back empty,
	 * and an empty number simply means no text is sent.
	 *
	 * @param string $raw Whatever was typed at booking time.
	 * @return string 8801XXXXXXXXX, or an empty string.
	 */
	public static function normalise_number( $raw ) {
		$digits = preg_replace( '/\D+/', '', (string) $raw );

		if ( '' === $digits ) {
			return '';
		}

		// 008801... from someone dialling internationally.
		if ( 0 === strpos( $digits, '00880' ) ) {
			$digits = substr( $digits, 2 );
		}

		// 01712345678 -> 8801712345678
		if ( 11 === strlen( $digits ) && 0 === strpos( $digits, '01' ) ) {
			$digits = '880' . substr( $digits, 1 );
		}

		// 1712345678, typed without the leading zero.
		if ( 10 === strlen( $digits ) && '1' === $digits[0] ) {
			$digits = '880' . $digits;
		}

		if ( 13 !== strlen( $digits ) || 0 !== strpos( $digits, '8801' ) ) {
			return '';
		}

		return $digits;
	}

	/**
	 * Send one message.
	 *
	 * @param string $number  Whatever number is on file.
	 * @param string $message The text.
	 * @return bool Whether the gateway was reached and answered without an error.
	 */
	public static function send( $number, $message ) {
		if ( ! self::enabled() ) {
			return false;
		}

		$number  = self::normalise_number( $number );
		$message = trim( wp_strip_all_tags( (string) $message ) );

		if ( '' === $number || '' === $message ) {
			return false;
		}

		/**
		 * Replaces the whole send, for a gateway that needs more than a URL.
		 *
		 * Return a boolean to take over; return null to let the configured
		 * endpoint handle it.
		 *
		 * @param null|bool $handled Whether a listener has sent it.
		 * @param string    $number  Normalised number.
		 * @param string    $message The text.
		 */
		$handled = apply_filters( 'eg_care_send_sms', null, $number, $message );

		if ( null !== $handled ) {
			return (bool) $handled;
		}

		$endpoint = trim( (string) get_option( self::ENDPOINT_OPTION, '' ) );
		$body     = trim( (string) get_option( self::BODY_OPTION, '' ) );

		$url = self::fill( $endpoint, $number, $message, 'url' );

		if ( '' === $body ) {
			$response = wp_remote_get( $url, array( 'timeout' => 15 ) );
		} else {
			$is_json = 0 === strpos( $body, '{' );

			$response = wp_remote_post(
				$url,
				array(
					'timeout' => 15,
					'headers' => array( 'Content-Type' => $is_json ? 'application/json' : 'application/x-www-form-urlencoded' ),
					'body'    => self::fill( $body, $number, $message, $is_json ? 'json' : 'url' ),
				)
			);
		}

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		return $code >= 200 && $code < 300;
	}

	/**
	 * Put the number and the message into a template.
	 *
	 * Escaping follows where the value is going: a message with an ampersand
	 * in it would otherwise cut a query string in half, and one with a quote
	 * in it would break a JSON body.
	 *
	 * @param string $template Template containing {number} and {message}.
	 * @param string $number   Normalised number.
	 * @param string $message  The text.
	 * @param string $context  'url' or 'json'.
	 * @return string
	 */
	public static function fill( $template, $number, $message, $context = 'url' ) {
		if ( 'json' === $context ) {
			$encoded_number  = trim( wp_json_encode( $number ), '"' );
			$encoded_message = trim( wp_json_encode( $message ), '"' );
		} else {
			$encoded_number  = rawurlencode( $number );
			$encoded_message = rawurlencode( $message );
		}

		return str_replace(
			array( '{number}', '{message}' ),
			array( $encoded_number, $encoded_message ),
			(string) $template
		);
	}
}
