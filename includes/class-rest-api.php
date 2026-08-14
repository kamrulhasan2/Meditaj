<?php
/**
 * REST API entrypoint register.
 *
 * @package Meditaj
 */

namespace Meditaj;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class RestApi
 */
class RestApi {
	/**
	 * Initialize REST API hooks.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register Meditaj REST routes.
	 */
	public static function register_routes() {
		$doctors = new RestControllerDoctors();
		$doctors->register_routes();

		$booking = new RestControllerBooking();
		$booking->register_routes();

		$payment = new RestControllerPayment();
		$payment->register_routes();
	}
}
