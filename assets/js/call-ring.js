/**
 * EG Care incoming call ring.
 *
 * There is no realtime channel in this plugin, so the waiting party polls a
 * cheap endpoint. When the other side steps into the room the server leaves a
 * short-lived marker for us, and we ring, flash the tab title and offer to join.
 *
 * @package EG Care
 */

document.addEventListener('DOMContentLoaded', function () {
	if ( typeof egCareSettings === 'undefined' || ! egCareSettings.restUrl ) {
		return;
	}

	const POLL_MS  = 6000;
	const CYCLE_MS = 2600;
	const ORIGINAL_TITLE = document.title;

	let currentCall = null;    // the call we are ringing for
	let declined    = {};      // appointment id -> started_at we already refused
	let audioCtx    = null;
	let cycleTimer  = null;
	let titleTimer  = null;

	// ---------------------------------------------------------------- audio

	// Browsers refuse to start audio without a gesture, so the context is
	// created lazily and resumed again on the first click anywhere. If it never
	// unlocks, the modal and the title flash still do their job.
	function ensureAudio() {
		const Ctx = window.AudioContext || window.webkitAudioContext;
		if ( ! Ctx ) {
			return null;
		}
		if ( ! audioCtx ) {
			try {
				audioCtx = new Ctx();
			} catch ( e ) {
				return null;
			}
		}
		if ( 'suspended' === audioCtx.state ) {
			audioCtx.resume().catch(function () {});
		}
		return audioCtx;
	}

	document.addEventListener('click', function () { ensureAudio(); }, { once: true });

	// One two-note chirp, the second note a fifth above the first.
	function chirp(at) {
		[ 523.25, 659.25 ].forEach(function (freq, i) {
			const start = at + ( i * 0.19 );
			const osc   = audioCtx.createOscillator();
			const gain  = audioCtx.createGain();

			osc.type = 'sine';
			osc.frequency.value = freq;

			gain.gain.setValueAtTime(0.0001, start);
			gain.gain.exponentialRampToValueAtTime(0.16, start + 0.03);
			gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.17);

			osc.connect(gain);
			gain.connect(audioCtx.destination);
			osc.start(start);
			osc.stop(start + 0.2);
		});
	}

	function ringOnce() {
		const ctx = ensureAudio();
		if ( ! ctx || 'running' !== ctx.state ) {
			return;
		}
		chirp(ctx.currentTime + 0.02);
		chirp(ctx.currentTime + 0.62);
	}

	// ------------------------------------------------------------ tab title

	function startTitleFlash() {
		let showAlert = true;
		titleTimer = setInterval(function () {
			document.title = showAlert ? '☎ Incoming call…' : ORIGINAL_TITLE;
			showAlert = ! showAlert;
		}, 900);
	}

	function stopTitleFlash() {
		if ( titleTimer ) {
			clearInterval(titleTimer);
			titleTimer = null;
		}
		document.title = ORIGINAL_TITLE;
	}

	// ---------------------------------------------------------------- modal

	function buildModal(call) {
		const wrap = document.createElement('div');
		wrap.id = 'eg-care-incoming-call';
		wrap.style = 'position: fixed; inset: 0; background: rgba(15,23,42,0.85); z-index: 2000001; display: flex; align-items: center; justify-content: center; font-family: -apple-system, BlinkMacSystemFont, sans-serif;';

		wrap.innerHTML = `
			<div style="background:#fff; padding:32px; border-radius:14px; max-width:380px; width:90%; text-align:center; box-shadow:0 25px 50px -12px rgba(0,0,0,0.35);">
				<div class="eg-care-incoming-avatar" style="width:76px; height:76px; margin:0 auto 18px; border-radius:50%; background:#d1fae5; color:#0f766e; display:flex; align-items:center; justify-content:center; font-size:34px;">&#9742;</div>
				<p style="margin:0 0 4px; font-size:13px; letter-spacing:.06em; text-transform:uppercase; color:#64748b; font-weight:700;">Incoming consultation call</p>
				<h3 class="eg-care-incoming-name" style="margin:0 0 24px; font-size:21px; color:#0f172a; font-weight:700;"></h3>
				<div style="display:flex; gap:12px; justify-content:center;">
					<button type="button" class="eg-care-incoming-decline" style="flex:1; padding:12px; border-radius:8px; border:1px solid #cbd5e1; background:#fff; color:#475569; font-weight:700; font-size:14px; cursor:pointer;">Decline</button>
					<button type="button" class="eg-care-incoming-join" style="flex:1; padding:12px; border-radius:8px; border:none; background:#16a34a; color:#fff; font-weight:700; font-size:14px; cursor:pointer;">Join Call</button>
				</div>
			</div>
		`;

		// The name comes from the database, so it is set as text, never markup.
		wrap.querySelector('.eg-care-incoming-name').textContent = call.from_name || 'Your consultation';

		wrap.querySelector('.eg-care-incoming-join').addEventListener('click', function () {
			const id = call.appointment_id;
			stopRinging();
			if ( window.EGCareVideoCall ) {
				window.EGCareVideoCall.join(id);
			}
		});

		wrap.querySelector('.eg-care-incoming-decline').addEventListener('click', function () {
			declined[ call.appointment_id ] = call.started_at;
			stopRinging();
		});

		// A gentle pulse on the avatar, in step with the ring.
		const style = document.createElement('style');
		style.textContent = '@keyframes egCareRingPulse{0%,100%{transform:scale(1);}50%{transform:scale(1.08);}}'
			+ '#eg-care-incoming-call .eg-care-incoming-avatar{animation:egCareRingPulse 1.3s ease-in-out infinite;}';
		wrap.appendChild(style);

		return wrap;
	}

	// -------------------------------------------------------------- ringing

	function startRinging(call) {
		currentCall = call;
		document.body.appendChild(buildModal(call));
		ringOnce();
		cycleTimer = setInterval(ringOnce, CYCLE_MS);
		startTitleFlash();
	}

	function stopRinging() {
		currentCall = null;

		if ( cycleTimer ) {
			clearInterval(cycleTimer);
			cycleTimer = null;
		}
		stopTitleFlash();

		const modal = document.getElementById('eg-care-incoming-call');
		if ( modal ) {
			modal.remove();
		}
	}

	// -------------------------------------------------------------- polling

	function poll() {
		// Never ring over a call that is already running.
		if ( document.getElementById('eg-care-video-call-modal') ) {
			stopRinging();
			return;
		}

		fetch(egCareSettings.restUrl + 'calls/incoming', {
			headers: { 'X-WP-Nonce': egCareSettings.nonce }
		})
		.then(function (res) { return res.ok ? res.json() : null; })
		.then(function (data) {
			if ( ! data || ! data.ringing ) {
				stopRinging();
				return;
			}
			if ( declined[ data.appointment_id ] === data.started_at ) {
				return;
			}
			if ( currentCall && currentCall.appointment_id === data.appointment_id ) {
				return;   // already ringing for this one
			}
			stopRinging();
			startRinging(data);
		})
		.catch(function () { /* a dropped poll is not worth reporting */ });
	}

	setInterval(poll, POLL_MS);
	poll();
});
