<?php
/**
 * Hides the WordPress version from the page source, feeds and core file URLs.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Settings;

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
		return __( 'Removes the WordPress version from the generator tag in the page source and feeds, and from ?ver= in the links to core scripts and styles. This only slows bots down; keeping WordPress updated is the real fix.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function may_break() {
		return __( 'Nothing on the site itself. Online scanners and some SEO tools will no longer show your WordPress version. Plugin versions in their own file links are not changed.', 'helloadmin-guard' );
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
