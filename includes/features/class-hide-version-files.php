<?php
/**
 * Setting: files that show versions answer 404.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Htaccess_Feature;

defined( 'ABSPATH' ) || exit;

/**
 * The readme.html file shows the WordPress version, readme.txt of a plugin shows its exact version. Bots read these files
 * to pick sites with known holes, so the server answers 404 for them.
 */
class Hide_Version_Files extends Htaccess_Feature {

	/**
	 * Setting key.
	 *
	 * @return string
	 */
	public function id() {
		return 'hide_version_files';
	}

	/**
	 * Title next to the toggle.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Hide files that show versions', 'helloadmin-guard' );
	}

	/**
	 * What it does.
	 *
	 * @return string
	 */
	public function what() {
		return __( 'The server answers 404 for readme.html, license.txt, wp-config-sample.php and wp-links-opml.php in the site root, for readme, changelog and license files inside plugin and theme folders, and for wp-admin/install.php, setup-config.php and upgrade.php (upgrade.php stays open for logged-in users). WordPress does not load plugins on the install and upgrade pages, so they show the version even when it is hidden elsewhere. Bots read all these files to find sites with known holes.', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'Nothing on the site itself: WordPress and browsers do not need these files. Installing WordPress again over this site needs the setting off. Online scanners that guess plugin versions from readme.txt will see less.', 'helloadmin-guard' );
	}

	/**
	 * Section of the server rules article.
	 *
	 * @return string
	 */
	public function anchor() {
		return 'version-files';
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
	 * Apache rules.
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		$dirs  = self::rel( plugins_url() ) . '|' . self::rel( get_theme_root_uri() );
		$admin = self::rel( admin_url() );
		return array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteRule ^(readme\.html|license\.txt|wp-config-sample\.php|wp-links-opml\.php)$ - [NC,R=404,L]',
			'RewriteRule ^(' . $dirs . ')/[^/]+/(readme|changelog|license)\.(txt|md)$ - [NC,R=404,L]',
			'RewriteRule ^' . $admin . '/(install|setup-config)\.php$ - [NC,R=404,L]',
			// Admins open upgrade.php when WordPress asks for a database update: only visitors without a login cookie get 404.
			// Cookie name without the per-site hash, so the line is the same on every copy of the site.
			'RewriteCond %{HTTP_COOKIE} !' . preg_quote( str_replace( COOKIEHASH, '', LOGGED_IN_COOKIE ), '#' ),
			'RewriteRule ^' . $admin . '/upgrade\.php$ - [NC,R=404,L]',
			'</IfModule>',
		);
	}

	/**
	 * Nginx rules.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		$dirs = self::rel( plugins_url() ) . '|' . self::rel( get_theme_root_uri() );
		return array(
			'location ~* ^/(readme\.html|license\.txt|wp-config-sample\.php|wp-links-opml\.php)$ { return 404; }',
			'location ~* ^/(' . $dirs . ')/[^/]+/(readme|changelog|license)\.(txt|md)$ { return 404; }',
			'location ~* ^/' . self::rel( admin_url() ) . '/(install|setup-config)\.php$ { return 404; }',
		);
	}
}
