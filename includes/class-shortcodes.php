<?php
namespace Meditaj;

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
		add_shortcode( 'meditaj_doctor_registration', array( __CLASS__, 'render_registration_shortcode' ) );
		add_shortcode( 'meditaj_booking_flow', array( __CLASS__, 'render_booking_shortcode' ) );
		add_action( 'template_redirect', array( __CLASS__, 'process_registration_form' ) );
	}

	/**
	 * Process form submission.
	 */
	public static function process_registration_form() {
		if ( ! isset( $_POST['meditaj_register_submit'] ) ) {
			return;
		}

		// Verify Nonce.
		if ( ! isset( $_POST['meditaj_register_nonce'] ) || ! wp_verify_nonce( $_POST['meditaj_register_nonce'], 'meditaj_register' ) ) {
			self::$errors[] = __( 'Security check failed. Please try again.', 'meditaj' );
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
			self::$errors[] = __( 'All fields marked with an asterisk (*) are required.', 'meditaj' );
			return;
		}

		if ( username_exists( $username ) ) {
			self::$errors[] = __( 'Username is already registered.', 'meditaj' );
			return;
		}

		if ( email_exists( $email ) ) {
			self::$errors[] = __( 'Email address is already registered.', 'meditaj' );
			return;
		}

		// Validate file uploads first.
		if ( empty( $_FILES['reg_certificate']['name'] ) ) {
			self::$errors[] = __( 'You must upload a valid copy of your BMDC/Medical Certificate.', 'meditaj' );
			return;
		}

		// Check file extension.
		$cert_file = $_FILES['reg_certificate'];
		$cert_ext  = strtolower( pathinfo( $cert_file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $cert_ext, array( 'pdf', 'jpg', 'jpeg', 'png' ), true ) ) {
			self::$errors[] = __( 'Invalid certificate format. Only PDF, JPG, JPEG, and PNG are allowed.', 'meditaj' );
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
				'role'         => 'meditaj_doctor',
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
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
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
		$cert_id = media_handle_upload( 'reg_certificate', $post_id );
		if ( is_wp_error( $cert_id ) ) {
			// Rollback.
			wp_delete_post( $post_id, true );
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
			self::$errors[] = __( 'Failed to save certificate attachment.', 'meditaj' );
			return;
		}

		// Associate specialty taxonomy term.
		wp_set_object_terms( $post_id, $specialty, 'specialty' );

		// Insert metadata row.
		global $wpdb;
		$table_meta = \Meditaj\DB::get_table( 'doctors_meta' );

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
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
			self::$errors[] = __( 'Database write error. Registration failed.', 'meditaj' );
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
		include MEDITAJ_PATH . 'templates/registration-form.php';

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
		wp_enqueue_script( 'meditaj-booking-js', MEDITAJ_URL . 'assets/js/booking.js', array(), MEDITAJ_VERSION, true );

		// Localize parameters for AJAX requests.
		wp_localize_script(
			'meditaj-booking-js',
			'meditajSettings',
			array(
				'restUrl' => esc_url_raw( rest_url( 'meditaj/v1/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);

		ob_start();
		include MEDITAJ_PATH . 'templates/booking-flow.php';
		return ob_get_clean();
	}
}
