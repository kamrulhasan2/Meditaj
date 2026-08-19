<?php
namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $wpdb;
$user_id = get_current_user_id();
$table_appointments = DB::get_table( 'appointments' );

// Retrieve all appointments for this user
$appointments = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM $table_appointments 
		 WHERE patient_user_id = %d 
		 ORDER BY appointment_date DESC, appointment_time DESC",
		$user_id
	)
);

// Calculate stats
$total_bookings = count( $appointments );
$total_spent    = 0;
$completed_calls = 0;

foreach ( $appointments as $a ) {
	if ( in_array( $a->status, array( 'confirmed', 'completed', 'ongoing' ) ) ) {
		$total_spent += floatval( $a->amount );
	}
	if ( 'completed' === $a->status ) {
		$completed_calls++;
	}
}

// Nonced links to the reports a patient attached. The files sit outside the
// public uploads tree, so a link is the only way to reach one.
$eg_care_report_links = static function ( $app ) {
	$links = array();

	foreach ( SecureUploads::attachment_ids( $app->uploaded_files ) as $attachment_id ) {
		$name    = get_the_title( $attachment_id );
		$links[] = '<a href="' . esc_url( SecureUploads::get_report_url( $app->id, $attachment_id ) ) . '" target="_blank" rel="noopener">'
			. esc_html( $name ? $name : __( 'Report', 'eg-care' ) ) . '</a>';
	}

	if ( ! $links ) {
		return '';
	}

	return '<div class="eg-care-report-links" style="font-size:12px;margin-top:4px;">&#128206; ' . implode( ', ', $links ) . '</div>';
};

// Categorize appointments
$active_consultations = array();
$upcoming_bookings    = array();
$past_history         = array();

$today_str = current_time( 'Y-m-d' );
$now_ts    = current_time( 'timestamp' );

foreach ( $appointments as $appt ) {
	if ( 'completed' === $appt->status || 'cancelled' === $appt->status || 'no_show' === $appt->status ) {
		$past_history[] = $appt;
		continue;
	}

	// Active consultations: Today's scheduled or instant call
	if ( 'instant' === $appt->appointment_type && 'confirmed' === $appt->status ) {
		$active_consultations[] = $appt;
	} elseif ( $appt->appointment_date === $today_str && 'confirmed' === $appt->status ) {
		$active_consultations[] = $appt;
	} elseif ( $appt->appointment_date > $today_str && 'confirmed' === $appt->status ) {
		$upcoming_bookings[] = $appt;
	} else {
		$upcoming_bookings[] = $appt;
	}
}

