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
	 * Handle carrying the shared JavaScript settings object.
	 *
	 * Every EG Care script - and the inline handlers in the templates - read
	 * egCareSettings. Hanging that off whichever file happened to be enqueued
	 * on a given screen made the dependency invisible, so it now has a handle
	 * of its own that the others can declare. The handle has no source; it
	 * exists purely to carry the localised data.
	 */
	const SETTINGS_HANDLE = 'eg-care-settings';

	/**
	 * The script that recovers from a stale nonce.
	 *
	 * It wraps window.fetch, so it has to run before any script that calls it.
	 * enqueue_settings() below is the first thing every render method does, so
	 * this handle enters the queue ahead of booking.js and the rest, and it
	 * depends on SETTINGS_HANDLE for the object it reads. WordPress prints in
	 * queue order once dependencies are resolved, which is why none of the
	 * other enqueues has to name it.
	 */
	const GUARD_HANDLE = 'eg-care-nonce-guard';

	/**
	 * Values handed to the browser this request.
	 *
	 * @var array
	 */
	private static $settings = array();

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
		add_shortcode( 'eg_care_instant_doctor', array( __CLASS__, 'render_instant_doctor_shortcode' ) );
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

		// The form, and so the script that validates it, is only on the page
		// before a successful submission.
		if ( ! self::$success ) {
			wp_enqueue_script( 'eg-care-registration-form-js', EG_CARE_URL . 'assets/js/registration-form.js', array(), eg_care_asset_version( 'assets/js/registration-form.js' ), true );

			wp_localize_script(
				'eg-care-registration-form-js',
				'egCareRegistration',
				array(
					'i18n' => array(
						'noFileChosen'          => __( 'No file chosen', 'eg-care' ),
						'required'              => __( 'is required.', 'eg-care' ),
						'requiredBank'          => __( 'is required for bank payout.', 'eg-care' ),
						'requiredMobile'        => __( 'is required for mobile payout.', 'eg-care' ),
						'photoTooLarge'         => __( 'Profile photo file size exceeds 2MB limit.', 'eg-care' ),
						'certificateTooLarge'   => __( 'BMDC/Medical Certificate file size exceeds 2MB limit.', 'eg-care' ),
						'certificateRequired'   => __( 'BMDC/Medical Certificate is required.', 'eg-care' ),
						'validationHeading'     => __( 'Form Validation Errors:', 'eg-care' ),
					),
				)
			);
		}

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
	 * Publish the shared settings object and let scripts depend on it.
	 *
	 * @param array $extra Values specific to the screen being rendered.
	 */
	private static function enqueue_settings( array $extra = array() ) {
		if ( ! wp_script_is( self::SETTINGS_HANDLE, 'registered' ) ) {
			wp_register_script( self::SETTINGS_HANDLE, false, array(), EG_CARE_VERSION, false );
		}

		wp_enqueue_script( self::SETTINGS_HANDLE );

		$defaults = array(
			'restUrl'          => esc_url_raw( rest_url( 'eg-care/v1/' ) ),
			'nonce'            => wp_create_nonce( 'wp_rest' ),
			// A printed nonce goes stale - cached HTML, a page left open past
			// the nonce lifetime, a login or logout in another tab. These let
			// assets/js/nonce-guard.js fetch a replacement and retry instead of
			// leaving the visitor with a booking that silently will not submit.
			'ajaxUrl'          => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
			'nonceAction'      => \EGCare\Nonce::ACTION,
			'loggedIn'         => is_user_logged_in(),
			// The checkout summary prints this before the booking request is
			// sent; the server recalculates it and stays authoritative.
			'serviceChargePct' => \EGCare\Fees::service_charge_percentage(),
			// The booking screen turns a file away before uploading it. These
			// come from the endpoint's own constants so the message a patient
			// reads is the rule the server will actually apply.
			'maxReports'       => \EGCare\RestControllerBooking::MAX_REPORTS,
			'maxReportBytes'   => \EGCare\RestControllerBooking::MAX_REPORT_BYTES,
		);

		// One page can hold more than one EG Care shortcode. Whatever the first
		// one declared stays put; later ones only fill in the gaps.
		self::$settings = self::$settings + $extra + $defaults;

		// wp_localize_script() appends to whatever is already on the handle, so
		// clear it first and print a single definition.
		wp_script_add_data( self::SETTINGS_HANDLE, 'data', '' );
		wp_localize_script( self::SETTINGS_HANDLE, 'egCareSettings', self::$settings );

		// Every render method calls this before enqueueing its own scripts, so
		// the guard is printed first and window.fetch is already wrapped by the
		// time booking.js or either dashboard runs.
		wp_enqueue_script(
			self::GUARD_HANDLE,
			EG_CARE_URL . 'assets/js/nonce-guard.js',
			array( self::SETTINGS_HANDLE ),
			eg_care_asset_version( 'assets/js/nonce-guard.js' ),
			true
		);
	}

	/**
	 * Render the booking flow shortcode content.
	 */
	public static function render_booking_shortcode( $atts = array() ) {
		$atts = shortcode_atts( array( 'doctor' => 0 ), (array) $atts, 'eg_care_booking_flow' );

		// A doctor's own page renders this same flow opened on that doctor,
		// which is why the id is an attribute rather than a second shortcode.
		$eg_care_doctor_id = self::approved_doctor_id( $atts['doctor'] );

		self::enqueue_settings( array( 'userType' => 'patient' ) );

		wp_enqueue_script( 'agora-rtc-sdk', 'https://download.agora.io/sdk/release/AgoraRTC_N-4.20.0.js', array(), '4.20.0', true );
		wp_enqueue_script( 'eg-care-video-call-js', EG_CARE_URL . 'assets/js/video-call.js', array( 'agora-rtc-sdk', self::SETTINGS_HANDLE ), eg_care_asset_version( 'assets/js/video-call.js' ), true );
		wp_enqueue_script( 'eg-care-booking-js', EG_CARE_URL . 'assets/js/booking.js', array( self::SETTINGS_HANDLE ), eg_care_asset_version( 'assets/js/booking.js' ), true );

		if ( is_user_logged_in() ) {
			wp_enqueue_script( 'eg-care-call-ring-js', EG_CARE_URL . 'assets/js/call-ring.js', array( 'eg-care-video-call-js', self::SETTINGS_HANDLE ), eg_care_asset_version( 'assets/js/call-ring.js' ), true );
		}

		ob_start();
		// booking-flow.php reads $eg_care_doctor_id.
		include EG_CARE_PATH . 'templates/booking-flow.php';
		return ob_get_clean();
	}

	/**
	 * The post ID of an approved, published doctor, or 0.
	 *
	 * Anything else - a draft, a doctor still awaiting verification, a post of
	 * another type, a made-up number - reads as 0, so the flow opens on its
	 * first step instead of on a profile that should not be bookable.
	 *
	 * @param mixed $candidate Doctor post ID from the shortcode attribute.
	 * @return int Doctor post ID, or 0.
	 */
	private static function approved_doctor_id( $candidate ) {
		global $wpdb;

		$id = intval( $candidate );

		if ( $id <= 0 ) {
			return 0;
		}

		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );

		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT m.post_id FROM $table_meta m
				JOIN {$wpdb->posts} p ON m.post_id = p.ID
				WHERE p.ID = %d AND p.post_type = 'doctors' AND p.post_status = 'publish'
					AND m.verification_status = 'approved'",
				$id
			)
		);

		return $found ? (int) $found : 0;
	}

	/**
	 * The page carrying a given EG Care shortcode, if the site has one.
	 *
	 * Used to point someone at the right next screen instead of telling them a
	 * page exists and leaving them to find it. Cached, because this runs on a
	 * screen nobody is signed in to and the answer changes about once a year.
	 *
	 * Public because SpecialtyArchive sends visitors to the same pages, and a
	 * second copy of the lookup would be a second cache to keep in step.
	 *
	 * @param string $shortcode Shortcode tag, without brackets.
	 * @return string Permalink, or an empty string when no page has it.
	 */
	public static function page_with_shortcode( $shortcode ) {
		global $wpdb;

		$key    = 'eg_care_page_' . $shortcode;
		$cached = get_transient( $key );

		if ( false !== $cached ) {
			return (string) $cached;
		}

		$found = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_status = 'publish' AND post_type IN ('page', 'post')
					AND post_content LIKE %s
				ORDER BY ID ASC LIMIT 1",
				'%[' . $wpdb->esc_like( $shortcode ) . '%'
			)
		);

		$url = $found ? (string) get_permalink( (int) $found ) : '';

		set_transient( $key, $url, HOUR_IN_SECONDS );

		return $url;
	}

	/**
	 * The screen shown instead of a portal somebody cannot see yet.
	 *
	 * One card rather than a line of red text: whoever lands here needs to know
	 * what is behind the page, how to get in, and what to do if signing in is
	 * not the answer for them.
	 *
	 * @param array $args {
	 *     @type string $icon      One of 'provider', 'patient', 'instant'.
	 *     @type string $title     Heading.
	 *     @type string $message   One sentence under it.
	 *     @type array  $points    What lives behind this page.
	 *     @type array  $primary   {label, url}, or empty for none.
	 *     @type array  $secondary {label, url}, or empty for none.
	 *     @type string $footnote  Small print under the buttons.
	 * }
	 * @return string Markup.
	 */
	private static function access_gate( array $args ) {
		$args = array_merge(
			array(
				'icon'      => 'patient',
				'title'     => '',
				'message'   => '',
				'points'    => array(),
				'primary'   => array(),
				'secondary' => array(),
				'footnote'  => '',
			),
			$args
		);

		$icons = array(
			// A stethoscope, a person, and a lightning bolt: the three doors
			// this gate stands in front of.
			'provider' => '<path d="M6 3v5a6 6 0 0 0 12 0V3"/><path d="M4 3h4M16 3h4"/><circle cx="18" cy="17" r="3"/><path d="M12 14v0a5 5 0 0 0 3 4.6"/>',
			'patient'  => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/>',
			'instant'  => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
		);

		$icon = isset( $icons[ $args['icon'] ] ) ? $icons[ $args['icon'] ] : $icons['patient'];

		ob_start();
		?>
		<div class="eg-care-gate">
			<div class="eg-care-gate-card">
				<span class="eg-care-gate-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hardcoded above. ?></svg>
				</span>

				<h2 class="eg-care-gate-title"><?php echo esc_html( $args['title'] ); ?></h2>

				<?php if ( $args['message'] ) : ?>
					<p class="eg-care-gate-message"><?php echo esc_html( $args['message'] ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $args['points'] ) ) : ?>
					<ul class="eg-care-gate-points">
						<?php foreach ( $args['points'] as $point ) : ?>
							<li><?php echo esc_html( $point ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<div class="eg-care-gate-actions">
					<?php if ( ! empty( $args['primary']['url'] ) ) : ?>
						<a class="eg-care-gate-btn primary" href="<?php echo esc_url( $args['primary']['url'] ); ?>">
							<?php echo esc_html( $args['primary']['label'] ); ?>
						</a>
					<?php endif; ?>
					<?php if ( ! empty( $args['secondary']['url'] ) ) : ?>
						<a class="eg-care-gate-btn" href="<?php echo esc_url( $args['secondary']['url'] ); ?>">
							<?php echo esc_html( $args['secondary']['label'] ); ?>
						</a>
					<?php endif; ?>
				</div>

				<?php if ( $args['footnote'] ) : ?>
					<p class="eg-care-gate-footnote"><?php echo esc_html( $args['footnote'] ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Where to send someone back to after they sign in.
	 *
	 * @return string Current permalink, or the site's front page.
	 */
	private static function return_here() {
		$here = get_permalink();

		return $here ? $here : home_url( '/' );
	}

	/**
	 * Render the doctor dashboard shortcode content.
	 */
	public static function render_doctor_dashboard_shortcode() {
		if ( ! is_user_logged_in() ) {
			$apply = self::page_with_shortcode( 'eg_care_doctor_registration' );

			return self::access_gate(
				array(
					'icon'      => 'provider',
					'title'     => __( 'Sign in to your provider dashboard', 'eg-care' ),
					'message'   => __( 'Everything you need to run your consultations lives behind this page.', 'eg-care' ),
					'points'    => array(
						__( 'Today\'s consultations, and the button that starts each video call', 'eg-care' ),
						__( 'Patients waiting on an instant request right now', 'eg-care' ),
						__( 'Your weekly availability, and the slots patients can book', 'eg-care' ),
						__( 'What you have earned, and what is due to be paid out', 'eg-care' ),
					),
					'primary'   => array(
						'label' => __( 'Log in', 'eg-care' ),
						// Back to this page afterwards, rather than dropping the
						// doctor on the WordPress admin screen.
						'url'   => wp_login_url( self::return_here() ),
					),
					'secondary' => $apply
						? array(
							'label' => __( 'Apply as a provider', 'eg-care' ),
							'url'   => $apply,
						)
						: array(),
					'footnote'  => $apply
						? __( 'New here? Applications are reviewed before your profile goes live.', 'eg-care' )
						: '',
				)
			);
		}

		// Verify this user maps to a registered doctor profile
		global $wpdb;
		$user_id = get_current_user_id();
		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );
		$doctor = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_meta WHERE user_id = %d", $user_id ) );

		if ( ! $doctor ) {
			$apply = self::page_with_shortcode( 'eg_care_doctor_registration' );

			return self::access_gate(
				array(
					'icon'      => 'provider',
					'title'     => __( 'This account has no provider profile', 'eg-care' ),
					// Naming the account matters here: the usual cause is being
					// signed in as a patient, or as the wrong one of two logins.
					/* translators: %s: display name of the logged-in user. */
					'message'   => sprintf( __( 'You are signed in as %s, and that account is not registered as a provider.', 'eg-care' ), wp_get_current_user()->display_name ),
					'points'    => array(),
					'primary'   => $apply
						? array(
							'label' => __( 'Apply as a provider', 'eg-care' ),
							'url'   => $apply,
						)
						: array(),
					'secondary' => array(
						'label' => __( 'Sign in as someone else', 'eg-care' ),
						'url'   => wp_logout_url( self::return_here() ),
					),
					'footnote'  => __( 'Already applied? A profile appears here once an administrator approves it.', 'eg-care' ),
				)
			);
		}

		self::enqueue_settings(
			array(
				'userType' => 'doctor',
				'doctor'   => array(
					'id'               => intval( $doctor->post_id ),
					'consultation_fee' => floatval( $doctor->consultation_fee ),
					'instant_call_fee' => floatval( $doctor->instant_call_fee ),
					'is_online'        => (bool) $doctor->is_online,
					'bio'              => get_post( $doctor->post_id )->post_content,
				),
			)
		);

		wp_enqueue_script( 'agora-rtc-sdk', 'https://download.agora.io/sdk/release/AgoraRTC_N-4.20.0.js', array(), '4.20.0', true );
		wp_enqueue_script( 'eg-care-video-call-js', EG_CARE_URL . 'assets/js/video-call.js', array( 'agora-rtc-sdk', self::SETTINGS_HANDLE ), eg_care_asset_version( 'assets/js/video-call.js' ), true );
		wp_enqueue_script( 'eg-care-doctor-dashboard-js', EG_CARE_URL . 'assets/js/doctor-dashboard.js', array( self::SETTINGS_HANDLE ), eg_care_asset_version( 'assets/js/doctor-dashboard.js' ), true );
		wp_enqueue_script( 'eg-care-call-ring-js', EG_CARE_URL . 'assets/js/call-ring.js', array( 'eg-care-video-call-js', self::SETTINGS_HANDLE ), eg_care_asset_version( 'assets/js/call-ring.js' ), true );

		ob_start();
		include EG_CARE_PATH . 'templates/doctor-dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Render Patient Dashboard shortcode.
	 */
	public static function render_patient_dashboard_shortcode() {
		if ( ! is_user_logged_in() ) {
			$book = self::page_with_shortcode( 'eg_care_booking_flow' );

			return self::access_gate(
				array(
					'icon'      => 'patient',
					'title'     => __( 'Sign in to see your consultations', 'eg-care' ),
					'message'   => __( 'Your appointments and payments are tied to your account, so they are only visible once you are signed in.', 'eg-care' ),
					'points'    => array(
						__( 'Upcoming consultations, and the link that joins the call', 'eg-care' ),
						__( 'Past consultations, prescriptions and receipts', 'eg-care' ),
						__( 'The people you book for - parent, child, spouse', 'eg-care' ),
					),
					'primary'   => array(
						'label' => __( 'Log in', 'eg-care' ),
						'url'   => wp_login_url( self::return_here() ),
					),
					'secondary' => get_option( 'users_can_register' )
						? array(
							'label' => __( 'Create an account', 'eg-care' ),
							'url'   => wp_registration_url(),
						)
						: ( $book
							? array(
								'label' => __( 'Browse doctors', 'eg-care' ),
								'url'   => $book,
							)
							: array() ),
				)
			);
		}

		// The template below carries an inline handler that reads egCareSettings,
		// so the settings handle is enqueued here in its own right rather than
		// riding along on video-call.js.
		self::enqueue_settings( array( 'userType' => 'patient' ) );

		wp_enqueue_script( 'agora-rtc-sdk', 'https://download.agora.io/sdk/release/AgoraRTC_N-4.20.0.js', array(), '4.20.0', true );
		wp_enqueue_script( 'eg-care-video-call-js', EG_CARE_URL . 'assets/js/video-call.js', array( 'agora-rtc-sdk', self::SETTINGS_HANDLE ), eg_care_asset_version( 'assets/js/video-call.js' ), true );
		wp_enqueue_script( 'eg-care-call-ring-js', EG_CARE_URL . 'assets/js/call-ring.js', array( 'eg-care-video-call-js', self::SETTINGS_HANDLE ), eg_care_asset_version( 'assets/js/call-ring.js' ), true );
		wp_enqueue_script( 'eg-care-patient-dashboard-js', EG_CARE_URL . 'assets/js/patient-dashboard.js', array( self::SETTINGS_HANDLE ), eg_care_asset_version( 'assets/js/patient-dashboard.js' ), true );

		ob_start();
		include EG_CARE_PATH . 'templates/patient-dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Render the instant consultation request screen.
	 *
	 * Logged in only, and deliberately so: the request has to belong to
	 * somebody the doctor can reach and who can be charged afterwards.
	 */
	public static function render_instant_doctor_shortcode() {
		if ( ! is_user_logged_in() ) {
			return self::access_gate(
				array(
					'icon'      => 'instant',
					'title'     => __( 'Sign in to ask for a doctor', 'eg-care' ),
					'message'   => __( 'A request has to belong to an account: that is how the doctor reaches you, and how the fee is taken once one accepts.', 'eg-care' ),
					'points'    => array(
						/* translators: %s: consultation fee, already formatted. */
						sprintf( __( 'A consultation costs %s, paid only after a doctor accepts', 'eg-care' ), number_format_i18n( \EGCare\Fees::breakdown( \EGCare\Fees::instant_request_fee() )['total'] ) . ' BDT' ),
						__( 'Your request goes to every doctor on duty at once', 'eg-care' ),
						__( 'Nothing is charged while you wait, and nothing if nobody answers', 'eg-care' ),
					),
					'primary'   => array(
						'label' => __( 'Log in', 'eg-care' ),
						'url'   => wp_login_url( self::return_here() ),
					),
					'secondary' => get_option( 'users_can_register' )
						? array(
							'label' => __( 'Create an account', 'eg-care' ),
							'url'   => wp_registration_url(),
						)
						: array(),
				)
			);
		}

		self::enqueue_settings( array( 'userType' => 'patient' ) );

		wp_enqueue_script( 'eg-care-instant-request-js', EG_CARE_URL . 'assets/js/instant-request.js', array( self::SETTINGS_HANDLE ), eg_care_asset_version( 'assets/js/instant-request.js' ), true );

		// Coming back from the gateway, this page shows the confirmation and its
		// Join Video Call button, which needs the call scripts. They are heavy -
		// the Agora SDK is fetched from their CDN - so an ordinary view of the
		// request form does not pay for them.
		if ( isset( $_GET['eg_care_payment'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_enqueue_script( 'agora-rtc-sdk', 'https://download.agora.io/sdk/release/AgoraRTC_N-4.20.0.js', array(), '4.20.0', true );
			wp_enqueue_script( 'eg-care-video-call-js', EG_CARE_URL . 'assets/js/video-call.js', array( 'agora-rtc-sdk', self::SETTINGS_HANDLE ), eg_care_asset_version( 'assets/js/video-call.js' ), true );
		}

		ob_start();
		include EG_CARE_PATH . 'templates/instant-request.php';
		return ob_get_clean();
	}
}
