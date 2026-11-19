<?php
/**
 * Setting: files and folders that start with a dot (.git, .env) answer 404.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Htaccess_Feature;

use HelloAdmin\Guard\Probe;

defined( 'ABSPATH' ) || exit;

/**
 * A .git folder gives away the whole code history, a .env file the database password and API keys.
 */
class Block_Hidden_Files extends Htaccess_Feature {

	/**
	 * Setting key.
	 *
	 * @return string
	 */
	public function id() {
		return 'block_hidden_files';
	}

	/**
	 * Title next to the toggle.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Block hidden files (.git, .env)', 'helloadmin-guard' );
	}

	/**
	 * What it does.
	 *
	 * @return string
	 */
	public function what() {
		return __( 'The server answers 404 for every file and folder whose name starts with a dot: .git (the whole code history), .env (database passwords and API keys), .htpasswd, .svn, .DS_Store, editor backups like .wp-config.php.swp. These show up when a site is deployed with Git or copied from a computer.', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'If a plugin or theme serves files to visitors from a folder whose name starts with a dot (rare), they stop opening. /.well-known/ stays open: SSL certificates and app links need it.', 'helloadmin-guard' );
	}

	/**
	 * Section of the server rules article.
	 *
	 * @return string
	 */
	public function anchor() {
		return 'hidden-files';
	}

	/**
	 * Is the gap closed now, by anyone?
	 *
	 * @return bool|null
	 */
	public function probe() {
		$path = Probe::file( '.hag-check' );
		return '' === $path ? null : Probe::closed( $path );
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
	 * Apache rules.
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		return array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteRule (^|/)\\.(?!well-known(/|$)) - [R=404,L]',
			'</IfModule>',
		);
	}

	/**
	 * Nginx rules.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		return array(
			'location ~ /\\.(?!well-known(/|$)) { return 404; }',
		);
	}
}
