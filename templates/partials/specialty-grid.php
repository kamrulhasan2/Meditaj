<?php
/**
 * Partial: Specialty Grid Container and Card template.
 *
 * @package Meditaj
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="meditaj-specialties-container">
	<h3 class="meditaj-step-title"><?php esc_html_e( 'Select a Medical Specialty', 'meditaj' ); ?></h3>
	<div class="meditaj-specialties-grid" id="meditaj-specialties-target">
		<!-- JavaScript will inject cards matching the structure below: -->
	</div>
</div>

<!-- Template for individual Specialty Card -->
<div id="meditaj-tmpl-specialty-card-item" style="display: none;">
	<div class="meditaj-specialty-card" data-slug="{{slug}}">
		<div class="meditaj-specialty-icon">
			{{icon}}
		</div>
		<h4 class="meditaj-specialty-name">{{name}}</h4>
	</div>
</div>
