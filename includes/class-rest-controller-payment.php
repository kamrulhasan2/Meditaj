<?php
/**
 * REST API Controller for Payments.
 *
 * @package Meditaj
 */

namespace Meditaj;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class RestControllerPayment
 */
class RestControllerPayment extends WP_REST_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$namespace = 'meditaj/v1';

		register_rest_route(
			$namespace,
			'/payment/init',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'initialize_payment' ),
					'permission_callback' => array( $this, 'check_logged_in_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/payment/webhook/sslcommerz',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'sslcommerz_webhook' ),
					'permission_callback' => '__return_true', // Webhook gets called publicly by gateway.
				),
			)
		);
	}

	/**
	 * Ensure the requesting user is logged in.
	 *
	 * @return bool|\WP_Error
	 */
	public function check_logged_in_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'You must be logged in to access this endpoint.', 'meditaj' ), array( 'status' => 401 ) );
		}
		return true;
	}

	/**
	 * Initialize SSLCommerz Payment Session.
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function initialize_payment( $request ) {
		global $wpdb;

		$user_id        = get_current_user_id();
		$appointment_id = intval( $request->get_param( 'appointment_id' ) );

		if ( empty( $appointment_id ) ) {
			return new WP_Error( 'rest_bad_request', __( 'Appointment ID is required.', 'meditaj' ), array( 'status' => 400 ) );
		}

		// Retrieve appointment.
		$table_appointments = \Meditaj\DB::get_table( 'appointments' );
		$appointment        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_appointments WHERE id = %d",
				$appointment_id
			)
		);

		if ( ! $appointment ) {
			return new WP_Error( 'rest_not_found', __( 'Selected appointment not found.', 'meditaj' ), array( 'status' => 404 ) );
		}

		// Verify ownership (owner or admin only).
		if ( intval( $appointment->patient_user_id ) !== $user_id && ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'rest_forbidden', __( 'You are not authorized to pay for this appointment.', 'meditaj' ), array( 'status' => 403 ) );
		}

		// Verify payment status is unpaid.
		if ( 'paid' === $appointment->payment_status ) {
			return new WP_Error( 'rest_bad_request', __( 'This appointment has already been paid for.', 'meditaj' ), array( 'status' => 400 ) );
		}

		// Generate Transaction ID.
		$tran_id = 'TXN_' . $appointment->id . '_' . time();

		$store_id     = defined( 'MEDITAJ_SSL_STORE_ID' ) ? MEDITAJ_SSL_STORE_ID : get_option( 'meditaj_ssl_store_id', 'testbox' );
		$store_passwd = defined( 'MEDITAJ_SSL_STORE_PASSWD' ) ? MEDITAJ_SSL_STORE_PASSWD : get_option( 'meditaj_ssl_store_passwd', 'testbox@ssl' );
		$is_sandbox   = defined( 'MEDITAJ_SSL_SANDBOX' ) ? MEDITAJ_SSL_SANDBOX : ( '1' === get_option( 'meditaj_ssl_sandbox', '1' ) );

		$return_url = $request->get_param( 'return_url' );
		if ( empty( $return_url ) ) {
			$return_url = home_url( '/' );
		}

		$success_url = add_query_arg( array( 'meditaj_payment' => 'success', 'id' => $appointment->id ), $return_url );
		$fail_url    = add_query_arg( array( 'meditaj_payment' => 'fail', 'id' => $appointment->id ), $return_url );
		$cancel_url  = add_query_arg( array( 'meditaj_payment' => 'cancel', 'id' => $appointment->id ), $return_url );

		// Configure SSLCommerz Gateway Arguments.
		$post_args = array(
			'store_id'         => $store_id,
			'store_passwd'     => $store_passwd,
			'total_amount'     => floatval( $appointment->amount ),
			'currency'         => 'BDT',
			'tran_id'          => $tran_id,
			'success_url'      => esc_url_raw( $success_url ),
			'fail_url'         => esc_url_raw( $fail_url ),
			'cancel_url'       => esc_url_raw( $cancel_url ),
			'ipn_url'          => rest_url( 'meditaj/v1/payment/webhook/sslcommerz' ),
			'cus_name'         => wp_get_current_user()->display_name,
			'cus_email'        => wp_get_current_user()->user_email,
			'cus_phone'        => '01700000000', // Default placeholder phone.
			'cus_add1'         => 'Dhaka',
			'cus_city'         => 'Dhaka',
			'cus_country'      => 'Bangladesh',
			'shipping_method'  => 'NO',
			'num_of_item'      => '1',
			'product_name'     => 'Medical Consultation',
			'product_category' => 'Healthcare',
			'product_profile'  => 'non-physical-goods',
		);

		// Determine gateway URL based on sandbox flag.
		$gateway_url = $is_sandbox ?
			'https://sandbox.sslcommerz.com/gwprocess/v4/api.php' :
			'https://header.sslcommerz.com/gwprocess/v4/api.php';

		$response = wp_remote_post(
			$gateway_url,
			array(
				'body'      => $post_args,
				'timeout'   => 15,
				'sslverify' => false, // Set to true in production.
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'meditaj_gateway_error', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || ! isset( $data['status'] ) ) {
			return new WP_Error( 'meditaj_gateway_error', __( 'Invalid response payload from payment gateway.', 'meditaj' ), array( 'status' => 502 ) );
		}

		if ( 'SUCCESS' !== $data['status'] ) {
			$msg = isset( $data['failedreason'] ) ? $data['failedreason'] : __( 'Failed to initiate payment session.', 'meditaj' );
			return new WP_Error( 'meditaj_gateway_error', $msg, array( 'status' => 502 ) );
		}

		// Update appointment record with generated transaction ID.
		$wpdb->update(
			$table_appointments,
			array(
				'transaction_id' => $tran_id,
				'updated_at'     => current_time( 'mysql' ),
			),
			array( 'id' => $appointment->id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return new WP_REST_Response(
			array(
				'payment_url' => $data['GatewayPageURL'],
			),
			200
		);
	}

	/**
	 * SSLCommerz Webhook Callback (IPN Receiver).
	 *
	 * @param \WP_REST_Request $request REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function sslcommerz_webhook( $request ) {
		global $wpdb;

		// Extract request parameters.
		$val_id       = sanitize_text_field( $request->get_param( 'val_id' ) );
		$tran_id      = sanitize_text_field( $request->get_param( 'tran_id' ) );
		$amount       = floatval( $request->get_param( 'amount' ) );
		$status       = sanitize_text_field( $request->get_param( 'status' ) );
		$bank_tran_id = sanitize_text_field( $request->get_param( 'bank_tran_id' ) );

		if ( empty( $val_id ) || empty( $tran_id ) || empty( $status ) ) {
			return new WP_Error( 'rest_bad_request', __( 'Webhook payload missing details.', 'meditaj' ), array( 'status' => 400 ) );
		}

		$store_id     = defined( 'MEDITAJ_SSL_STORE_ID' ) ? MEDITAJ_SSL_STORE_ID : get_option( 'meditaj_ssl_store_id', 'testbox' );
		$store_passwd = defined( 'MEDITAJ_SSL_STORE_PASSWD' ) ? MEDITAJ_SSL_STORE_PASSWD : get_option( 'meditaj_ssl_store_passwd', 'testbox@ssl' );

		// 1. Verify webhook signature via Gateway Validation API query.
		$validation_url = sprintf(
			'https://sandbox.sslcommerz.com/validator/api/validationserverAPI.php?val_id=%s&store_id=%s&store_passwd=%s&format=json',
			urlencode( $val_id ),
			urlencode( $store_id ),
			urlencode( $store_passwd )
		);

		$response = wp_remote_get(
			$validation_url,
			array(
				'timeout'   => 15,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'meditaj_gateway_error', $response->get_error_message(), array( 'status' => 502 ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( ! $data || ! isset( $data->status ) ) {
			return new WP_Error( 'meditaj_gateway_error', __( 'Failed to validate payload with payment gateway.', 'meditaj' ), array( 'status' => 502 ) );
		}

		// 2. Validate transaction status.
		if ( 'VALID' !== $data->status && 'VALIDATED' !== $data->status ) {
			return new WP_Error( 'meditaj_payment_failed', __( 'Transaction is invalid on gateway.', 'meditaj' ), array( 'status' => 400 ) );
		}

		// Retrieve matching appointment.
		$table_appointments = \Meditaj\DB::get_table( 'appointments' );
		$appointment        = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $table_appointments WHERE transaction_id = %s",
				$tran_id
			)
		);

		if ( ! $appointment ) {
			return new WP_Error( 'rest_not_found', __( 'Target appointment booking not found.', 'meditaj' ), array( 'status' => 404 ) );
		}

		// Double-check amount matches to avoid payload faking.
		if ( abs( floatval( $data->amount ) - floatval( $appointment->amount ) ) > 0.01 ) {
			return new WP_Error( 'meditaj_payment_error', __( 'Transaction amount mismatch.', 'meditaj' ), array( 'status' => 400 ) );
		}

		// Prevent double updates if already paid.
		if ( 'paid' === $appointment->payment_status ) {
			return new WP_REST_Response( array( 'status' => 'already_processed' ), 200 );
		}

		// 3. Confirm booking and record success.
		$now = current_time( 'mysql' );
		$wpdb->update(
			$table_appointments,
			array(
				'payment_status' => 'paid',
				'status'         => 'confirmed',
				'payment_method' => 'sslcommerz',
				'updated_at'     => $now,
			),
			array( 'id' => $appointment->id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		// Insert row into wp_meditaj_transactions.
		$table_transactions = \Meditaj\DB::get_table( 'transactions' );
		$wpdb->insert(
			$table_transactions,
			array(
				'appointment_id' => $appointment->id,
				'doctor_id'      => $appointment->doctor_id,
				'amount'         => $appointment->amount,
				'payment_method' => 'sslcommerz',
				'gateway_txn_id' => $bank_tran_id ? $bank_tran_id : $tran_id,
				'payment_status' => 'success',
				'created_at'     => $now,
			),
			array(
				'%d',
				'%d',
				'%f',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		// Dispatch confirmation emails.
		$doctor_user = get_userdata( $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {\Meditaj\DB::get_table('doctors_meta')} WHERE post_id = %d", $appointment->doctor_id ) ) );
		$patient_user = get_userdata( $appointment->patient_user_id );

		if ( $patient_user && $doctor_user ) {
			// Trigger booking notification emails.
			$subject = __( 'Consultation Booking Confirmed', 'meditaj' );
			$message = sprintf(
				__( "Hello,\n\nYour consultation booking is confirmed.\nAppointment Date: %s\nAppointment Time: %s\nAmount Paid: %s BDT.\n\nThank you.", 'meditaj' ),
				$appointment->appointment_date,
				$appointment->appointment_time,
				$appointment->amount
			);
			wp_mail( $patient_user->user_email, $subject, $message );
			wp_mail( $doctor_user->user_email, $subject, $message );
		}

		return new WP_REST_Response( array( 'status' => 'success' ), 200 );
	}
}
