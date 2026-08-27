<?php
/**
 * The doctors post type's own front-end page.
 *
 * @package EG Care
 */

namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Serves a profile page for a single doctor.
 *
 * The post type is already public with a 'doctors' rewrite, so the URLs
 * existed; without a template behind them WordPress fell back to the theme's
 * generic single view, which shows the post body and nothing else.
 */
class SingleDoctor {

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'use_plugin_template' ) );
	}

	/**
	 * Swap in the plugin's doctor template when the theme has none.
	 *
	 * @param string $template Template WordPress resolved.
	 * @return string Template to load.
	 */
	public static function use_plugin_template( $template ) {
		if ( ! is_singular( 'doctors' ) ) {
			return $template;
		}

		// A theme that ships its own single-doctors.php means to override this,
		// so leave it alone. locate_template() covers the child theme too.
		if ( locate_template( array( 'single-doctors.php' ) ) ) {
			return $template;
		}

		if ( ! self::is_approved( get_queried_object_id() ) ) {
			// An unapproved or unverified profile is not a page to show a
			// patient; let WordPress serve whatever it was going to serve.
			return $template;
		}

		$plugin_template = EG_CARE_PATH . 'templates/single-doctor.php';

		return file_exists( $plugin_template ) ? $plugin_template : $template;
	}

	/**
	 * Whether this doctor has been approved.
	 *
	 * @param int $doctor_id Doctor post ID.
	 * @return bool
	 */
	private static function is_approved( $doctor_id ) {
		global $wpdb;

		$doctor_id = intval( $doctor_id );

		if ( $doctor_id <= 0 ) {
			return false;
		}

		$table_meta = \EGCare\DB::get_table( 'doctors_meta' );

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT m.post_id FROM $table_meta m WHERE m.post_id = %d AND m.verification_status = 'approved'",
				$doctor_id
			)
		);
	}
}
