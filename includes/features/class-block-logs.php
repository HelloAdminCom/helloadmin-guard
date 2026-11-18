<?php
/**
 * Setting: logs, phpinfo pages and server status pages answer 404.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Htaccess_Feature;

use HelloAdmin\Guard\Probe;

defined( 'ABSPATH' ) || exit;

/**
 * Logs and phpinfo pages show server paths, user names, versions and sometimes payment errors.
 */
class Block_Logs extends Htaccess_Feature {

	/**
	 * Setting key.
	 *
	 * @return string
	 */
	public function id() {
		return 'block_logs';
	}

	/**
	 * Title next to the toggle.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Block logs and diagnostic pages', 'helloadmin-guard' );
	}

	/**
	 * What it does.
	 *
	 * @return string
	 */
	public function what() {
		return __( 'The server answers 404 for log files (debug.log, error_log, any .log), WooCommerce logs (uploads/wc-logs), Wordfence data (wflogs), forgotten test pages in the site root (phpinfo.php, info.php, test.php) and the Apache status pages (/server-status, /server-info). They show server paths, the hosting user name, versions of PHP and libraries, and sometimes errors from payment gateways.', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'You cannot open a log or a test page in the browser: read logs over SFTP or in the hosting panel instead. Logging itself keeps working.', 'helloadmin-guard' );
	}

	/**
	 * Section of the server rules article.
	 *
	 * @return string
	 */
	public function anchor() {
		return 'logs';
	}

	/**
	 * Is the gap closed now, by anyone?
	 *
	 * @return bool|null
	 */
	public function probe() {
		$path = Probe::file( 'hag-check.log' );
		return '' === $path ? null : Probe::closed( $path );
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
		return 40;
	}

	/**
	 * Apache rules.
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		$content = self::rel( content_url() );
		return array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteRule (\\.log|(^|/)error_log)$ - [NC,R=404,L]',
			'RewriteRule ^' . $content . '/(uploads/wc-logs|wflogs)(/|$) - [NC,R=404,L]',
			'RewriteRule ^(phpinfo|php_info|info|test)\\.php$ - [NC,R=404,L]',
			'RewriteRule ^(server-status|server-info)(/|$) - [NC,R=404,L]',
			'</IfModule>',
		);
	}

	/**
	 * Nginx rules.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		$content = self::rel( content_url() );
		return array(
			'location ~* (\\.log|/error_log)$ { return 404; }',
			'location ~* ^/' . $content . '/(uploads/wc-logs|wflogs)(/|$) { return 404; }',
			'location ~* ^/(phpinfo|php_info|info|test)\\.php$ { return 404; }',
		);
	}
}
