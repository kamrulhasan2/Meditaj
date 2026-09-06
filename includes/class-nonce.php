<?php
/**
 * Handing a fresh REST nonce back to a page whose own nonce has gone stale.
 *
 * @package EG Care
 */

namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * A nonce printed into a page stops being valid long before the page stops
 * being useful. Three ways that happens here:
 *
 *   - a page cache replays HTML, nonce and all, to a different visitor;
 *   - a booking screen is left open past the 12-24 hour nonce lifetime;
 *   - the visitor logs in or out in another tab, which changes the user id
 *     the nonce is tied to.
 *
 * In every case the next REST call comes back 403 rest_cookie_invalid_nonce
 * and the patient sees a booking that will not submit. assets/js/nonce-guard.js
 * catches that response and asks this endpoint for a replacement.
 *
 * It has to be admin-ajax rather than a REST route: WordPress rejects a REST
 * request with a bad nonce inside rest_authentication_errors, before any
 * permission_callback runs, so a REST endpoint could never answer the very
 * request that needs answering.
 */
class Nonce {

	/**
	 * The admin-ajax action name. Shared with JS through egCareSettings.
	 */
	const ACTION = 'eg_care_refresh_nonce';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'wp_ajax_' . self::ACTION, array( __CLASS__, 'respond' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( __CLASS__, 'respond' ) );
	}

	/**
	 * Return a nonce for whoever is asking.
	 *
	 * Deliberately unguarded, and safe to be so. The nonce returned is derived
	 * from the caller's own cookies, so a caller only ever receives the nonce
	 * they already had the right to have; a logged-out caller gets the
	 * logged-out nonce, which unlocks nothing. admin-ajax sends no CORS
	 * headers either, so another origin cannot read the response even though
	 * it can provoke it.
	 */
	public static function respond() {
		nocache_headers();

		wp_send_json_success(
			array(
				'nonce' => wp_create_nonce( 'wp_rest' ),
				// Tells the page whether a retry is worth attempting at all. A
				// stale nonce is recoverable; an expired login is not, and the
				// visitor needs to be told to sign in rather than watch the
				// same request fail twice.
				'loggedIn' => is_user_logged_in(),
			)
		);
	}
}
