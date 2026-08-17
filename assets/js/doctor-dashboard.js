/**
 * EG Care Doctor Dashboard Javascript Controller.
 *
 * Handles tab navigation, stats queries, weekly availability grid toggles,
 * call window validation, and profile updates.
 *
 * @package EG Care
 */

document.addEventListener('DOMContentLoaded', function() {
	const wrapper = document.querySelector('.eg-care-dashboard-wrapper');
	if ( ! wrapper ) {
		return;
	}

	// 1. TAB MANAGEMENT
	const tabs = document.querySelectorAll('.tab-trigger');
	const panes = document.querySelectorAll('.tab-pane');

	tabs.forEach(tab => {
		tab.addEventListener('click', function() {
			tabs.forEach(t => t.classList.remove('active'));
			panes.forEach(p => p.classList.remove('active'));

			tab.classList.add('active');
			const targetId = 'pane-' + tab.getAttribute('data-tab');
			document.getElementById(targetId).classList.add('active');
		});
	});

	// 2. INITIALIZE PROFILE FROM LOCALIZED VARIABLES
	const docSettings = egCareSettings.doctor;
	const feeInput = document.getElementById('profile-fee');
	const instantFeeInput = document.getElementById('profile-instant-fee');
	const bioInput = document.getElementById('profile-bio');
	const onlineToggle = document.getElementById('dashboard-instant-toggle');
	const onlineBadge = document.getElementById('stat-online-badge');

	if ( docSettings ) {
		feeInput.value = docSettings.consultation_fee || '';
		instantFeeInput.value = docSettings.instant_call_fee || '';
		bioInput.value = docSettings.bio || '';
		onlineToggle.checked = docSettings.is_online;
		updateOnlineBadge(docSettings.is_online);
	}

	function updateOnlineBadge(isOnline) {
		if ( isOnline ) {
			onlineBadge.textContent = 'Online';
			onlineBadge.className = 'stat-num online-green';
		} else {
			onlineBadge.textContent = 'Offline';
			onlineBadge.className = 'stat-num offline-gray';
		}
	}

	// Online Toggle handler
	onlineToggle.addEventListener('change', function() {
		const state = onlineToggle.checked;
		updateOnlineBadge(state);

		fetch(egCareSettings.restUrl + 'doctor/me/profile', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': egCareSettings.nonce
			},
			body: JSON.stringify({
				bio: bioInput.value,
				consultation_fee: parseFloat(feeInput.value) || 500,
				instant_call_fee: parseFloat(instantFeeInput.value) || 600,
				is_online: state
			})
		})
		.then(res => res.json())
		.then(data => {
			if ( data.status === 'success' ) {
				updateOnlineBadge(data.is_online);
			}
		})
		.catch(err => console.error('Failed to toggle status:', err));
	});

	// 3. FETCH AND RENDER DASHBOARD STATS
	function loadStats() {
		fetch(egCareSettings.restUrl + 'doctor/me/stats', {
			headers: { 'X-WP-Nonce': egCareSettings.nonce }
		})
		.then(res => res.json())
		.then(data => {
			document.getElementById('stat-total-appointments').textContent = data.total_appointments;
			document.getElementById('stat-net-earnings').textContent = data.net_earnings.toFixed(2) + ' BDT';
			document.getElementById('stat-gross-breakdown').textContent = 'Gross: ' + data.gross_earnings.toFixed(2) + ' BDT';
		})
		.catch(err => console.error('Error fetching dashboard stats:', err));
	}

	// 4. FETCH AND RENDER APPOINTMENTS
	function loadAppointments() {
		const todayTarget = document.getElementById('today-appointments-target');
		const upcomingTarget = document.getElementById('upcoming-appointments-target');

		fetch(egCareSettings.restUrl + 'doctor/me/appointments', {
			headers: { 'X-WP-Nonce': egCareSettings.nonce }
		})
		.then(res => res.json())
		.then(data => {
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

			// Today's appointments
			todayTarget.innerHTML = '';
			if ( ! data.today || 0 === data.today.length ) {
				todayTarget.innerHTML = '<tr><td colspan="5" class="empty-cell">No consultations scheduled for today.</td></tr>';
			} else {
				data.today.forEach(app => {
					const tr = document.createElement('tr');
					
					// Calculate if current time is within +/- 15 minutes of appointment window
					const now = new Date();
					const [hh, mm, ss] = app.appointment_time.split(':');
					const appTime = new Date();
					appTime.setHours(parseInt(hh), parseInt(mm), parseInt(ss));
					const diffMin = (appTime - now) / 60000;
					
					const isCallWindow = Math.abs(diffMin) <= 15;

					const escapedName = escapeHtml(app.family_member_name);
					const escapedRelation = escapeHtml(app.family_member_relation);
					const escapedNotes = escapeHtml(app.symptom_notes || '');

					tr.innerHTML = `
						<td><strong>${escapedName}</strong> <span class="relation-tag">${escapedRelation}</span></td>
						<td>${formatTime24To12(app.appointment_time)}</td>
						<td><span class="symptom-notes-cell" title="${escapedNotes}">${escapedNotes || '-'}</span></td>
						<td>${app.amount} BDT</td>
						<td>
							<button type="button" class="eg-care-btn-join-call ${isCallWindow ? 'active' : 'disabled'}" ${isCallWindow ? '' : 'disabled'}>
								Join Call
							</button>
						</td>
					`;

					const joinBtn = tr.querySelector('.eg-care-btn-join-call');
					if ( isCallWindow ) {
						joinBtn.addEventListener('click', function() {
							if ( window.EG CareVideoCall ) {
								window.EG CareVideoCall.join(app.id);
							} else {
								alert('Video call manager is not initialized.');
							}
						});
					} else {
						joinBtn.title = 'Available only within 15 minutes of scheduled time.';
					}

					todayTarget.appendChild(tr);
				});
			}

			// Upcoming appointments
			upcomingTarget.innerHTML = '';
			if ( ! data.upcoming || 0 === data.upcoming.length ) {
				upcomingTarget.innerHTML = '<tr><td colspan="5" class="empty-cell">No upcoming consultations found.</td></tr>';
			} else {
				data.upcoming.forEach(app => {
					const tr = document.createElement('tr');
					const escapedName = escapeHtml(app.family_member_name);
					const escapedRelation = escapeHtml(app.family_member_relation);
					const escapedNotes = escapeHtml(app.symptom_notes || '');

					tr.innerHTML = `
						<td><strong>${escapedName}</strong> <span class="relation-tag">${escapedRelation}</span></td>
						<td>${app.appointment_date}</td>
						<td>${formatTime24To12(app.appointment_time)}</td>
						<td><span class="symptom-notes-cell" title="${escapedNotes}">${escapedNotes || '-'}</span></td>
						<td>${app.amount} BDT</td>
					`;
					upcomingTarget.appendChild(tr);
				});
			}
		})
		.catch(err => {
			console.error('Error loading appointments:', err);
			todayTarget.innerHTML = '<tr><td colspan="5" class="error-cell">Failed to load appointments.</td></tr>';
			upcomingTarget.innerHTML = '<tr><td colspan="5" class="error-cell">Failed to load appointments.</td></tr>';
		});
	}

	// 5. WEEKLY SLOTS GRID SCHEDULER
	function createSlotBlock(startTime = '09:00', endTime = '12:00', duration = '30', breakTime = '0', isActive = true) {
		const block = document.createElement('div');
		block.className = 'time-slot-block';
		block.style = 'display: flex; align-items: center; gap: 15px; margin-bottom: 10px; background: #f8fafc; padding: 12px; border-radius: 6px; border: 1px solid #e2e8f0; flex-wrap: wrap; width: 100%; box-sizing: border-box;';
		
		block.innerHTML = `
			<!-- Active / Inactive Checkbox -->
			<label style="display: flex; align-items: center; gap: 6px; font-weight: bold; margin-bottom: 0; cursor: pointer;">
				<input type="checkbox" class="slot-active-check" ${isActive ? 'checked' : ''}>
				Active
			</label>
			
			<!-- Start Time -->
			<div class="time-group" style="margin-bottom: 0; display: flex; flex-direction: column; gap: 4px;">
				<label style="font-size: 11px; text-transform: uppercase; color: #64748b; display: block; font-weight: bold;">Start</label>
				<input type="time" class="time-start" value="${startTime}" required style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; font-weight: 500;">
			</div>
			
			<!-- End Time -->
			<div class="time-group" style="margin-bottom: 0; display: flex; flex-direction: column; gap: 4px;">
				<label style="font-size: 11px; text-transform: uppercase; color: #64748b; display: block; font-weight: bold;">End</label>
				<input type="time" class="time-end" value="${endTime}" required style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; font-weight: 500;">
			</div>
			
			<!-- Duration -->
			<div class="time-group" style="margin-bottom: 0; display: flex; flex-direction: column; gap: 4px;">
				<label style="font-size: 11px; text-transform: uppercase; color: #64748b; display: block; font-weight: bold;">Duration</label>
				<select class="slot-duration" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; font-weight: 500;">
					<option value="15" ${'15' == duration ? 'selected' : ''}>15 Min</option>
					<option value="30" ${'30' == duration ? 'selected' : ''}>30 Min</option>
					<option value="45" ${'45' == duration ? 'selected' : ''}>45 Min</option>
					<option value="60" ${'60' == duration ? 'selected' : ''}>60 Min</option>
				</select>
			</div>
			
			<!-- Break Time (Optional) -->
			<div class="time-group" style="margin-bottom: 0; display: flex; flex-direction: column; gap: 4px;">
				<label style="font-size: 11px; text-transform: uppercase; color: #64748b; display: block; font-weight: bold;">Break (Optional)</label>
				<select class="slot-break" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; font-weight: 500;">
					<option value="0" ${'0' == breakTime ? 'selected' : ''}>No Break</option>
					<option value="5" ${'5' == breakTime ? 'selected' : ''}>5 Min</option>
					<option value="10" ${'10' == breakTime ? 'selected' : ''}>10 Min</option>
					<option value="15" ${'15' == breakTime ? 'selected' : ''}>15 Min</option>
					<option value="20" ${'20' == breakTime ? 'selected' : ''}>20 Min</option>
					<option value="30" ${'30' == breakTime ? 'selected' : ''}>30 Min</option>
				</select>
			</div>
			
			<!-- Remove Button -->
			<button type="button" class="eg-care-remove-slot-btn" style="background: #fee2e2 !important; color: #b91c1c !important; border: none !important; border-radius: 6px !important; padding: 6px 12px !important; cursor: pointer !important; font-size: 12px !important; font-weight: bold !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; height: 32px !important; margin-top: 15px !important; box-shadow: none !important;">✕ Remove</button>
		`;

		// Attach remove button listener
		block.querySelector('.eg-care-remove-slot-btn').addEventListener('click', function() {
			block.remove();
		});

		return block;
	}

	function loadWeeklySchedules() {
		// First attach add button listeners (once)
		document.querySelectorAll('.day-slot-row').forEach(row => {
			const container = row.querySelector('.day-slots-blocks-container');
			const addBtn = row.querySelector('.eg-care-add-slot-btn');
			
			// Prevent duplicate bindings
			if (!addBtn.dataset.bound) {
				addBtn.addEventListener('click', function() {
					container.appendChild(createSlotBlock('09:00', '12:00', '30', '0', true));
				});
				addBtn.dataset.bound = 'true';
			}
			
			container.innerHTML = ''; // clear loading state
		});

		fetch(egCareSettings.restUrl + 'doctor/me/slots', {
			headers: { 'X-WP-Nonce': egCareSettings.nonce }
		})
		.then(res => res.json())
		.then(slots => {
			slots.forEach(slot => {
				const row = document.querySelector(`.day-slot-row[data-day="${slot.day_of_week}"]`);
				if ( row ) {
					const container = row.querySelector('.day-slots-blocks-container');
					
					// Formats HH:MM:SS to HH:MM
					const start = slot.start_time.substring(0, 5);
					const end = slot.end_time.substring(0, 5);
					const duration = slot.slot_duration_min;
					const breakTime = slot.break_duration_min || 0;
					const isActive = parseInt(slot.is_active) === 1;

					container.appendChild(createSlotBlock(start, end, duration, breakTime, isActive));
				}
			});
		})
		.catch(err => console.error('Error loading schedules:', err));
	}

	// Save Weekly Grid Scheduler Form Submit
	const slotsForm = document.getElementById('eg-care-slots-form');
	slotsForm.addEventListener('submit', function(e) {
		e.preventDefault();

		const slotsPayload = [];
		document.querySelectorAll('.day-slot-row').forEach(row => {
			const day = parseInt(row.getAttribute('data-day'));
			row.querySelectorAll('.time-slot-block').forEach(block => {
				const isActive = block.querySelector('.slot-active-check').checked ? 1 : 0;
				slotsPayload.push({
					day_of_week: day,
					start_time: block.querySelector('.time-start').value + ':00',
					end_time: block.querySelector('.time-end').value + ':00',
					slot_duration_min: parseInt(block.querySelector('.slot-duration').value),
					break_duration_min: parseInt(block.querySelector('.slot-break').value),
					is_active: isActive
				});
			});
		});

		const saveBtn = slotsForm.querySelector('button[type="submit"]');
		saveBtn.disabled = true;
		saveBtn.textContent = 'Saving Schedule...';

		fetch(egCareSettings.restUrl + 'doctor/me/slots', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': egCareSettings.nonce
			},
			body: JSON.stringify({ slots: slotsPayload })
		})
		.then(res => res.json())
		.then(data => {
			alert('Availability schedules updated successfully!');
			saveBtn.disabled = false;
			saveBtn.textContent = 'Save Schedule Grid';
		})
		.catch(err => {
			console.error('Error saving schedule slots:', err);
			alert('Failed to save schedules.');
			saveBtn.disabled = false;
			saveBtn.textContent = 'Save Schedule Grid';
		});
	});

	// 6. SAVE PROFILE UPDATES
	const profileForm = document.getElementById('eg-care-profile-form');
	const fileInput = document.getElementById('profile-photo');
	const fileNameDisplay = document.getElementById('profile-photo-name');

	fileInput.addEventListener('change', function(e) {
		if ( e.target.files.length > 0 ) {
			const maxFileSize = 2 * 1024 * 1024; // 2MB
			if ( e.target.files[0].size > maxFileSize ) {
				alert('Profile photo size exceeds 2MB limit. Please upload a smaller file.');
				fileInput.value = ''; // clear input
				fileNameDisplay.textContent = 'No file chosen';
				return;
			}
			fileNameDisplay.textContent = e.target.files[0].name;
		} else {
			fileNameDisplay.textContent = 'No file chosen';
		}
	});

	profileForm.addEventListener('submit', function(e) {
		e.preventDefault();

		const saveBtn = profileForm.querySelector('button[type="submit"]');
		saveBtn.disabled = true;
		saveBtn.textContent = 'Saving Profile...';

		const profilePayload = {
			bio: bioInput.value,
			consultation_fee: parseFloat(feeInput.value),
			instant_call_fee: parseFloat(instantFeeInput.value) || 0,
			is_online: onlineToggle.checked
		};

		// Helper to save profile text settings
		function saveProfileText(photoId = 0) {
			if ( photoId > 0 ) {
				profilePayload.photo_id = photoId;
			}

			fetch(egCareSettings.restUrl + 'doctor/me/profile', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': egCareSettings.nonce
				},
				body: JSON.stringify(profilePayload)
			})
			.then(res => res.json())
			.then(data => {
				alert('Profile configurations successfully saved!');
				saveBtn.disabled = false;
				saveBtn.textContent = 'Save Profile Changes';
				loadStats(); // reload stats card
			})
			.catch(err => {
				console.error('Error updating profile:', err);
				alert('Failed to save profile.');
				saveBtn.disabled = false;
				saveBtn.textContent = 'Save Profile Changes';
			});
		}

		// If a new photo is selected, upload it via WP Media REST API first
		if ( fileInput.files.length > 0 ) {
			const file = fileInput.files[0];

			fetch(egCareSettings.restUrl.replace('eg-care/v1/', 'wp/v2/media'), {
				method: 'POST',
				headers: {
					'X-WP-Nonce': egCareSettings.nonce,
					'Content-Disposition': 'attachment; filename="' + encodeURIComponent(file.name) + '"',
					'Content-Type': file.type
				},
				body: file
			})
			.then(res => {
				if (!res.ok) {
					return res.json().then(err => { throw new Error(err.message || 'Media REST error.'); });
				}
				return res.json();
			})
			.then(media => {
				if ( media.id ) {
					saveProfileText(media.id);
				} else {
					throw new Error('Failed to upload attachment.');
				}
			})
			.catch(err => {
				console.error('Photo upload failed:', err);
				alert('Failed to upload image. Saving text fields only.');
				saveProfileText(0);
			});
		} else {
			saveProfileText(0);
		}
	});

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
	loadStats();
	loadAppointments();
	loadWeeklySchedules();
});
