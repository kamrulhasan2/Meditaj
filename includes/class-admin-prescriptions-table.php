<?php
/**
 * The prescription review queue.
 *
 * A WP_List_Table so it looks and behaves like every other admin list on the
 * site: the same view links with counts, the same search box, the same
 * pagination. The default view is the one that is actually a job - the ones
 * waiting to be read.
 *
 * @package EG Care
 */

namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class AdminPrescriptionsTable
 */
class AdminPrescriptionsTable extends \WP_List_Table {

	/**
	 * Rows per page.
	 */
	const PER_PAGE = 20;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'eg_care_prescription',
				'plural'   => 'eg_care_prescriptions',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Which status the current view is showing.
	 *
	 * Unfiltered means the queue, not everything: a reviewer opening this
	 * screen wants the work, and "All" is one click away.
	 *
	 * @return string 'submitted', 'returned', 'sent', 'draft' or 'all'.
	 */
	private function current_view() {
		$view = isset( $_GET['status_filter'] ) ? sanitize_key( wp_unslash( $_GET['status_filter'] ) ) : \EGCare\Prescriptions::STATUS_SUBMITTED;

		$allowed = array_merge( \EGCare\Prescriptions::statuses(), array( 'all' ) );

		return in_array( $view, $allowed, true ) ? $view : \EGCare\Prescriptions::STATUS_SUBMITTED;
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'id'          => __( 'ID', 'eg-care' ),
			'patient'     => __( 'Patient', 'eg-care' ),
			'doctor'      => __( 'Doctor', 'eg-care' ),
			'summary'     => __( 'Prescription', 'eg-care' ),
			'updated'     => __( 'Last change', 'eg-care' ),
			'status'      => __( 'Status', 'eg-care' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return array(
			'id'      => array( 'id', true ),
			'updated' => array( 'updated_at', false ),
		);
	}

	/**
	 * Fallback renderer.
	 *
	 * @param object $item        Row.
	 * @param string $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				return '#' . intval( $item->id );

			case 'doctor':
				return esc_html( get_the_title( intval( $item->doctor_id ) ) );

			case 'updated':
				return esc_html( $item->updated_at );

			case 'status':
				$classes = array(
					\EGCare\Prescriptions::STATUS_DRAFT     => 'status-pending',
					\EGCare\Prescriptions::STATUS_SUBMITTED => 'status-pending',
					\EGCare\Prescriptions::STATUS_RETURNED  => 'status-rejected',
					\EGCare\Prescriptions::STATUS_SENT      => 'status-approved',
				);

				$class = isset( $classes[ $item->status ] ) ? $classes[ $item->status ] : 'status-pending';

				return sprintf(
					'<span class="eg-care-status-badge %s">%s</span>',
					esc_attr( $class ),
					esc_html( \EGCare\Prescriptions::status_label( $item->status ) )
				);
		}

		return '';
	}

	/**
	 * Patient column, carrying the row actions.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_patient( $item ) {
		// The patient's name is whatever was typed at booking time, so it is
		// escaped here and the column is assembled from escaped pieces only.
		$name = esc_html( \EGCare\AdminPrescriptions::patient_name( $item ) );

		if ( $item->family_member_relation ) {
			$name .= ' <span style="color:#64748b;">(' . esc_html( $item->family_member_relation ) . ')</span>';
		}

		$actions = array(
			'review' => sprintf(
				'<a href="%s">%s</a>',
				esc_url( \EGCare\AdminPrescriptions::review_url( $item->id ) ),
				esc_html__( 'Review', 'eg-care' )
			),
		);

		// The one-click path, for a prescription that needs no correcting.
		if ( \EGCare\Prescriptions::is_reviewable( $item->status ) ) {
			$actions['approve'] = sprintf(
				'<a href="%s" style="color:#15803d;font-weight:600;">%s</a>',
				\EGCare\AdminPrescriptions::approve_url( $item->id ),
				esc_html__( 'Approve &amp; send', 'eg-care' )
			);
		}

		return sprintf(
			'<strong><a href="%s">%s</a></strong>%s',
			esc_url( \EGCare\AdminPrescriptions::review_url( $item->id ) ),
			$name, // Already escaped above.
			$this->row_actions( $actions )
		);
	}

	/**
	 * A glance at what is in the prescription, so an obvious one can be
	 * approved without opening it.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	protected function column_summary( $item ) {
		$content = \EGCare\Prescriptions::content_of( $item );
		$bits    = array();

		if ( '' !== $content['diagnosis'] ) {
			$bits[] = '<strong>' . esc_html( wp_trim_words( $content['diagnosis'], 8, '&hellip;' ) ) . '</strong>';
		}

		$count = count( $content['medicines'] );

		if ( $count ) {
			$names = wp_list_pluck( array_slice( $content['medicines'], 0, 3 ), 'name' );
			$line  = esc_html( implode( ', ', $names ) );

			if ( $count > 3 ) {
				$line .= sprintf(
					/* translators: %d: how many more medicines. */
					esc_html__( ' +%d more', 'eg-care' ),
					$count - 3
				);
			}

			$bits[] = $line;
		}

		if ( ! $bits ) {
			return '<span style="color:#94a3b8;">' . esc_html__( 'Empty', 'eg-care' ) . '</span>';
		}

		return implode( '<br>', $bits );
	}

	/**
	 * View links with counts.
	 *
	 * @return array
	 */
	protected function get_views() {
		$counts  = \EGCare\Prescriptions::status_counts();
		$current = $this->current_view();
		$base    = admin_url( 'admin.php?page=' . \EGCare\AdminPrescriptions::LIST_PAGE );

		$labels = array(
			\EGCare\Prescriptions::STATUS_SUBMITTED => __( 'Waiting for review', 'eg-care' ),
			\EGCare\Prescriptions::STATUS_RETURNED  => __( 'Returned', 'eg-care' ),
			\EGCare\Prescriptions::STATUS_SENT      => __( 'Sent', 'eg-care' ),
			\EGCare\Prescriptions::STATUS_DRAFT     => __( 'Drafts', 'eg-care' ),
		);

		$views = array();

		foreach ( $labels as $status => $label ) {
			$views[ $status ] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'status_filter', $status, $base ) ),
				$status === $current ? 'current' : '',
				esc_html( $label ),
				isset( $counts[ $status ] ) ? $counts[ $status ] : 0
			);
		}

		$views['all'] = sprintf(
			'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
			esc_url( add_query_arg( 'status_filter', 'all', $base ) ),
			'all' === $current ? 'current' : '',
			esc_html__( 'All', 'eg-care' ),
			array_sum( $counts )
		);

		return $views;
	}

	/**
	 * What to show when the view is empty.
	 *
	 * @return void
	 */
	public function no_items() {
		if ( \EGCare\Prescriptions::STATUS_SUBMITTED === $this->current_view() ) {
			esc_html_e( 'Nothing waiting for review. Prescriptions appear here the moment a doctor submits one.', 'eg-care' );
			return;
		}

		esc_html_e( 'No prescriptions in this view.', 'eg-care' );
	}

	/**
	 * Query and paginate.
	 *
	 * @return void
	 */
	public function prepare_items() {
		global $wpdb;

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$prescriptions = \EGCare\Prescriptions::table();
		$appointments  = \EGCare\DB::get_table( 'appointments' );

		$where = array( '1=1' );
		$view  = $this->current_view();

		if ( 'all' !== $view ) {
			$where[] = $wpdb->prepare( 'p.status = %s', $view );
		}

		if ( ! empty( $_REQUEST['s'] ) ) {
			$search  = '%' . $wpdb->esc_like( sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) ) . '%';
			$where[] = $wpdb->prepare( 'a.family_member_name LIKE %s', $search );
		}

		$where_sql = implode( ' AND ', $where );

		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'id';
		$orderby = in_array( $orderby, array( 'id', 'updated_at' ), true ) ? $orderby : 'id';
		$order   = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC';

		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * self::PER_PAGE;

		$total_items = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.PreparedSQL
			"SELECT COUNT(p.id)
			   FROM $prescriptions p
		  LEFT JOIN $appointments a ON a.id = p.appointment_id
			  WHERE $where_sql"
		);

		$this->items = $wpdb->get_results( // phpcs:ignore WordPress.DB.PreparedSQL
			$wpdb->prepare(
				"SELECT p.*,
				        a.family_member_name,
				        a.family_member_relation,
				        a.family_member_age,
				        a.appointment_date,
				        a.appointment_time,
				        a.appointment_type
				   FROM $prescriptions p
			  LEFT JOIN $appointments a ON a.id = p.appointment_id
				  WHERE $where_sql
			   ORDER BY p.$orderby $order
				  LIMIT %d OFFSET %d",
				self::PER_PAGE,
				$offset
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => self::PER_PAGE,
				'total_pages' => (int) ceil( $total_items / self::PER_PAGE ),
			)
		);
	}
}
