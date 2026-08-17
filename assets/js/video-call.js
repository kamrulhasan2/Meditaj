(function() {
	// Define SVG icons for media controls (theme-proof & cross-browser)
	const icons = {
		micOn: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="video-icon" style="width: 20px; height: 20px;"><path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2"/><line x1="12" x2="12" y1="19" y2="22"/></svg>`,
		micOff: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="video-icon" style="width: 20px; height: 20px;"><line x1="1" x2="23" y1="1" y2="23"/><path d="M9 9v3a3 3 0 0 0 5.12 2.12M15 9.34V5a3 3 0 0 0-5.94-.6"/><path d="M17 16.95A7 7 0 0 1 5 12v-2m14 0v2a7 7 0 0 1-.11 1.23"/><line x1="12" x2="12" y1="19" y2="22"/></svg>`,
		camOn: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="video-icon" style="width: 20px; height: 20px;"><path d="m22 8-6 4 6 4V8Z"/><rect width="14" height="12" x="2" y="6" rx="2" ry="2"/></svg>`,
		camOff: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="video-icon" style="width: 20px; height: 20px;"><path d="M16 16v1a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h1m4 0h6a2 2 0 0 1 2 2v3.5l5-3.5v11l-5-3.5"/><line x1="1" x2="23" y1="1" y2="23"/></svg>`,
		hangup: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="video-icon" style="width: 20px; height: 20px; transform: rotate(135deg);"><path d="M10.68 13.31a16 16 0 0 0 3.41 2.6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7 2 2 0 0 1 1.72 2v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.42 19.42 0 0 1-3.33-2.67m-2.67-3.34a19.79 19.79 0 0 1-3.07-8.63A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91"/></svg>`
	};

	// Expose globally so dashboard and checkout scripts can trigger
	window.EG CareVideoCall = {
		join: function(appointmentId) {
			if ( ! appointmentId ) {
				alert('Invalid appointment session.');
				return;
			}

			// Show loading status
			const loadingOverlay = document.createElement('div');
			loadingOverlay.id = 'eg-care-video-loading';
			loadingOverlay.style = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.9); z-index: 1000000; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; font-family: sans-serif;';
			loadingOverlay.innerHTML = `
				<div class="eg-care-loading-spinner" style="border: 4px solid rgba(255,255,255,0.1); border-left-color: #0f766e; width: 50px; height: 50px; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 20px;"></div>
				<h3 style="margin: 0; font-size: 18px;">Initializing Call Room...</h3>
				<p style="margin: 8px 0 0; color: #94a3b8; font-size: 14px;">Retrieving secure RTC tokens</p>
				<style>
					@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
				</style>
			`;
			document.body.appendChild(loadingOverlay);

			// 1. Fetch Agora token from REST API
			fetch(egCareSettings.restUrl + 'video/token', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': egCareSettings.nonce
				},
				body: JSON.stringify({ appointment_id: appointmentId })
			})
			.then(res => {
				if ( ! res.ok ) {
					return res.json().then(err => { throw new Error(err.message || 'Failed to fetch video token.'); });
				}
				return res.json();
			})
			.then(async (data) => {
				// Remove loading overlay
				loadingOverlay.remove();

				// 2. Initialize Agora Call Modal
				injectVideoCallModal(data.doctor_name, data.patient_name, data.is_doctor);
				
				// 3. Connect to Agora Client Session
				await runAgoraSession(data, appointmentId);
			})
			.catch(err => {
				loadingOverlay.remove();
				console.error('Video Call Initialization Failed:', err);
				alert('Call Initialization Failed: ' + err.message);
			});
		}
	};

	// Inject the Call Overlay Modal UI into the page DOM.
	function injectVideoCallModal(doctorName, patientName, isDoctor) {
		// Remove existing modal if any
		const oldModal = document.getElementById('eg-care-video-call-modal');
		if ( oldModal ) {
			oldModal.remove();
		}

		const partnerName = isDoctor ? patientName : doctorName;
		const labelText = isDoctor ? 'Patient Feed' : 'Doctor Feed';

		const modalHtml = `
		<div id="eg-care-video-call-modal" class="eg-care-video-modal">
			<div class="video-modal-header">
				<span id="video-call-title">Consultation Call with ${partnerName}</span>
				<span id="video-call-duration">00:00</span>
			</div>
			
			<div class="video-grid-container">
				<!-- Fullscreen Remote Video Stream -->
				<div id="remote-video-container" class="remote-video-stream">
					<div class="video-placeholder" id="remote-placeholder">
						<div style="font-size: 24px; margin-bottom: 10px;">🕒</div>
						<div>Waiting for ${partnerName} to join the call...</div>
					</div>
				</div>
				
				<!-- Picture-in-Picture Local Video Stream -->
				<div id="local-video-container" class="local-video-stream">
					<div style="position: absolute; bottom: 8px; left: 8px; font-size: 10px; background: rgba(0,0,0,0.5); padding: 2px 6px; border-radius: 4px; color: #fff; z-index: 10; font-weight: bold;">You</div>
				</div>
			</div>
			
			<!-- Circular Media Controls Bar -->
			<div class="video-controls-toolbar">
				<button type="button" id="btn-toggle-mic" class="control-btn mic-on" title="Mute Microphone">${icons.micOn}</button>
				<button type="button" id="btn-toggle-cam" class="control-btn cam-on" title="Disable Camera">${icons.camOn}</button>
				<button type="button" id="btn-end-call" class="control-btn end-call" title="End Consultation">${icons.hangup}</button>
			</div>
		</div>
		`;

		const tempDiv = document.createElement('div');
		tempDiv.innerHTML = modalHtml;
		document.body.appendChild(tempDiv.firstElementChild);
	}

	// Connect and publish tracks to the Agora Channel.
	async function runAgoraSession(credentials, appointmentId) {
		const client = AgoraRTC.createClient({ mode: 'rtc', codec: 'vp8' });
		
		let localAudioTrack = null;
		let localVideoTrack = null;
		let callTimerInterval = null;

		const btnMic = document.getElementById('btn-toggle-mic');
		const btnCam = document.getElementById('btn-toggle-cam');
		const btnEnd = document.getElementById('btn-end-call');

		// Handle Cleanup and Leave Operation
		async function endCallSession() {
			// Clear duration timer
			if ( callTimerInterval ) {
				clearInterval(callTimerInterval);
			}

			// Stop and release local audio track
			if ( localAudioTrack ) {
				localAudioTrack.stop();
				localAudioTrack.close();
			}

			// Stop and release local video track
			if ( localVideoTrack ) {
				localVideoTrack.stop();
				localVideoTrack.close();
			}

			// Leave Agora Channel
			try {
				await client.leave();
			} catch (e) {
				console.error('Error leaving Agora channel:', e);
			}

			// Remove Modal from DOM
			const modal = document.getElementById('eg-care-video-call-modal');
			if ( modal ) {
				modal.remove();
			}

			// Update appointment status to Completed in background
			const loadingNotice = document.createElement('div');
			loadingNotice.style = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.9); z-index: 1000000; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #fff; font-family: sans-serif;';
			loadingNotice.innerHTML = `<h3>Closing Consultation...</h3><p style="color: #94a3b8;">Recording logs</p>`;
			document.body.appendChild(loadingNotice);

			fetch(egCareSettings.restUrl + 'appointments/' + appointmentId + '/complete', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': egCareSettings.nonce
				}
			})
			.then(() => {
				if ( typeof egCareSettings !== 'undefined' && egCareSettings.userType === 'patient' ) {
					if ( loadingNotice ) {
						loadingNotice.remove();
					}
					
					// Render rating modal in DOM.
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
								<button id="btn-skip-review" type="button" style="padding: 10px 16px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; cursor: pointer; color: #475569; font-weight: 600; font-size: 14px;">Skip</button>
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
						window.location.reload();
					});

					// Submit
					submitBtn.addEventListener('click', function() {
						if (selectedRating === 0) return;
						
						submitBtn.disabled = true;
						submitBtn.textContent = 'Submitting...';
						const comment = reviewModal.querySelector('#review-comment').value.trim();

						fetch(egCareSettings.restUrl + 'appointments/' + appointmentId + '/reviews', {
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
						.then(() => {
							reviewModal.remove();
							window.location.reload();
						})
						.catch(err => {
							console.error('Failed to submit review:', err);
							reviewModal.remove();
							window.location.reload();
						});
					});
				} else {
					window.location.reload();
				}
			})
			.catch(err => {
				console.error('Failed to complete appointment:', err);
				window.location.reload();
			});
		}

		try {
			// Join the channel
			await client.join(
				credentials.app_id,
				credentials.channel_name,
				credentials.token,
				null // Passing null lets Agora auto-assign a numeric UID
			);

			// Create Microphone and Camera Tracks
			[localAudioTrack, localVideoTrack] = await AgoraRTC.createMicrophoneAndCameraTracks();

			// Play Local Camera feed inside PiP window
			await localVideoTrack.play('local-video-container');

			// Publish local tracks
			await client.publish([localAudioTrack, localVideoTrack]);

			// Start Call Timer
			let callDurationSeconds = 0;
			callTimerInterval = setInterval(() => {
				callDurationSeconds++;
				const mins = String(Math.floor(callDurationSeconds / 60)).padStart(2, '0');
				const secs = String(callDurationSeconds % 60).padStart(2, '0');
				const durationSpan = document.getElementById('video-call-duration');
				if ( durationSpan ) {
					durationSpan.textContent = `${mins}:${secs}`;
				}
			}, 1000);

			// Subscribing to Remote Participant feeds
			client.on('user-published', async (user, mediaType) => {
				await client.subscribe(user, mediaType);
				
				if ( mediaType === 'video' ) {
					// Hide waiting placeholder
					const placeholder = document.getElementById('remote-placeholder');
					if ( placeholder ) {
						placeholder.style.display = 'none';
					}
					// Play remote participant video
					user.videoTrack.play('remote-video-container');
				}
				
				if ( mediaType === 'audio' ) {
					// Play remote participant audio
					user.audioTrack.play();
				}
			});

			client.on('user-unpublished', (user, mediaType) => {
				if ( mediaType === 'video' ) {
					// Show waiting placeholder
					const placeholder = document.getElementById('remote-placeholder');
					if ( placeholder ) {
						placeholder.style.display = 'block';
					}
				}
			});

			// Media Controls Mappings
			let isMicMuted = false;
			btnMic.addEventListener('click', async () => {
				if ( ! isMicMuted ) {
					await localAudioTrack.setEnabled(false);
					btnMic.classList.remove('mic-on');
					btnMic.classList.add('mic-off');
					btnMic.innerHTML = icons.micOff;
					btnMic.title = 'Unmute Microphone';
					isMicMuted = true;
				} else {
					await localAudioTrack.setEnabled(true);
					btnMic.classList.remove('mic-off');
					btnMic.classList.add('mic-on');
					btnMic.innerHTML = icons.micOn;
					btnMic.title = 'Mute Microphone';
					isMicMuted = false;
				}
			});

			let isCamDisabled = false;
			btnCam.addEventListener('click', async () => {
				if ( ! isCamDisabled ) {
					await localVideoTrack.setEnabled(false);
					btnCam.classList.remove('cam-on');
					btnCam.classList.add('cam-off');
					btnCam.innerHTML = icons.camOff;
					btnCam.title = 'Enable Camera';
					isCamDisabled = true;
				} else {
					await localVideoTrack.setEnabled(true);
					btnCam.classList.remove('cam-off');
					btnCam.classList.add('cam-on');
					btnCam.innerHTML = icons.camOn;
					btnCam.title = 'Disable Camera';
					isCamDisabled = false;
				}
			});

			// End Call button binding
			btnEnd.addEventListener('click', endCallSession);

		} catch (e) {
			console.error('Error starting Agora session:', e);
			alert('Failed to connect to video session: ' + e.message);
			endCallSession();
		}
	}

	// Wire up listeners for any static confirmation receipt elements enqueued in the DOM on load.
	document.addEventListener('DOMContentLoaded', function() {
		// Attach delegator on body to capture dynamically generated buttons.
		document.body.addEventListener('click', function(e) {
			if ( e.target && e.target.classList.contains('eg-care-btn-join-call') ) {
				const appointmentId = e.target.getAttribute('data-id');
				window.EG CareVideoCall.join(appointmentId);
			}
		});
	});
})();
