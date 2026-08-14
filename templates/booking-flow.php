<?php
/**
 * Main Template for Patient Booking Flow.
 *
 * @package Meditaj
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
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
