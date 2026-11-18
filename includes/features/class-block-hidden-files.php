<?php
/**
 * Setting: hidden files (.git, .env), backups and logs answer 404.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Htaccess_Feature;

defined( 'ABSPATH' ) || exit;

/**
 * A forgotten .git folder, .env file, database dump or wp-config.php copy gives away the whole site.
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
		return __( 'Block hidden files, backups and logs', 'helloadmin-guard' );
	}

	/**
	 * What it does.
	 *
	 * @return string
	 */
	public function what() {
		return __( 'The server answers 404 for files and folders that start with a dot (.git, .env, .htpasswd), database dumps and backups (.sql, .bak, .old, wp-config.php copies), logs (debug.log) and Composer or npm files. One forgotten file like this can give away passwords or the whole database.', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'Download links to .sql, .bak or .log files, if you share such files on purpose. /.well-known/ stays open: SSL certificates and app links need it.', 'helloadmin-guard' );
	}

	/**
	 * Article with details.
	 *
	 * @return string
	 */
	public function learn_more() {
		return 'https://helloadmin.com/wordpress-site-from-the-outside-12-checks/#backups';
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
			'RewriteRule (^|/)\.(?!well-known(/|$)) - [R=404,L]',
			'RewriteRule (^|/)wp-config\.php.+$ - [NC,R=404,L]',
			'RewriteRule \.(sql|sql\.gz|sql\.zip|bak|old|orig|save|swp|log)$ - [NC,R=404,L]',
			'RewriteRule (^|/)(composer\.(json|lock)|package(-lock)?\.json|yarn\.lock|php\.ini)$ - [NC,R=404,L]',
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
			'location ~ /\.(?!well-known(/|$)) { return 404; }',
			'location ~* /wp-config\.php.+$ { return 404; }',
			'location ~* \.(sql|sql\.gz|sql\.zip|bak|old|orig|save|swp|log)$ { return 404; }',
			'location ~* /(composer\.(json|lock)|package(-lock)?\.json|yarn\.lock|php\.ini)$ { return 404; }',
		);
	}
}
