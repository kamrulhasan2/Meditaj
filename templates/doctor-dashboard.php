<?php
/**
 * Template for Doctor-Facing Dashboard.
 *
 * @package Meditaj
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$user = wp_get_current_user();
?>

<div class="meditaj-dashboard-wrapper">
	<!-- Header Banner -->
	<div class="meditaj-dashboard-header">
		<div class="welcome-text">
			<h2>Welcome back, <?php echo esc_html( $user->display_name ); ?>!</h2>
			<p class="role-caption"><?php esc_html_e( 'Registered Meditaj Specialist', 'meditaj' ); ?></p>
		</div>
		<div class="instant-status-toggle">
			<span class="toggle-label"><?php esc_html_e( 'Go Online (Instant Call)', 'meditaj' ); ?></span>
			<label class="meditaj-switch">
				<input type="checkbox" id="dashboard-instant-toggle">
				<span class="slider round"></span>
			</label>
		</div>
	</div>

	<!-- Stats Overview Cards -->
	<div class="meditaj-stats-row">
		<div class="meditaj-stat-card">
			<div class="stat-icon purple">📅</div>
			<div class="stat-details">
				<span class="stat-num" id="stat-total-appointments">0</span>
				<span class="stat-label"><?php esc_html_e( 'Total Consultations', 'meditaj' ); ?></span>
			</div>
		</div>
		<div class="meditaj-stat-card">
			<div class="stat-icon green">৳</div>
			<div class="stat-details">
				<span class="stat-num" id="stat-net-earnings">0.00 BDT</span>
				<span class="stat-label"><?php esc_html_e( 'Net Earnings (85%)', 'meditaj' ); ?></span>
				<span class="stat-help" id="stat-gross-breakdown">Gross: 0.00 BDT</span>
			</div>
		</div>
		<div class="meditaj-stat-card">
			<div class="stat-icon blue">⚡</div>
			<div class="stat-details">
				<span class="stat-num" id="stat-online-badge">Offline</span>
				<span class="stat-label"><?php esc_html_e( 'Status', 'meditaj' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Tab Navigation -->
	<div class="meditaj-dashboard-tabs">
		<button type="button" class="tab-trigger active" data-tab="appointments"><?php esc_html_e( 'Today\'s Appointments', 'meditaj' ); ?></button>
		<button type="button" class="tab-trigger" data-tab="upcoming"><?php esc_html_e( 'Upcoming Consultations', 'meditaj' ); ?></button>
		<button type="button" class="tab-trigger" data-tab="slots"><?php esc_html_e( 'Schedules & Slots', 'meditaj' ); ?></button>
		<button type="button" class="tab-trigger" data-tab="profile"><?php esc_html_e( 'Profile Settings', 'meditaj' ); ?></button>
	</div>

	<!-- Tab Content Panes -->
	<div class="meditaj-tab-contents">
		<!-- Pane: Today's Appointments -->
		<div class="tab-pane active" id="pane-appointments">
			<h3><?php esc_html_e( 'Today\'s Appointed Consultations', 'meditaj' ); ?></h3>
			<div class="meditaj-table-responsive">
				<table class="meditaj-dashboard-table">
					<thead>
						<tr>
							<th>Patient Name</th>
							<th>Schedule Time</th>
							<th>Symptoms Notes</th>
							<th>Fee (Gross)</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody id="today-appointments-target">
						<tr>
							<td colspan="5" class="loading-cell">Loading appointments...</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Pane: Upcoming Appointments -->
		<div class="tab-pane" id="pane-upcoming">
			<h3><?php esc_html_e( 'Upcoming Scheduled Appointments', 'meditaj' ); ?></h3>
			<div class="meditaj-table-responsive">
				<table class="meditaj-dashboard-table">
					<thead>
						<tr>
							<th>Patient Name</th>
							<th>Schedule Date</th>
							<th>Schedule Time</th>
							<th>Symptoms Notes</th>
							<th>Fee (Gross)</th>
						</tr>
					</thead>
					<tbody id="upcoming-appointments-target">
						<tr>
							<td colspan="5" class="loading-cell">Loading appointments...</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

		<!-- Pane: Slots Scheduler -->
		<div class="tab-pane" id="pane-slots">
			<h3><?php esc_html_e( 'Configure Availability Slot Grid', 'meditaj' ); ?></h3>
			<p class="pane-desc"><?php esc_html_e( 'Define standard hourly duration slots for each day of the week to enable patients to book appointments.', 'meditaj' ); ?></p>
			
			<form id="meditaj-slots-form">
				<div class="schedules-weekly-grid">
					<?php
					$days = array(
						1 => 'Monday',
						2 => 'Tuesday',
						3 => 'Wednesday',
						4 => 'Thursday',
						5 => 'Friday',
						6 => 'Saturday',
						7 => 'Sunday',
					);
					foreach ( $days as $num => $name ) :
						?>
					<div class="day-slot-row" data-day="<?php echo esc_attr( $num ); ?>" style="display: flex; gap: 20px; border-bottom: 1px solid #f1f5f9; padding: 15px 0; align-items: flex-start; flex-wrap: wrap;">
						<div class="day-label-column" style="flex: 0 0 150px; font-weight: bold; font-size: 16px; color: #1e293b; padding-top: 10px;">
							<?php echo esc_html( $name ); ?>
						</div>
						<div class="day-blocks-column" style="flex-grow: 1;">
							<div class="day-slots-blocks-container">
								<!-- Slot blocks will be dynamically loaded/inserted here -->
							</div>
							<button type="button" class="meditaj-add-slot-btn" style="margin-top: 8px; font-size: 13px; padding: 8px 16px; background: #e0f2fe; color: #0369a1; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; transition: all 0.2s;">+ Add New Slot</button>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
				<button type="submit" class="meditaj-btn-register cyan-btn" style="margin-top:20px;">Save Schedule Grid</button>
			</form>
		</div>

		<!-- Pane: Profile Settings -->
		<div class="tab-pane" id="pane-profile">
			<h3><?php esc_html_e( 'Update Profile Credentials', 'meditaj' ); ?></h3>
			
			<form id="meditaj-profile-form">
				<div class="meditaj-fields-grid text-fields">
					<div class="meditaj-field">
						<label for="profile-fee">Consultation Fee (BDT) *</label>
						<input type="number" id="profile-fee" value="" required>
					</div>
					<div class="meditaj-field">
						<label for="profile-instant-fee">Instant Call Fee (BDT)</label>
						<input type="number" id="profile-instant-fee" value="">
					</div>
					<div class="meditaj-field span-full">
						<label for="profile-bio">Biography *</label>
						<textarea id="profile-bio" rows="6" required></textarea>
					</div>
					<div class="meditaj-field span-full">
						<label>Featured Profile Picture</label>
						<div class="meditaj-modern-upload-wrapper">
							<label for="profile-photo" class="meditaj-modern-upload-label">Choose New Photo</label>
							<input type="file" id="profile-photo" accept="image/*" class="meditaj-hidden-file-input">
							<span class="meditaj-file-name" id="profile-photo-name">No file chosen</span>
						</div>
					</div>
				</div>
				<button type="submit" class="meditaj-btn-register cyan-btn" style="margin-top:20px;">Save Profile Changes</button>
			</form>
		</div>
	</div>
</div>
