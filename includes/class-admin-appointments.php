<?php
namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AdminAppointmentsTable extends \WP_List_Table {

	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'eg_care_appointment',
				'plural'   => 'eg_care_appointments',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Define table columns.
	 */
	public function get_columns() {
		return array(
			'cb'             => '<input type="checkbox" />',
			'id'             => __( 'ID', 'eg-care' ),
			'patient_name'   => __( 'Patient Name', 'eg-care' ),
			'doctor_name'    => __( 'Doctor Name', 'eg-care' ),
			'type'           => __( 'Type', 'eg-care' ),
			'schedule'       => __( 'Schedule', 'eg-care' ),
			'amount'         => __( 'Amount', 'eg-care' ),
			'payment_status' => __( 'Payment Status', 'eg-care' ),
			'status'         => __( 'Status', 'eg-care' ),
		);
	}

	/**
	 * Columns eligible for sorting.
	 */
	public function get_sortable_columns() {
		return array(
			'id'       => array( 'id', true ),
			'amount'   => array( 'amount', false ),
			'schedule' => array( 'appointment_date', false ),
		);
	}

	/**
	 * Render bulk checkbox column.
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="bulk-appointments[]" value="%d" />', $item->id );
	}

	/**
	 * Default column renderer fallback.
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
				return sprintf( '<strong>#%d</strong>', $item->id );
			case 'type':
				return sprintf( '<span class="eg-care-status-badge %s">%s</span>', 'instant' === $item->appointment_type ? 'status-approved' : 'status-pending', strtoupper( $item->appointment_type ) );
			case 'amount':
				return sprintf( '%s BDT', number_format( $item->amount, 2 ) );
			case 'payment_status':
				$badge_class = 'paid' === $item->payment_status ? 'status-approved' : ( 'refunded' === $item->payment_status ? 'status-rejected' : 'status-pending' );
				return sprintf( '<span class="eg-care-status-badge %s">%s</span>', $badge_class, strtoupper( $item->payment_status ) );
			case 'status':
				$badge_class = 'completed' === $item->status ? 'status-approved' : ( 'cancelled' === $item->status ? 'status-rejected' : 'status-pending' );
				return sprintf( '<span class="eg-care-status-badge %s">%s</span>', $badge_class, strtoupper( $item->status ) );
			default:
				return print_r( $item, true );
		}
	}

	/**
	 * Render patient name with actions menu.
	 */
	protected function column_patient_name( $item ) {
		$name = $item->family_member_name;
		if ( empty( $name ) ) {
			$u = get_userdata( $item->patient_user_id );
			$name = $u ? $u->display_name : 'Patient #' . $item->patient_user_id;
		}

		$relation = $item->family_member_relation ? $item->family_member_relation : 'Self';

		// Action links.
		$actions = array();
		if ( 'confirmed' === $item->status || 'pending_payment' === $item->status ) {
			$actions['cancel'] = sprintf(
				'<a href="%s" style="color: #b91c1c;">%s</a>',
				wp_nonce_url( add_query_arg( array( 'eg_care_action' => 'cancel', 'appt_id' => $item->id ) ), 'eg_care_cancel_appointment' ),
				__( 'Cancel Booking', 'eg-care' )
			);
		}
		if ( 'confirmed' === $item->status || 'ongoing' === $item->status ) {
			$actions['complete'] = sprintf(
				'<a href="%s" style="color: #15803d;">%s</a>',
				wp_nonce_url( add_query_arg( array( 'eg_care_action' => 'complete', 'appt_id' => $item->id ) ), 'eg_care_complete_appointment' ),
				__( 'Mark Completed', 'eg-care' )
			);
		}

		return sprintf(
			'<strong>%1$s</strong> <span style="font-size:10px; background:#e2e8f0; padding:2px 6px; border-radius:4px; margin-left:5px; color:#475569;">%2$s</span>%3$s',
			esc_html( $name ),
			esc_html( $relation ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Render doctor name column.
	 */
	protected function column_doctor_name( $item ) {
		return esc_html( get_the_title( $item->doctor_id ) );
	}

	/**
	 * Render schedule column.
	 */
	protected function column_schedule( $item ) {
		if ( 'instant' === $item->appointment_type ) {
			return sprintf( '<em>%s</em><br/><span style="font-size:11px; color:#64748b;">%s</span>', __( 'Instant Call', 'eg-care' ), date( 'M d, Y', strtotime( $item->created_at ) ) );
		}
		return sprintf(
			'<strong>%s</strong><br/><span style="font-size:11px; color:#64748b;">%s</span>',
			date( 'M d, Y', strtotime( $item->appointment_date ) ),
			date( 'g:i A', strtotime( $item->appointment_time ) )
		);
	}

	/**
	 * Bulk actions list.
	 */
	protected function get_bulk_actions() {
		return array(
			'bulk-cancel'   => __( 'Bulk Cancel', 'eg-care' ),
			'bulk-complete' => __( 'Bulk Complete', 'eg-care' ),
		);
	}

	/**
	 * View links filters (tabs at the top of the table).
	 */
	protected function get_views() {
		global $wpdb;
		$table = DB::get_table( 'appointments' );

		$status_counts = $wpdb->get_results(
			"SELECT status, COUNT(id) as count FROM $table GROUP BY status",
			OBJECT_K
		);

		$total = 0;
		foreach ( $status_counts as $s ) {
			$total += intval( $s->count );
		}

		$current = isset( $_GET['status_filter'] ) ? sanitize_key( $_GET['status_filter'] ) : 'all';

		$views = array(
			'all' => sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				remove_query_arg( 'status_filter' ),
				'all' === $current ? 'current' : '',
				__( 'All', 'eg-care' ),
				$total
			),
		);

		$statuses = array(
			'pending_payment' => __( 'Pending Payment', 'eg-care' ),
			'confirmed'       => __( 'Confirmed', 'eg-care' ),
			'ongoing'         => __( 'Ongoing', 'eg-care' ),
			'completed'       => __( 'Completed', 'eg-care' ),
			'cancelled'       => __( 'Cancelled', 'eg-care' ),
		);

		foreach ( $statuses as $key => $label ) {
			$count = isset( $status_counts[ $key ] ) ? intval( $status_counts[ $key ]->count ) : 0;
			$views[ $key ] = sprintf(
				'<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
				add_query_arg( 'status_filter', $key ),
				$key === $current ? 'current' : '',
				$label,
				$count
			);
		}

		return $views;
	}

	/**
	 * Extra controls above/below the table (Doctor / Date Filters).
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		global $wpdb;
		$table_meta = DB::get_table( 'doctors_meta' );
		$doctors = $wpdb->get_results( "SELECT post_id FROM $table_meta WHERE verification_status = 'approved'" );

		$selected_doctor = isset( $_GET['doctor_filter'] ) ? intval( $_GET['doctor_filter'] ) : 0;
		$selected_date   = isset( $_GET['date_filter'] ) ? sanitize_text_field( $_GET['date_filter'] ) : '';

		?>
		<div class="alignleft actions">
			<!-- Doctor Filter Dropdown -->
			<select name="doctor_filter" id="doctor_filter" style="float: none; margin-right: 6px;">
				<option value="0"><?php esc_html_e( 'Filter by Doctor', 'eg-care' ); ?></option>
				<?php foreach ( $doctors as $d ) : ?>
					<option value="<?php echo intval( $d->post_id ); ?>" <?php selected( $selected_doctor, $d->post_id ); ?>>
						<?php echo esc_html( get_the_title( $d->post_id ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<!-- Date Filter Input -->
			<input type="date" name="date_filter" value="<?php echo esc_attr( $selected_date ); ?>" style="line-height: 1.5; padding: 2px 8px; margin-right: 6px;" />

			<?php submit_button( __( 'Apply Filter', 'eg-care' ), 'button', 'filter_action', false, array( 'id' => 'post-query-submit' ) ); ?>
		</div>
		<?php
	}

	/**
	 * Query and paginate records.
	 */
	public function prepare_items() {
		global $wpdb;
		$table = DB::get_table( 'appointments' );

		// 1. Column Headers
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable );

		// 2. Build Query Clauses
		$where = array( '1=1' );

		// Filter: Status Tab
		if ( isset( $_GET['status_filter'] ) && ! empty( $_GET['status_filter'] ) ) {
			$where[] = $wpdb->prepare( 'status = %s', sanitize_key( $_GET['status_filter'] ) );
		}

		// Filter: Doctor Select
		if ( isset( $_GET['doctor_filter'] ) && intval( $_GET['doctor_filter'] ) > 0 ) {
			$where[] = $wpdb->prepare( 'doctor_id = %d', intval( $_GET['doctor_filter'] ) );
		}

		// Filter: Date select
		if ( isset( $_GET['date_filter'] ) && ! empty( $_GET['date_filter'] ) ) {
			$where[] = $wpdb->prepare( 'appointment_date = %s', sanitize_text_field( $_GET['date_filter'] ) );
		}

		// Search Query
		if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {
			$search = '%' . $wpdb->esc_like( sanitize_text_field( $_REQUEST['s'] ) ) . '%';
			$where[] = $wpdb->prepare( '(family_member_name LIKE %s OR transaction_id LIKE %s)', $search, $search );
		}

		$where_sql = implode( ' AND ', $where );

		// Sorting
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : 'id';
		$order   = isset( $_GET['order'] ) && 'asc' === strtolower( $_GET['order'] ) ? 'ASC' : 'DESC';
		
		// Map schema orderby
		if ( 'schedule' === $orderby ) {
			$orderby = 'appointment_date';
		}

		// Pagination params
		$per_page     = 10;
		$current_page = $this->get_pagenum();
		$offset       = ( $current_page - 1 ) * $per_page;

		// 3. Query
		$total_items = $wpdb->get_var( "SELECT COUNT(id) FROM $table WHERE $where_sql" );
		
		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table WHERE $where_sql ORDER BY $orderby $order LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}
}
