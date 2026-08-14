<?php
namespace Meditaj;

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
		$list_table = new \Meditaj\Meditaj_Doctors_List_Table();
		$list_table->prepare_items();
		?>
		<div class="wrap meditaj-admin-wrap">
			<div class="meditaj-admin-header">
				<h1 class="meditaj-admin-title"><?php esc_html_e( 'Doctor List', 'meditaj' ); ?></h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=meditaj-add-doctor' ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Add New Doctor', 'meditaj' ); ?>
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
		// Handle submission.
		$message = '';
		$error   = '';

		if ( isset( $_POST['meditaj_add_doctor_submit'] ) ) {
			// Verify Nonce.
			if ( ! isset( $_POST['meditaj_add_doctor_nonce'] ) || ! wp_verify_nonce( $_POST['meditaj_add_doctor_nonce'], 'meditaj_add_doctor' ) ) {
				$error = __( 'Security verification failed.', 'meditaj' );
			} elseif ( ! current_user_can( 'manage_options' ) ) {
				$error = __( 'You do not have permission to perform this action.', 'meditaj' );
			} else {
				$result = self::handle_add_doctor_submission();
				if ( is_wp_error( $result ) ) {
					$error = $result->get_error_message();
				} else {
					$message = __( 'Doctor successfully added.', 'meditaj' );
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
		<div class="wrap meditaj-admin-wrap">
			<div class="meditaj-admin-header">
				<h1 class="meditaj-admin-title"><?php esc_html_e( 'Add New Doctor', 'meditaj' ); ?></h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=meditaj-doctors' ) ); ?>" class="page-title-action">
					<?php esc_html_e( 'Back to List', 'meditaj' ); ?>
				</a>
			</div>

			<?php if ( $error ) : ?>
				<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>
			<?php if ( $message ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
			<?php endif; ?>

			<div class="meditaj-form-card">
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'meditaj_add_doctor', 'meditaj_add_doctor_nonce' ); ?>

					<!-- SECTION 1: WordPress User Credentials -->
					<div class="meditaj-form-section">
						<h3 class="meditaj-form-section-title"><?php esc_html_e( '1. User Account Credentials', 'meditaj' ); ?></h3>
						<div class="meditaj-form-grid">
							<div class="meditaj-form-field">
								<label for="doctor_username"><?php esc_html_e( 'Username *', 'meditaj' ); ?></label>
								<input type="text" name="doctor_username" id="doctor_username" required value="<?php echo isset( $_POST['doctor_username'] ) ? esc_attr( $_POST['doctor_username'] ) : ''; ?>">
							</div>
							<div class="meditaj-form-field">
								<label for="doctor_email"><?php esc_html_e( 'Email Address *', 'meditaj' ); ?></label>
								<input type="email" name="doctor_email" id="doctor_email" required value="<?php echo isset( $_POST['doctor_email'] ) ? esc_attr( $_POST['doctor_email'] ) : ''; ?>">
							</div>
							<div class="meditaj-form-field">
								<label for="doctor_password"><?php esc_html_e( 'Password *', 'meditaj' ); ?></label>
								<input type="password" name="doctor_password" id="doctor_password" required minlength="6">
							</div>
						</div>
					</div>

					<!-- SECTION 2: Doctor Profile details -->
					<div class="meditaj-form-section">
						<h3 class="meditaj-form-section-title"><?php esc_html_e( '2. Profile Information', 'meditaj' ); ?></h3>
						<div class="meditaj-form-grid">
							<div class="meditaj-form-field">
								<label for="doctor_name"><?php esc_html_e( 'Full Name *', 'meditaj' ); ?></label>
								<input type="text" name="doctor_name" id="doctor_name" required value="<?php echo isset( $_POST['doctor_name'] ) ? esc_attr( $_POST['doctor_name'] ) : ''; ?>">
							</div>
							<div class="meditaj-form-field">
								<label for="specialty_id"><?php esc_html_e( 'Specialty *', 'meditaj' ); ?></label>
								<select name="specialty_id" id="specialty_id" required>
									<option value=""><?php esc_html_e( '-- Select Specialty --', 'meditaj' ); ?></option>
									<?php foreach ( $specialties as $spec ) : ?>
										<option value="<?php echo esc_attr( $spec->term_id ); ?>"><?php echo esc_html( $spec->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="meditaj-form-field">
								<label for="doctor_photo"><?php esc_html_e( 'Profile Photo', 'meditaj' ); ?></label>
								<input type="file" name="doctor_photo" id="doctor_photo" accept="image/*">
							</div>
							<div class="meditaj-form-field">
								<label for="provider_type"><?php esc_html_e( 'Provider Type *', 'meditaj' ); ?></label>
								<select name="provider_type" id="provider_type" required>
									<option value="doctor"><?php esc_html_e( 'Doctor', 'meditaj' ); ?></option>
									<option value="medical_professional"><?php esc_html_e( 'Medical Professional', 'meditaj' ); ?></option>
								</select>
							</div>
							<div class="meditaj-form-field span-2">
								<label for="doctor_bio"><?php esc_html_e( 'Biography / Experience Overview', 'meditaj' ); ?></label>
								<textarea name="doctor_bio" id="doctor_bio" rows="4"><?php echo isset( $_POST['doctor_bio'] ) ? esc_textarea( $_POST['doctor_bio'] ) : ''; ?></textarea>
							</div>
						</div>
					</div>

					<!-- SECTION 3: Professional Metadata -->
					<div class="meditaj-form-section">
						<h3 class="meditaj-form-section-title"><?php esc_html_e( '3. Professional Metadata & Pricing', 'meditaj' ); ?></h3>
						<div class="meditaj-form-grid">
							<div class="meditaj-form-field">
								<label for="bmdc_license_no"><?php esc_html_e( 'BMDC Registration No *', 'meditaj' ); ?></label>
								<input type="text" name="bmdc_license_no" id="bmdc_license_no" required value="<?php echo isset( $_POST['bmdc_license_no'] ) ? esc_attr( $_POST['bmdc_license_no'] ) : ''; ?>">
							</div>
							<div class="meditaj-form-field">
								<label for="degree"><?php esc_html_e( 'Degrees / Qualifications (comma separated) *', 'meditaj' ); ?></label>
								<input type="text" name="degree" id="degree" required placeholder="e.g. MBBS, FCPS, MD" value="<?php echo isset( $_POST['degree'] ) ? esc_attr( $_POST['degree'] ) : ''; ?>">
							</div>
							<div class="meditaj-form-field">
								<label for="designation"><?php esc_html_e( 'Designation *', 'meditaj' ); ?></label>
								<input type="text" name="designation" id="designation" required placeholder="e.g. Associate Professor" value="<?php echo isset( $_POST['designation'] ) ? esc_attr( $_POST['designation'] ) : ''; ?>">
							</div>
							<div class="meditaj-form-field">
								<label for="experience_years"><?php esc_html_e( 'Years of Experience *', 'meditaj' ); ?></label>
								<input type="number" name="experience_years" id="experience_years" min="0" required value="<?php echo isset( $_POST['experience_years'] ) ? esc_attr( $_POST['experience_years'] ) : ''; ?>">
							</div>
							<div class="meditaj-form-field">
								<label for="consultation_fee"><?php esc_html_e( 'Scheduled Consultation Fee (BDT) *', 'meditaj' ); ?></label>
								<input type="number" name="consultation_fee" id="consultation_fee" min="0" step="0.01" required value="<?php echo isset( $_POST['consultation_fee'] ) ? esc_attr( $_POST['consultation_fee'] ) : ''; ?>">
							</div>
							<div class="meditaj-form-field">
								<label for="instant_call_fee"><?php esc_html_e( 'Instant Call Fee (BDT) *', 'meditaj' ); ?></label>
								<input type="number" name="instant_call_fee" id="instant_call_fee" min="0" step="0.01" required value="<?php echo isset( $_POST['instant_call_fee'] ) ? esc_attr( $_POST['instant_call_fee'] ) : ''; ?>">
							</div>
						</div>
					</div>

					<!-- SECTION 4: Payment Payout accounts -->
					<div class="meditaj-form-section">
						<h3 class="meditaj-form-section-title"><?php esc_html_e( '4. Bank & Mobile Payout Settings', 'meditaj' ); ?></h3>
						<div class="meditaj-form-grid">
							<div class="meditaj-form-field">
								<label for="bank_account_name"><?php esc_html_e( 'Bank Account Name', 'meditaj' ); ?></label>
								<input type="text" name="bank_account_name" id="bank_account_name" value="<?php echo isset( $_POST['bank_account_name'] ) ? esc_attr( $_POST['bank_account_name'] ) : ''; ?>">
							</div>
							<div class="meditaj-form-field">
								<label for="bank_account_no"><?php esc_html_e( 'Bank Account Number', 'meditaj' ); ?></label>
								<input type="text" name="bank_account_no" id="bank_account_no" value="<?php echo isset( $_POST['bank_account_no'] ) ? esc_attr( $_POST['bank_account_no'] ) : ''; ?>">
							</div>
							<div class="meditaj-form-field">
								<label for="mobile_banking_type"><?php esc_html_e( 'Mobile Banking Wallet', 'meditaj' ); ?></label>
								<select name="mobile_banking_type" id="mobile_banking_type">
									<option value="bkash"><?php esc_html_e( 'bKash', 'meditaj' ); ?></option>
									<option value="nagad"><?php esc_html_e( 'Nagad', 'meditaj' ); ?></option>
									<option value="rocket"><?php esc_html_e( 'Rocket', 'meditaj' ); ?></option>
								</select>
							</div>
							<div class="meditaj-form-field">
								<label for="mobile_banking_no"><?php esc_html_e( 'Mobile Wallet Number', 'meditaj' ); ?></label>
								<input type="text" name="mobile_banking_no" id="mobile_banking_no" value="<?php echo isset( $_POST['mobile_banking_no'] ) ? esc_attr( $_POST['mobile_banking_no'] ) : ''; ?>">
							</div>
						</div>
					</div>

					<p class="submit">
						<input type="submit" name="meditaj_add_doctor_submit" id="submit" class="button button-primary meditaj-btn-primary" value="<?php esc_attr_e( 'Create Doctor Profile', 'meditaj' ); ?>">
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
		$degree            = sanitize_text_field( $_POST['degree'] );
		$designation       = sanitize_text_field( $_POST['designation'] );
		$experience        = intval( $_POST['experience_years'] );
		$consultation_fee  = floatval( $_POST['consultation_fee'] );
		$instant_call_fee  = floatval( $_POST['instant_call_fee'] );
		$bank_acc_name     = sanitize_text_field( $_POST['bank_account_name'] );
		$bank_acc_no       = sanitize_text_field( $_POST['bank_account_no'] );
		$mobile_wallet     = sanitize_text_field( $_POST['mobile_banking_type'] );
		$mobile_wallet_no  = sanitize_text_field( $_POST['mobile_banking_no'] );

		// 2. Form validation.
		if ( empty( $username ) || empty( $email ) || empty( $password ) || empty( $doctor_name ) || empty( $license ) ) {
			return new \WP_Error( 'missing_fields', __( 'Please fill in all required fields.', 'meditaj' ) );
		}

		if ( username_exists( $username ) ) {
			return new \WP_Error( 'username_taken', __( 'Username is already taken.', 'meditaj' ) );
		}

		if ( email_exists( $email ) ) {
			return new \WP_Error( 'email_taken', __( 'Email address is already in use.', 'meditaj' ) );
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
				'role'         => 'meditaj_doctor',
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
		$table_meta = \Meditaj\DB::get_table( 'doctors_meta' );
		$inserted   = $wpdb->insert(
			$table_meta,
			array(
				'post_id'             => $post_id,
				'user_id'             => $user_id,
				'provider_type'       => $provider_type,
				'bmdc_license_no'     => $license,
				'degree'              => $degree,
				'designation'         => $designation,
				'consultation_fee'    => $consultation_fee,
				'instant_call_fee'    => $instant_call_fee,
				'experience_years'    => $experience,
				'is_online'           => 0,
				'verification_status' => 'pending', // Starts pending.
				'bank_account_name'   => $bank_acc_name,
				'bank_account_no'     => $bank_acc_no,
				'mobile_banking_type' => $mobile_wallet,
				'mobile_banking_no'   => $mobile_wallet_no,
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
				'%f',
				'%f',
				'%d',
				'%d',
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
			return new \WP_Error( 'db_error', __( 'Failed to save doctor metadata in database.', 'meditaj' ) );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Listen and process GET actions in administration panel.
	 */
	public static function process_admin_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['page'] ) || 'meditaj-doctors' !== $_GET['page'] ) {
			return;
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
		$id     = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		if ( ! $action || ! $id ) {
			return;
		}

		global $wpdb;
		$table_meta = \Meditaj\DB::get_table( 'doctors_meta' );

		// Retrieve records.
		$doctor = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_meta WHERE id = %d", $id ) );
		if ( ! $doctor ) {
			return;
		}

		switch ( $action ) {
			case 'approve':
				check_admin_referer( 'meditaj_approve_doctor_' . $id );
				$wpdb->update( $table_meta, array( 'verification_status' => 'approved' ), array( 'id' => $id ) );
				wp_safe_redirect( admin_url( 'admin.php?page=meditaj-doctors&message=approved' ) );
				exit;

			case 'reject':
				check_admin_referer( 'meditaj_reject_doctor_' . $id );
				$wpdb->update( $table_meta, array( 'verification_status' => 'rejected' ), array( 'id' => $id ) );
				wp_safe_redirect( admin_url( 'admin.php?page=meditaj-doctors&message=rejected' ) );
				exit;

			case 'delete':
				check_admin_referer( 'meditaj_delete_doctor_' . $id );
				// Clean CPT post.
				wp_delete_post( $doctor->post_id, true );
				// Clean user account.
				require_once ABSPATH . 'wp-admin/includes/user.php';
				wp_delete_user( $doctor->user_id );
				// Clean SQL Meta row.
				$wpdb->delete( $table_meta, array( 'id' => $id ) );

				wp_safe_redirect( admin_url( 'admin.php?page=meditaj-doctors&message=deleted' ) );
				exit;
		}
	}

	/**
	 * Register Custom Meta Box on Doctors CPT edit screen.
	 */
	public static function add_doctor_meta_boxes() {
		add_meta_box(
			'meditaj_doctor_details',
			__( 'Meditaj Doctor Details', 'meditaj' ),
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
		$table_meta = \Meditaj\DB::get_table( 'doctors_meta' );
		$meta       = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_meta WHERE post_id = %d", $post->ID ) );

		wp_nonce_field( 'meditaj_save_doctors_meta', 'meditaj_doctors_meta_nonce' );

		// Set default values.
		$provider_type       = $meta ? $meta->provider_type : 'doctor';
		$bmdc_license_no     = $meta ? $meta->bmdc_license_no : '';
		$degree              = $meta ? $meta->degree : '';
		$designation         = $meta ? $meta->designation : '';
		$consultation_fee    = $meta ? $meta->consultation_fee : 0.00;
		$instant_call_fee    = $meta ? $meta->instant_call_fee : 0.00;
		$experience_years    = $meta ? $meta->experience_years : 0;
		$is_online           = $meta ? $meta->is_online : 0;
		$verification_status = $meta ? $meta->verification_status : 'pending';
		$bank_account_name   = $meta ? $meta->bank_account_name : '';
		$bank_account_no     = $meta ? $meta->bank_account_no : '';
		$mobile_banking_type = $meta ? $meta->mobile_banking_type : 'bkash';
		$mobile_banking_no   = $meta ? $meta->mobile_banking_no : '';
		?>
		<style>
			.meditaj-mb-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px; }
			.meditaj-mb-field { display: flex; flex-direction: column; margin-bottom: 10px; }
			.meditaj-mb-field label { font-weight: 600; margin-bottom: 5px; }
			.meditaj-mb-field input, .meditaj-mb-field select { padding: 6px; border: 1px solid #ccc; border-radius: 4px; }
		</style>
		<div class="meditaj-mb-grid">
			<div class="meditaj-mb-field">
				<label for="mb_provider_type"><?php esc_html_e( 'Provider Type', 'meditaj' ); ?></label>
				<select name="mb_provider_type" id="mb_provider_type">
					<option value="doctor" <?php selected( $provider_type, 'doctor' ); ?>><?php esc_html_e( 'Doctor', 'meditaj' ); ?></option>
					<option value="medical_professional" <?php selected( $provider_type, 'medical_professional' ); ?>><?php esc_html_e( 'Medical Professional', 'meditaj' ); ?></option>
				</select>
			</div>
			<div class="meditaj-mb-field">
				<label for="mb_bmdc_license_no"><?php esc_html_e( 'BMDC Registration No', 'meditaj' ); ?></label>
				<input type="text" name="mb_bmdc_license_no" id="mb_bmdc_license_no" value="<?php echo esc_attr( $bmdc_license_no ); ?>">
			</div>
			<div class="meditaj-mb-field">
				<label for="mb_degree"><?php esc_html_e( 'Degrees / Qualifications', 'meditaj' ); ?></label>
				<input type="text" name="mb_degree" id="mb_degree" value="<?php echo esc_attr( $degree ); ?>">
			</div>
			<div class="meditaj-mb-field">
				<label for="mb_designation"><?php esc_html_e( 'Designation', 'meditaj' ); ?></label>
				<input type="text" name="mb_designation" id="mb_designation" value="<?php echo esc_attr( $designation ); ?>">
			</div>
			<div class="meditaj-mb-field">
				<label for="mb_experience_years"><?php esc_html_e( 'Years of Experience', 'meditaj' ); ?></label>
				<input type="number" name="mb_experience_years" id="mb_experience_years" value="<?php echo esc_attr( $experience_years ); ?>">
			</div>
			<div class="meditaj-mb-field">
				<label for="mb_verification_status"><?php esc_html_e( 'Verification Status', 'meditaj' ); ?></label>
				<select name="mb_verification_status" id="mb_verification_status">
					<option value="pending" <?php selected( $verification_status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'meditaj' ); ?></option>
					<option value="approved" <?php selected( $verification_status, 'approved' ); ?>><?php esc_html_e( 'Approved', 'meditaj' ); ?></option>
					<option value="rejected" <?php selected( $verification_status, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'meditaj' ); ?></option>
				</select>
			</div>
			<div class="meditaj-mb-field">
				<label for="mb_consultation_fee"><?php esc_html_e( 'Scheduled Consultation Fee (BDT)', 'meditaj' ); ?></label>
				<input type="number" name="mb_consultation_fee" id="mb_consultation_fee" step="0.01" value="<?php echo esc_attr( $consultation_fee ); ?>">
			</div>
			<div class="meditaj-mb-field">
				<label for="mb_instant_call_fee"><?php esc_html_e( 'Instant Call Fee (BDT)', 'meditaj' ); ?></label>
				<input type="number" name="mb_instant_call_fee" id="mb_instant_call_fee" step="0.01" value="<?php echo esc_attr( $instant_call_fee ); ?>">
			</div>
			<div class="meditaj-mb-field">
				<label for="mb_is_online"><?php esc_html_e( 'Availability Indicator', 'meditaj' ); ?></label>
				<select name="mb_is_online" id="mb_is_online">
					<option value="0" <?php selected( $is_online, 0 ); ?>><?php esc_html_e( 'Offline', 'meditaj' ); ?></option>
					<option value="1" <?php selected( $is_online, 1 ); ?>><?php esc_html_e( 'Online Now', 'meditaj' ); ?></option>
				</select>
			</div>
			<div class="meditaj-mb-field">
				<label for="mb_bank_account_name"><?php esc_html_e( 'Bank Account Name', 'meditaj' ); ?></label>
				<input type="text" name="mb_bank_account_name" id="mb_bank_account_name" value="<?php echo esc_attr( $bank_account_name ); ?>">
			</div>
			<div class="meditaj-mb-field">
				<label for="mb_bank_account_no"><?php esc_html_e( 'Bank Account Number', 'meditaj' ); ?></label>
				<input type="text" name="mb_bank_account_no" id="mb_bank_account_no" value="<?php echo esc_attr( $bank_account_no ); ?>">
			</div>
			<div class="meditaj-mb-field">
				<label for="mb_mobile_banking_type"><?php esc_html_e( 'Mobile Payout Wallet', 'meditaj' ); ?></label>
				<select name="mb_mobile_banking_type" id="mb_mobile_banking_type">
					<option value="bkash" <?php selected( $mobile_banking_type, 'bkash' ); ?>><?php esc_html_e( 'bKash', 'meditaj' ); ?></option>
					<option value="nagad" <?php selected( $mobile_banking_type, 'nagad' ); ?>><?php esc_html_e( 'Nagad', 'meditaj' ); ?></option>
					<option value="rocket" <?php selected( $mobile_banking_type, 'rocket' ); ?>><?php esc_html_e( 'Rocket', 'meditaj' ); ?></option>
				</select>
			</div>
			<div class="meditaj-mb-field">
				<label for="mb_mobile_banking_no"><?php esc_html_e( 'Mobile Wallet Number', 'meditaj' ); ?></label>
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
		if ( ! isset( $_POST['meditaj_doctors_meta_nonce'] ) || ! wp_verify_nonce( $_POST['meditaj_doctors_meta_nonce'], 'meditaj_save_doctors_meta' ) ) {
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
		$table_meta = \Meditaj\DB::get_table( 'doctors_meta' );

		// Gather inputs.
		$provider_type       = sanitize_text_field( $_POST['mb_provider_type'] );
		$bmdc_license_no     = sanitize_text_field( $_POST['mb_bmdc_license_no'] );
		$degree              = sanitize_text_field( $_POST['mb_degree'] );
		$designation         = sanitize_text_field( $_POST['mb_designation'] );
		$experience_years    = intval( $_POST['mb_experience_years'] );
		$verification_status = sanitize_text_field( $_POST['mb_verification_status'] );
		$consultation_fee    = floatval( $_POST['mb_consultation_fee'] );
		$instant_call_fee    = floatval( $_POST['mb_instant_call_fee'] );
		$is_online           = intval( $_POST['mb_is_online'] );
		$bank_account_name   = sanitize_text_field( $_POST['mb_bank_account_name'] );
		$bank_account_no     = sanitize_text_field( $_POST['mb_bank_account_no'] );
		$mobile_banking_type = sanitize_text_field( $_POST['mb_mobile_banking_type'] );
		$mobile_banking_no   = sanitize_text_field( $_POST['mb_mobile_banking_no'] );

		// Find author ID.
		$post   = get_post( $post_id );
		$author = $post ? $post->post_author : 0;

		$meta_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table_meta WHERE post_id = %d", $post_id ) );

		$data = array(
			'provider_type'       => $provider_type,
			'bmdc_license_no'     => $bmdc_license_no,
			'degree'              => $degree,
			'designation'         => $designation,
			'experience_years'    => $experience_years,
			'verification_status' => $verification_status,
			'consultation_fee'    => $consultation_fee,
			'instant_call_fee'    => $instant_call_fee,
			'is_online'           => $is_online,
			'bank_account_name'   => $bank_account_name,
			'bank_account_no'     => $bank_account_no,
			'mobile_banking_type' => $mobile_banking_type,
			'mobile_banking_no'   => $mobile_banking_no,
		);

		if ( $meta_id ) {
			$wpdb->update( $table_meta, $data, array( 'id' => $meta_id ) );
		} else {
			$data['post_id']           = $post_id;
			$data['user_id']           = $author;
			$data['certificate_files'] = wp_json_encode( array() );
			$data['created_at']        = current_time( 'mysql' );
			$wpdb->insert( $table_meta, $data );
		}
	}
}

/**
 * WP_List_Table subclass for displaying doctors ledger.
 */
class Meditaj_Doctors_List_Table extends \WP_List_Table {
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
			return sprintf( '<img src="%s" class="meditaj-doctor-photo" alt="%s">', esc_url( $thumbnail ), esc_attr( $item->post_title ) );
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

		return sprintf( '<div class="meditaj-doctor-photo-placeholder">%s</div>', esc_html( strtoupper( $initials ) ) );
	}

	/**
	 * Render name along with Edit, Delete, and Approval row actions.
	 */
	public function column_name( $item ) {
		$actions = array();

		// Edit link.
		$actions['edit'] = sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $item->post_id ) ), __( 'Edit', 'meditaj' ) );

		// Approval / Rejection buttons.
		if ( 'approved' !== $item->verification_status ) {
			$approve_url = wp_nonce_url( admin_url( 'admin.php?page=meditaj-doctors&action=approve&id=' . $item->id ), 'meditaj_approve_doctor_' . $item->id );
			$actions['approve'] = sprintf( '<a href="%s" style="color:green;">%s</a>', esc_url( $approve_url ), __( 'Approve', 'meditaj' ) );
		}
		if ( 'rejected' !== $item->verification_status ) {
			$reject_url = wp_nonce_url( admin_url( 'admin.php?page=meditaj-doctors&action=reject&id=' . $item->id ), 'meditaj_reject_doctor_' . $item->id );
			$actions['reject'] = sprintf( '<a href="%s" style="color:orange;">%s</a>', esc_url( $reject_url ), __( 'Reject', 'meditaj' ) );
		}

		// Delete.
		$delete_url = wp_nonce_url( admin_url( 'admin.php?page=meditaj-doctors&action=delete&id=' . $item->id ), 'meditaj_delete_doctor_' . $item->id );
		$actions['delete'] = sprintf( '<a href="%s" style="color:red;" onclick="return confirm(\'%s\')">%s</a>', esc_url( $delete_url ), esc_attr__( 'Are you sure you want to delete this doctor? This will remove the WordPress user account and profile post too.', 'meditaj' ), __( 'Delete', 'meditaj' ) );

		return sprintf( '<strong><a href="%1$s">%2$s</a></strong> %3$s', esc_url( get_edit_post_link( $item->post_id ) ), esc_html( $item->post_title ), $this->row_actions( $actions ) );
	}

	/**
	 * Get CPT Taxonomy terms list.
	 */
	public function column_specialty( $item ) {
		$terms = wp_get_post_terms( $item->post_id, 'specialty' );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return __( 'Unassigned', 'meditaj' );
		}
		$names = wp_list_pluck( $terms, 'name' );
		return esc_html( implode( ', ', $names ) );
	}

	/**
	 * Render custom badge for verification status.
	 */
	public function column_verification_status( $item ) {
		$status = esc_attr( $item->verification_status );
		return sprintf( '<span class="meditaj-status-badge status-%s">%s</span>', $status, ucfirst( $status ) );
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
			'photo'               => __( 'Photo', 'meditaj' ),
			'name'                => __( 'Name', 'meditaj' ),
			'specialty'           => __( 'Specialty', 'meditaj' ),
			'verification_status' => __( 'Verification Status', 'meditaj' ),
			'consultation_fee'    => __( 'Consultation Fee', 'meditaj' ),
		);
	}

	/**
	 * Set up, query and populate the items array.
	 */
	public function prepare_items() {
		global $wpdb;
		$table_meta = \Meditaj\DB::get_table( 'doctors_meta' );

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
