/**
 * Meditaj Doctor Dashboard Javascript Controller.
 *
 * Handles tab navigation, stats queries, weekly availability grid toggles,
 * call window validation, and profile updates.
 *
 * @package Meditaj
 */

document.addEventListener('DOMContentLoaded', function() {
	const wrapper = document.querySelector('.meditaj-dashboard-wrapper');
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
	const docSettings = meditajSettings.doctor;
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

		fetch(meditajSettings.restUrl + 'doctor/me/profile', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': meditajSettings.nonce
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
		fetch(meditajSettings.restUrl + 'doctor/me/stats', {
			headers: { 'X-WP-Nonce': meditajSettings.nonce }
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

		fetch(meditajSettings.restUrl + 'doctor/me/appointments', {
			headers: { 'X-WP-Nonce': meditajSettings.nonce }
		})
		.then(res => res.json())
		.then(data => {
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

					tr.innerHTML = `
						<td><strong>${app.family_member_name}</strong> <span class="relation-tag">${app.family_member_relation}</span></td>
						<td>${app.appointment_time.substring(0, 5)}</td>
						<td><span class="symptom-notes-cell" title="${app.symptom_notes || ''}">${app.symptom_notes || '-'}</span></td>
						<td>${app.amount} BDT</td>
						<td>
							<button type="button" class="meditaj-btn-join-call ${isCallWindow ? 'active' : 'disabled'}" ${isCallWindow ? '' : 'disabled'}>
								Join Call
							</button>
						</td>
					`;

					const joinBtn = tr.querySelector('.meditaj-btn-join-call');
					if ( isCallWindow ) {
						joinBtn.addEventListener('click', function() {
							alert('Opening Meditaj Telemedicine Consultation Call Room...');
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
					tr.innerHTML = `
						<td><strong>${app.family_member_name}</strong> <span class="relation-tag">${app.family_member_relation}</span></td>
						<td>${app.appointment_date}</td>
						<td>${app.appointment_time.substring(0, 5)}</td>
						<td><span class="symptom-notes-cell" title="${app.symptom_notes || ''}">${app.symptom_notes || '-'}</span></td>
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
	function loadWeeklySchedules() {
		fetch(meditajSettings.restUrl + 'doctor/me/slots', {
			headers: { 'X-WP-Nonce': meditajSettings.nonce }
		})
		.then(res => res.json())
		.then(slots => {
			slots.forEach(slot => {
				const row = document.querySelector(`.day-slot-row[data-day="${slot.day_of_week}"]`);
				if ( row ) {
					row.querySelector('.day-active-check').checked = true;
					
					// Formats HH:MM:SS to HH:MM
					const start = slot.start_time.substring(0, 5);
					const end = slot.end_time.substring(0, 5);

					row.querySelector('.time-start').value = start;
					row.querySelector('.time-end').value = end;
					row.querySelector('.slot-duration').value = slot.slot_duration_min;
				}
			});
		})
		.catch(err => console.error('Error loading schedules:', err));
	}

	// Save Weekly Grid Scheduler Form Submit
	const slotsForm = document.getElementById('meditaj-slots-form');
	slotsForm.addEventListener('submit', function(e) {
		e.preventDefault();

		const slotsPayload = [];
		document.querySelectorAll('.day-slot-row').forEach(row => {
			const checked = row.querySelector('.day-active-check').checked;
			if ( checked ) {
				slotsPayload.push({
					day_of_week: parseInt(row.getAttribute('data-day')),
					start_time: row.querySelector('.time-start').value + ':00',
					end_time: row.querySelector('.time-end').value + ':00',
					slot_duration_min: parseInt(row.querySelector('.slot-duration').value)
				});
			}
		});

		const saveBtn = slotsForm.querySelector('button[type="submit"]');
		saveBtn.disabled = true;
		saveBtn.textContent = 'Saving Schedule...';

		fetch(meditajSettings.restUrl + 'doctor/me/slots', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': meditajSettings.nonce
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
	const profileForm = document.getElementById('meditaj-profile-form');
	const fileInput = document.getElementById('profile-photo');
	const fileNameDisplay = document.getElementById('profile-photo-name');

	fileInput.addEventListener('change', function(e) {
		if ( e.target.files.length > 0 ) {
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

			fetch(meditajSettings.restUrl + 'doctor/me/profile', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': meditajSettings.nonce
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
			const formData = new FormData();
			formData.append('file', file);
			formData.append('title', 'Doctor Profile ' + meditajSettings.doctor.id);

			fetch(meditajSettings.restUrl.replace('meditaj/v1/', 'wp/v2/media'), {
				method: 'POST',
				headers: {
					'X-WP-Nonce': meditajSettings.nonce
				},
				body: formData
			})
			.then(res => res.json())
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

	// Bootstrap Render
	loadStats();
	loadAppointments();
	loadWeeklySchedules();
});
