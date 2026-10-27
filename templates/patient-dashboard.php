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

// Prescriptions a reviewer has released. One query for the whole page, and
// only the released ones: a prescription still being written or still in
// review is simply not in this map, which is how the dashboard is unable to
// show one early even by accident.
$eg_care_sent_prescriptions = array();

if ( $appointments ) {
	$eg_care_appt_ids = implode( ',', array_map( 'intval', wp_list_pluck( $appointments, 'id' ) ) );
	$eg_care_rx_table = Prescriptions::table();

	$eg_care_rx_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id, appointment_id FROM {$eg_care_rx_table}
			  WHERE appointment_id IN ( {$eg_care_appt_ids} ) AND status = %s
		   ORDER BY id ASC",
			Prescriptions::STATUS_SENT
		)
	);

	// Ascending, so that when a prescription has been corrected the later
	// one overwrites the earlier in this map and the patient is pointed at
	// the current copy.
	foreach ( (array) $eg_care_rx_rows as $eg_care_rx_row ) {
		$eg_care_sent_prescriptions[ intval( $eg_care_rx_row->appointment_id ) ] = intval( $eg_care_rx_row->id );
	}
}

$eg_care_prescription_link = static function ( $app ) use ( $eg_care_sent_prescriptions ) {
	if ( ! isset( $eg_care_sent_prescriptions[ intval( $app->id ) ] ) ) {
		return '';
	}

	return '<div class="eg-care-prescription-link" style="font-size:12px;margin-top:4px;">&#128138; <a href="'
		. esc_url( PrescriptionView::url( $eg_care_sent_prescriptions[ intval( $app->id ) ] ) ) . '" target="_blank" rel="noopener">'
		. esc_html__( 'Prescription', 'eg-care' ) . '</a></div>';
};

// Categorize appointments
$active_consultations = array();
$upcoming_bookings    = array();
$past_history         = array();

$today_str = current_time( 'Y-m-d' );
$now_ts    = time();