?>
<div class="eg-care-patient-dashboard" style="max-width: 1000px; margin: 0 auto; font-family: -apple-system, BlinkMacSystemFont, sans-serif; padding: 20px;">
	
	<!-- Header -->
	<div class="eg-care-patient-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 30px;">
		<div>
			<h2 style="margin: 0; font-size: 26px; color: #0f172a; font-weight: 700;"><?php printf( esc_html__( 'Hello, %s', 'eg-care' ), esc_html( wp_get_current_user()->display_name ) ); ?></h2>
			<p style="margin: 5px 0 0; color: #64748b; font-size: 14px;"><?php esc_html_e( 'Manage your telemedicine appointments and consulting history.', 'eg-care' ); ?></p>
		</div>
		<a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>" style="padding: 8px 16px; border: 1px solid #cbd5e1; border-radius: 6px; text-decoration: none; color: #64748b; font-size: 14px; font-weight: 600; background: #fff; transition: background 0.2s;">
			<?php esc_html_e( 'Log Out', 'eg-care' ); ?>
		</a>
	</div>

	<!-- Stats Grid -->
	<div class="eg-care-patient-stats" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px;">
		<!-- Total Bookings -->
		<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
			<span style="font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase;"><?php esc_html_e( 'Total Consultations', 'eg-care' ); ?></span>
			<h3 style="margin: 10px 0 0; font-size: 28px; color: #0f766e; font-weight: 700;"><?php echo intval( $total_bookings ); ?></h3>
		</div>
		<!-- Total Spent -->
		<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
			<span style="font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase;"><?php esc_html_e( 'Total Spent', 'eg-care' ); ?></span>
			<h3 style="margin: 10px 0 0; font-size: 28px; color: #0f766e; font-weight: 700;"><?php echo number_format( $total_spent, 2 ); ?> BDT</h3>
		</div>
		<!-- Completed Calls -->
		<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
			<span style="font-size: 13px; color: #64748b; font-weight: 600; text-transform: uppercase;"><?php esc_html_e( 'Completed Consultations', 'eg-care' ); ?></span>
			<h3 style="margin: 10px 0 0; font-size: 28px; color: #0f766e; font-weight: 700;"><?php echo intval( $completed_calls ); ?></h3>
		</div>
	</div>

	<!-- 1. Active / Today's Consultations -->
	<div class="eg-care-dashboard-section" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 25px; margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
		<h3 style="margin: 0 0 20px; font-size: 18px; color: #1e293b; font-weight: 700; border-left: 4px solid #0f766e; padding-left: 10px;">
			<?php esc_html_e( "Today's Consultations", 'eg-care' ); ?>
		</h3>
		<?php if ( empty( $active_consultations ) ) : ?>
			<p style="color: #64748b; font-size: 14px; margin: 0;"><?php esc_html_e( 'You have no consultations scheduled for today.', 'eg-care' ); ?></p>
		<?php else : ?>
			<div class="eg-care-table-responsive">
				<table class="eg-care-patient-table">
					<thead>
						<tr style="border-bottom: 2px solid #f1f5f9; color: #64748b;">
							<th style="padding: 10px 0;"><?php esc_html_e( 'Doctor', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Patient Name', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Schedule Time', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Type', 'eg-care' ); ?></th>
							<th style="text-align: right;"><?php esc_html_e( 'Actions', 'eg-care' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $active_consultations as $app ) : 
							// Check call window
							$is_call_window = false;
							if ( 'instant' === $app->appointment_type ) {
								$is_call_window = true;
							} else {
								$app_time_ts = strtotime( $app->appointment_date . ' ' . $app->appointment_time );
								$start_allowed = $app_time_ts - ( 15 * MINUTE_IN_SECONDS );
								$end_allowed   = $app_time_ts + ( 60 * MINUTE_IN_SECONDS );
								if ( $now_ts >= $start_allowed && $now_ts <= $end_allowed ) {
									$is_call_window = true;
								}
							}
							
							$time_val = mysql2date( 'g:i A', $app->appointment_time, false );
						?>
							<tr style="border-bottom: 1px solid #f1f5f9;">
								<td style="padding: 15px 0; font-weight: 600; color: #0f172a;"><?php echo esc_html( get_the_title( $app->doctor_id ) ); ?></td>
								<td><?php echo esc_html( $app->family_member_name ? $app->family_member_name : wp_get_current_user()->display_name ); ?> (<?php echo esc_html( $app->family_member_relation ? $app->family_member_relation : 'Self' ); ?>)<?php echo $eg_care_report_links( $app ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td><?php echo esc_html( $time_val ); ?></td>
								<td>
									<span style="font-size: 11px; background: <?php echo 'instant' === $app->appointment_type ? '#e0f2fe; color: #0369a1;' : '#f0fdf4; color: #166534;'; ?> padding: 2px 8px; border-radius: 12px; font-weight: 600;">
										<?php echo esc_html( strtoupper( $app->appointment_type ) ); ?>
									</span>
								</td>
								<td style="text-align: right;">
									<button type="button" class="eg-care-btn-join-call <?php echo $is_call_window ? 'active' : 'disabled'; ?>" data-id="<?php echo intval( $app->id ); ?>" <?php echo $is_call_window ? '' : 'disabled'; ?> style="padding: 6px 14px; border-radius: 6px; font-weight: 600; border: none; font-size: 13px;">
										<?php esc_html_e( 'Join Call', 'eg-care' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<!-- 2. Upcoming Scheduled Consultations -->
	<div class="eg-care-dashboard-section" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 25px; margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
		<h3 style="margin: 0 0 20px; font-size: 18px; color: #1e293b; font-weight: 700; border-left: 4px solid #0f766e; padding-left: 10px;">
			<?php esc_html_e( 'Upcoming Scheduled Consultations', 'eg-care' ); ?>
		</h3>
		<?php if ( empty( $upcoming_bookings ) ) : ?>
			<p style="color: #64748b; font-size: 14px; margin: 0;"><?php esc_html_e( 'You have no upcoming consultations booked.', 'eg-care' ); ?></p>
		<?php else : ?>
			<div class="eg-care-table-responsive">
				<table class="eg-care-patient-table">
					<thead>
						<tr style="border-bottom: 2px solid #f1f5f9; color: #64748b;">
							<th style="padding: 10px 0;"><?php esc_html_e( 'Doctor', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Patient Name', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Date & Time', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Fee paid', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Status', 'eg-care' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $upcoming_bookings as $app ) : 
							$time_val = mysql2date( 'g:i A', $app->appointment_time, false );
							$date_val = mysql2date( 'M d, Y', $app->appointment_date, false );
						?>
							<tr style="border-bottom: 1px solid #f1f5f9;">
								<td style="padding: 15px 0; font-weight: 600; color: #0f172a;"><?php echo esc_html( get_the_title( $app->doctor_id ) ); ?></td>
								<td><?php echo esc_html( $app->family_member_name ? $app->family_member_name : wp_get_current_user()->display_name ); ?><?php echo $eg_care_report_links( $app ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td><?php echo esc_html( $date_val . ' @ ' . $time_val ); ?></td>
								<td><?php echo esc_html( $app->amount ); ?> BDT</td>
								<td>
									<span style="font-size: 11px; background: <?php echo 'pending_payment' === $app->status ? '#fef3c7; color: #92400e;' : '#f0fdf4; color: #166534;'; ?> padding: 2px 8px; border-radius: 12px; font-weight: 600;">
										<?php echo esc_html( strtoupper( $app->status ) ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>

	<!-- 3. Consultation History & Reviews -->
	<div class="eg-care-dashboard-section" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 25px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
		<h3 style="margin: 0 0 20px; font-size: 18px; color: #1e293b; font-weight: 700; border-left: 4px solid #0f766e; padding-left: 10px;">
			<?php esc_html_e( 'Consultation History', 'eg-care' ); ?>
		</h3>
		<?php if ( empty( $past_history ) ) : ?>
			<p style="color: #64748b; font-size: 14px; margin: 0;"><?php esc_html_e( 'Your past consultation history is empty.', 'eg-care' ); ?></p>
		<?php else : ?>
			<div class="eg-care-table-responsive">
				<table class="eg-care-patient-table">
					<thead>
						<tr style="border-bottom: 2px solid #f1f5f9; color: #64748b;">
							<th style="padding: 10px 0;"><?php esc_html_e( 'Doctor', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Patient Name', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Date & Time', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Paid Amount', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Status', 'eg-care' ); ?></th>
							<th style="text-align: right;"><?php esc_html_e( 'Feedback', 'eg-care' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $past_history as $app ) : 
							$time_val = mysql2date( 'g:i A', $app->appointment_time, false );
							$date_val = mysql2date( 'M d, Y', $app->appointment_date, false );
							
							// Check if reviewed already
							$reviewed = $wpdb->get_var( $wpdb->prepare( "SELECT rating FROM {$wpdb->prefix}eg_care_reviews WHERE appointment_id = %d", $app->id ) );
						?>
							<tr style="border-bottom: 1px solid #f1f5f9;">
								<td style="padding: 15px 0; font-weight: 600; color: #0f172a;"><?php echo esc_html( get_the_title( $app->doctor_id ) ); ?></td>
								<td><?php echo esc_html( $app->family_member_name ? $app->family_member_name : wp_get_current_user()->display_name ); ?><?php echo $eg_care_report_links( $app ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td><?php echo esc_html( $date_val . ' @ ' . $time_val ); ?></td>
								<td><?php echo esc_html( $app->amount ); ?> BDT</td>
								<td>
									<span style="font-size: 11px; background: <?php echo 'completed' === $app->status ? '#dcfce7; color: #166534;' : '#fee2e2; color: #991b1b;'; ?> padding: 2px 8px; border-radius: 12px; font-weight: 600;">
										<?php echo esc_html( strtoupper( $app->status ) ); ?>
									</span>
								</td>
								<td style="text-align: right;">
									<?php if ( 'completed' === $app->status ) : ?>
										<?php if ( $reviewed ) : ?>
											<span style="color: #fbbf24; font-weight: 600; font-size: 13px;">★ <?php echo intval( $reviewed ); ?> / 5</span>
										<?php else : ?>
											<button type="button" class="eg-care-btn-rate-appt" data-id="<?php echo intval( $app->id ); ?>" style="padding: 4px 10px; border-radius: 4px; background: #0f766e; color: #fff; border: none; font-size: 12px; font-weight: 600; cursor: pointer; transition: opacity 0.2s;">
												<?php esc_html_e( 'Rate Doctor', 'eg-care' ); ?>
											</button>
										<?php endif; ?>
									<?php else : ?>
										<span style="color: #94a3b8;">-</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	// Rate Doctor click handler for historical completed items
	const rateButtons = document.querySelectorAll('.eg-care-btn-rate-appt');
	rateButtons.forEach(btn => {
		btn.addEventListener('click', function() {
			const apptId = this.getAttribute('data-id');
			
			// Open the exact same modal dynamically!
			const reviewModal = document.createElement('div');
			reviewModal.id = 'eg-care-review-modal';
			reviewModal.style = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.85); z-index: 2000000; display: flex; align-items: center; justify-content: center; font-family: -apple-system, BlinkMacSystemFont, sans-serif;';
			
			reviewModal.innerHTML = `
				<div style="background: #fff; padding: 30px; border-radius: 12px; max-width: 450px; width: 90%; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); text-align: center;">
					<h3 style="margin: 0 0 10px; font-size: 20px; color: #0f172a; font-weight: 700;">Rate Your Consultation</h3>
					<p style="color: #64748b; font-size: 14px; margin: 0 0 20px;">Please take a moment to rate your experience with the doctor.</p>
					
					<!-- Star Rating Row -->
					<div id="star-rating-container" style="display: flex; justify-content: center; gap: 8px; margin-bottom: 20px; font-size: 36px; cursor: pointer; user-select: none;">
						<span class="star" data-value="1" style="color: #cbd5e1; transition: color 0.15s;">★</span>
						<span class="star" data-value="2" style="color: #cbd5e1; transition: color 0.15s;">★</span>
						<span class="star" data-value="3" style="color: #cbd5e1; transition: color 0.15s;">★</span>
						<span class="star" data-value="4" style="color: #cbd5e1; transition: color 0.15s;">★</span>
						<span class="star" data-value="5" style="color: #cbd5e1; transition: color 0.15s;">★</span>
					</div>
					
					<!-- Feedback Input -->
					<textarea id="review-comment" placeholder="Write a public review (optional)..." style="width: 100%; height: 100px; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 20px; font-size: 14px; resize: none; box-sizing: border-box; outline: none;"></textarea>
					
					<!-- Buttons -->
					<div style="display: flex; gap: 12px; justify-content: flex-end;">
						<button id="btn-skip-review" type="button" style="padding: 10px 16px; border-radius: 6px; border: 1px solid #cbd5e1; background: #fff; cursor: pointer; color: #475569; font-weight: 600; font-size: 14px;">Cancel</button>
						<button id="btn-submit-review" type="button" disabled style="padding: 10px 20px; border-radius: 6px; border: none; background: #0f766e; color: #fff; cursor: not-allowed; opacity: 0.5; font-weight: 600; font-size: 14px;">Submit</button>
					</div>
				</div>
			`;
			document.body.appendChild(reviewModal);

			let selectedRating = 0;
			const stars = reviewModal.querySelectorAll('.star');
			const submitBtn = reviewModal.querySelector('#btn-submit-review');

			stars.forEach(star => {
				star.addEventListener('mouseover', function() {
					const val = parseInt(this.getAttribute('data-value'));
					stars.forEach(s => {
						const sVal = parseInt(s.getAttribute('data-value'));
						s.style.color = sVal <= val ? '#fbbf24' : '#cbd5e1';
					});
				});

				star.addEventListener('mouseout', function() {
					stars.forEach(s => {
						const sVal = parseInt(s.getAttribute('data-value'));
						s.style.color = sVal <= selectedRating ? '#fbbf24' : '#cbd5e1';
					});
				});

				star.addEventListener('click', function() {
					selectedRating = parseInt(this.getAttribute('data-value'));
					stars.forEach(s => {
						const sVal = parseInt(s.getAttribute('data-value'));
						s.style.color = sVal <= selectedRating ? '#fbbf24' : '#cbd5e1';
					});
					submitBtn.disabled = false;
					submitBtn.style.cursor = 'pointer';
					submitBtn.style.opacity = '1';
				});
			});

			// Skip
			reviewModal.querySelector('#btn-skip-review').addEventListener('click', function() {
				reviewModal.remove();
			});

			// Submit
			submitBtn.addEventListener('click', function() {
				if (selectedRating === 0) return;
				
				submitBtn.disabled = true;
				submitBtn.textContent = 'Submitting...';
				const comment = reviewModal.querySelector('#review-comment').value.trim();

				fetch(egCareSettings.restUrl + 'appointments/' + apptId + '/reviews', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': egCareSettings.nonce
					},
					body: JSON.stringify({
						rating: selectedRating,
						comment: comment
					})
				})
				.then(res => {
					if (!res.ok) throw new Error('API error');
					return res.json();
				})
				.then(() => {
					reviewModal.remove();
					window.location.reload();
				})
				.catch(err => {
					console.error('Failed to submit review:', err);
					alert('Failed to submit review, please try again.');
					submitBtn.disabled = false;
					submitBtn.textContent = 'Submit';
				});
			});
		});
	});
});
</script>
