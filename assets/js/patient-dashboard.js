/**
 * Patient dashboard: the rate-your-consultation modal.
 *
 * Reads egCareSettings, published by the eg-care-settings handle that
 * Shortcodes::render_patient_dashboard_shortcode() enqueues.
 *
 * @package EG Care
 */

document.addEventListener('DOMContentLoaded', function() {
	// egCareSettings comes from the 'eg-care-settings' handle that
	// Shortcodes::render_patient_dashboard_shortcode() enqueues. Say so out loud
	// rather than failing silently if that ever stops happening.
	if ( typeof egCareSettings === 'undefined' || ! egCareSettings.restUrl ) {
		console.error('EG Care: egCareSettings is missing, so reviews cannot be submitted from this page.');
		return;
	}

	// Rate Doctor click handler for historical completed items
	const rateButtons = document.querySelectorAll('.eg-care-btn-rate-appt');
	rateButtons.forEach(btn => {
		btn.addEventListener('click', function() {
			const apptId = this.getAttribute('data-id');

			// Open the exact same modal dynamically!
			const reviewModal = document.createElement('div');
			reviewModal.id = 'eg-care-review-modal';
			reviewModal.style = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.85); z-index: 2000000; display: flex; align-items: center; justify-content: center; font-family: -apple-system, BlinkMacSystemFont, sans-serif;';

			reviewModal.innerHTML = `
				<div style="background: #fff; padding: 30px; border-radius: 12px; max-width: 450px; width: 90%; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); text-align: center;">
					<h3 style="margin: 0 0 10px; font-size: 20px; color: #0f172a; font-weight: 700;">Rate Your Consultation</h3>
					<p style="color: #64748b; font-size: 14px; margin: 0 0 20px;">Please take a moment to rate your experience with the doctor.</p>

					<!-- Star Rating Row -->
					<div id="star-rating-container" style="display: flex; justify-content: center; gap: 8px; margin-bottom: 20px; font-size: 36px; cursor: pointer; user-select: none;">
						<span class="star" data-value="1" style="color: #cbd5e1; transition: color 0.15s;">★</span>
						<span class="star" data-value="2" style="color: #cbd5e1; transition: color 0.15s;">★</span>
						<span class="star" data-value="3" style="color: #cbd5e1; transition: color 0.15s;">★</span>
						<span class="star" data-value="4" style="color: #cbd5e1; transition: color 0.15s;">★</span>
						<span class="star" data-value="5" style="color: #cbd5e1; transition: color 0.15s;">★</span>
					</div>

					<!-- Feedback Input -->
					<textarea id="review-comment" placeholder="Write a public review (optional)..." style="width: 100%; height: 100px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 20px; font-size: 14px; resize: none; box-sizing: border-box; outline: none;"></textarea>

					<!-- Buttons -->
					<div style="display: flex; gap: 12px; justify-content: flex-end;">
						<button id="btn-skip-review" type="button" style="padding: 10px 16px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; cursor: pointer; color: #475569; font-weight: 600; font-size: 14px;">Cancel</button>
						<button id="btn-submit-review" type="button" disabled style="padding: 10px 20px; border-radius: 6px; border: none; background: #0f766e; color: #fff; cursor: not-allowed; opacity: 0.5; font-weight: 600; font-size: 14px;">Submit</button>
					</div>
				</div>
			`;
			document.body.appendChild(reviewModal);

			let selectedRating = 0;
			const stars = reviewModal.querySelectorAll('.star');
			const submitBtn = reviewModal.querySelector('#btn-submit-review');

			stars.forEach(star => {
				star.addEventListener('mouseover', function() {
					const val = parseInt(this.getAttribute('data-value'));
					stars.forEach(s => {
						const sVal = parseInt(s.getAttribute('data-value'));
						s.style.color = sVal <= val ? '#fbbf24' : '#cbd5e1';
					});
				});

				star.addEventListener('mouseout', function() {
					stars.forEach(s => {
						const sVal = parseInt(s.getAttribute('data-value'));
						s.style.color = sVal <= selectedRating ? '#fbbf24' : '#cbd5e1';
					});
				});

				star.addEventListener('click', function() {
					selectedRating = parseInt(this.getAttribute('data-value'));
					stars.forEach(s => {
						const sVal = parseInt(s.getAttribute('data-value'));
						s.style.color = sVal <= selectedRating ? '#fbbf24' : '#cbd5e1';
					});
					submitBtn.disabled = false;
					submitBtn.style.cursor = 'pointer';
					submitBtn.style.opacity = '1';
				});
			});

			// Skip
			reviewModal.querySelector('#btn-skip-review').addEventListener('click', function() {
				reviewModal.remove();
			});

			// Submit
			submitBtn.addEventListener('click', function() {
				if (selectedRating === 0) return;

				submitBtn.disabled = true;
				submitBtn.textContent = 'Submitting...';
				const comment = reviewModal.querySelector('#review-comment').value.trim();

				fetch(egCareSettings.restUrl + 'appointments/' + apptId + '/reviews', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': egCareSettings.nonce
					},
					body: JSON.stringify({
						rating: selectedRating,
						comment: comment
					})
				})
				.then(res => {
					if (!res.ok) throw new Error('API error');
					return res.json();
				})
				.then(() => {
					reviewModal.remove();
					window.location.reload();
				})
				.catch(err => {
					console.error('Failed to submit review:', err);
					alert('Failed to submit review, please try again.');
					submitBtn.disabled = false;
					submitBtn.textContent = 'Submit';
				});
			});
		});
	});
});
