<?php
/**
 * Protected storage for verification documents.
 *
 * @package EG Care
 */

namespace EGCare;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Keeps BMDC certificates out of the public uploads tree.
 *
 * A certificate carries the doctor's licence number and frequently a scan of
 * their national ID. Left in wp-content/uploads it can be fetched by anyone who
 * guesses or enumerates the URL, so these uploads are routed into a directory
 * the web server refuses to serve and are handed to administrators through a
 * proxy that re-checks capability and nonce on every request.
 */
class SecureUploads {

	/**
	 * Directory name inside the uploads folder.
	 */
	const SUBDIR = 'eg-care-private';

	/**
	 * Attachment meta key marking a document as privately stored.
	 */
	const PRIVATE_META = '_eg_care_private_document';

	/**
	 * admin-post.php action used to view a document.
	 */
	const ACTION = 'eg_care_view_certificate';

	/**
	 * File types accepted for a verification document.
	 *
	 * WordPress checks these against the file itself, not just its extension.
	 *
	 * @return array Map of extension pattern to MIME type.
	 */
	public static function allowed_mimes() {
		return array(
			'pdf'          => 'application/pdf',
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
		);
	}

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'admin_post_' . self::ACTION, array( __CLASS__, 'serve_document' ) );
	}

	/**
	 * Absolute path of the protected directory, created and guarded on first use.
	 *
	 * @return string Directory path.
	 */
	public static function directory() {
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . self::SUBDIR;

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		self::write_guards( $dir );

		return $dir;
	}

	/**
	 * Write the files that stop a web server handing this directory out.
	 *
	 * @param string $dir Directory path.
	 */
	private static function write_guards( $dir ) {
		$htaccess = "# Verification documents. Never served directly.\n"
			. "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n";

		$web_config = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
			. "<configuration>\n\t<system.webServer>\n\t\t<authorization>\n"
			. "\t\t\t<deny users=\"*\" />\n"
			. "\t\t</authorization>\n\t</system.webServer>\n</configuration>\n";

		$guards = array(
			'.htaccess'  => $htaccess,
			'web.config' => $web_config,
			'index.php'  => "<?php\n// Silence is golden.\n",
		);

		foreach ( $guards as $name => $contents ) {
			$file = trailingslashit( $dir ) . $name;
			if ( ! file_exists( $file ) ) {
				file_put_contents( $file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}
	}

	/**
	 * Store an uploaded verification document out of public reach.
	 *
	 * @param string $file_key Key within $_FILES.
	 * @param int    $post_id  Doctor post to attach the document to.
	 * @return int|\WP_Error Attachment ID, or WP_Error on failure.
	 */
	public static function handle_upload( $file_key, $post_id ) {
		if ( empty( $_FILES[ $file_key ]['name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return new \WP_Error( 'eg_care_no_document', __( 'No document was uploaded.', 'eg-care' ) );
		}

		// Make sure the directory and its guards are in place before anything lands in it.
		self::directory();

		add_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );

		$attachment_id = media_handle_upload(
			$file_key,
			$post_id,
			array(),
			array(
				'test_form' => false,
				// Enforced against the file contents, not just the extension.
				'mimes'     => self::allowed_mimes(),
			)
		);

		remove_filter( 'upload_dir', array( __CLASS__, 'filter_upload_dir' ) );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		update_post_meta( $attachment_id, self::PRIVATE_META, 1 );

		return $attachment_id;
	}

	/**
	 * Point a single upload at the protected directory.
	 *
	 * @param array $dirs Upload directory parts.
	 * @return array Modified parts.
	 */
	public static function filter_upload_dir( $dirs ) {
		$dirs['subdir'] = '/' . self::SUBDIR;
		$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];

		return $dirs;
	}

	/**
	 * Nonced URL an administrator can use to view a stored document.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string URL.
	 */
	public static function get_view_url( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		return wp_nonce_url(
			add_query_arg(
				array(
					'action'        => self::ACTION,
					'attachment_id' => $attachment_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . $attachment_id
		);
	}

	/**
	 * Stream a stored document to an authorised administrator.
	 */
	public static function serve_document() {
		$attachment_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! $attachment_id || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to view this document.', 'eg-care' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION . '_' . $attachment_id );

		$path = get_attached_file( $attachment_id );

		if ( ! $path || ! file_exists( $path ) ) {
			wp_die( esc_html__( 'Document not found.', 'eg-care' ), '', array( 'response' => 404 ) );
		}

		// Never read outside the uploads folder, whatever the attachment meta says.
		$uploads  = wp_upload_dir();
		$basedir  = realpath( $uploads['basedir'] );
		$realpath = realpath( $path );

		if ( ! $basedir || ! $realpath || 0 !== strpos( $realpath, $basedir ) ) {
			wp_die( esc_html__( 'You are not allowed to view this document.', 'eg-care' ), '', array( 'response' => 403 ) );
		}

		// Only ever hand back a type we accept, whatever is on disk.
		$filetype = wp_check_filetype( $realpath, self::allowed_mimes() );

		if ( empty( $filetype['type'] ) ) {
			wp_die( esc_html__( 'Unsupported document type.', 'eg-care' ), '', array( 'response' => 415 ) );
		}

		nocache_headers();
		header( 'Content-Type: ' . $filetype['type'] );
		header( 'Content-Length: ' . filesize( $realpath ) );
		header( 'Content-Disposition: inline; filename="' . basename( $realpath ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'" );

		readfile( $realpath ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}
}
