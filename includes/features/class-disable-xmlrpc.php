<?php
/**
 * Turns off xmlrpc.php: every request gets 403, the pingback header and the RSD link are removed.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Settings;

use HelloAdmin\Guard\Probe;

defined( 'ABSPATH' ) || exit;

/**
 * XML-RPC is the old remote API and a second login door next to wp-login.php: a custom login URL or a captcha
 * on the login page does not cover it, and its pingbacks are used to flood other sites and to probe the server's
 * network. (Since WordPress 4.4 system.multicall no longer checks more than one wrong password per request.)
 *
 * Mode "off": the whole endpoint is refused, because the core filter xmlrpc_enabled only switches off the
 * methods that need a login. Mode "limit": for sites that need XML-RPC (Jetpack, old apps), the endpoint stays
 * and only the pingback methods are removed. The FBI notice on Gootloader (CC-111626-MW) says: disable
 * xmlrpc.php if unused, otherwise allow only the methods you need.
 */
class Disable_Xmlrpc extends Feature {

	/**
	 * Mode: the whole endpoint answers 403.
	 */
	const MODE_OFF = 'off';

	/**
	 * Mode: XML-RPC stays for Jetpack and apps, pingbacks are removed.
	 */
	const MODE_LIMIT = 'limit';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'disable_xmlrpc';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title() {
		return __( 'Turn off or limit XML-RPC', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function what() {
		return __( 'XML-RPC is an old remote API and a second login door next to the login page: a custom login URL or a captcha does not cover it, and bots use its pingbacks to flood other sites. "Turn off" answers every request to /xmlrpc.php with 403. "Keep for Jetpack and apps" leaves XML-RPC working and removes only the pingback methods. Both remove the X-Pingback header.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function may_break() {
		return __( 'Turn off: Jetpack and other services that connect through XML-RPC, old desktop and mobile blogging apps and pingbacks stop working. Keep for Jetpack and apps: only pingbacks stop. The WordPress mobile app works through the REST API and is not affected in either mode.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function undo() {
		return __( 'Turn this off and save. Nothing is deleted or changed in the database.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function group() {
		return 'legacy';
	}

	/**
	 * Is the gap closed now, by anyone?
	 *
	 * @return bool|null
	 */
	public function probe() {
		$xml    = '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName><params></params></methodCall>';
		$answer = Probe::request( '/xmlrpc.php', 'POST', $xml );
		if ( null === $answer ) {
			return null;
		}
		$open = 200 === $answer['code'] && false !== strpos( $answer['body'], 'system.multicall' );
		// In the limit mode XML-RPC answers on purpose; the gap is closed when pingbacks are gone.
		if ( $open && $this->is_limit_mode() ) {
			return false === strpos( $answer['body'], 'pingback.ping' );
		}
		return ! $open;
	}

	/**
	 * Mode choice.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function fields() {
		return array(
			'mode' => array(
				'label'       => __( 'Mode', 'helloadmin-guard' ),
				'type'        => 'radio',
				'default'     => self::MODE_OFF,
				'options'     => array(
					self::MODE_OFF   => __( 'Turn off (recommended if nothing on the site uses XML-RPC)', 'helloadmin-guard' ),
					self::MODE_LIMIT => __( 'Keep for Jetpack and apps, turn off pingbacks', 'helloadmin-guard' ),
				),
				'description' => __( 'Not sure? Choose Turn off, then check that Jetpack or your apps still work. If something breaks, switch to the second option.', 'helloadmin-guard' ),
			),
		);
	}

	/**
	 * Keeps only a known mode.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	public function sanitize_field( $field, $value ) {
		unset( $field );
		return self::MODE_LIMIT === $value ? self::MODE_LIMIT : self::MODE_OFF;
	}

	/**
	 * The same at the server level, before PHP starts (also for static files).
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		if ( $this->is_limit_mode() ) {
			return array();
		}
		return array(
			'<Files xmlrpc.php>',
			'<IfModule mod_authz_core.c>',
			'Require all denied',
			'</IfModule>',
			'<IfModule !mod_authz_core.c>',
			'Order deny,allow',
			'Deny from all',
			'</IfModule>',
			'</Files>',
		);
	}

	/**
	 * Nginx version, shown to copy.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		if ( $this->is_limit_mode() ) {
			return array();
		}
		return array(
			'location = /' . ltrim( (string) wp_parse_url( site_url( 'xmlrpc.php' ), PHP_URL_PATH ), '/' ) . ' { return 403; }',
		);
	}

	/**
	 * Risk while off.
	 *
	 * @return string
	 */
	public function risk() {
		return 'high';
	}

	/**
	 * Place on the tab.
	 *
	 * @return int
	 */
	public function priority() {
		return 10;
	}

	/**
	 * {@inheritDoc}
	 */
	public function learn_more() {
		return 'https://helloadmin.com/disable-and-secure-wordpress-xmlrpc-php-against-brute-force-attacks/';
	}

	/**
	 * Adds the hooks.
	 *
	 * @param Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( Settings $settings ) {
		add_filter( 'wp_headers', array( $this, 'remove_pingback_header' ) );
		if ( $this->is_limit_mode() ) {
			add_filter( 'xmlrpc_methods', array( $this, 'remove_pingback_methods' ) );
			return;
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			add_action( 'plugins_loaded', array( $this, 'refuse' ), PHP_INT_MAX );
		}
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );
		remove_action( 'wp_head', 'rsd_link' );
	}

	/**
	 * Whether the limit mode is chosen. Reads the saved value directly: enable() runs before translations load.
	 *
	 * @return bool
	 */
	private function is_limit_mode() {
		return self::MODE_LIMIT === $this->saved_value( 'mode', self::MODE_OFF );
	}

	/**
	 * Removes the pingback methods (limit mode).
	 *
	 * @param array<string, mixed> $methods XML-RPC methods.
	 * @return array<string, mixed>
	 */
	public function remove_pingback_methods( $methods ) {
		unset( $methods['pingback.ping'], $methods['pingback.extensions.getPingbacks'] );
		return $methods;
	}

	/**
	 * Stops the XML-RPC request with 403 before any method runs.
	 *
	 * @return void
	 */
	public function refuse() {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		// Not translated: this runs before WordPress loads translations, and the reader is a program.
		echo 'XML-RPC is turned off on this site.';
		exit;
	}

	/**
	 * Removes X-Pingback from the response headers.
	 *
	 * @param array<string, string> $headers Response headers.
	 * @return array<string, string>
	 */
	public function remove_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
}
