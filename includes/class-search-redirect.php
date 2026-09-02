<?php
/**
 * Where a site search that turns out to be about a doctor should land.
 *
 * @package EG Care
 */

namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sends a search that matches a doctor to the doctor list.
 *
 * The header search box posts to WordPress's own /?s= results, which the theme
 * renders with its blog archive template: a featured image, a publish date and
 * a category link under the doctor's name. Nothing on that card says what the
 * doctor charges, what they specialise in, whether they are online, or how to
 * book one - which is the entire reason somebody typed the name.
 *
 * When at least one doctor matches, the request goes to the booking list with
 * the term already in its search box, so the visitor lands on real doctor
 * cards with fee, rating, specialty and a Book button. A search that matches
 * no doctor is left alone, so pages and posts still search as they always did.
 *
 * Same idea, and the same one-URL-per-result rule, as SpecialtyArchive.
 */
class SearchRedirect {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ) );
	}

	/**
	 * Send a doctor search on to the doctor list.
	 */
	public static function maybe_redirect() {
		if ( is_admin() || is_feed() || ! is_search() ) {
			return;
		}

		// A search already scoped to a post type is somebody asking for exactly
		// that, and redirecting it would be overruling them.
		if ( ! empty( $_GET['post_type'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$term = trim( (string) get_search_query( false ) );

		if ( '' === $term || ! self::has_doctor( $term ) ) {
			return;
		}

		$target = self::list_url( $term );

		/**
		 * Filter where a doctor search is sent, or stop it being sent.
		 *
		 * Returning an empty string leaves the search results to render as
		 * WordPress would, for a site that has built its own template.
		 *
		 * @param string $target Redirect target, empty to leave the search alone.
		 * @param string $term   What was searched for.
		 */
		$target = apply_filters( 'eg_care_search_redirect', $target, $term );

		if ( ! $target ) {
			return;
		}

		// Temporary, not permanent: whether a term matches a doctor depends on
		// which doctors exist today, so it is not a fact about this URL that a
		// browser should be caching.
		wp_safe_redirect( $target, 302 );
		exit;
	}

	/**
	 * Does this search match a published doctor?
	 *
	 * One id is enough to answer the question, and the meta and term caches are
	 * left alone because nothing here reads them.
	 *
	 * @param string $term What was searched for.
	 * @return bool
	 */
	private static function has_doctor( $term ) {
		$found = get_posts(
			array(
				'post_type'              => 'doctors',
				'post_status'            => 'publish',
				's'                      => $term,
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return ! empty( $found );
	}

	/**
	 * The doctor list, with the search term already applied.
	 *
	 * @param string $term What was searched for.
	 * @return string URL, or an empty string when the site has no list page.
	 */
	private static function list_url( $term ) {
		$list = Shortcodes::page_with_shortcode( 'eg_care_booking_flow' );

		if ( ! $list ) {
			// Without a booking page there is nowhere better to send anyone,
			// so the search results are left to render.
			return '';
		}

		// eg_search, matching what the list writes into its own address, so a
		// search and a filter typed by hand produce the very same URL.
		return add_query_arg( 'eg_search', rawurlencode( $term ), $list );
	}
}
