<?php
/**
 * Main Template for Patient Booking Flow.
 *
 * @package EG Care
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * A return from the payment gateway is answered by the shared partial, which
 * prints the confirmation and stops the rest of this page rendering.
 */
$eg_care_payment_handled = false;
require EG_CARE_PATH . 'templates/partials/payment-return.php';

if ( $eg_care_payment_handled ) {
	return;
}
?>

<?php
/**
 * $eg_care_doctor_id is set by Shortcodes::render_booking_shortcode() when the
 * flow is opened on one doctor - their own page, or the shortcode's doctor
 * attribute. It is 0 everywhere else, and the script then starts at step one.
 */
$eg_care_doctor_id = isset( $eg_care_doctor_id ) ? intval( $eg_care_doctor_id ) : 0;
?>

<div class="eg-care-booking-flow-wrapper<?php echo $eg_care_doctor_id ? ' eg-care-single-doctor' : ''; ?>">
	<!-- Step Progress Indicator. Hidden when the flow is opened on one
	     doctor: there are no earlier steps left to walk back through. -->
	<div class="eg-care-booking-steps-bar"<?php echo $eg_care_doctor_id ? ' hidden' : ''; ?>>
		<div class="eg-care-step-indicator active" data-step="2">
			<span class="step-num">1</span>
			<span class="step-label"><?php esc_html_e( 'Choose Doctor', 'eg-care' ); ?></span>
		</div>
		<div class="eg-care-step-line"></div>
		<div class="eg-care-step-indicator" data-step="3">
			<span class="step-num">2</span>
			<span class="step-label"><?php esc_html_e( 'Details & Payout', 'eg-care' ); ?></span>
		</div>
	</div>

	<!-- Main Shell where JS will inject views -->
	<div id="eg-care-booking-flow-app" class="eg-care-booking-app-body" data-doctor-id="<?php echo esc_attr( $eg_care_doctor_id ); ?>">
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
