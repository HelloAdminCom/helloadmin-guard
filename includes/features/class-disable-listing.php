<?php
/**
 * Setting: no folder listings.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Htaccess_Feature;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress puts an empty index.php into wp-content, plugins and themes, but not into the uploads folders.
 */
class Disable_Listing extends Htaccess_Feature {

	/**
	 * Setting key.
	 *
	 * @return string
	 */
	public function id() {
		return 'disable_listing';
	}

	/**
	 * Title next to the toggle.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Turn off folder listings', 'helloadmin-guard' );
	}

	/**
	 * What it does.
	 *
	 * @return string
	 */
	public function what() {
		return __( 'Adds "Options -Indexes": when a folder has no index file, the server answers 403 instead of a list of all files in it. WordPress puts an empty index.php only into wp-content, plugins and themes, so on many hosts /wp-content/uploads/2025/05/ shows every uploaded file.', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'Nothing on the site. On a few hosts .htaccess may not change Options: then the home page answers with an error, the plugin puts the old file back and keeps this setting off.', 'helloadmin-guard' );
	}

	/**
	 * Section of the server rules article.
	 *
	 * @return string
	 */
	public function anchor() {
		return 'folder-listing';
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
	 * Place on the tab.
	 *
	 * @return int
	 */
	public function priority() {
		return 50;
	}

	/**
	 * Apache rules.
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		return array(
			'Options -Indexes',
		);
	}

	/**
	 * Nginx rules.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		return array(
			'# Nginx does not list folders unless autoindex is on; make sure it is off:',
			'autoindex off;',
		);
	}
}
