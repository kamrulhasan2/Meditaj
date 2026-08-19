<?php
namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AdminPatientsTable extends \WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'eg_care_patient',
				'plural'   => 'eg_care_patients',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Define table columns.
	 */
	public function get_columns() {
		return array(
			'user_id'          => __( 'User ID', 'eg-care' ),
			'display_name'     => __( 'Patient Name', 'eg-care' ),
			'email'            => __( 'Email Address', 'eg-care' ),
			'total_bookings'   => __( 'Total Bookings', 'eg-care' ),
			'total_spent'      => __( 'Total Spent (BDT)', 'eg-care' ),
			'last_appointment' => __( 'Last Consultation', 'eg-care' ),
			'actions'          => __( 'Actions', 'eg-care' ),
		);
	}

	/**
	 * Default column renderer.
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'user_id':
				return sprintf( '<strong>#%d</strong>', $item->user_id );
			case 'display_name':
				return esc_html( $item->display_name );
			case 'email':
				return esc_html( $item->email );
			case 'total_bookings':
				return sprintf( '<span class="eg-care-status-badge status-pending" style="font-size:11px; padding:2px 8px;">%d %s</span>', $item->total_bookings, _n( 'Booking', 'Bookings', $item->total_bookings, 'eg-care' ) );
			case 'total_spent':
				return sprintf( '<strong>%s BDT</strong>', number_format( $item->total_spent, 2 ) );
			case 'last_appointment':
				return $item->last_appointment ? esc_html( mysql2date( 'M d, Y', $item->last_appointment, false ) ) : '-';
			case 'actions':
				$url = admin_url( 'admin.php?page=eg-care-appointments&s=' . urlencode( $item->display_name ) );
				return sprintf( '<a href="%s" class="button button-small eg-care-btn-primary" style="background:#0f766e !important; color:#fff !important; border-color:#0f766e !important;">%s</a>', esc_url( $url ), __( 'View Bookings', 'eg-care' ) );
			default:
				return print_r( $item, true );
		}
	}

	/**
	 * Query database and build list items.
	 */
	public function prepare_items() {
		global $wpdb;
		$table_appointments = DB::get_table( 'appointments' );

		// Column headers
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = array();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Pagination params
		$per_page     = 10;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// Build WHERE search clause
		$where = "WHERE status IN ('confirmed', 'completed', 'ongoing')";
		if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {
			$search = '%' . $wpdb->esc_like( sanitize_text_field( $_REQUEST['s'] ) ) . '%';
			// Query users table matching display_name or user_email
			$matching_users = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->users WHERE display_name LIKE %s OR user_email LIKE %s", $search, $search ) );
			if ( ! empty( $matching_users ) ) {
				$where .= " AND patient_user_id IN (" . implode( ',', array_map( 'intval', $matching_users ) ) . ")";
			} else {
				$where .= " AND 1=0"; // force empty results if no user matches
			}
		}

		// Count total patients with booking entries
		$total_items = $wpdb->get_var( "SELECT COUNT(DISTINCT patient_user_id) FROM $table_appointments $where" );

		// Retrieve patients data grouping by user
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT patient_user_id as user_id, 
						COUNT(id) as total_bookings, 
						SUM(amount) as total_spent, 
						MAX(appointment_date) as last_appointment 
				 FROM $table_appointments 
				 $where 
				 GROUP BY patient_user_id 
				 ORDER BY total_bookings DESC 
				 LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		// Format output data by appending WP user details
		$items = array();
		foreach ( $results as $row ) {
			$user = get_userdata( $row->user_id );
			$items[] = (object) array(
				'user_id'          => $row->user_id,
				'display_name'     => $user ? $user->display_name : 'Patient #' . $row->user_id,
				'email'            => $user ? $user->user_email : '-',
				'total_bookings'   => intval( $row->total_bookings ),
				'total_spent'      => floatval( $row->total_spent ),
				'last_appointment' => $row->last_appointment,
			);
		}

		$this->items = $items;

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}
}
