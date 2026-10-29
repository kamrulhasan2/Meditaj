<?php
/**
 * The doctor's prescription pane.
 *
 * Two views live in here: the list of consultations waiting to be written up,
 * and the form for one of them. assets/js/doctor-prescription.js swaps between
 * them; the markup is printed here rather than built in JavaScript so that the
 * labels stay translatable and the fields exist before the script runs.
 *
 * @package EG Care
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<!-- Pane: Prescriptions -->
<div class="tab-pane" id="pane-prescriptions">

	<div id="rx-list-view">
		<h3><?php esc_html_e( 'Prescriptions', 'eg-care' ); ?></h3>
		<p class="eg-care-pane-lede">
			<?php esc_html_e( 'Write the prescription here. It goes to a reviewer first, and reaches the patient only after the reviewer approves it.', 'eg-care' ); ?>
		</p>
		<!--
			The signature appears above the doctor's name on every prescription
			they issue. It is optional: the page says the prescription was
			issued electronically, so it is a nicety rather than what makes it
			valid.
		-->
		<div class="rx-signature-strip">
			<span class="rx-sig-label"><?php esc_html_e( 'Your signature', 'eg-care' ); ?></span>
			<img id="rx-sig-preview" src="" alt="" hidden>
			<span id="rx-sig-none" class="rx-sig-none"><?php esc_html_e( 'none yet', 'eg-care' ); ?></span>
			<input type="file" id="rx-sig-file" accept="image/png,image/jpeg,image/webp" hidden>
			<button type="button" class="eg-care-btn-small" id="rx-sig-upload"><?php esc_html_e( 'Upload', 'eg-care' ); ?></button>
			<button type="button" class="eg-care-btn-small" id="rx-sig-remove" hidden><?php esc_html_e( 'Remove', 'eg-care' ); ?></button>
			<span class="rx-sig-state" id="rx-sig-state"></span>
		</div>

		<div class="eg-care-table-responsive" id="rx-list-target">
			<p class="eg-care-no-data"><?php esc_html_e( 'Loading consultations...', 'eg-care' ); ?></p>
		</div>
	</div>

	<div id="rx-form-view" hidden>
		<button type="button" class="eg-care-btn-small rx-back" id="rx-back">&larr; <?php esc_html_e( 'Back to list', 'eg-care' ); ?></button>

		<!--
			A doctor sees the same handful of cases every week. Saving one and
			loading it next time turns a two-minute form into a ten-second one.
		-->
		<div class="rx-template-bar" id="rx-template-bar" hidden>
			<label class="screen-reader-text" for="rx-template-pick"><?php esc_html_e( 'Saved prescriptions', 'eg-care' ); ?></label>
			<select id="rx-template-pick"></select>
			<button type="button" class="eg-care-btn-small" id="rx-template-delete" hidden><?php esc_html_e( 'Delete', 'eg-care' ); ?></button>
			<button type="button" class="eg-care-btn-small" id="rx-template-save"><?php esc_html_e( 'Save as template', 'eg-care' ); ?></button>
		</div>

		<div class="rx-patient-head">
			<div>
				<span class="rx-head-label"><?php esc_html_e( 'Patient', 'eg-care' ); ?></span>
				<strong id="rx-patient"></strong>
			</div>
			<div>
				<span class="rx-head-label"><?php esc_html_e( 'Consultation', 'eg-care' ); ?></span>
				<strong id="rx-when"></strong>
			</div>
		</div>

		<p class="rx-symptoms" id="rx-symptoms" hidden></p>
		<p class="rx-reports" id="rx-reports" hidden></p>

		<div class="rx-returned-note" id="rx-returned-note" hidden></div>

		<div class="eg-care-field">
			<label for="rx-complaints"><?php esc_html_e( 'Chief complaints', 'eg-care' ); ?></label>
			<textarea id="rx-complaints" rows="2" placeholder="<?php esc_attr_e( 'What the patient came with', 'eg-care' ); ?>"></textarea>
		</div>

		<div class="eg-care-field">
			<label for="rx-diagnosis"><?php esc_html_e( 'Diagnosis', 'eg-care' ); ?></label>
			<textarea id="rx-diagnosis" rows="2"></textarea>
		</div>

		<h4 class="rx-section-title">Rx</h4>

		<div class="eg-care-table-responsive">
			<table class="eg-care-dashboard-table rx-medicines-table">
				<thead>
					<tr>
						<th class="rx-num-col">#</th>
						<th><?php esc_html_e( 'Medicine', 'eg-care' ); ?></th>
						<th><?php esc_html_e( 'Dose', 'eg-care' ); ?></th>
						<th><?php esc_html_e( 'Duration', 'eg-care' ); ?></th>
						<th><?php esc_html_e( 'Instruction', 'eg-care' ); ?></th>
						<th><span class="screen-reader-text"><?php esc_html_e( 'Remove', 'eg-care' ); ?></span></th>
					</tr>
				</thead>
				<tbody id="rx-medicines"></tbody>
			</table>
		</div>

		<button type="button" class="eg-care-btn-small" id="rx-add-medicine">+ <?php esc_html_e( 'Add medicine', 'eg-care' ); ?></button>

		<div class="eg-care-field">
			<label for="rx-advice"><?php esc_html_e( 'Advice', 'eg-care' ); ?></label>
			<textarea id="rx-advice" rows="3" placeholder="<?php esc_attr_e( 'Rest, diet, warning signs - Bangla is fine in this box', 'eg-care' ); ?>"></textarea>
		</div>

		<div class="eg-care-field rx-followup">
			<label for="rx-followup"><?php esc_html_e( 'Follow-up date', 'eg-care' ); ?></label>
			<input type="date" id="rx-followup">
		</div>

		<div class="rx-form-footer">
			<span class="rx-save-state" id="rx-save-state"></span>
			<span class="rx-footer-actions">
				<a id="rx-view" class="eg-care-btn-small" href="#" target="_blank" rel="noopener" hidden><?php esc_html_e( 'Open the issued copy', 'eg-care' ); ?></a>
				<!--
					A sent prescription is never edited in place; correcting one
					makes a new version that goes back through review, and the
					patient is then pointed at the newer copy.
				-->
				<button type="button" class="eg-care-btn-small" id="rx-revise" hidden><?php esc_html_e( 'Write a correction', 'eg-care' ); ?></button>
				<button type="button" class="eg-care-btn-register cyan-btn" id="rx-submit"><?php esc_html_e( 'Submit for review', 'eg-care' ); ?></button>
			</span>
		</div>
	</div>

	<!--
		Filled in from the server when a prescription is opened: the doctor's
		own prescribing history first, then whatever the administrator listed.
		A native datalist rather than a hand-built dropdown, so it behaves the
		way the phone's own keyboard expects and there is no widget to break.
	-->
	<datalist id="rx-dl-medicine"></datalist>
	<datalist id="rx-dl-dose"></datalist>
	<datalist id="rx-dl-duration"></datalist>
	<datalist id="rx-dl-instruction"></datalist>
</div>
