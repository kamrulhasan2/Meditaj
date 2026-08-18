<?php
namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles Admin Menu registration and page routing.
 */
class AdminMenu {
	/**
	 * Initialize the admin menu actions.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
	}

	/**
	 * Register the main EG Care menu and submenus.
	 */
	public static function register_menus() {
		// Top Level Menu.
		add_menu_page(
			__( 'EG Care', 'eg-care' ),
			__( 'EG Care', 'eg-care' ),
			'manage_options',
			'eg-care',
			array( __CLASS__, 'render_overview_page' ),
			'dashicons-plus-alt',
			25
		);

		// Submenus.
		add_submenu_page(
			'eg-care',
			__( 'Overview', 'eg-care' ),
			__( 'Overview', 'eg-care' ),
			'manage_options',
			'eg-care',
			array( __CLASS__, 'render_overview_page' )
		);

		add_submenu_page(
			'eg-care',
			__( 'Doctor List', 'eg-care' ),
			__( 'Doctor List', 'eg-care' ),
			'manage_options',
			'eg-care-doctors',
			array( '\EGCare\AdminDoctors', 'render_doctors_page' )
		);

		add_submenu_page(
			'eg-care',
			__( 'Pending Verifications', 'eg-care' ),
			__( 'Pending Verifications', 'eg-care' ),
			'manage_options',
			'eg-care-pending',
			array( '\EGCare\AdminDoctors', 'render_pending_verifications_page' )
		);

		// Remove standard WordPress default CPT subpage.
		remove_submenu_page( 'edit.php?post_type=doctors', 'post-new.php?post_type=doctors' );

		// Register our custom layout form under Doctors CPT sidebar menu instead.
		add_submenu_page(
			'edit.php?post_type=doctors',
			__( 'Add New Doctor', 'eg-care' ),
			__( 'Add New Doctor', 'eg-care' ),
			'manage_options',
			'eg-care-add-doctor',
			array( '\EGCare\AdminDoctors', 'render_add_doctor_page' )
		);

		add_submenu_page(
			'eg-care',
			__( 'Patients', 'eg-care' ),
			__( 'Patients', 'eg-care' ),
			'manage_options',
			'eg-care-patients',
			array( __CLASS__, 'render_patients_page' )
		);

		add_submenu_page(
			'eg-care',
			__( 'Appointments', 'eg-care' ),
			__( 'Appointments', 'eg-care' ),
			'manage_options',
			'eg-care-appointments',
			array( __CLASS__, 'render_appointments_page' )
		);

		add_submenu_page(
			'eg-care',
			__( 'Setup Guide', 'eg-care' ),
			__( 'Setup Guide', 'eg-care' ),
			'manage_options',
			'eg-care-setup-guide',
			array( __CLASS__, 'render_setup_guide_page' )
		);

		add_submenu_page(
			'eg-care',
			__( 'Settings', 'eg-care' ),
			__( 'Settings', 'eg-care' ),
			'manage_options',
			'eg-care-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Render Overview page callback.
	 */
	public static function render_overview_page() {
		require_once EG_CARE_PATH . 'includes/class-admin-dashboard.php';
		\EGCare\AdminDashboard::render_dashboard();
	}

	/**
	 * Render Patients page placeholder.
	 */
	public static function render_patients_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require_once EG_CARE_PATH . 'includes/class-admin-patients.php';
		$table = new AdminPatientsTable();
		$table->prepare_items();
		?>
		<div class="wrap eg-care-admin-wrap">
			<div class="eg-care-admin-header" style="margin-bottom: 20px;">
				<h1 class="eg-care-admin-title"><?php esc_html_e( 'Patients Directory', 'eg-care' ); ?></h1>
			</div>

			<form method="get" action="">
				<input type="hidden" name="page" value="eg-care-patients" />
				<?php
				$table->search_box( __( 'Search Patients', 'eg-care' ), 'eg_care_search_patient' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render Appointments page list table.
	 */
	public static function render_appointments_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table_appointments = DB::get_table( 'appointments' );

		// 1. Process Actions (Cancel / Complete)
		if ( isset( $_GET['eg_care_action'] ) && isset( $_GET['appt_id'] ) ) {
			$action  = sanitize_key( $_GET['eg_care_action'] );
			$appt_id = intval( $_GET['appt_id'] );

			if ( 'cancel' === $action && check_admin_referer( 'eg_care_cancel_appointment' ) ) {
				$wpdb->update( $table_appointments, array( 'status' => 'cancelled' ), array( 'id' => $appt_id ) );
				echo '<div class="notice notice-success is-dismissible" style="margin: 15px 0;"><p>' . esc_html__( 'Appointment successfully cancelled!', 'eg-care' ) . '</p></div>';
			}

			if ( 'complete' === $action && check_admin_referer( 'eg_care_complete_appointment' ) ) {
				$wpdb->update( $table_appointments, array( 'status' => 'completed' ), array( 'id' => $appt_id ) );
				echo '<div class="notice notice-success is-dismissible" style="margin: 15px 0;"><p>' . esc_html__( 'Appointment successfully marked as completed!', 'eg-care' ) . '</p></div>';
			}
		}

		// 2. Process Bulk Actions
		if ( ( isset( $_GET['action'] ) || isset( $_GET['action2'] ) ) && isset( $_GET['bulk-appointments'] ) ) {
			if ( ! isset( $_GET['eg_care_bulk_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_GET['eg_care_bulk_nonce'] ), 'bulk-appointments' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'eg-care' ) );
			}
			$bulk_action = sanitize_key( ! empty( $_GET['action'] ) ? $_GET['action'] : $_GET['action2'] );
			$appt_ids    = array_map( 'intval', $_GET['bulk-appointments'] );

			if ( 'bulk-cancel' === $bulk_action ) {
				foreach ( $appt_ids as $id ) {
					$wpdb->update( $table_appointments, array( 'status' => 'cancelled' ), array( 'id' => $id ) );
				}
				echo '<div class="notice notice-success is-dismissible" style="margin: 15px 0;"><p>' . esc_html__( 'Selected appointments successfully cancelled!', 'eg-care' ) . '</p></div>';
			}

			if ( 'bulk-complete' === $bulk_action ) {
				foreach ( $appt_ids as $id ) {
					$wpdb->update( $table_appointments, array( 'status' => 'completed' ), array( 'id' => $id ) );
				}
				echo '<div class="notice notice-success is-dismissible" style="margin: 15px 0;"><p>' . esc_html__( 'Selected appointments marked as completed!', 'eg-care' ) . '</p></div>';
			}
		}

		// 3. Render Table
		require_once EG_CARE_PATH . 'includes/class-admin-appointments.php';
		$table = new AdminAppointmentsTable();
		$table->prepare_items();
		?>
		<div class="wrap eg-care-admin-wrap">
			<div class="eg-care-admin-header" style="margin-bottom: 20px;">
				<h1 class="eg-care-admin-title"><?php esc_html_e( 'Appointments Tracker Ledger', 'eg-care' ); ?></h1>
			</div>

			<form method="get" action="">
				<input type="hidden" name="page" value="eg-care-appointments" />
				<?php wp_nonce_field( 'bulk-appointments', 'eg_care_bulk_nonce' ); ?>
				<?php
				$table->search_box( __( 'Search Patients/Txn ID', 'eg-care' ), 'eg_care_search_appt' );
				$table->views();
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render Settings Page and handle options saving.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle Form Submission.
		if ( isset( $_POST['eg_care_settings_submit'] ) && check_admin_referer( 'eg_care_save_settings', 'eg_care_settings_nonce' ) ) {
			update_option( 'eg_care_ssl_store_id', sanitize_text_field( $_POST['eg_care_ssl_store_id'] ) );
			
			$new_store_passwd = sanitize_text_field( $_POST['eg_care_ssl_store_passwd'] );
			if ( '●●●●●●●●' !== $new_store_passwd && '' !== $new_store_passwd ) {
				update_option( 'eg_care_ssl_store_passwd', $new_store_passwd, 'no' );
			}
			
			update_option( 'eg_care_ssl_sandbox', isset( $_POST['eg_care_ssl_sandbox'] ) ? '1' : '0' );
			update_option( 'eg_care_commission_percentage', floatval( $_POST['eg_care_commission_percentage'] ) );
			update_option( 'eg_care_agora_app_id', sanitize_text_field( $_POST['eg_care_agora_app_id'] ) );
			
			$new_agora_app_crt = sanitize_text_field( $_POST['eg_care_agora_app_certificate'] );
			if ( '●●●●●●●●' !== $new_agora_app_crt && '' !== $new_agora_app_crt ) {
				update_option( 'eg_care_agora_app_certificate', $new_agora_app_crt, 'no' );
			}
			echo '<div class="notice notice-success is-dismissible" style="max-width: 600px; margin: 15px 0;"><p>' . esc_html__( 'Settings successfully saved!', 'eg-care' ) . '</p></div>';
		}

		// Fetch active values.
		$store_id      = get_option( 'eg_care_ssl_store_id', 'testbox' );
		$store_passwd  = get_option( 'eg_care_ssl_store_passwd', 'testbox@ssl' );
		$sandbox       = get_option( 'eg_care_ssl_sandbox', '1' );
		$commission    = get_option( 'eg_care_commission_percentage', '15' );
		$agora_app_id  = get_option( 'eg_care_agora_app_id', '' );
		$agora_app_crt = get_option( 'eg_care_agora_app_certificate', '' );

		$masked_store_passwd = ! empty( $store_passwd ) ? '●●●●●●●●' : '';
		$masked_agora_app_crt = ! empty( $agora_app_crt ) ? '●●●●●●●●' : '';
		?>
		<div class="wrap eg-care-admin-wrap">
			<div class="eg-care-admin-header" style="margin-bottom: 20px;">
				<h1 class="eg-care-admin-title"><?php esc_html_e( 'EG Care Gateway & Platform Settings', 'eg-care' ); ?></h1>
			</div>

			<form method="post" action="" style="background: #fff; padding: 30px; border-radius: 8px; border: 1px solid #ccd0d4; max-width: 600px;">
				<?php wp_nonce_field( 'eg_care_save_settings', 'eg_care_settings_nonce' ); ?>
				
				<h3 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;"><?php esc_html_e( 'SSLCommerz Gateway Settings', 'eg-care' ); ?></h3>
				
				<table class="form-table">
					<tr>
						<th scope="row"><label for="eg_care_ssl_store_id"><?php esc_html_e( 'Store ID', 'eg-care' ); ?></label></th>
						<td>
							<input name="eg_care_ssl_store_id" type="text" id="eg_care_ssl_store_id" value="<?php echo esc_attr( $store_id ); ?>" class="regular-text" required>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="eg_care_ssl_store_passwd"><?php esc_html_e( 'Store Password', 'eg-care' ); ?></label></th>
						<td>
							<div style="position: relative; display: inline-block;">
								<input name="eg_care_ssl_store_passwd" type="password" id="eg_care_ssl_store_passwd" value="<?php echo esc_attr( $masked_store_passwd ); ?>" class="regular-text" style="padding-right: 35px;" required>
								<span class="dashicons dashicons-visibility" id="toggle-password-visibility" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #72777c;"></span>
							</div>
							<p class="description"><?php esc_html_e( 'Input your custom SSLCommerz API Store Password.', 'eg-care' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Sandbox / Test Mode', 'eg-care' ); ?></th>
						<td>
							<label>
								<input name="eg_care_ssl_sandbox" type="checkbox" id="eg_care_ssl_sandbox" value="1" <?php checked( $sandbox, '1' ); ?>>
								<?php esc_html_e( 'Enable Sandbox (Test Mode) Gateway URLs', 'eg-care' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h3 style="margin-top: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px;"><?php esc_html_e( 'Agora Video Calling Settings', 'eg-care' ); ?></h3>
				
				<table class="form-table">
					<tr>
						<th scope="row"><label for="eg_care_agora_app_id"><?php esc_html_e( 'Agora App ID', 'eg-care' ); ?></label></th>
						<td>
							<input name="eg_care_agora_app_id" type="text" id="eg_care_agora_app_id" value="<?php echo esc_attr( $agora_app_id ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Input your Agora Console Project App ID.', 'eg-care' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="eg_care_agora_app_certificate"><?php esc_html_e( 'Agora App Certificate', 'eg-care' ); ?></label></th>
						<td>
							<div style="position: relative; display: inline-block;">
								<input name="eg_care_agora_app_certificate" type="password" id="eg_care_agora_app_certificate" value="<?php echo esc_attr( $masked_agora_app_crt ); ?>" class="regular-text" style="padding-right: 35px;">
								<span class="dashicons dashicons-visibility" id="toggle-agora-visibility" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #72777c;"></span>
							</div>
							<p class="description"><?php esc_html_e( 'Input your Agora Console Project Primary App Certificate.', 'eg-care' ); ?></p>
						</td>
					</tr>
				</table>

				<h3 style="margin-top: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px;"><?php esc_html_e( 'Platform Calculations', 'eg-care' ); ?></h3>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="eg_care_commission_percentage"><?php esc_html_e( 'Platform Commission (%)', 'eg-care' ); ?></label></th>
						<td>
							<input name="eg_care_commission_percentage" type="number" id="eg_care_commission_percentage" value="<?php echo esc_attr( $commission ); ?>" min="0" max="100" step="0.5" class="small-text" required>
							<p class="description"><?php esc_html_e( 'Platform service fee cut deducted from paid appointment totals.', 'eg-care' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit" style="margin-bottom: 0; padding-bottom: 0; margin-top: 25px;">
					<input type="submit" name="eg_care_settings_submit" id="submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Save Settings Configurations', 'eg-care' ); ?>">
				</p>
			</form>
		</div>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const toggleBtn = document.getElementById('toggle-password-visibility');
			const pwdInput = document.getElementById('eg_care_ssl_store_passwd');
			if (toggleBtn && pwdInput) {
				toggleBtn.addEventListener('click', function() {
					if (pwdInput.type === 'password') {
						pwdInput.type = 'text';
						toggleBtn.classList.remove('dashicons-visibility');
						toggleBtn.classList.add('dashicons-hidden');
					} else {
						pwdInput.type = 'password';
						toggleBtn.classList.remove('dashicons-hidden');
						toggleBtn.classList.add('dashicons-visibility');
					}
				});
			}

			const toggleAgoraBtn = document.getElementById('toggle-agora-visibility');
			const agoraInput = document.getElementById('eg_care_agora_app_certificate');
			if (toggleAgoraBtn && agoraInput) {
				toggleAgoraBtn.addEventListener('click', function() {
					if (agoraInput.type === 'password') {
						agoraInput.type = 'text';
						toggleAgoraBtn.classList.remove('dashicons-visibility');
						toggleAgoraBtn.classList.add('dashicons-hidden');
					} else {
						agoraInput.type = 'password';
						toggleAgoraBtn.classList.remove('dashicons-hidden');
						toggleAgoraBtn.classList.add('dashicons-visibility');
					}
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Render Setup Guide page callback.
	 */
	public static function render_setup_guide_page() {
		?>
		<div class="wrap eg-care-admin-wrap" style="max-width: 1000px; font-family: -apple-system, BlinkMacSystemFont, sans-serif;">
			<div class="eg-care-admin-header" style="margin-bottom: 25px;">
				<h1 class="eg-care-admin-title"><?php esc_html_e( 'EG Care Installation & Setup Guide', 'eg-care' ); ?></h1>
			</div>

			<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; padding: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); margin-bottom: 30px;">
				<p style="font-size: 15px; line-height: 1.6; color: #334155; margin-top: 0;">
					<?php esc_html_e( 'Welcome to EG Care! This guide walks you through the step-by-step process of setting up shortcodes, integrating SSLCommerz sandbox payments, securing WebRTC video calls via Agora, and scheduling automated email alerts.', 'eg-care' ); ?>
				</p>
			</div>

			<!-- SECTION 1: Shortcodes Integration -->
			<div class="eg-care-form-card" style="margin-top: 0; margin-bottom: 30px; padding: 25px;">
				<h3 style="margin-top: 0; font-size: 18px; color: #0f766e; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; font-weight: 700;">
					<?php esc_html_e( '1. Shortcodes Guide', 'eg-care' ); ?>
				</h3>
				<p style="font-size: 14px; color: #475569; line-height: 1.5; margin-bottom: 15px;">
					<?php esc_html_e( 'Create new WordPress pages for each of the following portals and insert their respective shortcodes:', 'eg-care' ); ?>
				</p>
				<table class="wp-list-table widefat fixed striped" style="box-shadow: none; border: 1px solid #e2e8f0;">
					<thead>
						<tr>
							<th style="width: 250px; font-weight: 600;"><?php esc_html_e( 'Shortcode', 'eg-care' ); ?></th>
							<th style="font-weight: 600;"><?php esc_html_e( 'Description / Usage', 'eg-care' ); ?></th>
							<th style="width: 150px; font-weight: 600;"><?php esc_html_e( 'User Access', 'eg-care' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><code style="font-size: 13px; color: #0f766e; font-weight: bold;">[eg_care_booking_flow]</code></td>
							<td><?php esc_html_e( 'Renders the complete patient-facing telemedicine booking journey (specialty selection → slots grid → checkout).', 'eg-care' ); ?></td>
							<td><span class="eg-care-status-badge status-approved" style="font-size: 10px;"><?php esc_html_e( 'PUBLIC GUEST', 'eg-care' ); ?></span></td>
						</tr>
						<tr>
							<td><code style="font-size: 13px; color: #0f766e; font-weight: bold;">[eg_care_patient_dashboard]</code></td>
							<td><?php esc_html_e( 'Renders the frontend patient control panel to view booked list, transactions, join calls, and submit reviews.', 'eg-care' ); ?></td>
							<td><span class="eg-care-status-badge status-pending" style="font-size: 10px;"><?php esc_html_e( 'PATIENTS ONLY', 'eg-care' ); ?></span></td>
						</tr>
						<tr>
							<td><code style="font-size: 13px; color: #0f766e; font-weight: bold;">[eg_care_doctor_dashboard]</code></td>
							<td><?php esc_html_e( 'Renders the frontend doctor portal to manage schedule grids, edit meta, and launch video calls.', 'eg-care' ); ?></td>
							<td><span class="eg-care-status-badge status-pending" style="font-size: 10px;"><?php esc_html_e( 'DOCTORS ONLY', 'eg-care' ); ?></span></td>
						</tr>
						<tr>
							<td><code style="font-size: 13px; color: #0f766e; font-weight: bold;">[eg_care_doctor_registration]</code></td>
							<td><?php esc_html_e( 'Renders a streamlined onboarding form for new doctors to sign up and submit certificates/license.', 'eg-care' ); ?></td>
							<td><span class="eg-care-status-badge status-approved" style="font-size: 10px;"><?php esc_html_e( 'PUBLIC GUEST', 'eg-care' ); ?></span></td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- SECTION 2: SSLCommerz Integration -->
			<div class="eg-care-form-card" style="margin-bottom: 30px; padding: 25px;">
				<h3 style="margin-top: 0; font-size: 18px; color: #0f766e; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; font-weight: 700;">
					<?php esc_html_e( '2. SSLCommerz Payment Gateway Setup', 'eg-care' ); ?>
				</h3>
				<ol style="margin: 0; padding-left: 20px; font-size: 14px; color: #334155; line-height: 1.6;">
					<li><?php printf( __( 'Navigate to the <a href="%s">Settings page</a> in this plugin.', 'eg-care' ), admin_url( 'admin.php?page=eg-care-settings' ) ); ?></li>
					<li><?php esc_html_e( 'Paste your Store ID and Store Password provided by SSLCommerz.', 'eg-care' ); ?></li>
					<li><?php esc_html_e( 'Check the Sandbox checkbox for local/staging testing. Uncheck it when migrating to production/live merchant credentials.', 'eg-care' ); ?></li>
					<li><strong><?php esc_html_e( 'Local Webhook Limitation Note:', 'eg-care' ); ?></strong> <?php esc_html_e( 'Localhost environments cannot receive public webhooks. To simulate a paid booking while testing locally, add define( \'EG_CARE_ALLOW_FAKE_PAYMENTS\', true ); to your wp-config.php. Never set this on a live site - it lets any logged-in patient confirm a booking without paying. Production relies on the real IPN handler.', 'eg-care' ); ?></li>
				</ol>
			</div>

			<!-- SECTION 3: Agora Video Setup -->
			<div class="eg-care-form-card" style="margin-bottom: 30px; padding: 25px;">
				<h3 style="margin-top: 0; font-size: 18px; color: #0f766e; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; font-weight: 700;">
					<?php esc_html_e( '3. Agora WebRTC Video Call Integration', 'eg-care' ); ?>
				</h3>
				<p style="font-size: 14px; color: #475569; line-height: 1.5; margin-bottom: 15px;">
					<?php esc_html_e( 'To generate short-lived, encrypted version 007 RTC AccessTokens, you must register the keys on your Agora Console:', 'eg-care' ); ?>
				</p>
				<ol style="margin: 0; padding-left: 20px; font-size: 14px; color: #334155; line-height: 1.6; margin-bottom: 15px;">
					<li><?php esc_html_e( 'Log into your Agora Console (https://console.agora.io) and create a new project.', 'eg-care' ); ?></li>
					<li><?php esc_html_e( 'Ensure you select "Secured Mode: App ID + App Certificate" (DO NOT use App ID only mode, as token validation will reject it!).', 'eg-care' ); ?></li>
					<li><?php esc_html_e( 'Copy your App ID and App Certificate from the Project management tab.', 'eg-care' ); ?></li>
					<li><?php printf( __( 'Paste them into their respective slots in the <a href="%s">EG Care Settings page</a>.', 'eg-care' ), admin_url( 'admin.php?page=eg-care-settings' ) ); ?></li>
				</ol>
				<div style="background: #fff8e1; border-left: 4px solid #ffb300; padding: 12px 15px; border-radius: 4px; font-size: 13px; color: #5d4037;">
					<strong><?php esc_html_e( 'SSL / HTTPS Context Warning:', 'eg-care' ); ?></strong> <?php esc_html_e( 'Browsers restrict camera and microphone access to HTTPS only on live domains. Video calling will fail to connect over HTTP (except on localhost).', 'eg-care' ); ?>
				</div>
			</div>

			<!-- SECTION 4: WP-Cron & Email Reminders -->
			<div class="eg-care-form-card" style="margin-bottom: 30px; padding: 25px;">
				<h3 style="margin-top: 0; font-size: 18px; color: #0f766e; border-bottom: 1px solid #e2e8f0; padding-bottom: 10px; font-weight: 700;">
					<?php esc_html_e( '4. Automated Email Alerts & Cron Reminders', 'eg-care' ); ?>
				</h3>
				<p style="font-size: 14px; color: #475569; line-height: 1.5; margin-bottom: 15px;">
					<?php esc_html_e( 'EG Care automatically fires transactional emails on booking confirmations, doctor meta approvals, and schedules reminder emails 30 minutes before consultations.', 'eg-care' ); ?>
				</p>
				<ol style="margin: 0; padding-left: 20px; font-size: 14px; color: #334155; line-height: 1.6; margin-bottom: 15px;">
					<li><?php esc_html_e( 'Ensure your server is configured to send emails. We highly recommend installing an SMTP plugin (like WP Mail SMTP) to prevent alerts from landing in spam folders.', 'eg-care' ); ?></li>
					<li><?php esc_html_e( 'WP-Cron depends on user traffic. On low-traffic staging/production sites, the 30-minute reminder emails may experience delays.', 'eg-care' ); ?></li>
					<li><?php esc_html_e( 'For production environments, disable default WP-Cron in wp-config.php and set up a system-level cron job in your hosting control panel to call wp-cron.php every 10 minutes:', 'eg-care' ); ?>
						<pre style="background: #f1f5f9; padding: 10px; border-radius: 4px; font-family: monospace; font-size: 12px; margin-top: 5px; color: #0f172a; overflow-x: auto;">*/10 * * * * wget -q -O - http://yourdomain.com/wp-cron.php?doing_wp_cron >/dev/null 2>&1</pre>
					</li>
				</ol>
			</div>
		</div>
		<?php
	}
}
