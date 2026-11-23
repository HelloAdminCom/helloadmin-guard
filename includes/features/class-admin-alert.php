<?php
/**
 * Sends an e-mail when an account becomes an administrator, and points out admins that look planted.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Hacked sites often get an extra administrator: the FBI notice on Gootloader (CC-111626-MW) describes
 * DollyWay creating hidden admins with random hex names. Two ways to notice it:
 *
 * - the role hooks (set_user_role, add_user_role) report a new admin right away;
 * - a regular check (Feature::watch(): after every login, on admin pages, at least once an hour while the
 *   site gets visits; no WP-Cron) compares the administrators in the database with the last list it saw, so
 *   an account written straight into the database is reported too, at the latest when it logs in.
 *
 * The list is read with a direct query on the capabilities meta, not with get_users(): malware hides
 * its accounts by filtering the user query, and the direct query does not go through those filters.
 */
class Admin_Alert extends Feature {

	/**
	 * Option with the IDs of the administrators seen last time. Autoloaded: it is small and read on every request.
	 */
	const SNAPSHOT_OPTION = 'helloadmin_guard_admins';

	/**
	 * Option with the time of the last regular check.
	 */
	const CHECKED_OPTION = 'helloadmin_guard_admins_checked';

	/**
	 * Cron hook of the hourly check in 0.5 before the regular check replaced it; only cleaned up now.
	 */
	const CRON_HOOK = 'helloadmin_guard_check_admins';

	/**
	 * True once the check is queued for the end of this request.
	 *
	 * @var bool
	 */
	private $queued = false;

