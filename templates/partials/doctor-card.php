<?php
/**
 * Partial: Doctor Card template.
 *
 * @package EG Care
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="eg-care-doctor-card" data-id="{{id}}">
	<div class="eg-care-doctor-card-header">
		<div class="eg-care-doctor-card-avatar-wrap">
			{{avatar}}
			<span class="eg-care-status-indicator {{status_class}}"></span>
		</div>
		<div class="eg-care-doctor-card-meta-summary">
			<h4 class="eg-care-doctor-card-name">{{name}}</h4>
			<p class="eg-care-doctor-card-designation">{{designation}}</p>
			<p class="eg-care-doctor-card-degree">{{degree}}</p>
		</div>
	</div>
	
	<div class="eg-care-doctor-card-body">
		<div class="eg-care-doctor-card-meta-row">
			<span class="meta-item spec-tag">{{specialties}}</span>
			<span class="meta-item rating-tag">
				<svg viewBox="0 0 24 24" fill="currentColor" style="width: 14px; height: 14px; color: #eab308; vertical-align: middle; margin-right: 2px;">
					<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
				</svg>
				{{rating}} ({{reviews}} <?php esc_html_e( 'reviews', 'eg-care' ); ?>)
			</span>
		</div>
		<div class="eg-care-doctor-card-price-row">
			<div class="fee-info">
				<span class="fee-label"><?php esc_html_e( 'Consultation Fee', 'eg-care' ); ?></span>
				<span class="fee-val">{{fee}} BDT</span>
			</div>
			<div class="experience-info">
				<span class="exp-label"><?php esc_html_e( 'Experience', 'eg-care' ); ?></span>
				<span class="exp-val">{{experience}} <?php esc_html_e( 'Yrs', 'eg-care' ); ?></span>
			</div>
		</div>
	</div>

	<div class="eg-care-doctor-card-footer">
		<button type="button" class="eg-care-btn-card-action select-doctor-trigger">
			<?php esc_html_e( 'Select & Book', 'eg-care' ); ?>
		</button>
	</div>
</div>
