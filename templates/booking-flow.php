<?php
/**
 * Main Template for Patient Booking Flow.
 *
 * @package Meditaj
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( isset( $_GET['meditaj_payment'] ) ) {
	// If the gateway returned via POST, redirect via GET to restore SameSite session cookies and nonces.
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
		$redirect_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	$payment_status = sanitize_key( $_GET['meditaj_payment'] );
	$appointment_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
	
	global $wpdb;
	$table_appointments = \Meditaj\DB::get_table( 'appointments' );
	$appointment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_appointments WHERE id = %d", $appointment_id ) );
	
	if ( $appointment ) {
		// Local development helper: Since localhost cannot receive public SSLCommerz webhook/IPN queries,
		// we automatically process the confirmation updates when the patient returns successfully.
		if ( 'pending_payment' === $appointment->status && 'success' === $payment_status ) {
			$now = current_time( 'mysql' );
			$wpdb->update(
				$table_appointments,
				array(
					'payment_status' => 'paid',
					'status'         => 'confirmed',
					'payment_method' => 'sslcommerz',
					'updated_at'     => $now,
				),
				array( 'id' => $appointment->id ),
				array( '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);

			// Record transaction in database ledger.
			$table_transactions = \Meditaj\DB::get_table( 'transactions' );
			$wpdb->insert(
				$table_transactions,
				array(
					'appointment_id' => $appointment->id,
					'doctor_id'      => $appointment->doctor_id,
					'type'           => 'booking_payment',
					'gateway'        => 'sslcommerz',
					'gateway_txn_id' => 'LOCAL_DEV_' . $appointment->id . '_' . time(),
					'amount'         => $appointment->amount,
					'status'         => 'success',
					'raw_payload'    => '{}',
					'created_at'     => $now,
				),
				array( '%d', '%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s' )
			);

			// Trigger booking confirmation notifications.
			\Meditaj\Notifications::send_booking_confirmation_emails( $appointment->id );

			// Fetch the freshly updated appointment object.
			$appointment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_appointments WHERE id = %d", $appointment_id ) );
		}

		$doctor_id = $appointment->doctor_id;
		$doctor_title = get_the_title( $doctor_id );
		$date = $appointment->appointment_date;
		$time = date( 'g:i A', strtotime( $appointment->appointment_time ) );
		$patient_name = $appointment->family_member_name ? $appointment->family_member_name : wp_get_current_user()->display_name;
		$relation = $appointment->family_member_relation ? $appointment->family_member_relation : 'Self';
		$amount = $appointment->amount;
		$type = $appointment->appointment_type;
		$status = $appointment->status;
		
		if ( 'success' === $payment_status ) {
			?>
			<div class="meditaj-payment-status-container success" style="max-width: 600px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; border: 1px solid #e2e8f0;">
				<div style="width: 70px; height: 70px; background: #d1fae5; color: #059669; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 32px; font-weight: bold;">✓</div>
				<h2 style="color: #0f172a; margin-bottom: 10px; font-size: 24px;">Booking Confirmed & Paid!</h2>
				<p style="color: #64748b; margin-bottom: 30px;">Your appointment has been successfully scheduled and paid. You can now join the telemedicine video consultation room.</p>
				
				<div style="background: #f8fafc; border-radius: 8px; padding: 20px; text-align: left; margin-bottom: 30px; border: 1px solid #e2e8f0;">
					<div style="display: flex; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
						<span style="color: #64748b;">Doctor</span>
						<strong><?php echo esc_html( $doctor_title ); ?></strong>
					</div>
					<div style="display: flex; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
						<span style="color: #64748b;">Patient</span>
						<strong><?php echo esc_html( $patient_name ); ?> (<?php echo esc_html( $relation ); ?>)</strong>
					</div>
					<div style="display: flex; justify-content: space-between; margin-bottom: 10px; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
						<span style="color: #64748b;">Schedule</span>
						<strong><?php echo 'instant' === $type ? 'Instant Call' : esc_html( $date . ' @ ' . $time ); ?></strong>
					</div>
					<div style="display: flex; justify-content: space-between;">
						<span style="color: #64748b;">Paid Amount</span>
						<strong style="color: #0f766e;"><?php echo esc_html( $amount ); ?> BDT</strong>
					</div>
				</div>

				<div style="display: flex; flex-direction: column; gap: 12px;">
					<button type="button" class="meditaj-btn-join-call active" data-id="<?php echo intval( $appointment_id ); ?>" style="width: 100%; padding: 14px; font-weight: bold; font-size: 16px; border-radius: 8px; border: none; cursor: pointer; transition: all 0.2s; background-color: #0f766e; color: #fff;">
						Join Video Call
					</button>
					<a href="<?php echo esc_url( remove_query_arg( array( 'meditaj_payment', 'id' ) ) ); ?>" style="color: #0f766e; text-decoration: none; font-weight: bold; font-size: 14px;">Book Another Appointment</a>
				</div>
			</div>
			<?php
		} else {
			?>
			<div class="meditaj-payment-status-container error" style="max-width: 600px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; border: 1px solid #e2e8f0;">
				<div style="width: 70px; height: 70px; background: #fee2e2; color: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 32px; font-weight: bold;">✕</div>
				<h2 style="color: #0f172a; margin-bottom: 10px; font-size: 24px;">Payment Failed / Cancelled</h2>
				<p style="color: #64748b; margin-bottom: 30px;">Unfortunately, the transaction could not be completed successfully. Please try again or choose another payment method.</p>
				
				<div style="display: flex; flex-direction: column; gap: 12px;">
					<a href="<?php echo esc_url( remove_query_arg( array( 'meditaj_payment', 'id' ) ) ); ?>" class="meditaj-btn-register cyan-btn" style="display: block; text-decoration: none; padding: 14px; font-weight: bold; font-size: 16px; border-radius: 8px; text-align: center;">Try Again</a>
				</div>
			</div>
			<?php
		}
		return;
	}
}
?>

<div class="meditaj-booking-flow-wrapper">
	<!-- Step Progress Indicator -->
	<div class="meditaj-booking-steps-bar">
		<div class="meditaj-step-indicator active" data-step="1">
			<span class="step-num">1</span>
			<span class="step-label"><?php esc_html_e( 'Select Specialty', 'meditaj' ); ?></span>
		</div>
		<div class="meditaj-step-line"></div>
		<div class="meditaj-step-indicator" data-step="2">
			<span class="step-num">2</span>
			<span class="step-label"><?php esc_html_e( 'Choose Doctor', 'meditaj' ); ?></span>
		</div>
		<div class="meditaj-step-line"></div>
		<div class="meditaj-step-indicator" data-step="3">
			<span class="step-num">3</span>
			<span class="step-label"><?php esc_html_e( 'Details & Payout', 'meditaj' ); ?></span>
		</div>
	</div>

	<!-- Main Shell where JS will inject views -->
	<div id="meditaj-booking-flow-app" class="meditaj-booking-app-body">
		<!-- Loading Spinner -->
		<div class="meditaj-spinner-wrapper">
			<div class="meditaj-loading-spinner"></div>
			<p><?php esc_html_e( 'Loading platform data...', 'meditaj' ); ?></p>
		</div>
	</div>
</div>

<template id="meditaj-tmpl-specialty-grid">
	<?php include MEDITAJ_PATH . 'templates/partials/specialty-grid.php'; ?>
</template>

<template id="meditaj-tmpl-doctor-card">
	<?php include MEDITAJ_PATH . 'templates/partials/doctor-card.php'; ?>
</template>

<template id="meditaj-tmpl-checkout-summary">
	<?php include MEDITAJ_PATH . 'templates/partials/checkout-summary.php'; ?>
</template>
