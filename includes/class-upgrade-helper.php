<?php
/**
 * Small must-use plugin that closes wp-admin/upgrade.php for visitors who are not logged in.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress defines WP_INSTALLING on upgrade.php, so normal plugins do not load there and cannot answer 404. The
 * server rule of "Hide files that show versions" lets through requests with a login cookie, and Apache cannot check
 * that cookie: anyone can send a fake one and see the WordPress version. Must-use plugins do load on upgrade.php, so
 * while that setting is on, this class keeps a small file in wp-content/mu-plugins that answers 404 to visitors who
 * are not logged in, unless a database update is due (then upgrade.php works as WordPress made it).
 *
 * The file checks the setting itself, so a copy left behind does nothing once the setting is off. It is written and
 * removed on admin pages, removed on deactivation and on uninstall.
 */
class Upgrade_Helper {

	/**
	 * File name in the must-use plugins folder.
	 */
	const FILE = 'helloadmin-guard-upgrade.php';

	/**
	 * Setting that owns the file.
	 */
	const SETTING = 'hide_version_files';

	/**
	 * Content of the file. The same on every site, so the must-use file alert can tell it from anything else with
	 * the same name.
	 *
	 * @return string
	 */
	public static function content() {
		return <<<'PHP'
<?php
/**
 * Plugin Name: HelloAdmin Guard: upgrade.php for visitors
 * Description: Written by HelloAdmin Guard while "Hide files that show versions" is on. Visitors who are not logged in get 404 on wp-admin/upgrade.php unless a database update is due. Removed when the setting or the plugin is turned off.
 */

defined( 'ABSPATH' ) || exit;

// $pagenow is set after must-use plugins load, so the page is checked on init.
if ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) {
	add_action(
		'init',
		static function () {
			global $wp_db_version, $pagenow;
			if ( 'upgrade.php' !== $pagenow ) {
				return;
			}
			$settings = get_option( 'helloadmin_guard_settings' );
			if ( empty( $settings['enabled']['hide_version_files'] ) || is_user_logged_in() ) {
				return;
			}
			if ( (int) get_option( 'db_version' ) !== (int) $wp_db_version ) {
				return;
			}
			status_header( 404 );
			nocache_headers();
			exit;
		},
		0
	);
}

PHP;
	}

	/**
	 * Full path of the file.
	 *
	 * @return string
	 */
	public static function path() {
		return WPMU_PLUGIN_DIR . '/' . self::FILE;
	}

	/**
	 * Whether the file at this path is exactly the one this class writes.
	 *
	 * @param string $path File path.
	 * @return bool
	 */
	public static function is_ours( $path ) {
		return is_file( $path ) && hash_file( 'sha256', $path ) === hash( 'sha256', self::content() );
	}

	/**
	 * Writes or removes the file to match the setting. Runs on admin pages.
	 *
	 * @param bool $on Whether the setting is on.
	 * @return void
	 */
	public static function sync( $on ) {
		$path = self::path();
		if ( ! $on ) {
			self::remove();
			return;
		}
		if ( self::is_ours( $path ) ) {
			return;
		}
		if ( file_exists( $path ) ) {
			// Something else has this name: leave it alone, the must-use file alert reports it.
			return;
		}
		if ( ! is_dir( WPMU_PLUGIN_DIR ) && ! wp_mkdir_p( WPMU_PLUGIN_DIR ) ) {
			return;
		}
		$fs = self::fs();
		if ( $fs->is_writable( WPMU_PLUGIN_DIR ) ) {
			$fs->put_contents( $path, self::content(), 0644 );
		}
	}

	/**
	 * Removes the file, only if it is the one this class wrote.
	 *
	 * @return void
	 */
	public static function remove() {
		$path = self::path();
		if ( self::is_ours( $path ) ) {
			self::fs()->delete( $path );
		}
	}

	/**
	 * Whether the file is in place and works.
	 *
	 * @return bool
	 */
	public static function in_place() {
		return self::is_ours( self::path() );
	}

	/**
	 * Direct file access, as for .htaccess: the folder is inside wp-content and PHP owns it on most hosts.
	 *
	 * @return \WP_Filesystem_Direct
	 */
	private static function fs() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		return new \WP_Filesystem_Direct( null );
	}
}
