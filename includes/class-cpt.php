<?php
namespace EGCare;

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
			'name'               => _x( 'Doctors', 'post type general name', 'eg-care' ),
			'singular_name'      => _x( 'Doctor', 'post type singular name', 'eg-care' ),
			'menu_name'          => _x( 'Doctors', 'admin menu', 'eg-care' ),
			'name_admin_bar'     => _x( 'Doctor', 'add new on admin bar', 'eg-care' ),
			'add_new'            => _x( 'Add New', 'doctor', 'eg-care' ),
			'add_new_item'       => __( 'Add New Doctor', 'eg-care' ),
			'new_item'           => __( 'New Doctor', 'eg-care' ),
			'edit_item'          => __( 'Edit Doctor', 'eg-care' ),
			'view_item'          => __( 'View Doctor', 'eg-care' ),
			'all_items'          => __( 'All Doctors', 'eg-care' ),
			'search_items'       => __( 'Search Doctors', 'eg-care' ),
			'parent_item_colon'  => __( 'Parent Doctors:', 'eg-care' ),
			'not_found'          => __( 'No doctors found.', 'eg-care' ),
			'not_found_in_trash' => __( 'No doctors found in Trash.', 'eg-care' ),
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
			'name'              => _x( 'Specialties', 'taxonomy general name', 'eg-care' ),
			'singular_name'     => _x( 'Specialty', 'taxonomy singular name', 'eg-care' ),
			'search_items'      => __( 'Search Specialties', 'eg-care' ),
			'all_items'         => __( 'All Specialties', 'eg-care' ),
			'parent_item'       => __( 'Parent Specialty', 'eg-care' ),
			'parent_item_colon' => __( 'Parent Specialty:', 'eg-care' ),
			'edit_item'         => __( 'Edit Specialty', 'eg-care' ),
			'update_item'       => __( 'Update Specialty', 'eg-care' ),
			'add_new_item'      => __( 'Add New Specialty', 'eg-care' ),
			'new_item_name'     => __( 'New Specialty Name', 'eg-care' ),
			'menu_name'         => __( 'Specialties', 'eg-care' ),
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
				'description'       => __( 'The attachment ID of the custom icon for this specialty.', 'eg-care' ),
				'single'            => true,
				'sanitize_callback' => 'absint',
				'show_in_rest'      => true,
			)
		);
	}
}
