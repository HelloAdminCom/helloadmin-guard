<?php
/**
 * Removes the plugin's data when it is deleted from the Plugins screen.
 *
 * @package HelloAdmin\Guard
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Deactivation already removes the .htaccess block. This covers a block left behind, for example when the file
// was restored from a backup after that. Only the plugin's own block between its markers is removed.
require_once __DIR__ . '/includes/class-htaccess.php';
\HelloAdmin\Guard\Htaccess::sync( array() );

delete_option( 'helloadmin_guard_settings' );
delete_option( 'helloadmin_guard_htaccess_backup' );
delete_option( 'helloadmin_guard_rules_written' );
delete_option( 'helloadmin_guard_version' );
delete_option( 'helloadmin_guard_admins' );
delete_option( 'helloadmin_guard_admins_checked' );
wp_clear_scheduled_hook( 'helloadmin_guard_check_admins' );
delete_option( 'helloadmin_guard_mu_files' );
delete_option( 'helloadmin_guard_mu_checked' );
wp_clear_scheduled_hook( 'helloadmin_guard_check_mu_plugins' );
delete_metadata( 'user', 0, 'helloadmin_guard_welcome_hidden', '', true );
delete_metadata( 'user', 0, 'helloadmin_guard_welcome_folded', '', true );
