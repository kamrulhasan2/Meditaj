/**
 * EG Care instant consultation request.
 *
 * The patient asks for whoever is free; this screen sends the request, waits
 * while the queue is open, and hands over to the ordinary checkout once a
 * doctor has accepted.
 *
 * @package EG Care
 */

document.addEventListener('DOMContentLoaded', function() {
	const app = document.getElementById('eg-care-instant-app');
	if ( ! app ) {
		return;
	}

	if ( typeof egCareSettings === 'undefined' || ! egCareSettings.restUrl ) {
		console.error('EG Care: egCareSettings is missing, so an instant request cannot be sent.');
		return;
	}

	// How often the waiting screen asks whether anyone has answered. Two
	// seconds is often enough to feel immediate without hammering the site.
	const POLL_MS = 2000;

	// How often the form re-checks who is on duty. Slower: it is context, not
	// something the patient is waiting on.
	const AVAILABILITY_MS = 30000;

	// Where a request in progress is remembered, so a reload or a wandering
	// tab comes back to the waiting screen rather than to an empty form.
	const ACTIVE_KEY = 'eg_care_instant_request_id';

	const panels = {
		form: document.getElementById('instant-form-panel'),
		waiting: document.getElementById('instant-waiting-panel'),
		accepted: document.getElementById('instant-accepted-panel'),
		closed: document.getElementById('instant-closed-panel')
	};

	const familyTarget = document.getElementById('instant-family-target');
	const changeMemberBtn = document.getElementById('instant-change-member');
	const contactInput = document.getElementById('instant-contact');
	const symptomsInput = document.getElementById('instant-symptoms');
	const fileInput = document.getElementById('instant-files');
	const fileListEl = document.getElementById('instant-files-list');
	const fileHint = document.getElementById('instant-files-hint');
	const fileError = document.getElementById('instant-files-error');
	const dropzone = document.getElementById('instant-dropzone');
	const chipsBlock = document.getElementById('instant-specialty-block');
	const chipsTarget = document.getElementById('instant-specialty-chips');
	const termsBox = document.getElementById('instant-terms');
	const errorLine = document.getElementById('instant-error');
	const submitBtn = document.getElementById('instant-submit');
	const cancelBtn = document.getElementById('instant-cancel');
	const countdown = document.getElementById('instant-countdown');
	const payBtn = document.getElementById('instant-pay');
	const payError = document.getElementById('instant-pay-error');

	let current = null;
	let pollTimer = null;
	let members = [];
	let chosenMemberId = null; // null means the account holder themselves
	let pickingMember = false;
	let chosenSpecialty = '';

	// The File objects cannot survive a reload, and there is no appointment to
	// attach them to until a doctor accepts, so they are held here and sent
	// once there is somewhere to put them.
	let pendingReports = [];

	function show(name) {
		Object.keys(panels).forEach(key => {
			panels[key].hidden = key !== name;
		});
	}

	/**
	 * Escape a value for HTML, attributes included.
	 *
	 * The textContent-into-innerHTML trick this used to do escapes < and &
	 * but leaves quotes alone, which is fine in a text node and an injection
	 * point inside title="..." or src="...". Doctor names come from post
	 * titles, so they are not something to take on trust.
	 *
	 * @param {*} text Value to escape.
	 * @return {string} Escaped text.
	 */
	function esc(text) {
		return String(null === text || undefined === text ? '' : text)
			.replace(/[&<>"']/g, function(ch) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[ch];
			});
	}

	function showError(el, message) {
		el.textContent = message;
		el.hidden = ! message;
	}

	function api(path, options) {
		options = options || {};
		options.headers = Object.assign(
			{ 'X-WP-Nonce': egCareSettings.nonce },
			options.body && 'string' === typeof options.body ? { 'Content-Type': 'application/json' } : {},
			options.headers || {}
		);

		return fetch(egCareSettings.restUrl + path, options).then(res =>
			res.json().then(body => {
				if ( ! res.ok ) {
					throw new Error(body && body.message ? body.message : 'Something went wrong. Please try again.');
				}
				return body;
			})
		);
	}

	function money(amount) {
		return Math.round(parseFloat(amount) || 0) + ' BDT';
	}

	function remember(id) {
		try {
			if ( id ) {
				sessionStorage.setItem(ACTIVE_KEY, String(id));
			} else {
				sessionStorage.removeItem(ACTIVE_KEY);
			}
		} catch ( e ) {
			// Losing the reminder only costs the patient a reload landing on
			// the form; it is not worth failing the request over.
		}
	}

	function rememberedId() {
		try {
			return parseInt(sessionStorage.getItem(ACTIVE_KEY), 10) || 0;
		} catch ( e ) {
			return 0;
		}
	}

	// ---- who the consultation is for -----------------------------------

	function titleCase(text) {
		return text ? text.charAt(0).toUpperCase() + text.slice(1) : '';
	}

	function detailLine(member) {
		const bits = [];
		if ( member.age || 0 === member.age ) {
			bits.push(member.age + ' yrs');
		}
		if ( member.gender ) {
			bits.push(titleCase(member.gender));
		}
		if ( member.phone ) {
			bits.push(member.phone);
		}
		return bits.join(' · ');
	}

	function selfName() {
		const bar = document.getElementById('wp-admin-bar-my-account');
		const name = bar ? bar.querySelector('.display-name') : null;
		return name ? name.textContent.trim() : 'Myself';
	}

	function chosenMember() {
		if ( null === chosenMemberId ) {
			return { id: null, name: selfName(), relation: 'self', meta: 'Booking for yourself' };
		}
		const found = members.filter(m => m.id === chosenMemberId)[0];
		return found
			? { id: found.id, name: found.name, relation: found.relation, meta: detailLine(found) }
			: { id: null, name: selfName(), relation: 'self', meta: 'Booking for yourself' };
	}

	function card(member, active) {
		return '<button type="button" class="eg-care-family-card' + (active ? ' active' : '') + '"'
			+ ' data-member="' + (null === member.id ? 'self' : member.id) + '">'
			+ '<span class="eg-care-family-badge' + ('self' === member.relation ? '' : ' alt') + '">'
			+ esc('self' === member.relation ? 'Self' : titleCase(member.relation)) + '</span>'
			+ '<span class="eg-care-family-name">' + esc(member.name) + '</span>'
			+ '<span class="eg-care-family-meta">' + esc(member.meta) + '</span>'
			+ '</button>';
	}

	function renderFamily() {
		// One card when the choice is made, the whole list while choosing.
		// Shukhee shows the chosen person and a way to change it; a list of
		// six cards above the form would push everything else off the screen.
		if ( ! pickingMember ) {
			familyTarget.innerHTML = card(chosenMember(), true);
			changeMemberBtn.hidden = members.length === 0;
			bindFamilyCards();
			return;
		}

		const cards = [ card({ id: null, name: selfName(), relation: 'self', meta: 'Booking for yourself' },
			null === chosenMemberId) ];

		members.forEach(member => {
			cards.push(card({
				id: member.id,
				name: member.name,
				relation: member.relation,
				meta: detailLine(member)
			}, member.id === chosenMemberId));
		});

		familyTarget.innerHTML = cards.join('');
		changeMemberBtn.hidden = true;
		bindFamilyCards();
	}

	function bindFamilyCards() {
		familyTarget.querySelectorAll('.eg-care-family-card').forEach(el => {
			el.addEventListener('click', function() {
				if ( ! pickingMember ) {
					// The single card is a shortcut into the picker, so tapping
					// the person's name does what it looks like it should.
					pickingMember = true;
					renderFamily();
					return;
				}
				const value = el.getAttribute('data-member');
				chosenMemberId = 'self' === value ? null : parseInt(value, 10);
				pickingMember = false;
				renderFamily();
			});
		});
	}

	changeMemberBtn.addEventListener('click', function() {
		pickingMember = true;
		renderFamily();
	});

	api('patient/me/family')
		.then(list => {
			members = (list || []).filter(m => 'self' !== m.relation);
			renderFamily();
		})
		.catch(() => {
			// The list is a convenience; asking for yourself still works.
			members = [];
			renderFamily();
		});

	// ---- who could answer, and what it costs ---------------------------

	function renderAvailability(data) {
		const count = parseInt(data.doctors_on_duty, 10) || 0;
		const onDuty = document.getElementById('instant-onduty');
		const faces = document.getElementById('instant-faces');
		const note = document.getElementById('instant-onduty-note');

		onDuty.textContent = 0 === count
			? 'No doctor is on duty at the moment'
			: (1 === count ? '1 doctor on duty right now' : count + ' doctors on duty right now');
		onDuty.className = 'eg-care-instant-onduty' + (0 === count ? ' none' : '');

		faces.innerHTML = (data.doctors || []).map(doctor =>
			'<span class="eg-care-instant-face" title="' + esc(doctor.name) + '">'
			+ ( doctor.photo_url
				? '<img src="' + esc(doctor.photo_url) + '" alt="' + esc(doctor.name) + '">'
				: '<span class="eg-care-instant-face-initials">' + esc(initials(doctor.name)) + '</span>' )
			+ '</span>'
		).join('');

		note.textContent = 0 === count
			? 'You can still send the request - it waits ' + data.wait_minutes
				+ ' minutes for someone to come on duty.'
			: 'Your request goes to all of them at once. Whoever accepts first takes the call.';

		if ( data.breakdown ) {
			renderSummary(data.breakdown);
		}
	}

	function renderSummary(breakdown) {
		document.getElementById('summary-instant-fee').textContent = money(breakdown.fee);
		document.getElementById('summary-instant-charge-label').textContent =
			'Service Charge (' + breakdown.percentage + '%)';
		document.getElementById('summary-instant-charge').textContent = money(breakdown.service_charge);
		document.getElementById('summary-instant-total').textContent = money(breakdown.total);
	}

	function initials(name) {
		return String(name || '').split(' ').filter(part => 'Dr.' !== part)
			.map(part => part[0] || '').join('').substring(0, 2).toUpperCase();
	}

	function loadAvailability() {
		api('instant-availability').then(renderAvailability).catch(() => {
			document.getElementById('instant-onduty').textContent = 'Could not check who is on duty.';
		});
	}

	loadAvailability();
	setInterval(function() {
		if ( ! panels.form.hidden && ! document.hidden ) {
			loadAvailability();
		}
	}, AVAILABILITY_MS);

	// ---- what the consultation is about --------------------------------

	api('specialties')
		.then(list => {
			if ( ! list || ! list.length ) {
				return;
			}

			chipsBlock.hidden = false;
			chipsTarget.innerHTML = list.map(spec =>
				'<button type="button" class="eg-care-instant-chip" data-slug="' + esc(spec.slug) + '"'
				+ ' aria-pressed="false">' + esc(spec.name) + '</button>'
			).join('');

			chipsTarget.querySelectorAll('.eg-care-instant-chip').forEach(chip => {
				chip.addEventListener('click', function() {
					const slug = chip.getAttribute('data-slug');
					// Tapping the chosen one again clears it: this is optional,
					// so there has to be a way back to having said nothing.
					chosenSpecialty = chosenSpecialty === slug ? '' : slug;
					chipsTarget.querySelectorAll('.eg-care-instant-chip').forEach(other => {
						const on = other.getAttribute('data-slug') === chosenSpecialty;
						other.classList.toggle('active', on);
						other.setAttribute('aria-pressed', on ? 'true' : 'false');
					});
				});
			});
		})
		.catch(() => {
			// Without the taxonomy the chips simply never appear.
		});

	// ---- medical documents ---------------------------------------------

	function maxReports() {
		const n = parseInt(egCareSettings.maxReports, 10);
		return ( isNaN(n) || n < 1 ) ? 3 : n;
	}

	function maxReportBytes() {
		const n = parseInt(egCareSettings.maxReportBytes, 10);
		return ( isNaN(n) || n < 1 ) ? 2 * 1024 * 1024 : n;
	}

	function formatBytes(bytes) {
		if ( bytes >= 1024 * 1024 ) {
			return (Math.round(bytes / (1024 * 1024) * 10) / 10) + ' MB';
		}
		return Math.max(1, Math.round(bytes / 1024)) + ' KB';
	}

	// SecureUploads::allowed_mimes() takes these three and nothing else.
	const REPORT_MIMES = ['application/pdf', 'image/jpeg', 'image/png'];

	function reportTypeAllowed(f) {
		if ( f.type && REPORT_MIMES.indexOf(f.type) !== -1 ) {
			return true;
		}
		return ! f.type && /\.(pdf|jpe?g|png)$/i.test(f.name);
	}

	function renderFileList() {
		const cap = maxReports();
		fileListEl.innerHTML = '';

		pendingReports.forEach(function(file, index) {
			const row = document.createElement('li');
			row.className = 'eg-care-file-row';

			const name = document.createElement('span');
			name.className = 'eg-care-file-row-name';
			name.textContent = file.name;

			const size = document.createElement('span');
			size.className = 'eg-care-file-row-size';
			size.textContent = formatBytes(file.size);

			const remove = document.createElement('button');
			remove.type = 'button';
			remove.className = 'eg-care-file-row-remove';
			remove.setAttribute('aria-label', 'Remove ' + file.name);
			remove.textContent = '×';
			remove.addEventListener('click', function() {
				pendingReports.splice(index, 1);
				renderFileList();
			});

			row.appendChild(name);
			row.appendChild(size);
			row.appendChild(remove);
			fileListEl.appendChild(row);
		});

		const left = cap - pendingReports.length;
		fileHint.textContent = left > 0
			? 'Maximum ' + cap + ' documents · PDF, JPG or PNG · ' + formatBytes(maxReportBytes()) + ' each'
			: 'Maximum of ' + cap + ' documents reached';

		// The input is stretched invisibly across the dropzone, so it has to be
		// disabled rather than merely hidden once the cap is reached.
		fileInput.disabled = left <= 0;
		dropzone.classList.toggle('full', left <= 0);
	}

	fileInput.addEventListener('change', function(e) {
		const picked = Array.prototype.slice.call(e.target.files || []);
		const refused = [];

		picked.forEach(function(f) {
			if ( pendingReports.length >= maxReports() ) {
				refused.push(f.name + ' — only ' + maxReports() + ' documents can be attached');
				return;
			}
			if ( ! reportTypeAllowed(f) ) {
				refused.push(f.name + ' — only PDF, JPG and PNG are accepted');
				return;
			}
			if ( f.size > maxReportBytes() ) {
				refused.push(f.name + ' — larger than ' + formatBytes(maxReportBytes()));
				return;
			}
			if ( pendingReports.some(p => p.name === f.name && p.size === f.size) ) {
				refused.push(f.name + ' — already added');
				return;
			}
			pendingReports.push(f);
		});

		// Clearing the input is what lets a file be picked again after it has
		// been removed from the list.
		fileInput.value = '';
		renderFileList();

		showError(fileError, refused.length
			? (1 === refused.length ? 'This file was not added: ' : 'These files were not added: ')
				+ refused.join('; ')
			: '');
	});

	renderFileList();

	// ---- sending --------------------------------------------------------

	termsBox.addEventListener('change', function() {
		submitBtn.disabled = ! termsBox.checked;
	});

	submitBtn.addEventListener('click', function() {
		const contact = contactInput.value.trim();
		const symptoms = symptomsInput.value.trim();

		if ( ! contact ) {
			showError(errorLine, 'Please enter a contact number.');
			contactInput.focus();
			return;
		}

		if ( ! symptoms ) {
			showError(errorLine, 'Please describe the problem.');
			symptomsInput.focus();
			return;
		}

		showError(errorLine, '');
		submitBtn.disabled = true;
		submitBtn.textContent = 'Sending...';

		api('instant-requests', {
			method: 'POST',
			body: JSON.stringify({
				contact_number: contact,
				symptoms: symptoms,
				family_member_id: chosenMemberId || 0,
				specialty: chosenSpecialty
			})
		})
			.then(request => {
				current = request;
				remember(request.id);
				render();
				startPolling();
			})
			.catch(err => {
				showError(errorLine, err.message);
			})
			.finally(() => {
				submitBtn.disabled = ! termsBox.checked;
				submitBtn.textContent = 'Request a doctor';
			});
	});

	// ---- waiting --------------------------------------------------------

	function startPolling() {
		stopPolling();
		pollTimer = setInterval(poll, POLL_MS);
	}

	function stopPolling() {
		if ( pollTimer ) {
			clearInterval(pollTimer);
			pollTimer = null;
		}
	}

	function poll() {
		if ( ! current ) {
			stopPolling();
			return;
		}

		api('instant-requests/' + current.id)
			.then(request => {
				current = request;
				render();
			})
			.catch(() => {
				// A single failed poll means nothing; the next one will answer.
			});
	}

	cancelBtn.addEventListener('click', function() {
		if ( ! current ) {
			return;
		}

		cancelBtn.disabled = true;

		api('instant-requests/' + current.id, { method: 'DELETE' })
			.then(request => {
				current = request;
				render();
			})
			.catch(err => {
				// Most likely a doctor accepted between the click and the
				// request; ask the server what actually happened.
				console.error('Cancelling the request failed:', err);
				poll();
			})
			.finally(() => {
				cancelBtn.disabled = false;
			});
	});

	// ---- a doctor accepted ----------------------------------------------

	/**
	 * Send the held documents to the appointment the claim created.
	 *
	 * One request per file, one after another, because the endpoint appends to
	 * the appointment's list and parallel requests would overwrite each other.
	 * A document that will not upload must not cost the patient their
	 * consultation, so a failure is reported and the payment carries on.
	 *
	 * @param {number} appointmentId Appointment the doctor's claim created.
	 * @return {Promise} Resolves once every file has been tried.
	 */
	function uploadReports(appointmentId) {
		if ( ! pendingReports.length ) {
			return Promise.resolve();
		}

		const queue = pendingReports.slice();
		const failed = [];

		function sendOne(index) {
			if ( index >= queue.length ) {
				return Promise.resolve();
			}

			const file = queue[index];
			payBtn.textContent = queue.length > 1
				? 'Sending document ' + (index + 1) + ' of ' + queue.length + '...'
				: 'Sending your document...';

			const form = new FormData();
			form.append('report', file, file.name);

			return api('appointments/' + appointmentId + '/reports', { method: 'POST', body: form })
				.catch(err => {
					console.error('Report upload failed:', err);
					failed.push(file);
				})
				.then(() => sendOne(index + 1));
		}

		return sendOne(0).then(() => {
			pendingReports = failed;

			if ( failed.length ) {
				showError(payError, (failed.length > 1 ? 'These documents were' : 'This document was')
					+ ' not attached: ' + failed.map(f => f.name).join(', ')
					+ '. You can still share ' + (failed.length > 1 ? 'them' : 'it')
					+ ' with the doctor during the call.');
			}
		});
	}

	payBtn.addEventListener('click', function() {
		if ( ! current || ! current.appointment_id ) {
			return;
		}

		payBtn.disabled = true;
		payBtn.textContent = 'Opening checkout...';
		showError(payError, '');

		uploadReports(current.appointment_id)
			.then(() => {
				payBtn.textContent = 'Opening checkout...';
				return api('payment/init', {
					method: 'POST',
					body: JSON.stringify({
						appointment_id: current.appointment_id,
						return_url: window.location.href
					})
				});
			})
			.then(payment => {
				remember(0);
				window.location.href = payment.payment_url;
			})
			.catch(err => {
				showError(payError, err.message);
				payBtn.disabled = false;
				payBtn.textContent = 'Pay and start the call';
			});
	});

	document.getElementById('instant-retry').addEventListener('click', function() {
		current = null;
		remember(0);
		showError(errorLine, '');
		symptomsInput.value = '';
		loadAvailability();
		show('form');
	});

	// ---- drawing whichever screen matches the request --------------------

	function render() {
		if ( ! current ) {
			show('form');
			return;
		}

		if ( 'pending' === current.status ) {
			show('waiting');
			tick();
			return;
		}

		stopPolling();

		if ( 'claimed' === current.status ) {
			remember(0);
			const doctor = current.doctor || {};
			document.getElementById('instant-doctor').innerHTML = doctor.photo_url
				? '<img class="eg-care-instant-avatar" src="' + esc(doctor.photo_url) + '" alt="' + esc(doctor.name) + '">'
				: '<div class="eg-care-instant-avatar placeholder">' + esc(initials(doctor.name || '')) + '</div>';
			document.getElementById('instant-accepted-title').textContent =
				(doctor.name || 'A doctor') + ' has accepted';
			document.getElementById('instant-due').textContent = money(current.amount);
			show('accepted');
			return;
		}

		remember(0);

		document.getElementById('instant-closed-title').textContent = 'cancelled' === current.status
			? 'Request withdrawn'
			: 'No doctor was available';
		document.getElementById('instant-closed-note').textContent = 'cancelled' === current.status
			? 'You cancelled this request. Nothing has been charged.'
			: 'Nobody picked this up in time, and nothing has been charged. You can ask again, or book a doctor for a set time instead.';
		show('closed');
	}

	function tick() {
		if ( ! current ) {
			return;
		}

		const left = Math.max(0, parseInt(current.seconds_left, 10) || 0);
		const minutes = Math.floor(left / 60);
		const seconds = left % 60;

		countdown.textContent = left > 0
			? 'Waiting — ' + minutes + ':' + String(seconds).padStart(2, '0') + ' left'
			: 'Time is up.';
	}

	// The seconds_left the server sent goes stale between polls, so count it
	// down locally and let each poll correct the drift.
	setInterval(function() {
		if ( current && 'pending' === current.status && current.seconds_left > 0 ) {
			current.seconds_left -= 1;
			tick();
		}
	}, 1000);

	// ---- pick up a request already in flight ---------------------------

	const inFlight = rememberedId();

	if ( inFlight ) {
		api('instant-requests/' + inFlight)
			.then(request => {
				current = request;
				render();
				if ( 'pending' === request.status ) {
					startPolling();
				}
			})
			.catch(() => {
				remember(0);
				show('form');
			});
	} else {
		show('form');
	}
});
