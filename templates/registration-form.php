<?php
/**
 * Template for Doctor Onboarding Frontend Registration Form (Mockup Aligned & Generalized)
 *
 * @package EG Care
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="eg-care-registration-container">
	<?php if ( \EGCare\Shortcodes::is_success() ) : ?>
		<div class="eg-care-success-card">
			<div class="eg-care-success-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
					<polyline points="20 6 9 17 4 12"></polyline>
				</svg>
			</div>
			<h2><?php esc_html_e( 'Registration Submitted!', 'eg-care' ); ?></h2>
			<p class="eg-care-success-msg">
				<?php esc_html_e( 'Thank you for applying. Our administrative board is currently verifying your certificates and medical license.', 'eg-care' ); ?>
			</p>
			<p class="eg-care-success-sub">
				<?php esc_html_e( 'We will send a notification email to your registered address as soon as the verification is complete.', 'eg-care' ); ?>
			</p>
		</div>
	<?php else : ?>

		<div class="eg-care-register-header">
			<h2 class="eg-care-register-title"><?php esc_html_e( 'Become a Provider', 'eg-care' ); ?></h2>
			<p class="eg-care-register-desc"><?php esc_html_e( 'Join our telemedicine platform as a verified doctor or healthcare professional', 'eg-care' ); ?></p>
		</div>

		<?php
		$errors = \EGCare\Shortcodes::get_errors();
		if ( ! empty( $errors ) ) :
			?>
			<div class="eg-care-alert eg-care-alert-error">
				<div class="eg-care-alert-icon">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<circle cx="12" cy="12" r="10"></circle>
						<line x1="12" y1="8" x2="12" y2="12"></line>
						<line x1="12" y1="16" x2="12.01" y2="16"></line>
					</svg>
				</div>
				<div class="eg-care-alert-content">
					<strong><?php esc_html_e( 'Registration Errors:', 'eg-care' ); ?></strong>
					<ul>
						<?php foreach ( $errors as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<form method="post" enctype="multipart/form-data" class="eg-care-register-form" novalidate>
			<?php wp_nonce_field( 'eg_care_register', 'eg_care_register_nonce' ); ?>
			
			<!-- Hidden provider type input defaults to doctor -->
			<input type="hidden" name="reg_provider_type" value="doctor">

			<!-- Profile Picture section -->
			<div class="eg-care-card-section inline-profile">
				<div class="eg-care-field span-full">
					<label class="eg-care-input-label-primary"><?php esc_html_e( 'Profile Picture', 'eg-care' ); ?></label>
					<div class="eg-care-modern-upload-wrapper">
						<label for="reg_photo" class="eg-care-modern-upload-label">
							<?php esc_html_e( 'Choose File', 'eg-care' ); ?>
						</label>
						<input type="file" name="reg_photo" id="reg_photo" accept="image/*" class="eg-care-hidden-file-input">
						<span class="eg-care-file-name" id="reg_photo_name"><?php esc_html_e( 'No file chosen', 'eg-care' ); ?></span>
					</div>
				</div>
			</div>

			<!-- SECTION 1: Personal Information -->
			<div class="eg-care-card-section">
				<h3 class="eg-care-section-title"><?php esc_html_e( 'Personal Information', 'eg-care' ); ?></h3>
				<div class="eg-care-fields-grid text-fields">
					<div class="eg-care-field">
						<label for="reg_first_name"><?php esc_html_e( 'Name *', 'eg-care' ); ?></label>
						<input type="text" name="reg_first_name" id="reg_first_name" required placeholder="<?php esc_attr_e( 'Enter your name', 'eg-care' ); ?>" value="<?php echo isset( $_POST['reg_first_name'] ) ? esc_attr( $_POST['reg_first_name'] ) : ''; ?>">
					</div>
					<div class="eg-care-field">
						<label for="reg_email"><?php esc_html_e( 'Email *', 'eg-care' ); ?></label>
						<input type="email" name="reg_email" id="reg_email" required placeholder="<?php esc_attr_e( 'Enter your email', 'eg-care' ); ?>" value="<?php echo isset( $_POST['reg_email'] ) ? esc_attr( $_POST['reg_email'] ) : ''; ?>">
					</div>
					<div class="eg-care-field">
						<label for="reg_mobile"><?php esc_html_e( 'Mobile *', 'eg-care' ); ?></label>
						<input type="text" name="reg_mobile" id="reg_mobile" required placeholder="<?php esc_attr_e( 'Enter your number', 'eg-care' ); ?>" value="<?php echo isset( $_POST['reg_mobile'] ) ? esc_attr( $_POST['reg_mobile'] ) : ''; ?>">
					</div>
					<div class="eg-care-field">
						<label for="reg_nid"><?php esc_html_e( 'NID *', 'eg-care' ); ?></label>
						<input type="text" name="reg_nid" id="reg_nid" required placeholder="<?php esc_attr_e( 'Enter your NID', 'eg-care' ); ?>" value="<?php echo isset( $_POST['reg_nid'] ) ? esc_attr( $_POST['reg_nid'] ) : ''; ?>">
					</div>
					<div class="eg-care-field">
						<label for="reg_nationality"><?php esc_html_e( 'Nationality *', 'eg-care' ); ?></label>
						<select name="reg_nationality" id="reg_nationality" required>
							<option value=""><?php esc_html_e( 'Select your nationality', 'eg-care' ); ?></option>
							<?php $reg_nationality = isset( $_POST['reg_nationality'] ) ? sanitize_text_field( wp_unslash( $_POST['reg_nationality'] ) ) : 'Bangladeshi'; ?>
							<option value="Bangladeshi" <?php selected( $reg_nationality, 'Bangladeshi' ); ?>>Bangladeshi</option>
							<option value="Other" <?php selected( $reg_nationality, 'Other' ); ?>>Other</option>
						</select>
					</div>
					<div class="eg-care-field">
						<label for="reg_fee"><?php esc_html_e( 'Fee *', 'eg-care' ); ?></label>
						<input type="number" name="reg_fee" id="reg_fee" min="0" step="0.01" required placeholder="<?php esc_attr_e( 'Enter your Fee', 'eg-care' ); ?>" value="<?php echo isset( $_POST['reg_fee'] ) ? esc_attr( $_POST['reg_fee'] ) : ''; ?>">
					</div>
					<div class="eg-care-field span-full">
						<label for="reg_organization"><?php esc_html_e( 'Organization', 'eg-care' ); ?></label>
						<input type="text" name="reg_organization" id="reg_organization" placeholder="<?php esc_attr_e( 'Enter your Organization', 'eg-care' ); ?>" value="<?php echo isset( $_POST['reg_organization'] ) ? esc_attr( $_POST['reg_organization'] ) : ''; ?>">
					</div>
					<div class="eg-care-field span-full">
						<label for="reg_bio"><?php esc_html_e( 'Biography', 'eg-care' ); ?></label>
						<textarea name="reg_bio" id="reg_bio" rows="4" placeholder="<?php esc_attr_e( 'Enter your biography', 'eg-care' ); ?>"><?php echo isset( $_POST['reg_bio'] ) ? esc_textarea( $_POST['reg_bio'] ) : ''; ?></textarea>
					</div>
				</div>
			</div>

			<!-- SECTION 2: Account Access Credentials -->
			<div class="eg-care-card-section">
				<h3 class="eg-care-section-title"><?php esc_html_e( 'Account Portal Access', 'eg-care' ); ?></h3>
				<div class="eg-care-fields-grid text-fields">
					<div class="eg-care-field">
						<label for="reg_username"><?php esc_html_e( 'Username *', 'eg-care' ); ?></label>
						<input type="text" name="reg_username" id="reg_username" required placeholder="<?php esc_attr_e( 'Pick a portal username', 'eg-care' ); ?>" value="<?php echo isset( $_POST['reg_username'] ) ? esc_attr( $_POST['reg_username'] ) : ''; ?>">
					</div>
					<div class="eg-care-field">
						<label for="reg_password"><?php esc_html_e( 'Password *', 'eg-care' ); ?></label>
						<input type="password" name="reg_password" id="reg_password" required minlength="6" placeholder="<?php esc_attr_e( 'Enter password (min 6 characters)', 'eg-care' ); ?>">
					</div>
				</div>
			</div>

			<!-- SECTION 3: Professional Information -->
			<div class="eg-care-card-section">
				<h3 class="eg-care-section-title"><?php esc_html_e( 'Professional Information', 'eg-care' ); ?></h3>
				<div class="eg-care-fields-grid meta-fields">
					<div class="eg-care-field">
						<label for="reg_license"><?php esc_html_e( 'BMDC Code *', 'eg-care' ); ?></label>
						<input type="text" name="reg_license" id="reg_license" required placeholder="<?php esc_attr_e( 'Enter BMDC code', 'eg-care' ); ?>" value="<?php echo isset( $_POST['reg_license'] ) ? esc_attr( $_POST['reg_license'] ) : ''; ?>">
					</div>
					<div class="eg-care-field">
						<label for="reg_bmdc_expiry"><?php esc_html_e( 'BMDC Expiry Date *', 'eg-care' ); ?></label>
						<input type="date" name="reg_bmdc_expiry" id="reg_bmdc_expiry" required value="<?php echo isset( $_POST['reg_bmdc_expiry'] ) ? esc_attr( $_POST['reg_bmdc_expiry'] ) : ''; ?>">
					</div>
					<div class="eg-care-field">
						<label for="reg_degree"><?php esc_html_e( 'Degrees *', 'eg-care' ); ?></label>
						<input type="text" name="reg_degree" id="reg_degree" required placeholder="<?php esc_attr_e( 'Enter your degrees', 'eg-care' ); ?>" value="<?php echo isset( $_POST['reg_degree'] ) ? esc_attr( $_POST['reg_degree'] ) : ''; ?>">
					</div>
					<div class="eg-care-field">
						<label for="reg_specialty_id"><?php esc_html_e( 'Specialty *', 'eg-care' ); ?></label>
						<select name="reg_specialty_id" id="reg_specialty_id" required>
							<option value=""><?php esc_html_e( 'Select Specialty', 'eg-care' ); ?></option>
							<?php foreach ( $specialties as $spec ) : ?>
								<option value="<?php echo esc_attr( $spec->term_id ); ?>" <?php echo ( isset( $_POST['reg_specialty_id'] ) && intval( $_POST['reg_specialty_id'] ) === $spec->term_id ) ? 'selected' : ''; ?>>
									<?php echo esc_html( $spec->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="eg-care-field">
						<label for="reg_experience"><?php esc_html_e( 'Years of Experience *', 'eg-care' ); ?></label>
						<input type="number" name="reg_experience" id="reg_experience" min="0" required placeholder="<?php esc_attr_e( 'Enter your years of experience', 'eg-care' ); ?>" value="<?php echo isset( $_POST['reg_experience'] ) ? esc_attr( $_POST['reg_experience'] ) : ''; ?>">
					</div>
					<div class="eg-care-field">
						<label for="reg_follow_up_days"><?php esc_html_e( 'Follow up days *', 'eg-care' ); ?></label>
						<input type="number" name="reg_follow_up_days" id="reg_follow_up_days" min="0" required placeholder="<?php esc_attr_e( 'Enter your follow up days', 'eg-care' ); ?>" value="<?php echo isset( $_POST['reg_follow_up_days'] ) ? esc_attr( $_POST['reg_follow_up_days'] ) : ''; ?>">
					</div>
					<div class="eg-care-field">
						<label for="reg_follow_up_cost"><?php esc_html_e( 'Follow up cost *', 'eg-care' ); ?></label>
						<input type="number" name="reg_follow_up_cost" id="reg_follow_up_cost" min="0" step="0.01" required placeholder="<?php esc_attr_e( 'Enter your follow up cost', 'eg-care' ); ?>" value="<?php echo isset( $_POST['reg_follow_up_cost'] ) ? esc_attr( $_POST['reg_follow_up_cost'] ) : ''; ?>">
					</div>
					<!-- Add default instant call fee mapping same as fee or 0 -->
					<input type="hidden" name="reg_instant_fee" value="0">
					<!-- Designation defaults to degrees -->
					<input type="hidden" name="reg_designation" value="Consultant">
				</div>
			</div>

			<!-- SECTION 4: Bank Information -->
			<div class="eg-care-card-section">
				<h3 class="eg-care-section-title"><?php esc_html_e( 'Bank Information', 'eg-care' ); ?></h3>
				
				<div class="eg-care-field" style="margin-bottom: 20px;">
					<label class="eg-care-input-label-primary"><?php esc_html_e( 'Account Type', 'eg-care' ); ?></label>
					<div class="eg-care-toggle-buttons">
						<input type="hidden" name="reg_acc_type" id="reg_acc_type" value="bank">
						<button type="button" id="btn_acc_bank" class="eg-care-toggle-btn active"><?php esc_html_e( 'Bank Account', 'eg-care' ); ?></button>
						<button type="button" id="btn_acc_mobile" class="eg-care-toggle-btn"><?php esc_html_e( 'Mobile Banking', 'eg-care' ); ?></button>
					</div>
				</div>

				<!-- Bank Details Sub-Form -->
				<div class="eg-care-fields-grid payout-fields" id="sec_acc_bank">
					<div class="eg-care-field">
						<label for="reg_bank_name"><?php esc_html_e( 'Bank Name *', 'eg-care' ); ?></label>
						<select name="reg_bank_name" id="reg_bank_name" required>
							<option value=""><?php esc_html_e( 'Select your bank name', 'eg-care' ); ?></option>
							<option value="Sonali Bank">Sonali Bank</option>
							<option value="BRAC Bank">BRAC Bank</option>
							<option value="Dutch-Bangla Bank">Dutch-Bangla Bank</option>
							<option value="Islami Bank">Islami Bank</option>
							<option value="Eastern Bank">Eastern Bank</option>
						</select>
					</div>
					<div class="eg-care-field">
						<label for="reg_bank_branch"><?php esc_html_e( 'Branch Name *', 'eg-care' ); ?></label>
						<input type="text" name="reg_bank_branch" id="reg_bank_branch" required placeholder="<?php esc_attr_e( 'Enter your branch name', 'eg-care' ); ?>" value="<?php echo isset( $_POST['reg_bank_branch'] ) ? esc_attr( $_POST['reg_bank_branch'] ) : ''; ?>">
					</div>
					<div class="eg-care-field">
						<label for="reg_bank_no"><?php esc_html_e( 'Account Number *', 'eg-care' ); ?></label>
						<input type="text" name="reg_bank_no" id="reg_bank_no" required placeholder="<?php esc_attr_e( 'Enter your account number', 'eg-care' ); ?>" value="<?php echo isset( $_POST['reg_bank_no'] ) ? esc_attr( $_POST['reg_bank_no'] ) : ''; ?>">
					</div>
					<div class="eg-care-field">
						<label for="reg_bank_acc_name"><?php esc_html_e( 'Account Name *', 'eg-care' ); ?></label>
						<input type="text" name="reg_bank_acc_name" id="reg_bank_acc_name" required placeholder="<?php esc_attr_e( 'Enter account name', 'eg-care' ); ?>" value="<?php echo isset( $_POST['reg_bank_acc_name'] ) ? esc_attr( $_POST['reg_bank_acc_name'] ) : ''; ?>">
					</div>
					<div class="eg-care-field">
						<label for="reg_bank_routing"><?php esc_html_e( 'Routing Number *', 'eg-care' ); ?></label>
						<input type="text" name="reg_bank_routing" id="reg_bank_routing" required placeholder="<?php esc_attr_e( 'Enter routing number', 'eg-care' ); ?>" value="<?php echo isset( $_POST['reg_bank_routing'] ) ? esc_attr( $_POST['reg_bank_routing'] ) : ''; ?>">
					</div>
				</div>

				<!-- Mobile Banking Sub-Form (Hidden by default) -->
				<div class="eg-care-fields-grid payout-fields" id="sec_acc_mobile" style="display: none;">
					<div class="eg-care-field">
						<label for="reg_m_wallet"><?php esc_html_e( 'Mobile Payout Wallet *', 'eg-care' ); ?></label>
						<select name="reg_m_wallet" id="reg_m_wallet">
							<option value="bkash"><?php esc_html_e( 'bKash', 'eg-care' ); ?></option>
							<option value="nagad"><?php esc_html_e( 'Nagad', 'eg-care' ); ?></option>
							<option value="rocket"><?php esc_html_e( 'Rocket', 'eg-care' ); ?></option>
						</select>
					</div>
					<div class="eg-care-field">
						<label for="reg_m_no"><?php esc_html_e( 'Mobile Wallet Number *', 'eg-care' ); ?></label>
						<input type="text" name="reg_m_no" id="reg_m_no" placeholder="<?php esc_attr_e( 'Enter mobile wallet number', 'eg-care' ); ?>" value="<?php echo isset( $_POST['reg_m_no'] ) ? esc_attr( $_POST['reg_m_no'] ) : ''; ?>">
					</div>
				</div>
			</div>

			<!-- SECTION 5: Documents Attachment -->
			<div class="eg-care-card-section">
				<h3 class="eg-care-section-title"><?php esc_html_e( 'Documents', 'eg-care' ); ?></h3>
				<div class="eg-care-field">
					<div class="eg-care-modern-attachment-box">
						<div class="eg-care-attachment-uploader">
							<span class="eg-care-attachment-icon">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; color: #0d9488; vertical-align: middle;">
									<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
									<polyline points="17 8 12 3 7 8"></polyline>
									<line x1="12" y1="3" x2="12" y2="15"></line>
								</svg>
							</span>
							<span class="eg-care-attachment-label"><?php esc_html_e( 'Attachment', 'eg-care' ); ?></span>
							<label for="reg_certificate" class="eg-care-modern-upload-label" style="margin-left: 10px; margin-right: 10px;">
								<?php esc_html_e( 'Choose File', 'eg-care' ); ?>
							</label>
							<input type="file" name="reg_certificate" id="reg_certificate" accept=".pdf,image/*" required class="eg-care-hidden-file-input">
							<span class="eg-care-file-name" id="reg_certificate_name"><?php esc_html_e( 'No file chosen', 'eg-care' ); ?></span>
						</div>
						<p class="eg-care-attachment-help" style="margin-top: 10px;"><?php esc_html_e( 'Upload any certificate or document (maximum file size: 2 MB)', 'eg-care' ); ?></p>
					</div>
				</div>
			</div>

			<div class="eg-care-submit-wrap">
				<button type="submit" name="eg_care_register_submit" class="eg-care-btn-register cyan-btn">
					<?php esc_html_e( 'Request', 'eg-care' ); ?>
				</button>
			</div>
		</form>

		<script>
			document.addEventListener('DOMContentLoaded', function() {
				// Tabs toggle bank vs mobile
				const btnBank = document.getElementById('btn_acc_bank');
				const btnMobile = document.getElementById('btn_acc_mobile');
				const inputAccType = document.getElementById('reg_acc_type');
				const bankSection = document.getElementById('sec_acc_bank');
				const mobileSection = document.getElementById('sec_acc_mobile');

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
						nameSpan.textContent = '<?php echo esc_js( __( 'No file chosen', 'eg-care' ) ); ?>';
					}
				});

				document.getElementById('reg_certificate').addEventListener('change', function(e) {
					const nameSpan = document.getElementById('reg_certificate_name');
					if (e.target.files.length > 0) {
						nameSpan.textContent = e.target.files[0].name;
					} else {
						nameSpan.textContent = '<?php echo esc_js( __( 'No file chosen', 'eg-care' ) ); ?>';
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
								errors.push(field.label + ' ' + '<?php echo esc_js( __( 'is required.', 'eg-care' ) ); ?>');
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
									errors.push(field.label + ' ' + '<?php echo esc_js( __( 'is required for bank payout.', 'eg-care' ) ); ?>');
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
									errors.push(field.label + ' ' + '<?php echo esc_js( __( 'is required for mobile payout.', 'eg-care' ) ); ?>');
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
								errors.push('<?php echo esc_js( __( 'Profile photo file size exceeds 2MB limit.', 'eg-care' ) ); ?>');
								photoInput.style.outline = '1px solid #ef4444';
							} else {
								photoInput.style.outline = '';
							}
						}

						const certInput = document.getElementById('reg_certificate');
						if (certInput && certInput.files.length > 0) {
							if (certInput.files[0].size > maxFileSize) {
								errors.push('<?php echo esc_js( __( 'BMDC/Medical Certificate file size exceeds 2MB limit.', 'eg-care' ) ); ?>');
								certInput.style.outline = '1px solid #ef4444';
							} else {
								certInput.style.outline = '';
							}
						} else {
							errors.push('<?php echo esc_js( __( 'BMDC/Medical Certificate is required.', 'eg-care' ) ); ?>');
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
									<strong>${'<?php echo esc_js( __( 'Form Validation Errors:', 'eg-care' ) ); ?>'}</strong>
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
		</script>
	<?php endif; ?>
</div>
