<?php
namespace Meditaj;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Handles registration of Custom Post Types and Custom Taxonomies.
 */
class CPT {
	/**
	 * Initialize actions.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_types' ) );
		add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );
		add_action( 'init', array( __CLASS__, 'register_term_meta' ) );
	}

	/**
	 * Register 'doctors' Custom Post Type.
	 */
	public static function register_post_types() {
		$labels = array(
			'name'               => _x( 'Doctors', 'post type general name', 'meditaj' ),
			'singular_name'      => _x( 'Doctor', 'post type singular name', 'meditaj' ),
			'menu_name'          => _x( 'Doctors', 'admin menu', 'meditaj' ),
			'name_admin_bar'     => _x( 'Doctor', 'add new on admin bar', 'meditaj' ),
			'add_new'            => _x( 'Add New', 'doctor', 'meditaj' ),
			'add_new_item'       => __( 'Add New Doctor', 'meditaj' ),
			'new_item'           => __( 'New Doctor', 'meditaj' ),
			'edit_item'          => __( 'Edit Doctor', 'meditaj' ),
			'view_item'          => __( 'View Doctor', 'meditaj' ),
			'all_items'          => __( 'All Doctors', 'meditaj' ),
			'search_items'       => __( 'Search Doctors', 'meditaj' ),
			'parent_item_colon'  => __( 'Parent Doctors:', 'meditaj' ),
			'not_found'          => __( 'No doctors found.', 'meditaj' ),
			'not_found_in_trash' => __( 'No doctors found in Trash.', 'meditaj' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'doctors' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'menu_icon'          => 'dashicons-businessman',
			'show_in_rest'       => true,
			'supports'           => array( 'title', 'editor', 'thumbnail' ),
		);

		register_post_type( 'doctors', $args );
	}

	/**
	 * Register 'specialty' Custom Taxonomy.
	 */
	public static function register_taxonomies() {
		$labels = array(
			'name'              => _x( 'Specialties', 'taxonomy general name', 'meditaj' ),
			'singular_name'     => _x( 'Specialty', 'taxonomy singular name', 'meditaj' ),
			'search_items'      => __( 'Search Specialties', 'meditaj' ),
			'all_items'         => __( 'All Specialties', 'meditaj' ),
			'parent_item'       => __( 'Parent Specialty', 'meditaj' ),
			'parent_item_colon' => __( 'Parent Specialty:', 'meditaj' ),
			'edit_item'         => __( 'Edit Specialty', 'meditaj' ),
			'update_item'       => __( 'Update Specialty', 'meditaj' ),
			'add_new_item'      => __( 'Add New Specialty', 'meditaj' ),
			'new_item_name'     => __( 'New Specialty Name', 'meditaj' ),
			'menu_name'         => __( 'Specialties', 'meditaj' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'specialty' ),
			'show_in_rest'      => true,
		);

		register_taxonomy( 'specialty', array( 'doctors' ), $args );
	}

	/**
	 * Register custom term meta field for Specialty Icon.
	 */
	public static function register_term_meta() {
		register_term_meta(
			'specialty',
			'specialty_icon_id',
			array(
				'type'              => 'integer',
				'description'       => __( 'The attachment ID of the custom icon for this specialty.', 'meditaj' ),
				'single'            => true,
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
			)
		);
	}
}
