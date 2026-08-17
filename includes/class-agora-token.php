<?php
namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Require the official Agora SDK files.
require_once EG_CARE_PATH . 'includes/agora/Util.php';
require_once EG_CARE_PATH . 'includes/agora/AccessToken2.php';
require_once EG_CARE_PATH . 'includes/agora/RtcTokenBuilder2.php';

/**
 * Handles Agora RTC token generation.
 */
class AgoraToken {
	/**
	 * Generate an Agora RTC token for a given channel and user.
	 *
	 * @param string $channel_name Channel name (video_room_id).
	 * @param int    $uid          User ID (numeric).
	 * @param int    $role         Agora role (1 = Publisher, 2 = Subscriber).
	 * @param int    $expire_sec   Expiration duration in seconds (default 3600).
	 * @return string|false Generated token or false on failure.
	 */
	public static function generate_token( $channel_name, $uid, $role = 1, $expire_sec = 3600 ) {
		// Retrieve credentials from settings.
		$app_id          = get_option( 'eg_care_agora_app_id', '' );
		$app_certificate = get_option( 'eg_care_agora_app_certificate', '' );

		if ( empty( $app_id ) || empty( $app_certificate ) ) {
			return false;
		}

		try {
			// Generate the token using the official RtcTokenBuilder2.
			$token = \RtcTokenBuilder2::buildTokenWithUid(
				$app_id,
				$app_certificate,
				$channel_name,
				$uid,
				$role,
				$expire_sec,
				$expire_sec
			);
			return $token;
		} catch ( \Exception $e ) {
			error_log( 'Agora Token Gen Error: ' . $e->getMessage() );
			return false;
		}
	}
}
