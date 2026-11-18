<?php
/**
 * Setting: PHP files in the uploads folder do not run.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Htaccess_Feature;

defined( 'ABSPATH' ) || exit;

/**
 * The uploads folder is writable by PHP, so it is the usual place where a hacked plugin or form drops a backdoor.
 * Nothing there needs to run as PHP.
 */
class No_Php_In_Uploads extends Htaccess_Feature {

	/**
	 * Setting key.
	 *
	 * @return string
	 */
	public function id() {
		return 'no_php_in_uploads';
	}

	/**
	 * Title next to the toggle.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'No PHP in the uploads folder', 'helloadmin-guard' );
	}

	/**
	 * What it does.
	 *
	 * @return string
	 */
	public function what() {
		return __( 'The server answers 404 to any PHP file in the uploads folder. If a weak plugin or form lets someone upload a PHP file there, it cannot be opened from the browser.', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'A rare plugin that keeps its own PHP scripts in the uploads folder and opens them from the browser. Images, PDFs and other uploads work as before.', 'helloadmin-guard' );
	}

	/**
	 * Section of the server rules article.
	 *
	 * @return string
	 */
	public function anchor() {
		return 'php-in-uploads';
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
		return 30;
	}

	/**
	 * Apache rules.
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		return array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteRule ^' . $this->uploads() . '/.*\.(php[0-9]?|phtml|phar|pht|phps)$ - [NC,R=404,L]',
			'</IfModule>',
		);
	}

	/**
	 * Nginx rules. Must stand above the PHP location, Nginx uses the first matching regex location.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		return array(
			'location ~* ^/' . $this->uploads() . '/.*\.(php[0-9]?|phtml|phar|pht|phps)$ { return 404; }',
		);
	}

	/**
	 * Uploads folder relative to the site root, as a regex fragment.
	 *
	 * @return string
	 */
	private function uploads() {
		$uploads = wp_upload_dir( null, false );
		return self::rel( $uploads['baseurl'] );
	}
}
