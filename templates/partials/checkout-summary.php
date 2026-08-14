<?php
/**
 * Partial: Checkout Summary template.
 *
 * @package Meditaj
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="meditaj-checkout-summary-card">
	<h3 class="meditaj-checkout-title"><?php esc_html_e( 'Checkout Summary', 'meditaj' ); ?></h3>
	
	<div class="meditaj-checkout-section">
		<h4 class="meditaj-checkout-subtitle"><?php esc_html_e( 'Doctor & Appointment', 'meditaj' ); ?></h4>
		<div class="meditaj-checkout-item">
			<span class="label"><?php esc_html_e( 'Provider Name', 'meditaj' ); ?></span>
			<span class="value" id="summary-doc-name">-</span>
		</div>
		<div class="meditaj-checkout-item">
			<span class="label"><?php esc_html_e( 'Specialty', 'meditaj' ); ?></span>
			<span class="value" id="summary-doc-specialty">-</span>
		</div>
		<div class="meditaj-checkout-item">
			<span class="label"><?php esc_html_e( 'Booking Type', 'meditaj' ); ?></span>
			<span class="value" id="summary-booking-type">-</span>
		</div>
		<div class="meditaj-checkout-item" id="summary-slot-row">
			<span class="label"><?php esc_html_e( 'Schedule Slot', 'meditaj' ); ?></span>
			<span class="value" id="summary-slot-time">-</span>
		</div>
	</div>

	<div class="meditaj-checkout-section">
		<h4 class="meditaj-checkout-subtitle"><?php esc_html_e( 'Patient Details', 'meditaj' ); ?></h4>
		<div class="meditaj-checkout-item">
			<span class="label"><?php esc_html_e( 'Patient Name', 'meditaj' ); ?></span>
			<span class="value" id="summary-patient-name">-</span>
		</div>
		<div class="meditaj-checkout-item">
			<span class="label"><?php esc_html_e( 'Relation', 'meditaj' ); ?></span>
			<span class="value" id="summary-patient-relation">-</span>
		</div>
	</div>

	<div class="meditaj-checkout-section totals">
		<div class="meditaj-checkout-item">
			<span class="label"><?php esc_html_e( 'Consultation Fee', 'meditaj' ); ?></span>
			<span class="value" id="summary-fee">0.00 BDT</span>
		</div>
		<div class="meditaj-checkout-item">
			<span class="label"><?php esc_html_e( 'Service Charge (5%)', 'meditaj' ); ?></span>
			<span class="value" id="summary-tax">0.00 BDT</span>
		</div>
		<div class="meditaj-checkout-item total-row">
			<span class="label"><?php esc_html_e( 'Total Amount', 'meditaj' ); ?></span>
			<span class="value" id="summary-total">0.00 BDT</span>
		</div>
	</div>
</div>
