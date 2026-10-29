<?php
/**
 * The moderator's side of prescriptions.
 *
 * Two screens: a queue of prescriptions waiting to be read, and the one where
 * a reviewer reads, corrects and releases a single prescription. Everything
 * that changes a record happens on admin_init, before a byte of the page is
 * printed, so each action can finish with a redirect and a refresh cannot
 * replay it.
 *
 * @package EG Care
 */

namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class AdminPrescriptions
 */
class AdminPrescriptions {

	/**
	 * Which admin menu these screens hang under.
	 *
	 * The Doctors menu rather than EG Care's own: prescriptions are something
	 * the doctors on this site produce, and that is where whoever reviews them
	 * goes looking. One constant, because the parent decides both where the
	 * item appears and what the page's URL is - get those two out of step and
	 * every link 404s.
	 */
	const MENU_PARENT = 'edit.php?post_type=doctors';

	/**
	 * The queue.
	 */
	const LIST_PAGE = 'eg-care-prescriptions';

	/**
	 * The single-prescription screen. Registered as a submenu so wp-admin will
	 * route to it, then hidden from the sidebar on admin_head.
	 */
	const REVIEW_PAGE = 'eg-care-review-prescription';

	/**
	 * Hook the action handler.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
	}

	/**
	 * The address of one of these screens.
	 *
	 * A page registered under a parent other than admin.php is served from
	 * that parent, so the URL has to carry it. Everything builds its links
	 * through here rather than writing admin.php by hand.
	 *
	 * @param string $page Page slug.
	 * @param array  $args Extra query arguments.
	 * @return string
	 */
	public static function page_url( $page, $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'post_type' => 'doctors',
					'page'      => $page,
				),
				$args
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Link to the review screen for one prescription.
	 *
	 * @param int $id Prescription id.
	 * @return string
	 */
	public static function review_url( $id ) {
		return self::page_url( self::REVIEW_PAGE, array( 'id' => (int) $id ) );
	}

	/**
	 * Link back to the queue, optionally carrying a result message.
	 *
	 * @param string $message Message key, or an empty string.
	 * @return string
	 */
	public static function list_url( $message = '' ) {
		return self::page_url(
			self::LIST_PAGE,
			$message ? array( 'eg_care_message' => $message ) : array()
		);
	}

	/**
	 * A one-click approve link for the queue.
	 *
	 * wp_nonce_url() returns its output already escaped for HTML, which is
	 * right here because this goes straight into an href attribute.
	 *
	 * @param int $id Prescription id.
	 * @return string
	 */
	public static function approve_url( $id ) {
		return wp_nonce_url(
			self::page_url(
				self::LIST_PAGE,
				array(
					'eg_care_rx'      => 'approve',
					'prescription_id' => (int) $id,
				)
			),
			'eg_care_approve_prescription_' . (int) $id
		);
	}

	// ---------------------------------------------------------------- actions

	/**
	 * Run whichever action the request is asking for, then redirect.
	 *
	 * @return void
	 */
	public static function handle_actions() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( self::LIST_PAGE !== $page && self::REVIEW_PAGE !== $page ) {
			return;
		}

		if ( ! \EGCare\Prescriptions::can_review() ) {
			return;
		}

		if ( self::LIST_PAGE === $page ) {
			self::handle_quick_approve();
			return;
		}

