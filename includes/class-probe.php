<?php
/**
 * Requests from the site to itself, as a visitor who is not logged in.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Checks what the site really answers, whoever closed the gap: this plugin, another plugin or the server.
 * No cookies are sent, so the answers are what a bot sees.
 */
class Probe {

	/**
	 * Answers already fetched in this request, keyed by method and URL.
	 *
	 * @var array<string, array{code: int, headers: array<string, string>, body: string}|null>
	 */
	private static $cache = array();

	/**
	 * Name of the temporary folder in uploads used by file(), or '' when it was not created.
	 *
	 * @var string
	 */
	private static $dir = '';

	/**
	 * Sends a request to a path of the site.
	 *
	 * @param string $path   Path from the site root, for example '/xmlrpc.php'.
	 * @param string $method GET, HEAD or POST.
	 * @param string $body   Request body for POST.
	 * @return array{code: int, headers: array<string, string>, body: string}|null Null if the site could not be reached.
	 */
	public static function request( $path, $method = 'GET', $body = '' ) {
		$url = 0 === strpos( $path, 'http' ) ? $path : home_url( $path );
		$key = $method . ' ' . $url . ' ' . md5( $body );
		if ( array_key_exists( $key, self::$cache ) ) {
			return self::$cache[ $key ];
		}

		$args = array(
			'method'      => $method,
			'timeout'     => 8,
			'redirection' => 0,
			'cookies'     => array(),
			'user-agent'  => 'Mozilla/5.0 (compatible; HelloAdmin-Guard-Check)',
			/** This filter is documented in wp-includes/class-wp-http-streams.php */
			'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
		);
		if ( '' !== $body ) {
			$args['body']    = $body;
			$args['headers'] = array( 'Content-Type' => 'text/xml' );
		}
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			self::$cache[ $key ] = null;
			return null;
		}

		$headers = array();
		foreach ( wp_remote_retrieve_headers( $response ) as $name => $value ) {
			$headers[ strtolower( (string) $name ) ] = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
		}
		self::$cache[ $key ] = array(
			'code'    => (int) wp_remote_retrieve_response_code( $response ),
			'headers' => $headers,
			'body'    => (string) wp_remote_retrieve_body( $response ),
		);
		return self::$cache[ $key ];
	}

	/**
	 * Is the path closed for visitors: 401, 403, 404 or 410?
	 *
	 * @param string $path Path from the site root.
	 * @return bool|null Null if the site could not be reached.
	 */
	public static function closed( $path ) {
		$answer = self::request( $path );
		return null === $answer ? null : in_array( $answer['code'], array( 401, 403, 404, 410 ), true );
	}

	/**
	 * Creates a harmless test file in a temporary folder inside uploads and returns its URL path.
	 * The folder is removed by cleanup() at the end of the check.
	 *
	 * @param string $name    File name, for example '.hag-probe' or 'hag-probe.sql'.
	 * @param string $content File content.
	 * @return string Path from the site root, or '' if uploads is not writable.
	 */
	public static function file( $name, $content = 'helloadmin-guard probe' ) {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}
		if ( '' === self::$dir ) {
			self::remove_leftovers( $uploads['basedir'] );
			self::$dir = 'helloadmin-guard-check-' . strtolower( wp_generate_password( 12, false ) );
		}
		$dir = trailingslashit( $uploads['basedir'] ) . self::$dir;
		if ( ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		$fs = self::fs();
		if ( ! $fs->put_contents( $dir . '/' . $name, $content, 0644 ) ) {
			return '';
		}
		return (string) wp_parse_url( trailingslashit( $uploads['baseurl'] ) . self::$dir . '/' . rawurlencode( $name ), PHP_URL_PATH );
	}

	/**
	 * Removes test folders left by an earlier check that died before cleanup() (a PHP fatal error or the time
	 * limit stops the script without running "finally"). Only folders older than ten minutes: a check started
	 * by another admin at the same moment keeps its folder.
	 *
	 * @param string $basedir Uploads folder.
	 * @return void
	 */
	private static function remove_leftovers( $basedir ) {
		$old = glob( trailingslashit( $basedir ) . 'helloadmin-guard-check-*', GLOB_ONLYDIR );
		foreach ( is_array( $old ) ? $old : array() as $dir ) {
			if ( preg_match( '/helloadmin-guard-check-[a-z0-9]{12}$/', $dir ) && time() - (int) filemtime( $dir ) > 10 * MINUTE_IN_SECONDS ) {
				self::fs()->rmdir( $dir, true );
			}
		}
	}

	/**
	 * URL path of the temporary folder itself (for the folder listing check).
	 *
	 * @return string
	 */
	public static function dir_path() {
		$uploads = wp_upload_dir( null, false );
		return '' === self::$dir ? '' : (string) wp_parse_url( trailingslashit( $uploads['baseurl'] ) . self::$dir . '/', PHP_URL_PATH );
	}

	/**
	 * Removes the temporary folder with all test files.
	 *
	 * @return void
	 */
	public static function cleanup() {
		if ( '' === self::$dir ) {
			return;
		}
		$uploads = wp_upload_dir( null, false );
		self::fs()->rmdir( trailingslashit( $uploads['basedir'] ) . self::$dir, true );
		self::$dir = '';
	}

	/**
	 * Direct file access, like Htaccess.
	 *
	 * @return \WP_Filesystem_Direct
	 */
	private static function fs() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		return new \WP_Filesystem_Direct( null );
	}
}
