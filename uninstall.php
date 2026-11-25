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

/**
 * Removes the plugin's options and transients of the current site.
 *
 * @return void
 */
function helloadmin_guard_uninstall_site() {
	global $wpdb;
	delete_option( 'helloadmin_guard_settings' );
	delete_transient( 'helloadmin_guard_status' );
	delete_transient( 'helloadmin_guard_heal_wait' );
	delete_option( 'helloadmin_guard_heal_seen' );
	delete_option( 'helloadmin_guard_htaccess_backup' );
	delete_option( 'helloadmin_guard_rules_written' );
	delete_option( 'helloadmin_guard_version' );
	delete_option( 'helloadmin_guard_admins' );
	delete_option( 'helloadmin_guard_admins_checked' );
	delete_option( 'helloadmin_guard_registration' );
	delete_option( 'helloadmin_guard_admin_creds' );
	delete_option( 'helloadmin_guard_site_email' );
	wp_clear_scheduled_hook( 'helloadmin_guard_check_admins' );
	delete_option( 'helloadmin_guard_mu_files' );
	delete_option( 'helloadmin_guard_mu_scope' );
	delete_option( 'helloadmin_guard_inactive_plugins' );
	delete_option( 'helloadmin_guard_login_all' );
	delete_option( 'helloadmin_guard_login_all_lock' );
	delete_transient( 'helloadmin_guard_login_all_mailed' );
	delete_option( 'helloadmin_guard_mu_checked' );
	wp_clear_scheduled_hook( 'helloadmin_guard_check_mu_plugins' );
	// Cached REST namespace warnings, one per set of active plugins.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_helloadmin\\_guard\\_rest\\_ns\\_%' OR option_name LIKE '\\_transient\\_timeout\\_helloadmin\\_guard\\_rest\\_ns\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall, no cache to keep.
	// Login limit counters and locks are transients named after a hash of each visitor address.
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_hag\\_login\\_%' OR option_name LIKE '\\_transient\\_timeout\\_hag\\_login\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uninstall, no cache to keep.
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $helloadmin_guard_site ) {
		switch_to_blog( (int) $helloadmin_guard_site );
		helloadmin_guard_uninstall_site();
		restore_current_blog();
	}
} else {
	helloadmin_guard_uninstall_site();
}

delete_metadata( 'user', 0, 'helloadmin_guard_welcome_hidden', '', true );
delete_metadata( 'user', 0, 'helloadmin_guard_welcome_folded', '', true );
delete_metadata( 'user', 0, 'helloadmin_guard_card_view', '', true );
