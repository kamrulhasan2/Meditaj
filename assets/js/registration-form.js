/**
 * Doctor registration form: payout tabs, file labels and client-side checks.
 *
 * Messages come from egCareRegistration, localised in
 * Shortcodes::render_registration_shortcode().
 *
 * @package EG Care
 */

document.addEventListener('DOMContentLoaded', function() {
	const i18n = ( 'undefined' !== typeof egCareRegistration && egCareRegistration.i18n ) ? egCareRegistration.i18n : {};

	// Tabs toggle bank vs mobile
	const btnBank = document.getElementById('btn_acc_bank');
	const btnMobile = document.getElementById('btn_acc_mobile');
	const inputAccType = document.getElementById('reg_acc_type');
	const bankSection = document.getElementById('sec_acc_bank');
	const mobileSection = document.getElementById('sec_acc_mobile');

	// The form is not on the page after a successful registration.
	if ( ! btnBank || ! btnMobile || ! inputAccType || ! bankSection || ! mobileSection ) {
		return;
	}

	btnBank.addEventListener('click', function(e) {
		e.preventDefault();
		btnBank.classList.add('active');
		btnMobile.classList.remove('active');
		inputAccType.value = 'bank';
		bankSection.style.display = 'grid';
		mobileSection.style.display = 'none';

		bankSection.querySelectorAll('input, select').forEach(el => el.setAttribute('required', 'required'));
		mobileSection.querySelectorAll('input, select').forEach(el => el.removeAttribute('required'));
	});

	btnMobile.addEventListener('click', function(e) {
		e.preventDefault();
		btnMobile.classList.add('active');
		btnBank.classList.remove('active');
		inputAccType.value = 'mobile';
		mobileSection.style.display = 'grid';
		bankSection.style.display = 'none';

		mobileSection.querySelectorAll('input, select').forEach(el => el.setAttribute('required', 'required'));
		bankSection.querySelectorAll('input, select').forEach(el => el.removeAttribute('required'));
	});

	// Filename updater inside labels
	document.getElementById('reg_photo').addEventListener('change', function(e) {
		const nameSpan = document.getElementById('reg_photo_name');
		if (e.target.files.length > 0) {
			nameSpan.textContent = e.target.files[0].name;
		} else {
			nameSpan.textContent = i18n.noFileChosen;
		}
	});

	document.getElementById('reg_certificate').addEventListener('change', function(e) {
		const nameSpan = document.getElementById('reg_certificate_name');
		if (e.target.files.length > 0) {
			nameSpan.textContent = e.target.files[0].name;
		} else {
			nameSpan.textContent = i18n.noFileChosen;
		}
	});

	// Form validation before sending request to backend
	const registerForm = document.querySelector('.eg-care-register-form');
	if (registerForm) {
		registerForm.addEventListener('submit', function(e) {
			let errors = [];

			// 1. Required text fields check
			const requiredFields = [
				{ id: 'reg_first_name', label: 'Name' },
				{ id: 'reg_email', label: 'Email' },
				{ id: 'reg_mobile', label: 'Mobile' },
				{ id: 'reg_nid', label: 'NID' },
				{ id: 'reg_nationality', label: 'Nationality' },
				{ id: 'reg_fee', label: 'Consultation Fee' },
				{ id: 'reg_username', label: 'Username' },
				{ id: 'reg_password', label: 'Password' },
				{ id: 'reg_license', label: 'BMDC Code' },
				{ id: 'reg_bmdc_expiry', label: 'BMDC Expiry Date' },
				{ id: 'reg_degree', label: 'Degrees' },
				{ id: 'reg_specialty_id', label: 'Specialty' },
				{ id: 'reg_experience', label: 'Years of Experience' },
				{ id: 'reg_follow_up_days', label: 'Follow up days' },
				{ id: 'reg_follow_up_cost', label: 'Follow up cost' }
			];

			requiredFields.forEach(function(field) {
				const el = document.getElementById(field.id);
				if (el && !el.value.trim()) {
					errors.push(field.label + ' ' + i18n.required);
					el.style.border = '1px solid #ef4444';
				} else if (el) {
					el.style.border = ''; // reset
				}
			});

			// 2. Validate payout fields depending on selected tab
			if (inputAccType.value === 'bank') {
				const bankFields = [
					{ id: 'reg_bank_name', label: 'Bank Name' },
					{ id: 'reg_bank_branch', label: 'Branch Name' },
					{ id: 'reg_bank_no', label: 'Account Number' },
					{ id: 'reg_bank_acc_name', label: 'Account Name' },
					{ id: 'reg_bank_routing', label: 'Routing Number' }
				];
				bankFields.forEach(function(field) {
					const el = document.getElementById(field.id);
					if (el && !el.value.trim()) {
						errors.push(field.label + ' ' + i18n.requiredBank);
						el.style.border = '1px solid #ef4444';
					} else if (el) {
						el.style.border = '';
					}
				});
			} else {
				const mobileFields = [
					{ id: 'reg_m_wallet', label: 'Mobile Payout Wallet' },
					{ id: 'reg_m_no', label: 'Mobile Wallet Number' }
				];
				mobileFields.forEach(function(field) {
					const el = document.getElementById(field.id);
					if (el && !el.value.trim()) {
						errors.push(field.label + ' ' + i18n.requiredMobile);
						el.style.border = '1px solid #ef4444';
					} else if (el) {
						el.style.border = '';
					}
				});
			}

			// 3. File size limits (Max 2 MB)
			const maxFileSize = 2 * 1024 * 1024; // 2MB

			const photoInput = document.getElementById('reg_photo');
			if (photoInput && photoInput.files.length > 0) {
				if (photoInput.files[0].size > maxFileSize) {
					errors.push(i18n.photoTooLarge);
					photoInput.style.outline = '1px solid #ef4444';
				} else {
					photoInput.style.outline = '';
				}
			}

			const certInput = document.getElementById('reg_certificate');
			if (certInput && certInput.files.length > 0) {
				if (certInput.files[0].size > maxFileSize) {
					errors.push(i18n.certificateTooLarge);
					certInput.style.outline = '1px solid #ef4444';
				} else {
					certInput.style.outline = '';
				}
			} else {
				errors.push(i18n.certificateRequired);
			}

			// Show errors if any and block submission
			if (errors.length > 0) {
				e.preventDefault();

				// Remove any existing dynamic validation boxes
				const existingBox = document.getElementById('eg-care-frontend-errors');
				if (existingBox) {
					existingBox.remove();
				}

				// Render alert block directly above the Request/Submit button
				const alertDiv = document.createElement('div');
				alertDiv.id = 'eg-care-frontend-errors';
				alertDiv.className = 'eg-care-alert eg-care-alert-error';
				alertDiv.style.marginBottom = '20px';
				alertDiv.innerHTML = `
					<div class="eg-care-alert-icon">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 24px; height: 24px;">
							<circle cx="12" cy="12" r="10"></circle>
							<line x1="12" y1="8" x2="12" y2="12"></line>
							<line x1="12" y1="16" x2="12.01" y2="16"></line>
						</svg>
					</div>
					<div class="eg-care-alert-content">
						<strong>${i18n.validationHeading}</strong>
						<ul style="margin: 5px 0 0; padding-left: 15px;">
							${errors.map(err => `<li>${err}</li>`).join('')}
						</ul>
					</div>
				`;

				const submitWrap = document.querySelector('.eg-care-submit-wrap');
				if (submitWrap) {
					submitWrap.parentNode.insertBefore(alertDiv, submitWrap);
				} else {
					registerForm.parentNode.insertBefore(alertDiv, registerForm);
				}

				// Scroll smoothly to the validation box
				alertDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
			}
		});
	}
});
