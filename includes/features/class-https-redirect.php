<?php
/**
 * Setting: every http:// address answers with a redirect to the same https:// address.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Htaccess_Feature;

use HelloAdmin\Guard\Probe;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress redirects only the pages it builds itself, and only when the site address is https. Images, CSS,
 * wp-login.php opened by an old bookmark and every file PHP never sees still open over plain http.
 * The rule is the first one in the plugin's block, so the other rules answer on the https address.
 */
class Https_Redirect extends Htaccess_Feature {

	/**
	 * Setting key.
	 *
	 * @return string
	 */
	public function id() {
		return 'https_redirect';
	}

	/**
	 * Title next to the toggle.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Redirect http:// to https://', 'helloadmin-guard' );
	}

	/**
	 * What it does.
	 *
	 * @return string
	 */
	public function what() {
		return __( 'The server answers every plain http:// request with a permanent redirect to the same https:// address, before WordPress starts: pages, images, files and old links. Behind Cloudflare or another proxy the address the visitor typed counts, so there is no redirect loop. SSL certificate checks (/.well-known/, cPanel AutoSSL files) stay on http, so certificates keep renewing.', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'Only the site address and its www version are redirected. If HTTPS does not work on one of them, for example www without a certificate, that address stops opening. Other domain names pointed at the site stay on http. The www and non-www versions are left as they are: which one is main is your choice. If your host or proxy already redirects to https, the rule does nothing.', 'helloadmin-guard' );
	}

	/**
	 * Section of the server rules article.
	 *
	 * @return string
	 */
	public function anchor() {
		return 'https-redirect';
	}

	/**
	 * Is the site address https? Without that the rule would send visitors to a version the site does not use.
	 *
	 * @return bool
	 */
	private static function https_site() {
		return 0 === strpos( (string) get_option( 'home' ), 'https://' );
	}

	/**
	 * Host names of the site address (home and siteurl), without www., escaped for a rewrite pattern.
	 * Empty when a name has characters a host name cannot have.
	 *
	 * @return string[]
	 */
	private static function site_hosts() {
		$hosts = array();
		foreach ( array( 'home', 'siteurl' ) as $option ) {
			$host = strtolower( (string) wp_parse_url( (string) get_option( $option ), PHP_URL_HOST ) );
			$host = (string) preg_replace( '/^www\\./', '', $host );
			if ( ! preg_match( '/^[a-z0-9.-]+$/', $host ) ) {
				return array();
			}
			$hosts[] = preg_quote( $host, '#' );
		}
		return array_values( array_unique( $hosts ) );
	}

	/**
	 * The rule needs an https site address.
	 *
	 * @return string[]
	 */
	public function warnings() {
		if ( self::https_site() ) {
			return array();
		}
		return array( __( 'The site address in Settings, General starts with http://, so the plugin writes no rule. Switch the site to https first.', 'helloadmin-guard' ) );
	}

	/**
	 * Does the plain http home page redirect to https, by anyone?
	 *
	 * @return bool|null
	 */
	public function probe() {
		if ( ! self::https_site() ) {
			return null;
		}
		$answer = Probe::request( set_url_scheme( home_url( '/' ), 'http' ) );
		if ( null === $answer ) {
			return null;
		}
		$location = isset( $answer['headers']['location'] ) ? $answer['headers']['location'] : '';
		return $answer['code'] >= 300 && $answer['code'] < 400 && 0 === strpos( $location, 'https://' );
	}

	/**
	 * Risk while off.
	 *
	 * @return string
	 */
	public function risk() {
		return 'medium';
	}

	/**
	 * Place on the tab: right before HSTS, which needs it.
	 *
	 * @return int
	 */
	public function priority() {
		return 52;
	}

	/**
	 * Apache rules. None while the site address is http.
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		$hosts = self::site_hosts();
		if ( ! self::https_site() || ! $hosts ) {
			return array();
		}
		return array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			// Only the visitor's own request, not the second pass after WordPress rewrites it to index.php.
			'RewriteCond %{ENV:REDIRECT_STATUS} ^$',
			// Only this site's own names: the redirect target is built from the Host header the visitor sent.
			'RewriteCond %{HTTP_HOST} ^(www\\.)?(' . implode( '|', $hosts ) . ')(:[0-9]+)?$ [NC]',
			'RewriteCond %{HTTPS} !=on',
			'RewriteCond %{HTTP:X-Forwarded-Proto} !https [NC]',
			'RewriteCond %{REQUEST_URI} !^/\\.well-known/',
			'RewriteCond %{REQUEST_URI} !\\.cpaneldcv$ [NC]',
			'RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]',
			'</IfModule>',
		);
	}

	/**
	 * Nginx rules: a separate server block for port 80.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		return array(
			'# A separate server block for plain http (port 80), with server_name listing only your own domains',
			'# (not a default_server block: $host is the name the visitor sent):',
			'location /.well-known/ { try_files $uri =404; }',
			'location / { return 301 https://$host$request_uri; }',
		);
	}
}
