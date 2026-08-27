/**
 * EG Care Patient Booking Flow Javascript Logic
 *
 * Handles client-side state, steps routing, fetch filters, slots querying,
 * validations, and mock checkout summaries.
 *
 * @package EG Care
 */

document.addEventListener('DOMContentLoaded', function() {
	const app = document.getElementById('eg-care-booking-flow-app');
	if ( ! app ) {
		return;
	}

	// Initialize Translate Helper
	function translate( text ) {
		return text;
	}

	// The percentage the platform adds to the consultation fee. Set in
	// EG Care -> Settings; Fees::service_charge_percentage() is the server side
	// of the same number.
	function serviceChargePct() {
		const pct = parseFloat(egCareSettings && egCareSettings.serviceChargePct);
		return isNaN(pct) ? 5 : pct;
	}

	// The fee actually charged for a booking. A doctor whose instant fee was
	// left at 0 still costs the scheduled fee for a live call - the server
	// applies the same rule in Fees::consultation_fee().
	function serviceFee(doc, bookingType) {
		const scheduled = parseFloat(doc.consultation_fee) || 0;
		if ( 'instant' !== bookingType ) {
			return scheduled;
		}
		const instant = parseFloat(doc.instant_call_fee) || 0;
		return instant > 0 ? instant : scheduled;
	}

	// Helper to escape HTML tags and attributes for security
	function escapeHtml(text) {
		if (typeof text !== 'string') return text;
		return text.replace(/[&<>"']/g, function(m) {
			return {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			}[m];
		});
	}

	// 1. STATE MANAGEMENT
	let state = {
		// The list, not the specialty grid. Choosing a specialty is a filter
		// now, so making it a gate only stood between a visitor and the
		// doctors they came to see.
		step: 2,
		// Chosen specialty slugs, comma separated. A single slug written by an
		// older session is a valid one-item list, so nothing has to migrate.
		specialty: '',
		// 'online', 'scheduled', both, or neither - comma separated like the
		// specialties, and for the same reason: it travels in the URL.
		availability: '',
		search: '',
		doctor: null,
		bookingType: 'scheduled', // 'instant' or 'scheduled'
		date: '',
		slotTime: '',
		patientType: 'self',
		patientName: '',
		patientAge: '',
		patientRelation: '',
		familyMembers: [],
		familyLoaded: false,
		familyMemberId: null, // null means the account holder themselves
		contactNumber: '',
		notes: '',
		files: []
	};

	// The File objects themselves cannot live in sessionStorage, so they are
	// held here and deliberately lost on reload; state.files only carries what
	// the list shows.
	let pendingReports = [];

	/**
	 * How many reports one appointment accepts.
	 *
	 * Published by Shortcodes::enqueue_settings() from the endpoint's own
	 * constant. The fallback matches RestControllerBooking::MAX_REPORTS so a
	 * page served without the setting still stops at the same number.
	 *
	 * @return {number} Maximum number of files.
	 */
	function maxReports() {
		const n = parseInt(egCareSettings.maxReports, 10);
		return ( isNaN(n) || n < 1 ) ? 3 : n;
	}

	/**
	 * The largest report the endpoint will take, in bytes.
	 *
	 * @return {number} Maximum size in bytes.
	 */
	function maxReportBytes() {
		const n = parseInt(egCareSettings.maxReportBytes, 10);
		return ( isNaN(n) || n < 1 ) ? 2 * 1024 * 1024 : n;
	}

	/**
	 * A file size a patient can read, e.g. "1.4 MB".
	 *
	 * @param {number} bytes Size in bytes.
	 * @return {string} Formatted size.
	 */
	function formatBytes(bytes) {
		if ( bytes >= 1024 * 1024 ) {
			return (Math.round(bytes / (1024 * 1024) * 10) / 10) + ' MB';
		}
		return Math.max(1, Math.round(bytes / 1024)) + ' KB';
	}

	/**
	 * Years of experience for the stat tile.
	 *
	 * @param {Object} doc Doctor.
	 * @return {string} Tile value.
	 */
	function statExperience(doc) {
		const years = parseInt(doc.experience_years, 10) || 0;
		return years > 0 ? years + ' Yrs' : '\u2014';
	}

	/**
	 * Patients seen, for the stat tile.
	 *
	 * Counted from completed appointments, so a doctor who has not finished one
	 * yet reads as new rather than as a bare zero.
	 *
	 * @param {Object} doc Doctor.
	 * @return {string} Tile value.
	 */
	function statPatients(doc) {
		const seen = parseInt(doc.patients_count, 10) || 0;
		return seen > 0 ? String(seen) : 'New';
	}

	/**
	 * What a follow-up costs, for the stat tile.
	 *
	 * @param {Object} doc Doctor.
	 * @return {string} Tile value.
	 */
	function statFollowUpValue(doc) {
		const days = parseInt(doc.follow_up_days, 10) || 0;
		if ( days <= 0 ) {
			return '\u2014';
		}
		const cost = parseFloat(doc.follow_up_cost) || 0;
		return cost > 0 ? Math.round(cost) + ' BDT' : 'Free';
	}

	/**
	 * The follow-up tile's label, which carries the window when there is one.
	 *
	 * @param {Object} doc Doctor.
	 * @return {string} Tile label.
	 */
	function statFollowUpLabel(doc) {
		const days = parseInt(doc.follow_up_days, 10) || 0;
		return days > 0 ? 'Follow-up \u00b7 ' + days + ' days' : 'Follow-up';
	}

	// Doctors fetched per page. The endpoint's own default, repeated here only
	// so the Load more button asks for a consistent page size.
	const PER_PAGE = 12;

	/**
	 * Query string names for the list's filters.
	 *
	 * Every one is prefixed. 'specialty' on its own is the taxonomy's query
	 * var, and putting it on an ordinary page's URL makes WordPress try to
	 * resolve that page as a specialty archive - which answers 404. 'search'
	 * and 'availability' are free today but would be a trap to leave unguarded
	 * next to a prefixed one.
	 */
	const URL_PARAMS = {
		specialty: 'eg_specialty',
		availability: 'eg_availability',
		search: 'eg_search'
	};

	/**
	 * Read the list's filters out of the address bar.
	 *
	 * @return {Object|null} The filters found, or null if the URL carries none.
	 */
	function filtersFromUrl() {
		const query = new URLSearchParams(window.location.search);
		let found = false;
		const filters = {};

		Object.keys(URL_PARAMS).forEach(key => {
			const value = query.get(URL_PARAMS[key]);
			if ( null !== value ) {
				filters[key] = value;
				found = true;
			}
		});

		return found ? filters : null;
	}

	/**
	 * Write the list's filters into the address bar.
	 *
	 * replaceState rather than pushState: the search box filters on every
	 * keystroke, and pushing each one would bury the page the visitor arrived
	 * from under a stack of near-identical entries. The address bar still
	 * carries a link worth copying, sharing or reloading.
	 *
	 * The page number is deliberately left out. Load more is not a place you
	 * would send someone, and 'page' is a WordPress query var that would turn
	 * the URL into something else entirely.
	 */
	function filtersToUrl() {
		if ( ! window.history || ! window.history.replaceState ) {
			return;
		}

		const query = new URLSearchParams(window.location.search);

		Object.keys(URL_PARAMS).forEach(key => {
			const value = String(state[key] || '').trim();
			if ( value ) {
				query.set(URL_PARAMS[key], value);
			} else {
				// An empty filter should leave no trace in the URL at all.
				query.delete(URL_PARAMS[key]);
			}
		});

		const search = query.toString();
		window.history.replaceState(
			null,
			'',
			window.location.pathname + ( search ? '?' + search : '' ) + window.location.hash
		);
	}

	/**
	 * The specialty slugs currently ticked in the filter.
	 *
	 * @return {string[]} Slugs, possibly empty.
	 */
	function selectedSpecialties() {
		return String(state.specialty || '')
			.split(',')
			.map(slug => slug.trim())
			.filter(Boolean);
	}

	// Where the doctor list was when it was left, so the doctor's page can
	// offer a way back to the same filtered list rather than to a bare one.
	const LIST_URL_KEY = 'eg_care_list_url';

	/**
	 * Note the list's current address before leaving it.
	 */
	function rememberListUrl() {
		try {
			sessionStorage.setItem(LIST_URL_KEY, window.location.href);
		} catch ( e ) {
			// A browser refusing session storage is not a reason to stop
			// someone booking; they simply lose the back link.
		}
	}

	/**
	 * The list address noted on the way out, if there is one.
	 *
	 * @return {string} A URL, or an empty string.
	 */
	function rememberedListUrl() {
		try {
			return sessionStorage.getItem(LIST_URL_KEY) || '';
		} catch ( e ) {
			return '';
		}
	}

	/**
	 * Whether an availability box should open ticked.
	 *
	 * @param {string} value Either 'online' or 'scheduled'.
	 * @return {boolean} True when that one is part of the current filter.
	 */
	function wantsAvailability(value) {
		return String(state.availability || '').split(',').indexOf(value) !== -1;
	}

	// SecureUploads::allowed_mimes() takes these three and nothing else, so the
	// picker turns anything else away before the appointment is even created.
	const REPORT_MIMES = ['application/pdf', 'image/jpeg', 'image/png'];

	/**
	 * Whether a chosen file is a type the server will accept.
	 *
	 * Some browsers hand back an empty type for a file dragged in or picked on
	 * a phone, so the extension is the fallback rather than an outright no.
	 *
	 * @param {File} f Chosen file.
	 * @return {boolean} True when the type looks acceptable.
	 */
	function reportTypeAllowed(f) {
		if ( f.type && REPORT_MIMES.indexOf(f.type) !== -1 ) {
			return true;
		}
		return ! f.type && /\.(pdf|jpe?g|png)$/i.test(f.name);
	}

	// A doctor's own page renders this flow opened on that doctor. The id comes
	// from the markup rather than the URL, because the page is served by a
	// template, not by a route this script knows about.
	const lockedDoctorId = parseInt(app.getAttribute('data-doctor-id'), 10) || 0;

	// Each doctor page keeps its own state. Sharing one key would mean opening
	// a profile quietly discards a booking half-filled on the main flow - and
	// walking back to that flow would find someone else's doctor in it.
	const STATE_KEY = lockedDoctorId
		? 'eg_care_booking_state_doctor_' + lockedDoctorId
		: 'eg_care_booking_state';

	// Load existing state from sessionStorage if available
	const savedState = sessionStorage.getItem(STATE_KEY);
	if ( savedState ) {
		try {
			state = JSON.parse(savedState);
		} catch ( e ) {
			console.error('Failed to parse saved state:', e);
		}
	}

	// Save state to sessionStorage
	function saveState() {
		sessionStorage.setItem(STATE_KEY, JSON.stringify(state));
	}

	// 2. RENDERING STEPS
	function render() {
		// Update Steps Indicator Bar. The specialty grid is an optional way of
		// narrowing the list rather than a step of its own, so it lights the
		// same lamp the list does.
		const shownStep = state.step < 2 ? 2 : state.step;

		document.querySelectorAll('.eg-care-step-indicator').forEach(el => {
			const s = parseInt(el.getAttribute('data-step'));
			if ( s <= shownStep ) {
				el.classList.add('active');
			} else {
				el.classList.remove('active');
			}
		});

		app.innerHTML = ''; // Clear viewport

		if ( 1 === state.step ) {
			renderStep1();
		} else if ( 2 === state.step ) {
			renderStep2();
		} else if ( 3 === state.step ) {
			renderStep3();
		}
	}

	// STEP 1: Specialty Selection Grid
	function renderStep1() {
		const tmpl = document.getElementById('eg-care-tmpl-specialty-grid');
		if ( ! tmpl ) {
			return;
		}

		const clone = tmpl.content.cloneNode(true);
		app.appendChild(clone);

		// Nobody has to come through here any more, so there has to be a way
		// out that is not the browser's back button.
		const backToList = document.createElement('button');
		backToList.type = 'button';
		backToList.className = 'eg-care-btn-secondary eg-care-back-to-list';
		backToList.textContent = 'Show all doctors';
		backToList.addEventListener('click', function() {
			state.step = 2;
			saveState();
			render();
		});
		app.appendChild(backToList);

		const gridTarget = document.getElementById('eg-care-specialties-target');
		const cardTmpl = document.getElementById('eg-care-tmpl-specialty-card-item');

		// Fetch specialties from endpoint
		fetch(egCareSettings.restUrl + 'specialties')
			.then(res => res.json())
			.then(data => {
				gridTarget.innerHTML = '';
				if ( ! data || 0 === data.length ) {
					gridTarget.innerHTML = '<p class="eg-care-no-data">No specialties available.</p>';
					return;
				}

				data.forEach(spec => {
					const cardClone = cardTmpl.cloneNode(true);
					const card = cardClone.querySelector('.eg-care-specialty-card');
					card.setAttribute('data-slug', spec.slug);
					card.querySelector('.eg-care-specialty-name').textContent = spec.name;

					const iconContainer = card.querySelector('.eg-care-specialty-icon');
					if ( spec.icon_url ) {
						iconContainer.innerHTML = `<img src="${spec.icon_url}" alt="${spec.name}" style="width:32px; height:32px;">`;
					} else {
						// Default SVG icon
						iconContainer.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 32px; height: 32px; color: #0d9488;"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>`;
					}

					// Click handler
					card.addEventListener('click', function() {
						// Arriving from here ticks exactly this one, replacing
						// whatever was ticked before: the visitor just said
						// which specialty they want.
						state.specialty = spec.slug;
						state.step = 2;
						saveState();
						render();
					});

					gridTarget.appendChild(card);
				});
			})
			.catch(err => {
				console.error('Error fetching specialties:', err);
				gridTarget.innerHTML = '<p class="eg-care-error-msg">Error loading specialties. Please try again.</p>';
			});
	}

	// STEP 2: Doctor Listing with Filters
	function renderStep2() {
		const step2Html = `
		<div class="eg-care-step2-wrapper">
			<div class="eg-care-filters-sidebar">
				<h3>${translate('Filters')}</h3>
				<div class="eg-care-filter-group">
					<label for="filter-search">${translate('Search')}</label>
					<input type="text" id="filter-search" placeholder="Doctor name or degree..." value="${escapeHtml(state.search || '')}">
				</div>
				<div class="eg-care-filter-group">
					<span class="eg-care-filter-legend">${translate('Specialty')}</span>
					<div class="eg-care-filter-checks" id="filter-specialty" role="group" aria-label="Specialty">
						<p class="eg-care-filter-loading">Loading specialties...</p>
					</div>
				</div>
				<div class="eg-care-filter-group">
					<span class="eg-care-filter-legend">${translate('Availability')}</span>
					<div class="eg-care-filter-checks" role="group" aria-label="Availability">
						<label class="eg-care-filter-check" for="filter-online">
							<input type="checkbox" id="filter-online" value="online"${wantsAvailability('online') ? ' checked' : ''}>
							<span>${translate('Online now')}</span>
						</label>
						<label class="eg-care-filter-check" for="filter-scheduled">
							<input type="checkbox" id="filter-scheduled" value="scheduled"${wantsAvailability('scheduled') ? ' checked' : ''}>
							<span>${translate('Book later')}</span>
						</label>
					</div>
				</div>
				<div class="eg-care-filter-actions">
					<button type="button" id="btn-reset-filters" class="eg-care-btn-secondary">Reset Filters</button>
					<button type="button" id="btn-back-to-step1" class="eg-care-btn-secondary" style="margin-top:10px;">Browse by Specialty</button>
				</div>
			</div>
			<div class="eg-care-doctors-list-content">
				<div class="eg-care-doctors-section" id="sec-instant-doctors">
					<h3 class="section-title">Instant Specialist Doctors <span class="online-indicator-dot"></span></h3>
					<div class="eg-care-doctors-grid" id="grid-instant-doctors"></div>
				</div>
				<div class="eg-care-doctors-section">
					<h3 class="section-title">
						All Specialists
						<span class="eg-care-result-count" id="doctors-total" hidden></span>
					</h3>
					<div class="eg-care-doctors-grid" id="grid-all-doctors"></div>
					<div class="eg-care-load-more-wrap">
						<button type="button" id="btn-load-more" class="eg-care-btn-secondary" hidden>Load more</button>
					</div>
				</div>
			</div>
		</div>
		`;

		app.innerHTML = step2Html;

		// Specialty is a filter you can tick more than one of, not a gate you
		// have to pass through first. state.specialty holds the chosen slugs as
		// a comma separated list, which a single slug saved by an older session
		// still reads as correctly.
		const specBox = document.getElementById('filter-specialty');

		fetch(egCareSettings.restUrl + 'specialties')
			.then(res => res.json())
			.then(data => {
				specBox.innerHTML = '';

				if ( ! data || ! data.length ) {
					specBox.innerHTML = '<p class="eg-care-filter-loading">No specialties yet.</p>';
					return;
				}

				const chosen = selectedSpecialties();

				data.forEach(spec => {
					const label = document.createElement('label');
					label.className = 'eg-care-filter-check';

					const box = document.createElement('input');
					box.type = 'checkbox';
					box.value = spec.slug;
					box.checked = chosen.indexOf(spec.slug) !== -1;
					box.addEventListener('change', refilter);

					const text = document.createElement('span');
					text.textContent = spec.name;

					label.appendChild(box);
					label.appendChild(text);
					specBox.appendChild(label);
				});
			})
			.catch(() => {
				specBox.innerHTML = '<p class="eg-care-filter-loading">Specialties could not be loaded.</p>';
			});

		const totalLabel = document.getElementById('doctors-total');
		const loadMoreBtn = document.getElementById('btn-load-more');

		// How far through the list we are. Page one replaces what is on screen;
		// every page after it is appended by Load more.
		let listPage = 1;
		let listTotal = 0;

		// The controls write into state, state builds the request and the URL.
		// Reading the DOM in three places instead would let the address bar and
		// the request disagree about what is being asked for.
		function readControlsIntoState() {
			state.specialty = Array.from(specBox.querySelectorAll('input:checked'))
				.map(el => el.value).join(',');
			state.availability = Array.from(
				document.querySelectorAll('#filter-online:checked, #filter-scheduled:checked')
			).map(el => el.value).join(',');
			state.search = document.getElementById('filter-search').value;
			saveState();
			filtersToUrl();
		}

		function currentFilters() {
			const params = ['per_page=' + PER_PAGE];
			const spec = selectedSpecialties().join(',');

			if ( spec ) {
				params.push('specialty=' + encodeURIComponent(spec));
			}
			if ( state.availability ) {
				params.push('availability=' + encodeURIComponent(state.availability));
			}
			if ( state.search ) {
				params.push('search=' + encodeURIComponent(state.search));
			}
			return params;
		}

		// Fetch and filter doctors logic
		function fetchDoctors( append ) {
			listPage = append ? listPage + 1 : 1;

			const url = egCareSettings.restUrl + 'doctors?'
				+ currentFilters().concat('page=' + listPage).join('&');

			const gridInstant = document.getElementById('grid-instant-doctors');
			const gridAll = document.getElementById('grid-all-doctors');

			loadMoreBtn.disabled = true;

			if ( ! append ) {
				gridInstant.innerHTML = '<div class="eg-care-loading-pills"></div>';
				gridAll.innerHTML = '<div class="eg-care-loading-pills"></div>';
				totalLabel.hidden = true;
				loadMoreBtn.hidden = true;
			} else {
				loadMoreBtn.textContent = 'Loading...';
			}

			fetch(url)
				.then(res => {
					// The endpoint reports the size of the whole result set in a
					// header, so the body stays a plain array of doctors.
					listTotal = parseInt(res.headers.get('X-WP-Total'), 10);
					return res.json();
				})
				.then(data => {
					const rows = Array.isArray(data) ? data : [];

					if ( ! append ) {
						gridInstant.innerHTML = '';
						gridAll.innerHTML = '';
					}

					if ( isNaN(listTotal) ) {
						// An older server, or a proxy that dropped the header.
						listTotal = rows.length;
					}

					totalLabel.textContent = listTotal === 1
						? '1 consultant available'
						: listTotal + ' consultants available';
					totalLabel.hidden = false;

					if ( ! append && 0 === rows.length ) {
						gridAll.innerHTML = '<p class="eg-care-no-data">No doctors found matching filters.</p>';
						document.getElementById('sec-instant-doctors').style.display = 'none';
						loadMoreBtn.hidden = true;
						return;
					}

					const cardTmpl = document.getElementById('eg-care-tmpl-doctor-card').content;

					let instantCount = gridInstant.querySelectorAll('.eg-care-doctor-card').length;
					let allCount = gridAll.querySelectorAll('.eg-care-doctor-card').length;

					data.forEach(doc => {
						const cardClone = cardTmpl.cloneNode(true);
						const card = cardClone.querySelector('.eg-care-doctor-card');
						card.setAttribute('data-id', doc.id);

						// The name is a real link, so a profile can be opened in
						// a new tab, bookmarked or sent to someone. Select & Book
						// below still goes straight to the booking step.
						const nameLink = card.querySelector('.eg-care-doctor-card-link');
						nameLink.textContent = doc.name;
						if ( doc.permalink ) {
							nameLink.href = doc.permalink;
						} else {
							nameLink.removeAttribute('href');
						}

						card.querySelector('.eg-care-doctor-card-designation').textContent = doc.designation;
						card.querySelector('.eg-care-doctor-card-degree').textContent = doc.degree;

						const orgLine = card.querySelector('.eg-care-doctor-card-organization');
						if ( doc.organization ) {
							orgLine.textContent = doc.organization;
							orgLine.hidden = false;
						}

						const patientsTag = card.querySelector('.patients-tag');
						const seen = parseInt(doc.patients_count, 10) || 0;
						if ( seen > 0 ) {
							patientsTag.textContent = 1 === seen ? '1 patient' : seen + ' patients';
							patientsTag.hidden = false;
						}

						card.querySelector('.spec-tag').textContent = doc.specialties.join(', ');
						card.querySelector('.fee-val').textContent = doc.consultation_fee + ' BDT';
						card.querySelector('.exp-val').textContent = doc.experience_years + ' Yrs';
						card.querySelector('.rating-tag').innerHTML = `⭐ ${doc.avg_rating} (${doc.total_reviews} reviews)`;

						const indicator = card.querySelector('.eg-care-status-indicator');
						if ( doc.is_online ) {
							indicator.classList.add('online');
						} else {
							indicator.classList.add('offline');
						}

						const avatarContainer = card.querySelector('.eg-care-doctor-card-avatar-wrap');
						const dot = `<span class="eg-care-status-indicator ${doc.is_online ? 'online' : 'offline'}"></span>`;
						let face;
						if ( doc.photo_url ) {
							face = `<img src="${escapeHtml(doc.photo_url)}" class="eg-care-doc-avatar" alt="${escapeHtml(doc.name)}">`;
						} else {
							// Initials placeholder
							const initials = doc.name.split(' ').filter(n => n !== 'Dr.').map(n => n[0]).join('').substring(0, 2).toUpperCase();
							face = `<div class="eg-care-doc-initials">${escapeHtml(initials)}</div>`;
						}
						// The photo leads to the profile as well, which is where
						// most people click first.
						avatarContainer.innerHTML = doc.permalink
							? `<a class="eg-care-doctor-card-photo-link" href="${escapeHtml(doc.permalink)}" tabindex="-1" aria-hidden="true">${face}</a>${dot}`
							: face + dot;

						function openBookingFor() {
							if ( doc.permalink ) {
								// Booking happens on the doctor's own page, so
								// the address bar names who is being booked and
								// the browser's back button returns to this
								// list, filters and all.
								rememberListUrl();
								window.location.href = doc.permalink;
								return;
							}

							// No page to go to - fall back to opening the
							// booking panel here rather than doing nothing.
							state.doctor = doc;
							state.step = 3;
							saveState();
							render();
						}

						// An online doctor is shown twice: once under Available
						// Now and once in the full list. Take the copy before
						// anything is inserted - appending a DocumentFragment
						// empties it, so cloning afterwards would yield nothing
						// and the whole list would come out blank.
						if ( doc.is_online ) {
							const instantClone = cardClone.cloneNode(true);
							instantClone.querySelector('.select-doctor-trigger')
								.addEventListener('click', openBookingFor);
							gridInstant.appendChild(instantClone);
							instantCount++;
						}

						// Listeners are not carried over by cloneNode, so each
						// copy binds its own.
						cardClone.querySelector('.select-doctor-trigger')
							.addEventListener('click', openBookingFor);
						gridAll.appendChild(cardClone);
						allCount++;
					});

					// Hide/show instant container depending on whether any online doctor fits filters
					if ( 0 === instantCount ) {
						document.getElementById('sec-instant-doctors').style.display = 'none';
					} else {
						document.getElementById('sec-instant-doctors').style.display = 'block';
					}

					// Count what is on screen rather than multiplying pages: a
					// short last page would otherwise leave the button offering
					// doctors that are not there.
					loadMoreBtn.hidden = allCount >= listTotal;
					loadMoreBtn.disabled = false;
					loadMoreBtn.textContent = 'Load more';
				})
				.catch(err => {
					console.error('Doctor list failed to load:', err);
					loadMoreBtn.disabled = false;
					loadMoreBtn.textContent = 'Load more';
					if ( ! append ) {
						gridInstant.innerHTML = '';
						gridAll.innerHTML = '<p class="eg-care-no-data">'
							+ 'The doctor list could not be loaded. Please try again.</p>';
						document.getElementById('sec-instant-doctors').style.display = 'none';
					}
				});
		}

		// Bind events to filters. Every one of them starts the list again from
		// page one; keeping the old page number would show page three of a
		// result set the visitor has just changed.
		function refilter() {
			readControlsIntoState();
			fetchDoctors(false);
		}

		document.getElementById('filter-search').addEventListener('input', refilter);
		document.getElementById('filter-online').addEventListener('change', refilter);
		document.getElementById('filter-scheduled').addEventListener('change', refilter);
		loadMoreBtn.addEventListener('click', function() {
			fetchDoctors(true);
		});

		document.getElementById('btn-reset-filters').addEventListener('click', function() {
			document.getElementById('filter-search').value = '';
			specBox.querySelectorAll('input:checked').forEach(el => { el.checked = false; });
			document.getElementById('filter-online').checked = false;
			document.getElementById('filter-scheduled').checked = false;
			refilter();
		});

		document.getElementById('btn-back-to-step1').addEventListener('click', function() {
			state.step = 1;
			saveState();
			render();
		});

		// Trigger initial load. Not through refilter(): the specialty boxes are
		// still on their way, so reading the controls here would blank whatever
		// the URL or the saved session asked for.
		filtersToUrl();
		fetchDoctors(false);
	}

	// STEP 3: Doctor Details & Checkout Panel
	function renderStep3() {
		const doc = state.doctor;
		const todayStr = ymd();
		if ( ! doc ) {
			state.step = 2;
			render();
			return;
		}

		const step3Html = `
		<div class="eg-care-step3-wrapper">
			<div class="eg-care-doctor-detail-panel">
				<div class="eg-care-detail-header">
					<div class="detail-avatar-wrap">
						<!-- Injected via JS -->
					</div>
					<div class="detail-summary">
						<h2 id="detail-name">${escapeHtml(doc.name)}</h2>
						<p id="detail-designation" class="detail-subtitle">${escapeHtml(doc.designation || '')}</p>
						<p id="detail-degree" class="detail-subtitle">${escapeHtml(doc.degree || '')}</p>
						${doc.organization ? `<p id="detail-organization" class="detail-organization">${escapeHtml(doc.organization)}</p>` : ''}
						<div class="detail-rating-row">
							<span class="rating-badge">⭐ ${doc.avg_rating} (${doc.total_reviews} reviews)</span>
						</div>
					</div>
				</div>

				<div class="eg-care-stat-tiles">
					<div class="eg-care-stat-tile">
						<span class="eg-care-stat-value">${statExperience(doc)}</span>
						<span class="eg-care-stat-label">Experience</span>
					</div>
					<div class="eg-care-stat-tile">
						<span class="eg-care-stat-value" id="stat-patients">${statPatients(doc)}</span>
						<span class="eg-care-stat-label">Patients</span>
					</div>
					<div class="eg-care-stat-tile">
						<span class="eg-care-stat-value">${Math.round(parseFloat(doc.consultation_fee) || 0)} BDT</span>
						<span class="eg-care-stat-label">Fee</span>
					</div>
					<div class="eg-care-stat-tile">
						<span class="eg-care-stat-value">${statFollowUpValue(doc)}</span>
						<span class="eg-care-stat-label">${statFollowUpLabel(doc)}</span>
					</div>
				</div>
				<div class="eg-care-detail-body">
					<h3>Biography</h3>
					<p id="detail-bio">${doc.bio || 'No biography text available for this provider.'}</p>
					
					<div class="booking-options-block">
						<h3>Fee Information</h3>
						<div class="eg-care-fee-card">
							<div class="eg-care-fee-card-main">
								<span class="eg-care-fee-card-icon" aria-hidden="true">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<polygon points="23 7 16 12 23 17 23 7"></polygon>
										<rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
									</svg>
								</span>
								<span class="eg-care-fee-card-text">
									<span class="eg-care-fee-card-title">
										Video Call
										${doc.is_online ? '<span class="eg-care-live-badge">LIVE</span>' : ''}
									</span>
									<span class="eg-care-fee-card-status ${doc.is_online ? 'online' : 'offline'}" id="fee-card-status"></span>
								</span>
							</div>
							<span class="eg-care-fee-card-price">
								<span class="eg-care-fee-card-amount" id="fee-card-amount"></span>
								<span class="eg-care-fee-card-note">+ ${serviceChargePct()}% service charge</span>
							</span>
						</div>
						<p class="eg-care-fee-card-help" id="fee-card-help"></p>

						<h3>Select Appointment Type</h3>
						<div class="eg-care-type-cards" role="radiogroup" aria-label="Appointment type">
							<label class="eg-care-type-card ${'instant' === state.bookingType ? 'active' : ''}${doc.is_online ? '' : ' disabled'}" for="btn-type-instant" id="card-type-instant">
								<input type="radio" name="eg-care-booking-type" id="btn-type-instant" value="instant" ${'instant' === state.bookingType ? 'checked' : ''}${doc.is_online ? '' : ' disabled'}>
								<span class="eg-care-type-card-body">
									<span class="eg-care-type-card-title">Book Now</span>
									<span class="eg-care-type-card-desc">Instant live consultation, starting right away</span>
									<span class="eg-care-type-card-note" id="type-instant-note">${doc.is_online ? 'Provider is online now' : 'Provider is currently offline'}</span>
								</span>
							</label>
							<label class="eg-care-type-card ${'scheduled' === state.bookingType ? 'active' : ''}" for="btn-type-scheduled" id="card-type-scheduled">
								<input type="radio" name="eg-care-booking-type" id="btn-type-scheduled" value="scheduled" ${'scheduled' === state.bookingType ? 'checked' : ''}>
								<span class="eg-care-type-card-body">
									<span class="eg-care-type-card-title">Book for Later</span>
									<span class="eg-care-type-card-desc">Schedule a time that suits you</span>
									<span class="eg-care-type-card-note">Pick a date and a slot below</span>
								</span>
							</label>
						</div>

						<div id="booking-calendar-section" class="booking-calendar-wrapper" style="${'scheduled' === state.bookingType ? 'display:block;' : 'display:none;'}">
							<h4 class="eg-care-date-strip-label" id="booking-date-label">Choose Date</h4>
							<div class="eg-care-date-strip">
								<button type="button" class="eg-care-date-strip-nav" id="date-strip-prev" aria-label="Show earlier dates">&#8249;</button>
								<div class="eg-care-date-strip-track" id="date-strip-track" role="group" aria-labelledby="booking-date-label"></div>
								<button type="button" class="eg-care-date-strip-nav" id="date-strip-next" aria-label="Show later dates">&#8250;</button>
							</div>
							<input type="hidden" id="booking-date" value="${state.date}">

							<div id="booking-slots-section" style="display:none; margin-top: 15px;">
								<div class="eg-care-slots-head">
									<h4>Available Slots</h4>
									<p class="eg-care-available-time" id="available-time-range" hidden></p>
								</div>
								<div class="eg-care-slots-grid" id="grid-slots-target"></div>
							</div>
						</div>
					</div>

					<div class="patient-details-block">
						<h3>Who is this appointment for?</h3>
						<div class="eg-care-family-members" id="family-members-target">
							<div class="eg-care-loading-spinner small"></div>
						</div>

						<div class="eg-care-family-form" id="family-add-form" hidden>
							<h4>Add someone</h4>
							<div class="eg-care-fields-grid text-fields">
								<div class="eg-care-field">
									<label for="fm-name">Full name *</label>
									<input type="text" id="fm-name" placeholder="Full name">
								</div>
								<div class="eg-care-field">
									<label for="fm-relation">Relation *</label>
									<select id="fm-relation">
										<option value="father">Father</option>
										<option value="mother">Mother</option>
										<option value="spouse">Spouse</option>
										<option value="son">Son</option>
										<option value="daughter">Daughter</option>
										<option value="brother">Brother</option>
										<option value="sister">Sister</option>
										<option value="other">Other</option>
									</select>
								</div>
								<div class="eg-care-field">
									<label for="fm-dob">Date of birth</label>
									<input type="date" id="fm-dob" max="${todayStr}">
								</div>
								<div class="eg-care-field">
									<label for="fm-gender">Gender</label>
									<select id="fm-gender">
										<option value="">Prefer not to say</option>
										<option value="male">Male</option>
										<option value="female">Female</option>
										<option value="other">Other</option>
									</select>
								</div>
								<div class="eg-care-field">
									<label for="fm-phone">Phone</label>
									<input type="tel" id="fm-phone" placeholder="Optional">
								</div>
								<div class="eg-care-field">
									<label for="fm-email">Email</label>
									<input type="email" id="fm-email" placeholder="Optional">
								</div>
							</div>
							<p class="eg-care-family-form-error" id="fm-error" hidden></p>
							<div class="eg-care-family-form-actions">
								<button type="button" id="fm-save" class="eg-care-btn-register cyan-btn">Save person</button>
								<button type="button" id="fm-cancel" class="eg-care-btn-secondary">Cancel</button>
							</div>
						</div>

						<div class="eg-care-field span-full" style="margin-top: 14px;">
							<label for="patient-contact">Contact Number *</label>
							<input type="tel" id="patient-contact" placeholder="e.g. 01712345678" value="${escapeHtml(state.contactNumber)}">
							<span class="eg-care-field-hint">The doctor's team uses this if the video call drops.</span>
						</div>
						<div class="eg-care-field span-full" style="margin-top: 10px;">
							<label for="patient-notes">Symptom Notes / Reason for Visit</label>
							<textarea id="patient-notes" rows="3" placeholder="Describe symptoms or medical concern...">${state.notes}</textarea>
						</div>
						<div class="eg-care-field span-full" style="margin-top: 10px;">
							<label>Medical Reports (Optional)</label>
							<div class="eg-care-modern-upload-wrapper">
								<label for="patient-files" class="eg-care-modern-upload-label" id="patient-files-label">Add Files</label>
								<input type="file" id="patient-files" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" multiple class="eg-care-hidden-file-input">
								<span class="eg-care-file-name" id="patient-files-hint"></span>
							</div>
							<ul class="eg-care-file-list" id="patient-files-list"></ul>
						</div>
					</div>
				</div>
			</div>
			<div class="eg-care-checkout-panel-wrapper">
				<div id="checkout-summary-target"></div>
				<button type="button" id="btn-confirm-booking" class="eg-care-btn-register cyan-btn" style="width:100%; margin-top:20px;">Confirm Booking</button>
				<button type="button" id="btn-back-to-step2" class="eg-care-btn-secondary" style="width:100%; margin-top: 10px;"${lockedDoctorId ? ' hidden' : ''}>Back to Doctors List</button>
				${lockedDoctorId && rememberedListUrl() ? `<a href="${escapeHtml(rememberedListUrl())}" id="link-back-to-list" class="eg-care-btn-secondary eg-care-back-link">Back to Doctors List</a>` : ''}
			</div>
		</div>
		`;

		app.innerHTML = step3Html;

		// Populate Avatar
		const avatarWrap = app.querySelector('.detail-avatar-wrap');
		if ( doc.photo_url ) {
			avatarWrap.innerHTML = `<img src="${doc.photo_url}" class="eg-care-detail-avatar" alt="${escapeHtml(doc.name)}">`;
		} else {
			const initials = doc.name.split(' ').filter(n => n !== 'Dr.').map(n => n[0]).join('').substring(0, 2).toUpperCase();
			avatarWrap.innerHTML = `<div class="eg-care-detail-avatar-placeholder">${escapeHtml(initials)}</div>`;
		}

		// Booking type cards. The Book Now card is a disabled radio when the
		// provider is offline, so neither a click nor the keyboard can pick it.
		const btnInstant = document.getElementById('btn-type-instant');
		const btnScheduled = document.getElementById('btn-type-scheduled');
		const cardInstant = document.getElementById('card-type-instant');
		const cardScheduled = document.getElementById('card-type-scheduled');
		if ( ! doc.is_online ) {
			btnInstant.disabled = true;
			cardInstant.title = 'Provider is currently offline';
		}

		const feeAmount = document.getElementById('fee-card-amount');
		const feeStatus = document.getElementById('fee-card-status');
		const feeHelp = document.getElementById('fee-card-help');

		function syncTypeCards() {
			const instant = 'instant' === state.bookingType;
			cardInstant.classList.toggle('active', instant);
			cardScheduled.classList.toggle('active', ! instant);
			btnInstant.checked = instant;
			btnScheduled.checked = ! instant;
			document.getElementById('booking-calendar-section').style.display = instant ? 'none' : 'block';

			// The fee card shows what this booking will actually cost, which is
			// not the same figure for both types when a doctor charges more for
			// an instant call.
			feeAmount.textContent = Math.round(serviceFee(doc, state.bookingType)) + ' BDT';

			if ( instant ) {
				feeStatus.textContent = doc.is_online ? 'Available now' : 'Currently offline';
				feeHelp.textContent = 'The doctor joins a video call as soon as the payment goes through.';
			} else {
				feeStatus.textContent = doc.is_online ? 'Online now' : 'Books ahead';
				feeHelp.textContent = 'Pick a date and a time below; the call opens at the slot you choose.';
			}
		}

		btnInstant.addEventListener('change', function() {
			if ( ! doc.is_online ) {
				return;
			}
			state.bookingType = 'instant';
			state.date = '';
			state.slotTime = '';
			saveState();
			syncTypeCards();
			updateCheckoutSummary();
		});

		btnScheduled.addEventListener('change', function() {
			state.bookingType = 'scheduled';
			saveState();
			syncTypeCards();
			updateCheckoutSummary();
		});

		// Fill the fee card for whichever type the flow opens on.
		syncTypeCards();

		// Date selection. The visible control is the strip; the hidden input
		// stays because the confirm handler reads the chosen date from it.
		const dateInput = document.getElementById('booking-date');
		const slotsSection = document.getElementById('booking-slots-section');
		const slotsTarget = document.getElementById('grid-slots-target');
		const rangeLine = document.getElementById('available-time-range');
		const stripTrack = document.getElementById('date-strip-track');
		const stripPrev = document.getElementById('date-strip-prev');
		const stripNext = document.getElementById('date-strip-next');

		const STRIP_DAYS = 7;      // cells shown at once
		const STRIP_MAX_AHEAD = 56; // how far forward the arrows go
		const DAY_NAMES = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
		const MONTH_NAMES = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

		// Days the doctor actually sits on, as ISO weekdays (1 = Monday).
		// Null means "not known yet" — every day stays clickable until the
		// answer arrives, so a slow or failed request never hides real slots.
		let scheduleDays = null;
		let stripOffset = 0;

		const today = startOfDay(new Date());

		function startOfDay(d) {
			return new Date(d.getFullYear(), d.getMonth(), d.getDate());
		}

		function addDays(base, n) {
			const d = startOfDay(base);
			d.setDate(d.getDate() + n);
			return d;
		}

		function isoWeekday(d) {
			return 0 === d.getDay() ? 7 : d.getDay();
		}

		function dayIsOpen(d) {
			return null === scheduleDays || scheduleDays.indexOf(isoWeekday(d)) !== -1;
		}

		// Line the window up with an already-chosen date, so coming back to
		// this step does not show a strip that leaves the selection off-screen.
		if ( state.date ) {
			const chosen = startOfDay(new Date(state.date + 'T00:00:00'));
			const away = Math.round((chosen - today) / 86400000);
			if ( away >= 0 && away < STRIP_MAX_AHEAD + STRIP_DAYS ) {
				stripOffset = Math.floor(away / STRIP_DAYS) * STRIP_DAYS;
			}
		}

		function renderStrip() {
			stripTrack.innerHTML = '';
			for ( let i = 0; i < STRIP_DAYS; i++ ) {
				const d = addDays(today, stripOffset + i);
				const iso = ymd(d);
				const cell = document.createElement('button');
				cell.type = 'button';
				cell.className = 'eg-care-date-cell';
				cell.setAttribute('data-date', iso);
				cell.innerHTML = `<span class="eg-care-date-cell-day">${DAY_NAMES[d.getDay()]}</span>`
					+ `<span class="eg-care-date-cell-num">${d.getDate()}</span>`
					+ `<span class="eg-care-date-cell-mon">${MONTH_NAMES[d.getMonth()]}</span>`;

				if ( iso === ymd(today) ) {
					cell.classList.add('today');
				}

				if ( ! dayIsOpen(d) ) {
					cell.classList.add('closed');
					cell.disabled = true;
					cell.title = 'The provider does not sit on this day';
				} else {
					if ( iso === state.date ) {
						cell.classList.add('active');
						cell.setAttribute('aria-pressed', 'true');
					}
					cell.addEventListener('click', function() {
						dateInput.value = iso;
						state.date = iso;
						state.slotTime = '';
						saveState();
						renderStrip();
						loadSlots();
						updateCheckoutSummary();
					});
				}

				stripTrack.appendChild(cell);
			}

			stripPrev.disabled = 0 === stripOffset;
			stripNext.disabled = stripOffset + STRIP_DAYS > STRIP_MAX_AHEAD;
		}

		stripPrev.addEventListener('click', function() {
			stripOffset = Math.max(0, stripOffset - STRIP_DAYS);
			renderStrip();
		});

		stripNext.addEventListener('click', function() {
			stripOffset = Math.min(STRIP_MAX_AHEAD, stripOffset + STRIP_DAYS);
			renderStrip();
		});

		renderStrip();

		fetch(egCareSettings.restUrl + 'doctors/' + doc.id + '/schedule-days')
			.then(res => res.ok ? res.json() : null)
			.then(data => {
				if ( data && Array.isArray(data.days) ) {
					scheduleDays = data.days;
					renderStrip();
				}
			})
			.catch(() => {
				// Leave every day clickable; the slots request is the real gate.
			});

		function loadSlots() {
			const d = dateInput.value;
			if ( ! d ) {
				slotsSection.style.display = 'none';
				return;
			}

			state.date = d;
			saveState();
			slotsSection.style.display = 'block';
			rangeLine.hidden = true;
			slotsTarget.innerHTML = '<div class="eg-care-loading-spinner small"></div>';

			fetch(egCareSettings.restUrl + 'doctors/' + doc.id + '/slots?date=' + d)
				.then(res => res.json())
				.then(payload => {
					// The endpoint used to answer with a bare array of slots and
					// now wraps them alongside the day's opening hours. Accept
					// both, so a cached script never breaks against a new server.
					const data = Array.isArray(payload) ? { slots: payload } : (payload || {});
					const slots = Array.isArray(data.slots) ? data.slots : [];

					if ( data.start_time && data.end_time ) {
						rangeLine.textContent = 'Available Time: '
							+ formatTime24To12(data.start_time) + ' - ' + formatTime24To12(data.end_time);
						rangeLine.hidden = false;
					}

					slotsTarget.innerHTML = '';
					if ( 0 === slots.length ) {
						slotsTarget.innerHTML = '<p class="eg-care-no-data">No consultation hours available on this date.</p>';
						return;
					}

					slots.forEach(slot => {
						const pill = document.createElement('button');
						pill.type = 'button';
						pill.className = 'eg-care-slot-pill';
						pill.setAttribute('data-time', slot.time);
						pill.textContent = formatTime24To12(slot.time);

						if ( ! slot.available ) {
							pill.classList.add('booked');
							pill.disabled = true;
						} else {
							if ( slot.time === state.slotTime ) {
								pill.classList.add('active');
							}

							pill.addEventListener('click', function() {
								slotsTarget.querySelectorAll('.eg-care-slot-pill').forEach(p => p.classList.remove('active'));
								pill.classList.add('active');
								state.slotTime = slot.time;
								saveState();
								updateCheckoutSummary();
							});
						}

						slotsTarget.appendChild(pill);
					});
				});
		}

		if ( state.date ) {
			loadSlots();
		}

		// Who the appointment is for. "Self" is always first and needs no saved
		// record; everyone else comes from the patient's family list.
		const familyTarget = document.getElementById('family-members-target');
		const addForm = document.getElementById('family-add-form');

		function relationLabel(relation) {
			if ( ! relation ) return '';
			return relation.charAt(0).toUpperCase() + relation.slice(1);
		}

		function memberDetailLine(member) {
			const bits = [];
			if ( null !== member.age && undefined !== member.age ) bits.push(member.age + ' yrs');
			if ( member.gender ) bits.push(relationLabel(member.gender));
			if ( member.phone ) bits.push(member.phone);
			return bits.join(' \u00b7 ');
		}

		function renderFamilyMembers() {
			if ( ! state.familyLoaded ) {
				familyTarget.innerHTML = '<div class="eg-care-loading-spinner small"></div>';
				return;
			}

			const cards = [];

			const selfActive = null === state.familyMemberId ? ' active' : '';
			cards.push(
				'<button type="button" class="eg-care-family-card' + selfActive + '" data-member="self">' +
					'<span class="eg-care-family-badge">Self</span>' +
					'<span class="eg-care-family-name">' + escapeHtml(wp_get_current_user_name()) + '</span>' +
					'<span class="eg-care-family-meta">Booking for yourself</span>' +
				'</button>'
			);

			state.familyMembers.forEach(function(member) {
				if ( 'self' === member.relation ) return; // the card above covers this
				const active = member.id === state.familyMemberId ? ' active' : '';
				cards.push(
					'<button type="button" class="eg-care-family-card' + active + '" data-member="' + member.id + '">' +
						'<span class="eg-care-family-badge alt">' + escapeHtml(relationLabel(member.relation)) + '</span>' +
						'<span class="eg-care-family-name">' + escapeHtml(member.name) + '</span>' +
						'<span class="eg-care-family-meta">' + escapeHtml(memberDetailLine(member)) + '</span>' +
					'</button>'
				);
			});

			cards.push(
				'<button type="button" class="eg-care-family-card add" data-member="add">' +
					'<span class="eg-care-family-add-icon">+</span>' +
					'<span class="eg-care-family-name">Add someone</span>' +
					'<span class="eg-care-family-meta">Parent, child, spouse\u2026</span>' +
				'</button>'
			);

			familyTarget.innerHTML = cards.join('');

			familyTarget.querySelectorAll('.eg-care-family-card').forEach(function(card) {
				card.addEventListener('click', function() {
					const value = card.getAttribute('data-member');

					if ( 'add' === value ) {
						addForm.hidden = false;
						document.getElementById('fm-name').focus();
						return;
					}

					if ( 'self' === value ) {
						state.familyMemberId = null;
						state.patientType = 'self';
						state.patientName = '';
						state.patientAge = '';
					} else {
						const picked = state.familyMembers.find(function(m) { return m.id === parseInt(value, 10); });
						if ( ! picked ) return;
						state.familyMemberId = picked.id;
						state.patientType = picked.relation;
						state.patientName = picked.name;
						state.patientAge = (null === picked.age || undefined === picked.age) ? '' : picked.age;
					}

					addForm.hidden = true;
					saveState();
					renderFamilyMembers();
					updateCheckoutSummary();
				});
			});
		}

		function loadFamilyMembers() {
			fetch(egCareSettings.restUrl + 'patient/me/family', {
				headers: { 'X-WP-Nonce': egCareSettings.nonce }
			})
			.then(function(res) { return res.ok ? res.json() : []; })
			.then(function(members) {
				state.familyMembers = Array.isArray(members) ? members : [];
				state.familyLoaded = true;

				// A member chosen earlier may have been deleted in another tab.
				if ( null !== state.familyMemberId && ! state.familyMembers.some(function(m) { return m.id === state.familyMemberId; }) ) {
					state.familyMemberId = null;
					state.patientType = 'self';
				}

				saveState();
				renderFamilyMembers();
				updateCheckoutSummary();
			})
			.catch(function() {
				state.familyLoaded = true;
				renderFamilyMembers();
			});
		}

		renderFamilyMembers();
		loadFamilyMembers();

		// Add-a-person form.
		const fmError = document.getElementById('fm-error');

		function showFamilyError(message) {
			fmError.textContent = message;
			fmError.hidden = ! message;
		}

		document.getElementById('fm-cancel').addEventListener('click', function() {
			addForm.hidden = true;
			showFamilyError('');
		});

		document.getElementById('fm-save').addEventListener('click', function() {
			const saveBtn = this;
			const payload = {
				name: document.getElementById('fm-name').value,
				relation: document.getElementById('fm-relation').value,
				date_of_birth: document.getElementById('fm-dob').value,
				gender: document.getElementById('fm-gender').value,
				phone: document.getElementById('fm-phone').value,
				email: document.getElementById('fm-email').value
			};

			if ( ! payload.name.trim() ) {
				showFamilyError('Please enter a name.');
				return;
			}

			showFamilyError('');
			saveBtn.disabled = true;
			saveBtn.textContent = 'Saving\u2026';

			fetch(egCareSettings.restUrl + 'patient/me/family', {
				method: 'POST',
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
			.then(function(member) {
				state.familyMembers.push(member);
				state.familyMemberId = member.id;
				state.patientType = member.relation;
				state.patientName = member.name;
				state.patientAge = (null === member.age || undefined === member.age) ? '' : member.age;
				addForm.hidden = true;
				['fm-name', 'fm-dob', 'fm-phone', 'fm-email'].forEach(function(id) {
					document.getElementById(id).value = '';
				});
				saveState();
				renderFamilyMembers();
				updateCheckoutSummary();
			})
			.catch(function(err) {
				showFamilyError(err.message);
			})
			.finally(function() {
				saveBtn.disabled = false;
				saveBtn.textContent = 'Save person';
			});
		});

		// Contact number.
		const contactInput = document.getElementById('patient-contact');
		contactInput.addEventListener('input', function() {
			state.contactNumber = contactInput.value;
			saveState();
		});

		document.getElementById('patient-notes').addEventListener('input', function(e) {
			state.notes = e.target.value;
			saveState();
		});

		// Medical reports: several files, each checked before it joins the list.
		const fileInput = document.getElementById('patient-files');
		const fileListEl = document.getElementById('patient-files-list');
		const fileHint = document.getElementById('patient-files-hint');
		const fileLabel = document.getElementById('patient-files-label');

		// A restored session remembers the names but not the files themselves,
		// so start clean rather than promise an upload that cannot happen.
		if ( state.files.length && ! pendingReports.length ) {
			state.files = [];
			saveState();
		}

		function renderFileList() {
			const cap = maxReports();
			fileListEl.innerHTML = '';

			pendingReports.forEach(function(f, index) {
				const row = document.createElement('li');
				row.className = 'eg-care-file-row';

				const name = document.createElement('span');
				name.className = 'eg-care-file-row-name';
				name.textContent = f.name;

				const size = document.createElement('span');
				size.className = 'eg-care-file-row-size';
				size.textContent = formatBytes(f.size);

				const remove = document.createElement('button');
				remove.type = 'button';
				remove.className = 'eg-care-file-row-remove';
				remove.setAttribute('aria-label', 'Remove ' + f.name);
				remove.textContent = '\u00d7';
				remove.addEventListener('click', function() {
					pendingReports.splice(index, 1);
					syncFiles();
				});

				row.appendChild(name);
				row.appendChild(size);
				row.appendChild(remove);
				fileListEl.appendChild(row);
			});

			const left = cap - pendingReports.length;
			if ( ! pendingReports.length ) {
				fileHint.textContent = 'Up to ' + cap + ' files \u00b7 PDF, JPG or PNG \u00b7 '
					+ formatBytes(maxReportBytes()) + ' each';
			} else if ( left > 0 ) {
				fileHint.textContent = left + ' more can be added';
			} else {
				fileHint.textContent = 'Maximum of ' + cap + ' files reached';
			}

			// Stop at the cap rather than let a fourth file be picked and then
			// refused. The input is stretched invisibly across the whole
			// wrapper, so hiding the label alone would still leave it clickable.
			fileLabel.hidden = left <= 0;
			fileInput.disabled = left <= 0;
		}

		function syncFiles() {
			state.files = pendingReports.map(function(f) {
				return { name: f.name, size: f.size };
			});
			saveState();
			renderFileList();
		}

		fileInput.addEventListener('change', function(e) {
			const picked = Array.prototype.slice.call(e.target.files || []);
			const refused = [];

			picked.forEach(function(f) {
				if ( pendingReports.length >= maxReports() ) {
					refused.push(f.name + ' \u2014 only ' + maxReports() + ' files can be attached');
					return;
				}
				if ( ! reportTypeAllowed(f) ) {
					refused.push(f.name + ' \u2014 only PDF, JPG and PNG are accepted');
					return;
				}
				if ( f.size > maxReportBytes() ) {
					refused.push(f.name + ' \u2014 larger than ' + formatBytes(maxReportBytes()));
					return;
				}
				const already = pendingReports.some(function(p) {
					return p.name === f.name && p.size === f.size;
				});
				if ( already ) {
					refused.push(f.name + ' \u2014 already added');
					return;
				}
				pendingReports.push(f);
			});

			// Clearing the input is what lets a file be picked again after it
			// has been removed from the list; the browser fires no change event
			// for a value it considers unchanged.
			fileInput.value = '';
			syncFiles();

			if ( refused.length ) {
				alert('These files were not added:\n\n' + refused.join('\n'));
			}
		});

		renderFileList();

		// Checkout summary cloning & population
		const summaryTarget = document.getElementById('checkout-summary-target');
		const summaryTmpl = document.getElementById('eg-care-tmpl-checkout-summary').content;

		function updateCheckoutSummary() {
			summaryTarget.innerHTML = '';
			const clone = summaryTmpl.cloneNode(true);

			clone.getElementById('summary-doc-name').textContent = doc.name;
			clone.getElementById('summary-doc-specialty').textContent = doc.specialties.join(', ');
			
			const typeDisplay = clone.getElementById('summary-booking-type');
			const slotRow = clone.getElementById('summary-slot-row');
			if ( 'instant' === state.bookingType ) {
				typeDisplay.textContent = translate('Instant Video Call');
				slotRow.style.display = 'none';
			} else {
				typeDisplay.textContent = translate('Scheduled Booking');
				slotRow.style.display = 'flex';
				const timeVal = state.slotTime ? formatTime24To12(state.slotTime) : '-';
				clone.getElementById('summary-slot-time').textContent = state.date + ' @ ' + timeVal;
			}

			// Patient details
			const patientNameDisplay = clone.getElementById('summary-patient-name');
			const patientRelationDisplay = clone.getElementById('summary-patient-relation');

			if ( 'self' === state.patientType ) {
				patientNameDisplay.textContent = (typeof wp !== 'undefined' && wp.sanitize) ? wp.sanitize.stripTags(wp_get_current_user_name()) : wp_get_current_user_name();
				patientRelationDisplay.textContent = translate('Self');
			} else {
				patientNameDisplay.textContent = state.patientName || '-';
				patientRelationDisplay.textContent = state.patientType.charAt(0).toUpperCase() + state.patientType.slice(1);
			}

			// Calculations. The server recomputes all of this in Fees::breakdown();
			// what is printed here only has to agree with it.
			const fee = serviceFee(doc, state.bookingType);
			const pct = serviceChargePct();
			const tax = Math.round(fee * pct) / 100;
			const total = Math.round((fee + tax) * 100) / 100;

			const taxLabel = clone.getElementById('summary-tax-label');
			if ( taxLabel ) {
				taxLabel.textContent = translate('Service Charge') + ' (' + pct + '%)';
			}

			clone.getElementById('summary-fee').textContent = fee.toFixed(2) + ' BDT';
			clone.getElementById('summary-tax').textContent = tax.toFixed(2) + ' BDT';
			clone.getElementById('summary-total').textContent = total.toFixed(2) + ' BDT';

			summaryTarget.appendChild(clone);
		}

		// Helper to fetch username for summary if logged in
		function wp_get_current_user_name() {
			const adminBar = document.getElementById('wp-admin-bar-my-account');
			if ( adminBar ) {
				const userSpan = adminBar.querySelector('.display-name');
				if ( userSpan ) {
					return userSpan.textContent;
				}
			}
			return 'Patient User';
		}

		updateCheckoutSummary();

		// Back navigation. A doctor's own page has no step two behind it, so the
		// button is hidden there and this listener never fires; a link back to
		// the list the visitor came from takes its place when there was one.
		document.getElementById('btn-back-to-step2').addEventListener('click', function() {
			state.step = 2;
			state.doctor = null;
			state.date = '';
			state.slotTime = '';
			saveState();
			render();
		});

		// Booking Confirmation trigger
		document.getElementById('btn-confirm-booking').addEventListener('click', function() {
			// Synchronize inputs directly from DOM (bypasses browser input cache delays)
			const dateInputEl = document.getElementById('booking-date');
			if ( dateInputEl && dateInputEl.value ) {
				state.date = dateInputEl.value;
			}

			const activeSlotEl = document.querySelector('.eg-care-slot-pill.active');
			if ( activeSlotEl ) {
				state.slotTime = activeSlotEl.getAttribute('data-time');
			}

			// Validation check
			if ( 'scheduled' === state.bookingType ) {
				if ( ! state.date ) {
					alert('Please select an appointment date.');
					return;
				}
				if ( ! state.slotTime ) {
					alert('Please choose an available time slot.');
					return;
				}
			}

			// A saved family member carries its own name and age, so the only
			// thing still to check here is how to reach the patient.
			const contactDigits = String(state.contactNumber || '').replace(/[^0-9]/g, '');
			if ( contactDigits.length < 6 || contactDigits.length > 15 ) {
				alert('Please enter a valid contact number.');
				document.getElementById('patient-contact').focus();
				return;
			}

			// Valid checkout payload compilation
			const payload = {
				doctor_id: doc.id,
				booking_type: state.bookingType,
				date: state.bookingType === 'scheduled' ? state.date : todayStr,
				time: state.bookingType === 'scheduled' ? state.slotTime : new Date().toTimeString().split(' ')[0],
				family_member_id: state.familyMemberId,
				contact_number: state.contactNumber,
				// Still sent so the server can fall back to them when no saved
				// member was chosen.
				patient_relation: state.patientType,
				patient_name: state.patientType === 'self' ? wp_get_current_user_name() : state.patientName,
				patient_age: state.patientType === 'self' ? null : parseInt(state.patientAge),
				notes: state.notes
			};

			const btnConfirm = document.getElementById('btn-confirm-booking');
			btnConfirm.disabled = true;
			btnConfirm.textContent = 'Processing Booking...';

			// 1. Send request to create appointment
			fetch(egCareSettings.restUrl + 'appointments', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': egCareSettings.nonce
				},
				body: JSON.stringify(payload)
			})
			.then(res => {
				if (!res.ok) {
					return res.json().then(err => { throw new Error(err.message || 'Booking conflict or database error.'); });
				}
				return res.json();
			})
			.then(data => {
				const appointmentId = data.appointment_id;

				// The reports are attached to the appointment that now exists, so
				// the server only has to check who owns it.
				return uploadReports(appointmentId, btnConfirm).then(() => {
					btnConfirm.textContent = 'Redirecting to Payment...';

					return fetch(egCareSettings.restUrl + 'payment/init', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': egCareSettings.nonce
						},
						body: JSON.stringify({
							appointment_id: appointmentId,
							return_url: window.location.href
						})
					});
				});
			})
			.then(res => {
				if (!res.ok) {
					return res.json().then(err => { throw new Error(err.message || 'Payment initiation failed.'); });
				}
				return res.json();
			})
			.then(paymentData => {
				// Success: redirect to gateway page
				sessionStorage.removeItem(STATE_KEY);
				window.location.href = paymentData.payment_url;
			})
			.catch(err => {
				console.error('Error during booking integration:', err);
				alert('Booking failed: ' + err.message);
				btnConfirm.disabled = false;
				btnConfirm.textContent = 'Confirm Booking';
			});
		});
	}

	// Sends the chosen reports to the appointment that has just been created.
	// The endpoint takes one file per request, so they go up one after another
	// rather than at once: it appends to the appointment's list, and parallel
	// requests would each read the same list and overwrite one another.
	//
	// A failure here must not cost the patient their booking, so a file that
	// will not go up is reported and the payment step carries on.
	function uploadReports(appointmentId, btnConfirm) {
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

			if ( btnConfirm ) {
				btnConfirm.textContent = queue.length > 1
					? 'Uploading Report ' + (index + 1) + ' of ' + queue.length + '...'
					: 'Uploading Report...';
			}

			const form = new FormData();
			form.append('report', file, file.name);

			// No Content-Type header: the browser has to set the multipart boundary.
			return fetch(egCareSettings.restUrl + 'appointments/' + appointmentId + '/reports', {
				method: 'POST',
				headers: { 'X-WP-Nonce': egCareSettings.nonce },
				body: form
			})
			.then(res => {
				if ( res.ok ) {
					return res.json();
				}
				return res.json().then(err => { throw new Error(err.message || 'The report could not be uploaded.'); });
			})
			.catch(err => {
				console.error('Report upload failed:', err);
				failed.push({ file: file, message: err.message });
			})
			.then(() => sendOne(index + 1));
		}

		return sendOne(0).then(() => {
			// Keep only what did not make it, so nothing is silently forgotten.
			pendingReports = failed.map(f => f.file);

			if ( failed.length ) {
				alert(
					'Your appointment was created, but '
					+ (failed.length > 1 ? 'these reports were' : 'this report was')
					+ ' not attached:\n\n'
					+ failed.map(f => f.file.name + ' \u2014 ' + f.message).join('\n')
					+ '\n\nYou can still share '
					+ (failed.length > 1 ? 'them' : 'it')
					+ ' with the doctor during the call.'
				);
			}
		});
	}

	// Formats 24-hour time "16:30:00" or "16:30" to 12-hour "4:30 PM"
	/**
	 * The calendar date in the visitor's own timezone, as YYYY-MM-DD.
	 *
	 * toISOString() converts to UTC first, so east of Greenwich it names
	 * yesterday for the first hours of every day. The booking strip and the
	 * "is this slot in the past" checks both need the local date instead.
	 *
	 * @param {Date} d Date object, defaults to now.
	 * @return {string} YYYY-MM-DD.
	 */
	function ymd(d) {
		d = d || new Date();
		const mm = String(d.getMonth() + 1).padStart(2, '0');
		const dd = String(d.getDate()).padStart(2, '0');
		return `${d.getFullYear()}-${mm}-${dd}`;
	}

	function formatTime24To12(time24) {
		if (!time24) return '';
		const parts = time24.split(':');
		let hh = parseInt(parts[0], 10);
		const mm = parts[1] ? parts[1].substring(0, 2) : '00';
		const ampm = hh >= 12 ? 'PM' : 'AM';
		hh = hh % 12;
		hh = hh ? hh : 12; // the hour '0' should be '12'
		return `${hh}:${mm} ${ampm}`;
	}

	/**
	 * Open the flow.
	 *
	 * On a doctor's own page there is nothing to choose: the profile and the
	 * booking panel are step three for that one doctor. The saved state is
	 * only reused when it is about the same doctor, so a stale copy cannot put
	 * someone else's name above the booking form.
	 */
	function boot() {
		if ( ! lockedDoctorId ) {
			// A link that names its filters means them. Someone opening a
			// shared URL should see what it describes, not what this browser
			// happened to be looking at earlier in the session.
			const fromUrl = filtersFromUrl();

			if ( fromUrl ) {
				state.specialty = fromUrl.specialty || '';
				state.availability = fromUrl.availability || '';
				state.search = fromUrl.search || '';
				state.step = 2;
				saveState();
			}

			render();
			return;
		}

		const sameDoctor = state.doctor && parseInt(state.doctor.id, 10) === lockedDoctorId;

		state.step = 3;
		if ( ! sameDoctor ) {
			state.doctor = null;
			state.date = '';
			state.slotTime = '';
			state.bookingType = 'scheduled';
		}

		if ( sameDoctor ) {
			// Draw at once from what is saved, then refresh underneath: the fee
			// or the online light may have changed since the page was last open.
			render();
		} else {
			app.innerHTML = '<div class="eg-care-spinner-wrapper"><div class="eg-care-loading-spinner"></div></div>';
		}

		fetch(egCareSettings.restUrl + 'doctors/' + lockedDoctorId)
			.then(res => {
				if ( ! res.ok ) {
					throw new Error('This profile could not be loaded.');
				}
				return res.json();
			})
			.then(doc => {
				state.doctor = doc;
				saveState();
				render();
			})
			.catch(err => {
				console.error('Doctor profile failed to load:', err);
				if ( ! state.doctor ) {
					app.innerHTML = '<p class="eg-care-no-data">'
						+ 'This profile could not be loaded. Please refresh the page and try again.</p>';
				}
			});
	}

	boot();
});
