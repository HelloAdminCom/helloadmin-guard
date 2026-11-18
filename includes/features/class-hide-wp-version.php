<?php
/**
 * Hides the WordPress version from the page source, feeds and core file URLs.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Settings;

use HelloAdmin\Guard\Probe;

defined( 'ABSPATH' ) || exit;

/**
 * Bots compare the WordPress version with vulnerability lists. This removes the places that print it:
 * the generator meta tag, the generator line in feeds and ?ver=X.Y.Z on core scripts and styles.
 * The ?ver value is replaced with a short hash of the version, so browser caches still refresh after
 * an update.
 */
class Hide_Wp_Version extends Feature {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'hide_wp_version';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title() {
		return __( 'Hide the WordPress version', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function what() {
		return __( 'Removes the WordPress version from the generator tag in the page source and feeds, and from ?ver= in the links to core scripts and styles. Also removes the X-Powered-By header with the PHP version and, on Apache and LiteSpeed, the server version on error pages. This only slows bots down; keeping WordPress and PHP updated is the real fix.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function may_break() {
		return __( 'Nothing changes on the site itself. Online scanners and some SEO tools no longer show your WordPress version. Plugin versions in their own file links stay as they are.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function undo() {
		return __( 'Turn this off and save. If a page looks broken right after that, clear the page cache once.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function group() {
		return 'leaks';
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
		$version = (string) get_bloginfo( 'version' );
		return false === stripos( $answer['body'], 'content="WordPress ' . $version ) && false === strpos( $answer['body'], 'ver=' . $version );
	}

	/**
	 * The same at the server level, before PHP starts (also for static files).
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		return array(
			'<IfModule mod_headers.c>',
			'Header unset X-Powered-By',
			'Header always unset X-Powered-By',
			'</IfModule>',
			'# No server name and version on error pages made by the server itself.',
			'ServerSignature Off',
		);
	}

	/**
	 * Nginx version, shown to copy.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		return array(
			'# In php.ini: expose_php = Off. In nginx.conf: server_tokens off;',
		);
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
		return 70;
	}

	/**
	 * {@inheritDoc}
	 */
	public function learn_more() {
		return 'https://helloadmin.com/wordpress-site-from-the-outside-12-checks/#plugins';
	}

	/**
	 * Adds the filters.
	 *
	 * @param Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( Settings $settings ) {
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', '__return_empty_string' );
		add_filter( 'style_loader_src', array( $this, 'mask_version' ), PHP_INT_MAX );
		add_filter( 'script_loader_src', array( $this, 'mask_version' ), PHP_INT_MAX );
		// Right away, on every request that loads WordPress: REST API and AJAX answers never reach send_headers.
		$this->remove_powered_by();
	}

	/**
	 * PHP adds "X-Powered-By: PHP/8.x" when expose_php is on in php.ini; most hosts leave it on.
	 *
	 * @return void
	 */
	public function remove_powered_by() {
		if ( ! headers_sent() ) {
			header_remove( 'X-Powered-By' );
		}
	}

	/**
	 * Replaces ver=<WordPress version> in a script or style URL with a hash of it.
	 *
	 * @param mixed $src Asset URL.
	 * @return mixed
	 */
	public function mask_version( $src ) {
		if ( ! is_string( $src ) || is_admin() ) {
			return $src;
		}
		$version = (string) get_bloginfo( 'version' );
		$query   = (string) wp_parse_url( $src, PHP_URL_QUERY );
		parse_str( $query, $args );
		if ( ! isset( $args['ver'] ) || $args['ver'] !== $version ) {
			return $src;
		}
		return add_query_arg( 'ver', substr( wp_hash( 'helloadmin-guard-ver-' . $version ), 0, 8 ), $src );
	}
}
