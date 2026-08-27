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

	// ------------------------------------------------------------------
	// Family members
	//
	// The booking flow can add people, but only here can they be corrected or
	// removed. Deleting someone never touches past appointments: the name, age
	// and relation were copied onto the appointment when it was booked.
	// ------------------------------------------------------------------

	const familyList = document.getElementById('eg-care-family-list');

	if ( familyList ) {
		const editor = document.getElementById('eg-care-family-editor');
		const editorTitle = document.getElementById('eg-care-family-editor-title');
		const errorBox = document.getElementById('egfm-error');
		const fields = {
			id: document.getElementById('egfm-id'),
			name: document.getElementById('egfm-name'),
			relation: document.getElementById('egfm-relation'),
			dob: document.getElementById('egfm-dob'),
			gender: document.getElementById('egfm-gender'),
			phone: document.getElementById('egfm-phone'),
			email: document.getElementById('egfm-email')
		};

		let members = [];

		function esc(text) {
			return String(text === null || text === undefined ? '' : text).replace(/[&<>"']/g, function(m) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
			});
		}

		function titleCase(value) {
			if ( ! value ) return '';
			return value.charAt(0).toUpperCase() + value.slice(1);
		}

		function detailLine(member) {
			const bits = [];
			if ( member.age !== null && member.age !== undefined ) bits.push(member.age + ' yrs');
			if ( member.gender ) bits.push(titleCase(member.gender));
			if ( member.phone ) bits.push(member.phone);
			if ( member.email ) bits.push(member.email);
			return bits.join(' \u00b7 ');
		}

		function showError(message) {
			errorBox.textContent = message || '';
			errorBox.hidden = ! message;
		}

		function closeEditor() {
			editor.hidden = true;
			showError('');
		}

		function openEditor(member) {
			showError('');
			fields.id.value = member ? member.id : '';
			fields.name.value = member ? member.name : '';
			fields.relation.value = member ? member.relation : 'father';
			fields.dob.value = member && member.date_of_birth ? member.date_of_birth : '';
			fields.gender.value = member && member.gender ? member.gender : '';
			fields.phone.value = member && member.phone ? member.phone : '';
			fields.email.value = member && member.email ? member.email : '';
			editorTitle.textContent = member ? 'Edit ' + member.name : 'Add someone';
			editor.hidden = false;
			fields.name.focus();
		}

		function render() {
			if ( ! members.length ) {
				familyList.innerHTML = '<p style="color:#64748b;font-size:14px;margin:0;">No one saved yet. Add the people you book consultations for.</p>';
				return;
			}

			familyList.innerHTML = '<div class="eg-care-family-members">' + members.map(function(member) {
				return '<div class="eg-care-family-card" style="cursor:default;">' +
					'<span class="eg-care-family-badge alt">' + esc(titleCase(member.relation)) + '</span>' +
					'<span class="eg-care-family-name">' + esc(member.name) + '</span>' +
					'<span class="eg-care-family-meta">' + esc(detailLine(member)) + '</span>' +
					'<span class="eg-care-family-actions">' +
						'<button type="button" class="eg-care-family-edit" data-id="' + member.id + '">Edit</button>' +
						'<button type="button" class="eg-care-family-delete" data-id="' + member.id + '">Remove</button>' +
					'</span>' +
				'</div>';
			}).join('') + '</div>';

			familyList.querySelectorAll('.eg-care-family-edit').forEach(function(btn) {
				btn.addEventListener('click', function() {
					const id = parseInt(btn.getAttribute('data-id'), 10);
					const member = members.find(function(m) { return m.id === id; });
					if ( member ) openEditor(member);
				});
			});

			familyList.querySelectorAll('.eg-care-family-delete').forEach(function(btn) {
				btn.addEventListener('click', function() {
					const id = parseInt(btn.getAttribute('data-id'), 10);
					const member = members.find(function(m) { return m.id === id; });
					if ( ! member ) return;

					// Deliberately a confirm(): removing someone is not undoable
					// from this screen.
					if ( ! window.confirm('Remove ' + member.name + ' from your list? Past appointments are not affected.') ) {
						return;
					}

					btn.disabled = true;

					fetch(egCareSettings.restUrl + 'patient/me/family/' + id, {
						method: 'DELETE',
						headers: { 'X-WP-Nonce': egCareSettings.nonce }
					})
					.then(function(res) {
						if ( ! res.ok ) {
							return res.json().then(function(body) { throw new Error(body.message || 'Could not remove this person.'); });
						}
						members = members.filter(function(m) { return m.id !== id; });
						render();
					})
					.catch(function(err) {
						btn.disabled = false;
						window.alert(err.message);
					});
				});
			});
		}

		function load() {
			fetch(egCareSettings.restUrl + 'patient/me/family', {
				headers: { 'X-WP-Nonce': egCareSettings.nonce }
			})
			.then(function(res) { return res.ok ? res.json() : []; })
			.then(function(list) {
				members = Array.isArray(list) ? list : [];
				render();
			})
			.catch(function() {
				familyList.innerHTML = '<p style="color:#b91c1c;font-size:14px;margin:0;">Could not load your family members. Please refresh the page.</p>';
			});
		}

		document.getElementById('eg-care-family-add-trigger').addEventListener('click', function() {
			openEditor(null);
		});

		document.getElementById('egfm-cancel').addEventListener('click', closeEditor);

		document.getElementById('egfm-save').addEventListener('click', function() {
			const saveBtn = this;
			const id = fields.id.value ? parseInt(fields.id.value, 10) : 0;

			if ( ! fields.name.value.trim() ) {
				showError('Please enter a name.');
				fields.name.focus();
				return;
			}

			const payload = {
				name: fields.name.value,
				relation: fields.relation.value,
				date_of_birth: fields.dob.value,
				gender: fields.gender.value,
				phone: fields.phone.value,
				email: fields.email.value
			};

			showError('');
			saveBtn.disabled = true;
			saveBtn.textContent = 'Saving\u2026';

			fetch(egCareSettings.restUrl + 'patient/me/family' + ( id ? '/' + id : '' ), {
				method: id ? 'PUT' : 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': egCareSettings.nonce
				},
				body: JSON.stringify(payload)
			})
			.then(function(res) {
				return res.json().then(function(body) {
					if ( ! res.ok ) throw new Error(body.message || 'Could not save this person.');
					return body;
				});
			})
			.then(function(saved) {
				if ( id ) {
					members = members.map(function(m) { return m.id === saved.id ? saved : m; });
				} else {
					members.push(saved);
				}
				closeEditor();
				render();
			})
			.catch(function(err) {
				showError(err.message);
			})
			.finally(function() {
				saveBtn.disabled = false;
				saveBtn.textContent = 'Save';
			});
		});

		load();
	}
});
