<?php
namespace EGCare;

use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The patient's saved family members.
 *
 * Everything is scoped to the caller: the routes never take a user id, they use
 * the logged-in one, and FamilyMembers filters on it in SQL.
 */
class RestControllerFamily {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$namespace = 'eg-care/v1';

		register_rest_route(
			$namespace,
			'/patient/me/family',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_members' ),
					'permission_callback' => array( $this, 'check_logged_in_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_member' ),
					'permission_callback' => array( $this, 'check_logged_in_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/patient/me/family/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_member' ),
					'permission_callback' => array( $this, 'check_logged_in_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => static function ( $value ) {
								return is_numeric( $value ) && intval( $value ) > 0;
							},
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_member' ),
					'permission_callback' => array( $this, 'check_logged_in_permission' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => static function ( $value ) {
								return is_numeric( $value ) && intval( $value ) > 0;
							},
						),
					),
				),
			)
		);
	}

	/**
	 * Ensure the requesting user is logged in.
	 *
	 * @return bool|WP_Error
	 */
	public function check_logged_in_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_forbidden', __( 'You must be logged in to access this endpoint.', 'eg-care' ), array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * GET /patient/me/family
	 *
	 * @return WP_REST_Response
	 */
	public function list_members() {
		return new WP_REST_Response( FamilyMembers::all_for_user( get_current_user_id() ), 200 );
	}

	/**
	 * POST /patient/me/family
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_member( $request ) {
		$fields = FamilyMembers::sanitize( $this->incoming( $request ) );

		if ( is_wp_error( $fields ) ) {
			return $fields;
		}

		$user_id = get_current_user_id();
		$id      = FamilyMembers::create( $user_id, $fields );

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		$row = FamilyMembers::find_owned( $id, $user_id );

		return new WP_REST_Response( FamilyMembers::shape( $row ), 201 );
	}

	/**
	 * PUT /patient/me/family/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_member( $request ) {
		$id      = intval( $request->get_param( 'id' ) );
		$user_id = get_current_user_id();

		$fields = FamilyMembers::sanitize( $this->incoming( $request ) );

		if ( is_wp_error( $fields ) ) {
			return $fields;
		}

		$updated = FamilyMembers::update( $id, $user_id, $fields );

		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$row = FamilyMembers::find_owned( $id, $user_id );

		return new WP_REST_Response( FamilyMembers::shape( $row ), 200 );
	}

	/**
	 * DELETE /patient/me/family/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_member( $request ) {
		$deleted = FamilyMembers::delete( intval( $request->get_param( 'id' ) ), get_current_user_id() );

		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	/**
	 * Pull the member fields out of a request.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array
	 */
	private function incoming( $request ) {
		return array(
			'name'          => $request->get_param( 'name' ),
			'relation'      => $request->get_param( 'relation' ),
			'date_of_birth' => $request->get_param( 'date_of_birth' ),
			'gender'        => $request->get_param( 'gender' ),
			'phone'         => $request->get_param( 'phone' ),
			'email'         => $request->get_param( 'email' ),
		);
	}
}