foreach ( $appointments as $appt ) {
	if ( 'completed' === $appt->status || 'cancelled' === $appt->status || 'no_show' === $appt->status ) {
		$past_history[] = $appt;
		continue;
	}

	// A call somebody has already stepped into is 'ongoing', not 'confirmed'.
	// It has to stay in Active Consultations - that row is the only way back in
	// after a connection drops.
	$eg_care_open = in_array( $appt->status, array( 'confirmed', 'ongoing' ), true );

	// Active consultations: Today's scheduled or instant call
	if ( 'instant' === $appt->appointment_type && $eg_care_open ) {
		$active_consultations[] = $appt;
	} elseif ( $appt->appointment_date === $today_str && $eg_care_open ) {
		$active_consultations[] = $appt;
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

	<?php
	/**
	 * After the stats, before the consultation sections. Other plugins put
	 * their own part of the patient's account here (E-Care Health Services adds
	 * a tab bar for its lab test, caregiver and ambulance bookings).
	 *
	 * @param int $user_id The signed-in patient.
	 */
	do_action( 'eg_care_patient_dashboard_after_stats', $user_id );
	?>

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
							// One source of truth: the same window generate_video_token()
							// enforces, so the button is never enabled for a call the
							// endpoint would refuse, or greyed out for one it would allow.
							$eg_care_window = \EGCare\RestControllerBooking::call_window( $app );
							$is_call_window = null !== $eg_care_window
								&& $now_ts >= $eg_care_window['start']
								&& $now_ts <= $eg_care_window['end'];
							
							$time_val = mysql2date( 'g:i A', $app->appointment_time, false );
						?>
							<tr style="border-bottom: 1px solid #f1f5f9;">
								<td data-label="<?php esc_attr_e( 'Doctor', 'eg-care' ); ?>" style="padding: 15px 0; font-weight: 600; color: #0f172a;"><?php echo esc_html( get_the_title( $app->doctor_id ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'Patient Name', 'eg-care' ); ?>"><?php echo esc_html( $app->family_member_name ? $app->family_member_name : wp_get_current_user()->display_name ); ?> (<?php echo esc_html( $app->family_member_relation ? $app->family_member_relation : 'Self' ); ?>)<?php echo $eg_care_report_links( $app ) . $eg_care_prescription_link( $app ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td data-label="<?php esc_attr_e( 'Schedule Time', 'eg-care' ); ?>"><?php echo esc_html( $time_val ); ?></td>
								<td data-label="<?php esc_attr_e( 'Type', 'eg-care' ); ?>">
									<span style="font-size: 11px; background: <?php echo 'instant' === $app->appointment_type ? '#e0f2fe; color: #0369a1;' : '#f0fdf4; color: #166534;'; ?> padding: 2px 8px; border-radius: 12px; font-weight: 600;">
										<?php echo esc_html( strtoupper( $app->appointment_type ) ); ?>
									</span>
								</td>
								<td data-label="<?php esc_attr_e( 'Actions', 'eg-care' ); ?>" style="text-align: right;">
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
								<td data-label="<?php esc_attr_e( 'Doctor', 'eg-care' ); ?>" style="padding: 15px 0; font-weight: 600; color: #0f172a;"><?php echo esc_html( get_the_title( $app->doctor_id ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'Patient Name', 'eg-care' ); ?>"><?php echo esc_html( $app->family_member_name ? $app->family_member_name : wp_get_current_user()->display_name ); ?><?php echo $eg_care_report_links( $app ) . $eg_care_prescription_link( $app ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td data-label="<?php esc_attr_e( 'Date & Time', 'eg-care' ); ?>"><?php echo esc_html( $date_val . ' @ ' . $time_val ); ?></td>
								<td data-label="<?php esc_attr_e( 'Fee paid', 'eg-care' ); ?>"><?php echo esc_html( $app->amount ); ?> BDT</td>
								<td data-label="<?php esc_attr_e( 'Status', 'eg-care' ); ?>">
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
							<th><?php esc_html_e( 'Prescription', 'eg-care' ); ?></th>
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
								<td data-label="<?php esc_attr_e( 'Doctor', 'eg-care' ); ?>" style="padding: 15px 0; font-weight: 600; color: #0f172a;"><?php echo esc_html( get_the_title( $app->doctor_id ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'Patient Name', 'eg-care' ); ?>"><?php echo esc_html( $app->family_member_name ? $app->family_member_name : wp_get_current_user()->display_name ); ?><?php echo $eg_care_report_links( $app ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td data-label="<?php esc_attr_e( 'Date & Time', 'eg-care' ); ?>"><?php echo esc_html( $date_val . ' @ ' . $time_val ); ?></td>
								<td data-label="<?php esc_attr_e( 'Paid Amount', 'eg-care' ); ?>"><?php echo esc_html( $app->amount ); ?> BDT</td>
								<td data-label="<?php esc_attr_e( 'Status', 'eg-care' ); ?>">
									<span style="font-size: 11px; background: <?php echo 'completed' === $app->status ? '#dcfce7; color: #166534;' : '#fee2e2; color: #991b1b;'; ?> padding: 2px 8px; border-radius: 12px; font-weight: 600;">
										<?php echo esc_html( strtoupper( $app->status ) ); ?>
									</span>
								</td>
								<td data-label="<?php esc_attr_e( 'Prescription', 'eg-care' ); ?>">
									<?php if ( isset( $eg_care_sent_prescriptions[ intval( $app->id ) ] ) ) : ?>
										<a href="<?php echo esc_url( PrescriptionView::url( $eg_care_sent_prescriptions[ intval( $app->id ) ] ) ); ?>" target="_blank" rel="noopener" style="color: #0f766e; font-weight: 600; font-size: 13px;">
											&#128138; <?php esc_html_e( 'View', 'eg-care' ); ?>
										</a>
									<?php else : ?>
										<span style="color: #94a3b8;">-</span>
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Feedback', 'eg-care' ); ?>" style="text-align: right;">
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

	<!-- Family members: the people this account books consultations for. -->
	<div class="eg-care-dashboard-section" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 25px; margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
		<div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 8px;">
			<h3 style="margin: 0; font-size: 18px; color: #1e293b; font-weight: 700; border-left: 4px solid #0f766e; padding-left: 10px;">
				<?php esc_html_e( 'My Family Members', 'eg-care' ); ?>
			</h3>
			<button type="button" id="eg-care-family-add-trigger" class="eg-care-btn-register cyan-btn" style="width: auto; padding: 9px 18px; font-size: 14px;">
				<?php esc_html_e( 'Add someone', 'eg-care' ); ?>
			</button>
		</div>
		<p style="color: #64748b; font-size: 14px; margin: 0 0 18px;">
			<?php esc_html_e( 'Save the people you book for and you will not have to type their details again. Past appointments keep the details they were booked with.', 'eg-care' ); ?>
		</p>

		<div id="eg-care-family-list">
			<div class="eg-care-loading-spinner small"></div>
		</div>

		<div class="eg-care-family-form" id="eg-care-family-editor" hidden>
			<h4 id="eg-care-family-editor-title"><?php esc_html_e( 'Add someone', 'eg-care' ); ?></h4>
			<input type="hidden" id="egfm-id" value="">
			<div class="eg-care-fields-grid text-fields">
				<div class="eg-care-field">
					<label for="egfm-name"><?php esc_html_e( 'Full name *', 'eg-care' ); ?></label>
					<input type="text" id="egfm-name" placeholder="<?php esc_attr_e( 'Full name', 'eg-care' ); ?>">
				</div>
				<div class="eg-care-field">
					<label for="egfm-relation"><?php esc_html_e( 'Relation *', 'eg-care' ); ?></label>
					<select id="egfm-relation">
						<option value="father"><?php esc_html_e( 'Father', 'eg-care' ); ?></option>
						<option value="mother"><?php esc_html_e( 'Mother', 'eg-care' ); ?></option>
						<option value="spouse"><?php esc_html_e( 'Spouse', 'eg-care' ); ?></option>
						<option value="son"><?php esc_html_e( 'Son', 'eg-care' ); ?></option>
						<option value="daughter"><?php esc_html_e( 'Daughter', 'eg-care' ); ?></option>
						<option value="brother"><?php esc_html_e( 'Brother', 'eg-care' ); ?></option>
						<option value="sister"><?php esc_html_e( 'Sister', 'eg-care' ); ?></option>
						<option value="other"><?php esc_html_e( 'Other', 'eg-care' ); ?></option>
					</select>
				</div>
				<div class="eg-care-field">
					<label for="egfm-dob"><?php esc_html_e( 'Date of birth', 'eg-care' ); ?></label>
					<input type="date" id="egfm-dob" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>">
				</div>
				<div class="eg-care-field">
					<label for="egfm-gender"><?php esc_html_e( 'Gender', 'eg-care' ); ?></label>
					<select id="egfm-gender">
						<option value=""><?php esc_html_e( 'Prefer not to say', 'eg-care' ); ?></option>
						<option value="male"><?php esc_html_e( 'Male', 'eg-care' ); ?></option>
						<option value="female"><?php esc_html_e( 'Female', 'eg-care' ); ?></option>
						<option value="other"><?php esc_html_e( 'Other', 'eg-care' ); ?></option>
					</select>
				</div>
				<div class="eg-care-field">
					<label for="egfm-phone"><?php esc_html_e( 'Phone', 'eg-care' ); ?></label>
					<input type="tel" id="egfm-phone" placeholder="<?php esc_attr_e( 'Optional', 'eg-care' ); ?>">
				</div>
				<div class="eg-care-field">
					<label for="egfm-email"><?php esc_html_e( 'Email', 'eg-care' ); ?></label>
					<input type="email" id="egfm-email" placeholder="<?php esc_attr_e( 'Optional', 'eg-care' ); ?>">
				</div>
			</div>
			<p class="eg-care-family-form-error" id="egfm-error" hidden></p>
			<div class="eg-care-family-form-actions">
				<button type="button" id="egfm-save" class="eg-care-btn-register cyan-btn"><?php esc_html_e( 'Save', 'eg-care' ); ?></button>
				<button type="button" id="egfm-cancel" class="eg-care-btn-secondary"><?php esc_html_e( 'Cancel', 'eg-care' ); ?></button>
			</div>
		</div>
	</div>

	<?php
	/**
	 * The end of the patient dashboard, for other plugins' sections.
	 *
	 * @param int $user_id The signed-in patient.
	 */
	do_action( 'eg_care_patient_dashboard_end', $user_id );
	?>
</div>

