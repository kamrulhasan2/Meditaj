<?php
/**
 * Template for Doctor Onboarding Frontend Registration Form (Mockup Aligned & Generalized)
 *
 * @package Meditaj
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>

<div class="meditaj-registration-container">
	<?php if ( \Meditaj\Shortcodes::is_success() ) : ?>
		<div class="meditaj-success-card">
			<div class="meditaj-success-icon">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
					<polyline points="20 6 9 17 4 12"></polyline>
				</svg>
			</div>
			<h2><?php esc_html_e( 'Registration Submitted!', 'meditaj' ); ?></h2>
			<p class="meditaj-success-msg">
				<?php esc_html_e( 'Thank you for applying. Our administrative board is currently verifying your certificates and medical license.', 'meditaj' ); ?>
			</p>
			<p class="meditaj-success-sub">
				<?php esc_html_e( 'We will send a notification email to your registered address as soon as the verification is complete.', 'meditaj' ); ?>
			</p>
		</div>
	<?php else : ?>

		<div class="meditaj-register-header">
			<h2 class="meditaj-register-title"><?php esc_html_e( 'Become a Provider', 'meditaj' ); ?></h2>
			<p class="meditaj-register-desc"><?php esc_html_e( 'Join our telemedicine platform as a verified doctor or healthcare professional', 'meditaj' ); ?></p>
		</div>

		<?php
		$errors = \Meditaj\Shortcodes::get_errors();
		if ( ! empty( $errors ) ) :
			?>
			<div class="meditaj-alert meditaj-alert-error">
				<div class="meditaj-alert-icon">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<circle cx="12" cy="12" r="10"></circle>
						<line x1="12" y1="8" x2="12" y2="12"></line>
						<line x1="12" y1="16" x2="12.01" y2="16"></line>
					</svg>
				</div>
				<div class="meditaj-alert-content">
					<strong><?php esc_html_e( 'Registration Errors:', 'meditaj' ); ?></strong>
					<ul>
						<?php foreach ( $errors as $error ) : ?>
							<li><?php echo esc_html( $error ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<form method="post" enctype="multipart/form-data" class="meditaj-register-form">
			<?php wp_nonce_field( 'meditaj_register', 'meditaj_register_nonce' ); ?>
			
			<!-- Hidden provider type input defaults to doctor -->
			<input type="hidden" name="reg_provider_type" value="doctor">

			<!-- Profile Picture section -->
			<div class="meditaj-card-section inline-profile">
				<div class="meditaj-field span-full">
					<label class="meditaj-input-label-primary"><?php esc_html_e( 'Profile Picture', 'meditaj' ); ?></label>
					<div class="meditaj-modern-upload-wrapper">
						<label for="reg_photo" class="meditaj-modern-upload-label">
							<?php esc_html_e( 'Choose File', 'meditaj' ); ?>
						</label>
						<input type="file" name="reg_photo" id="reg_photo" accept="image/*" class="meditaj-hidden-file-input">
						<span class="meditaj-file-name" id="reg_photo_name"><?php esc_html_e( 'No file chosen', 'meditaj' ); ?></span>
					</div>
				</div>
			</div>

			<!-- SECTION 1: Personal Information -->
			<div class="meditaj-card-section">
				<h3 class="meditaj-section-title"><?php esc_html_e( 'Personal Information', 'meditaj' ); ?></h3>
				<div class="meditaj-fields-grid text-fields">
					<div class="meditaj-field">
						<label for="reg_first_name"><?php esc_html_e( 'Name *', 'meditaj' ); ?></label>
						<input type="text" name="reg_first_name" id="reg_first_name" required placeholder="<?php esc_attr_e( 'Enter your name', 'meditaj' ); ?>" value="<?php echo isset( $_POST['reg_first_name'] ) ? esc_attr( $_POST['reg_first_name'] ) : ''; ?>">
					</div>
					<div class="meditaj-field">
						<label for="reg_email"><?php esc_html_e( 'Email *', 'meditaj' ); ?></label>
						<input type="email" name="reg_email" id="reg_email" required placeholder="<?php esc_attr_e( 'Enter your email', 'meditaj' ); ?>" value="<?php echo isset( $_POST['reg_email'] ) ? esc_attr( $_POST['reg_email'] ) : ''; ?>">
					</div>
					<div class="meditaj-field">
						<label for="reg_mobile"><?php esc_html_e( 'Mobile *', 'meditaj' ); ?></label>
						<input type="text" name="reg_mobile" id="reg_mobile" required placeholder="<?php esc_attr_e( 'Enter your number', 'meditaj' ); ?>" value="<?php echo isset( $_POST['reg_mobile'] ) ? esc_attr( $_POST['reg_mobile'] ) : ''; ?>">
					</div>
					<div class="meditaj-field">
						<label for="reg_nid"><?php esc_html_e( 'NID *', 'meditaj' ); ?></label>
						<input type="text" name="reg_nid" id="reg_nid" required placeholder="<?php esc_attr_e( 'Enter your NID', 'meditaj' ); ?>" value="<?php echo isset( $_POST['reg_nid'] ) ? esc_attr( $_POST['reg_nid'] ) : ''; ?>">
					</div>
					<div class="meditaj-field">
						<label for="reg_nationality"><?php esc_html_e( 'Nationality *', 'meditaj' ); ?></label>
						<select name="reg_nationality" id="reg_nationality" required>
							<option value=""><?php esc_html_e( 'Select your nationality', 'meditaj' ); ?></option>
							<option value="Bangladeshi" <?php echo ( isset( $_POST['reg_nationality'] ) && 'Bangladeshi' === $_POST['reg_nationality'] ) ? 'selected' : 'selected'; ?>>Bangladeshi</option>
							<option value="Other">Other</option>
						</select>
					</div>
					<div class="meditaj-field">
						<label for="reg_fee"><?php esc_html_e( 'Fee *', 'meditaj' ); ?></label>
						<input type="number" name="reg_fee" id="reg_fee" min="0" step="0.01" required placeholder="<?php esc_attr_e( 'Enter your Fee', 'meditaj' ); ?>" value="<?php echo isset( $_POST['reg_fee'] ) ? esc_attr( $_POST['reg_fee'] ) : ''; ?>">
					</div>
					<div class="meditaj-field span-full">
						<label for="reg_organization"><?php esc_html_e( 'Organization', 'meditaj' ); ?></label>
						<input type="text" name="reg_organization" id="reg_organization" placeholder="<?php esc_attr_e( 'Enter your Organization', 'meditaj' ); ?>" value="<?php echo isset( $_POST['reg_organization'] ) ? esc_attr( $_POST['reg_organization'] ) : ''; ?>">
					</div>
					<div class="meditaj-field span-full">
						<label for="reg_bio"><?php esc_html_e( 'Biography', 'meditaj' ); ?></label>
						<textarea name="reg_bio" id="reg_bio" rows="4" placeholder="<?php esc_attr_e( 'Enter your biography', 'meditaj' ); ?>"><?php echo isset( $_POST['reg_bio'] ) ? esc_textarea( $_POST['reg_bio'] ) : ''; ?></textarea>
					</div>
				</div>
			</div>

			<!-- SECTION 2: Account Access Credentials -->
			<div class="meditaj-card-section">
				<h3 class="meditaj-section-title"><?php esc_html_e( 'Account Portal Access', 'meditaj' ); ?></h3>
				<div class="meditaj-fields-grid text-fields">
					<div class="meditaj-field">
						<label for="reg_username"><?php esc_html_e( 'Username *', 'meditaj' ); ?></label>
						<input type="text" name="reg_username" id="reg_username" required placeholder="<?php esc_attr_e( 'Pick a portal username', 'meditaj' ); ?>" value="<?php echo isset( $_POST['reg_username'] ) ? esc_attr( $_POST['reg_username'] ) : ''; ?>">
					</div>
					<div class="meditaj-field">
						<label for="reg_password"><?php esc_html_e( 'Password *', 'meditaj' ); ?></label>
						<input type="password" name="reg_password" id="reg_password" required minlength="6" placeholder="<?php esc_attr_e( 'Enter password (min 6 characters)', 'meditaj' ); ?>">
					</div>
				</div>
			</div>

			<!-- SECTION 3: Professional Information -->
			<div class="meditaj-card-section">
				<h3 class="meditaj-section-title"><?php esc_html_e( 'Professional Information', 'meditaj' ); ?></h3>
				<div class="meditaj-fields-grid meta-fields">
					<div class="meditaj-field">
						<label for="reg_license"><?php esc_html_e( 'BMDC Code *', 'meditaj' ); ?></label>
						<input type="text" name="reg_license" id="reg_license" required placeholder="<?php esc_attr_e( 'Enter BMDC code', 'meditaj' ); ?>" value="<?php echo isset( $_POST['reg_license'] ) ? esc_attr( $_POST['reg_license'] ) : ''; ?>">
					</div>
					<div class="meditaj-field">
						<label for="reg_bmdc_expiry"><?php esc_html_e( 'BMDC Expiry Date *', 'meditaj' ); ?></label>
						<input type="date" name="reg_bmdc_expiry" id="reg_bmdc_expiry" required value="<?php echo isset( $_POST['reg_bmdc_expiry'] ) ? esc_attr( $_POST['reg_bmdc_expiry'] ) : ''; ?>">
					</div>
					<div class="meditaj-field">
						<label for="reg_degree"><?php esc_html_e( 'Degrees *', 'meditaj' ); ?></label>
						<input type="text" name="reg_degree" id="reg_degree" required placeholder="<?php esc_attr_e( 'Enter your degrees', 'meditaj' ); ?>" value="<?php echo isset( $_POST['reg_degree'] ) ? esc_attr( $_POST['reg_degree'] ) : ''; ?>">
					</div>
					<div class="meditaj-field">
						<label for="reg_specialty_id"><?php esc_html_e( 'Specialty *', 'meditaj' ); ?></label>
						<select name="reg_specialty_id" id="reg_specialty_id" required>
							<option value=""><?php esc_html_e( 'Select Specialty', 'meditaj' ); ?></option>
							<?php foreach ( $specialties as $spec ) : ?>
								<option value="<?php echo esc_attr( $spec->term_id ); ?>" <?php echo ( isset( $_POST['reg_specialty_id'] ) && intval( $_POST['reg_specialty_id'] ) === $spec->term_id ) ? 'selected' : ''; ?>>
									<?php echo esc_html( $spec->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="meditaj-field">
						<label for="reg_experience"><?php esc_html_e( 'Years of Experience *', 'meditaj' ); ?></label>
						<input type="number" name="reg_experience" id="reg_experience" min="0" required placeholder="<?php esc_attr_e( 'Enter your years of experience', 'meditaj' ); ?>" value="<?php echo isset( $_POST['reg_experience'] ) ? esc_attr( $_POST['reg_experience'] ) : ''; ?>">
					</div>
					<div class="meditaj-field">
						<label for="reg_follow_up_days"><?php esc_html_e( 'Follow up days *', 'meditaj' ); ?></label>
						<input type="number" name="reg_follow_up_days" id="reg_follow_up_days" min="0" required placeholder="<?php esc_attr_e( 'Enter your follow up days', 'meditaj' ); ?>" value="<?php echo isset( $_POST['reg_follow_up_days'] ) ? esc_attr( $_POST['reg_follow_up_days'] ) : ''; ?>">
					</div>
					<div class="meditaj-field">
						<label for="reg_follow_up_cost"><?php esc_html_e( 'Follow up cost *', 'meditaj' ); ?></label>
						<input type="number" name="reg_follow_up_cost" id="reg_follow_up_cost" min="0" step="0.01" required placeholder="<?php esc_attr_e( 'Enter your follow up cost', 'meditaj' ); ?>" value="<?php echo isset( $_POST['reg_follow_up_cost'] ) ? esc_attr( $_POST['reg_follow_up_cost'] ) : ''; ?>">
					</div>
					<!-- Add default instant call fee mapping same as fee or 0 -->
					<input type="hidden" name="reg_instant_fee" value="0">
					<!-- Designation defaults to degrees -->
					<input type="hidden" name="reg_designation" value="Consultant">
				</div>
			</div>

			<!-- SECTION 4: Bank Information -->
			<div class="meditaj-card-section">
				<h3 class="meditaj-section-title"><?php esc_html_e( 'Bank Information', 'meditaj' ); ?></h3>
				
				<div class="meditaj-field" style="margin-bottom: 20px;">
					<label class="meditaj-input-label-primary"><?php esc_html_e( 'Account Type', 'meditaj' ); ?></label>
					<div class="meditaj-toggle-buttons">
						<input type="hidden" name="reg_acc_type" id="reg_acc_type" value="bank">
						<button type="button" id="btn_acc_bank" class="meditaj-toggle-btn active"><?php esc_html_e( 'Bank Account', 'meditaj' ); ?></button>
						<button type="button" id="btn_acc_mobile" class="meditaj-toggle-btn"><?php esc_html_e( 'Mobile Banking', 'meditaj' ); ?></button>
					</div>
				</div>

				<!-- Bank Details Sub-Form -->
				<div class="meditaj-fields-grid payout-fields" id="sec_acc_bank">
					<div class="meditaj-field">
						<label for="reg_bank_name"><?php esc_html_e( 'Bank Name *', 'meditaj' ); ?></label>
						<select name="reg_bank_name" id="reg_bank_name" required>
							<option value=""><?php esc_html_e( 'Select your bank name', 'meditaj' ); ?></option>
							<option value="Sonali Bank">Sonali Bank</option>
							<option value="BRAC Bank">BRAC Bank</option>
							<option value="Dutch-Bangla Bank">Dutch-Bangla Bank</option>
							<option value="Islami Bank">Islami Bank</option>
							<option value="Eastern Bank">Eastern Bank</option>
						</select>
					</div>
					<div class="meditaj-field">
						<label for="reg_bank_branch"><?php esc_html_e( 'Branch Name *', 'meditaj' ); ?></label>
						<input type="text" name="reg_bank_branch" id="reg_bank_branch" required placeholder="<?php esc_attr_e( 'Enter your branch name', 'meditaj' ); ?>" value="<?php echo isset( $_POST['reg_bank_branch'] ) ? esc_attr( $_POST['reg_bank_branch'] ) : ''; ?>">
					</div>
					<div class="meditaj-field">
						<label for="reg_bank_no"><?php esc_html_e( 'Account Number *', 'meditaj' ); ?></label>
						<input type="text" name="reg_bank_no" id="reg_bank_no" required placeholder="<?php esc_attr_e( 'Enter your account number', 'meditaj' ); ?>" value="<?php echo isset( $_POST['reg_bank_no'] ) ? esc_attr( $_POST['reg_bank_no'] ) : ''; ?>">
					</div>
					<div class="meditaj-field">
						<label for="reg_bank_acc_name"><?php esc_html_e( 'Account Name *', 'meditaj' ); ?></label>
						<input type="text" name="reg_bank_acc_name" id="reg_bank_acc_name" required placeholder="<?php esc_attr_e( 'Enter account name', 'meditaj' ); ?>" value="<?php echo isset( $_POST['reg_bank_acc_name'] ) ? esc_attr( $_POST['reg_bank_acc_name'] ) : ''; ?>">
					</div>
					<div class="meditaj-field">
						<label for="reg_bank_routing"><?php esc_html_e( 'Routing Number *', 'meditaj' ); ?></label>
						<input type="text" name="reg_bank_routing" id="reg_bank_routing" required placeholder="<?php esc_attr_e( 'Enter routing number', 'meditaj' ); ?>" value="<?php echo isset( $_POST['reg_bank_routing'] ) ? esc_attr( $_POST['reg_bank_routing'] ) : ''; ?>">
					</div>
				</div>

				<!-- Mobile Banking Sub-Form (Hidden by default) -->
				<div class="meditaj-fields-grid payout-fields" id="sec_acc_mobile" style="display: none;">
					<div class="meditaj-field">
						<label for="reg_m_wallet"><?php esc_html_e( 'Mobile Payout Wallet *', 'meditaj' ); ?></label>
						<select name="reg_m_wallet" id="reg_m_wallet">
							<option value="bkash"><?php esc_html_e( 'bKash', 'meditaj' ); ?></option>
							<option value="nagad"><?php esc_html_e( 'Nagad', 'meditaj' ); ?></option>
							<option value="rocket"><?php esc_html_e( 'Rocket', 'meditaj' ); ?></option>
						</select>
					</div>
					<div class="meditaj-field">
						<label for="reg_m_no"><?php esc_html_e( 'Mobile Wallet Number *', 'meditaj' ); ?></label>
						<input type="text" name="reg_m_no" id="reg_m_no" placeholder="<?php esc_attr_e( 'Enter mobile wallet number', 'meditaj' ); ?>" value="<?php echo isset( $_POST['reg_m_no'] ) ? esc_attr( $_POST['reg_m_no'] ) : ''; ?>">
					</div>
				</div>
			</div>

			<!-- SECTION 5: Documents Attachment -->
			<div class="meditaj-card-section">
				<h3 class="meditaj-section-title"><?php esc_html_e( 'Documents', 'meditaj' ); ?></h3>
				<div class="meditaj-field">
					<div class="meditaj-modern-attachment-box">
						<div class="meditaj-attachment-uploader">
							<span class="meditaj-attachment-icon">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 20px; height: 20px; color: #0d9488; vertical-align: middle;">
									<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
									<polyline points="17 8 12 3 7 8"></polyline>
									<line x1="12" y1="3" x2="12" y2="15"></line>
								</svg>
							</span>
							<span class="meditaj-attachment-label"><?php esc_html_e( 'Attachment', 'meditaj' ); ?></span>
							<label for="reg_certificate" class="meditaj-modern-upload-label" style="margin-left: 10px; margin-right: 10px;">
								<?php esc_html_e( 'Choose File', 'meditaj' ); ?>
							</label>
							<input type="file" name="reg_certificate" id="reg_certificate" accept=".pdf,image/*" required class="meditaj-hidden-file-input">
							<span class="meditaj-file-name" id="reg_certificate_name"><?php esc_html_e( 'No file chosen', 'meditaj' ); ?></span>
						</div>
						<p class="meditaj-attachment-help" style="margin-top: 10px;"><?php esc_html_e( 'Upload any certificate or document (maximum file size: 2 MB)', 'meditaj' ); ?></p>
					</div>
				</div>
			</div>

			<div class="meditaj-submit-wrap">
				<button type="submit" name="meditaj_register_submit" class="meditaj-btn-register cyan-btn">
					<?php esc_html_e( 'Request', 'meditaj' ); ?>
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
						nameSpan.textContent = '<?php echo esc_js( __( 'No file chosen', 'meditaj' ) ); ?>';
					}
				});

				document.getElementById('reg_certificate').addEventListener('change', function(e) {
					const nameSpan = document.getElementById('reg_certificate_name');
					if (e.target.files.length > 0) {
						nameSpan.textContent = e.target.files[0].name;
					} else {
						nameSpan.textContent = '<?php echo esc_js( __( 'No file chosen', 'meditaj' ) ); ?>';
					}
				});
			});
		</script>
	<?php endif; ?>
</div>
