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
		return __( 'The server answers 404 for database dumps and backups (.sql, .sql.gz, .sql.bz2, .sql.xz, .sqlite, .db, .dump, .bak, .old, .orig, .save, .wpress, Akeeba .jpa, files ending with ~), archives in the site root and in the content folder (backup.zip, site.tar.gz), every file whose name starts with wp-config and has a dot (wp-config.txt, wp-config.old, wp-config.php.save; WordPress still reads wp-config.php from the disk), Duplicator installer.php leftovers and the backup folders of UpdraftPlus, All-in-One WP Migration, Duplicator, BackWPup and WPvivid. A file like this left after a move gives away the whole site and its database.', 'helloadmin-guard' );
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
			// Copies like wp-config.php.bak or "wp-config - Copy.php" have a dot; /wp-config-tips (a post) has none.
			'RewriteRule (^|/)wp-config[^/]*\\.[^/]*$ - [NC,R=404,L]',
			'RewriteRule \\.(sql|sql\\.gz|sql\\.zip|sql\\.bz2|sql\\.xz|sqlite|sqlite3|db|dump|bak|old|orig|save|wpress|jpa)$ - [NC,R=404,L]',
			'RewriteRule ~$ - [R=404,L]',
			'RewriteRule ^(' . $content . '/)?[^/]+\\.(zip|tar|tar\\.gz|tgz|gz|tar\\.bz2|tbz2|bz2|tar\\.xz|xz|7z|rar)$ - [NC,R=404,L]',
			'RewriteRule ^(installer|installer-backup)\\.php$ - [NC,R=404,L]',
			'RewriteRule ^' . $content . '/(updraft|ai1wm-backups|backups-dup-lite|backups-dup-pro|backup-db|backupwordpress[^/]*|backwpup-[^/]*|wpvividbackups|uploads/backwpup[^/]*)(/|$) - [NC,R=404,L]',
			'</IfModule>',
		);
	}

	/**
	 * Nginx rules.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		$content = self::abs( content_url() );
		$root    = self::prefix( home_url( '/' ), true );
		return array(
			'location ~* /wp-config[^/]*\\.[^/]*$ { return 404; }',
			'location ~* \\.(sql|sql\\.gz|sql\\.zip|sql\\.bz2|sql\\.xz|sqlite|sqlite3|db|dump|bak|old|orig|save|wpress|jpa)$ { return 404; }',
			'location ~ ~$ { return 404; }',
			'location ~* ^/' . $root . '(' . self::rel( content_url() ) . '/)?[^/]+\\.(zip|tar|tar\\.gz|tgz|gz|tar\\.bz2|tbz2|bz2|tar\\.xz|xz|7z|rar)$ { return 404; }',
			'location ~* ^/' . $root . '(installer|installer-backup)\\.php$ { return 404; }',
			'location ~* ^/' . $content . '/(updraft|ai1wm-backups|backups-dup-lite|backups-dup-pro|backup-db|backupwordpress[^/]*|backwpup-[^/]*|wpvividbackups|uploads/backwpup[^/]*)(/|$) { return 404; }',
		);
	}
}
