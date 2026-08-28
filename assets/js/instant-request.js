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

	// Where a request in progress is remembered, so a reload or a wandering
	// tab comes back to the waiting screen rather than to an empty form.
	const ACTIVE_KEY = 'eg_care_instant_request_id';

	const panels = {
		form: document.getElementById('instant-form-panel'),
		waiting: document.getElementById('instant-waiting-panel'),
		accepted: document.getElementById('instant-accepted-panel'),
		closed: document.getElementById('instant-closed-panel')
	};

	const forSelect = document.getElementById('instant-for');
	const contactInput = document.getElementById('instant-contact');
	const symptomsInput = document.getElementById('instant-symptoms');
	const errorLine = document.getElementById('instant-error');
	const submitBtn = document.getElementById('instant-submit');
	const cancelBtn = document.getElementById('instant-cancel');
	const countdown = document.getElementById('instant-countdown');
	const payBtn = document.getElementById('instant-pay');
	const payError = document.getElementById('instant-pay-error');

	let current = null;
	let pollTimer = null;

	function show(name) {
		Object.keys(panels).forEach(key => {
			panels[key].hidden = key !== name;
		});
	}

	function esc(text) {
		const div = document.createElement('div');
		div.textContent = null === text || undefined === text ? '' : text;
		return div.innerHTML;
	}

	function showError(el, message) {
		el.textContent = message;
		el.hidden = ! message;
	}

	function api(path, options) {
		options = options || {};
		options.headers = Object.assign(
			{ 'X-WP-Nonce': egCareSettings.nonce },
			options.body ? { 'Content-Type': 'application/json' } : {},
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

	// ---- the form -----------------------------------------------------

	// Family members, so a parent can ask on behalf of a child.
	api('patient/me/family')
		.then(members => {
			(members || []).forEach(member => {
				if ( 'self' === member.relation ) {
					return;
				}
				const option = document.createElement('option');
				option.value = member.id;
				option.textContent = member.name + (member.relation ? ' (' + member.relation + ')' : '');
				forSelect.appendChild(option);
			});
		})
		.catch(() => {
			// The list is a convenience; asking for yourself still works.
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
				family_member_id: parseInt(forSelect.value, 10) || 0
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
				submitBtn.disabled = false;
				submitBtn.textContent = 'Request a doctor';
			});
	});

	// ---- waiting ------------------------------------------------------

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
				// Only a status change stops the timer.
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

	// ---- a doctor accepted --------------------------------------------

	payBtn.addEventListener('click', function() {
		if ( ! current || ! current.appointment_id ) {
			return;
		}

		payBtn.disabled = true;
		payBtn.textContent = 'Opening checkout...';
		showError(payError, '');

		api('payment/init', {
			method: 'POST',
			body: JSON.stringify({
				appointment_id: current.appointment_id,
				return_url: window.location.href
			})
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
		show('form');
	});

	// ---- drawing whichever screen matches the request ------------------

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
			document.getElementById('instant-due').textContent = Math.round(current.amount) + ' BDT';
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

	function initials(name) {
		return name.split(' ').filter(part => 'Dr.' !== part)
			.map(part => part[0] || '').join('').substring(0, 2).toUpperCase();
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
