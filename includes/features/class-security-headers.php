<?php
/**
 * Sends the basic security headers on pages, the login page and wp-admin.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Settings;

use HelloAdmin\Guard\Probe;

defined( 'ABSPATH' ) || exit;

/**
 * Four headers that are safe on almost every site. A header that is already set (by another plugin or
 * PHP code) is left alone. HSTS is not here on purpose: browsers remember it for months, so it gets its
 * own setting with its own warning.
 */
class Security_Headers extends Feature {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'security_headers';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title() {
		return __( 'Security headers', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function what() {
		return __( 'Adds four headers: X-Content-Type-Options: nosniff (browsers do not guess file types), X-Frame-Options: SAMEORIGIN (other sites cannot show your pages in a frame), Referrer-Policy: strict-origin-when-cross-origin (other sites see only your domain, not full URLs) and Permissions-Policy (no camera or microphone, location only for your own pages).', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function may_break() {
		return __( 'Showing your pages inside a frame on another domain, for example a landing page builder or a partner site. Embedded widgets from other sites that ask for the camera, microphone or location. Your own pages, the editor and the Customizer are not affected.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function undo() {
		return __( 'Turn this off and save. Browsers drop these headers on the next page load. If the server or another plugin also sends them, those copies stay.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function group() {
		return 'headers';
	}

	/**
	 * Is the gap closed now, by anyone?
	 *
	 * @return bool|null
	 */
	public function probe() {
		$answer = Probe::request( '/' );
		if ( null === $answer ) {
			return null;
		}
		foreach ( array_keys( self::headers() ) as $name ) {
			if ( ! isset( $answer['headers'][ strtolower( $name ) ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * The same at the server level, before PHP starts (also for static files).
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		$lines = array( '<IfModule mod_headers.c>' );
		foreach ( self::headers() as $name => $value ) {
			$lines[] = 'Header set ' . $name . ' "' . $value . '"';
		}
		$lines[] = '</IfModule>';
		return $lines;
	}

	/**
	 * Nginx version, shown to copy.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		$lines = array();
		foreach ( self::headers() as $name => $value ) {
			$lines[] = 'add_header ' . $name . ' "' . $value . '" always;';
		}
		return $lines;
	}

	/**
	 * Risk while off.
	 *
	 * @return string
	 */
	public function risk() {
		return 'low';
	}

	/**
	 * Place on the tab.
	 *
	 * @return int
	 */
	public function priority() {
		return 60;
	}

	/**
	 * {@inheritDoc}
	 */
	public function learn_more() {
		return 'https://helloadmin.com/wordpress-site-from-the-outside-12-checks/#headers';
	}

	/**
	 * Headers to send: name => value.
	 *
	 * @return array<string, string>
	 */
	public static function headers() {
		return array(
			'X-Content-Type-Options' => 'nosniff',
			'X-Frame-Options'        => 'SAMEORIGIN',
			'Referrer-Policy'        => 'strict-origin-when-cross-origin',
			'Permissions-Policy'     => 'camera=(), microphone=(), geolocation=(self)',
		);
	}

	/**
	 * Hooks the headers into pages, the login page and wp-admin.
	 *
	 * @param Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( Settings $settings ) {
		add_action( 'send_headers', array( $this, 'send' ) );
		add_action( 'login_init', array( $this, 'send' ) );
		add_action( 'admin_init', array( $this, 'send' ) );
	}

	/**
	 * Sends each header unless it is already in the response.
	 *
	 * @return void
	 */
	public function send() {
		if ( headers_sent() ) {
			return;
		}
		$present = array();
		foreach ( headers_list() as $line ) {
			$present[] = strtolower( trim( strstr( $line, ':', true ) ) );
		}
		// The same headers in .htaccess: Apache with PHP-FPM would send both copies.
		if ( \HelloAdmin\Guard\Htaccess::written( $this->id() ) ) {
			return;
		}
		foreach ( self::headers() as $name => $value ) {
			if ( ! in_array( strtolower( $name ), $present, true ) ) {
				header( $name . ': ' . $value );
			}
		}
	}
}
