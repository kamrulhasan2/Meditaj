<?php
namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Load WP_List_Table if it's not already loaded.
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Handles Admin UI list rendering, creation form, actions, and meta boxes.
 */
class AdminDoctors {
	/**
	 * Initialize actions.
	 */
	public static function init() {
		// Actions handler.
		add_action( 'admin_init', array( __CLASS__, 'process_admin_actions' ) );
		// Meta boxes.
		add_action( 'add_meta_boxes_doctors', array( __CLASS__, 'add_doctor_meta_boxes' ) );
		add_action( 'save_post_doctors', array( __CLASS__, 'save_doctor_meta_box_data' ) );
	}

	/**
	 * Render Doctors List Table page in wp-admin.
	 */
	public static function render_doctors_page() {
		$list_table = new EG_Care_Doctors_List_Table();
		$list_table->prepare_items();
		?>
		<div class="wrap eg-care-admin-wrap">
			<div class="eg-care-admin-header">
				<h1 class="eg-care-admin-title"><?php esc_html_e( 'Doctor List', 'eg-care' ); ?></h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=eg-care-add-doctor' ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Add New Doctor', 'eg-care' ); ?>
				</a>
			</div>

			<form method="post">
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render Add Doctor Form page in wp-admin.
	 */
	public static function render_add_doctor_page() {
		wp_enqueue_script( 'eg-care-admin-add-doctor-js', EG_CARE_URL . 'assets/js/admin-add-doctor.js', array(), eg_care_asset_version( 'assets/js/admin-add-doctor.js' ), true );

		// Handle submission.
		$message = '';
		$error   = '';

		if ( isset( $_POST['eg_care_add_doctor_submit'] ) ) {
			// Verify Nonce.
			if ( ! isset( $_POST['eg_care_add_doctor_nonce'] ) || ! wp_verify_nonce( $_POST['eg_care_add_doctor_nonce'], 'eg_care_add_doctor' ) ) {
				$error = __( 'Security verification failed.', 'eg-care' );
			} elseif ( ! current_user_can( 'manage_options' ) ) {
				$error = __( 'You do not have permission to perform this action.', 'eg-care' );
			} else {
				$result = self::handle_add_doctor_submission();
				if ( is_wp_error( $result ) ) {
					$error = $result->get_error_message();
				} else {
					$message = __( 'Doctor successfully added.', 'eg-care' );
				}
			}
		}

		// Retrieve Specialties.
		$specialties = get_terms(
			array(
				'taxonomy'   => 'specialty',
				'hide_empty' => false,
			)
		);
		?>
		<div class="wrap eg-care-admin-wrap">
			<div class="eg-care-admin-header">
				<h1 class="eg-care-admin-title"><?php esc_html_e( 'Add New Doctor', 'eg-care' ); ?></h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=eg-care-doctors' ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Back to List', 'eg-care' ); ?>
				</a>
			</div>

			<?php if ( $error ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>
			<?php if ( $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif; ?>

			<div class="eg-care-form-card">
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'eg_care_add_doctor', 'eg_care_add_doctor_nonce' ); ?>
 
					<!-- SECTION 1: WordPress User Credentials -->
					<div class="eg-care-form-section">
						<h3 class="eg-care-form-section-title"><?php esc_html_e( '1. User Account Credentials', 'eg-care' ); ?></h3>
						<div class="eg-care-form-grid">
							<div class="eg-care-form-field">
								<label for="doctor_username"><?php esc_html_e( 'Username *', 'eg-care' ); ?></label>
								<input type="text" name="doctor_username" id="doctor_username" required value="<?php echo isset( $_POST['doctor_username'] ) ? esc_attr( $_POST['doctor_username'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="doctor_email"><?php esc_html_e( 'Email Address *', 'eg-care' ); ?></label>
								<input type="email" name="doctor_email" id="doctor_email" required value="<?php echo isset( $_POST['doctor_email'] ) ? esc_attr( $_POST['doctor_email'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="doctor_password"><?php esc_html_e( 'Password *', 'eg-care' ); ?></label>
								<input type="password" name="doctor_password" id="doctor_password" required minlength="6">
							</div>
						</div>
					</div>
 
					<!-- SECTION 2: Doctor Profile details -->
					<div class="eg-care-form-section">
						<h3 class="eg-care-form-section-title"><?php esc_html_e( '2. Profile Information', 'eg-care' ); ?></h3>
						<div class="eg-care-form-grid">
							<div class="eg-care-form-field">
								<label for="doctor_name"><?php esc_html_e( 'Full Name *', 'eg-care' ); ?></label>
								<input type="text" name="doctor_name" id="doctor_name" required value="<?php echo isset( $_POST['doctor_name'] ) ? esc_attr( $_POST['doctor_name'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="specialty_id"><?php esc_html_e( 'Specialty *', 'eg-care' ); ?></label>
								<select name="specialty_id" id="specialty_id" required>
									<option value=""><?php esc_html_e( '-- Select Specialty --', 'eg-care' ); ?></option>
									<?php foreach ( $specialties as $spec ) : ?>
										<option value="<?php echo esc_attr( $spec->term_id ); ?>"><?php echo esc_html( $spec->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="eg-care-form-field">
								<label for="doctor_photo"><?php esc_html_e( 'Profile Photo', 'eg-care' ); ?></label>
								<input type="file" name="doctor_photo" id="doctor_photo" accept="image/*">
							</div>
							<div class="eg-care-form-field">
								<label for="provider_type"><?php esc_html_e( 'Provider Type *', 'eg-care' ); ?></label>
								<select name="provider_type" id="provider_type" required>
									<option value="doctor"><?php esc_html_e( 'Doctor', 'eg-care' ); ?></option>
									<option value="medical_professional"><?php esc_html_e( 'Medical Professional', 'eg-care' ); ?></option>
								</select>
							</div>
							<div class="eg-care-form-field">
								<label for="mobile"><?php esc_html_e( 'Mobile *', 'eg-care' ); ?></label>
								<input type="text" name="mobile" id="mobile" required value="<?php echo isset( $_POST['mobile'] ) ? esc_attr( $_POST['mobile'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="nid"><?php esc_html_e( 'NID *', 'eg-care' ); ?></label>
								<input type="text" name="nid" id="nid" required value="<?php echo isset( $_POST['nid'] ) ? esc_attr( $_POST['nid'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="nationality"><?php esc_html_e( 'Nationality *', 'eg-care' ); ?></label>
								<select name="nationality" id="nationality" required>
									<option value="Bangladeshi"><?php esc_html_e( 'Bangladeshi', 'eg-care' ); ?></option>
									<option value="Other"><?php esc_html_e( 'Other', 'eg-care' ); ?></option>
								</select>
							</div>
							<div class="eg-care-form-field">
								<label for="organization"><?php esc_html_e( 'Organization', 'eg-care' ); ?></label>
								<input type="text" name="organization" id="organization" value="<?php echo isset( $_POST['organization'] ) ? esc_attr( $_POST['organization'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field span-2">
								<label for="doctor_bio"><?php esc_html_e( 'Biography / Experience Overview', 'eg-care' ); ?></label>
								<textarea name="doctor_bio" id="doctor_bio" rows="4"><?php echo isset( $_POST['doctor_bio'] ) ? esc_textarea( $_POST['doctor_bio'] ) : ''; ?></textarea>
							</div>
						</div>
					</div>
 
					<!-- SECTION 3: Professional Metadata -->
					<div class="eg-care-form-section">
						<h3 class="eg-care-form-section-title"><?php esc_html_e( '3. Professional Metadata & Pricing', 'eg-care' ); ?></h3>
						<div class="eg-care-form-grid">
							<div class="eg-care-form-field">
								<label for="bmdc_license_no"><?php esc_html_e( 'BMDC Registration No *', 'eg-care' ); ?></label>
								<input type="text" name="bmdc_license_no" id="bmdc_license_no" required value="<?php echo isset( $_POST['bmdc_license_no'] ) ? esc_attr( $_POST['bmdc_license_no'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="bmdc_expiry_date"><?php esc_html_e( 'BMDC Expiry Date *', 'eg-care' ); ?></label>
								<input type="date" name="bmdc_expiry_date" id="bmdc_expiry_date" required value="<?php echo isset( $_POST['bmdc_expiry_date'] ) ? esc_attr( $_POST['bmdc_expiry_date'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="degree"><?php esc_html_e( 'Degrees / Qualifications (comma separated) *', 'eg-care' ); ?></label>
								<input type="text" name="degree" id="degree" required placeholder="e.g. MBBS, FCPS, MD" value="<?php echo isset( $_POST['degree'] ) ? esc_attr( $_POST['degree'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="designation"><?php esc_html_e( 'Designation *', 'eg-care' ); ?></label>
								<input type="text" name="designation" id="designation" required placeholder="e.g. Associate Professor" value="<?php echo isset( $_POST['designation'] ) ? esc_attr( $_POST['designation'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="experience_years"><?php esc_html_e( 'Years of Experience *', 'eg-care' ); ?></label>
								<input type="number" name="experience_years" id="experience_years" min="0" required value="<?php echo isset( $_POST['experience_years'] ) ? esc_attr( $_POST['experience_years'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="consultation_fee"><?php esc_html_e( 'Scheduled Consultation Fee (BDT) *', 'eg-care' ); ?></label>
								<input type="number" name="consultation_fee" id="consultation_fee" min="0" step="0.01" required value="<?php echo isset( $_POST['consultation_fee'] ) ? esc_attr( $_POST['consultation_fee'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="instant_call_fee"><?php esc_html_e( 'Instant Call Fee (BDT) *', 'eg-care' ); ?></label>
								<input type="number" name="instant_call_fee" id="instant_call_fee" min="0" step="0.01" required value="<?php echo isset( $_POST['instant_call_fee'] ) ? esc_attr( $_POST['instant_call_fee'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="follow_up_days"><?php esc_html_e( 'Follow up days *', 'eg-care' ); ?></label>
								<input type="number" name="follow_up_days" id="follow_up_days" min="0" required value="<?php echo isset( $_POST['follow_up_days'] ) ? esc_attr( $_POST['follow_up_days'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="follow_up_cost"><?php esc_html_e( 'Follow up cost *', 'eg-care' ); ?></label>
								<input type="number" name="follow_up_cost" id="follow_up_cost" min="0" step="0.01" required value="<?php echo isset( $_POST['follow_up_cost'] ) ? esc_attr( $_POST['follow_up_cost'] ) : ''; ?>">
							</div>
						</div>
					</div>
 
					<!-- SECTION 4: Payment Payout accounts -->
					<div class="eg-care-form-section">
						<h3 class="eg-care-form-section-title"><?php esc_html_e( '4. Bank & Mobile Payout Settings', 'eg-care' ); ?></h3>
						
						<div class="eg-care-form-grid" style="margin-bottom: 20px;">
							<div class="eg-care-form-field">
								<label for="payout_type"><?php esc_html_e( 'Payout Method', 'eg-care' ); ?></label>
								<select name="payout_type" id="payout_type">
									<option value="bank" <?php selected( isset( $_POST['payout_type'] ) ? $_POST['payout_type'] : 'bank', 'bank' ); ?>><?php esc_html_e( 'Bank Account', 'eg-care' ); ?></option>
									<option value="mobile" <?php selected( isset( $_POST['payout_type'] ) ? $_POST['payout_type'] : 'bank', 'mobile' ); ?>><?php esc_html_e( 'Mobile Banking', 'eg-care' ); ?></option>
								</select>
							</div>
						</div>

						<!-- Bank Payout Fields -->
						<div class="eg-care-form-grid" id="sec_bank_fields">
							<div class="eg-care-form-field">
								<label for="bank_name"><?php esc_html_e( 'Bank Name', 'eg-care' ); ?></label>
								<input type="text" name="bank_name" id="bank_name" value="<?php echo isset( $_POST['bank_name'] ) ? esc_attr( $_POST['bank_name'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="bank_branch"><?php esc_html_e( 'Branch Name', 'eg-care' ); ?></label>
								<input type="text" name="bank_branch" id="bank_branch" value="<?php echo isset( $_POST['bank_branch'] ) ? esc_attr( $_POST['bank_branch'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="bank_account_no"><?php esc_html_e( 'Bank Account Number', 'eg-care' ); ?></label>
								<input type="text" name="bank_account_no" id="bank_account_no" value="<?php echo isset( $_POST['bank_account_no'] ) ? esc_attr( $_POST['bank_account_no'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="bank_account_name"><?php esc_html_e( 'Bank Account Name', 'eg-care' ); ?></label>
								<input type="text" name="bank_account_name" id="bank_account_name" value="<?php echo isset( $_POST['bank_account_name'] ) ? esc_attr( $_POST['bank_account_name'] ) : ''; ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="bank_routing_number"><?php esc_html_e( 'Routing Number', 'eg-care' ); ?></label>
								<input type="text" name="bank_routing_number" id="bank_routing_number" value="<?php echo isset( $_POST['bank_routing_number'] ) ? esc_attr( $_POST['bank_routing_number'] ) : ''; ?>">
							</div>
						</div>

						<!-- Mobile Payout Fields -->
						<div class="eg-care-form-grid" id="sec_mobile_fields" style="display: none;">
							<div class="eg-care-form-field">
								<label for="mobile_banking_type"><?php esc_html_e( 'Mobile Banking Wallet', 'eg-care' ); ?></label>
								<select name="mobile_banking_type" id="mobile_banking_type">
									<option value="bkash"><?php esc_html_e( 'bKash', 'eg-care' ); ?></option>
									<option value="nagad"><?php esc_html_e( 'Nagad', 'eg-care' ); ?></option>
									<option value="rocket"><?php esc_html_e( 'Rocket', 'eg-care' ); ?></option>
								</select>
							</div>
							<div class="eg-care-form-field">
								<label for="mobile_banking_no"><?php esc_html_e( 'Mobile Wallet Number', 'eg-care' ); ?></label>
								<input type="text" name="mobile_banking_no" id="mobile_banking_no" value="<?php echo isset( $_POST['mobile_banking_no'] ) ? esc_attr( $_POST['mobile_banking_no'] ) : ''; ?>">
							</div>
						</div>
					</div>
 
					<p class="submit">
						<input type="submit" name="eg_care_add_doctor_submit" id="submit" class="button button-primary eg-care-btn-primary" value="<?php esc_attr_e( 'Create Doctor Profile', 'eg-care' ); ?>">
					</p>
				</form>
			</div>
		</div>
		<?php
	}
 
	/**
	 * Process form submission to register a doctor, create post, and meta entries atomically.
	 *
	 * @return int|\WP_Error ID of created meta row or WP_Error.
	 */
	private static function handle_add_doctor_submission() {
		global $wpdb;
 
		// 1. Gather & Sanitize inputs.
		$username      = sanitize_user( $_POST['doctor_username'] );
		$email         = sanitize_email( $_POST['doctor_email'] );
		$password      = $_POST['doctor_password'];
		$doctor_name   = sanitize_text_field( $_POST['doctor_name'] );
		$bio           = wp_kses_post( $_POST['doctor_bio'] );
		$provider_type = sanitize_text_field( $_POST['provider_type'] );
		$specialty_id  = intval( $_POST['specialty_id'] );
 
		$license           = sanitize_text_field( $_POST['bmdc_license_no'] );
		$bmdc_expiry_date  = sanitize_text_field( $_POST['bmdc_expiry_date'] );
		$degree            = sanitize_text_field( $_POST['degree'] );
		$designation       = sanitize_text_field( $_POST['designation'] );
		$experience        = intval( $_POST['experience_years'] );
		$consultation_fee  = floatval( $_POST['consultation_fee'] );
		$instant_call_fee  = floatval( $_POST['instant_call_fee'] );
		
		$mobile            = sanitize_text_field( $_POST['mobile'] );
		$nid               = sanitize_text_field( $_POST['nid'] );
		$nationality       = sanitize_text_field( $_POST['nationality'] );
		$organization      = sanitize_text_field( $_POST['organization'] );
		$follow_up_days    = intval( $_POST['follow_up_days'] );
		$follow_up_cost    = floatval( $_POST['follow_up_cost'] );

		$payout_type       = sanitize_text_field( $_POST['payout_type'] );
		$bank_name         = sanitize_text_field( $_POST['bank_name'] );
		$bank_branch       = sanitize_text_field( $_POST['bank_branch'] );
		$bank_acc_name     = sanitize_text_field( $_POST['bank_account_name'] );
		$bank_acc_no       = sanitize_text_field( $_POST['bank_account_no'] );
		$bank_routing      = sanitize_text_field( $_POST['bank_routing_number'] );
		$mobile_wallet     = sanitize_text_field( $_POST['mobile_banking_type'] );
		$mobile_wallet_no  = sanitize_text_field( $_POST['mobile_banking_no'] );
 
		// 2. Form validation.
		if ( empty( $username ) || empty( $email ) || empty( $password ) || empty( $doctor_name ) || empty( $license ) || empty( $bmdc_expiry_date ) || empty( $mobile ) || empty( $nid ) ) {
			return new \WP_Error( 'missing_fields', __( 'Please fill in all required fields.', 'eg-care' ) );
		}
 
		if ( username_exists( $username ) ) {
			return new \WP_Error( 'username_taken', __( 'Username is already taken.', 'eg-care' ) );
		}
 
		if ( email_exists( $email ) ) {
			return new \WP_Error( 'email_taken', __( 'Email address is already in use.', 'eg-care' ) );
		}
 
		// 3. User Creation.
		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
 
		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $doctor_name,
				'role'         => 'eg_care_doctor',
			)
		);
 