		self::handle_review_submission();
	}

	/**
	 * The Approve link in the queue, for a prescription that needs no edits.
	 *
	 * @return void
	 */
	private static function handle_quick_approve() {
		if ( ! isset( $_GET['eg_care_rx'], $_GET['prescription_id'] ) ) {
			return;
		}

		if ( 'approve' !== sanitize_key( wp_unslash( $_GET['eg_care_rx'] ) ) ) {
			return;
		}

		$id = intval( $_GET['prescription_id'] );

		check_admin_referer( 'eg_care_approve_prescription_' . $id );

		$result = \EGCare\Prescriptions::approve( $id );

		wp_safe_redirect( self::list_url( is_wp_error( $result ) ? 'error:' . $result->get_error_code() : 'sent' ) );
		exit;
	}

	/**
	 * The review screen's two buttons.
	 *
	 * @return void
	 */
	private static function handle_review_submission() {
		if ( ! isset( $_POST['eg_care_rx_review_nonce'] ) ) {
			return;
		}

		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

		check_admin_referer( 'eg_care_review_prescription_' . $id, 'eg_care_rx_review_nonce' );

		// Whatever the reviewer has on screen, cleaned the same way the
		// doctor's own submission was.
		$content = \EGCare\Prescriptions::sanitize_content( self::posted_content() );

		if ( isset( $_POST['eg_care_rx_send_back'] ) ) {
			$note   = isset( $_POST['moderator_note'] ) ? wp_unslash( $_POST['moderator_note'] ) : '';
			$result = \EGCare\Prescriptions::send_back( $id, $note );

			if ( is_wp_error( $result ) ) {
				wp_safe_redirect( add_query_arg( 'eg_care_message', 'error:' . $result->get_error_code(), self::review_url( $id ) ) );
				exit;
			}

			wp_safe_redirect( self::list_url( 'returned' ) );
			exit;
		}

		if ( isset( $_POST['eg_care_rx_approve'] ) ) {
			$result = \EGCare\Prescriptions::approve( $id, $content );

			if ( is_wp_error( $result ) ) {
				wp_safe_redirect( add_query_arg( 'eg_care_message', 'error:' . $result->get_error_code(), self::review_url( $id ) ) );
				exit;
			}

			wp_safe_redirect( self::list_url( 'sent' ) );
			exit;
		}
	}

	/**
	 * Rebuild the content array out of the posted form.
	 *
	 * Sanitizing is left to Prescriptions::sanitize_content(), which is the
	 * same gate the doctor's own submission goes through - one set of rules,
	 * not two.
	 *
	 * @return array
	 */
	private static function posted_content() {
		$medicines = array();

		if ( isset( $_POST['medicine'] ) && is_array( $_POST['medicine'] ) ) {
			foreach ( wp_unslash( $_POST['medicine'] ) as $row ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				if ( is_array( $row ) ) {
					$medicines[] = $row;
				}
			}
		}

		return array(
			'complaints'    => isset( $_POST['complaints'] ) ? wp_unslash( $_POST['complaints'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'diagnosis'     => isset( $_POST['diagnosis'] ) ? wp_unslash( $_POST['diagnosis'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'advice'        => isset( $_POST['advice'] ) ? wp_unslash( $_POST['advice'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'followup_date' => isset( $_POST['followup_date'] ) ? wp_unslash( $_POST['followup_date'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'medicines'     => $medicines,
		);
	}

	// ----------------------------------------------------------------- shared

	/**
	 * Who the consultation was for.
	 *
	 * @param object|null $appointment Appointment row.
	 * @return string
	 */
	public static function patient_name( $appointment ) {
		if ( ! $appointment ) {
			return __( 'Unknown', 'eg-care' );
		}

		if ( ! empty( $appointment->family_member_name ) ) {
			return $appointment->family_member_name;
		}

		if ( ! empty( $appointment->patient_user_id ) ) {
			$user = get_userdata( intval( $appointment->patient_user_id ) );

			if ( $user ) {
				return $user->display_name;
			}
		}

		return __( 'Patient', 'eg-care' );
	}

	/**
	 * Print whatever the last action had to say.
	 *
	 * @return void
	 */
	public static function print_notice() {
		if ( empty( $_GET['eg_care_message'] ) ) {
			return;
		}

		$message = sanitize_text_field( wp_unslash( $_GET['eg_care_message'] ) );

		$known = array(
			'sent'     => array( 'success', __( 'Prescription approved and released to the patient.', 'eg-care' ) ),
			'returned' => array( 'warning', __( 'Sent back to the doctor with your note.', 'eg-care' ) ),
		);

		if ( isset( $known[ $message ] ) ) {
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $known[ $message ][0] ),
				esc_html( $known[ $message ][1] )
			);
			return;
		}

		if ( 0 !== strpos( $message, 'error:' ) ) {
			return;
		}

		$errors = array(
			'eg_care_prescription_missing'        => __( 'That prescription no longer exists.', 'eg-care' ),
			'eg_care_prescription_not_reviewable' => __( 'That prescription is not waiting for review - someone may have dealt with it already.', 'eg-care' ),
			'eg_care_prescription_note_required'  => __( 'Write a note telling the doctor what needs changing.', 'eg-care' ),
			'eg_care_prescription_empty'          => __( 'A prescription needs a diagnosis or at least one medicine.', 'eg-care' ),
			'eg_care_prescription_save_failed'    => __( 'The database refused the change. Nothing was saved.', 'eg-care' ),
		);

		$code = substr( $message, strlen( 'error:' ) );
		$text = isset( $errors[ $code ] ) ? $errors[ $code ] : __( 'That did not work.', 'eg-care' );

		printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $text ) );
	}

	// ------------------------------------------------------------------ queue

	/**
	 * Render the review queue.
	 *
	 * @return void
	 */
	public static function render_list_page() {
		if ( ! \EGCare\Prescriptions::can_review() ) {
			wp_die( esc_html__( 'You are not allowed to review prescriptions.', 'eg-care' ) );
		}

		$table = new AdminPrescriptionsTable();
		$table->prepare_items();

		?>
		<div class="wrap eg-care-admin-wrap">
			<div class="eg-care-admin-header" style="margin-bottom: 20px;">
				<h1 class="eg-care-admin-title"><?php esc_html_e( 'Prescriptions', 'eg-care' ); ?></h1>
				<p class="description">
					<?php esc_html_e( 'A doctor never sends a prescription straight to the patient. Read it here, correct anything that needs correcting, and release it.', 'eg-care' ); ?>
				</p>
			</div>

			<?php self::print_notice(); ?>

			<form method="get" action="">
				<input type="hidden" name="post_type" value="doctors" />
				<input type="hidden" name="page" value="<?php echo esc_attr( self::LIST_PAGE ); ?>" />
				<?php
				$table->search_box( __( 'Search patients', 'eg-care' ), 'eg_care_search_rx' );
				$table->views();
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	// ----------------------------------------------------------- review screen

	/**
	 * Render the single-prescription screen.
	 *
	 * @return void
	 */
	public static function render_review_page() {
		if ( ! \EGCare\Prescriptions::can_review() ) {
			wp_die( esc_html__( 'You are not allowed to review prescriptions.', 'eg-care' ) );
		}

		global $wpdb;

		$id  = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		$row = \EGCare\Prescriptions::get( $id );

		if ( ! $row ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Prescription', 'eg-care' ) . '</h1>';
			echo '<div class="notice notice-error"><p>' . esc_html__( 'That prescription no longer exists.', 'eg-care' ) . '</p></div>';
			echo '<p><a href="' . esc_url( self::list_url() ) . '">' . esc_html__( 'Back to the queue', 'eg-care' ) . '</a></p></div>';
			return;
		}

		$appointments = \EGCare\DB::get_table( 'appointments' );
		$appointment  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $appointments WHERE id = %d", intval( $row->appointment_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$content    = \EGCare\Prescriptions::content_of( $row );
		$reviewable = \EGCare\Prescriptions::is_reviewable( $row->status );

		// Existing lines plus a few blanks, so a reviewer can add a medicine
		// without the screen needing any JavaScript of its own.
		$medicines = $content['medicines'];
		for ( $i = 0; $i < 3; $i++ ) {
			$medicines[] = array(
				'name'        => '',
				'dose'        => '',
				'duration'    => '',
				'instruction' => '',
			);
		}

		?>
		<div class="wrap eg-care-admin-wrap">
			<h1 class="eg-care-admin-title">
				<?php esc_html_e( 'Review prescription', 'eg-care' ); ?>
				<span style="font-weight:400;color:#64748b;">#<?php echo intval( $row->id ); ?></span>
			</h1>

			<p><a href="<?php echo esc_url( self::list_url() ); ?>">&larr; <?php esc_html_e( 'Back to the queue', 'eg-care' ); ?></a></p>

			<?php self::print_notice(); ?>

			<?php if ( ! $reviewable ) : ?>
				<div class="notice notice-info">
					<p>
						<?php
						printf(
							/* translators: %s: current status, already translated. */
							esc_html__( 'This prescription is %s, so it is shown here read-only.', 'eg-care' ),
							'<strong>' . esc_html( strtolower( \EGCare\Prescriptions::status_label( $row->status ) ) ) . '</strong>'
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<table class="widefat" style="margin:16px 0;max-width:900px;">
				<tbody>
					<tr>
						<th style="width:180px;"><?php esc_html_e( 'Patient', 'eg-care' ); ?></th>
						<td>
							<?php echo esc_html( self::patient_name( $appointment ) ); ?>
							<?php if ( $appointment && $appointment->family_member_relation ) : ?>
								<span style="color:#64748b;">(<?php echo esc_html( $appointment->family_member_relation ); ?>)</span>
							<?php endif; ?>
							<?php if ( $appointment && $appointment->family_member_age ) : ?>
								<span style="color:#64748b;">, <?php echo intval( $appointment->family_member_age ); ?> <?php esc_html_e( 'years', 'eg-care' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Doctor', 'eg-care' ); ?></th>
						<td><?php echo esc_html( get_the_title( intval( $row->doctor_id ) ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Consultation', 'eg-care' ); ?></th>
						<td>
							<?php
							if ( $appointment && $appointment->appointment_date ) {
								echo esc_html( $appointment->appointment_date . ' ' . substr( (string) $appointment->appointment_time, 0, 5 ) );
							} elseif ( $appointment ) {
								esc_html_e( 'Instant call', 'eg-care' );
							} else {
								esc_html_e( 'Appointment record missing', 'eg-care' );
							}
							?>
							<span style="color:#64748b;">
								&middot; <?php esc_html_e( 'submitted', 'eg-care' ); ?> <?php echo esc_html( $row->updated_at ); ?>
							</span>
						</td>
					</tr>
					<?php if ( $appointment && $appointment->symptom_notes ) : ?>
					<tr>
						<th><?php esc_html_e( 'Patient said', 'eg-care' ); ?></th>
						<td><?php echo esc_html( $appointment->symptom_notes ); ?></td>
					</tr>
					<?php endif; ?>
					<?php
					$reports = $appointment ? \EGCare\SecureUploads::attachment_ids( $appointment->uploaded_files ) : array();
					if ( $reports ) :
						?>
					<tr>
						<th><?php esc_html_e( 'Patient attached', 'eg-care' ); ?></th>
						<td>
							<?php foreach ( $reports as $index => $attachment_id ) : ?>
								<a href="<?php echo esc_url( \EGCare\SecureUploads::get_report_url( $appointment->id, $attachment_id ) ); ?>" target="_blank" rel="noopener">
									<?php echo esc_html( get_the_title( $attachment_id ) ? get_the_title( $attachment_id ) : sprintf( /* translators: %d: index */ __( 'Report %d', 'eg-care' ), $index + 1 ) ); ?>
								</a><?php echo count( $reports ) - 1 > $index ? ', ' : ''; ?>
							<?php endforeach; ?>
						</td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( self::review_url( $row->id ) ); ?>">
				<?php wp_nonce_field( 'eg_care_review_prescription_' . $row->id, 'eg_care_rx_review_nonce' ); ?>

				<table class="form-table" role="presentation" style="max-width:900px;">
					<tr>
						<th scope="row"><label for="complaints"><?php esc_html_e( 'Chief complaints', 'eg-care' ); ?></label></th>
						<td><textarea name="complaints" id="complaints" rows="2" class="large-text" <?php disabled( ! $reviewable ); ?>><?php echo esc_textarea( $content['complaints'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="diagnosis"><?php esc_html_e( 'Diagnosis', 'eg-care' ); ?></label></th>
						<td><textarea name="diagnosis" id="diagnosis" rows="2" class="large-text" <?php disabled( ! $reviewable ); ?>><?php echo esc_textarea( $content['diagnosis'] ); ?></textarea></td>
					</tr>
				</table>

				<h2>Rx</h2>
				<p class="description">
					<?php esc_html_e( 'Clear a medicine name to drop that line. Blank lines at the bottom are there if you need to add one.', 'eg-care' ); ?>
				</p>

				<table class="widefat striped" style="max-width:900px;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Medicine', 'eg-care' ); ?></th>
							<th style="width:130px;"><?php esc_html_e( 'Dose', 'eg-care' ); ?></th>
							<th style="width:130px;"><?php esc_html_e( 'Duration', 'eg-care' ); ?></th>
							<th><?php esc_html_e( 'Instruction', 'eg-care' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $medicines as $index => $medicine ) : ?>
						<tr>
							<td><input type="text" class="regular-text" style="width:100%;" name="medicine[<?php echo intval( $index ); ?>][name]" value="<?php echo esc_attr( $medicine['name'] ); ?>" <?php disabled( ! $reviewable ); ?>></td>
							<td><input type="text" style="width:100%;" name="medicine[<?php echo intval( $index ); ?>][dose]" value="<?php echo esc_attr( $medicine['dose'] ); ?>" <?php disabled( ! $reviewable ); ?>></td>
							<td><input type="text" style="width:100%;" name="medicine[<?php echo intval( $index ); ?>][duration]" value="<?php echo esc_attr( $medicine['duration'] ); ?>" <?php disabled( ! $reviewable ); ?>></td>
							<td><input type="text" style="width:100%;" name="medicine[<?php echo intval( $index ); ?>][instruction]" value="<?php echo esc_attr( $medicine['instruction'] ); ?>" <?php disabled( ! $reviewable ); ?>></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<table class="form-table" role="presentation" style="max-width:900px;">
					<tr>
						<th scope="row"><label for="advice"><?php esc_html_e( 'Advice', 'eg-care' ); ?></label></th>
						<td><textarea name="advice" id="advice" rows="3" class="large-text" <?php disabled( ! $reviewable ); ?>><?php echo esc_textarea( $content['advice'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="followup_date"><?php esc_html_e( 'Follow-up date', 'eg-care' ); ?></label></th>
						<td><input type="date" name="followup_date" id="followup_date" value="<?php echo esc_attr( $content['followup_date'] ); ?>" <?php disabled( ! $reviewable ); ?>></td>
					</tr>
					<?php if ( $reviewable ) : ?>
					<tr>
						<th scope="row"><label for="moderator_note"><?php esc_html_e( 'Note to the doctor', 'eg-care' ); ?></label></th>
						<td>
							<textarea name="moderator_note" id="moderator_note" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Only needed if you are sending it back', 'eg-care' ); ?>"></textarea>
						</td>
					</tr>
					<?php elseif ( $row->moderator_note ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Note to the doctor', 'eg-care' ); ?></th>
						<td><?php echo esc_html( $row->moderator_note ); ?></td>
					</tr>
					<?php endif; ?>
				</table>

				<?php if ( $reviewable ) : ?>
				<p class="submit" style="display:flex;gap:12px;align-items:center;">
					<button type="submit" name="eg_care_rx_approve" class="button button-primary button-large">
						<?php esc_html_e( 'Approve & send to patient', 'eg-care' ); ?>
					</button>
					<button type="submit" name="eg_care_rx_send_back" class="button button-large">
						<?php esc_html_e( 'Send back to doctor', 'eg-care' ); ?>
					</button>
				</p>
				<?php endif; ?>
			</form>
		</div>
		<?php
	}
}
