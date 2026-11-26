<?php
/**
 * Sends an e-mail when a must-use plugin or a drop-in appears or changes, and points out suspicious ones.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Must-use plugins (wp-content/mu-plugins) and drop-ins (db.php, object-cache.php and others in wp-content)
 * run on every request, cannot be turned off in the admin area and are not in the normal plugin list.
 * That makes them a favourite place for malware: the FBI notice on Gootloader (CC-111626-MW) names hidden
 * and fake must-use plugins in the StopAndProtect and DollyWay campaigns.
 *
 * The plugin compares these files (name and SHA-256 of the content) with the list it saw last time (Feature::watch():
 * after every login, on admin pages, at least once an hour while the site gets visits; no WP-Cron) and sends one e-mail about the new and changed ones. Only files WordPress loads by itself are watched:
 * the PHP files directly in mu-plugins, also the ones whose names start with a dot, and the known drop-ins.
 */
class Mu_Plugins_Watch extends Feature {

	/**
	 * Option with the files seen last time: relative path => SHA-256 (MD5 in lists from before 0.6). Autoloaded: small, read on every request.
	 */
	const SNAPSHOT_OPTION = 'helloadmin_guard_mu_files';

	/**
	 * Option with the time of the last regular check.
	 */
	const CHECKED_OPTION = 'helloadmin_guard_mu_checked';

	/**
	 * Cron hook of the hourly check in 0.5 before the regular check replaced it; only cleaned up now.
	 */
	const CRON_HOOK = 'helloadmin_guard_check_mu_plugins';

	/**
	 * Option with the version of the watched file set. Files of a set added in an update are only remembered the
	 * first time, so an update does not send an e-mail about files that were there all along.
	 */
	const SCOPE_OPTION = 'helloadmin_guard_mu_scope';

	/**
	 * Version of the watched file set: 1 = mu-plugins and drop-ins, 2 = also subfolders of mu-plugins and the
	 * site files below.
	 */
	const SCOPE = 2;

	/**
	 * Files in the site folder that run on every request or control how PHP runs: key in the list => file name.
	 * .user.ini and php.ini can add a file to every request (auto_prepend_file), a common way for malware to stay.
	 */
	const SITE_FILES = array( 'index.php', 'wp-config.php', '.user.ini', 'php.ini' );

	/**
	 * Subfolders of mu-plugins that hosts install and update themselves; their files are not watched.
	 */
	const HOST_FOLDERS = array( 'kinsta-mu-plugins', 'wpengine-common', 'gd-system-plugin', 'pantheon-mu-plugin', 'wpcomsh' );

	/**
	 * Most files read from mu-plugins subfolders, so a huge folder cannot slow the check down.
	 */
	const MAX_SUBFOLDER_FILES = 2000;

	/**
	 * Set when the last scan stopped at MAX_SUBFOLDER_FILES (shown on the card).
	 *
	 * @var bool
	 */
	private static $cut = false;

