<?php
/**
 * Partial: Checkout Summary template.
 *
 * @package EG Care
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="eg-care-checkout-summary-card">
	<h3 class="eg-care-checkout-title"><?php esc_html_e( 'Checkout Summary', 'eg-care' ); ?></h3>
	
	<div class="eg-care-checkout-section">
		<h4 class="eg-care-checkout-subtitle"><?php esc_html_e( 'Doctor & Appointment', 'eg-care' ); ?></h4>
		<div class="eg-care-checkout-item">
			<span class="label"><?php esc_html_e( 'Provider Name', 'eg-care' ); ?></span>
			<span class="value" id="summary-doc-name">-</span>
		</div>
		<div class="eg-care-checkout-item">
			<span class="label"><?php esc_html_e( 'Specialty', 'eg-care' ); ?></span>
			<span class="value" id="summary-doc-specialty">-</span>
		</div>
		<div class="eg-care-checkout-item">
			<span class="label"><?php esc_html_e( 'Booking Type', 'eg-care' ); ?></span>
			<span class="value" id="summary-booking-type">-</span>
		</div>
		<div class="eg-care-checkout-item" id="summary-slot-row">
			<span class="label"><?php esc_html_e( 'Schedule Slot', 'eg-care' ); ?></span>
			<span class="value" id="summary-slot-time">-</span>
		</div>
	</div>

	<div class="eg-care-checkout-section">
		<h4 class="eg-care-checkout-subtitle"><?php esc_html_e( 'Patient Details', 'eg-care' ); ?></h4>
		<div class="eg-care-checkout-item">
			<span class="label"><?php esc_html_e( 'Patient Name', 'eg-care' ); ?></span>
			<span class="value" id="summary-patient-name">-</span>
		</div>
		<div class="eg-care-checkout-item">
			<span class="label"><?php esc_html_e( 'Relation', 'eg-care' ); ?></span>
			<span class="value" id="summary-patient-relation">-</span>
		</div>
	</div>

	<div class="eg-care-checkout-section totals">
		<div class="eg-care-checkout-item">
			<span class="label"><?php esc_html_e( 'Consultation Fee', 'eg-care' ); ?></span>
			<span class="value" id="summary-fee">0.00 BDT</span>
		</div>
		<div class="eg-care-checkout-item">
			<span class="label"><?php esc_html_e( 'Service Charge (5%)', 'eg-care' ); ?></span>
			<span class="value" id="summary-tax">0.00 BDT</span>
		</div>
		<div class="eg-care-checkout-item total-row">
			<span class="label"><?php esc_html_e( 'Total Amount', 'eg-care' ); ?></span>
			<span class="value" id="summary-total">0.00 BDT</span>
		</div>
	</div>
</div>
