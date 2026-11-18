<?php
/**
 * Setting: PHP files inside wp-includes and wp-admin/includes cannot be opened from the browser.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Htaccess_Feature;

use HelloAdmin\Guard\Probe;

defined( 'ABSPATH' ) || exit;

/**
 * These files are only loaded by WordPress itself; opened directly, some of them print errors with server paths.
 */
class Block_Core_Internals extends Htaccess_Feature {

	/**
	 * Setting key.
	 *
	 * @return string
	 */
	public function id() {
		return 'block_core_internals';
	}

	/**
	 * Title next to the toggle.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Close internal WordPress folders', 'helloadmin-guard' );
	}

	/**
	 * What it does.
	 *
	 * @return string
	 */
	public function what() {
		return __( 'The server answers 404 when someone opens PHP files from wp-admin/includes, wp-includes (top level), wp-includes/theme-compat or the TinyMCE language files directly. WordPress only loads them from inside; opened from the browser, some print errors with full server paths. These are the rules from the WordPress hardening guide.', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'Multisite networks that still serve files through wp-includes/ms-files.php (networks created before WordPress 3.5).', 'helloadmin-guard' );
	}

	/**
	 * Section of the server rules article.
	 *
	 * @return string
	 */
	public function anchor() {
		return 'core-folders';
	}

	/**
	 * Is the gap closed now, by anyone?
	 *
	 * @return bool|null
	 */
	public function probe() {
		return Probe::closed( '/' . self::rel_path( includes_url() ) . 'block-patterns.php' );
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
	 * Apache rules.
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		$admin    = self::rel( admin_url() );
		$includes = self::rel( includes_url() );
		return array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteRule ^' . $admin . '/includes/ - [R=404,L]',
			'RewriteRule ^' . $includes . '/[^/]+\\.php$ - [R=404,L]',
			'RewriteRule ^' . $includes . '/js/tinymce/langs/.+\\.php - [R=404,L]',
			'RewriteRule ^' . $includes . '/theme-compat/ - [R=404,L]',
			'</IfModule>',
		);
	}

	/**
	 * Nginx rules.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		$admin    = self::rel( admin_url() );
		$includes = self::rel( includes_url() );
		return array(
			'location ~* ^/' . $admin . '/includes/ { return 404; }',
			'location ~* ^/' . $includes . '/[^/]+\\.php$ { return 404; }',
			'location ~* ^/' . $includes . '/js/tinymce/langs/.+\\.php { return 404; }',
			'location ~* ^/' . $includes . '/theme-compat/ { return 404; }',
		);
	}
}
