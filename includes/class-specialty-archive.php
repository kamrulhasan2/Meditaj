<?php
/**
 * Where a specialty link should actually land.
 *
 * @package EG Care
 */

namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sends specialty and doctor archive URLs to the doctor list.
 *
 * The specialty taxonomy and the doctors post type both have public archives,
 * so /specialty/cardiology/ and /doctors/ resolve to the theme's generic post
 * loop: a heading, a featured image, an author byline and an excerpt. Someone
 * who clicked "Cardiology" wanted cardiologists they can book, not a blog
 * archive of them.
 *
 * They go to the booking page instead, with the filter already applied - the
 * same address the list itself produces when you tick that specialty, so there
 * is one URL for one result rather than two that disagree.
 */
class SpecialtyArchive {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ) );
	}

	/**
	 * Send an archive request on to the doctor list.
	 */
	public static function maybe_redirect() {
		// Feeds are a legitimate use of an archive URL, and redirecting one
		// breaks the reader rather than helping anybody.
		if ( is_feed() || is_admin() ) {
			return;
		}

		$target = '';

		if ( is_tax( 'specialty' ) ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				$target = self::list_url( $term->slug );
			}
		} elseif ( is_post_type_archive( 'doctors' ) ) {
			$target = self::list_url( '' );
		}

		/**
		 * Filter where an archive request is sent, or stop it being sent.
		 *
		 * Returning an empty string leaves the archive to render as WordPress
		 * would, for a site that has built a template for it.
		 *
		 * @param string $target Redirect target, empty to leave the archive alone.
		 */
		$target = apply_filters( 'eg_care_specialty_archive_redirect', $target );

		if ( ! $target ) {
			return;
		}

		// Temporary, not permanent: a site that later builds its own archive
		// template should not be fighting a redirect browsers have cached.
		wp_safe_redirect( $target, 302 );
		exit;
	}

	/**
	 * The doctor list, optionally filtered to one specialty.
	 *
	 * @param string $slug Specialty slug, or an empty string for the full list.
	 * @return string URL, or an empty string when the site has no list page.
	 */
	private static function list_url( $slug ) {
		$list = Shortcodes::page_with_shortcode( 'eg_care_booking_flow' );

		if ( ! $list ) {
			// Without a booking page there is nowhere better to send anyone,
			// so the archive is left to render.
			return '';
		}

		if ( ! $slug ) {
			return $list;
		}

		// eg_specialty, matching what the list writes into its own address.
		// The bare name is the taxonomy's query var, and putting that on a
		// page URL makes WordPress try to resolve the page as an archive.
		return add_query_arg( 'eg_specialty', rawurlencode( $slug ), $list );
	}
}
