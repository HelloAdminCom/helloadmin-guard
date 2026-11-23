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
 * The plugin compares these files (name and MD5 of the content) with the list it saw last time (Feature::watch():
 * after every login, on admin pages, at least once an hour while the site gets visits; no WP-Cron) and sends one e-mail about the new and changed ones. Only files WordPress loads by itself are watched:
 * the PHP files directly in mu-plugins, also the ones whose names start with a dot, and the known drop-ins.
 */
class Mu_Plugins_Watch extends Feature {

	/**
	 * Option with the files seen last time: relative path => MD5. Autoloaded: small, read on every request.
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
		return __( 'E-mail me about new must-use plugins', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function what() {
		return __( 'Must-use plugins in wp-content/mu-plugins and drop-ins like db.php or object-cache.php run on every page, cannot be turned off in the admin area and are not in the normal plugin list, so malware likes to hide there. After every login, on admin pages and at least once an hour while the site gets visits (without WP-Cron), the plugin compares these files with the list it saw last time and e-mails the site address about new and changed ones, including files whose names start with a dot.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function may_break() {
		return __( 'Nothing stops working. You also get the e-mail when you or your host add or update a must-use plugin or a caching drop-in. If e-mail from this site does not arrive, the alert does not arrive either.', 'helloadmin-guard' );
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

		foreach ( array_keys( self::files() ) as $path ) {
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
	}

	/**
	 * Compares the files with the last list and sends one e-mail about the new and changed ones.
	 *
	 * @return void
	 */
	public function check() {
		$now                 = self::files();
		list( $known, $bad ) = self::load_list( self::SNAPSHOT_OPTION );
		if ( $bad ) {
			// The stored list cannot be trusted: report every file.
			$known = array();
		}
		$added   = array_diff_key( $now, $known );
		$changed = array();
		foreach ( array_intersect_key( $now, $known ) as $path => $hash ) {
			if ( $known[ $path ] !== $hash ) {
				$changed[ $path ] = $hash;
			}
		}

		if ( $bad || $now !== $known || ! self::list_is_signed( self::SNAPSHOT_OPTION ) ) {
			self::save_list( self::SNAPSHOT_OPTION, $now );
		}
		if ( $added || $changed || $bad ) {
			$this->send_alert( array_keys( $added ), array_keys( $changed ), $bad );
		}
	}

	/**
	 * Files WordPress loads by itself: PHP files directly in mu-plugins (dot files too) and the drop-ins.
	 *
	 * @return array<string, string> "mu-plugins/name.php" or the drop-in name => MD5 of the content, sorted by path.
	 */
	public static function files() {
		$files = array();
		$dir   = WPMU_PLUGIN_DIR;
		$base  = basename( $dir );

		// scandir(), not glob(): glob() skips names that start with a dot, wp_get_mu_plugins() does not.
		$names = is_dir( $dir ) ? scandir( $dir ) : false;
		foreach ( (array) $names as $name ) {
			$path = $dir . '/' . $name;
			if ( '.php' === substr( (string) $name, -4 ) && is_file( $path ) ) {
				$files[ $base . '/' . $name ] = (string) md5_file( $path );
			}
		}
		foreach ( self::DROPINS as $name ) {
			$path = WP_CONTENT_DIR . '/' . $name;
			if ( is_file( $path ) ) {
				$files[ $name ] = (string) md5_file( $path );
			}
		}
		ksort( $files );
		return $files;
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
		$runs   = preg_match( '/\b(eval|assert)\s*\(/i', $code );
		$decode = preg_match( '/\b(base64_decode|gzinflate|gzuncompress|gzdecode|str_rot13|hex2bin)\s*\(/i', $code );
		return $runs && $decode;
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
			$lines[] = $bad ? __( 'Files that WordPress loads on every page:', 'helloadmin-guard' ) : __( 'New files that WordPress now loads on every page:', 'helloadmin-guard' );
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
			? sprintf( __( 'New or changed must-use file: %s', 'helloadmin-guard' ), basename( $first ) )
			/* translators: %d: number of files. */
			: sprintf( __( '%d new or changed must-use files', 'helloadmin-guard' ), $count );

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
