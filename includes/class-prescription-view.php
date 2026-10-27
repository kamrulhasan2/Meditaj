<?php
/**
 * The prescription as the patient sees it.
 *
 * A standalone, printable page served through admin-post.php rather than a
 * WordPress page, so there is nothing for anyone to set up and nothing in the
 * theme to fight with when it is printed.
 *
 * There is deliberately no nonce on this URL. A nonce is a defence against a
 * request the user did not mean to make, and this request changes nothing; it
 * also expires within a day, which would break the link in an email a week
 * later. What guards the page is the question asked on every visit: is this
 * the patient it belongs to, the doctor who wrote it, or a reviewer - and has
 * it actually been released yet.
 *
 * @package EG Care
 */

namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class PrescriptionView
 */
class PrescriptionView {

	/**
	 * admin-post.php action name.
	 */
	const ACTION = 'eg_care_view_prescription';

	/**
	 * Hook the handler, for signed-in and signed-out visitors alike. The
	 * signed-out case exists only so the link in an email leads to the login
	 * form and then back here, instead of to a blank page.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'serve' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( __CLASS__, 'serve' ) );
	}

	/**
	 * The link to one prescription.
	 *
	 * @param int $id Prescription id.
	 * @return string
	 */
	public static function url( $id ) {
		return add_query_arg(
			array(
				'action' => self::ACTION,
				'id'     => (int) $id,
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * May the current user read this prescription?
	 *
	 * Three people may: the patient it was written for, the doctor who wrote
	 * it, and a reviewer. Everyone else, signed in or not, gets nothing - and
	 * nobody at all sees one that has not been released.
	 *
	 * @param object $row Prescription row.
	 * @return bool
	 */
	public static function may_view( $row ) {
		if ( ! $row || ! \EGCare\Prescriptions::is_visible_to_patient( $row->status ) ) {
			return false;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		if ( ! empty( $row->patient_user_id ) && (int) $row->patient_user_id === (int) $user_id ) {
			return true;
		}

		if ( self::is_issuing_doctor( $row, $user_id ) ) {
			return true;
		}

		return \EGCare\Prescriptions::can_review();
	}

	/**
	 * Whether this user is the doctor whose name is on the prescription.
	 *
	 * @param object $row     Prescription row.
	 * @param int    $user_id Current user.
	 * @return bool
	 */
	private static function is_issuing_doctor( $row, $user_id ) {
		global $wpdb;

		if ( empty( $row->doctor_id ) ) {
			return false;
		}

		$table = \EGCare\DB::get_table( 'doctors_meta' );

		$owner = $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT user_id FROM $table WHERE post_id = %d", intval( $row->doctor_id ) )
		);

		return $owner && (int) $owner === (int) $user_id;
	}

	/**
	 * Serve the page.
	 *
	 * @return void
	 */
	public static function serve() {
		$id  = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		$row = $id ? \EGCare\Prescriptions::get( $id ) : null;

		// Somebody following a link from their inbox on a device that is not
		// signed in. Send them through the login form and straight back here.
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( self::url( $id ) ) );
			exit;
		}

		if ( ! self::may_view( $row ) ) {
			// The same answer whether the prescription is missing, still in
			// review, or simply someone else's. Anything more would let a
			// stranger learn which ids exist.
			wp_die(
				esc_html__( 'This prescription is not available to you.', 'eg-care' ),
				esc_html__( 'Prescription', 'eg-care' ),
				array( 'response' => 403 )
			);
		}

		nocache_headers();

		self::render( $row );
		exit;
	}

	/**
	 * Gather everything the page prints.
	 *
	 * @param object $row Prescription row.
	 * @return array
	 */
	public static function build_context( $row ) {
		global $wpdb;

		$appointments = \EGCare\DB::get_table( 'appointments' );
		$doctors_meta = \EGCare\DB::get_table( 'doctors_meta' );

		$appointment = $wpdb->get_row( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT * FROM $appointments WHERE id = %d", intval( $row->appointment_id ) )
		);

		$doctor = $wpdb->get_row( // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT * FROM $doctors_meta WHERE post_id = %d", intval( $row->doctor_id ) )
		);

		$patient_name = __( 'Patient', 'eg-care' );

		if ( $appointment && ! empty( $appointment->family_member_name ) ) {
			$patient_name = $appointment->family_member_name;
		} elseif ( ! empty( $row->patient_user_id ) ) {
			$user = get_userdata( intval( $row->patient_user_id ) );

			if ( $user ) {
				$patient_name = $user->display_name;
			}
		}

		return array(
			'row'          => $row,
			'content'      => \EGCare\Prescriptions::content_of( $row ),
			'appointment'  => $appointment,
			'doctor'       => $doctor,
			'doctor_name'  => get_the_title( intval( $row->doctor_id ) ),
			'patient_name' => $patient_name,
			'patient_age'  => $appointment && $appointment->family_member_age ? intval( $appointment->family_member_age ) : 0,
			'relation'     => $appointment && $appointment->family_member_relation ? $appointment->family_member_relation : '',
			'issued_on'    => $row->sent_at ? $row->sent_at : $row->updated_at,
		);
	}

	/**
	 * Print the document.
	 *
	 * @param object $row Prescription row.
	 * @return void
	 */
	private static function render( $row ) {
		$context = self::build_context( $row );

		$content  = $context['content'];
		$doctor   = $context['doctor'];
		$site     = get_bloginfo( 'name' );
		$issued   = mysql2date( get_option( 'date_format' ), $context['issued_on'] );

		header( 'Content-Type: text/html; charset=utf-8' );

		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php
		/* translators: %s: patient name. */
		printf( esc_html__( 'Prescription for %s', 'eg-care' ), esc_html( $context['patient_name'] ) );
	?></title>
	<style>
		:root { --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --brand:#0f766e; }
		* { box-sizing: border-box; }
		body {
			margin: 0;
			padding: 24px 16px 64px;
			background: #f1f5f9;
			color: var(--ink);
			font: 15px/1.55 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
		}
		.sheet {
			max-width: 820px;
			margin: 0 auto;
			padding: 36px 40px 48px;
			background: #fff;
			border-radius: 8px;
			box-shadow: 0 2px 14px rgba(15,23,42,.08);
		}
		.masthead {
			display: flex; flex-wrap: wrap; gap: 16px;
			align-items: flex-start; justify-content: space-between;
			padding-bottom: 16px; border-bottom: 3px solid var(--brand);
		}
		.site-name { margin: 0; font-size: 24px; font-weight: 700; color: var(--brand); letter-spacing: -.3px; }
		.ref { text-align: right; font-size: 13px; color: var(--muted); }
		.parties {
			display: flex; flex-wrap: wrap; gap: 24px;
			justify-content: space-between; padding: 18px 0; border-bottom: 1px solid var(--line);
		}
		.party { min-width: 240px; }
		.party h2 { margin: 0 0 2px; font-size: 11px; letter-spacing: .6px; text-transform: uppercase; color: var(--muted); font-weight: 700; }
		.party .who { font-size: 17px; font-weight: 700; }
		.party .line { font-size: 13px; color: var(--muted); }
		section { padding: 18px 0; border-bottom: 1px solid var(--line); }
		section:last-of-type { border-bottom: 0; }
		h3 { margin: 0 0 6px; font-size: 11px; letter-spacing: .6px; text-transform: uppercase; color: var(--muted); font-weight: 700; }
		.body-text { margin: 0; white-space: pre-wrap; }
		.rx { font-size: 30px; font-weight: 700; color: var(--brand); margin: 6px 0 10px; line-height: 1; }
		table { width: 100%; border-collapse: collapse; }
		th, td { padding: 9px 10px; text-align: left; vertical-align: top; }
		thead th { font-size: 11px; letter-spacing: .5px; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--line); }
		tbody tr + tr td { border-top: 1px solid #f1f5f9; }
		tbody td:first-child { font-weight: 600; }
		.num { width: 28px; color: var(--muted); font-weight: 400 !important; }
		.footer { margin-top: 26px; font-size: 12px; color: var(--muted); }
		.sign { margin-top: 36px; text-align: right; }
		.sign .rule { display: inline-block; width: 220px; border-top: 1px solid var(--ink); padding-top: 6px; font-size: 13px; }
		.toolbar { max-width: 820px; margin: 0 auto 14px; display: flex; justify-content: flex-end; gap: 10px; }
		.btn {
			padding: 9px 18px; font: inherit; font-weight: 600; color: #fff;
			background: var(--brand); border: 0; border-radius: 6px; cursor: pointer;
		}
		.btn.ghost { color: var(--brand); background: #fff; border: 1px solid var(--brand); text-decoration: none; }
		@media print {
			body { background: #fff; padding: 0; }
			.sheet { max-width: none; box-shadow: none; border-radius: 0; padding: 0; }
			.toolbar { display: none; }
			section { break-inside: avoid; }
		}
	</style>
</head>
<body>

	<div class="toolbar">
		<a class="btn ghost" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to the site', 'eg-care' ); ?></a>
		<button type="button" class="btn" onclick="window.print()"><?php esc_html_e( 'Print / Save as PDF', 'eg-care' ); ?></button>
	</div>

	<div class="sheet">

		<div class="masthead">
			<div>
				<h1 class="site-name"><?php echo esc_html( $site ); ?></h1>
				<div class="party line"><?php esc_html_e( 'Online consultation prescription', 'eg-care' ); ?></div>
			</div>
			<div class="ref">
				<div><?php esc_html_e( 'Prescription', 'eg-care' ); ?> #<?php echo intval( $context['row']->id ); ?></div>
				<div><?php echo esc_html( $issued ); ?></div>
			</div>
		</div>

		<div class="parties">
			<div class="party">
				<h2><?php esc_html_e( 'Patient', 'eg-care' ); ?></h2>
				<div class="who"><?php echo esc_html( $context['patient_name'] ); ?></div>
				<div class="line">
					<?php
					$bits = array();

					if ( $context['patient_age'] ) {
						/* translators: %d: age in years. */
						$bits[] = sprintf( esc_html__( '%d years', 'eg-care' ), $context['patient_age'] );
					}

					if ( $context['relation'] ) {
						$bits[] = esc_html( $context['relation'] );
					}

					echo esc_html( implode( ' · ', array_map( 'wp_strip_all_tags', $bits ) ) );
					?>
				</div>
			</div>

			<div class="party">
				<h2><?php esc_html_e( 'Prescribed by', 'eg-care' ); ?></h2>
				<div class="who"><?php echo esc_html( $context['doctor_name'] ); ?></div>
				<?php if ( $doctor ) : ?>
					<?php if ( $doctor->degree ) : ?>
						<div class="line"><?php echo esc_html( $doctor->degree ); ?></div>
					<?php endif; ?>
					<?php if ( $doctor->designation ) : ?>
						<div class="line"><?php echo esc_html( $doctor->designation ); ?></div>
					<?php endif; ?>
					<?php if ( $doctor->organization ) : ?>
						<div class="line"><?php echo esc_html( $doctor->organization ); ?></div>
					<?php endif; ?>
					<?php if ( $doctor->bmdc_license_no ) : ?>
						<div class="line"><?php esc_html_e( 'BMDC Reg. No.', 'eg-care' ); ?> <?php echo esc_html( $doctor->bmdc_license_no ); ?></div>
					<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( '' !== $content['complaints'] ) : ?>
		<section>
			<h3><?php esc_html_e( 'Chief complaints', 'eg-care' ); ?></h3>
			<p class="body-text"><?php echo esc_html( $content['complaints'] ); ?></p>
		</section>
		<?php endif; ?>

		<?php if ( '' !== $content['diagnosis'] ) : ?>
		<section>
			<h3><?php esc_html_e( 'Diagnosis', 'eg-care' ); ?></h3>
			<p class="body-text"><?php echo esc_html( $content['diagnosis'] ); ?></p>
		</section>
		<?php endif; ?>

		<?php if ( ! empty( $content['medicines'] ) ) : ?>
		<section>
			<div class="rx">&#8478;</div>
			<table>
				<thead>
					<tr>
						<th class="num"></th>
						<th><?php esc_html_e( 'Medicine', 'eg-care' ); ?></th>
						<th><?php esc_html_e( 'Dose', 'eg-care' ); ?></th>
						<th><?php esc_html_e( 'Duration', 'eg-care' ); ?></th>
						<th><?php esc_html_e( 'Instruction', 'eg-care' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $content['medicines'] as $index => $medicine ) : ?>
					<tr>
						<td class="num"><?php echo intval( $index + 1 ); ?>.</td>
						<td><?php echo esc_html( $medicine['name'] ); ?></td>
						<td><?php echo esc_html( $medicine['dose'] ); ?></td>
						<td><?php echo esc_html( $medicine['duration'] ); ?></td>
						<td><?php echo esc_html( $medicine['instruction'] ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
		<?php endif; ?>

		<?php if ( '' !== $content['advice'] ) : ?>
		<section>
			<h3><?php esc_html_e( 'Advice', 'eg-care' ); ?></h3>
			<p class="body-text"><?php echo esc_html( $content['advice'] ); ?></p>
		</section>
		<?php endif; ?>

		<?php if ( '' !== $content['followup_date'] ) : ?>
		<section>
			<h3><?php esc_html_e( 'Follow-up', 'eg-care' ); ?></h3>
			<p class="body-text"><?php echo esc_html( mysql2date( get_option( 'date_format' ), $content['followup_date'] ) ); ?></p>
		</section>
		<?php endif; ?>

		<div class="sign">
			<div class="rule"><?php echo esc_html( $context['doctor_name'] ); ?></div>
		</div>

		<p class="footer">
			<?php
			printf(
				/* translators: %s: site name. */
				esc_html__( 'Issued electronically through %s after review. No handwritten signature is required.', 'eg-care' ),
				esc_html( $site )
			);
			?>
		</p>
	</div>

</body>
</html>
		<?php
	}
}
