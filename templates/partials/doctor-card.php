<?php
/**
 * Partial: Doctor Card template.
 *
 * @package Meditaj
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="meditaj-doctor-card" data-id="{{id}}">
	<div class="meditaj-doctor-card-header">
		<div class="meditaj-doctor-card-avatar-wrap">
			{{avatar}}
			<span class="meditaj-status-indicator {{status_class}}"></span>
		</div>
		<div class="meditaj-doctor-card-meta-summary">
			<h4 class="meditaj-doctor-card-name">{{name}}</h4>
			<p class="meditaj-doctor-card-designation">{{designation}}</p>
			<p class="meditaj-doctor-card-degree">{{degree}}</p>
		</div>
	</div>
	
	<div class="meditaj-doctor-card-body">
		<div class="meditaj-doctor-card-meta-row">
			<span class="meta-item spec-tag">{{specialties}}</span>
			<span class="meta-item rating-tag">
				<svg viewBox="0 0 24 24" fill="currentColor" style="width: 14px; height: 14px; color: #eab308; vertical-align: middle; margin-right: 2px;">
					<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
				</svg>
				{{rating}} ({{reviews}} <?php esc_html_e( 'reviews', 'meditaj' ); ?>)
			</span>
		</div>
		<div class="meditaj-doctor-card-price-row">
			<div class="fee-info">
				<span class="fee-label"><?php esc_html_e( 'Consultation Fee', 'meditaj' ); ?></span>
				<span class="fee-val">{{fee}} BDT</span>
			</div>
			<div class="experience-info">
				<span class="exp-label"><?php esc_html_e( 'Experience', 'meditaj' ); ?></span>
				<span class="exp-val">{{experience}} <?php esc_html_e( 'Yrs', 'meditaj' ); ?></span>
			</div>
		</div>
	</div>

	<div class="meditaj-doctor-card-footer">
		<button type="button" class="meditaj-btn-card-action select-doctor-trigger">
			<?php esc_html_e( 'Select & Book', 'meditaj' ); ?>
		</button>
	</div>
</div>
