<?php
/**
 * Server rule: PHP files of inactive plugins answer 404.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Htaccess_Feature;
use HelloAdmin\Guard\Probe;

defined( 'ABSPATH' ) || exit;

/**
 * An inactive plugin does not run inside WordPress, but its PHP files still run when someone opens them by URL.
 * Many plugins have files made to be opened directly (upload handlers, file managers, AJAX endpoints), and a hole in
 * such a file works whether the plugin is active or not: the elFinder connector of WP File Manager (2020) was used
 * this way. The rule closes .php files in the folders of plugins that are not active.
 *
 * The list follows the plugins: after a plugin is activated or deactivated the block no longer matches the settings,
 * and the plugin rewrites it on the next admin page (Plugin::heal_htaccess()), which is the plugins screen itself.
 */
class Block_Inactive_Plugins extends Htaccess_Feature {

	/**
	 * Folders per rule line, so one line does not grow too long.
	 */
	const PER_LINE = 20;

	/**
	 * Inactive folders found in this request (the rules are built several times per admin page).
	 *
	 * @var string[]|null
	 */
	private static $cache = null;

	/**
	 * Setting key.
	 *
	 * @return string
	 */
	public function id() {
		return 'block_inactive_plugins';
	}

	/**
	 * Title next to the toggle.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Block PHP files of inactive plugins', 'helloadmin-guard' );
	}

	/**
	 * What it does.
	 *
	 * @return string
	 */
	public function what() {
		return __( 'An inactive plugin does not run inside WordPress, but its PHP files still run when someone opens them by their address. Many plugins have files made for that (upload handlers, file managers, AJAX endpoints), and a hole in such a file works whether the plugin is on or off: the file manager hole of 2020 was used this way. The server answers 404 for .php files in the folders of plugins that are not active. When you activate or deactivate a plugin, the list is updated on the next admin page.', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'Nothing that works now: these plugins are off. If a plugin is activated with WP-CLI or straight in the database, its own PHP files answer 404 until someone opens an admin page. Better still, delete plugins you do not use: their files stay on the server and need updates too.', 'helloadmin-guard' );
	}

	/**
	 * After a plugin is activated, deactivated or deleted, the next admin page rewrites the block at once: the pause
	 * between heals (against bots that hit the login page in parallel) does not apply to an admin's own action.
	 *
	 * @param \HelloAdmin\Guard\Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( \HelloAdmin\Guard\Settings $settings ) {
		unset( $settings );
		foreach ( array( 'activated_plugin', 'deactivated_plugin', 'deleted_plugin' ) as $hook ) {
			add_action( $hook, array( $this, 'allow_heal' ) );
		}
	}

	/**
	 * Lifts the pause between heals.
	 *
	 * @return void
	 */
	public function allow_heal() {
		delete_transient( \HelloAdmin\Guard\Plugin::HEAL_WAIT );
	}

	/**
	 * Section of the server rules article.
	 *
	 * @return string
	 */
	public function anchor() {
		return 'inactive-plugins';
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
	 * Place on the tab: after the other folder rules.
	 *
	 * @return int
	 */
	public function priority() {
		return 82;
	}

	/**
	 * Option with the inactive plugin folders found last time in the admin area. The login page reads this instead
	 * of scanning every plugin file on each view (bots open it thousands of times).
	 */
	const OPTION = 'helloadmin_guard_inactive_plugins';

	/**
	 * Folders of installed plugins that are not active. Scanned in the admin area and in WP-CLI, where plugins are
	 * activated and installed; elsewhere the list saved then is used.
	 *
	 * @return string[]
	 */
	public static function inactive_folders() {
		if ( null !== self::$cache && ! did_action( 'activated_plugin' ) && ! did_action( 'deactivated_plugin' ) ) {
			return self::$cache;
		}
		$saved = get_option( self::OPTION, null );
		if ( is_array( $saved ) && ! is_admin() && ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			self::$cache = array_values( array_filter( $saved, 'is_string' ) );
			return self::$cache;
		}
		$folders = self::scan();
		if ( $folders !== $saved ) {
			update_option( self::OPTION, $folders, true );
		}
		self::$cache = $folders;
		return $folders;
	}

	/**
	 * Folders of installed plugins that are not active anywhere: on multisite a plugin active on any site of the
	 * network stays open, because all sites share one .htaccess. Single-file plugins have no folder and are left
	 * out, and so are names with characters other than letters, numbers, dots, dashes and underscores, so the
	 * rule never carries a name that changes its meaning.
	 *
	 * @return string[]
	 */
	private static function scan() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active_files = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active_files = array_merge( $active_files, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
			foreach ( get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			) as $site_id ) {
				$active_files = array_merge( $active_files, (array) get_blog_option( (int) $site_id, 'active_plugins', array() ) );
			}
		}
		$folders = array();
		$active  = array();
		foreach ( array_keys( get_plugins() ) as $file ) {
			$folder = dirname( $file );
			if ( '.' === $folder || ! preg_match( '/^[A-Za-z0-9._-]+$/', $folder ) ) {
				continue;
			}
			if ( in_array( $file, $active_files, true ) ) {
				$active[ $folder ] = true;
			} else {
				$folders[ $folder ] = true;
			}
		}
		// A folder with one active plugin file stays open, even if it holds another, inactive one.
		$folders = array_keys( array_diff_key( $folders, $active ) );
		sort( $folders );
		return $folders;
	}

	/**
	 * Is a PHP file of an inactive plugin closed?
	 *
	 * @return bool|null
	 */
	public function probe() {
		$folders = self::inactive_folders();
		if ( ! $folders ) {
			return true; // Nothing to close.
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php'; // A check may run outside the admin area.
		}
		// Up to three plugins: one may close its own folder (Akismet ships a .htaccess), which says nothing about the rest.
		$checked = 0;
		foreach ( array_slice( $folders, 0, 3 ) as $folder ) {
			$files = array_keys( get_plugins( '/' . $folder ) );
			if ( ! $files ) {
				continue;
			}
			$closed = Probe::closed( '/' . self::rel_path( plugins_url( '/' ) ) . $folder . '/' . $files[0] );
			if ( true !== $closed ) {
				return $closed;
			}
			++$checked;
		}
		return $checked ? true : null;
	}

	/**
	 * Apache rules.
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		$folders = self::inactive_folders();
		if ( ! $folders ) {
			return array( '# No inactive plugins at the moment.' );
		}
		$plugins = self::rel( plugins_url( '/' ) );
		$lines   = array( '<IfModule mod_rewrite.c>', 'RewriteEngine On' );
		foreach ( array_chunk( $folders, self::PER_LINE ) as $chunk ) {
			$lines[] = 'RewriteRule ^' . $plugins . '/(' . implode( '|', array_map( 'preg_quote', $chunk ) ) . ')/.*\\.php$ - [NC,R=404,L]';
		}
		$lines[] = '</IfModule>';
		return $lines;
	}

	/**
	 * Nginx rules.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		$folders = self::inactive_folders();
		if ( ! $folders ) {
			return array( '# No inactive plugins at the moment.' );
		}
		$plugins = self::abs( plugins_url( '/' ) );
		$lines   = array();
		foreach ( array_chunk( $folders, self::PER_LINE ) as $chunk ) {
			$lines[] = 'location ~* ^/' . $plugins . '/(' . implode( '|', array_map( 'preg_quote', $chunk ) ) . ')/.*\\.php$ { return 404; }';
		}
		return $lines;
	}
}
