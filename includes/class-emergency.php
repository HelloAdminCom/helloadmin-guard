<?php
/**
 * The "If hacked" tab: one button that logs out every other session and deletes all application passwords.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * The FBI notice on Gootloader (CC-111626-MW, "WordPress Response") says to revoke active sessions and
 * application passwords when a compromise is suspected. Changing a password does not end the sessions
 * that are already open, and application passwords keep working after it, so a stolen session or an
 * app password stays a way in. This button closes both.
 *
 * The session of the admin who presses the button is kept, so nobody is thrown out in the middle of the
 * cleanup; every other session of every user ends, including other browsers of the same admin.
 */
class Emergency {

	/**
	 * Action name for admin-post.php and the nonce.
	 */
	const ACTION = 'helloadmin_guard_emergency';

	/**
	 * Transient prefix for the result message (one per user, shown once).
	 */
	const RESULT = 'helloadmin_guard_emergency_';

	/**
	 * Hooks the form handler.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Who may press the button. On multisite it ends sessions of every user of the network, so only a super
	 * admin may, not the admin of one site.
	 *
	 * @return bool
	 */
	private static function allowed() {
		return is_multisite() ? is_super_admin() : current_user_can( 'manage_options' );
	}

	/**
	 * Handles the button: checks the nonce and the confirmation, does the cleanup, redirects back.
	 *
	 * @return void
	 */
	public function handle() {
		if ( ! self::allowed() ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do this.', 'helloadmin-guard' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::ACTION );

		$back = add_query_arg(
			array(
				'page' => Admin_Page::SLUG,
				'tab'  => 'emergency',
			),
			admin_url( 'admin.php' )
		);

		if ( empty( $_POST['hag_confirm'] ) ) {
			set_transient( self::RESULT . get_current_user_id(), array( 'error', __( 'Nothing was done: tick the box to confirm first.', 'helloadmin-guard' ) ), 5 * MINUTE_IN_SECONDS );
			wp_safe_redirect( $back );
			exit;
		}

		$done = self::run();

		/* translators: 1: number of sessions, 2: number of users, 3: number of application passwords. */
		$message = sprintf( __( 'Done. Sessions ended: %1$d (users: %2$d). Application passwords deleted: %3$d. Your session in this browser is kept.', 'helloadmin-guard' ), $done['sessions'], $done['users'], $done['app_passwords'] );
		set_transient( self::RESULT . get_current_user_id(), array( 'success', $message ), 5 * MINUTE_IN_SECONDS );
		self::notify( $message );

		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * Ends every session except the current one and deletes every application password.
	 *
	 * @return array{sessions: int, users: int, app_passwords: int} What was removed.
	 */
	public static function run() {
		$me      = get_current_user_id();
		$token   = wp_get_session_token();
		$counts  = array(
			'sessions'      => 0,
			'users'         => 0,
			'app_passwords' => 0,
		);
		$session = self::users_with_meta( 'session_tokens' );
		foreach ( $session as $user_id ) {
			$manager = \WP_Session_Tokens::get_instance( $user_id );
			$all     = count( $manager->get_all() );
			if ( $user_id === $me && $token ) {
				$manager->destroy_others( $token );
				$all = max( 0, $all - 1 );
			} else {
				$manager->destroy_all();
			}
			if ( $all ) {
				$counts['sessions'] += $all;
				++$counts['users'];
			}
		}

		if ( class_exists( '\WP_Application_Passwords' ) ) {
			foreach ( self::users_with_meta( \WP_Application_Passwords::USERMETA_KEY_APPLICATION_PASSWORDS ) as $user_id ) {
				$deleted = \WP_Application_Passwords::delete_all_application_passwords( $user_id );
				if ( is_int( $deleted ) ) {
					$counts['app_passwords'] += $deleted;
				}
			}
		}
		return $counts;
	}

	/**
	 * IDs of users that have a given user meta key.
	 *
	 * @param string $key Meta key.
	 * @return int[]
	 */
	private static function users_with_meta( $key ) {
		global $wpdb;
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-off cleanup, must see every user, no cache.
			$wpdb->prepare( "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s", $key )
		);
		return array_map( 'intval', $ids );
	}

	/**
	 * Tells the site e-mail address who pressed the button, so a second admin knows what happened.
	 *
	 * @param string $result The result message.
	 * @return void
	 */
	private static function notify( $result ) {
		$user = wp_get_current_user();
		$site = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$who  = $user->exists() ? $user->user_login : __( 'no logged-in user (WP-CLI, a scheduled task or code)', 'helloadmin-guard' );
		/* translators: %s: user name. */
		$lines   = array( sprintf( __( '%s pressed "Log out everyone" in HelloAdmin Guard.', 'helloadmin-guard' ), $who ), '', $result );
		$lines[] = '';
		$lines[] = __( 'If this was not you or your team, someone else has administrator access: log in, change your password and check the list of administrators.', 'helloadmin-guard' );
		$to      = is_multisite() ? (string) get_site_option( 'admin_email' ) : (string) get_option( 'admin_email' );
		wp_mail( $to, '[' . $site . '] ' . __( 'Everyone was logged out', 'helloadmin-guard' ), implode( "\n", $lines ) );
	}

	/**
	 * Renders the tab.
	 *
	 * @return void
	 */
	public static function render() {
		$key    = self::RESULT . get_current_user_id();
		$result = get_transient( $key );
		if ( is_array( $result ) && 2 === count( $result ) ) {
			delete_transient( $key );
			wp_admin_notice(
				esc_html( (string) $result[1] ),
				array(
					'type'        => (string) $result[0],
					'dismissible' => true,
				)
			);
		}

		echo '<div class="hag-card hag-emergency">';
		echo '<h2>' . esc_html__( 'Log out everyone', 'helloadmin-guard' ) . '</h2>';
		echo '<p>' . esc_html__( 'This button ends every session of every user except yours in this browser, and deletes all application passwords. Everyone else has to log in again, and apps and services connected with an application password stop working until you create new passwords for them. A new password alone is not enough: it does not end sessions that are already open, and application passwords keep working after it. If someone stole a password or a login cookie, they stay in.', 'helloadmin-guard' ) . '</p>';
		if ( self::allowed() ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
			wp_nonce_field( self::ACTION );
			echo '<p><label><input type="checkbox" name="hag_confirm" value="1" required> ' . esc_html__( 'I understand that everyone else is logged out and all application passwords are deleted.', 'helloadmin-guard' ) . '</label></p>';
			echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Log out everyone and delete application passwords', 'helloadmin-guard' ) . '</button></p>';
			echo '</form>';
		} else {
			echo '<p><strong>' . esc_html__( 'On a multisite network this button logs out the users of every site, so only a network administrator (super admin) can use it.', 'helloadmin-guard' ) . '</strong></p>';
		}
		echo '</div>';

		echo '<div class="hag-card">';
		echo '<h2>' . esc_html__( 'If you think the site was hacked', 'helloadmin-guard' ) . '</h2>';
		echo '<ol>';
		$steps = array(
			__( 'Before you clean anything, save a copy of the files, the database and the server logs. You will need them to find out how they got in.', 'helloadmin-guard' ),
			__( 'From a computer you trust, change the passwords of the hosting panel, SSH or SFTP, the database, every administrator and the API keys of services like a CDN.', 'helloadmin-guard' ),
			__( 'Press the button above, so the old sessions and application passwords stop working.', 'helloadmin-guard' ),
			__( 'Check the administrators and the must-use plugins: the General tab has e-mail alerts and warnings for both.', 'helloadmin-guard' ),
			__( 'Find how they got in before you restore a backup, or the same hole lets them in again.', 'helloadmin-guard' ),
		);
		foreach ( $steps as $step ) {
			echo '<li>' . esc_html( $step ) . '</li>';
		}
		echo '</ol>';
		echo '</div>';
	}
}