	/**
	 * Drop-in file names WordPress loads from wp-content (see _get_dropins()).
	 */
	const DROPINS = array(
		'advanced-cache.php',
		'db.php',
		'db-error.php',
		'install.php',
		'maintenance.php',
		'object-cache.php',
		'php-error.php',
		'fatal-error-handler.php',
		'sunrise.php',
		'blog-deleted.php',
		'blog-inactive.php',
		'blog-suspended.php',
	);

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'mu_plugins_watch';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title() {
		return __( 'E-mail me about changed must-use plugins and site files', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function what() {
		return __( 'Must-use plugins in wp-content/mu-plugins and drop-ins like db.php or object-cache.php run on every page, cannot be turned off in the admin area and are not in the normal plugin list, so malware likes to hide there. The same goes for wp-config.php and index.php, and for .user.ini or php.ini in the site folder, which can add a file to every request. After every login, on admin pages and at least once an hour while the site gets visits (without WP-Cron), the plugin compares these files with the list it saw last time and e-mails the administration address (Settings → General) about new and changed ones, including files whose names start with a dot and PHP files in subfolders of mu-plugins (except the folders hosts manage themselves).', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function may_break() {
		return __( 'Nothing stops working. You also get the e-mail when you or your host add or update a must-use plugin or a caching drop-in, and when a plugin writes to wp-config.php (caching and SSL plugins do). If e-mail from this site does not arrive, the alert does not arrive either.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function undo() {
		return __( 'Turn this off and save. No files are changed or deleted.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function group() {
		return 'files';
	}

	/**
	 * Sends e-mails, nothing to check from outside.
	 *
	 * @return bool
	 */
	public function is_alert() {
		return true;
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
	 * Place on the tab: next to the admin alert.
	 *
	 * @return int
	 */
	public function priority() {
		return 46;
	}

	/**
	 * Extra address for the alert.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function fields() {
		return self::email_field();
	}

	/**
	 * Keeps only a valid e-mail address.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	public function sanitize_field( $field, $value ) {
		unset( $field );
		return sanitize_email( is_scalar( $value ) ? (string) $value : '' );
	}

	/**
	 * Files that look suspicious right now, and a hidden Must-Use list.
	 *
	 * @return string[]
	 */
	public function warnings() {
		$warnings = array();

		/** This filter is documented in wp-admin/includes/class-wp-plugins-list-table.php */
		if ( ! apply_filters( 'show_advanced_plugins', true, 'mustuse' ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
			$warnings[] = __( 'Something on this site hides the Must-Use list on the Plugins screen. Malware does this to keep its files out of sight.', 'helloadmin-guard' );
		}

		$files = self::files();
		if ( self::$cut ) {
			/* translators: %d: number of files. */
			$warnings[] = sprintf( __( 'The subfolders of mu-plugins hold more than %d PHP files, so the plugin watches only the first ones. Files after them are not checked. Move libraries out of mu-plugins, or watch that folder with your host\'s tools.', 'helloadmin-guard' ), self::MAX_SUBFOLDER_FILES );
		}
		foreach ( array_keys( $files ) as $path ) {
			$name = basename( $path );
			if ( 0 === strpos( $name, '.' ) ) {
				/* translators: %s: file path inside wp-content. */
				$warnings[] = sprintf( __( '%s has a name that starts with a dot, so many FTP programs and file managers do not show it, but WordPress loads it on every page.', 'helloadmin-guard' ), $path );
			}
			if ( self::looks_obfuscated( self::full_path( $path ) ) ) {
				/* translators: %s: file path inside wp-content. */
				$warnings[] = sprintf( __( '%s runs code it decodes first (eval with base64, gzinflate or similar). That is how malware hides what it does. Check this file.', 'helloadmin-guard' ), $path );
			}
		}
		return $warnings;
	}

	/**
	 * Adds the regular check.
	 *
	 * @param Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( Settings $settings ) {
		$this->watch( array( $this, 'check' ), self::CHECKED_OPTION );
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			self::unschedule();
		}
	}

	/**
	 * Remembers the current files when the setting is switched on, so only later changes are reported.
	 * Only here: a list that goes missing later is reported, not quietly rebuilt.
	 *
	 * @return void
	 */
	public function switched_on() {
		self::save_list( self::SNAPSHOT_OPTION, self::files() );
		update_option( self::SCOPE_OPTION, self::SCOPE, true );
	}

	/**
	 * Compares the files with the last list and sends one e-mail about the new and changed ones.
	 *
	 * @return void
	 */
	public function check() {
		$now                 = self::files();
		list( $known, $bad ) = self::load_list( self::SNAPSHOT_OPTION );
		$stored              = $known;
		if ( $bad ) {
			// The stored list cannot be trusted: report every file.
			$known = array();
		} else {
			// Lists from before 0.6 hold MD5 sums: a file whose MD5 still matches is unchanged, keep it quietly.
			foreach ( $known as $path => $hash ) {
				$file = self::full_path( (string) $path );
				if ( is_string( $hash ) && 32 === strlen( $hash ) && isset( $now[ $path ] ) && '' !== $file && is_file( $file ) && md5_file( $file ) === $hash ) {
					$known[ $path ] = $now[ $path ];
				}
			}
		}
		if ( ! $bad && (int) get_option( self::SCOPE_OPTION, 1 ) < self::SCOPE ) {
			// First check after an update that watches more files: they were there before, only remember them.
			foreach ( $now as $path => $hash ) {
				if ( ! isset( $known[ $path ] ) && self::new_in_scope( $path ) ) {
					$known[ $path ] = $hash;
				}
			}
			update_option( self::SCOPE_OPTION, self::SCOPE, true );
		}
		$added   = array_diff_key( $now, $known );
		$changed = array();
		foreach ( array_intersect_key( $now, $known ) as $path => $hash ) {
			if ( $known[ $path ] !== $hash ) {
				$changed[ $path ] = $hash;
			}
		}

		// Compared with what is stored, not with $known: files remembered after an update must be saved too.
		if ( $bad || $now !== $stored || ! self::list_is_signed( self::SNAPSHOT_OPTION ) ) {
			self::save_list( self::SNAPSHOT_OPTION, $now );
		}
		if ( $added || $changed || $bad ) {
			$this->send_alert( array_keys( $added ), array_keys( $changed ), $bad );
		}
	}

	/**
	 * Whether a list key belongs to the files added in scope 2 (subfolders of mu-plugins, site files).
	 *
	 * @param string $path Path as stored in the list.
	 * @return bool
	 */
	private static function new_in_scope( $path ) {
		if ( 0 === strpos( $path, 'site:' ) ) {
			return true;
		}
		// A file inside a subfolder of mu-plugins: "mu-plugins/<folder>/<file>"; drop-ins have no folder at all.
		$base = basename( WPMU_PLUGIN_DIR ) . '/';
		return 0 === strpos( $path, $base ) && false !== strpos( substr( $path, strlen( $base ) ), '/' );
	}

	/**
	 * Watched files: PHP files in mu-plugins (dot files too) and its subfolders, the drop-ins, and the site files.
	 *
	 * @return array<string, string> "mu-plugins/name.php", "mu-plugins/folder/name.php", the drop-in name or
	 *                               "site:wp-config.php" => SHA-256 of the content, sorted by path.
	 */
	public static function files() {
		static $memo = null;
		// The card warnings and the regular check may both ask in one request: scan and hash the files once.
		if ( null !== $memo ) {
			return $memo;
		}
		$files = array();
		$dir   = WPMU_PLUGIN_DIR;
		$base  = basename( $dir );

		// scandir(), not glob(): glob() skips names that start with a dot, wp_get_mu_plugins() does not.
		$names     = is_dir( $dir ) ? scandir( $dir ) : false;
		$left      = self::MAX_SUBFOLDER_FILES;
		self::$cut = false;
		foreach ( (array) $names as $name ) {
			$name = (string) $name;
			$path = $dir . '/' . $name;
			if ( \HelloAdmin\Guard\Upgrade_Helper::FILE === $name && \HelloAdmin\Guard\Upgrade_Helper::is_ours( $path ) ) {
				// This plugin's own file, byte for byte; anything else with that name is still reported.
				continue;
			}
			if ( '.php' === substr( $name, -4 ) && is_file( $path ) ) {
				$files[ $base . '/' . $name ] = (string) hash_file( 'sha256', $path );
			} elseif ( '.' !== $name && '..' !== $name && is_dir( $path ) && ! is_link( $path ) && ! in_array( $name, self::host_folders(), true ) ) {
				// Not loaded by WordPress itself, but a loader file in mu-plugins can include anything from here.
				self::scan_folder( $path, $base . '/' . $name, 1, $left, $files );
			}
		}
		foreach ( self::SITE_FILES as $name ) {
			$path = self::site_file( $name );
			if ( '' !== $path && is_file( $path ) ) {
				$files[ 'site:' . $name ] = (string) hash_file( 'sha256', $path );
			}
		}
		foreach ( self::DROPINS as $name ) {
			$path = WP_CONTENT_DIR . '/' . $name;
			if ( is_file( $path ) ) {
				$files[ $name ] = (string) hash_file( 'sha256', $path );
			}
		}
		ksort( $files );
		$memo = $files;
		return $files;
	}

	/**
	 * Subfolders of mu-plugins left out of the check.
	 *
	 * @return string[]
	 */
	private static function host_folders() {
		/**
		 * Subfolders of wp-content/mu-plugins that HelloAdmin Guard does not watch, because the host updates them.
		 *
		 * @param string[] $folders Folder names.
		 */
		return (array) apply_filters( 'helloadmin_guard_mu_ignore', self::HOST_FOLDERS );
	}

	/**
	 * Adds the PHP files of a mu-plugins subfolder to the list, three levels deep at most.
	 *
	 * @param string                $dir   Full path of the folder.
	 * @param string                $key   Its path in the list.
	 * @param int                   $depth Level of this folder.
	 * @param int                   $left  Files that may still be read (shared by all folders).
	 * @param array<string, string> $files List to add to.
	 * @return void
	 */
	private static function scan_folder( $dir, $key, $depth, &$left, array &$files ) {
		$names = scandir( $dir );
		foreach ( (array) $names as $name ) {
			$name = (string) $name;
			if ( '.' === $name || '..' === $name ) {
				continue;
			}
			if ( $left <= 0 ) {
				self::$cut = true;
				return;
			}
			$path = $dir . '/' . $name;
			if ( is_link( $path ) ) {
				continue;
			}
			if ( '.php' === substr( $name, -4 ) && is_file( $path ) ) {
				$files[ $key . '/' . $name ] = (string) hash_file( 'sha256', $path );
				--$left;
			} elseif ( $depth < 3 && is_dir( $path ) ) {
				self::scan_folder( $path, $key . '/' . $name, $depth + 1, $left, $files );
			}
		}
	}

	/**
	 * Full path of a watched site file. wp-config.php may sit one folder above the site, as WordPress allows.
	 *
	 * @param string $name File name from SITE_FILES.
	 * @return string Empty when not found.
	 */
	private static function site_file( $name ) {
		if ( is_file( ABSPATH . $name ) ) {
			return ABSPATH . $name;
		}
		if ( 'wp-config.php' === $name && is_file( dirname( ABSPATH ) . '/wp-config.php' ) && ! is_file( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return dirname( ABSPATH ) . '/wp-config.php';
		}
		return '';
	}

	/**
	 * Whether a file runs decoded code: eval() or assert() next to base64_decode, gzinflate, str_rot13 and similar.
	 *
	 * @param string $file Full path.
	 * @return bool
	 */
	public static function looks_obfuscated( $file ) {
		$code = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file, read only.
		if ( '' === $code ) {
			return false;
		}
		// The decoding call inside the eval() or assert() call itself, not anywhere in the file: big libraries have
		// both functions in different places. assert() stays: on PHP 7 it still runs a string as code.
		return (bool) preg_match( '/\b(eval|assert)\s*\([^;]{0,200}?\b(base64_decode|gzinflate|gzuncompress|gzdecode|str_rot13|hex2bin)\s*\(/is', $code );
	}

	/**
	 * Sends the e-mail about new and changed files.
	 *
	 * @param string[] $added   New files, as stored in the list.
	 * @param string[] $changed Changed files, as stored in the list.
	 * @param bool     $bad     The stored list was changed or removed outside the plugin.
	 * @return void
	 */
	private function send_alert( array $added, array $changed, $bad = false ) {
		$lines = array();
		if ( $bad ) {
			$lines[] = __( 'The list of must-use files that HelloAdmin Guard keeps was changed or removed outside the plugin, for example straight in the database. Someone may have hidden a file from this alert, so here are all the files WordPress loads by itself.', 'helloadmin-guard' );
			$lines[] = __( 'If you changed the WordPress salts in wp-config.php or moved the database to another site, this is expected.', 'helloadmin-guard' );
			$lines[] = '';
			if ( ! $added ) {
				$lines[] = __( 'There are no such files now.', 'helloadmin-guard' );
				$lines[] = '';
			}
		}
		if ( $added ) {
			$lines[] = $bad ? __( 'All files this alert watches:', 'helloadmin-guard' ) : __( 'New files that WordPress now loads on every page:', 'helloadmin-guard' );
			foreach ( $added as $path ) {
				$lines[] = '  ' . self::shown_path( $path ) . self::file_info( $path );
			}
			$lines[] = '';
		}
		if ( $changed ) {
			$lines[] = __( 'Changed files:', 'helloadmin-guard' );
			foreach ( $changed as $path ) {
				$lines[] = '  ' . self::shown_path( $path ) . self::file_info( $path );
			}
			$lines[] = '';
		}
		$lines[] = __( 'If you, your developer or your host added or updated them, nothing to do. Hosts and caching plugins add files like these too.', 'helloadmin-guard' );
		$lines[] = __( 'If not: copy the file somewhere safe first (it is evidence), then remove it from the site, change the passwords of all administrators and of the hosting, and check the list of administrators.', 'helloadmin-guard' );
		$lines[] = '';
		$lines[] = admin_url( 'plugins.php?plugin_status=mustuse' );

		if ( $bad ) {
			$this->send_alert_mail( __( 'The list of must-use files was changed outside the plugin', 'helloadmin-guard' ), $lines );
			return;
		}
		$first   = $added ? $added[0] : $changed[0];
		$count   = count( $added ) + count( $changed );
		$subject = 1 === $count
			/* translators: %s: file name. */
			? sprintf( __( 'New or changed file: %s', 'helloadmin-guard' ), basename( str_replace( 'site:', '', $first ) ) )
			/* translators: %d: number of files. */
			: sprintf( _n( '%d new or changed file that runs on every page', '%d new or changed files that run on every page', $count, 'helloadmin-guard' ), $count );

		$this->send_alert_mail( $subject, $lines );
	}

	/**
	 * Size and modification time of a file, for the e-mail.
	 *
	 * @param string $path Path as stored in the list.
	 * @return string
	 */
	private static function file_info( $path ) {
		$file = self::full_path( $path );
		if ( ! is_file( $file ) ) {
			return '';
		}
		$time = (int) filemtime( $file );
		/* translators: 1: file size, 2: date and time. */
		return ' (' . sprintf( __( '%1$s, modified %2$s', 'helloadmin-guard' ), size_format( (int) filesize( $file ) ), wp_date( 'Y-m-d H:i', $time ) ) . ')';
	}

	/**
	 * Full path of a watched file: "mu-plugins/x.php" lives in WPMU_PLUGIN_DIR, drop-ins in WP_CONTENT_DIR.
	 *
	 * @param string $path Path as stored in the list.
	 * @return string
	 */
	private static function full_path( $path ) {
		if ( 0 === strpos( $path, 'site:' ) ) {
			return self::site_file( substr( $path, 5 ) );
		}
		$mu = basename( WPMU_PLUGIN_DIR ) . '/';
		return 0 === strpos( $path, $mu ) ? WPMU_PLUGIN_DIR . '/' . substr( $path, strlen( $mu ) ) : WP_CONTENT_DIR . '/' . $path;
	}

	/**
	 * Path for the e-mail, relative to the site folder when possible (wp-content/mu-plugins/x.php).
	 *
	 * @param string $path Path as stored in the list.
	 * @return string
	 */
	private static function shown_path( $path ) {
		$full = wp_normalize_path( self::full_path( $path ) );
		$root = wp_normalize_path( ABSPATH );
		return 0 === strpos( $full, $root ) ? substr( $full, strlen( $root ) ) : $full;
	}

	/**
	 * Removes the old hourly cron event (plugin deactivated or deleted, or left from an earlier 0.5 build).
	 *
	 * @return void
	 */
	public static function unschedule() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}
}
