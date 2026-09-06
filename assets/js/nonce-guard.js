/**
 * EG Care - recover from a stale REST nonce instead of failing in the user's face.
 *
 * Every EG Care screen carries a nonce printed into the HTML. It goes stale
 * when a page cache replays the HTML to somebody else, when the page is left
 * open past the nonce lifetime, or when the visitor signs in or out in another
 * tab. WordPress answers the next REST call with 403 rest_cookie_invalid_nonce
 * and, until now, the booking simply refused to submit with no explanation.
 *
 * This wraps window.fetch, watches only for that one error on only EG Care's
 * own REST routes, fetches a replacement nonce and retries the request once.
 * Everything else - other origins, other plugins, any other status code -
 * passes through untouched.
 *
 * The 22 call sites across the other scripts need no changes: each of them
 * reads egCareSettings.nonce at the moment it builds its request, so once the
 * value here is refreshed every later call is already correct.
 *
 * @package EG Care
 */

( function ( window, document ) {
	'use strict';

	var settings = window.egCareSettings;

	if ( ! settings || ! settings.restUrl || ! settings.ajaxUrl || 'function' !== typeof window.fetch ) {
		return;
	}

	var nativeFetch = window.fetch.bind( window );

	// One refresh serves every request that failed together, which is the
	// normal case: a dashboard fires several calls at once and they all come
	// back 403.
	var inFlight = null;

	/**
	 * Ask admin-ajax for a nonce that matches the current cookies.
	 *
	 * @return {Promise<Object|null>} { nonce, loggedIn }, or null if unreachable.
	 */
	function refreshNonce() {
		if ( inFlight ) {
			return inFlight;
		}

		inFlight = nativeFetch( settings.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: 'action=' + encodeURIComponent( settings.nonceAction )
		} )
			.then( function ( res ) {
				return res.ok ? res.json() : null;
			} )
			.catch( function () {
				return null;
			} )
			.then( function ( body ) {
				inFlight = null;

				if ( ! body || ! body.success || ! body.data || ! body.data.nonce ) {
					return null;
				}

				settings.nonce = body.data.nonce;
				settings.loggedIn = !! body.data.loggedIn;

				return body.data;
			} );

		return inFlight;
	}

	/**
	 * Whether this request is one of ours and one we can safely repeat.
	 *
	 * Only a plain URL string is retryable. A Request object may carry a body
	 * stream that has already been read, and replaying that would send an
	 * empty body rather than the booking the patient filled in.
	 *
	 * @param {*} input First argument given to fetch().
	 * @return {boolean} True when a retry is allowed.
	 */
	function isRetryable( input ) {
		return 'string' === typeof input && 0 === input.indexOf( settings.restUrl );
	}

	/**
	 * Copy the request options with the new nonce in place.
	 *
	 * @param {Object} init  Original fetch options.
	 * @param {string} nonce Fresh nonce.
	 * @return {Object} Options to retry with.
	 */
	function withNonce( init, nonce ) {
		var next = {};
		var key;

		init = init || {};

		for ( key in init ) {
			if ( Object.prototype.hasOwnProperty.call( init, key ) ) {
				next[ key ] = init[ key ];
			}
		}

		if ( init.headers instanceof window.Headers ) {
			next.headers = new window.Headers( init.headers );
			next.headers.set( 'X-WP-Nonce', nonce );
		} else {
			next.headers = Object.assign( {}, init.headers || {}, { 'X-WP-Nonce': nonce } );
		}

		return next;
	}

	/**
	 * Whether a 403 body is the specific error a new nonce would fix.
	 *
	 * A genuine permission failure - a patient reaching for another patient's
	 * appointment - is also a 403 and must not be retried.
	 *
	 * @param {Response} res The failed response.
	 * @return {Promise<boolean>} True when the nonce is what went wrong.
	 */
	function isStaleNonce( res ) {
		return res
			.clone()
			.json()
			.then( function ( body ) {
				return !! body && 'rest_cookie_invalid_nonce' === body.code;
			} )
			.catch( function () {
				return false;
			} );
	}

	var announced = false;

	/**
	 * Tell the visitor their session ended, once, and let them reload.
	 *
	 * Without this a signed-out visitor watches the same request fail twice
	 * and has no idea why.
	 */
	function announceSignedOut() {
		if ( announced ) {
			return;
		}
		announced = true;

		document.dispatchEvent( new window.CustomEvent( 'eg-care:session-expired' ) );

		var bar = document.createElement( 'div' );
		bar.setAttribute( 'role', 'alert' );
		bar.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:99999;padding:14px 18px;' +
			'background:#1f2937;color:#fff;font-size:15px;line-height:1.5;text-align:center';
		bar.textContent = 'Your session has ended. Please sign in again to continue. ';

		var link = document.createElement( 'a' );
		link.href = window.location.href;
		link.textContent = 'Reload';
		link.style.cssText = 'color:#fff;text-decoration:underline;font-weight:600';

		bar.appendChild( link );
		document.body.appendChild( bar );
	}

	window.fetch = function ( input, init ) {
		if ( ! isRetryable( input ) ) {
			return nativeFetch( input, init );
		}

		return nativeFetch( input, init ).then( function ( res ) {
			if ( 403 !== res.status ) {
				return res;
			}

			return isStaleNonce( res ).then( function ( stale ) {
				if ( ! stale ) {
					return res;
				}

				return refreshNonce().then( function ( data ) {
					// Unreachable, or a nonce we already know is no better than
					// the one that just failed: hand back the original response
					// so the caller's own error handling runs.
					if ( ! data ) {
						return res;
					}

					if ( ! data.loggedIn ) {
						announceSignedOut();
						return res;
					}

					// Exactly one retry. If this fails too, it is not the nonce.
					return nativeFetch( input, withNonce( init, data.nonce ) );
				} );
			} );
		} );
	};
}( window, document ) );
