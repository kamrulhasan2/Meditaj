/**
 * The doctor's prescription screen.
 *
 * Two views in one tab: a list of consultations, and the form for one of them.
 * The form auto-saves, because a doctor writing during a video call should
 * never lose a line to a dropped connection, and because "did that save?" is
 * the question that makes people distrust a tool.
 *
 * Most of this file is about saving the doctor keystrokes: their own
 * prescribing history offered back to them, the dose they usually write filled
 * in for them, the common doses one tap away, and whole prescriptions they can
 * save and reuse. None of it is a drug database, and none of it needs setting
 * up - it is built from what this doctor has already done.
 *
 * Nothing here decides who may see a prescription. The server does that; this
 * file only shows what the server returns.
 */
document.addEventListener('DOMContentLoaded', function () {
	const pane = document.getElementById('pane-prescriptions');

	if (!pane || typeof egCareSettings === 'undefined') {
		return;
	}

	const listView   = document.getElementById('rx-list-view');
	const formView   = document.getElementById('rx-form-view');
	const listTarget = document.getElementById('rx-list-target');
	const rows       = document.getElementById('rx-medicines');
	const saveState  = document.getElementById('rx-save-state');
	const submitBtn  = document.getElementById('rx-submit');
	const backBtn    = document.getElementById('rx-back');
	const addRowBtn  = document.getElementById('rx-add-medicine');
	const noteBox    = document.getElementById('rx-returned-note');

	const reviseBtn  = document.getElementById('rx-revise');
	const viewLink   = document.getElementById('rx-view');

	const sigPreview = document.getElementById('rx-sig-preview');
	const sigNone    = document.getElementById('rx-sig-none');
	const sigFile    = document.getElementById('rx-sig-file');
	const sigUpload  = document.getElementById('rx-sig-upload');
	const sigRemove  = document.getElementById('rx-sig-remove');
	const sigState   = document.getElementById('rx-sig-state');

	const templateBar    = document.getElementById('rx-template-bar');
	const templatePick   = document.getElementById('rx-template-pick');
	const templateSave   = document.getElementById('rx-template-save');
	const templateDelete = document.getElementById('rx-template-delete');

	const fields = {
		complaints:    document.getElementById('rx-complaints'),
		diagnosis:     document.getElementById('rx-diagnosis'),
		advice:        document.getElementById('rx-advice'),
		followup_date: document.getElementById('rx-followup')
	};

	let current      = null;   // the consultation being written up
	let maxMedicines = 30;
	let saveTimer    = null;
	let dirty        = false;
	let saving       = false;

	// Filled from the server when a prescription is opened.
	let suggestions = [];      // [{name, dose, duration, instruction}]
	let byName      = {};      // lowercase name -> suggestion
	let doseChips   = [];
	let templates   = [];

	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text === null || text === undefined ? '' : String(text);
		return div.innerHTML;
	}

	function api(path, options) {
		const opts = options || {};
		opts.headers = Object.assign({ 'X-WP-Nonce': egCareSettings.nonce }, opts.headers || {});

		if (opts.body && typeof opts.body !== 'string') {
			opts.headers['Content-Type'] = 'application/json';
			opts.body = JSON.stringify(opts.body);
		}

		return fetch(egCareSettings.restUrl + path, opts).then(function (response) {
			return response.json().then(function (data) {
				if (!response.ok) {
					throw new Error((data && data.message) || 'Request failed.');
				}
				return data;
			});
		});
	}

	// ---------------------------------------------------------------- list

	function statusBadge(rx) {
		if (!rx) {
			return '<span class="rx-badge rx-badge-none">Not written yet</span>';
		}

		const cls = {
			draft:     'rx-badge-draft',
			submitted: 'rx-badge-waiting',
			returned:  'rx-badge-returned',
			sent:      'rx-badge-sent'
		}[rx.status] || 'rx-badge-none';

		return '<span class="rx-badge ' + cls + '">' + escapeHtml(rx.status_label) + '</span>';
	}

	function actionLabel(rx) {
		if (!rx) {
			return 'Write prescription';
		}
		if (rx.status === 'draft') {
			return 'Continue draft';
		}
		if (rx.status === 'returned') {
			return 'Make the changes';
		}
		return 'View';
	}

	function whenText(item) {
		if (item.appointment_date) {
			return escapeHtml(item.appointment_date + (item.appointment_time ? ' ' + item.appointment_time.slice(0, 5) : ''));
		}
		return item.appointment_type === 'instant' ? 'Instant call' : '&mdash;';
	}

	function renderList(consultations) {
		if (!consultations.length) {
			listTarget.innerHTML = '<p class="eg-care-no-data">No finished consultations yet. '
				+ 'Once a paid consultation is under way or complete, it appears here so you can write the prescription.</p>';
			return;
		}

		const table = document.createElement('table');
		table.className = 'eg-care-dashboard-table';
		table.innerHTML = '<thead><tr>'
			+ '<th>Patient</th><th>When</th><th>Prescription</th><th>Action</th>'
			+ '</tr></thead><tbody></tbody>';

		const body = table.querySelector('tbody');

		consultations.forEach(function (item) {
			const tr = document.createElement('tr');
			const who = escapeHtml(item.patient_name)
				+ (item.relation ? ' <small>(' + escapeHtml(item.relation) + ')</small>' : '')
				+ (item.age ? ' <small>' + escapeHtml(item.age) + 'y</small>' : '');

			tr.innerHTML = '<td data-label="Patient">' + who + '</td>'
				+ '<td data-label="When">' + whenText(item) + '</td>'
				+ '<td data-label="Prescription">' + statusBadge(item.prescription) + '</td>'
				+ '<td data-label="Action"><button type="button" class="eg-care-btn-small rx-open">'
				+ escapeHtml(actionLabel(item.prescription)) + '</button></td>';

			tr.querySelector('.rx-open').addEventListener('click', function () {
				openForm(item.appointment_id);
			});

			body.appendChild(tr);
		});

		listTarget.innerHTML = '';
		listTarget.appendChild(table);
	}

	function loadList() {
		listTarget.innerHTML = '<p class="eg-care-no-data">Loading consultations&hellip;</p>';

		return api('doctor/me/prescriptions')
			.then(function (data) {
				renderList(data.consultations || []);
				renderSignature(data.signature);
				return data.consultations || [];
			})
			.catch(function (error) {
				listTarget.innerHTML = '<p class="eg-care-no-data">' + escapeHtml(error.message) + '</p>';
				return [];
			});
	}

	// ----------------------------------------------------------- signature

	function renderSignature(signature) {
		const has = !!(signature && signature.url);

		sigPreview.hidden = !has;
		sigNone.hidden    = has;
		sigRemove.hidden  = !has;

		if (has) {
			sigPreview.src = signature.url;
		}
	}

	function sigSay(message, kind) {
		sigState.textContent = message || '';
		sigState.className   = 'rx-sig-state' + (kind ? ' rx-' + kind : '');
	}

	if (sigUpload) {
		sigUpload.addEventListener('click', function () {
			sigFile.click();
		});
	}

	if (sigFile) {
		sigFile.addEventListener('change', function () {
			const file = sigFile.files[0];

			if (!file) {
				return;
			}

			if (file.size > 2 * 1024 * 1024) {
				sigSay('That image is over 2MB. A signature only needs to be small.', 'error');
				sigFile.value = '';
				return;
			}

			sigSay('Uploading\u2026', 'pending');

			// Into the media library first, exactly as the profile photo goes,
			// then the id is pointed at. The server checks the image is this
			// doctor's own before it will use it.
			fetch(egCareSettings.restUrl.replace('eg-care/v1/', 'wp/v2/media'), {
				method: 'POST',
				headers: {
					'X-WP-Nonce': egCareSettings.nonce,
					'Content-Disposition': 'attachment; filename="' + encodeURIComponent(file.name) + '"',
					'Content-Type': file.type
				},
				body: file
			})
				.then(function (response) {
					return response.json().then(function (data) {
						if (!response.ok) {
							throw new Error((data && data.message) || 'Upload failed.');
						}
						return data;
					});
				})
				.then(function (media) {
					return api('doctor/me/signature', {
						method: 'POST',
						body:   { attachment_id: media.id }
					});
				})
				.then(function (data) {
					renderSignature(data.signature);
					sigSay('Saved. It will appear on prescriptions from now on.', 'ok');
				})
				.catch(function (error) {
					sigSay(error.message, 'error');
				})
				.then(function () {
					sigFile.value = '';
				});
		});
	}

	if (sigRemove) {
		sigRemove.addEventListener('click', function () {
			if (!window.confirm('Remove your signature?\n\nIt will come off prescriptions you have already issued as well as new ones.')) {
				return;
			}

			api('doctor/me/signature', { method: 'DELETE' })
				.then(function (data) {
					renderSignature(data.signature);
					sigSay('Removed.', 'ok');
				})
				.catch(function (error) {
					sigSay(error.message, 'error');
				});
		});
	}

	// --------------------------------------------------------- suggestions

	function fillDatalist(id, values) {
		const list = document.getElementById(id);

		if (!list) {
			return;
		}

		list.innerHTML = '';

		values.forEach(function (value) {
			if (!value) {
				return;
			}
			const option = document.createElement('option');
			option.value = value;
			list.appendChild(option);
		});
	}

	function loadSuggestions(data) {
		suggestions = data.suggestions || [];
		doseChips   = data.dose_chips || [];

		byName = {};
		suggestions.forEach(function (entry) {
			byName[entry.name.toLowerCase()] = entry;
		});

		const options = data.options || {};

		fillDatalist('rx-dl-medicine', suggestions.map(function (s) { return s.name; }));
		fillDatalist('rx-dl-dose', options.dose || []);
		fillDatalist('rx-dl-duration', options.duration || []);
		fillDatalist('rx-dl-instruction', options.instruction || []);
	}

	// ------------------------------------------------------------ medicines

	function medicineRow(medicine) {
		const tr = document.createElement('tr');
		const m  = medicine || { name: '', dose: '', duration: '', instruction: '' };

		const chips = doseChips.map(function (value) {
			return '<button type="button" class="rx-chip" data-dose="' + escapeHtml(value) + '">' + escapeHtml(value) + '</button>';
		}).join('');

		tr.className = 'rx-medicine-row';
		tr.innerHTML = ''
			+ '<td class="rx-num" data-label="#"></td>'
			+ '<td data-label="Medicine"><input type="text" class="rx-m-name" list="rx-dl-medicine" placeholder="e.g. Napa 500mg" autocomplete="off">'
			+ '<span class="rx-row-warning">Add a medicine name, or this line will not be saved.</span></td>'
			+ '<td data-label="Dose"><input type="text" class="rx-m-dose" list="rx-dl-dose" placeholder="1+0+1" autocomplete="off">'
			+ (chips ? '<div class="rx-chips">' + chips + '</div>' : '') + '</td>'
			+ '<td data-label="Duration"><input type="text" class="rx-m-duration" list="rx-dl-duration" placeholder="7 days" autocomplete="off"></td>'
			+ '<td data-label="Instruction"><input type="text" class="rx-m-instruction" list="rx-dl-instruction" placeholder="After meals" autocomplete="off"></td>'
			+ '<td class="rx-act" data-label="Remove"><button type="button" class="rx-remove" aria-label="Remove this medicine" title="Remove this medicine">&times;</button></td>';

		const name        = tr.querySelector('.rx-m-name');
		const dose        = tr.querySelector('.rx-m-dose');
		const duration    = tr.querySelector('.rx-m-duration');
		const instruction = tr.querySelector('.rx-m-instruction');

		name.value        = m.name || '';
		dose.value        = m.dose || '';
		duration.value    = m.duration || '';
		instruction.value = m.instruction || '';

		tr.querySelector('.rx-remove').addEventListener('click', function () {
			tr.remove();
			syncRows();
			touched();
		});

		// Typing into the last row opens the next one, so adding medicines is
		// just typing - no hunting for a button between each.
		//
		// And once the name matches something this doctor has prescribed
		// before, the dose they usually write appears with it. Only into empty
		// boxes: a dose they have already typed is a decision, not a gap.
		name.addEventListener('input', function () {
			const match = byName[name.value.trim().toLowerCase()];

			if (match) {
				if (!dose.value && match.dose) { dose.value = match.dose; }
				if (!duration.value && match.duration) { duration.value = match.duration; }
				if (!instruction.value && match.instruction) { instruction.value = match.instruction; }
			}

			syncRows();
		});

		// A dose with no medicine beside it is dropped on save. Say so while it
		// is being typed rather than letting it disappear quietly.
		[dose, duration, instruction].forEach(function (input) {
			input.addEventListener('input', syncRows);
		});

		tr.querySelectorAll('.rx-chip').forEach(function (chip) {
			chip.addEventListener('click', function () {
				dose.value = chip.getAttribute('data-dose');
				touched();
			});
		});

		return tr;
	}

	function rowIsEmpty(tr) {
		return !tr.querySelector('.rx-m-name').value.trim()
			&& !tr.querySelector('.rx-m-dose').value.trim()
			&& !tr.querySelector('.rx-m-duration').value.trim()
			&& !tr.querySelector('.rx-m-instruction').value.trim();
	}

	/**
	 * Put the table back into its one valid shape.
	 *
	 * Exactly one blank row at the bottom, waiting to be typed into, and that
	 * row carries no cross - there is nothing to remove, and a cross that
	 * removes a row only for it to reappear is what made the old version feel
	 * broken.
	 *
	 * Also renumbers, and flags any line that has a dose but no medicine,
	 * because that line is dropped when the prescription is saved.
	 */
	function syncRows() {
		let all = Array.prototype.slice.call(rows.querySelectorAll('.rx-medicine-row'));

		// Collapse any run of blank rows at the bottom down to one.
		while (all.length > 1 && rowIsEmpty(all[all.length - 1]) && rowIsEmpty(all[all.length - 2])) {
			all.pop().remove();
		}

		all = Array.prototype.slice.call(rows.querySelectorAll('.rx-medicine-row'));

		const room = all.length < maxMedicines;

		if (!all.length || (room && !rowIsEmpty(all[all.length - 1]))) {
			rows.appendChild(medicineRow(null));
			all = Array.prototype.slice.call(rows.querySelectorAll('.rx-medicine-row'));
		}

		all.forEach(function (tr, index) {
			tr.querySelector('.rx-num').textContent = (index + 1) + '.';

			const isTrailingBlank = index === all.length - 1 && rowIsEmpty(tr);
			tr.querySelector('.rx-remove').hidden = isTrailingBlank;

			const name  = tr.querySelector('.rx-m-name').value.trim();
			const other = tr.querySelector('.rx-m-dose').value.trim()
				|| tr.querySelector('.rx-m-duration').value.trim()
				|| tr.querySelector('.rx-m-instruction').value.trim();

			tr.classList.toggle('is-incomplete', !name && !!other);
		});
	}

	/** Lines that would be thrown away on save. */
	function incompleteRows() {
		return rows.querySelectorAll('.rx-medicine-row.is-incomplete');
	}

	function readMedicines() {
		const out = [];

		rows.querySelectorAll('.rx-medicine-row').forEach(function (tr) {
			const name = tr.querySelector('.rx-m-name').value.trim();

			if (!name) {
				return;
			}

			out.push({
				name:        name,
				dose:        tr.querySelector('.rx-m-dose').value.trim(),
				duration:    tr.querySelector('.rx-m-duration').value.trim(),
				instruction: tr.querySelector('.rx-m-instruction').value.trim()
			});
		});

		return out;
	}

	// ------------------------------------------------------------ templates

	function renderTemplates() {
		if (!templatePick) {
			return;
		}

		templatePick.innerHTML = '<option value="">Load a saved prescription&hellip;</option>';

		templates.forEach(function (template) {
			const option = document.createElement('option');
			option.value       = template.id;
			option.textContent = template.name;
			templatePick.appendChild(option);
		});

		templatePick.disabled    = !templates.length;
		templateDelete.hidden    = true;
		templateBar.hidden       = !current || !current.editable;
	}

	function applyTemplate(id) {
		const template = templates.filter(function (t) { return t.id === id; })[0];

		if (!template) {
			return;
		}

		// Everything except the follow-up date, which belongs to this patient
		// on this day and never to a saved pattern.
		const content = Object.assign({}, template.content, { followup_date: fields.followup_date.value });

		fillForm(content);
		touched();
		say('Loaded "' + template.name + '". Change whatever this patient needs.', 'ok');
	}

	// ----------------------------------------------------------------- form

	function readForm() {
		return {
			complaints:    fields.complaints.value,
			diagnosis:     fields.diagnosis.value,
			advice:        fields.advice.value,
			followup_date: fields.followup_date.value,
			medicines:     readMedicines()
		};
	}

	function fillForm(content) {
		fields.complaints.value    = content.complaints || '';
		fields.diagnosis.value     = content.diagnosis || '';
		fields.advice.value        = content.advice || '';
		fields.followup_date.value = content.followup_date || '';

		rows.innerHTML = '';
		(content.medicines || []).forEach(function (medicine) {
			rows.appendChild(medicineRow(medicine));
		});
		syncRows();
	}

	function setReadOnly(readOnly) {
		formView.classList.toggle('rx-readonly', readOnly);

		Object.keys(fields).forEach(function (key) {
			fields[key].disabled = readOnly;
		});

		rows.querySelectorAll('input').forEach(function (input) {
			input.disabled = readOnly;
		});

		rows.querySelectorAll('.rx-remove, .rx-chip').forEach(function (button) {
			button.disabled = readOnly;
		});

		addRowBtn.disabled = readOnly;
		submitBtn.disabled = readOnly;
		submitBtn.hidden   = readOnly;
		templateBar.hidden = readOnly;
	}

	function say(message, kind) {
		saveState.textContent = message;
		saveState.className   = 'rx-save-state' + (kind ? ' rx-' + kind : '');
	}

	function touched() {
		if (!current || !current.editable) {
			return;
		}

		dirty = true;
		say('Unsaved changes', 'pending');

		window.clearTimeout(saveTimer);
		saveTimer = window.setTimeout(save, 1500);
	}

	function save() {
		if (!current || !current.editable || saving) {
			return Promise.resolve(false);
		}

		window.clearTimeout(saveTimer);
		saving = true;
		say('Saving…', 'pending');

		return api('doctor/me/prescriptions/' + current.appointment_id, {
			method: 'POST',
			body:   { content: readForm() }
		})
			.then(function (data) {
				saving = false;
				dirty  = false;
				const now = new Date();
				say('Saved as draft at ' + now.toTimeString().slice(0, 5), 'ok');
				submitBtn.disabled = !data.can_submit;
				return true;
			})
			.catch(function (error) {
				saving = false;
				say('Not saved — ' + error.message, 'error');
				return false;
			});
	}

	function openForm(appointmentId) {
		listView.hidden = true;
		formView.hidden = false;
		say('Loading…', 'pending');

		api('doctor/me/prescriptions/' + appointmentId)
			.then(function (data) {
				const rx = data.prescription;

				current = {
					appointment_id: data.appointment_id,
					editable:       !rx || rx.editable
				};

				maxMedicines = data.max_medicines || 30;

				loadSuggestions(data);
				templates = data.templates || [];

				document.getElementById('rx-patient').textContent = data.patient_name
					+ (data.relation ? ' (' + data.relation + ')' : '')
					+ (data.age ? ', ' + data.age + ' years' : '');

				document.getElementById('rx-when').textContent = data.appointment_date
					? data.appointment_date + (data.appointment_time ? ' ' + data.appointment_time.slice(0, 5) : '')
					: (data.appointment_type === 'instant' ? 'Instant call' : '');

				const complaintNote = document.getElementById('rx-symptoms');
				complaintNote.textContent = data.symptom_notes || '';
				complaintNote.hidden      = !data.symptom_notes;

				renderReports(data.reports || []);

				if (rx && rx.status === 'returned' && rx.moderator_note) {
					noteBox.textContent = rx.moderator_note;
					noteBox.hidden = false;
				} else {
					noteBox.hidden = true;
				}

				fillForm(data.content || {});
				setReadOnly(!current.editable);
				renderTemplates();

				const isSent = !!rx && rx.status === 'sent';

				reviseBtn.hidden = !isSent;
				viewLink.hidden  = !isSent || !data.view_url;

				if (data.view_url) {
					viewLink.href = data.view_url;
				}

				if (!current.editable) {
					say(rx ? rx.status_label : '', 'ok');
				} else {
					say(rx ? 'Draft loaded' : 'New prescription', '');
					submitBtn.disabled = false;
				}

				dirty = false;
			})
			.catch(function (error) {
				say(error.message, 'error');
			});
	}

	function renderReports(reports) {
		const target = document.getElementById('rx-reports');

		if (!reports.length) {
			target.hidden = true;
			return;
		}

		target.hidden = false;
		target.innerHTML = '<strong>Patient’s attachments:</strong> '
			+ reports.map(function (report, index) {
				return '<a href="' + report.url + '" target="_blank" rel="noopener">'
					+ escapeHtml(report.name || ('Report ' + (index + 1))) + '</a>';
			}).join(', ');
	}

	function closeForm() {
		current         = null;
		formView.hidden = true;
		listView.hidden = false;
		loadList();
	}

	// -------------------------------------------------------------- wiring

	Object.keys(fields).forEach(function (key) {
		fields[key].addEventListener('input', touched);
	});

	rows.addEventListener('input', touched);

	// There is always a blank row waiting, so this button's job is to put the
	// cursor in it - not to add a second blank row below the first.
	addRowBtn.addEventListener('click', function () {
		if (rows.querySelectorAll('.rx-medicine-row').length >= maxMedicines) {
			say('That is as many medicines as one prescription can hold.', 'error');
			return;
		}

		syncRows();

		const last = rows.querySelector('.rx-medicine-row:last-child .rx-m-name');

		if (last) {
			last.focus();
			last.scrollIntoView({ block: 'center', behavior: 'smooth' });
		}
	});

	if (templatePick) {
		templatePick.addEventListener('change', function () {
			templateDelete.hidden = !templatePick.value;

			if (templatePick.value) {
				applyTemplate(templatePick.value);
			}
		});
	}

	if (templateSave) {
		templateSave.addEventListener('click', function () {
			const content = readForm();

			if (!content.medicines.length && !content.diagnosis.trim()) {
				say('Write the prescription first, then save it as a template.', 'error');
				return;
			}

			const name = window.prompt('Save this prescription as:', content.diagnosis.trim().slice(0, 60));

			if (!name) {
				return;
			}

			api('doctor/me/prescription-templates', {
				method: 'POST',
				body:   { name: name, content: content }
			})
				.then(function (data) {
					templates = data.templates || [];
					renderTemplates();
					say('Saved as "' + data.saved.name + '". It is in the list above.', 'ok');
				})
				.catch(function (error) {
					say('Not saved — ' + error.message, 'error');
				});
		});
	}

	if (templateDelete) {
		templateDelete.addEventListener('click', function () {
			const id       = templatePick.value;
			const template = templates.filter(function (t) { return t.id === id; })[0];

			if (!template || !window.confirm('Delete the saved prescription "' + template.name + '"?')) {
				return;
			}

			api('doctor/me/prescription-templates/' + encodeURIComponent(id), { method: 'DELETE' })
				.then(function (data) {
					templates = data.templates || [];
					renderTemplates();
					say('Deleted.', 'ok');
				})
				.catch(function (error) {
					say(error.message, 'error');
				});
		});
	}

	if (reviseBtn) {
		reviseBtn.addEventListener('click', function () {
			if (!current) {
				return;
			}

			if (!window.confirm('Write a correction?\n\nThe copy the patient already has stays as it is and will say it has been replaced. The correction goes back to the reviewer before it reaches them.')) {
				return;
			}

			reviseBtn.disabled = true;
			say('Starting a correction\u2026', 'pending');

			const appointmentId = current.appointment_id;

			api('doctor/me/prescriptions/' + appointmentId + '/revise', { method: 'POST' })
				.then(function () {
					reviseBtn.disabled = false;
					openForm(appointmentId);
				})
				.catch(function (error) {
					reviseBtn.disabled = false;
					say(error.message, 'error');
				});
		});
	}

	backBtn.addEventListener('click', function () {
		if (dirty && current && current.editable) {
			save().then(closeForm);
			return;
		}

		closeForm();
	});

	submitBtn.addEventListener('click', function () {
		if (!current || !current.editable) {
			return;
		}

		const content = readForm();

		// readForm() throws away a line with no medicine name. Better to stop
		// and point at it than to send a prescription missing something the
		// doctor thought they had written.
		const unnamed = incompleteRows();

		if (unnamed.length) {
			say('One line has a dose but no medicine. Add the name, or clear the line.', 'error');
			unnamed[0].querySelector('.rx-m-name').focus();
			unnamed[0].scrollIntoView({ block: 'center', behavior: 'smooth' });
			return;
		}

		if (!content.medicines.length && !content.diagnosis.trim()) {
			say('Add a diagnosis or at least one medicine before submitting.', 'error');
			return;
		}

		if (!window.confirm('Send this prescription for review?\n\nYou will not be able to change it while the reviewer has it.')) {
			return;
		}

		submitBtn.disabled = true;
		say('Submitting…', 'pending');

		api('doctor/me/prescriptions/' + current.appointment_id + '/submit', {
			method: 'POST',
			body:   { content: content }
		})
			.then(function () {
				dirty = false;
				say('Submitted. The reviewer will send it to the patient.', 'ok');
				window.setTimeout(closeForm, 1200);
			})
			.catch(function (error) {
				submitBtn.disabled = false;
				say('Not submitted — ' + error.message, 'error');
			});
	});

	// A half-written prescription is worth a warning before the tab closes.
	window.addEventListener('beforeunload', function (event) {
		if (dirty && current && current.editable) {
			event.preventDefault();
			event.returnValue = '';
		}
	});

	// The list is only fetched when the tab is first opened, so the dashboard
	// does not pay for it on every page load.
	let loaded = false;

	function openTab() {
		document.querySelectorAll('.tab-trigger').forEach(function (tab) {
			tab.classList.toggle('active', tab.getAttribute('data-tab') === 'prescriptions');
		});

		document.querySelectorAll('.tab-pane').forEach(function (p) {
			p.classList.toggle('active', p.id === 'pane-prescriptions');
		});
	}

	document.querySelectorAll('.tab-trigger').forEach(function (tab) {
		tab.addEventListener('click', function () {
			if (tab.getAttribute('data-tab') !== 'prescriptions' || loaded) {
				return;
			}

			loaded = true;
			loadList();
		});
	});

	// The call has just finished. The prescription is the next thing the
	// doctor has to do, so put them in front of it rather than making them
	// find the tab. video-call.js fires this on the same page.
	document.addEventListener('eg-care:call-completed', function (event) {
		if (egCareSettings.userType !== 'doctor') {
			return;
		}

		const appointmentId = event.detail && event.detail.appointmentId;

		if (!appointmentId) {
			return;
		}

		loaded = true;
		openTab();
		openForm(appointmentId);
	});
});
