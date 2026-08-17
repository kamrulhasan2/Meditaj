<?php
/**
 * Template for Doctor-Facing Dashboard.
 *
 * @package EG Care
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$user = wp_get_current_user();
?>

<div class="eg-care-dashboard-wrapper">
	<!-- Header Banner -->
	<div class="eg-care-dashboard-header">
		<div class="welcome-text">
			<h2>Welcome back, <?php echo esc_html( $user->display_name ); ?>!</h2>
			<p class="role-caption"><?php esc_html_e( 'Registered EG Care Specialist', 'eg-care' ); ?></p>
		</div>
		<div class="instant-status-toggle">
			<span class="toggle-label"><?php esc_html_e( 'Go Online (Instant Call)', 'eg-care' ); ?></span>
			<label class="eg-care-switch">
				<input type="checkbox" id="dashboard-instant-toggle">
				<span class="slider round"></span>
			</label>
		</div>
	</div>

	<!-- Stats Overview Cards -->
	<div class="eg-care-stats-row">
		<div class="eg-care-stat-card">
			<div class="stat-icon purple">📅</div>
			<div class="stat-details">
				<span class="stat-num" id="stat-total-appointments">0</span>
				<span class="stat-label"><?php esc_html_e( 'Total Consultations', 'eg-care' ); ?></span>
			</div>
		</div>
		<div class="eg-care-stat-card">
			<div class="stat-icon green">৳</div>
			<div class="stat-details">
				<span class="stat-num" id="stat-net-earnings">0.00 BDT</span>
				<span class="stat-label"><?php esc_html_e( 'Net Earnings (85%)', 'eg-care' ); ?></span>
				<span class="stat-help" id="stat-gross-breakdown">Gross: 0.00 BDT</span>
			</div>
		</div>
		<div class="eg-care-stat-card">
			<div class="stat-icon blue">⚡</div>
			<div class="stat-details">
				<span class="stat-num" id="stat-online-badge">Offline</span>
				<span class="stat-label"><?php esc_html_e( 'Status', 'eg-care' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Tab Navigation -->
	<div class="eg-care-dashboard-tabs">
		<button type="button" class="tab-trigger active" data-tab="appointments"><?php esc_html_e( 'Today\'s Appointments', 'eg-care' ); ?></button>
		<button type="button" class="tab-trigger" data-tab="upcoming"><?php esc_html_e( 'Upcoming Consultations', 'eg-care' ); ?></button>
		<button type="button" class="tab-trigger" data-tab="slots"><?php esc_html_e( 'Schedules & Slots', 'eg-care' ); ?></button>
		<button type="button" class="tab-trigger" data-tab="profile"><?php esc_html_e( 'Profile Settings', 'eg-care' ); ?></button>
	</div>

	<!-- Tab Content Panes -->
	<div class="eg-care-tab-contents">
		<!-- Pane: Today's Appointments -->
		<div class="tab-pane active" id="pane-appointments">
			<h3><?php esc_html_e( 'Today\'s Appointed Consultations', 'eg-care' ); ?></h3>
			<div class="eg-care-table-responsive">
				<table class="eg-care-dashboard-table">
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
			<h3><?php esc_html_e( 'Upcoming Scheduled Appointments', 'eg-care' ); ?></h3>
			<div class="eg-care-table-responsive">
				<table class="eg-care-dashboard-table">
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
			<h3><?php esc_html_e( 'Configure Availability Slot Grid', 'eg-care' ); ?></h3>
			<p class="pane-desc"><?php esc_html_e( 'Define standard hourly duration slots for each day of the week to enable patients to book appointments.', 'eg-care' ); ?></p>
			
			<form id="eg-care-slots-form">
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
							<button type="button" class="eg-care-add-slot-btn" style="margin-top: 8px; font-size: 13px; padding: 8px 16px; background: #e0f2fe; color: #0369a1; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; transition: all 0.2s;">+ Add New Slot</button>
						</div>
					</div>
					<?php endforeach; ?>
				</div>
				<button type="submit" class="eg-care-btn-register cyan-btn" style="margin-top:20px;">Save Schedule Grid</button>
			</form>
		</div>

		<!-- Pane: Profile Settings -->
		<div class="tab-pane" id="pane-profile">
			<h3><?php esc_html_e( 'Update Profile Credentials', 'eg-care' ); ?></h3>
			
			<form id="eg-care-profile-form">
				<div class="eg-care-fields-grid text-fields">
					<div class="eg-care-field">
						<label for="profile-fee">Consultation Fee (BDT) *</label>
						<input type="number" id="profile-fee" value="" required>
					</div>
					<div class="eg-care-field">
						<label for="profile-instant-fee">Instant Call Fee (BDT)</label>
						<input type="number" id="profile-instant-fee" value="">
					</div>
					<div class="eg-care-field span-full">
						<label for="profile-bio">Biography *</label>
						<textarea id="profile-bio" rows="6" required></textarea>
					</div>
					<div class="eg-care-field span-full">
						<label>Featured Profile Picture</label>
						<div class="eg-care-modern-upload-wrapper">
							<label for="profile-photo" class="eg-care-modern-upload-label">Choose New Photo</label>
							<input type="file" id="profile-photo" accept="image/*" class="eg-care-hidden-file-input">
							<span class="eg-care-file-name" id="profile-photo-name">No file chosen</span>
						</div>
					</div>
				</div>
				<button type="submit" class="eg-care-btn-register cyan-btn" style="margin-top:20px;">Save Profile Changes</button>
			</form>
		</div>
	</div>
</div>
