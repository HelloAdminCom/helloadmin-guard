<?php
/**
 * Setting: backups, database dumps and wp-config copies answer 404.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Htaccess_Feature;

use HelloAdmin\Guard\Probe;

defined( 'ABSPATH' ) || exit;

/**
 * A backup left after moving a site lets anyone download the whole site with its database.
 */
class Block_Backups extends Htaccess_Feature {

	/**
	 * Setting key.
	 *
	 * @return string
	 */
	public function id() {
		return 'block_backups';
	}

	/**
	 * Title next to the toggle.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Block backups and database dumps', 'helloadmin-guard' );
	}

	/**
	 * What it does.
	 *
	 * @return string
	 */
	public function what() {
		return __( 'The server answers 404 for database dumps and backups (.sql, .sql.gz, .sqlite, .db, .bak, .old, .orig, .save, .wpress, files ending with ~), archives in the site root and in the content folder (backup.zip, site.tar.gz), every file whose name starts with wp-config (wp-config.txt, wp-config.old, wp-config.php.save; WordPress still reads wp-config.php from the disk), Duplicator installer.php leftovers and the backup folders of UpdraftPlus, All-in-One WP Migration, Duplicator and BackWPup. A file like this left after a move gives away the whole site and its database.', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'If you share links to archives in the site root or the content folder, or to .sql and .bak files, they stop downloading. Archives in the uploads folder stay open. Backup plugins keep working: they read their folders from PHP, not through the browser.', 'helloadmin-guard' );
	}

	/**
	 * Section of the server rules article.
	 *
	 * @return string
	 */
	public function anchor() {
		return 'backups';
	}

	/**
	 * Is the gap closed now, by anyone?
	 *
	 * @return bool|null
	 */
	public function probe() {
		$path = Probe::file( 'hag-check.sql' );
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
		return 20;
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
			'RewriteRule (^|/)wp-config[^/]*$ - [NC,R=404,L]',
			'RewriteRule \\.(sql|sql\\.gz|sql\\.zip|sqlite|sqlite3|db|bak|old|orig|save|wpress)$ - [NC,R=404,L]',
			'RewriteRule ~$ - [R=404,L]',
			'RewriteRule ^(' . $content . '/)?[^/]+\\.(zip|tar|tar\\.gz|tgz|gz|7z|rar)$ - [NC,R=404,L]',
			'RewriteRule ^(installer|installer-backup)\\.php$ - [NC,R=404,L]',
			'RewriteRule ^' . $content . '/(updraft|ai1wm-backups|backups-dup-lite|backups-dup-pro|backup-db|backupwordpress[^/]*|uploads/backwpup[^/]*)(/|$) - [NC,R=404,L]',
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
			'location ~* /wp-config[^/]*$ { return 404; }',
			'location ~* \\.(sql|sql\\.gz|sql\\.zip|sqlite|sqlite3|db|bak|old|orig|save|wpress)$ { return 404; }',
			'location ~ ~$ { return 404; }',
			'location ~* ^/(' . $content . '/)?[^/]+\\.(zip|tar|tar\\.gz|tgz|gz|7z|rar)$ { return 404; }',
			'location ~* ^/(installer|installer-backup)\\.php$ { return 404; }',
			'location ~* ^/' . $content . '/(updraft|ai1wm-backups|backups-dup-lite|backups-dup-pro|backup-db|backupwordpress[^/]*|uploads/backwpup[^/]*)(/|$) { return 404; }',
		);
	}
}
