<?php
/**
 * Main Template for Patient Booking Flow.
 *
 * @package EG Care
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( isset( $_GET['eg_care_payment'] ) ) {
	// If the gateway returned via POST, redirect via GET to restore SameSite session cookies and nonces.
	if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
		$redirect_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
		wp_safe_redirect( esc_url_raw( $redirect_url ) );
		exit;
	}

	$payment_status = sanitize_key( $_GET['eg_care_payment'] );
	$appointment_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
	
	global $wpdb;
	$table_appointments = \EGCare\DB::get_table( 'appointments' );
	$appointment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_appointments WHERE id = %d", $appointment_id ) );
	
	if ( $appointment ) {
		// Enforce user ownership of the appointment to prevent IDOR disclosure.
		if ( ! is_user_logged_in() || get_current_user_id() !== intval( $appointment->patient_user_id ) ) {
			wp_die( esc_html__( 'Unauthorized access. You do not have permission to view this appointment.', 'eg-care' ), '', array( 'response' => 403 ) );
		}

		// Local testing helper. SSLCommerz cannot deliver an IPN to a machine that
		// is not reachable from the internet, so this simulates a paid callback on
		// the gateway return - but ONLY when the site owner has deliberately opted
		// in by adding this line to wp-config.php:
		//
		//     define( 'EG_CARE_ALLOW_FAKE_PAYMENTS', true );
		//
		// Never enable it on a public site. With it on, any logged-in patient can
		// confirm their own booking without paying, just by loading the success URL.
		// Environment sniffing is deliberately not used here: WP_DEBUG is routinely
		// left on in production, and REMOTE_ADDR is 127.0.0.1 on any site sitting
		// behind a local reverse proxy, so neither is evidence of a private machine.
		$fake_payments_allowed = defined( 'EG_CARE_ALLOW_FAKE_PAYMENTS' ) && EG_CARE_ALLOW_FAKE_PAYMENTS;

		if ( $fake_payments_allowed && 'pending_payment' === $appointment->status && 'success' === $payment_status ) {
			error_log(
				sprintf(
					'EG Care: simulated payment recorded for appointment #%d by user #%d because EG_CARE_ALLOW_FAKE_PAYMENTS is enabled.',
					$appointment->id,
					get_current_user_id()
				)
			);

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
			$table_transactions = \EGCare\DB::get_table( 'transactions' );
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
			\EGCare\Notifications::send_booking_confirmation_emails( $appointment->id );

			// Fetch the freshly updated appointment object.
			$appointment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_appointments WHERE id = %d", $appointment_id ) );
		}

		$doctor_id = $appointment->doctor_id;
		$doctor_title = get_the_title( $doctor_id );
		$date = $appointment->appointment_date;
		$time = mysql2date( 'g:i A', $appointment->appointment_time, false );
		$patient_name = $appointment->family_member_name ? $appointment->family_member_name : wp_get_current_user()->display_name;
		$relation = $appointment->family_member_relation ? $appointment->family_member_relation : 'Self';
		$amount = $appointment->amount;
		$type = $appointment->appointment_type;
		$status = $appointment->status;
		
		if ( 'success' === $payment_status ) {
			if ( 'paid' === $appointment->payment_status ) {
				?>
				<div class="eg-care-payment-status-container success" style="max-width: 600px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; border: 1px solid #e2e8f0;">
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
						<button type="button" class="eg-care-btn-join-call active" data-id="<?php echo intval( $appointment_id ); ?>" style="width: 100%; padding: 14px; font-weight: bold; font-size: 16px; border-radius: 8px; border: none; cursor: pointer; transition: all 0.2s; background-color: #0f766e; color: #fff;">
							Join Video Call
						</button>
						<a href="<?php echo esc_url( remove_query_arg( array( 'eg_care_payment', 'id' ) ) ); ?>" style="color: #0f766e; text-decoration: none; font-weight: bold; font-size: 14px;">Book Another Appointment</a>
					</div>
				</div>
				<?php
			} else {
				?>
				<div class="eg-care-payment-status-container pending" style="max-width: 600px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; border: 1px solid #e2e8f0;">
					<div style="width: 70px; height: 70px; background: #fef3c7; color: #d97706; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 32px; font-weight: bold;">🕒</div>
					<h2 style="color: #0f172a; margin-bottom: 10px; font-size: 24px;">Verifying Payment...</h2>
					<p style="color: #64748b; margin-bottom: 30px;">We are currently verifying your payment with SSLCommerz. This usually takes a few seconds. Please do not close this window.</p>
					
					<div style="display: flex; flex-direction: column; gap: 12px; align-items: center;">
						<div class="eg-care-loading-spinner small" style="margin-bottom: 15px;"></div>
						<button type="button" onclick="window.location.reload();" class="eg-care-btn-register cyan-btn" style="width: 100%; max-width: 250px; padding: 12px; font-weight: bold; border-radius: 8px;">Check Status Again</button>
					</div>
				</div>
				<?php
			}
		} else {
			?>
			<div class="eg-care-payment-status-container error" style="max-width: 600px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; border: 1px solid #e2e8f0;">
				<div style="width: 70px; height: 70px; background: #fee2e2; color: #dc2626; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; font-size: 32px; font-weight: bold;">✕</div>
				<h2 style="color: #0f172a; margin-bottom: 10px; font-size: 24px;">Payment Failed / Cancelled</h2>
				<p style="color: #64748b; margin-bottom: 30px;">Unfortunately, the transaction could not be completed successfully. Please try again or choose another payment method.</p>
				
				<div style="display: flex; flex-direction: column; gap: 12px;">
					<a href="<?php echo esc_url( remove_query_arg( array( 'eg_care_payment', 'id' ) ) ); ?>" class="eg-care-btn-register cyan-btn" style="display: block; text-decoration: none; padding: 14px; font-weight: bold; font-size: 16px; border-radius: 8px; text-align: center;">Try Again</a>
				</div>
			</div>
			<?php
		}
		return;
	}
}
?>

<div class="eg-care-booking-flow-wrapper">
	<!-- Step Progress Indicator -->
	<div class="eg-care-booking-steps-bar">
		<div class="eg-care-step-indicator active" data-step="1">
			<span class="step-num">1</span>
			<span class="step-label"><?php esc_html_e( 'Select Specialty', 'eg-care' ); ?></span>
		</div>
		<div class="eg-care-step-line"></div>
		<div class="eg-care-step-indicator" data-step="2">
			<span class="step-num">2</span>
			<span class="step-label"><?php esc_html_e( 'Choose Doctor', 'eg-care' ); ?></span>
		</div>
		<div class="eg-care-step-line"></div>
		<div class="eg-care-step-indicator" data-step="3">
			<span class="step-num">3</span>
			<span class="step-label"><?php esc_html_e( 'Details & Payout', 'eg-care' ); ?></span>
		</div>
	</div>

	<!-- Main Shell where JS will inject views -->
	<div id="eg-care-booking-flow-app" class="eg-care-booking-app-body">
		<!-- Loading Spinner -->
		<div class="eg-care-spinner-wrapper">
			<div class="eg-care-loading-spinner"></div>
			<p><?php esc_html_e( 'Loading platform data...', 'eg-care' ); ?></p>
		</div>
	</div>
</div>

<template id="eg-care-tmpl-specialty-grid">
	<?php include EG_CARE_PATH . 'templates/partials/specialty-grid.php'; ?>
</template>

<template id="eg-care-tmpl-doctor-card">
	<?php include EG_CARE_PATH . 'templates/partials/doctor-card.php'; ?>
</template>

<template id="eg-care-tmpl-checkout-summary">
	<?php include EG_CARE_PATH . 'templates/partials/checkout-summary.php'; ?>
</template>
