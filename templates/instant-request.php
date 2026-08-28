<?php
/**
 * Template: ask for whoever is free.
 *
 * Rendered by the [eg_care_instant_doctor] shortcode. The form, the waiting
 * screen and the "a doctor accepted" screen are all here; instant-request.js
 * shows one at a time.
 *
 * @package EG Care
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$eg_care_instant_fee = \EGCare\Fees::instant_request_fee();
?>

<div class="eg-care-instant-wrapper" id="eg-care-instant-app">

	<!-- ============ 1. the form ============ -->
	<section class="eg-care-instant-panel" id="instant-form-panel">
		<header class="eg-care-instant-head">
			<span class="eg-care-instant-badge"><?php esc_html_e( 'Instant', 'eg-care' ); ?></span>
			<h2><?php esc_html_e( 'Talk to a doctor now', 'eg-care' ); ?></h2>
			<p class="eg-care-instant-lede">
				<?php esc_html_e( 'Tell us what is wrong and the first available doctor will take your call. No appointment, no waiting room.', 'eg-care' ); ?>
			</p>
		</header>

		<div class="eg-care-instant-fee-row">
			<span class="eg-care-instant-fee-label"><?php esc_html_e( 'Consultation fee', 'eg-care' ); ?></span>
			<span class="eg-care-instant-fee-amount" id="instant-fee-amount">
				<?php echo esc_html( number_format_i18n( $eg_care_instant_fee ) ); ?> BDT
			</span>
		</div>
		<p class="eg-care-field-hint">
			<?php esc_html_e( 'You pay only after a doctor accepts. Nothing is charged while you wait.', 'eg-care' ); ?>
		</p>

		<div class="eg-care-field">
			<label for="instant-for"><?php esc_html_e( 'Who is this for?', 'eg-care' ); ?></label>
			<select id="instant-for">
				<option value=""><?php esc_html_e( 'Myself', 'eg-care' ); ?></option>
			</select>
		</div>

		<div class="eg-care-field">
			<label for="instant-contact"><?php esc_html_e( 'Contact Number', 'eg-care' ); ?> *</label>
			<input type="tel" id="instant-contact" placeholder="e.g. 01712345678">
			<span class="eg-care-field-hint"><?php esc_html_e( 'The doctor uses this if the video call drops.', 'eg-care' ); ?></span>
		</div>

		<div class="eg-care-field">
			<label for="instant-symptoms"><?php esc_html_e( 'What is the problem?', 'eg-care' ); ?> *</label>
			<textarea id="instant-symptoms" rows="4" placeholder="<?php esc_attr_e( 'Describe the symptoms, when they started, and anything the doctor should know.', 'eg-care' ); ?>"></textarea>
		</div>

		<p class="eg-care-instant-error" id="instant-error" hidden></p>

		<button type="button" id="instant-submit" class="eg-care-btn-register cyan-btn">
			<?php esc_html_e( 'Request a doctor', 'eg-care' ); ?>
		</button>
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
