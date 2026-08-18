<?php
namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers and processes frontend shortcodes.
 */
class Shortcodes {
	/**
	 * Errors collected during registration.
	 *
	 * @var array
	 */
	private static $errors = array();

	/**
	 * Success flag.
	 *
	 * @var bool
	 */
	private static $success = false;

	/**
	 * Initialize actions.
	 */
	public static function init() {
		add_shortcode( 'eg_care_doctor_registration', array( __CLASS__, 'render_registration_shortcode' ) );
		add_shortcode( 'eg_care_booking_flow', array( __CLASS__, 'render_booking_shortcode' ) );
		add_shortcode( 'eg_care_doctor_dashboard', array( __CLASS__, 'render_doctor_dashboard_shortcode' ) );
		add_shortcode( 'eg_care_patient_dashboard', array( __CLASS__, 'render_patient_dashboard_shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_gateway_return' ), 1 );
		add_action( 'template_redirect', array( __CLASS__, 'process_registration_form' ) );
	}

	/**
	 * Turn the gateway's POST back into a GET before anything is rendered.
	 *
	 * SSLCommerz returns the customer by POSTing to the success URL. A browser
	 * will not attach a SameSite=Lax auth cookie to a cross-site POST, so that
	 * request arrives logged out, and reloading it re-submits the form. Bouncing
	 * it through a GET fixes both.
	 *
	 * This has to run on template_redirect: by the time a shortcode renders, the
	 * theme has already sent output, the Location header is ignored, and the exit
	 * that follows it truncates the page into a blank screen.
	 */
	public static function handle_gateway_return() {
		if ( ! isset( $_GET['eg_care_payment'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'POST' !== $method ) {
			return;
		}

		$host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( '' === $host || '' === $uri ) {
			return;
		}

		// wp_safe_redirect() refuses any host other than this site's, so a forged
		// Host header cannot send the customer somewhere else. 303 tells the browser
		// to follow up with a GET.
		wp_safe_redirect( esc_url_raw( set_url_scheme( '//' . $host . $uri ) ), 303 );
		exit;
	}

	/**
	 * Process form submission.
	 */
	public static function process_registration_form() {
		if ( ! isset( $_POST['eg_care_register_submit'] ) ) {
			return;
		}

		// Verify Nonce.
		if ( ! isset( $_POST['eg_care_register_nonce'] ) || ! wp_verify_nonce( $_POST['eg_care_register_nonce'], 'eg_care_register' ) ) {
			self::$errors[] = __( 'Security check failed. Please try again.', 'eg-care' );
			return;
		}

		// Sanitize inputs.
		$username    = sanitize_user( $_POST['reg_username'] );
		$email       = sanitize_email( $_POST['reg_email'] );
		$password    = $_POST['reg_password'];
		$name        = sanitize_text_field( $_POST['reg_first_name'] );
		$bio         = wp_kses_post( $_POST['reg_bio'] );
		$provider    = sanitize_text_field( $_POST['reg_provider_type'] ); // 'doctor'
		$specialty   = intval( $_POST['reg_specialty_id'] );
		$license     = sanitize_text_field( $_POST['reg_license'] );
		$expiry      = sanitize_text_field( $_POST['reg_bmdc_expiry'] );
		$degree      = sanitize_text_field( $_POST['reg_degree'] );
		$designation = sanitize_text_field( $_POST['reg_designation'] ); // defaults to Consultant
		$experience  = intval( $_POST['reg_experience'] );
		$fee         = floatval( $_POST['reg_fee'] );
		$instant_fee = floatval( $_POST['reg_instant_fee'] ); // defaults to 0
		$mobile      = sanitize_text_field( $_POST['reg_mobile'] );
		$nid         = sanitize_text_field( $_POST['reg_nid'] );
		$nationality = sanitize_text_field( $_POST['reg_nationality'] );
		$organization= sanitize_text_field( $_POST['reg_organization'] );
		$f_days      = intval( $_POST['reg_follow_up_days'] );
		$f_cost      = floatval( $_POST['reg_follow_up_cost'] );
		$acc_type    = sanitize_text_field( $_POST['reg_acc_type'] ); // 'bank' or 'mobile'

		$bank_name     = null;
		$bank_branch   = null;
		$bank_no       = null;
		$bank_acc_name = null;
		$bank_routing  = null;
		$m_wallet      = null;
		$m_no          = null;

		if ( 'bank' === $acc_type ) {
			$bank_name     = sanitize_text_field( $_POST['reg_bank_name'] );
			$bank_branch   = sanitize_text_field( $_POST['reg_bank_branch'] );
			$bank_no       = sanitize_text_field( $_POST['reg_bank_no'] );
			$bank_acc_name = sanitize_text_field( $_POST['reg_bank_acc_name'] );
			$bank_routing  = sanitize_text_field( $_POST['reg_bank_routing'] );
		} else {
			$m_wallet      = sanitize_text_field( $_POST['reg_m_wallet'] );
			$m_no          = sanitize_text_field( $_POST['reg_m_no'] );
		}

		// Validate.
		if ( empty( $username ) || empty( $email ) || empty( $password ) || empty( $name ) || empty( $license ) || empty( $expiry ) || empty( $specialty ) || empty( $mobile ) || empty( $nid ) || empty( $nationality ) || empty( $degree ) ) {
			self::$errors[] = __( 'All fields marked with an asterisk (*) are required.', 'eg-care' );
			return;
		}

		if ( username_exists( $username ) ) {
			self::$errors[] = __( 'Username is already registered.', 'eg-care' );
			return;
		}

		if ( email_exists( $email ) ) {
			self::$errors[] = __( 'Email address is already registered.', 'eg-care' );
			return;
		}

		// Validate file uploads first.
		if ( empty( $_FILES['reg_certificate']['name'] ) ) {
			self::$errors[] = __( 'You must upload a valid copy of your BMDC/Medical Certificate.', 'eg-care' );
			return;
		}

		// Pre-flight file upload error validations and size limits (max 2MB).
		$max_file_size = 2 * 1024 * 1024; // 2MB

		if ( isset( $_FILES['reg_certificate'] ) ) {
			if ( $_FILES['reg_certificate']['error'] !== UPLOAD_ERR_OK ) {
				self::$errors[] = __( 'Error uploading certificate file. Please verify the file is not corrupted.', 'eg-care' );
				return;
			}
			if ( $_FILES['reg_certificate']['size'] > $max_file_size ) {
				self::$errors[] = __( 'Certificate file size exceeds the 2MB limit.', 'eg-care' );
				return;
			}
		}

		if ( isset( $_FILES['reg_photo'] ) && ! empty( $_FILES['reg_photo']['name'] ) ) {
			if ( $_FILES['reg_photo']['error'] !== UPLOAD_ERR_OK ) {
				self::$errors[] = __( 'Error uploading profile photo. Please verify the file is not corrupted.', 'eg-care' );
				return;
			}
			if ( $_FILES['reg_photo']['size'] > $max_file_size ) {
				self::$errors[] = __( 'Profile photo file size exceeds the 2MB limit.', 'eg-care' );
				return;
			}
		}

		// Check file extension.
		$cert_file = $_FILES['reg_certificate'];
		$cert_ext  = strtolower( pathinfo( $cert_file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $cert_ext, array( 'pdf', 'jpg', 'jpeg', 'png' ), true ) ) {
			self::$errors[] = __( 'Invalid certificate format. Only PDF, JPG, JPEG, and PNG are allowed.', 'eg-care' );
			return;
		}

		// Create user.
		$user_id = wp_create_user( $username, $password, $email );
		if ( is_wp_error( $user_id ) ) {
			self::$errors[] = $user_id->get_error_message();
			return;
		}

		wp_update_user(
			array(
				'ID'           => $user_id,
				'display_name' => $name,
				'role'         => 'eg_care_doctor',
			)
		);

		// Create CPT Post.
		$post_id = wp_insert_post(
			array(
				'post_title'   => $name,
				'post_content' => $bio,
				'post_status'  => 'pending', // Pending verification.
				'post_type'    => 'doctors',
				'post_author'  => $user_id,
			)
		);

		if ( is_wp_error( $post_id ) ) {
			global $wpdb;
			$wpdb->delete( $wpdb->users, array( 'ID' => $user_id ), array( '%d' ) );
			$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $user_id ), array( '%d' ) );
			self::$errors[] = $post_id->get_error_message();
			return;
		}

		// Handle Media Uploads.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// 1. Profile photo.
		if ( ! empty( $_FILES['reg_photo']['name'] ) ) {
			$photo_id = media_handle_upload( 'reg_photo', $post_id );
			if ( ! is_wp_error( $photo_id ) ) {
				set_post_thumbnail( $post_id, $photo_id );
			}
		}

		// 2. Certificate file.
		$cert_id = \EGCare\SecureUploads::handle_upload( 'reg_certificate', $post_id );
		if ( is_wp_error( $cert_id ) ) {
			// Rollback.
			wp_delete_post( $post_id, true );
			global $wpdb;
			$wpdb->delete( $wpdb->users, array( 'ID' => $user_id ), array( '%d' ) );
			$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $user_id ), array( '%d' ) );
			self::$errors[] = __( 'Failed to save certificate attachment.', 'eg-care' );
			return;
		}

		// Associate specialty taxonomy term.
		wp_set_object_terms( $post_id, $specialty, 'specialty' );

		// Insert metadata row.
		global $wpdb;
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );

		$inserted = $wpdb->insert(
			$table_meta,
			array(
				'post_id'             => $post_id,
				'user_id'             => $user_id,
				'provider_type'       => $provider,
				'bmdc_license_no'     => $license,
				'bmdc_expiry_date'    => $expiry,
				'degree'              => $degree,
				'designation'         => $designation,
				'consultation_fee'    => $fee,
				'instant_call_fee'    => $instant_fee,
				'experience_years'    => $experience,
				'is_online'           => 0,
				'verification_status' => 'pending',
				'mobile'              => $mobile,
				'nid'                 => $nid,
				'nationality'         => $nationality,
				'organization'        => $organization,
				'follow_up_days'      => $f_days,
				'follow_up_cost'      => $f_cost,
				'bank_account_name'   => $bank_acc_name,
				'bank_account_no'     => $bank_no,
				'bank_branch_name'    => $bank_branch,
				'bank_routing_number' => $bank_routing,
				'mobile_banking_type' => $m_wallet,
				'mobile_banking_no'   => $m_no,
				'certificate_files'   => wp_json_encode( array( $cert_id ) ),
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
			// Rollback.
			wp_delete_attachment( $cert_id, true );
			wp_delete_post( $post_id, true );
			global $wpdb;
			$wpdb->delete( $wpdb->users, array( 'ID' => $user_id ), array( '%d' ) );
			$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $user_id ), array( '%d' ) );
			self::$errors[] = __( 'Database write error. Registration failed.', 'eg-care' );
			return;
		}

		// Success! Redirect to prevent duplicate posts.
		wp_safe_redirect( add_query_arg( 'registration', 'success', get_permalink() ) );
		exit;
	}

	/**
	 * Render the registration shortcode content.
	 */
	public static function render_registration_shortcode() {
		// Output buffering.
		ob_start();

		// Check for success.
		if ( isset( $_GET['registration'] ) && 'success' === $_GET['registration'] ) {
			self::$success = true;
		}

		$specialties = get_terms(
			array(
				'taxonomy'   => 'specialty',
				'hide_empty' => false,
			)
		);

		// Include the template.
		include EG_CARE_PATH . 'templates/registration-form.php';

		return ob_get_clean();
	}

	/**
	 * Get errors array.
	 *
	 * @return array
	 */
	public static function get_errors() {
		return self::$errors;
	}

	/**
	 * Check success flag.
	 *
	 * @return bool
	 */
	public static function is_success() {
		return self::$success;
	}

	/**
	 * Render the booking flow shortcode content.
	 */
	public static function render_booking_shortcode() {
		wp_enqueue_script( 'agora-rtc-sdk', 'https://download.agora.io/sdk/release/AgoraRTC_N-4.20.0.js', array(), '4.20.0', true );
		wp_enqueue_script( 'eg-care-video-call-js', EG_CARE_URL . 'assets/js/video-call.js', array( 'agora-rtc-sdk' ), eg_care_asset_version( 'assets/js/video-call.js' ), true );
		wp_enqueue_script( 'eg-care-booking-js', EG_CARE_URL . 'assets/js/booking.js', array(), eg_care_asset_version( 'assets/js/booking.js' ), true );

		wp_localize_script(
			'eg-care-booking-js',
			'egCareSettings',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'eg-care/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'userType' => 'patient',
			)
		);

		ob_start();
		include EG_CARE_PATH . 'templates/booking-flow.php';
		return ob_get_clean();
	}

	/**
	 * Render the doctor dashboard shortcode content.
	 */
	public static function render_doctor_dashboard_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<div class="eg-care-alert">' . esc_html__( 'You must be logged in to view the doctor dashboard.', 'eg-care' ) . ' <a href="' . esc_url( wp_login_url() ) . '">' . esc_html__( 'Log In Here', 'eg-care' ) . '</a></div>';
		}

		// Verify this user maps to a registered doctor profile
		global $wpdb;
		$user_id = get_current_user_id();
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );
		$doctor = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_meta WHERE user_id = %d", $user_id ) );

		if ( ! $doctor ) {
			return '<div class="eg-care-alert">' . esc_html__( 'You do not have a doctor profile registered on this platform.', 'eg-care' ) . '</div>';
		}

		wp_enqueue_script( 'agora-rtc-sdk', 'https://download.agora.io/sdk/release/AgoraRTC_N-4.20.0.js', array(), '4.20.0', true );
		wp_enqueue_script( 'eg-care-video-call-js', EG_CARE_URL . 'assets/js/video-call.js', array( 'agora-rtc-sdk' ), eg_care_asset_version( 'assets/js/video-call.js' ), true );
		wp_enqueue_script( 'eg-care-doctor-dashboard-js', EG_CARE_URL . 'assets/js/doctor-dashboard.js', array(), eg_care_asset_version( 'assets/js/doctor-dashboard.js' ), true );

		// Localize parameters for AJAX requests.
		wp_localize_script(
			'eg-care-doctor-dashboard-js',
			'egCareSettings',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'eg-care/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'userType' => 'doctor',
				'doctor'  => array(
					'id'               => intval( $doctor->post_id ),
					'consultation_fee' => floatval( $doctor->consultation_fee ),
					'instant_call_fee' => floatval( $doctor->instant_call_fee ),
					'is_online'        => (bool) $doctor->is_online,
					'bio'              => get_post( $doctor->post_id )->post_content,
				),
			)
		);

		ob_start();
		include EG_CARE_PATH . 'templates/doctor-dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Render Patient Dashboard shortcode.
	 */
	public static function render_patient_dashboard_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<div class="eg-care-alert">' . esc_html__( 'You must be logged in to view your patient dashboard.', 'eg-care' ) . ' <a href="' . esc_url( wp_login_url() ) . '">' . esc_html__( 'Log In Here', 'eg-care' ) . '</a></div>';
		}

		wp_enqueue_script( 'agora-rtc-sdk', 'https://download.agora.io/sdk/release/AgoraRTC_N-4.20.0.js', array(), '4.20.0', true );
		wp_enqueue_script( 'eg-care-video-call-js', EG_CARE_URL . 'assets/js/video-call.js', array( 'agora-rtc-sdk' ), eg_care_asset_version( 'assets/js/video-call.js' ), true );

		// Localize parameters for AJAX requests.
		wp_localize_script(
			'eg-care-video-call-js',
			'egCareSettings',
			array(
				'restUrl'  => esc_url_raw( rest_url( 'eg-care/v1/' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'userType' => 'patient',
			)
		);

		ob_start();
		include EG_CARE_PATH . 'templates/patient-dashboard.php';
		return ob_get_clean();
	}
}
