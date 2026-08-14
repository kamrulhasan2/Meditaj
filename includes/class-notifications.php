<?php
namespace Meditaj;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles sending email (and in the future SMS) notifications.
 */
class Notifications {
	/**
	 * Send approval email to a doctor.
	 *
	 * @param string $email Recipient email.
	 * @param string $name  Doctor display name.
	 * @return bool Whether the email was sent successfully.
	 */
	public static function send_doctor_approval_email( $email, $name ) {
		$subject = sprintf( '[%s] %s', get_bloginfo( 'name' ), __( 'Your Doctor Profile Has Been Approved!', 'meditaj' ) );

		$body  = sprintf( __( 'Dear %s,', 'meditaj' ), $name ) . "\r\n\r\n";
		$body .= __( 'Congratulations! Your medical provider profile has been verified and approved by our administrators.', 'meditaj' ) . "\r\n\r\n";
		$body .= __( 'You can now log into your doctor dashboard and manage your slots and appointments.', 'meditaj' ) . "\r\n\r\n";
		$body .= sprintf( __( 'Login Page: %s', 'meditaj' ), wp_login_url() ) . "\r\n\r\n";
		$body .= __( 'Regards,', 'meditaj' ) . "\r\n";
		$body .= get_bloginfo( 'name' ) . ' ' . __( 'Team', 'meditaj' );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		return wp_mail( $email, $subject, $body, $headers );
	}

	/**
	 * Send rejection email to a doctor.
	 *
	 * @param string $email  Recipient email.
	 * @param string $name   Doctor display name.
	 * @param string $reason Rejection reason text.
	 * @return bool Whether the email was sent successfully.
	 */
	public static function send_doctor_rejection_email( $email, $name, $reason ) {
		$subject = sprintf( '[%s] %s', get_bloginfo( 'name' ), __( 'Update on Your Doctor Application', 'meditaj' ) );

		$body  = sprintf( __( 'Dear %s,', 'meditaj' ), $name ) . "\r\n\r\n";
		$body .= __( 'We regret to inform you that your doctor profile application has been rejected by our verification team.', 'meditaj' ) . "\r\n\r\n";

		if ( ! empty( $reason ) ) {
			$body .= __( 'Reason provided:', 'meditaj' ) . "\r\n";
			$body .= '--------------------------------------' . "\r\n";
			$body .= $reason . "\r\n";
			$body .= '--------------------------------------' . "\r\n\r\n";
		}

		$body .= __( 'If you believe this was an error or wish to submit additional details, please contact our support team.', 'meditaj' ) . "\r\n\r\n";
		$body .= __( 'Regards,', 'meditaj' ) . "\r\n";
		$body .= get_bloginfo( 'name' ) . ' ' . __( 'Team', 'meditaj' );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		return wp_mail( $email, $subject, $body, $headers );
	}
}
