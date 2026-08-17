<?php
namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class AdminDashboard {
	/**
	 * Render the admin dashboard page.
	 */
	public static function render_dashboard() {
		global $wpdb;
		$table_appointments = DB::get_table( 'appointments' );
		$table_doctors      = DB::get_table( 'doctors_meta' );

		$commission_rate = floatval( get_option( 'eg_care_commission_percentage', '15' ) ) / 100;

		// --- 1. Metric Counts ---
		// Total Patients (Unique)
		$total_patients = intval( $wpdb->get_var( "SELECT COUNT(DISTINCT patient_user_id) FROM $table_appointments WHERE status IN ('confirmed', 'completed', 'ongoing')" ) );
		if ( 0 === $total_patients ) {
			$total_patients = intval( $wpdb->get_var( "SELECT COUNT(DISTINCT family_member_name) FROM $table_appointments WHERE status IN ('confirmed', 'completed', 'ongoing')" ) );
		}

		// Total Appointments
		$total_appointments = intval( $wpdb->get_var( "SELECT COUNT(id) FROM $table_appointments WHERE status IN ('confirmed', 'completed', 'ongoing')" ) );

		// Today's Appointments
		$today_date = current_time( 'Y-m-d' );
		$todays_appointments = intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_appointments WHERE appointment_date = %s AND status IN ('confirmed', 'completed', 'ongoing')", $today_date ) ) );

		// Pending Approvals / Verifications
		$pending_notifications = intval( $wpdb->get_var( "SELECT COUNT(id) FROM $table_doctors WHERE verification_status = 'pending'" ) );

		// Total Revenue
		$total_revenue = floatval( $wpdb->get_var( "SELECT SUM(amount) FROM $table_appointments WHERE status IN ('confirmed', 'completed', 'ongoing')" ) );

		// Platform Earnings
		$platform_earnings = $total_revenue * $commission_rate;

		// --- Previous Period Comparisons (Previous 30 Days vs Preceding 30 Days) ---
		$current_30_start = date( 'Y-m-d', strtotime( '-30 days' ) );
		$current_30_end   = date( 'Y-m-d' );
		$prev_30_start    = date( 'Y-m-d', strtotime( '-60 days' ) );
		$prev_30_end      = date( 'Y-m-d', strtotime( '-31 days' ) );

		// Current 30 Days Metrics
		$cur_app_count = intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_appointments WHERE status IN ('confirmed', 'completed', 'ongoing') AND appointment_date BETWEEN %s AND %s", $current_30_start, $current_30_end ) ) );
		$cur_revenue   = floatval( $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM $table_appointments WHERE status IN ('confirmed', 'completed', 'ongoing') AND appointment_date BETWEEN %s AND %s", $current_30_start, $current_30_end ) ) );

		// Previous 30 Days Metrics
		$prev_app_count = intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_appointments WHERE status IN ('confirmed', 'completed', 'ongoing') AND appointment_date BETWEEN %s AND %s", $prev_30_start, $prev_30_end ) ) );
		$prev_revenue   = floatval( $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM $table_appointments WHERE status IN ('confirmed', 'completed', 'ongoing') AND appointment_date BETWEEN %s AND %s", $prev_30_start, $prev_30_end ) ) );

		// Calculate Percentage Changes
		$app_change_pct = ( $prev_app_count > 0 ) ? ( ( $cur_app_count - $prev_app_count ) / $prev_app_count ) * 100 : 0;
		$rev_change_pct = ( $prev_revenue > 0 ) ? ( ( $cur_revenue - $prev_revenue ) / $prev_revenue ) * 100 : 0;

		// --- 2. Chart.js Daily Trend (Last 30 Days) ---
		$trend_data = array();
		for ( $i = 29; $i >= 0; $i-- ) {
			$d = date( 'Y-m-d', strtotime( "-$i days" ) );
			$label = date( 'M d', strtotime( "-$i days" ) );
			$count = intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(id) FROM $table_appointments WHERE appointment_date = %s AND status IN ('confirmed', 'completed', 'ongoing')", $d ) ) );
			$revenue = floatval( $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM $table_appointments WHERE appointment_date = %s AND status IN ('confirmed', 'completed', 'ongoing')", $d ) ) );

			$trend_data[] = array(
				'label'   => $label,
				'count'   => $count,
				'revenue' => $revenue
			);
		}

		// --- 3. Chart.js Appointment Types distribution ---
		$instant_count   = intval( $wpdb->get_var( "SELECT COUNT(id) FROM $table_appointments WHERE appointment_type = 'instant' AND status IN ('confirmed', 'completed', 'ongoing')" ) );
		$scheduled_count = intval( $wpdb->get_var( "SELECT COUNT(id) FROM $table_appointments WHERE appointment_type = 'scheduled' AND status IN ('confirmed', 'completed', 'ongoing')" ) );

		// --- 4. Recent Appointments list (Latest 5 rows) ---
		$recent_appointments = $wpdb->get_results(
			"SELECT * FROM $table_appointments 
			 ORDER BY created_at DESC LIMIT 5"
		);

		// Enqueue scripts with cache-busting
		wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true );
		wp_enqueue_script( 'eg-care-admin-charts-js', EG_CARE_URL . 'assets/js/admin-charts.js', array( 'chart-js' ), time(), true );
		wp_localize_script(
			'eg-care-admin-charts-js',
			'eg-careChartsData',
			array(
				'trend'     => $trend_data,
				'instant'   => $instant_count,
				'scheduled' => $scheduled_count,
			)
		);

		// Output layout markup
		?>
		<div class="wrap eg-care-admin-wrap">
			<div class="eg-care-admin-header" style="margin-bottom: 25px;">
				<h1 class="eg-care-admin-title"><?php esc_html_e( 'EG Care Back-Office Dashboard', 'eg-care' ); ?></h1>
			</div>

			<!-- Grid of 6 Metric Cards -->
			<div class="eg-care-dashboard-grid">
				<!-- Total Patients -->
				<div class="eg-care-metric-card">
					<div class="eg-care-metric-icon-wrap icon-patients">👤</div>
					<div class="eg-care-metric-content">
						<h4 class="eg-care-metric-title"><?php esc_html_e( 'Total Patients', 'eg-care' ); ?></h4>
						<p class="eg-care-metric-value"><?php echo number_format( $total_patients ); ?></p>
						<span class="eg-care-metric-change neutral"><?php esc_html_e( 'Unique individuals', 'eg-care' ); ?></span>
					</div>
				</div>

				<!-- Total Appointments -->
				<div class="eg-care-metric-card">
					<div class="eg-care-metric-icon-wrap icon-appointments">📅</div>
					<div class="eg-care-metric-content">
						<h4 class="eg-care-metric-title"><?php esc_html_e( 'Total Appointments', 'eg-care' ); ?></h4>
						<p class="eg-care-metric-value"><?php echo number_format( $total_appointments ); ?></p>
						<span class="eg-care-metric-change <?php echo $app_change_pct >= 0 ? 'up' : 'down'; ?>">
							<?php echo $app_change_pct >= 0 ? '▲' : '▼'; ?> <?php echo number_format( abs( $app_change_pct ), 1 ); ?>% <?php esc_html_e( 'vs last 30d', 'eg-care' ); ?>
						</span>
					</div>
				</div>

				<!-- Today's Appointments -->
				<div class="eg-care-metric-card">
					<div class="eg-care-metric-icon-wrap icon-today">⏰</div>
					<div class="eg-care-metric-content">
						<h4 class="eg-care-metric-title"><?php esc_html_e( "Today's Consultations", 'eg-care' ); ?></h4>
						<p class="eg-care-metric-value"><?php echo number_format( $todays_appointments ); ?></p>
						<span class="eg-care-metric-change neutral"><?php esc_html_e( 'Pending or confirmed today', 'eg-care' ); ?></span>
					</div>
				</div>

				<!-- Pending Verifications -->
				<div class="eg-care-metric-card">
					<div class="eg-care-metric-icon-wrap icon-pending">🛡️</div>
					<div class="eg-care-metric-content">
						<h4 class="eg-care-metric-title"><?php esc_html_e( 'Pending Doctor Approvals', 'eg-care' ); ?></h4>
						<p class="eg-care-metric-value"><?php echo number_format( $pending_notifications ); ?></p>
						<span class="eg-care-metric-change <?php echo $pending_notifications > 0 ? 'down' : 'neutral'; ?>">
							<?php if ( $pending_notifications > 0 ) : ?>
								<?php esc_html_e( 'Requires attention', 'eg-care' ); ?>
							<?php else : ?>
								<?php esc_html_e( 'All clear', 'eg-care' ); ?>
							<?php endif; ?>
						</span>
					</div>
				</div>

				<!-- Total Revenue -->
				<div class="eg-care-metric-card">
					<div class="eg-care-metric-icon-wrap icon-revenue">৳</div>
					<div class="eg-care-metric-content">
						<h4 class="eg-care-metric-title"><?php esc_html_e( 'Total Revenue', 'eg-care' ); ?></h4>
						<p class="eg-care-metric-value"><?php echo number_format( $total_revenue, 2 ); ?> BDT</p>
						<span class="eg-care-metric-change <?php echo $rev_change_pct >= 0 ? 'up' : 'down'; ?>">
							<?php echo $rev_change_pct >= 0 ? '▲' : '▼'; ?> <?php echo number_format( abs( $rev_change_pct ), 1 ); ?>% <?php esc_html_e( 'vs last 30d', 'eg-care' ); ?>
						</span>
					</div>
				</div>

				<!-- Platform Earnings -->
				<div class="eg-care-metric-card">
					<div class="eg-care-metric-icon-wrap icon-commission">📈</div>
					<div class="eg-care-metric-content">
						<h4 class="eg-care-metric-title"><?php esc_html_e( 'Platform Earnings', 'eg-care' ); ?></h4>
						<p class="eg-care-metric-value"><?php echo number_format( $platform_earnings, 2 ); ?> BDT</p>
						<span class="eg-care-metric-change neutral"><?php echo esc_html( floatval( $commission_rate * 100 ) ); ?>% <?php esc_html_e( 'commission cut', 'eg-care' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Graphs Section -->
			<div class="eg-care-charts-container">
				<!-- Daily Performance Trend (Line chart) -->
				<div class="eg-care-chart-card">
					<h3><?php esc_html_e( 'Appointment Performance Trend (Last 30 Days)', 'eg-care' ); ?></h3>
					<div class="eg-care-chart-wrap">
						<canvas id="eg-careDailyTrendChart"></canvas>
					</div>
				</div>

				<!-- Appointment Types Share (Doughnut chart) -->
				<div class="eg-care-chart-card">
					<h3><?php esc_html_e( 'Consultation Types Share', 'eg-care' ); ?></h3>
					<div class="eg-care-chart-wrap">
						<canvas id="eg-careTypeChart"></canvas>
					</div>
				</div>
			</div>

			<!-- Recent Activity Table Ledger -->
			<div class="eg-care-ledger-card">
				<h3><?php esc_html_e( 'Latest Appointments Ledger', 'eg-care' ); ?></h3>
				<table class="wp-list-table widefat fixed striped posts" style="border: 0; box-shadow: none;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Appointment ID', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Doctor', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Patient Name', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Type', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Date & Time', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Amount', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Status', 'eg-care' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $recent_appointments ) ) : ?>
							<tr>
								<td colspan="7" style="text-align: center; color: #8c8f94; padding: 20px;">
									<?php esc_html_e( 'No recent bookings recorded.', 'eg-care' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $recent_appointments as $app ) : ?>
								<tr>
									<td><strong>#<?php echo esc_html( $app->id ); ?></strong></td>
									<td><?php echo esc_html( get_the_title( $app->doctor_id ) ); ?></td>
									<td>
										<?php 
										if ( $app->family_member_name ) {
											echo esc_html( $app->family_member_name );
										} else {
											$u = get_userdata( $app->patient_user_id );
											echo esc_html( $u ? $u->display_name : 'Patient #' . $app->patient_user_id );
										}
										?>
									</td>
									<td>
										<span class="eg-care-status-badge <?php echo 'instant' === $app->appointment_type ? 'status-approved' : 'status-pending'; ?>" style="font-size: 10px; padding: 2px 6px;">
											<?php echo esc_html( strtoupper( $app->appointment_type ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( $app->appointment_date . ' @ ' . date( 'g:i A', strtotime( $app->appointment_time ) ) ); ?></td>
									<td><?php echo esc_html( $app->amount ); ?> BDT</td>
									<td>
										<span class="eg-care-status-badge <?php echo 'completed' === $app->status ? 'status-approved' : ( 'cancelled' === $app->status ? 'status-rejected' : 'status-pending' ); ?>" style="font-size: 10px; padding: 2px 6px;">
											<?php echo esc_html( strtoupper( $app->status ) ); ?>
										</span>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}
}
