<?php
/**
 * Partial: Specialty Grid Container and Card template.
 *
 * @package EG Care
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="eg-care-specialties-container">
	<h3 class="eg-care-step-title"><?php esc_html_e( 'Select a Medical Specialty', 'eg-care' ); ?></h3>
	<div class="eg-care-specialties-grid" id="eg-care-specialties-target">
		<!-- JavaScript will inject cards matching the structure below: -->
	</div>
</div>

<!-- Template for individual Specialty Card -->
<div id="eg-care-tmpl-specialty-card-item" style="display: none;">
	<div class="eg-care-specialty-card" data-slug="{{slug}}">
		<div class="eg-care-specialty-icon">
			{{icon}}
		</div>
		<h4 class="eg-care-specialty-name">{{name}}</h4>
	</div>
</div>
