<?php
/**
 * Removes the plugin's data when it is deleted from the Plugins screen.
 *
 * @package HelloAdmin\Guard
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'helloadmin_guard_settings' );
