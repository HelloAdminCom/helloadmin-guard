<?php
/**
 * Removes the plugin's data when it is deleted from the Plugins screen.
 *
 * @package HelloAdmin\Guard
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'helloadmin_guard_settings' );
delete_option( 'helloadmin_guard_htaccess_backup' );
delete_option( 'helloadmin_guard_rules_written' );
delete_option( 'helloadmin_guard_version' );
delete_option( 'helloadmin_guard_admins' );
wp_clear_scheduled_hook( 'helloadmin_guard_check_admins' );
delete_metadata( 'user', 0, 'helloadmin_guard_welcome_hidden', '', true );
delete_metadata( 'user', 0, 'helloadmin_guard_welcome_folded', '', true );
