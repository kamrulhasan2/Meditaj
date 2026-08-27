<?php
/**
 * Template: a single doctor's own page.
 *
 * Loaded through SingleDoctor::use_plugin_template() for an approved doctor,
 * so a site gets working profile pages without its theme having to carry a
 * single-doctors.php of its own. A theme that does supply one wins, exactly
 * as it would for any other post type.
 *
 * The page is the booking flow opened on this doctor: the profile panel and
 * the booking panel are the same screen, which is what a visitor arriving
 * from a search result or a shared link expects.
 *
 * @package EG Care
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

get_header();
?>

<div class="eg-care-single-doctor-page">
	<?php
	// Through do_shortcode() rather than the render method directly, so the
	// page picks up whatever else is filtered onto shortcode output.
	echo do_shortcode( '[eg_care_booking_flow doctor="' . intval( get_the_ID() ) . '"]' );
	?>
</div>

<?php
get_footer();
