<?php
/**
 * Template: ask for whoever is free.
 *
 * Rendered by the [eg_care_instant_doctor] shortcode. Two columns: what the
 * patient has to tell us on the left, what they get for it on the right. The
 * form, the waiting screen and the two closing screens all live here, and
 * instant-request.js shows one at a time.
 *
 * @package EG Care
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Paying for an instant consultation sends the patient to the gateway and the
 * gateway sends them back here. Without this the page just drew the empty
 * request form again, so somebody who had paid was left with no confirmation
 * and no way into the call they had just bought.
 */
$eg_care_payment_handled = false;
require EG_CARE_PATH . 'templates/partials/payment-return.php';

if ( $eg_care_payment_handled ) {
	return;
}
?>

<div class="eg-care-instant-wrapper" id="eg-care-instant-app">

	<!-- ============ 1. the form ============ -->
	<section id="instant-form-panel">
		<header class="eg-care-instant-head">
			<span class="eg-care-instant-badge"><?php esc_html_e( 'Instant', 'eg-care' ); ?></span>
			<h2><?php esc_html_e( 'Talk to a doctor now', 'eg-care' ); ?></h2>
			<p class="eg-care-instant-lede">
				<?php esc_html_e( 'Tell us what is wrong and the first available doctor will take your call. No appointment, no waiting room.', 'eg-care' ); ?>
			</p>
		</header>

		<div class="eg-care-instant-columns">

			<!-- ---- left: what we need from the patient ---- -->
			<div class="eg-care-instant-main">

				<div class="eg-care-instant-block">
					<div class="eg-care-instant-block-head">
						<h3><?php esc_html_e( 'Who is this for?', 'eg-care' ); ?></h3>
						<button type="button" class="eg-care-linkish" id="instant-change-member" hidden>
							<?php esc_html_e( 'Change', 'eg-care' ); ?>
						</button>
					</div>
					<div class="eg-care-family-members" id="instant-family-target">
						<p class="eg-care-instant-loading"><?php esc_html_e( 'Loading your list...', 'eg-care' ); ?></p>
					</div>
				</div>

				<div class="eg-care-instant-block">
					<h3><?php esc_html_e( 'Request Information', 'eg-care' ); ?></h3>

					<div class="eg-care-field">
						<label for="instant-contact"><?php esc_html_e( 'Contact Number', 'eg-care' ); ?> *</label>
						<input type="tel" id="instant-contact" placeholder="e.g. 01712345678">
						<span class="eg-care-field-hint"><?php esc_html_e( 'The doctor uses this if the video call drops.', 'eg-care' ); ?></span>
					</div>

					<div class="eg-care-field">
						<label for="instant-symptoms"><?php esc_html_e( 'Reason', 'eg-care' ); ?> *</label>
						<textarea id="instant-symptoms" rows="3" placeholder="<?php esc_attr_e( 'e.g. Fever since last night, not eating', 'eg-care' ); ?>"></textarea>
					</div>

					<div class="eg-care-field">
						<label id="instant-files-label"><?php esc_html_e( 'Medical Documents', 'eg-care' ); ?></label>
						<div class="eg-care-instant-dropzone" id="instant-dropzone">
							<input type="file" id="instant-files" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" multiple class="eg-care-hidden-file-input" aria-labelledby="instant-files-label">
							<span class="eg-care-instant-dropzone-plus" aria-hidden="true">+</span>
							<span class="eg-care-instant-dropzone-text"><?php esc_html_e( 'Select medical documents (prescriptions / lab reports)', 'eg-care' ); ?></span>
							<span class="eg-care-instant-dropzone-hint" id="instant-files-hint"></span>
						</div>
						<ul class="eg-care-file-list" id="instant-files-list"></ul>
						<p class="eg-care-instant-error" id="instant-files-error" hidden></p>
					</div>
				</div>

				<div class="eg-care-instant-block">
					<h3><?php esc_html_e( 'Payment Summary', 'eg-care' ); ?></h3>
					<table class="eg-care-instant-summary">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e( 'Consultation Fee', 'eg-care' ); ?></th>
								<td id="summary-instant-fee">&mdash;</td>
							</tr>
							<tr>
								<th scope="row" id="summary-instant-charge-label"><?php esc_html_e( 'Service Charge', 'eg-care' ); ?></th>
								<td id="summary-instant-charge">&mdash;</td>
							</tr>
							<tr class="total">
								<th scope="row"><?php esc_html_e( 'Total Payable', 'eg-care' ); ?></th>
								<td id="summary-instant-total">&mdash;</td>
							</tr>
						</tbody>
					</table>
					<p class="eg-care-field-hint">
						<?php esc_html_e( 'You pay only after a doctor accepts. Nothing is charged while you wait.', 'eg-care' ); ?>
					</p>
				</div>

			</div>

			<!-- ---- right: what they get, and the way out ---- -->
			<aside class="eg-care-instant-side">

				<div class="eg-care-instant-block">
					<h3><?php esc_html_e( 'Who can answer right now', 'eg-care' ); ?></h3>
					<p class="eg-care-instant-onduty" id="instant-onduty"><?php esc_html_e( 'Checking...', 'eg-care' ); ?></p>
					<div class="eg-care-instant-faces" id="instant-faces"></div>
					<p class="eg-care-field-hint" id="instant-onduty-note"></p>
				</div>

				<div class="eg-care-instant-block" id="instant-specialty-block" hidden>
					<h3><?php esc_html_e( 'What is this about?', 'eg-care' ); ?></h3>
					<p class="eg-care-field-hint"><?php esc_html_e( 'Optional. It tells whoever answers what to expect.', 'eg-care' ); ?></p>
					<div class="eg-care-instant-chips" id="instant-specialty-chips" role="group" aria-label="<?php esc_attr_e( 'Subject', 'eg-care' ); ?>"></div>
				</div>

				<div class="eg-care-instant-block eg-care-instant-submit-block">
					<label class="eg-care-instant-terms" for="instant-terms">
						<input type="checkbox" id="instant-terms">
						<span><?php esc_html_e( 'I understand a doctor will call me back over video, and that I pay only once one accepts.', 'eg-care' ); ?></span>
					</label>

					<p class="eg-care-instant-error" id="instant-error" hidden></p>

					<button type="button" id="instant-submit" class="eg-care-btn-register cyan-btn" disabled>
						<?php esc_html_e( 'Request a doctor', 'eg-care' ); ?>
					</button>
				</div>

			</aside>
		</div>
	</section>

	<!-- ============ 2. waiting ============ -->
	<section class="eg-care-instant-panel eg-care-instant-waiting" id="instant-waiting-panel" hidden>
		<div class="eg-care-instant-pulse" aria-hidden="true"><span></span><span></span><span></span></div>
		<h2><?php esc_html_e( 'Looking for an available doctor', 'eg-care' ); ?></h2>
		<p class="eg-care-instant-lede" id="instant-waiting-note">
			<?php esc_html_e( 'Your request has gone out to every doctor on duty. This usually takes a couple of minutes.', 'eg-care' ); ?>
		</p>
		<p class="eg-care-instant-countdown" id="instant-countdown"></p>
		<button type="button" id="instant-cancel" class="eg-care-btn-secondary">
			<?php esc_html_e( 'Cancel request', 'eg-care' ); ?>
		</button>
	</section>

	<!-- ============ 3. a doctor accepted ============ -->
	<section class="eg-care-instant-panel eg-care-instant-accepted" id="instant-accepted-panel" hidden>
		<div class="eg-care-instant-doctor" id="instant-doctor"></div>
		<h2 id="instant-accepted-title"><?php esc_html_e( 'A doctor has accepted', 'eg-care' ); ?></h2>
		<p class="eg-care-instant-lede">
			<?php esc_html_e( 'Pay the consultation fee and the video call opens straight away.', 'eg-care' ); ?>
		</p>
		<div class="eg-care-instant-fee-row">
			<span class="eg-care-instant-fee-label"><?php esc_html_e( 'Amount due', 'eg-care' ); ?></span>
			<span class="eg-care-instant-fee-amount" id="instant-due"></span>
		</div>
		<p class="eg-care-instant-error" id="instant-pay-error" hidden></p>
		<button type="button" id="instant-pay" class="eg-care-btn-register cyan-btn">
			<?php esc_html_e( 'Pay and start the call', 'eg-care' ); ?>
		</button>
	</section>

	<!-- ============ 4. nobody answered ============ -->
	<section class="eg-care-instant-panel eg-care-instant-closed" id="instant-closed-panel" hidden>
		<h2 id="instant-closed-title"></h2>
		<p class="eg-care-instant-lede" id="instant-closed-note"></p>
		<button type="button" id="instant-retry" class="eg-care-btn-register cyan-btn">
			<?php esc_html_e( 'Ask again', 'eg-care' ); ?>
		</button>
	</section>

</div>
