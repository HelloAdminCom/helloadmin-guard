<?php
/**
 * Plugin Name:       HelloAdmin Guard
 * Plugin URI:        https://github.com/HelloAdminCom/helloadmin-guard
 * Description:       WordPress security hardening without upsells. Every setting explains what it does, what may break and how to undo it.
 * Version:           0.1.0-dev
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            HelloAdmin
 * Author URI:        https://helloadmin.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       helloadmin-guard
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

define( 'HELLOADMIN_GUARD_VERSION', '0.1.0-dev' );
define( 'HELLOADMIN_GUARD_FILE', __FILE__ );
define( 'HELLOADMIN_GUARD_DIR', plugin_dir_path( __FILE__ ) );
define( 'HELLOADMIN_GUARD_URL', plugin_dir_url( __FILE__ ) );

/*
 * Autoloader for the plugin's own classes, WordPress file naming:
 * HelloAdmin\Guard\Admin_Page          -> includes/class-admin-page.php
 * HelloAdmin\Guard\Features\Disable_X -> includes/features/class-disable-x.php
 */
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$parts = explode( '\\', substr( $class_name, strlen( $prefix ) ) );
		$class = array_pop( $parts );
		$dir   = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';
		$file  = HELLOADMIN_GUARD_DIR . 'includes/' . $dir . 'class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

add_action(
	'plugins_loaded',
	static function () {
		Plugin::instance();
	}
);