	/**
	 * Why the check runs: 'role' (a role hook fired in this request) or 'watch' (the regular check).
	 *
	 * @var string
	 */
	private $reason = 'watch';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'admin_alert';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title() {
		return __( 'E-mail me about new administrators', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function what() {
		return __( 'When an account becomes an administrator, the site e-mail address gets a message with the user name, the e-mail of the account and who made the change. The plugin also compares the administrators in the database with the list it saw last time: after every login, on admin pages and at least once an hour while the site gets visits, without WP-Cron. So an account added straight into the database, or hidden from the Users screen, is reported too, at the latest when someone logs in with it. Hacked sites often get an extra admin with a random name like "a3f9c2".', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function may_break() {
		return __( 'Nothing stops working. You also get the e-mail when you add an administrator yourself. If e-mail from this site does not arrive, the alert does not arrive either, so check that the site can send mail.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function undo() {
		return __( 'Turn this off and save. No users or roles are changed.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function group() {
		return 'login';
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
	 * Place on the tab: right after the login settings.
	 *
	 * @return int
	 */
	public function priority() {
		return 45;
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
	 * Administrators that look planted: hidden from the Users screen, random hex names, created in the last week.
	 *
	 * @return string[]
	 */
	public function warnings() {
		$warnings = array();
		$all      = self::admin_ids();
		$visible  = array_map(
			'intval',
			get_users(
				array(
					'role'   => 'administrator',
					'fields' => 'ID',
				)
			)
		);
		$week_ago = time() - WEEK_IN_SECONDS;

		foreach ( $all as $id ) {
			$user = get_userdata( $id );
			if ( ! $user ) {
				continue;
			}
			$login      = $user->user_login;
			$registered = strtotime( $user->user_registered . ' UTC' );
			$recent     = $registered && $registered > $week_ago && get_current_user_id() !== $id;
			$created    = $recent
				/* translators: %s: date. */
				? ' ' . sprintf( __( 'Created on %s.', 'helloadmin-guard' ), wp_date( (string) get_option( 'date_format' ), $registered ) )
				: '';

			if ( ! in_array( $id, $visible, true ) ) {
				/* translators: %s: user name. */
				$warnings[] = sprintf( __( 'The administrator "%s" is in the database but hidden from the Users screen. Something on this site filters the user list, which is how malware hides its accounts. Check this account and the must-use plugins.', 'helloadmin-guard' ), $login ) . $created;
			} elseif ( self::looks_random( $login ) ) {
				/* translators: %s: user name. */
				$warnings[] = sprintf( __( 'The administrator "%s" has a name that looks random. If you do not know this account, check it.', 'helloadmin-guard' ), $login ) . $created;
			} elseif ( $recent ) {
				/* translators: %s: user name. */
				$warnings[] = sprintf( __( 'The administrator "%s" was added in the last 7 days.', 'helloadmin-guard' ), $login ) . $created;
			}
		}
		return $warnings;
	}

	/**
	 * Adds the role hooks and the regular check.
	 *
	 * @param Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( Settings $settings ) {
		add_action( 'set_user_role', array( $this, 'on_set_role' ), 10, 3 );
		add_action( 'add_user_role', array( $this, 'on_add_role' ), 10, 2 );
		add_action( 'granted_super_admin', array( $this, 'queue_check' ) );
		$this->watch( array( $this, 'check' ), self::CHECKED_OPTION );

		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			self::unschedule();
		}
	}

	/**
	 * Remembers the current admins when the setting is switched on, so only the ones added later are reported.
	 * Only here: a list that goes missing later is reported, not quietly rebuilt.
	 *
	 * @return void
	 */
	public function switched_on() {
		self::save_list( self::SNAPSHOT_OPTION, self::admin_ids() );
	}

	/**
	 * A role was set (also when a new user is created).
	 *
	 * @param int      $user_id   User ID.
	 * @param string   $role      New role.
	 * @param string[] $old_roles Roles before the change.
	 * @return void
	 */
	public function on_set_role( $user_id, $role, $old_roles ) {
		unset( $user_id );
		if ( 'administrator' === $role && ! in_array( 'administrator', (array) $old_roles, true ) ) {
			$this->queue_check();
		}
	}

	/**
	 * A role was added next to the existing ones.
	 *
	 * @param int    $user_id User ID.
	 * @param string $role    Added role.
	 * @return void
	 */
	public function on_add_role( $user_id, $role ) {
		unset( $user_id );
		if ( 'administrator' === $role ) {
			$this->queue_check();
		}
	}

	/**
	 * Runs the check at the end of the request, when the role is saved.
	 *
	 * @return void
	 */
	public function queue_check() {
		if ( ! $this->queued ) {
			$this->queued = true;
			$this->reason = 'role';
			add_action( 'shutdown', array( $this, 'check' ) );
		}
	}

	/**
	 * Compares the administrators with the last list and sends one e-mail per new admin.
	 *
	 * @return void
	 */
	public function check() {
		$now                 = self::admin_ids();
		list( $known, $bad ) = self::load_list( self::SNAPSHOT_OPTION );
		$known               = array_map( 'intval', $known );

		if ( $bad || $now !== $known || ! self::list_is_signed( self::SNAPSHOT_OPTION ) ) {
			self::save_list( self::SNAPSHOT_OPTION, $now );
		}
		if ( $bad ) {
			// The stored list cannot be trusted: one message with every administrator instead of a diff.
			$this->send_list_alert( $now );
			return;
		}
		foreach ( array_diff( $now, $known ) as $id ) {
			$this->send_alert( (int) $id );
		}
	}

	/**
	 * E-mail when the stored list of administrators was changed or removed outside the plugin.
	 *
	 * @param int[] $ids Administrators now.
	 * @return void
	 */
	private function send_list_alert( array $ids ) {
		$lines   = array();
		$lines[] = __( 'The list of administrators that HelloAdmin Guard keeps was changed or removed outside the plugin, for example straight in the database. Someone may have added an administrator and hidden it from this alert.', 'helloadmin-guard' );
		$lines[] = __( 'If you changed the WordPress salts in wp-config.php or moved the database to another site, this is expected.', 'helloadmin-guard' );
		$lines[] = '';
		$lines[] = __( 'Administrators now:', 'helloadmin-guard' );
		foreach ( $ids as $id ) {
			$user = get_userdata( $id );
			if ( $user ) {
				$lines[] = '  ' . $user->user_login . ' (' . $user->user_email . ', ' . $user->user_registered . ' UTC)';
			}
		}
		$lines[] = '';
		$lines[] = __( 'If one of them is not yours: delete the account, change the passwords of all administrators and of the hosting, and look at wp-content/mu-plugins for files you do not know.', 'helloadmin-guard' );
		$lines[] = '';
		$lines[] = admin_url( 'users.php?role=administrator' );

		$this->send_alert_mail( __( 'The list of administrators was changed outside the plugin', 'helloadmin-guard' ), $lines );
	}

	/**
	 * IDs of all administrators of this site, read past any user query filters.
	 *
	 * @return int[] Sorted IDs.
	 */
	public static function admin_ids() {
		global $wpdb;

		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- on purpose: get_users() can be filtered by malware that hides accounts; the result changes with every role change, so no cache.
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s",
				$wpdb->get_blog_prefix() . 'capabilities',
				'%' . $wpdb->esc_like( '"administrator"' ) . '%'
			)
		);
		$ids = array_map( 'intval', $ids );
		if ( is_multisite() ) {
			foreach ( get_super_admins() as $login ) {
				$user = get_user_by( 'login', $login );
				if ( $user ) {
					$ids[] = (int) $user->ID;
				}
			}
		}
		$ids = array_values( array_unique( $ids ) );
		sort( $ids );
		return $ids;
	}

	/**
	 * Hex names of six characters or more with at least one digit, like "a3f9c2": typical for planted admins.
	 *
	 * @param string $login User name.
	 * @return bool
	 */
	public static function looks_random( $login ) {
		return (bool) preg_match( '/^(?=.*\d)(?=.*[a-f])[a-f0-9]{6,}$/i', $login );
	}

	/**
	 * Sends the alert about one new administrator.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function send_alert( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$actor = wp_get_current_user();
		if ( 'watch' === $this->reason ) {
			$by = __( 'found by the regular check: the role was not set through WordPress while this alert was on, for example it was written straight into the database', 'helloadmin-guard' );
		} elseif ( $actor->exists() ) {
			$by = $actor->user_login;
		} else {
			$by = __( 'no logged-in user (WP-CLI, a scheduled task or code)', 'helloadmin-guard' );
		}
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) && 'role' === $this->reason && ! ( defined( 'WP_CLI' ) && WP_CLI ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		/* translators: %s: user name. */
		$subject = sprintf( __( 'New administrator: %s', 'helloadmin-guard' ), $user->user_login );

		$lines   = array();
		$lines[] = __( 'An account on your site now has the Administrator role.', 'helloadmin-guard' );
		$lines[] = '';
		/* translators: %s: user name. */
		$lines[] = sprintf( __( 'User name: %s', 'helloadmin-guard' ), $user->user_login );
		/* translators: %s: e-mail address. */
		$lines[] = sprintf( __( 'E-mail: %s', 'helloadmin-guard' ), $user->user_email );
		/* translators: %s: date and time. */
		$lines[] = sprintf( __( 'Account created: %s', 'helloadmin-guard' ), $user->user_registered . ' UTC' );
		/* translators: %s: user name or a short explanation. */
		$lines[] = sprintf( __( 'Changed by: %s', 'helloadmin-guard' ), $by );
		if ( $ip ) {
			/* translators: %s: IP address. */
			$lines[] = sprintf( __( 'From IP address: %s', 'helloadmin-guard' ), $ip );
		}
		$lines[] = '';
		$lines[] = __( 'If you or your team added this account, nothing to do.', 'helloadmin-guard' );
		$lines[] = __( 'If not: delete the account, change the passwords of all administrators and of the hosting, and look at wp-content/mu-plugins for files you do not know.', 'helloadmin-guard' );
		$lines[] = '';
		$lines[] = admin_url( 'users.php?role=administrator' );

		$this->send_alert_mail( $subject, $lines );
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