		// 4. Post CPT Creation.
		$post_id = wp_insert_post(
			array(
				'post_title'   => $doctor_name,
				'post_content' => $bio,
				'post_status'  => 'publish',
				'post_type'    => 'doctors',
				'post_author'  => $user_id,
			)
		);
 
		if ( is_wp_error( $post_id ) ) {
			// Rollback user.
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
			return $post_id;
		}
 
		// Set profile thumbnail if uploaded.
		if ( ! empty( $_FILES['doctor_photo']['name'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
 
			$attachment_id = media_handle_upload( 'doctor_photo', $post_id );
			if ( ! is_wp_error( $attachment_id ) ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}
 
		// Associate Taxonomy term.
		wp_set_object_terms( $post_id, $specialty_id, 'specialty' );
 
		// 5. Custom Table insertion.
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );
		$inserted   = $wpdb->insert(
			$table_meta,
			array(
				'post_id'             => $post_id,
				'user_id'             => $user_id,
				'provider_type'       => $provider_type,
				'bmdc_license_no'     => $license,
				'bmdc_expiry_date'    => $bmdc_expiry_date,
				'degree'              => $degree,
				'designation'         => $designation,
				'consultation_fee'    => $consultation_fee,
				'instant_call_fee'    => $instant_call_fee,
				'experience_years'    => $experience,
				'is_online'           => 0,
				'verification_status' => 'pending', // Starts pending.
				'mobile'              => $mobile,
				'nid'                 => $nid,
				'nationality'         => $nationality,
				'organization'        => $organization,
				'follow_up_days'      => $follow_up_days,
				'follow_up_cost'      => $follow_up_cost,
				'bank_account_name'   => 'bank' === $payout_type ? $bank_acc_name : null,
				'bank_account_no'     => 'bank' === $payout_type ? $bank_acc_no : null,
				'bank_branch_name'    => 'bank' === $payout_type ? $bank_name : null,
				'bank_routing_number' => 'bank' === $payout_type ? $bank_routing : null,
				'mobile_banking_type' => 'mobile' === $payout_type ? $mobile_wallet : null,
				'mobile_banking_no'   => 'mobile' === $payout_type ? $mobile_wallet_no : null,
				'certificate_files'   => wp_json_encode( array() ),
				'created_at'          => current_time( 'mysql' ),
			),
			array(
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%f',
				'%f',
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%f',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( false === $inserted ) {
			// Rollback user & post.
			wp_delete_post( $post_id, true );
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
			return new \WP_Error( 'db_error', __( 'Failed to save doctor metadata in database.', 'eg-care' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Listen and process admin actions (GET and POST).
	 */
	public static function process_admin_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle POST rejection.
		if ( isset( $_POST['eg_care_reject_submit'] ) ) {
			$id = isset( $_POST['doctor_id'] ) ? intval( $_POST['doctor_id'] ) : 0;
			if ( ! $id ) {
				return;
			}
			if ( ! isset( $_POST['eg_care_reject_nonce'] ) || ! wp_verify_nonce( $_POST['eg_care_reject_nonce'], 'eg_care_reject_doctor_' . $id ) ) {
				wp_die( esc_html__( 'Security check failed.', 'eg-care' ) );
			}

			global $wpdb;
			$table_meta = \EGCare\DB::get_table( 'doctors_meta' );
			$doctor     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_meta WHERE id = %d", $id ) );
			if ( ! $doctor ) {
				return;
			}

			$reason = isset( $_POST['rejection_reason'] ) ? sanitize_text_field( $_POST['rejection_reason'] ) : '';

			// Update status in custom table.
			$wpdb->update( $table_meta, array( 'verification_status' => 'rejected' ), array( 'id' => $id ) );

			// Draft/Trash CPT post if needed (let's keep it draft).
			wp_update_post(
				array(
					'ID'          => $doctor->post_id,
					'post_status' => 'draft',
				)
			);

			// Send rejection email.
			$user_data = get_userdata( $doctor->user_id );
			if ( $user_data ) {
				\EGCare\Notifications::send_doctor_rejection_email( $user_data->user_email, $user_data->display_name, $reason );
			}

			wp_safe_redirect( add_query_arg( 'message', 'rejected', wp_get_referer() ) );
			exit;
		}

		// Handle GET actions.
		if ( ! isset( $_GET['page'] ) || ! in_array( $_GET['page'], array( 'eg-care-doctors', 'eg-care-pending' ), true ) ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
		$id     = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( ! $action || ! $id ) {
			return;
		}

		global $wpdb;
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );

		// Retrieve records.
		$doctor = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_meta WHERE id = %d", $id ) );
		if ( ! $doctor ) {
			return;
		}

		switch ( $action ) {
			case 'approve':
				check_admin_referer( 'eg_care_approve_doctor_' . $id );

				// Update verification status.
				$wpdb->update( $table_meta, array( 'verification_status' => 'approved' ), array( 'id' => $id ) );

				// Publish the corresponding post.
				wp_update_post(
					array(
						'ID'          => $doctor->post_id,
						'post_status' => 'publish',
					)
				);

				// Send notification email.
				$user_data = get_userdata( $doctor->user_id );
				if ( $user_data ) {
					\EGCare\Notifications::send_doctor_approval_email( $user_data->user_email, $user_data->display_name );
				}

				wp_safe_redirect( add_query_arg( 'message', 'approved', wp_get_referer() ) );
				exit;

			case 'reject':
				check_admin_referer( 'eg_care_reject_doctor_' . $id );

				$wpdb->update( $table_meta, array( 'verification_status' => 'rejected' ), array( 'id' => $id ) );

				wp_update_post(
					array(
						'ID'          => $doctor->post_id,
						'post_status' => 'draft',
					)
				);

				$user_data = get_userdata( $doctor->user_id );
				if ( $user_data ) {
					\EGCare\Notifications::send_doctor_rejection_email( $user_data->user_email, $user_data->display_name, '' );
				}

				wp_safe_redirect( add_query_arg( 'message', 'rejected', wp_get_referer() ) );
				exit;

			case 'delete':
				check_admin_referer( 'eg_care_delete_doctor_' . $id );
				// Clean CPT post.
				wp_delete_post( $doctor->post_id, true );
				// Clean user account.
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $doctor->user_id );
				// Clean SQL Meta row.
				$wpdb->delete( $table_meta, array( 'id' => $id ) );

				wp_safe_redirect( add_query_arg( 'message', 'deleted', wp_get_referer() ) );
				exit;
		}
	}

	/**
	 * Render Pending Verifications Page.
	 */
	public static function render_pending_verifications_page() {
		global $wpdb;
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );

		// Query pending doctors only.
		$query = "SELECT m.*, p.post_title 
			FROM $table_meta m 
			JOIN {$wpdb->posts} p ON m.post_id = p.ID 
			WHERE p.post_type = 'doctors' AND m.verification_status = 'pending' 
			ORDER BY m.id DESC";

		$pending_doctors = $wpdb->get_results( $query );

		$message = isset( $_GET['message'] ) ? sanitize_text_field( $_GET['message'] ) : '';
		?>
		<div class="wrap eg-care-admin-wrap">
			<div class="eg-care-admin-header">
				<h1 class="eg-care-admin-title"><?php esc_html_e( 'Pending Verifications', 'eg-care' ); ?></h1>
			</div>

			<?php if ( 'approved' === $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Doctor application successfully approved and user notified.', 'eg-care' ); ?></p></div>
			<?php elseif ( 'rejected' === $message ) : ?>
				<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Doctor application successfully rejected and user notified.', 'eg-care' ); ?></p></div>
			<?php elseif ( 'deleted' === $message ) : ?>
				<div class="notice notice-info is-dismissible"><p><?php esc_html_e( 'Doctor profile successfully deleted.', 'eg-care' ); ?></p></div>
			<?php endif; ?>

			<table class="wp-list-table widefat fixed striped table-view-list posts">
				<thead>
					<tr>
						<th scope="col" style="width: 80px;"><?php esc_html_e( 'Photo', 'eg-care' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Name', 'eg-care' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Specialty', 'eg-care' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Degrees / Qualifications', 'eg-care' ); ?></th>
						<th scope="col"><?php esc_html_e( 'BMDC Registration No', 'eg-care' ); ?></th>
						<th scope="col" style="width: 250px;"><?php esc_html_e( 'Certificate Files', 'eg-care' ); ?></th>
						<th scope="col" style="width: 320px;"><?php esc_html_e( 'Actions', 'eg-care' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $pending_doctors ) ) : ?>
						<tr>
							<td colspan="7" style="text-align: center; padding: 20px;">
								<strong><?php esc_html_e( 'No pending verification requests found.', 'eg-care' ); ?></strong>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $pending_doctors as $doctor ) : ?>
							<tr>
								<td>
									<?php
									$thumbnail = get_the_post_thumbnail_url( $doctor->post_id, 'thumbnail' );
									if ( $thumbnail ) :
										?>
										<img src="<?php echo esc_url( $thumbnail ); ?>" class="eg-care-doctor-photo" alt="<?php echo esc_attr( $doctor->post_title ); ?>">
									<?php else : ?>
										<?php
										$initials = '';
										$parts    = explode( ' ', $doctor->post_title );
										foreach ( $parts as $part ) {
											if ( 'Dr.' === $part ) {
												continue;
											}
											$initials .= substr( $part, 0, 1 );
										}
										$initials = substr( $initials, 0, 2 );
										?>
										<div class="eg-care-doctor-photo-placeholder"><?php echo esc_html( strtoupper( $initials ) ); ?></div>
									<?php endif; ?>
								</td>
								<td>
									<strong><a href="<?php echo esc_url( get_edit_post_link( $doctor->post_id ) ); ?>"><?php echo esc_html( $doctor->post_title ); ?></a></strong>
									<div class="row-actions" style="position: static !important; visibility: visible !important;">
										<span class="edit"><a href="<?php echo esc_url( self::edit_url( $doctor->id ) ); ?>"><?php esc_html_e( 'Edit', 'eg-care' ); ?></a></span>
									</div>
								</td>
								<td>
									<?php
									$terms = wp_get_post_terms( $doctor->post_id, 'specialty' );
									if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
										$names = wp_list_pluck( $terms, 'name' );
										echo esc_html( implode( ', ', $names ) );
									} else {
										esc_html_e( 'Unassigned', 'eg-care' );
									}
									?>
								</td>
								<td><?php echo esc_html( $doctor->degree ); ?></td>
								<td><code><?php echo esc_html( $doctor->bmdc_license_no ); ?></code></td>
								<td>
									<?php
									$certs = json_decode( $doctor->certificate_files, true );
									if ( ! empty( $certs ) ) {
										foreach ( $certs as $cert_id ) {
											$url  = \EGCare\SecureUploads::get_view_url( $cert_id );
											$mime = get_post_mime_type( $cert_id );
											if ( $mime && strpos( $mime, 'image' ) !== false ) {
												echo sprintf( '<a href="%1$s" target="_blank"><img src="%1$s" style="max-width: 80px; max-height: 80px; border: 1px solid #ccd0d4; border-radius: 4px; padding: 2px; margin-right: 5px;" alt="Certificate"/></a>', esc_url( $url ) );
											} else {
												echo sprintf( '<a href="%1$s" target="_blank" class="button button-secondary" style="font-size:11px; height:auto; line-height:1.5; padding: 4px 8px; margin-right: 5px;">%2$s</a>', esc_url( $url ), esc_html__( 'View Document', 'eg-care' ) );
											}
										}
									} else {
										esc_html_e( 'No certificate uploaded', 'eg-care' );
									}
									?>
								</td>
								<td>
									<?php
									$approve_url = wp_nonce_url( admin_url( 'admin.php?page=eg-care-pending&action=approve&id=' . $doctor->id ), 'eg_care_approve_doctor_' . $doctor->id );
									?>
									<a href="<?php echo esc_url( $approve_url ); ?>" class="button button-primary" style="background-color: green; border-color: green; color: #fff; margin-right: 5px; vertical-align:middle;">
										<?php esc_html_e( 'Approve', 'eg-care' ); ?>
									</a>

									<form method="post" style="display: inline-block; vertical-align:middle;">
										<?php wp_nonce_field( 'eg_care_reject_doctor_' . $doctor->id, 'eg_care_reject_nonce' ); ?>
										<input type="hidden" name="doctor_id" value="<?php echo esc_attr( $doctor->id ); ?>">
										<input type="text" name="rejection_reason" placeholder="<?php esc_attr_e( 'Rejection reason...', 'eg-care' ); ?>" style="width: 140px; font-size:11px; height:30px; vertical-align:middle; margin-right:2px;" required>
										<button type="submit" name="eg_care_reject_submit" class="button button-link-delete" style="color: red; border: 1px solid red; height:30px; vertical-align:middle; padding: 0 8px; border-radius: 3px; cursor:pointer; background: none;">
											<?php esc_html_e( 'Reject', 'eg-care' ); ?>
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}


	/**
	 * Register Custom Meta Box on Doctors CPT edit screen.
	 */
	public static function add_doctor_meta_boxes() {
		add_meta_box(
			'eg_care_doctor_details',
			__( 'EG Care Doctor Details', 'eg-care' ),
			array( __CLASS__, 'render_doctor_meta_box' ),
			'doctors',
			'normal',
			'high'
		);
	}

	/**
	 * Render Custom Meta Box HTML.
	 *
	 * @param \WP_Post $post Current post object.
	 */
	public static function render_doctor_meta_box( $post ) {
		global $wpdb;
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );
		$meta       = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_meta WHERE post_id = %d", $post->ID ) );

		wp_nonce_field( 'eg_care_save_doctors_meta', 'eg_care_doctors_meta_nonce' );

		// Set default values.
		$provider_type       = $meta ? $meta->provider_type : 'doctor';
		$bmdc_license_no     = $meta ? $meta->bmdc_license_no : '';
		$bmdc_expiry_date    = $meta ? $meta->bmdc_expiry_date : '';
		$degree              = $meta ? $meta->degree : '';
		$designation         = $meta ? $meta->designation : '';
		$consultation_fee    = $meta ? $meta->consultation_fee : 0.00;
		$instant_call_fee    = $meta ? $meta->instant_call_fee : 0.00;
		$experience_years    = $meta ? $meta->experience_years : 0;
		$is_online           = $meta ? $meta->is_online : 0;
		$verification_status = $meta ? $meta->verification_status : 'pending';
		$mobile              = $meta ? $meta->mobile : '';
		$nid                 = $meta ? $meta->nid : '';
		$nationality         = $meta ? $meta->nationality : '';
		$organization        = $meta ? $meta->organization : '';
		$follow_up_days      = $meta ? $meta->follow_up_days : 0;
		$follow_up_cost      = $meta ? $meta->follow_up_cost : 0.00;
		$bank_account_name   = $meta ? $meta->bank_account_name : '';
		$bank_account_no     = $meta ? $meta->bank_account_no : '';
		$bank_branch_name    = $meta ? $meta->bank_branch_name : '';
		$bank_routing_number = $meta ? $meta->bank_routing_number : '';
		$mobile_banking_type = $meta ? $meta->mobile_banking_type : 'bkash';
		$mobile_banking_no   = $meta ? $meta->mobile_banking_no : '';
		?>
		<style>
			.eg-care-mb-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px; }
			.eg-care-mb-field { display: flex; flex-direction: column; margin-bottom: 10px; }
			.eg-care-mb-field label { font-weight: 600; margin-bottom: 5px; }
			.eg-care-mb-field input, .eg-care-mb-field select { padding: 6px; border: 1px solid #ccc; border-radius: 4px; }
		</style>
		<div class="eg-care-mb-grid">
			<div class="eg-care-mb-field">
				<label for="mb_provider_type"><?php esc_html_e( 'Provider Type', 'eg-care' ); ?></label>
				<select name="mb_provider_type" id="mb_provider_type">
					<option value="doctor" <?php selected( $provider_type, 'doctor' ); ?>><?php esc_html_e( 'Doctor', 'eg-care' ); ?></option>
					<option value="medical_professional" <?php selected( $provider_type, 'medical_professional' ); ?>><?php esc_html_e( 'Medical Professional', 'eg-care' ); ?></option>
				</select>
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_verification_status"><?php esc_html_e( 'Verification Status', 'eg-care' ); ?></label>
				<select name="mb_verification_status" id="mb_verification_status">
					<option value="pending" <?php selected( $verification_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'eg-care' ); ?></option>
					<option value="approved" <?php selected( $verification_status, 'approved' ); ?>><?php esc_html_e( 'Approved', 'eg-care' ); ?></option>
					<option value="rejected" <?php selected( $verification_status, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'eg-care' ); ?></option>
				</select>
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_mobile"><?php esc_html_e( 'Mobile Number', 'eg-care' ); ?></label>
				<input type="text" name="mb_mobile" id="mb_mobile" value="<?php echo esc_attr( $mobile ); ?>">
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_nid"><?php esc_html_e( 'NID Number', 'eg-care' ); ?></label>
				<input type="text" name="mb_nid" id="mb_nid" value="<?php echo esc_attr( $nid ); ?>">
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_nationality"><?php esc_html_e( 'Nationality', 'eg-care' ); ?></label>
				<input type="text" name="mb_nationality" id="mb_nationality" value="<?php echo esc_attr( $nationality ); ?>">
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_organization"><?php esc_html_e( 'Organization', 'eg-care' ); ?></label>
				<input type="text" name="mb_organization" id="mb_organization" value="<?php echo esc_attr( $organization ); ?>">
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_bmdc_license_no"><?php esc_html_e( 'BMDC Registration Code', 'eg-care' ); ?></label>
				<input type="text" name="mb_bmdc_license_no" id="mb_bmdc_license_no" value="<?php echo esc_attr( $bmdc_license_no ); ?>">
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_bmdc_expiry_date"><?php esc_html_e( 'BMDC Expiry Date', 'eg-care' ); ?></label>
				<input type="date" name="mb_bmdc_expiry_date" id="mb_bmdc_expiry_date" value="<?php echo esc_attr( $bmdc_expiry_date ); ?>">
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_degree"><?php esc_html_e( 'Degrees / Academic Titles', 'eg-care' ); ?></label>
				<input type="text" name="mb_degree" id="mb_degree" value="<?php echo esc_attr( $degree ); ?>">
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_designation"><?php esc_html_e( 'Designation', 'eg-care' ); ?></label>
				<input type="text" name="mb_designation" id="mb_designation" value="<?php echo esc_attr( $designation ); ?>">
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_experience_years"><?php esc_html_e( 'Years of Experience', 'eg-care' ); ?></label>
				<input type="number" name="mb_experience_years" id="mb_experience_years" value="<?php echo esc_attr( $experience_years ); ?>">
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_consultation_fee"><?php esc_html_e( 'Consultation Fee (BDT)', 'eg-care' ); ?></label>
				<input type="number" name="mb_consultation_fee" id="mb_consultation_fee" step="0.01" value="<?php echo esc_attr( $consultation_fee ); ?>">
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_instant_call_fee"><?php esc_html_e( 'Instant Call Fee (BDT)', 'eg-care' ); ?></label>
				<input type="number" name="mb_instant_call_fee" id="mb_instant_call_fee" step="0.01" value="<?php echo esc_attr( $instant_call_fee ); ?>">
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_is_online"><?php esc_html_e( 'Availability Indicator', 'eg-care' ); ?></label>
				<select name="mb_is_online" id="mb_is_online">
					<option value="0" <?php selected( $is_online, 0 ); ?>><?php esc_html_e( 'Offline', 'eg-care' ); ?></option>
					<option value="1" <?php selected( $is_online, 1 ); ?>><?php esc_html_e( 'Online Now', 'eg-care' ); ?></option>
				</select>
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_follow_up_days"><?php esc_html_e( 'Follow Up Days', 'eg-care' ); ?></label>
				<input type="number" name="mb_follow_up_days" id="mb_follow_up_days" value="<?php echo esc_attr( $follow_up_days ); ?>">
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_follow_up_cost"><?php esc_html_e( 'Follow Up Cost (BDT)', 'eg-care' ); ?></label>
				<input type="number" name="mb_follow_up_cost" id="mb_follow_up_cost" step="0.01" value="<?php echo esc_attr( $follow_up_cost ); ?>">
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_bank_account_name"><?php esc_html_e( 'Bank Account Name', 'eg-care' ); ?></label>
				<input type="text" name="mb_bank_account_name" id="mb_bank_account_name" value="<?php echo esc_attr( $bank_account_name ); ?>">
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_bank_account_no"><?php esc_html_e( 'Bank Account Number', 'eg-care' ); ?></label>
				<input type="text" name="mb_bank_account_no" id="mb_bank_account_no" value="<?php echo esc_attr( $bank_account_no ); ?>">
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_bank_branch_name"><?php esc_html_e( 'Bank Branch Name', 'eg-care' ); ?></label>
				<input type="text" name="mb_bank_branch_name" id="mb_bank_branch_name" value="<?php echo esc_attr( $bank_branch_name ); ?>">
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_bank_routing_number"><?php esc_html_e( 'Bank Routing Number', 'eg-care' ); ?></label>
				<input type="text" name="mb_bank_routing_number" id="mb_bank_routing_number" value="<?php echo esc_attr( $bank_routing_number ); ?>">
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_mobile_banking_type"><?php esc_html_e( 'Mobile Payout Wallet', 'eg-care' ); ?></label>
				<select name="mb_mobile_banking_type" id="mb_mobile_banking_type">
					<option value="bkash" <?php selected( $mobile_banking_type, 'bkash' ); ?>><?php esc_html_e( 'bKash', 'eg-care' ); ?></option>
					<option value="nagad" <?php selected( $mobile_banking_type, 'nagad' ); ?>><?php esc_html_e( 'Nagad', 'eg-care' ); ?></option>
					<option value="rocket" <?php selected( $mobile_banking_type, 'rocket' ); ?>><?php esc_html_e( 'Rocket', 'eg-care' ); ?></option>
				</select>
			</div>
			<div class="eg-care-mb-field">
				<label for="mb_mobile_banking_no"><?php esc_html_e( 'Mobile Wallet Number', 'eg-care' ); ?></label>
				<input type="text" name="mb_mobile_banking_no" id="mb_mobile_banking_no" value="<?php echo esc_attr( $mobile_banking_no ); ?>">
			</div>
		</div>
		<?php
	}

	/**
	 * Save Custom Meta Box data.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function save_doctor_meta_box_data( $post_id ) {
		// Verify Nonce.
		if ( ! isset( $_POST['eg_care_doctors_meta_nonce'] ) || ! wp_verify_nonce( $_POST['eg_care_doctors_meta_nonce'], 'eg_care_save_doctors_meta' ) ) {
			return;
		}

		// Check autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check permissions.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		global $wpdb;
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );

		// Gather inputs.
		$provider_type       = sanitize_text_field( $_POST['mb_provider_type'] );
		$bmdc_license_no     = sanitize_text_field( $_POST['mb_bmdc_license_no'] );
		$bmdc_expiry_date    = sanitize_text_field( $_POST['mb_bmdc_expiry_date'] );
		$degree              = sanitize_text_field( $_POST['mb_degree'] );
		$designation         = sanitize_text_field( $_POST['mb_designation'] );
		$experience_years    = intval( $_POST['mb_experience_years'] );
		$verification_status = sanitize_text_field( $_POST['mb_verification_status'] );
		$consultation_fee    = floatval( $_POST['mb_consultation_fee'] );
		$instant_call_fee    = floatval( $_POST['mb_instant_call_fee'] );
		$is_online           = intval( $_POST['mb_is_online'] );
		$mobile              = sanitize_text_field( $_POST['mb_mobile'] );
		$nid                 = sanitize_text_field( $_POST['mb_nid'] );
		$nationality         = sanitize_text_field( $_POST['mb_nationality'] );
		$organization        = sanitize_text_field( $_POST['mb_organization'] );
		$follow_up_days      = intval( $_POST['mb_follow_up_days'] );
		$follow_up_cost      = floatval( $_POST['mb_follow_up_cost'] );
		$bank_account_name   = sanitize_text_field( $_POST['mb_bank_account_name'] );
		$bank_account_no     = sanitize_text_field( $_POST['mb_bank_account_no'] );
		$bank_branch_name    = sanitize_text_field( $_POST['mb_bank_branch_name'] );
		$bank_routing_number = sanitize_text_field( $_POST['mb_bank_routing_number'] );
		$mobile_banking_type = sanitize_text_field( $_POST['mb_mobile_banking_type'] );
		$mobile_banking_no   = sanitize_text_field( $_POST['mb_mobile_banking_no'] );

		// Find author ID.
		$post   = get_post( $post_id );
		$author = $post ? $post->post_author : 0;

		$meta_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_meta WHERE post_id = %d", $post_id ) );

		$data = array(
			'provider_type'       => $provider_type,
			'bmdc_license_no'     => $bmdc_license_no,
			'bmdc_expiry_date'    => $bmdc_expiry_date ? $bmdc_expiry_date : null,
			'degree'              => $degree,
			'designation'         => $designation,
			'experience_years'    => $experience_years,
			'verification_status' => $verification_status,
			'consultation_fee'    => $consultation_fee,
			'instant_call_fee'    => $instant_call_fee,
			'is_online'           => $is_online,
			'mobile'              => $mobile,
			'nid'                 => $nid,
			'nationality'         => $nationality,
			'organization'        => $organization,
			'follow_up_days'      => $follow_up_days,
			'follow_up_cost'      => $follow_up_cost,
			'bank_account_name'   => $bank_account_name,
			'bank_account_no'     => $bank_account_no,
			'bank_branch_name'    => $bank_branch_name,
			'bank_routing_number' => $bank_routing_number,
			'mobile_banking_type' => $mobile_banking_type,
			'mobile_banking_no'   => $mobile_banking_no,
		);

		if ( $meta_id ) {
			$old_status = $wpdb->get_var( $wpdb->prepare( "SELECT verification_status FROM $table_meta WHERE id = %d", $meta_id ) );
			$wpdb->update( $table_meta, $data, array( 'id' => $meta_id ) );

			if ( $old_status !== $verification_status ) {
				$user_data = get_userdata( $author );
				if ( $user_data ) {
					if ( 'approved' === $verification_status ) {
						\EGCare\Notifications::send_doctor_approval_email( $user_data->user_email, $user_data->display_name );
						// Remove this hook temporarily to avoid infinite save_post loop
						remove_action( 'save_post_doctors', array( __CLASS__, 'save_doctor_meta_box_data' ) );
						wp_update_post( array( 'ID' => $post_id, 'post_status' => 'publish' ) );
						add_action( 'save_post_doctors', array( __CLASS__, 'save_doctor_meta_box_data' ) );
					} elseif ( 'rejected' === $verification_status ) {
						\EGCare\Notifications::send_doctor_rejection_email( $user_data->user_email, $user_data->display_name, '' );
						remove_action( 'save_post_doctors', array( __CLASS__, 'save_doctor_meta_box_data' ) );
						wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
						add_action( 'save_post_doctors', array( __CLASS__, 'save_doctor_meta_box_data' ) );
					}
				}
			}
		} else {
			$data['post_id']           = $post_id;
			$data['user_id']           = $author;
			$data['certificate_files'] = wp_json_encode( array() );
			$data['created_at']        = current_time( 'mysql' );
			$wpdb->insert( $table_meta, $data );
		}
	}

	/* ---------------------------------------------------------------------
	 * Edit Doctor
	 *
	 * Everything about a doctor lives in three places - the WordPress user, the
	 * `doctors` post, and the doctors_meta row - and until now only the middle
	 * one had a screen. The CPT meta box covers doctors_meta, but the `doctors`
	 * post type is registered with show_in_rest, so the block editor renders and
	 * pushes that box into the collapsed drawer at the very bottom of the page,
	 * where nobody finds it. It also has no way to change the specialty, the
	 * certificate, or the account the doctor signs in with.
	 *
	 * This screen edits all three together, laid out like Add New Doctor so the
	 * two read the same way. The meta box is deliberately left in place: anyone
	 * already used to it keeps working.
	 * ------------------------------------------------------------------- */

	/**
	 * Where the Edit Doctor screen lives.
	 */
	const EDIT_PAGE = 'eg-care-edit-doctor';

	/**
	 * Link to the Edit Doctor screen for one doctors_meta row.
	 *
	 * @param int $meta_id Row id in doctors_meta.
	 * @return string
	 */
	public static function edit_url( $meta_id ) {
		return admin_url( 'admin.php?page=' . self::EDIT_PAGE . '&id=' . (int) $meta_id );
	}

	/**
	 * Fetch a doctor by its doctors_meta row id.
	 *
	 * @param int $meta_id Row id.
	 * @return object|null
	 */
	private static function get_doctor( $meta_id ) {
		global $wpdb;
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_meta WHERE id = %d", (int) $meta_id ) );
	}

	/**
	 * Read a posted value, falling back to what is already stored.
	 *
	 * A failed save re-renders the form, and the doctor should see what they
	 * just typed rather than have it silently reverted.
	 *
	 * @param string $key      Field name.
	 * @param mixed  $fallback Stored value.
	 * @return string
	 */
	private static function field_value( $key, $fallback ) {
		if ( isset( $_POST['eg_care_edit_doctor_submit'] ) && isset( $_POST[ $key ] ) ) {
			return (string) wp_unslash( $_POST[ $key ] );
		}

		return null === $fallback ? '' : (string) $fallback;
	}

	/**
	 * Render the Edit Doctor screen.
	 */
	public static function render_edit_doctor_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'eg-care' ) );
		}

		wp_enqueue_script( 'eg-care-admin-add-doctor-js', EG_CARE_URL . 'assets/js/admin-add-doctor.js', array(), eg_care_asset_version( 'assets/js/admin-add-doctor.js' ), true );

		$meta_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = '';
		$error   = '';

		if ( isset( $_POST['eg_care_edit_doctor_submit'] ) ) {
			if ( ! isset( $_POST['eg_care_edit_doctor_nonce'] ) || ! wp_verify_nonce( $_POST['eg_care_edit_doctor_nonce'], 'eg_care_edit_doctor_' . $meta_id ) ) {
				$error = __( 'Security verification failed.', 'eg-care' );
			} else {
				$result = self::handle_edit_doctor_submission( $meta_id );
				if ( is_wp_error( $result ) ) {
					$error = $result->get_error_message();
				} else {
					$message = __( 'Doctor updated.', 'eg-care' );
				}
			}
		}

		// Read AFTER saving, so the form shows what was just written.
		$doctor = self::get_doctor( $meta_id );

		if ( ! $doctor ) {
			echo '<div class="wrap eg-care-admin-wrap"><h1>' . esc_html__( 'Edit Doctor', 'eg-care' ) . '</h1>';
			echo '<div class="notice notice-error"><p>' . esc_html__( 'That doctor could not be found. It may have been deleted.', 'eg-care' ) . '</p></div>';
			echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=eg-care-doctors' ) ) . '" class="button">' . esc_html__( 'Back to Doctor List', 'eg-care' ) . '</a></p></div>';
			return;
		}

		$post = get_post( $doctor->post_id );
		$user = get_userdata( $doctor->user_id );

		$specialties = get_terms(
			array(
				'taxonomy'   => 'specialty',
				'hide_empty' => false,
			)
		);

		$current_terms   = wp_get_post_terms( $doctor->post_id, 'specialty', array( 'fields' => 'ids' ) );
		$current_special = ! is_wp_error( $current_terms ) && ! empty( $current_terms ) ? (int) $current_terms[0] : 0;
		$current_special = (int) self::field_value( 'specialty_id', $current_special );

		// Which payout column already holds something decides which tab opens.
		$payout_type = '' !== (string) $doctor->mobile_banking_no || '' !== (string) $doctor->mobile_banking_type ? 'mobile' : 'bank';
		$payout_type = self::field_value( 'payout_type', $payout_type );

		$thumb_id = get_post_thumbnail_id( $doctor->post_id );
		$certs    = json_decode( (string) $doctor->certificate_files, true );
		$certs    = is_array( $certs ) ? array_filter( $certs ) : array();
		?>
		<div class="wrap eg-care-admin-wrap">
			<div class="eg-care-admin-header">
				<h1 class="eg-care-admin-title">
					<?php
					printf(
						/* translators: %s: doctor name */
						esc_html__( 'Edit Doctor: %s', 'eg-care' ),
						esc_html( $post ? $post->post_title : __( '(untitled)', 'eg-care' ) )
					);
					?>
				</h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=eg-care-doctors' ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Back to List', 'eg-care' ); ?>
				</a>
				<?php if ( $post ) : ?>
					<a href="<?php echo esc_url( get_edit_post_link( $doctor->post_id ) ); ?>" class="page-title-action">
						<?php esc_html_e( 'Open in WordPress editor', 'eg-care' ); ?>
					</a>
				<?php endif; ?>
			</div>

			<?php if ( $error ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>
			<?php if ( $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! $user ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'This doctor has no WordPress account attached, so the account fields below are disabled. The profile can still be edited.', 'eg-care' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="eg-care-form-card">
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'eg_care_edit_doctor_' . $meta_id, 'eg_care_edit_doctor_nonce' ); ?>

					<!-- SECTION 1: the account the doctor signs in with -->
					<div class="eg-care-form-section">
						<h3 class="eg-care-form-section-title"><?php esc_html_e( '1. User Account Credentials', 'eg-care' ); ?></h3>
						<div class="eg-care-form-grid">
							<div class="eg-care-form-field">
								<label><?php esc_html_e( 'Username', 'eg-care' ); ?></label>
								<input type="text" value="<?php echo esc_attr( $user ? $user->user_login : '' ); ?>" disabled>
								<p class="description"><?php esc_html_e( 'WordPress does not allow a username to be changed.', 'eg-care' ); ?></p>
							</div>
							<div class="eg-care-form-field">
								<label for="doctor_email"><?php esc_html_e( 'Email Address', 'eg-care' ); ?></label>
								<input type="email" name="doctor_email" id="doctor_email" <?php disabled( ! $user ); ?> value="<?php echo esc_attr( self::field_value( 'doctor_email', $user ? $user->user_email : '' ) ); ?>">
								<p class="description"><?php esc_html_e( 'Verification and appointment notices are sent here.', 'eg-care' ); ?></p>
							</div>
							<div class="eg-care-form-field">
								<label for="doctor_password"><?php esc_html_e( 'New Password', 'eg-care' ); ?></label>
								<input type="password" name="doctor_password" id="doctor_password" autocomplete="new-password" minlength="6" <?php disabled( ! $user ); ?>>
								<p class="description"><?php esc_html_e( 'Leave blank to keep the current password. Setting one signs the doctor out of their other sessions.', 'eg-care' ); ?></p>
							</div>
						</div>
					</div>

					<!-- SECTION 2: profile -->
					<div class="eg-care-form-section">
						<h3 class="eg-care-form-section-title"><?php esc_html_e( '2. Profile Information', 'eg-care' ); ?></h3>
						<div class="eg-care-form-grid">
							<div class="eg-care-form-field">
								<label for="doctor_name"><?php esc_html_e( 'Full Name *', 'eg-care' ); ?></label>
								<input type="text" name="doctor_name" id="doctor_name" required value="<?php echo esc_attr( self::field_value( 'doctor_name', $post ? $post->post_title : '' ) ); ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="specialty_id"><?php esc_html_e( 'Specialty', 'eg-care' ); ?></label>
								<select name="specialty_id" id="specialty_id">
									<option value=""><?php esc_html_e( '-- Select Specialty --', 'eg-care' ); ?></option>
									<?php foreach ( $specialties as $spec ) : ?>
										<option value="<?php echo esc_attr( $spec->term_id ); ?>" <?php selected( $current_special, (int) $spec->term_id ); ?>>
											<?php echo esc_html( $spec->name ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="eg-care-form-field">
								<label for="doctor_photo"><?php esc_html_e( 'Profile Photo', 'eg-care' ); ?></label>
								<?php if ( $thumb_id ) : ?>
									<div style="margin-bottom:8px;">
										<?php echo get_the_post_thumbnail( $doctor->post_id, array( 80, 80 ), array( 'style' => 'border-radius:6px;border:1px solid #ccd0d4;' ) ); ?>
									</div>
									<label style="font-weight:400;">
										<input type="checkbox" name="remove_photo" value="1">
										<?php esc_html_e( 'Remove the current photo', 'eg-care' ); ?>
									</label>
								<?php endif; ?>
								<input type="file" name="doctor_photo" id="doctor_photo" accept="image/*">
								<p class="description"><?php esc_html_e( 'Choosing a file replaces the current photo.', 'eg-care' ); ?></p>
							</div>
							<div class="eg-care-form-field">
								<label for="provider_type"><?php esc_html_e( 'Provider Type', 'eg-care' ); ?></label>
								<?php $provider_type = self::field_value( 'provider_type', $doctor->provider_type ); ?>
								<select name="provider_type" id="provider_type">
									<option value="doctor" <?php selected( $provider_type, 'doctor' ); ?>><?php esc_html_e( 'Doctor', 'eg-care' ); ?></option>
									<option value="medical_professional" <?php selected( $provider_type, 'medical_professional' ); ?>><?php esc_html_e( 'Medical Professional', 'eg-care' ); ?></option>
								</select>
							</div>
							<div class="eg-care-form-field">
								<label for="mobile"><?php esc_html_e( 'Mobile *', 'eg-care' ); ?></label>
								<input type="text" name="mobile" id="mobile" required value="<?php echo esc_attr( self::field_value( 'mobile', $doctor->mobile ) ); ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="nid"><?php esc_html_e( 'NID', 'eg-care' ); ?></label>
								<input type="text" name="nid" id="nid" value="<?php echo esc_attr( self::field_value( 'nid', $doctor->nid ) ); ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="nationality"><?php esc_html_e( 'Nationality', 'eg-care' ); ?></label>
								<?php $nationality = self::field_value( 'nationality', $doctor->nationality ); ?>
								<select name="nationality" id="nationality">
									<option value=""><?php esc_html_e( '-- Not stated --', 'eg-care' ); ?></option>
									<option value="Bangladeshi" <?php selected( $nationality, 'Bangladeshi' ); ?>><?php esc_html_e( 'Bangladeshi', 'eg-care' ); ?></option>
									<option value="Other" <?php selected( $nationality, 'Other' ); ?>><?php esc_html_e( 'Other', 'eg-care' ); ?></option>
								</select>
							</div>
							<div class="eg-care-form-field">
								<label for="organization"><?php esc_html_e( 'Organization', 'eg-care' ); ?></label>
								<input type="text" name="organization" id="organization" value="<?php echo esc_attr( self::field_value( 'organization', $doctor->organization ) ); ?>">
							</div>
							<div class="eg-care-form-field span-2">
								<label for="doctor_bio"><?php esc_html_e( 'Biography / Experience Overview', 'eg-care' ); ?></label>
								<textarea name="doctor_bio" id="doctor_bio" rows="4"><?php echo esc_textarea( self::field_value( 'doctor_bio', $post ? $post->post_content : '' ) ); ?></textarea>
							</div>
						</div>
					</div>

					<!-- SECTION 3: professional -->
					<div class="eg-care-form-section">
						<h3 class="eg-care-form-section-title"><?php esc_html_e( '3. Professional Metadata & Pricing', 'eg-care' ); ?></h3>
						<div class="eg-care-form-grid">
							<div class="eg-care-form-field">
								<label for="bmdc_license_no"><?php esc_html_e( 'BMDC Registration No', 'eg-care' ); ?></label>
								<input type="text" name="bmdc_license_no" id="bmdc_license_no" value="<?php echo esc_attr( self::field_value( 'bmdc_license_no', $doctor->bmdc_license_no ) ); ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="bmdc_expiry_date"><?php esc_html_e( 'BMDC Expiry Date', 'eg-care' ); ?></label>
								<?php
								// 0000-00-00 is not a date any browser will show, and older
								// rows can hold it. Treat it as "not set".
								$expiry = (string) $doctor->bmdc_expiry_date;
								$expiry = ( '' === $expiry || '0000-00-00' === $expiry ) ? '' : $expiry;
								?>
								<input type="date" name="bmdc_expiry_date" id="bmdc_expiry_date" value="<?php echo esc_attr( self::field_value( 'bmdc_expiry_date', $expiry ) ); ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="degree"><?php esc_html_e( 'Degrees / Qualifications (comma separated)', 'eg-care' ); ?></label>
								<input type="text" name="degree" id="degree" placeholder="e.g. MBBS, FCPS, MD" value="<?php echo esc_attr( self::field_value( 'degree', $doctor->degree ) ); ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="designation"><?php esc_html_e( 'Designation', 'eg-care' ); ?></label>
								<input type="text" name="designation" id="designation" placeholder="e.g. Associate Professor" value="<?php echo esc_attr( self::field_value( 'designation', $doctor->designation ) ); ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="experience_years"><?php esc_html_e( 'Years of Experience', 'eg-care' ); ?></label>
								<input type="number" name="experience_years" id="experience_years" min="0" value="<?php echo esc_attr( self::field_value( 'experience_years', $doctor->experience_years ) ); ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="consultation_fee"><?php esc_html_e( 'Scheduled Consultation Fee (BDT) *', 'eg-care' ); ?></label>
								<input type="number" name="consultation_fee" id="consultation_fee" min="0" step="0.01" required value="<?php echo esc_attr( self::field_value( 'consultation_fee', $doctor->consultation_fee ) ); ?>">
								<p class="description"><?php esc_html_e( 'This is what the patient is charged, so it cannot be left blank.', 'eg-care' ); ?></p>
							</div>
							<div class="eg-care-form-field">
								<label for="instant_call_fee"><?php esc_html_e( 'Instant Call Fee (BDT)', 'eg-care' ); ?></label>
								<input type="number" name="instant_call_fee" id="instant_call_fee" min="0" step="0.01" value="<?php echo esc_attr( self::field_value( 'instant_call_fee', $doctor->instant_call_fee ) ); ?>">
								<p class="description"><?php esc_html_e( '0 means an instant call falls back to the scheduled fee.', 'eg-care' ); ?></p>
							</div>
							<div class="eg-care-form-field">
								<label for="follow_up_days"><?php esc_html_e( 'Follow up days', 'eg-care' ); ?></label>
								<input type="number" name="follow_up_days" id="follow_up_days" min="0" value="<?php echo esc_attr( self::field_value( 'follow_up_days', $doctor->follow_up_days ) ); ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="follow_up_cost"><?php esc_html_e( 'Follow up cost', 'eg-care' ); ?></label>
								<input type="number" name="follow_up_cost" id="follow_up_cost" min="0" step="0.01" value="<?php echo esc_attr( self::field_value( 'follow_up_cost', $doctor->follow_up_cost ) ); ?>">
							</div>
						</div>
					</div>

					<!-- SECTION 4: payout -->
					<div class="eg-care-form-section">
						<h3 class="eg-care-form-section-title"><?php esc_html_e( '4. Bank & Mobile Payout Settings', 'eg-care' ); ?></h3>

						<div class="eg-care-form-grid" style="margin-bottom: 20px;">
							<div class="eg-care-form-field">
								<label for="payout_type"><?php esc_html_e( 'Payout Method', 'eg-care' ); ?></label>
								<select name="payout_type" id="payout_type">
									<option value="bank" <?php selected( $payout_type, 'bank' ); ?>><?php esc_html_e( 'Bank Account', 'eg-care' ); ?></option>
									<option value="mobile" <?php selected( $payout_type, 'mobile' ); ?>><?php esc_html_e( 'Mobile Banking', 'eg-care' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Saving keeps only the method selected here; the other one is cleared.', 'eg-care' ); ?></p>
							</div>
						</div>

						<div class="eg-care-form-grid" id="sec_bank_fields"<?php echo 'bank' === $payout_type ? '' : ' style="display: none;"'; ?>>
							<div class="eg-care-form-field span-2">
								<label for="bank_branch"><?php esc_html_e( 'Bank Name & Branch', 'eg-care' ); ?></label>
								<input type="text" name="bank_branch" id="bank_branch" value="<?php echo esc_attr( self::field_value( 'bank_branch', $doctor->bank_branch_name ) ); ?>">
								<p class="description"><?php esc_html_e( 'One box, because the record keeps one line for it.', 'eg-care' ); ?></p>
							</div>
							<div class="eg-care-form-field">
								<label for="bank_account_no"><?php esc_html_e( 'Bank Account Number', 'eg-care' ); ?></label>
								<input type="text" name="bank_account_no" id="bank_account_no" value="<?php echo esc_attr( self::field_value( 'bank_account_no', $doctor->bank_account_no ) ); ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="bank_account_name"><?php esc_html_e( 'Bank Account Name', 'eg-care' ); ?></label>
								<input type="text" name="bank_account_name" id="bank_account_name" value="<?php echo esc_attr( self::field_value( 'bank_account_name', $doctor->bank_account_name ) ); ?>">
							</div>
							<div class="eg-care-form-field">
								<label for="bank_routing_number"><?php esc_html_e( 'Routing Number', 'eg-care' ); ?></label>
								<input type="text" name="bank_routing_number" id="bank_routing_number" value="<?php echo esc_attr( self::field_value( 'bank_routing_number', $doctor->bank_routing_number ) ); ?>">
							</div>
						</div>

						<div class="eg-care-form-grid" id="sec_mobile_fields"<?php echo 'mobile' === $payout_type ? '' : ' style="display: none;"'; ?>>
							<div class="eg-care-form-field">
								<label for="mobile_banking_type"><?php esc_html_e( 'Mobile Banking Wallet', 'eg-care' ); ?></label>
								<?php $wallet = self::field_value( 'mobile_banking_type', $doctor->mobile_banking_type ); ?>
								<select name="mobile_banking_type" id="mobile_banking_type">
									<option value="bkash" <?php selected( $wallet, 'bkash' ); ?>><?php esc_html_e( 'bKash', 'eg-care' ); ?></option>
									<option value="nagad" <?php selected( $wallet, 'nagad' ); ?>><?php esc_html_e( 'Nagad', 'eg-care' ); ?></option>
									<option value="rocket" <?php selected( $wallet, 'rocket' ); ?>><?php esc_html_e( 'Rocket', 'eg-care' ); ?></option>
								</select>
							</div>
							<div class="eg-care-form-field">
								<label for="mobile_banking_no"><?php esc_html_e( 'Mobile Wallet Number', 'eg-care' ); ?></label>
								<input type="text" name="mobile_banking_no" id="mobile_banking_no" value="<?php echo esc_attr( self::field_value( 'mobile_banking_no', $doctor->mobile_banking_no ) ); ?>">
							</div>
						</div>
					</div>

					<!-- SECTION 5: documents -->
					<div class="eg-care-form-section">
						<h3 class="eg-care-form-section-title"><?php esc_html_e( '5. Verification Documents', 'eg-care' ); ?></h3>
						<div class="eg-care-form-grid">
							<div class="eg-care-form-field span-2">
								<label><?php esc_html_e( 'Current certificate', 'eg-care' ); ?></label>
								<p>
									<?php
									if ( $certs ) {
										foreach ( $certs as $cert_id ) {
											$url  = \EGCare\SecureUploads::get_view_url( $cert_id );
											$mime = get_post_mime_type( $cert_id );
											if ( $mime && false !== strpos( $mime, 'image' ) ) {
												printf(
													'<a href="%1$s" target="_blank" rel="noopener"><img src="%1$s" style="max-width:90px;max-height:90px;border:1px solid #ccd0d4;border-radius:4px;padding:2px;margin-right:6px;" alt="%2$s" /></a>',
													esc_url( $url ),
													esc_attr__( 'Certificate', 'eg-care' )
												);
											} else {
												printf(
													'<a href="%1$s" target="_blank" rel="noopener" class="button button-secondary" style="margin-right:6px;">%2$s</a>',
													esc_url( $url ),
													esc_html__( 'View Document', 'eg-care' )
												);
											}
										}
									} else {
										esc_html_e( 'No certificate uploaded.', 'eg-care' );
									}
									?>
								</p>
								<label for="doctor_certificate" style="margin-top:8px;display:block;"><?php esc_html_e( 'Replace certificate', 'eg-care' ); ?></label>
								<input type="file" name="doctor_certificate" id="doctor_certificate" accept=".pdf,image/*">
								<p class="description"><?php esc_html_e( 'PDF, JPG, JPEG or PNG, up to 2 MB. Choosing a file replaces the one above.', 'eg-care' ); ?></p>
							</div>
						</div>
					</div>

					<!-- SECTION 6: status -->
					<div class="eg-care-form-section">
						<h3 class="eg-care-form-section-title"><?php esc_html_e( '6. Verification & Availability', 'eg-care' ); ?></h3>
						<div class="eg-care-form-grid">
							<div class="eg-care-form-field">
								<label for="verification_status"><?php esc_html_e( 'Verification Status', 'eg-care' ); ?></label>
								<?php $status = self::field_value( 'verification_status', $doctor->verification_status ); ?>
								<select name="verification_status" id="verification_status">
									<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'eg-care' ); ?></option>
									<option value="approved" <?php selected( $status, 'approved' ); ?>><?php esc_html_e( 'Approved', 'eg-care' ); ?></option>
									<option value="rejected" <?php selected( $status, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'eg-care' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Only approved doctors appear to patients. Changing this here does not send an email; use Approve or Reject on the Pending Verifications screen for that.', 'eg-care' ); ?></p>
							</div>
							<div class="eg-care-form-field">
								<label for="is_online"><?php esc_html_e( 'Online for instant calls', 'eg-care' ); ?></label>
								<?php $online = self::field_value( 'is_online', $doctor->is_online ); ?>
								<select name="is_online" id="is_online">
									<option value="0" <?php selected( $online, '0' ); ?>><?php esc_html_e( 'Offline', 'eg-care' ); ?></option>
									<option value="1" <?php selected( $online, '1' ); ?>><?php esc_html_e( 'Online', 'eg-care' ); ?></option>
								</select>
							</div>
						</div>
					</div>

					<p class="submit">
						<input type="submit" name="eg_care_edit_doctor_submit" id="submit" class="button button-primary eg-care-btn-primary" value="<?php esc_attr_e( 'Save Changes', 'eg-care' ); ?>">
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Write an edited doctor back to all three places it lives.
	 *
	 * @param int $meta_id doctors_meta row id.
	 * @return true|\WP_Error
	 */
	private static function handle_edit_doctor_submission( $meta_id ) {
		global $wpdb;

		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'forbidden', __( 'You do not have permission to perform this action.', 'eg-care' ) );
		}

		$doctor = self::get_doctor( $meta_id );

		if ( ! $doctor ) {
			return new \WP_Error( 'not_found', __( 'That doctor could not be found.', 'eg-care' ) );
		}

		$post_id = (int) $doctor->post_id;
		$user_id = (int) $doctor->user_id;
		$user    = $user_id ? get_userdata( $user_id ) : null;

		$name  = sanitize_text_field( wp_unslash( $_POST['doctor_name'] ?? '' ) );
		$bio   = wp_kses_post( wp_unslash( $_POST['doctor_bio'] ?? '' ) );
		$email = sanitize_email( wp_unslash( $_POST['doctor_email'] ?? '' ) );
		$pass  = (string) ( $_POST['doctor_password'] ?? '' );

		$mobile   = sanitize_text_field( wp_unslash( $_POST['mobile'] ?? '' ) );
		$fee_raw  = trim( (string) ( $_POST['consultation_fee'] ?? '' ) );

		// The same three the registration form insists on, for the same reasons:
		// a nameless profile, an uncontactable doctor, and a blank fee that would
		// be charged to the patient as 0.00.
		if ( '' === $name || '' === $mobile || '' === $fee_raw ) {
			return new \WP_Error( 'missing_fields', __( 'Full Name, Mobile and Consultation Fee are required.', 'eg-care' ) );
		}

		// The account, when there is one to change.
		if ( $user ) {
			if ( '' === $email || ! is_email( $email ) ) {
				return new \WP_Error( 'bad_email', __( 'Please enter a valid email address.', 'eg-care' ) );
			}

			$owner = email_exists( $email );
			if ( $owner && (int) $owner !== $user_id ) {
				return new \WP_Error( 'email_taken', __( 'That email address belongs to another account.', 'eg-care' ) );
			}

			$account = array(
				'ID'           => $user_id,
				'user_email'   => $email,
				'display_name' => $name,
			);

			if ( '' !== $pass ) {
				if ( strlen( $pass ) < 6 ) {
					return new \WP_Error( 'short_password', __( 'A new password must be at least 6 characters.', 'eg-care' ) );
				}
				$account['user_pass'] = $pass;
			}

			$updated = wp_update_user( $account );

			if ( is_wp_error( $updated ) ) {
				return $updated;
			}
		}

		// The post. save_post_doctors would run the meta box handler over a
		// request that carries none of its fields, so it is stood down first.
		if ( $post_id ) {
			remove_action( 'save_post_doctors', array( __CLASS__, 'save_doctor_meta_box_data' ) );

			$post_result = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_title'   => $name,
					'post_content' => $bio,
				),
				true
			);

			add_action( 'save_post_doctors', array( __CLASS__, 'save_doctor_meta_box_data' ) );

			if ( is_wp_error( $post_result ) ) {
				return $post_result;
			}

			$specialty_id = isset( $_POST['specialty_id'] ) ? absint( $_POST['specialty_id'] ) : 0;

			if ( $specialty_id ) {
				wp_set_object_terms( $post_id, $specialty_id, 'specialty' );
			} else {
				wp_set_object_terms( $post_id, array(), 'specialty' );
			}

			self::save_edited_photo( $post_id );
		}

		$payout = 'mobile' === ( $_POST['payout_type'] ?? 'bank' ) ? 'mobile' : 'bank';

		$data = array(
			'provider_type'       => sanitize_text_field( wp_unslash( $_POST['provider_type'] ?? '' ) ),
			'bmdc_license_no'     => sanitize_text_field( wp_unslash( $_POST['bmdc_license_no'] ?? '' ) ),
			// A date column: '' becomes 0000-00-00 on a server that is not
			// strict, and a hard error on one that is.
			'bmdc_expiry_date'    => '' !== trim( (string) ( $_POST['bmdc_expiry_date'] ?? '' ) ) ? sanitize_text_field( wp_unslash( $_POST['bmdc_expiry_date'] ) ) : null,
			'degree'              => sanitize_text_field( wp_unslash( $_POST['degree'] ?? '' ) ),
			'designation'         => sanitize_text_field( wp_unslash( $_POST['designation'] ?? '' ) ),
			'consultation_fee'    => floatval( $fee_raw ),
			'instant_call_fee'    => floatval( $_POST['instant_call_fee'] ?? 0 ),
			'experience_years'    => intval( $_POST['experience_years'] ?? 0 ),
			'is_online'           => ! empty( $_POST['is_online'] ) ? 1 : 0,
			'verification_status' => sanitize_text_field( wp_unslash( $_POST['verification_status'] ?? 'pending' ) ),
			'mobile'              => $mobile,
			'nid'                 => sanitize_text_field( wp_unslash( $_POST['nid'] ?? '' ) ),
			'nationality'         => sanitize_text_field( wp_unslash( $_POST['nationality'] ?? '' ) ),
			'organization'        => sanitize_text_field( wp_unslash( $_POST['organization'] ?? '' ) ),
			'follow_up_days'      => intval( $_POST['follow_up_days'] ?? 0 ),
			'follow_up_cost'      => floatval( $_POST['follow_up_cost'] ?? 0 ),
			// Only the chosen method is kept, so a payout can never be attempted
			// against a stale account left behind by the other one.
			'bank_account_name'   => 'bank' === $payout ? sanitize_text_field( wp_unslash( $_POST['bank_account_name'] ?? '' ) ) : null,
			'bank_account_no'     => 'bank' === $payout ? sanitize_text_field( wp_unslash( $_POST['bank_account_no'] ?? '' ) ) : null,
			'bank_branch_name'    => 'bank' === $payout ? sanitize_text_field( wp_unslash( $_POST['bank_branch'] ?? '' ) ) : null,
			'bank_routing_number' => 'bank' === $payout ? sanitize_text_field( wp_unslash( $_POST['bank_routing_number'] ?? '' ) ) : null,
			'mobile_banking_type' => 'mobile' === $payout ? sanitize_text_field( wp_unslash( $_POST['mobile_banking_type'] ?? '' ) ) : null,
			'mobile_banking_no'   => 'mobile' === $payout ? sanitize_text_field( wp_unslash( $_POST['mobile_banking_no'] ?? '' ) ) : null,
		);

		if ( ! in_array( $data['verification_status'], array( 'pending', 'approved', 'rejected' ), true ) ) {
			$data['verification_status'] = 'pending';
		}

		// A replacement certificate, when one was attached.
		$new_cert = self::save_edited_certificate( $post_id );

		if ( is_wp_error( $new_cert ) ) {
			return $new_cert;
		}

		if ( $new_cert ) {
			$data['certificate_files'] = wp_json_encode( array( $new_cert ) );
		}

		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );
		$saved      = $wpdb->update( $table_meta, $data, array( 'id' => (int) $meta_id ) );

		if ( false === $saved ) {
			return new \WP_Error( 'db_error', __( 'Failed to save the doctor record.', 'eg-care' ) );
		}

		return true;
	}

	/**
	 * Replace or remove the profile photo.
	 *
	 * @param int $post_id Doctor post id.
	 */
	private static function save_edited_photo( $post_id ) {
		if ( ! empty( $_POST['remove_photo'] ) && empty( $_FILES['doctor_photo']['name'] ) ) {
			delete_post_thumbnail( $post_id );
			return;
		}

		if ( empty( $_FILES['doctor_photo']['name'] ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_upload( 'doctor_photo', $post_id );

		if ( ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	/**
	 * Store a replacement certificate.
	 *
	 * @param int $post_id Doctor post id.
	 * @return int|false|\WP_Error Attachment id, false when nothing was attached.
	 */
	private static function save_edited_certificate( $post_id ) {
		if ( empty( $_FILES['doctor_certificate']['name'] ) ) {
			return false;
		}

		if ( ! $post_id ) {
			return new \WP_Error( 'no_post', __( 'This doctor has no profile post, so a certificate cannot be attached.', 'eg-care' ) );
		}

		if ( UPLOAD_ERR_OK !== $_FILES['doctor_certificate']['error'] ) {
			return new \WP_Error( 'upload_error', __( 'Error uploading the certificate. Please verify the file is not corrupted.', 'eg-care' ) );
		}

		if ( $_FILES['doctor_certificate']['size'] > 2 * 1024 * 1024 ) {
			return new \WP_Error( 'too_large', __( 'Certificate file size exceeds the 2MB limit.', 'eg-care' ) );
		}

		$ext = strtolower( pathinfo( $_FILES['doctor_certificate']['name'], PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, array( 'pdf', 'jpg', 'jpeg', 'png' ), true ) ) {
			return new \WP_Error( 'bad_format', __( 'Invalid certificate format. Only PDF, JPG, JPEG, and PNG are allowed.', 'eg-care' ) );
		}

		// SecureUploads reads $_FILES by key and keeps the file out of the media
		// library, which is what the registration form does too.
		$cert_id = \EGCare\SecureUploads::handle_upload( 'doctor_certificate', $post_id );

		if ( is_wp_error( $cert_id ) ) {
			return $cert_id;
		}

		return (int) $cert_id;
	}
}

/**
 * WP_List_Table subclass for displaying doctors ledger.
 */
class EG_Care_Doctors_List_Table extends \WP_List_Table {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'doctor',
				'plural'   => 'doctors',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Default column rendering helper.
	 */
	public function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}

	/**
	 * Render profile picture thumbnail.
	 */
	public function column_photo( $item ) {
		$thumbnail = get_the_post_thumbnail_url( $item->post_id, 'thumbnail' );
		if ( $thumbnail ) {
			return sprintf( '<img src="%s" class="eg-care-doctor-photo" alt="%s">', esc_url( $thumbnail ), esc_attr( $item->post_title ) );
		}

		// Initial display placeholder.
		$initials = '';
		$parts    = explode( ' ', $item->post_title );
		foreach ( $parts as $part ) {
			if ( 'Dr.' === $part ) {
				continue;
			}
			$initials .= substr( $part, 0, 1 );
		}
		$initials = substr( $initials, 0, 2 );

		return sprintf( '<div class="eg-care-doctor-photo-placeholder">%s</div>', esc_html( strtoupper( $initials ) ) );
	}

	/**
	 * Render name along with Edit, Delete, and Approval row actions.
	 */
	public function column_name( $item ) {
		$actions = array();

		// Edit link. AdminDoctors::edit_url() is the screen that can
		// change everything about a doctor; the WordPress editor only reaches
		// the post title, the body and the meta box buried under it.
		$actions['edit'] = sprintf( '<a href="%s">%s</a>', esc_url( \EGCare\AdminDoctors::edit_url( $item->id ) ), __( 'Edit', 'eg-care' ) );
		$actions['edit_cpt'] = sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $item->post_id ) ), __( 'WP Editor', 'eg-care' ) );

		// Approval / Rejection buttons.
		if ( 'approved' !== $item->verification_status ) {
			$approve_url = wp_nonce_url( admin_url( 'admin.php?page=eg-care-doctors&action=approve&id=' . $item->id ), 'eg_care_approve_doctor_' . $item->id );
			$actions['approve'] = sprintf( '<a href="%s" style="color:green;">%s</a>', esc_url( $approve_url ), __( 'Approve', 'eg-care' ) );
		}
		if ( 'rejected' !== $item->verification_status ) {
			$reject_url = wp_nonce_url( admin_url( 'admin.php?page=eg-care-doctors&action=reject&id=' . $item->id ), 'eg_care_reject_doctor_' . $item->id );
			$actions['reject'] = sprintf( '<a href="%s" style="color:orange;">%s</a>', esc_url( $reject_url ), __( 'Reject', 'eg-care' ) );
		}

		// Delete.
		$delete_url = wp_nonce_url( admin_url( 'admin.php?page=eg-care-doctors&action=delete&id=' . $item->id ), 'eg_care_delete_doctor_' . $item->id );
		$actions['delete'] = sprintf( '<a href="%s" style="color:red;" onclick="return confirm(\'%s\')">%s</a>', esc_url( $delete_url ), esc_attr__( 'Are you sure you want to delete this doctor? This will remove the WordPress user account and profile post too.', 'eg-care' ), __( 'Delete', 'eg-care' ) );

		return sprintf( '<strong><a href="%1$s">%2$s</a></strong> %3$s', esc_url( \EGCare\AdminDoctors::edit_url( $item->id ) ), esc_html( $item->post_title ), $this->row_actions( $actions ) );
	}

	/**
	 * Get CPT Taxonomy terms list.
	 */
	public function column_specialty( $item ) {
		$terms = wp_get_post_terms( $item->post_id, 'specialty' );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return __( 'Unassigned', 'eg-care' );
		}
		$names = wp_list_pluck( $terms, 'name' );
		return esc_html( implode( ', ', $names ) );
	}

	/**
	 * Render custom badge for verification status.
	 */
	public function column_verification_status( $item ) {
		$status = esc_attr( $item->verification_status );
		return sprintf( '<span class="eg-care-status-badge status-%s">%s</span>', $status, ucfirst( $status ) );
	}

	/**
	 * Render consultation fee.
	 */
	public function column_consultation_fee( $item ) {
		return esc_html( number_format( $item->consultation_fee, 2 ) . ' BDT' );
	}

	/**
	 * Define column grid labels.
	 */
	public function get_columns() {
		return array(
			'photo'               => __( 'Photo', 'eg-care' ),
			'name'                => __( 'Name', 'eg-care' ),
			'specialty'           => __( 'Specialty', 'eg-care' ),
			'verification_status' => __( 'Verification Status', 'eg-care' ),
			'consultation_fee'    => __( 'Consultation Fee', 'eg-care' ),
		);
	}

	/**
	 * Set up, query and populate the items array.
	 */
	public function prepare_items() {
		global $wpdb;
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );

		// Columns.
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Query items.
		$query = "SELECT m.*, p.post_title 
			FROM $table_meta m 
			JOIN {$wpdb->posts} p ON m.post_id = p.ID 
			WHERE p.post_type = 'doctors' 
			ORDER BY m.id DESC";

		$this->items = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
