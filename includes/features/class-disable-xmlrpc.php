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
 * XML-RPC is the old remote API. Its system.multicall method lets a bot try hundreds of passwords in one
 * request. The core filter xmlrpc_enabled only switches off the methods that need a login, so the whole
 * endpoint is refused here instead.
 */
class Disable_Xmlrpc extends Feature {

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
		return __( 'Turn off XML-RPC', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function what() {
		return __( 'Every request to /xmlrpc.php gets a 403 answer, so bots cannot try many passwords in one request through system.multicall. The X-Pingback header and the RSD link in the page source are removed too.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function may_break() {
		return __( 'Jetpack and other services that connect to the site through XML-RPC, old desktop and mobile blogging apps, and pingbacks from other blogs. The WordPress mobile app works through the REST API and is not affected.', 'helloadmin-guard' );
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
		return ! ( 200 === $answer['code'] && false !== strpos( $answer['body'], 'system.multicall' ) );
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
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			add_action( 'plugins_loaded', array( $this, 'refuse' ), PHP_INT_MAX );
		}
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'xmlrpc_methods', '__return_empty_array' );
		add_filter( 'wp_headers', array( $this, 'remove_pingback_header' ) );
		remove_action( 'wp_head', 'rsd_link' );
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
