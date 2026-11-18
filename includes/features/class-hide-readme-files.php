<?php
/**
 * Setting: readme, license and changelog files answer 404.
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
class Hide_Readme_Files extends Htaccess_Feature {

	/**
	 * Setting key.
	 *
	 * @return string
	 */
	public function id() {
		return 'hide_readme_files';
	}

	/**
	 * Title next to the toggle.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Hide readme and changelog files', 'helloadmin-guard' );
	}

	/**
	 * What it does.
	 *
	 * @return string
	 */
	public function what() {
		return __( 'The server answers 404 for readme.html, license.txt and wp-config-sample.php in the site root and for readme, changelog and license files inside plugin and theme folders. These files show exact versions, and bots read them to find sites with known holes.', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'Nothing on the site itself: WordPress and browsers do not need these files. Online scanners that guess plugin versions from readme.txt will see less.', 'helloadmin-guard' );
	}

	/**
	 * Article with details.
	 *
	 * @return string
	 */
	public function learn_more() {
		return 'https://helloadmin.com/wordpress-site-from-the-outside-12-checks/#plugins';
	}

	/**
	 * Apache rules.
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		$dirs = self::rel( plugins_url() ) . '|' . self::rel( get_theme_root_uri() );
		return array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteRule ^(readme\.html|license\.txt|wp-config-sample\.php)$ - [NC,R=404,L]',
			'RewriteRule ^(' . $dirs . ')/[^/]+/(readme|changelog|license)\.(txt|md)$ - [NC,R=404,L]',
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
			'location ~* ^/(readme\.html|license\.txt|wp-config-sample\.php)$ { return 404; }',
			'location ~* ^/(' . $dirs . ')/[^/]+/(readme|changelog|license)\.(txt|md)$ { return 404; }',
		);
	}
}
