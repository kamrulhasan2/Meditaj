<?php
namespace Meditaj;

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
	 * Register the main Meditaj menu and submenus.
	 */
	public static function register_menus() {
		// Top Level Menu.
		add_menu_page(
			__( 'Meditaj', 'meditaj' ),
			__( 'Meditaj', 'meditaj' ),
			'manage_options',
			'meditaj',
			array( __CLASS__, 'render_overview_page' ),
			'dashicons-plus-alt',
			25
		);

		// Submenus.
		add_submenu_page(
			'meditaj',
			__( 'Overview', 'meditaj' ),
			__( 'Overview', 'meditaj' ),
			'manage_options',
			'meditaj',
			array( __CLASS__, 'render_overview_page' )
		);

		add_submenu_page(
			'meditaj',
			__( 'Doctor List', 'meditaj' ),
			__( 'Doctor List', 'meditaj' ),
			'manage_options',
			'meditaj-doctors',
			array( '\Meditaj\AdminDoctors', 'render_doctors_page' )
		);

		add_submenu_page(
			'meditaj',
			__( 'Pending Verifications', 'meditaj' ),
			__( 'Pending Verifications', 'meditaj' ),
			'manage_options',
			'meditaj-pending',
			array( '\Meditaj\AdminDoctors', 'render_pending_verifications_page' )
		);

		add_submenu_page(
			'meditaj',
			__( 'Add Doctor', 'meditaj' ),
			__( 'Add Doctor', 'meditaj' ),
			'manage_options',
			'meditaj-add-doctor',
			array( '\Meditaj\AdminDoctors', 'render_add_doctor_page' )
		);

		add_submenu_page(
			'meditaj',
			__( 'Patients', 'meditaj' ),
			__( 'Patients', 'meditaj' ),
			'manage_options',
			'meditaj-patients',
			array( __CLASS__, 'render_patients_page' )
		);

		add_submenu_page(
			'meditaj',
			__( 'Appointments', 'meditaj' ),
			__( 'Appointments', 'meditaj' ),
			'manage_options',
			'meditaj-appointments',
			array( __CLASS__, 'render_appointments_page' )
		);

		add_submenu_page(
			'meditaj',
			__( 'Settings', 'meditaj' ),
			__( 'Settings', 'meditaj' ),
			'manage_options',
			'meditaj-settings',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Render Overview page placeholder.
	 */
	public static function render_overview_page() {
		?>
		<div class="wrap meditaj-admin-wrap">
			<div class="meditaj-admin-header">
				<h1 class="meditaj-admin-title"><?php esc_html_e( 'Meditaj Dashboard', 'meditaj' ); ?></h1>
			</div>
			<div class="meditaj-placeholder-card">
				<h2><?php esc_html_e( 'System Overview', 'meditaj' ); ?></h2>
				<p><?php esc_html_e( 'This page will show operational metrics and transaction graphs in Phase 8.', 'meditaj' ); ?></p>
			</div>
		</div>
		<?php
	}



	/**
	 * Render Patients page placeholder.
	 */
	public static function render_patients_page() {
		?>
		<div class="wrap meditaj-admin-wrap">
			<div class="meditaj-admin-header">
				<h1 class="meditaj-admin-title"><?php esc_html_e( 'Patients Directory', 'meditaj' ); ?></h1>
			</div>
			<div class="meditaj-placeholder-card">
				<h2><?php esc_html_e( 'Patient Registry', 'meditaj' ); ?></h2>
				<p><?php esc_html_e( 'A list of registered patient users and their respective appointment history will be displayed here in a later phase.', 'meditaj' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Appointments page placeholder.
	 */
	public static function render_appointments_page() {
		?>
		<div class="wrap meditaj-admin-wrap">
			<div class="meditaj-admin-header">
				<h1 class="meditaj-admin-title"><?php esc_html_e( 'Appointments Manager', 'meditaj' ); ?></h1>
			</div>
			<div class="meditaj-placeholder-card">
				<h2><?php esc_html_e( 'All Appointments', 'meditaj' ); ?></h2>
				<p><?php esc_html_e( 'A full appointments tracking ledger with status filtering and logs will be displayed here in Phase 8.', 'meditaj' ); ?></p>
			</div>
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
		if ( isset( $_POST['meditaj_settings_submit'] ) && check_admin_referer( 'meditaj_save_settings', 'meditaj_settings_nonce' ) ) {
			update_option( 'meditaj_ssl_store_id', sanitize_text_field( $_POST['meditaj_ssl_store_id'] ) );
			update_option( 'meditaj_ssl_store_passwd', sanitize_text_field( $_POST['meditaj_ssl_store_passwd'] ) );
			update_option( 'meditaj_ssl_sandbox', isset( $_POST['meditaj_ssl_sandbox'] ) ? '1' : '0' );
			update_option( 'meditaj_commission_percentage', floatval( $_POST['meditaj_commission_percentage'] ) );
			echo '<div class="notice notice-success is-dismissible" style="max-width: 600px; margin: 15px 0;"><p>' . esc_html__( 'Settings successfully saved!', 'meditaj' ) . '</p></div>';
		}

		// Fetch active values.
		$store_id     = get_option( 'meditaj_ssl_store_id', 'testbox' );
		$store_passwd = get_option( 'meditaj_ssl_store_passwd', 'testbox@ssl' );
		$sandbox      = get_option( 'meditaj_ssl_sandbox', '1' );
		$commission   = get_option( 'meditaj_commission_percentage', '15' );
		?>
		<div class="wrap meditaj-admin-wrap">
			<div class="meditaj-admin-header" style="margin-bottom: 20px;">
				<h1 class="meditaj-admin-title"><?php esc_html_e( 'Meditaj Gateway & Platform Settings', 'meditaj' ); ?></h1>
			</div>

			<form method="post" action="" style="background: #fff; padding: 30px; border-radius: 8px; border: 1px solid #ccd0d4; max-width: 600px;">
				<?php wp_nonce_field( 'meditaj_save_settings', 'meditaj_settings_nonce' ); ?>
				
				<h3 style="margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 10px;"><?php esc_html_e( 'SSLCommerz Gateway Settings', 'meditaj' ); ?></h3>
				
				<table class="form-table">
					<tr>
						<th scope="row"><label for="meditaj_ssl_store_id"><?php esc_html_e( 'Store ID', 'meditaj' ); ?></label></th>
						<td>
							<input name="meditaj_ssl_store_id" type="text" id="meditaj_ssl_store_id" value="<?php echo esc_attr( $store_id ); ?>" class="regular-text" required>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="meditaj_ssl_store_passwd"><?php esc_html_e( 'Store Password', 'meditaj' ); ?></label></th>
						<td>
							<div style="position: relative; display: inline-block;">
								<input name="meditaj_ssl_store_passwd" type="password" id="meditaj_ssl_store_passwd" value="<?php echo esc_attr( $store_passwd ); ?>" class="regular-text" style="padding-right: 35px;" required>
								<span class="dashicons dashicons-visibility" id="toggle-password-visibility" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #72777c;"></span>
							</div>
							<p class="description"><?php esc_html_e( 'Input your custom SSLCommerz API Store Password.', 'meditaj' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Sandbox / Test Mode', 'meditaj' ); ?></th>
						<td>
							<label>
								<input name="meditaj_ssl_sandbox" type="checkbox" id="meditaj_ssl_sandbox" value="1" <?php checked( $sandbox, '1' ); ?>>
								<?php esc_html_e( 'Enable Sandbox (Test Mode) Gateway URLs', 'meditaj' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<h3 style="margin-top: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px;"><?php esc_html_e( 'Platform Calculations', 'meditaj' ); ?></h3>

				<table class="form-table">
					<tr>
						<th scope="row"><label for="meditaj_commission_percentage"><?php esc_html_e( 'Platform Commission (%)', 'meditaj' ); ?></label></th>
						<td>
							<input name="meditaj_commission_percentage" type="number" id="meditaj_commission_percentage" value="<?php echo esc_attr( $commission ); ?>" min="0" max="100" step="0.5" class="small-text" required>
							<p class="description"><?php esc_html_e( 'Platform service fee cut deducted from paid appointment totals.', 'meditaj' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit" style="margin-bottom: 0; padding-bottom: 0; margin-top: 25px;">
					<input type="submit" name="meditaj_settings_submit" id="submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Save Settings Configurations', 'meditaj' ); ?>">
				</p>
			</form>
		</div>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			const toggleBtn = document.getElementById('toggle-password-visibility');
			const pwdInput = document.getElementById('meditaj_ssl_store_passwd');
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
		});
		</script>
		<?php
	}
}
