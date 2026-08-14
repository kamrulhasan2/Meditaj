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
}
