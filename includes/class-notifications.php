<?php
namespace EGCare;

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
		$subject = sprintf( '[%s] %s', get_bloginfo( 'name' ), __( 'Your Doctor Profile Has Been Approved!', 'eg-care' ) );

		$body  = sprintf( __( 'Dear Dr. %s,', 'eg-care' ), $name ) . "\r\n\r\n";
		$body .= __( 'Congratulations! Your medical provider profile has been verified and approved by our administrators.', 'eg-care' ) . "\r\n\r\n";
		$body .= __( 'You can now log into your doctor dashboard and manage your slots and appointments.', 'eg-care' ) . "\r\n\r\n";
		$body .= sprintf( __( 'Login Page: %s', 'eg-care' ), wp_login_url() ) . "\r\n\r\n";
		$body .= __( 'Regards,', 'eg-care' ) . "\r\n";
		$body .= get_bloginfo( 'name' ) . ' ' . __( 'Team', 'eg-care' );

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
		$subject = sprintf( '[%s] %s', get_bloginfo( 'name' ), __( 'Update on Your Doctor Application', 'eg-care' ) );

		$body  = sprintf( __( 'Dear Dr. %s,', 'eg-care' ), $name ) . "\r\n\r\n";
		$body .= __( 'We regret to inform you that your doctor profile application has been rejected by our verification team.', 'eg-care' ) . "\r\n\r\n";

		if ( ! empty( $reason ) ) {
			$body .= __( 'Reason provided:', 'eg-care' ) . "\r\n";
			$body .= '--------------------------------------' . "\r\n";
			$body .= $reason . "\r\n";
			$body .= '--------------------------------------' . "\r\n\r\n";
		}

		$body .= __( 'If you believe this was an error or wish to submit additional details, please contact our support team.', 'eg-care' ) . "\r\n\r\n";
		$body .= __( 'Regards,', 'eg-care' ) . "\r\n";
		$body .= get_bloginfo( 'name' ) . ' ' . __( 'Team', 'eg-care' );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		return wp_mail( $email, $subject, $body, $headers );
	}

	/**
	 * Send booking confirmation emails to both patient and doctor.
	 *
	 * @param int $appointment_id Appointment ID.
	 */
	public static function send_booking_confirmation_emails( $appointment_id ) {
		global $wpdb;
		$table_appointments = DB::get_table( 'appointments' );
		$table_doctors      = DB::get_table( 'doctors_meta' );

		$appointment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_appointments WHERE id = %d", $appointment_id ) );
		if ( ! $appointment ) {
			return;
		}

		// Get Doctor Info
		$doctor_post_id = $appointment->doctor_id;
		$doctor_title   = get_the_title( $doctor_post_id );
		$doctor_meta    = $wpdb->get_row( $wpdb->prepare( "SELECT user_id FROM $table_doctors WHERE post_id = %d", $doctor_post_id ) );
		
		$doctor_email = '';
		$doctor_name  = $doctor_title;
		if ( $doctor_meta && $doctor_meta->user_id ) {
			$doc_user = get_userdata( $doctor_meta->user_id );
			if ( $doc_user ) {
				$doctor_email = $doc_user->user_email;
				$doctor_name  = $doc_user->display_name;
			}
		}

		// Get Patient Info
		$patient_email = '';
		$patient_name  = $appointment->family_member_name;
		$patient_user  = get_userdata( $appointment->patient_user_id );
		if ( $patient_user ) {
			$patient_email = $patient_user->user_email;
			if ( empty( $patient_name ) ) {
				$patient_name = $patient_user->display_name;
			}
		}

		$time_formatted = mysql2date( 'g:i A', $appointment->appointment_time, false );
		$date_formatted = mysql2date( 'M d, Y', $appointment->appointment_date, false );
		$type_display   = 'instant' === $appointment->appointment_type ? __( 'Instant Video Call', 'eg-care' ) : __( 'Scheduled Consultation', 'eg-care' );

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		// 1. Email to Patient
		if ( ! empty( $patient_email ) ) {
			$subject = sprintf( '[%s] %s', get_bloginfo( 'name' ), __( 'Appointment Confirmed & Paid!', 'eg-care' ) );
			$body  = sprintf( __( 'Dear %s,', 'eg-care' ), $patient_name ) . "\r\n\r\n";
			$body .= sprintf( __( 'Your booking with Dr. %s has been confirmed.', 'eg-care' ), $doctor_title ) . "\r\n\r\n";
			$body .= '--- ' . __( 'Consultation Details', 'eg-care' ) . " ---\r\n";
			$body .= sprintf( __( 'Type: %s', 'eg-care' ), $type_display ) . "\r\n";
			$body .= sprintf( __( 'Date: %s', 'eg-care' ), $date_formatted ) . "\r\n";
			$body .= sprintf( __( 'Time: %s', 'eg-care' ), $time_formatted ) . "\r\n";
			$body .= sprintf( __( 'Paid: %s BDT', 'eg-care' ), $appointment->amount ) . "\r\n\r\n";
			
			$body .= __( 'You can join the consultation call directly from your booking flow redirect receipt screen or patient registry page when the call starts.', 'eg-care' ) . "\r\n\r\n";
			$body .= __( 'Regards,', 'eg-care' ) . "\r\n";
			$body .= get_bloginfo( 'name' ) . ' ' . __( 'Team', 'eg-care' );

			wp_mail( $patient_email, $subject, $body, $headers );
		}

		// 2. Email to Doctor
		if ( ! empty( $doctor_email ) ) {
			$subject = sprintf( '[%s] %s', get_bloginfo( 'name' ), __( 'New Appointment Confirmed!', 'eg-care' ) );
			$body  = sprintf( __( 'Dear Dr. %s,', 'eg-care' ), $doctor_name ) . "\r\n\r\n";
			$body .= sprintf( __( 'A new consultation has been booked and paid for by patient %s.', 'eg-care' ), $patient_name ) . "\r\n\r\n";
			$body .= '--- ' . __( 'Consultation Details', 'eg-care' ) . " ---\r\n";
			$body .= sprintf( __( 'Type: %s', 'eg-care' ), $type_display ) . "\r\n";
			$body .= sprintf( __( 'Date: %s', 'eg-care' ), $date_formatted ) . "\r\n";
			$body .= sprintf( __( 'Time: %s', 'eg-care' ), $time_formatted ) . "\r\n";
			$body .= sprintf( __( 'Fee (Gross): %s BDT', 'eg-care' ), $appointment->amount ) . "\r\n\r\n";
			
			$body .= __( 'Please log into your dashboard to join the consultation call when scheduled.', 'eg-care' ) . "\r\n\r\n";
			$body .= __( 'Regards,', 'eg-care' ) . "\r\n";
			$body .= get_bloginfo( 'name' ) . ' ' . __( 'Team', 'eg-care' );

			wp_mail( $doctor_email, $subject, $body, $headers );
		}
	}

	/**
	 * Send reminder emails to patient and doctor before a scheduled appointment.
	 *
	 * @param int $appointment_id Appointment ID.
	 */
	public static function send_appointment_reminder_emails( $appointment_id ) {
		global $wpdb;
		$table_appointments = DB::get_table( 'appointments' );
		$table_doctors      = DB::get_table( 'doctors_meta' );

		$appointment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_appointments WHERE id = %d", $appointment_id ) );
		if ( ! $appointment ) {
			return;
		}

		// Get Doctor Info
		$doctor_post_id = $appointment->doctor_id;
		$doctor_title   = get_the_title( $doctor_post_id );
		$doctor_meta    = $wpdb->get_row( $wpdb->prepare( "SELECT user_id FROM $table_doctors WHERE post_id = %d", $doctor_post_id ) );
		
		$doctor_email = '';
		$doctor_name  = $doctor_title;
		if ( $doctor_meta && $doctor_meta->user_id ) {
			$doc_user = get_userdata( $doctor_meta->user_id );
			if ( $doc_user ) {
				$doctor_email = $doc_user->user_email;
				$doctor_name  = $doc_user->display_name;
			}
		}

		// Get Patient Info
		$patient_email = '';
		$patient_name  = $appointment->family_member_name;
		$patient_user  = get_userdata( $appointment->patient_user_id );
		if ( $patient_user ) {
			$patient_email = $patient_user->user_email;
			if ( empty( $patient_name ) ) {
				$patient_name = $patient_user->display_name;
			}
		}

		$time_formatted = mysql2date( 'g:i A', $appointment->appointment_time, false );
		$headers        = array( 'Content-Type: text/plain; charset=UTF-8' );

		// Email to Patient
		if ( ! empty( $patient_email ) ) {
			$subject = sprintf( '[%s] %s', get_bloginfo( 'name' ), __( 'Reminder: Upcoming Consultation in 30 Minutes', 'eg-care' ) );
			$body  = sprintf( __( 'Dear %s,', 'eg-care' ), $patient_name ) . "\r\n\r\n";
			$body .= sprintf( __( 'This is a friendly reminder that your consultation with Dr. %s is scheduled to start in 30 minutes at %s.', 'eg-care' ), $doctor_title, $time_formatted ) . "\r\n\r\n";
			$body .= __( 'Please prepare your camera and microphone, and log in to join the call room on time.', 'eg-care' ) . "\r\n\r\n";
			$body .= __( 'Regards,', 'eg-care' ) . "\r\n";
			$body .= get_bloginfo( 'name' ) . ' ' . __( 'Team', 'eg-care' );

			wp_mail( $patient_email, $subject, $body, $headers );
		}

		// Email to Doctor
		if ( ! empty( $doctor_email ) ) {
			$subject = sprintf( '[%s] %s', get_bloginfo( 'name' ), __( 'Reminder: Upcoming Consultation in 30 Minutes', 'eg-care' ) );
			$body  = sprintf( __( 'Dear Dr. %s,', 'eg-care' ), $doctor_name ) . "\r\n\r\n";
			$body .= sprintf( __( 'This is a friendly reminder that your consultation with patient %s is scheduled to start in 30 minutes at %s.', 'eg-care' ), $patient_name, $time_formatted ) . "\r\n\r\n";
			$body .= __( 'Please log into your doctor dashboard to join the video call room on time.', 'eg-care' ) . "\r\n\r\n";
			$body .= __( 'Regards,', 'eg-care' ) . "\r\n";
			$body .= get_bloginfo( 'name' ) . ' ' . __( 'Team', 'eg-care' );

			wp_mail( $doctor_email, $subject, $body, $headers );
		}
	}
}
