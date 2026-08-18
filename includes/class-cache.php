<?php
/**
 * Cache exclusion for EG Care's dynamic output.
 *
 * @package EG Care
 */

namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Tells page and REST caching layers not to store EG Care output.
 *
 * Every EG Care front-end screen embeds a nonce, and the doctor and patient
 * dashboards embed the logged-in user's own records. If a caching layer stores
 * that HTML it will be replayed to a different visitor, which breaks nonce
 * checks and can show one user another user's data. The same applies to the
 * REST routes that back those screens.
 */
class Cache {

	/**
	 * Shortcodes whose output must never be cached.
	 *
	 * @var string[]
	 */
	const SHORTCODES = array(
		'eg_care_booking_flow',
		'eg_care_patient_dashboard',
		'eg_care_doctor_dashboard',
		'eg_care_doctor_registration',
	);

	/**
	 * REST namespace whose responses must never be cached.
	 */
	const REST_NAMESPACE = 'eg-care/v1';

	/**
	 * Register hooks.
	 */
	public static function init() {
		// Early enough that cache headers can still be sent.
		add_action( 'template_redirect', array( __CLASS__, 'maybe_exclude_page' ), 0 );

		// Catch-all for shortcodes rendered outside the main post content,
		// e.g. from a widget, a block template or a page builder.
		add_filter( 'do_shortcode_tag', array( __CLASS__, 'exclude_on_shortcode_render' ), 10, 2 );

		add_filter( 'rest_post_dispatch', array( __CLASS__, 'exclude_rest_response' ), 10, 3 );
	}

	/**
	 * Flag the current front-end request when it renders EG Care output.
	 */
	public static function maybe_exclude_page() {
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( ! self::request_renders_eg_care() ) {
			return;
		}

		self::declare_uncacheable( 'EG Care page carries a nonce and per-user data' );

		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}

	/**
	 * Whether this request is going to render EG Care output.
	 *
	 * @return bool
	 */
	private static function request_renders_eg_care() {
		// Gateway return URLs render a per-appointment receipt on whichever
		// page the booking flow was started from.
		if ( isset( $_GET['eg_care_payment'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return false;
		}

		foreach ( self::SHORTCODES as $shortcode ) {
			if ( has_shortcode( $post->post_content, $shortcode ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Flag the request when one of our shortcodes actually renders.
	 *
	 * @param string $output Shortcode output.
	 * @param string $tag    Shortcode name.
	 * @return string Unmodified output.
	 */
	public static function exclude_on_shortcode_render( $output, $tag ) {
		if ( in_array( $tag, self::SHORTCODES, true ) ) {
			self::declare_uncacheable( 'EG Care shortcode rendered' );
		}

		return $output;
	}

	/**
	 * Send no-store headers on every EG Care REST response.
	 *
	 * @param mixed            $response Response object.
	 * @param mixed            $server   REST server instance.
	 * @param \WP_REST_Request $request  Request object.
	 * @return mixed Response object.
	 */
	public static function exclude_rest_response( $response, $server, $request ) {
		if ( ! $request instanceof \WP_REST_Request ) {
			return $response;
		}

		$route = ltrim( (string) $request->get_route(), '/' );
		if ( 0 !== strpos( $route, self::REST_NAMESPACE ) ) {
			return $response;
		}

		self::declare_uncacheable( 'EG Care REST response' );

		if ( is_object( $response ) && method_exists( $response, 'header' ) ) {
			$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private' );
			$response->header( 'Pragma', 'no-cache' );
			$response->header( 'Expires', 'Wed, 11 Jan 1984 05:00:00 GMT' );
		}

		return $response;
	}

	/**
	 * Signal the caching layers we know about.
	 *
	 * DONOTCACHEPAGE is honoured by LiteSpeed Cache, WP Rocket, W3 Total Cache,
	 * WP Super Cache, Comet Cache and SG Optimizer. The action below is
	 * LiteSpeed's own control hook and is a no-op when LiteSpeed is not active.
	 *
	 * @param string $reason Human-readable reason, shown in LiteSpeed's debug log.
	 */
	private static function declare_uncacheable( $reason ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		do_action( 'litespeed_control_set_nocache', $reason );
	}
}
