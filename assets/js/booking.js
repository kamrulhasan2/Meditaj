/**
 * Meditaj Patient Booking Flow Javascript Logic
 *
 * Handles client-side state, steps routing, fetch filters, slots querying,
 * validations, and mock checkout summaries.
 *
 * @package Meditaj
 */

document.addEventListener('DOMContentLoaded', function() {
	const app = document.getElementById('meditaj-booking-flow-app');
	if ( ! app ) {
		return;
	}

	// Initialize Translate Helper
	function translate( text ) {
		return text;
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
		step: 1,
		specialty: '',
		doctor: null,
		bookingType: 'scheduled', // 'instant' or 'scheduled'
		date: '',
		slotTime: '',
		patientType: 'self',
		patientName: '',
		patientAge: '',
		patientRelation: '',
		notes: '',
		files: []
	};

	// Load existing state from sessionStorage if available
	const savedState = sessionStorage.getItem('meditaj_booking_state');
	if ( savedState ) {
		try {
			state = JSON.parse(savedState);
		} catch ( e ) {
			console.error('Failed to parse saved state:', e);
		}
	}

	// Save state to sessionStorage
	function saveState() {
		sessionStorage.setItem('meditaj_booking_state', JSON.stringify(state));
	}

	// 2. RENDERING STEPS
	function render() {
		// Update Steps Indicator Bar
		document.querySelectorAll('.meditaj-step-indicator').forEach(el => {
			const s = parseInt(el.getAttribute('data-step'));
			if ( s <= state.step ) {
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
		const tmpl = document.getElementById('meditaj-tmpl-specialty-grid');
		if ( ! tmpl ) {
			return;
		}

		const clone = tmpl.content.cloneNode(true);
		app.appendChild(clone);

		const gridTarget = document.getElementById('meditaj-specialties-target');
		const cardTmpl = document.getElementById('meditaj-tmpl-specialty-card-item');

		// Fetch specialties from endpoint
		fetch(meditajSettings.restUrl + 'specialties')
			.then(res => res.json())
			.then(data => {
				gridTarget.innerHTML = '';
				if ( ! data || 0 === data.length ) {
					gridTarget.innerHTML = '<p class="meditaj-no-data">No specialties available.</p>';
					return;
				}

				data.forEach(spec => {
					const cardClone = cardTmpl.cloneNode(true);
					const card = cardClone.querySelector('.meditaj-specialty-card');
					card.setAttribute('data-slug', spec.slug);
					card.querySelector('.meditaj-specialty-name').textContent = spec.name;

					const iconContainer = card.querySelector('.meditaj-specialty-icon');
					if ( spec.icon_url ) {
						iconContainer.innerHTML = `<img src="${spec.icon_url}" alt="${spec.name}" style="width:32px; height:32px;">`;
					} else {
						// Default SVG icon
						iconContainer.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 32px; height: 32px; color: #0d9488;"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>`;
					}

					// Click handler
					card.addEventListener('click', function() {
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
				gridTarget.innerHTML = '<p class="meditaj-error-msg">Error loading specialties. Please try again.</p>';
			});
	}

	// STEP 2: Doctor Listing with Filters
	function renderStep2() {
		const step2Html = `
		<div class="meditaj-step2-wrapper">
			<div class="meditaj-filters-sidebar">
				<h3>${translate('Filters')}</h3>
				<div class="meditaj-filter-group">
					<label for="filter-search">${translate('Search')}</label>
					<input type="text" id="filter-search" placeholder="Doctor name or degree...">
				</div>
				<div class="meditaj-filter-group">
					<label for="filter-specialty">${translate('Specialty')}</label>
					<select id="filter-specialty">
						<option value="">All Specialties</option>
					</select>
				</div>
				<div class="meditaj-filter-group">
					<label>${translate('Consultation Fee')}</label>
					<input type="range" id="filter-fee" min="0" max="5000" step="100" value="5000">
					<div class="range-values">
						<span>0 BDT</span>
						<span id="fee-val-display">5000 BDT</span>
					</div>
				</div>
				<div class="meditaj-filter-group checkbox">
					<label for="filter-instant">
						<input type="checkbox" id="filter-instant">
						${translate('Instant Call Only')}
					</label>
				</div>
				<div class="meditaj-filter-actions">
					<button type="button" id="btn-reset-filters" class="meditaj-btn-secondary">Reset Filters</button>
					<button type="button" id="btn-back-to-step1" class="meditaj-btn-secondary" style="margin-top:10px;">Back to Specialties</button>
				</div>
			</div>
			<div class="meditaj-doctors-list-content">
				<div class="meditaj-doctors-section" id="sec-instant-doctors">
					<h3 class="section-title">Instant Specialist Doctors <span class="online-indicator-dot"></span></h3>
					<div class="meditaj-doctors-grid" id="grid-instant-doctors"></div>
				</div>
				<div class="meditaj-doctors-section">
					<h3 class="section-title">All Specialists</h3>
					<div class="meditaj-doctors-grid" id="grid-all-doctors"></div>
				</div>
			</div>
		</div>
		`;

		app.innerHTML = step2Html;

		// Bind Sidebar Specialty Dropdown Options
		const specSelect = document.getElementById('filter-specialty');
		fetch(meditajSettings.restUrl + 'specialties')
			.then(res => res.json())
			.then(data => {
				data.forEach(spec => {
					const opt = document.createElement('option');
					opt.value = spec.slug;
					opt.textContent = spec.name;
					if ( spec.slug === state.specialty ) {
						opt.selected = true;
					}
					specSelect.appendChild(opt);
				});
			});

		// Range Input display updater
		const feeSlider = document.getElementById('filter-fee');
		const feeDisplay = document.getElementById('fee-val-display');
		feeSlider.addEventListener('input', function() {
			feeDisplay.textContent = feeSlider.value + ' BDT';
		});

		// Fetch and filter doctors logic
		function fetchDoctors() {
			const search = document.getElementById('filter-search').value;
			const spec = specSelect.value;
			const fee = feeSlider.value;
			const instant = document.getElementById('filter-instant').checked;

			let url = meditajSettings.restUrl + 'doctors?fee_max=' + fee;
			if ( spec ) {
				url += '&specialty=' + spec;
			}
			if ( instant ) {
				url += '&instant_only=true';
			}
			if ( search ) {
				url += '&search=' + encodeURIComponent(search);
			}

			const gridInstant = document.getElementById('grid-instant-doctors');
			const gridAll = document.getElementById('grid-all-doctors');

			gridInstant.innerHTML = '<div class="meditaj-loading-pills"></div>';
			gridAll.innerHTML = '<div class="meditaj-loading-pills"></div>';

			fetch(url)
				.then(res => res.json())
				.then(data => {
					gridInstant.innerHTML = '';
					gridAll.innerHTML = '';

					if ( ! data || 0 === data.length ) {
						gridAll.innerHTML = '<p class="meditaj-no-data">No doctors found matching filters.</p>';
						document.getElementById('sec-instant-doctors').style.display = 'none';
						return;
					}

					const cardTmpl = document.getElementById('meditaj-tmpl-doctor-card').content;

					let instantCount = 0;
					let allCount = 0;

					data.forEach(doc => {
						const cardClone = cardTmpl.cloneNode(true);
						const card = cardClone.querySelector('.meditaj-doctor-card');
						card.setAttribute('data-id', doc.id);
						card.querySelector('.meditaj-doctor-card-name').textContent = doc.name;
						card.querySelector('.meditaj-doctor-card-designation').textContent = doc.designation;
						card.querySelector('.meditaj-doctor-card-degree').textContent = doc.degree;
						card.querySelector('.spec-tag').textContent = doc.specialties.join(', ');
						card.querySelector('.fee-val').textContent = doc.consultation_fee + ' BDT';
						card.querySelector('.exp-val').textContent = doc.experience_years + ' Yrs';
						card.querySelector('.rating-tag').innerHTML = `⭐ ${doc.avg_rating} (${doc.total_reviews} reviews)`;

						const indicator = card.querySelector('.meditaj-status-indicator');
						if ( doc.is_online ) {
							indicator.classList.add('online');
						} else {
							indicator.classList.add('offline');
						}

						const avatarContainer = card.querySelector('.meditaj-doctor-card-avatar-wrap');
						if ( doc.photo_url ) {
							avatarContainer.innerHTML = `<img src="${doc.photo_url}" class="meditaj-doc-avatar" alt="${escapeHtml(doc.name)}"><span class="meditaj-status-indicator ${doc.is_online ? 'online' : 'offline'}"></span>`;
						} else {
							// Initials placeholder
							const initials = doc.name.split(' ').filter(n => n !== 'Dr.').map(n => n[0]).join('').substring(0, 2).toUpperCase();
							avatarContainer.innerHTML = `<div class="meditaj-doc-initials">${escapeHtml(initials)}</div><span class="meditaj-status-indicator ${doc.is_online ? 'online' : 'offline'}"></span>`;
						}

						// Click selector
						card.querySelector('.select-doctor-trigger').addEventListener('click', function() {
							state.doctor = doc;
							state.step = 3;
							saveState();
							render();
						});

						// Distribute to grids
						if ( doc.is_online ) {
							gridInstant.appendChild(cardClone);
							instantCount++;
						}
						// Append clone copy to all grid list
						const allClone = cardClone.cloneNode(true);
						allClone.querySelector('.select-doctor-trigger').addEventListener('click', function() {
							state.doctor = doc;
							state.step = 3;
							saveState();
							render();
						});
						gridAll.appendChild(allClone);
						allCount++;
					});

					// Hide/show instant container depending on whether any online doctor fits filters
					if ( 0 === instantCount ) {
						document.getElementById('sec-instant-doctors').style.display = 'none';
					} else {
						document.getElementById('sec-instant-doctors').style.display = 'block';
					}
				});
		}

		// Bind events to filters
		document.getElementById('filter-search').addEventListener('input', fetchDoctors);
		specSelect.addEventListener('change', function() {
			state.specialty = specSelect.value;
			saveState();
			fetchDoctors();
		});
		feeSlider.addEventListener('change', fetchDoctors);
		document.getElementById('filter-instant').addEventListener('change', fetchDoctors);

		document.getElementById('btn-reset-filters').addEventListener('click', function() {
			document.getElementById('filter-search').value = '';
			specSelect.value = '';
			feeSlider.value = 5000;
			feeDisplay.textContent = '5000 BDT';
			document.getElementById('filter-instant').checked = false;
			state.specialty = '';
			saveState();
			fetchDoctors();
		});

		document.getElementById('btn-back-to-step1').addEventListener('click', function() {
			state.step = 1;
			state.specialty = '';
			saveState();
			render();
		});

		// Trigger initial load
		fetchDoctors();
	}

	// STEP 3: Doctor Details & Checkout Panel
	function renderStep3() {
		const doc = state.doctor;
		if ( ! doc ) {
			state.step = 2;
			render();
			return;
		}

		const step3Html = `
		<div class="meditaj-step3-wrapper">
			<div class="meditaj-doctor-detail-panel">
				<div class="meditaj-detail-header">
					<div class="detail-avatar-wrap">
						<!-- Injected via JS -->
					</div>
					<div class="detail-summary">
						<h2 id="detail-name">${doc.name}</h2>
						<p id="detail-designation" class="detail-subtitle">${doc.designation}</p>
						<p id="detail-degree" class="detail-subtitle">${doc.degree}</p>
						<div class="detail-rating-row">
							<span class="rating-badge">⭐ ${doc.avg_rating} (${doc.total_reviews} reviews)</span>
							<span class="experience-badge">💼 ${doc.experience_years} Yrs Exp</span>
						</div>
					</div>
				</div>
				<div class="meditaj-detail-body">
					<h3>Biography</h3>
					<p id="detail-bio">${doc.bio || 'No biography text available for this provider.'}</p>
					
					<div class="booking-options-block">
						<h3>Select Appointment Type</h3>
						<div class="booking-type-toggles">
							<button type="button" class="type-toggle-btn ${'scheduled' === state.bookingType ? 'active' : ''}" data-type="scheduled" id="btn-type-scheduled">Book Later</button>
							<button type="button" class="type-toggle-btn ${'instant' === state.bookingType ? 'active' : ''}" data-type="instant" id="btn-type-instant">Instant Call</button>
						</div>
						
						<div id="booking-calendar-section" class="booking-calendar-wrapper" style="${'scheduled' === state.bookingType ? 'display:block;' : 'display:none;'}">
							<label for="booking-date">Choose Date</label>
							<input type="date" id="booking-date" value="${state.date}">
							
							<div id="booking-slots-section" style="display:none; margin-top: 15px;">
								<h4>Available Slots</h4>
								<div class="meditaj-slots-grid" id="grid-slots-target"></div>
							</div>
						</div>
					</div>

					<div class="patient-details-block">
						<h3>Patient Information</h3>
						<div class="meditaj-field">
							<label for="patient-relation">Consultation For</label>
							<select id="patient-relation">
								<option value="self" ${'self' === state.patientType ? 'selected' : ''}>Self</option>
								<option value="father" ${'father' === state.patientType ? 'selected' : ''}>Father</option>
								<option value="mother" ${'mother' === state.patientType ? 'selected' : ''}>Mother</option>
								<option value="spouse" ${'spouse' === state.patientType ? 'selected' : ''}>Spouse</option>
								<option value="child" ${'child' === state.patientType ? 'selected' : ''}>Child</option>
								<option value="other" ${'other' === state.patientType ? 'selected' : ''}>Other</option>
							</select>
						</div>
						<div id="patient-family-fields" style="${'self' === state.patientType ? 'display:none;' : 'display:grid;'}" class="meditaj-fields-grid text-fields">
							<div class="meditaj-field">
								<label for="patient-name">Patient Name *</label>
								<input type="text" id="patient-name" placeholder="Enter patient name" value="${state.patientName}">
							</div>
							<div class="meditaj-field">
								<label for="patient-age">Patient Age *</label>
								<input type="number" id="patient-age" placeholder="Enter age" value="${state.patientAge}">
							</div>
						</div>
						<div class="meditaj-field span-full" style="margin-top: 10px;">
							<label for="patient-notes">Symptom Notes / Reason for Visit</label>
							<textarea id="patient-notes" rows="3" placeholder="Describe symptoms or medical concern...">${state.notes}</textarea>
						</div>
						<div class="meditaj-field span-full" style="margin-top: 10px;">
							<label>Medical Reports (Optional)</label>
							<div class="meditaj-modern-upload-wrapper">
								<label for="patient-files" class="meditaj-modern-upload-label">Upload File</label>
								<input type="file" id="patient-files" accept="image/*,.pdf" class="meditaj-hidden-file-input">
								<span class="meditaj-file-name" id="patient-files-name">No file chosen</span>
							</div>
						</div>
					</div>
				</div>
			</div>
			<div class="meditaj-checkout-panel-wrapper">
				<div id="checkout-summary-target"></div>
				<button type="button" id="btn-confirm-booking" class="meditaj-btn-register cyan-btn" style="width:100%; margin-top:20px;">Confirm Booking</button>
				<button type="button" id="btn-back-to-step2" class="meditaj-btn-secondary" style="width:100%; margin-top: 10px;">Back to Doctors List</button>
			</div>
		</div>
		`;

		app.innerHTML = step3Html;

		// Populate Avatar
		const avatarWrap = app.querySelector('.detail-avatar-wrap');
		if ( doc.photo_url ) {
			avatarWrap.innerHTML = `<img src="${doc.photo_url}" class="meditaj-detail-avatar" alt="${escapeHtml(doc.name)}">`;
		} else {
			const initials = doc.name.split(' ').filter(n => n !== 'Dr.').map(n => n[0]).join('').substring(0, 2).toUpperCase();
			avatarWrap.innerHTML = `<div class="meditaj-detail-avatar-placeholder">${escapeHtml(initials)}</div>`;
		}

		// Disable Instant toggle if doctor is offline
		const btnInstant = document.getElementById('btn-type-instant');
		const btnScheduled = document.getElementById('btn-type-scheduled');
		if ( ! doc.is_online ) {
			btnInstant.disabled = true;
			btnInstant.title = 'Provider is currently offline';
			btnInstant.classList.add('disabled');
		}

		// Bind Booking Type Toggles
		btnInstant.addEventListener('click', function() {
			if ( ! doc.is_online ) {
				return;
			}
			btnInstant.classList.add('active');
			btnScheduled.classList.remove('active');
			state.bookingType = 'instant';
			state.date = '';
			state.slotTime = '';
			saveState();
			document.getElementById('booking-calendar-section').style.display = 'none';
			updateCheckoutSummary();
		});

		btnScheduled.addEventListener('click', function() {
			btnScheduled.classList.add('active');
			btnInstant.classList.remove('active');
			state.bookingType = 'scheduled';
			saveState();
			document.getElementById('booking-calendar-section').style.display = 'block';
			updateCheckoutSummary();
		});

		// Date picker selection logic
		const dateInput = document.getElementById('booking-date');
		const slotsSection = document.getElementById('booking-slots-section');
		const slotsTarget = document.getElementById('grid-slots-target');

		// Restrict Date picker to today or future dates
		const todayStr = new Date().toISOString().split('T')[0];
		dateInput.setAttribute('min', todayStr);

		function loadSlots() {
			const d = dateInput.value;
			if ( ! d ) {
				slotsSection.style.display = 'none';
				return;
			}

			state.date = d;
			saveState();
			slotsSection.style.display = 'block';
			slotsTarget.innerHTML = '<div class="meditaj-loading-spinner small"></div>';

			fetch(meditajSettings.restUrl + 'doctors/' + doc.id + '/slots?date=' + d)
				.then(res => res.json())
				.then(slots => {
					slotsTarget.innerHTML = '';
					if ( ! slots || 0 === slots.length ) {
						slotsTarget.innerHTML = '<p class="meditaj-no-data">No consultation hours available on this date.</p>';
						return;
					}

					slots.forEach(slot => {
						const pill = document.createElement('button');
						pill.type = 'button';
						pill.className = 'meditaj-slot-pill';
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
								slotsTarget.querySelectorAll('.meditaj-slot-pill').forEach(p => p.classList.remove('active'));
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

		dateInput.addEventListener('change', loadSlots);
		if ( state.date ) {
			loadSlots();
		}

		// Bind Patient Relation Selection
		const relationSelect = document.getElementById('patient-relation');
		const familyFields = document.getElementById('patient-family-fields');
		const pNameInput = document.getElementById('patient-name');
		const pAgeInput = document.getElementById('patient-age');

		relationSelect.addEventListener('change', function() {
			state.patientType = relationSelect.value;
			if ( 'self' === relationSelect.value ) {
				familyFields.style.display = 'none';
				pNameInput.removeAttribute('required');
				pAgeInput.removeAttribute('required');
			} else {
				familyFields.style.display = 'grid';
				pNameInput.setAttribute('required', 'required');
				pAgeInput.setAttribute('required', 'required');
			}
			saveState();
			updateCheckoutSummary();
		});

		pNameInput.addEventListener('input', function() {
			state.patientName = pNameInput.value;
			saveState();
			updateCheckoutSummary();
		});

		pAgeInput.addEventListener('input', function() {
			state.patientAge = pAgeInput.value;
			saveState();
			updateCheckoutSummary();
		});

		document.getElementById('patient-notes').addEventListener('input', function(e) {
			state.notes = e.target.value;
			saveState();
		});

		// File Uploader metadata capturing
		const fileInput = document.getElementById('patient-files');
		const fileNameDisplay = document.getElementById('patient-files-name');
		fileInput.addEventListener('change', function(e) {
			if ( e.target.files.length > 0 ) {
				const f = e.target.files[0];
				const maxFileSize = 2 * 1024 * 1024; // 2MB
				if ( f.size > maxFileSize ) {
					alert('File size exceeds the 2MB limit. Please upload a smaller document.');
					fileInput.value = ''; // clear input
					fileNameDisplay.textContent = 'No file chosen';
					state.files = [];
					saveState();
					return;
				}
				fileNameDisplay.textContent = f.name;
				state.files = [ { name: f.name, size: f.size } ];
			} else {
				fileNameDisplay.textContent = 'No file chosen';
				state.files = [];
			}
			saveState();
		});

		// Checkout summary cloning & population
		const summaryTarget = document.getElementById('checkout-summary-target');
		const summaryTmpl = document.getElementById('meditaj-tmpl-checkout-summary').content;

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

			// Calculations
			const fee = 'instant' === state.bookingType ? doc.instant_call_fee : doc.consultation_fee;
			const tax = Math.round(fee * 0.05 * 100) / 100;
			const total = fee + tax;

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

		// Back navigation
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

			const activeSlotEl = document.querySelector('.meditaj-slot-pill.active');
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

			if ( 'self' !== state.patientType ) {
				if ( ! state.patientName.trim() ) {
					alert('Please enter the patient\'s full name.');
					return;
				}
				if ( ! state.patientAge ) {
					alert('Please specify the patient\'s age.');
					return;
				}
			}

			// Valid checkout payload compilation
			const payload = {
				doctor_id: doc.id,
				booking_type: state.bookingType,
				date: state.bookingType === 'scheduled' ? state.date : todayStr,
				time: state.bookingType === 'scheduled' ? state.slotTime : new Date().toTimeString().split(' ')[0],
				patient_relation: state.patientType,
				patient_name: state.patientType === 'self' ? wp_get_current_user_name() : state.patientName,
				patient_age: state.patientType === 'self' ? null : parseInt(state.patientAge),
				notes: state.notes,
				files: state.files
			};

			const btnConfirm = document.getElementById('btn-confirm-booking');
			btnConfirm.disabled = true;
			btnConfirm.textContent = 'Processing Booking...';

			// 1. Send request to create appointment
			fetch(meditajSettings.restUrl + 'appointments', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': meditajSettings.nonce
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
				btnConfirm.textContent = 'Redirecting to Payment...';
				const appointmentId = data.appointment_id;

				return fetch(meditajSettings.restUrl + 'payment/init', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': meditajSettings.nonce
					},
					body: JSON.stringify({
						appointment_id: appointmentId,
						return_url: window.location.href
					})
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
				sessionStorage.removeItem('meditaj_booking_state');
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

	// Success popup screen overlay
	function renderSuccessScreen( payload, doc ) {
		const fee = 'instant' === payload.booking_type ? doc.instant_call_fee : doc.consultation_fee;
		const total = fee + (fee * 0.05);

		const successHtml = `
		<div class="meditaj-booking-success-overlay">
			<div class="meditaj-success-popup-card">
				<div class="meditaj-success-popup-icon">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width: 32px; height: 32px; color: #16a34a;">
						<polyline points="20 6 9 17 4 12"></polyline>
					</svg>
				</div>
				<h2>Booking Confirmed!</h2>
				<p class="popup-desc">Your medical consultation request has been successfully captured. A notification will be dispatched once confirmed.</p>
				
				<div class="popup-receipt-details">
					<div class="receipt-row">
						<strong>Doctor:</strong>
						<span>${doc.name}</span>
					</div>
					<div class="receipt-row">
						<strong>Appointment:</strong>
						<span>${payload.booking_type === 'instant' ? 'Instant Call' : payload.date + ' @ ' + payload.time.substring(0, 5)}</span>
					</div>
					<div class="receipt-row">
						<strong>Patient:</strong>
						<span>${payload.patient_name} (${payload.patient_relation})</span>
					</div>
					<div class="receipt-row total">
						<strong>Total Cost:</strong>
						<span>${total.toFixed(2)} BDT</span>
					</div>
				</div>

				<button type="button" id="btn-close-success" class="meditaj-btn-register cyan-btn" style="width:100%; margin-top:20px;">Book Another Appointment</button>
			</div>
		</div>
		`;

		const overlayDiv = document.createElement('div');
		overlayDiv.innerHTML = successHtml;
		document.body.appendChild(overlayDiv.firstElementChild);

		// Clear sessionStorage
		sessionStorage.removeItem('meditaj_booking_state');

		document.getElementById('btn-close-success').addEventListener('click', function() {
			document.querySelector('.meditaj-booking-success-overlay').remove();
			state = {
				step: 1,
				specialty: '',
				doctor: null,
				bookingType: 'scheduled',
				date: '',
				slotTime: '',
				patientType: 'self',
				patientName: '',
				patientAge: '',
				patientRelation: '',
				notes: '',
				files: []
			};
			render();
		});
	}

	// Formats 24-hour time "16:30:00" or "16:30" to 12-hour "4:30 PM"
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

	// Bootstrap Render
	render();
});
