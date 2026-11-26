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
	 * Option with the registration state seen last time: "closed" or "open:<role>".
	 */
	const REGISTRATION_OPTION = 'helloadmin_guard_registration';

	/**
	 * Option with the e-mail and a password fingerprint of every administrator, signed like the admin list.
	 */
	const CREDS_OPTION = 'helloadmin_guard_admin_creds';

	/**
	 * Option with the site e-mail address seen last time.
	 */
	const SITE_EMAIL_OPTION = 'helloadmin_guard_site_email';

	/**
	 * True once the account check is queued for the end of this request.
	 *
	 * @var bool
	 */
	private $creds_queued = false;

	/**
	 * Rights that a role given to anyone who registers must not have. Besides ADMIN_CAPS: unfiltered_html lets
	 * a script into posts that runs when an administrator opens them, the rest edit other people's content,
	 * users or the theme.
	 */
	const RISKY_CAPS = array( 'unfiltered_html', 'edit_others_posts', 'edit_others_pages', 'edit_users', 'create_users', 'delete_users', 'list_users', 'edit_theme_options', 'switch_themes' );

	/**
	 * True once the registration check is queued for the end of this request.
	 *
	 * @var bool
	 */
	private $registration_queued = false;

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
		return __( 'When an account becomes an administrator, the site e-mail address gets a message with the user name, the e-mail of the account and who made the change. The plugin also compares the administrators in the database with the list it saw last time: after every login, on admin pages and at least once an hour while the site gets visits, without WP-Cron. So an account added straight into the database, or hidden from the Users screen, is reported too, at the latest when someone logs in with it. Hacked sites often get an extra admin with a random name like "a3f9c2".', 'helloadmin-guard' )
			. ' ' . __( 'Taking over an existing administrator is reported too: an e-mail goes out when the e-mail address or the password of any administrator changes, and when the site e-mail address changes (then to the old address as well, because the alerts would go to the new one).', 'helloadmin-guard' )
			. ' ' . __( 'You also get an e-mail when anyone becomes able to register on the site, or when the role for new users changes while registration is open. A known attack through a plugin hole turns on "Anyone can register" with Administrator as the new user role, then simply registers.', 'helloadmin-guard' );
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
		$state    = self::registration_state();
		if ( null !== $state && $state['open'] && self::risky_role( $state['role'] ) ) {
			$warnings[] = sprintf(
				/* translators: %s: role name. */
				__( 'Anyone can register on this site and gets the role "%s", which can change other people\'s content, users or the site itself. If you did not set this up on purpose, set the new user role to Subscriber or turn off "Anyone can register" in Settings → General.', 'helloadmin-guard' ),
				self::role_name( $state['role'] )
			);
		}
		$all      = self::admin_ids();
		$visible  = $all ? array_map(
			'intval',
			get_users(
				array(
					'include' => $all,
					'fields'  => 'ID',
				)
			)
		) : array();
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

			if ( ! in_array( 'administrator', (array) $user->roles, true ) && ! is_super_admin( $id ) ) {
				/* translators: %s: user name. */
				$warnings[] = sprintf( __( 'The account "%s" has administrator rights without the Administrator role: through another role or rights added to the account itself. Malware does this so the account does not show up as an administrator. If you did not set this up, check this account.', 'helloadmin-guard' ), $login ) . $created;
			}
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
		add_action( 'added_user_meta', array( $this, 'on_caps_meta' ), 10, 3 );
		add_action( 'updated_user_meta', array( $this, 'on_caps_meta' ), 10, 3 );
		add_action( 'granted_super_admin', array( $this, 'queue_check' ) );
		add_action( 'profile_update', array( $this, 'queue_creds_check' ) );
		add_action( 'after_password_reset', array( $this, 'queue_creds_check' ) );
		add_action( 'wp_set_password', array( $this, 'on_set_password' ), 10, 2 );
		add_action( 'update_option_admin_email', array( $this, 'queue_creds_check' ) );
		foreach ( array( 'users_can_register', 'default_role' ) as $name ) {
			add_action( 'add_option_' . $name, array( $this, 'queue_registration_check' ) );
			add_action( 'update_option_' . $name, array( $this, 'queue_registration_check' ) );
		}
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
		update_option( self::REGISTRATION_OPTION, self::registration_key(), false );
		self::save_list( self::CREDS_OPTION, self::creds() );
		update_option( self::SITE_EMAIL_OPTION, strtolower( (string) get_option( 'admin_email' ) ), false );
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
		unset( $user_id, $role, $old_roles );
		// Any role: a custom role may carry administrator rights. The check compares the full list, so it is cheap.
		$this->queue_check();
	}

	/**
	 * A role was added next to the existing ones.
	 *
	 * @param int    $user_id User ID.
	 * @param string $role    Added role.
	 * @return void
	 */
	public function on_add_role( $user_id, $role ) {
		unset( $user_id, $role );
		$this->queue_check();
	}

	/**
	 * Rights changed on an account directly (WP_User::add_cap() writes the capabilities meta).
	 *
	 * @param int    $meta_id  Meta ID.
	 * @param int    $user_id  User ID.
	 * @param string $meta_key Meta key.
	 * @return void
	 */
	public function on_caps_meta( $meta_id, $user_id, $meta_key ) {
		global $wpdb;
		unset( $meta_id, $user_id );
		if ( $wpdb->get_blog_prefix() . 'capabilities' === $meta_key ) {
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
		$this->check_registration();
		$this->check_creds();
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
	 * Checks the administrator accounts at the end of the request, when the change is saved.
	 *
	 * @return void
	 */
	public function queue_creds_check() {
		if ( ! $this->creds_queued ) {
			$this->creds_queued = true;
			$this->reason       = 'role';
			add_action( 'shutdown', array( $this, 'check_creds' ) );
		}
	}

	/**
	 * A password was set. During a login this is WordPress upgrading the stored hash of the same password
	 * (wp_password_needs_rehash(): bcrypt since 6.8, a new cost on a newer PHP): only the right password gets there,
	 * so the record is updated quietly. Any other time it is a real change and goes to the check.
	 *
	 * @param string $password New password (not used).
	 * @param int    $user_id  User ID.
	 * @return void
	 */
	public function on_set_password( $password, $user_id = 0 ) {
		unset( $password );
		if ( ! doing_filter( 'authenticate' ) ) {
			$this->queue_creds_check();
			return;
		}
		list( $known, $bad ) = self::load_list( self::CREDS_OPTION );
		$user                = get_userdata( (int) $user_id );
		if ( $bad || ! $user || ! isset( $known[ (int) $user_id ] ) || ! is_array( $known[ (int) $user_id ] ) ) {
			return;
		}
		$known[ (int) $user_id ]['p'] = self::fingerprint( $user->user_pass );
		self::save_list( self::CREDS_OPTION, $known );
	}

	/**
	 * Fingerprint of a stored password hash.
	 *
	 * @param string $hash Value of user_pass.
	 * @return string
	 */
	private static function fingerprint( $hash ) {
		return substr( hash_hmac( 'sha256', $hash, wp_salt( 'auth' ) ), 0, 20 );
	}

	/**
	 * E-mail and a password fingerprint of every administrator. The fingerprint is an HMAC of the stored password
	 * hash, so the option holds nothing that helps to guess the password.
	 *
	 * @return array<int, array{e: string, p: string}> Keyed by user ID.
	 */
	private static function creds() {
		$found = array();
		foreach ( self::admin_ids() as $id ) {
			$user = get_userdata( $id );
			if ( $user ) {
				$found[ $id ] = array(
					'e' => strtolower( $user->user_email ),
					'p' => self::fingerprint( $user->user_pass ),
				);
			}
		}
		return $found;
	}

	/**
	 * Sends an e-mail when the e-mail address or the password of an administrator changed, or the site e-mail
	 * address. New administrators are only recorded: the new administrator alert reports them.
	 *
	 * @return void
	 */
	public function check_creds() {
		$this->check_site_email();
		$now                 = self::creds();
		list( $known, $bad ) = self::load_list( self::CREDS_OPTION );
		$missing             = null === get_option( self::CREDS_OPTION, null );
		if ( $now !== $known || $bad ) {
			self::save_list( self::CREDS_OPTION, $now );
		}
		if ( $missing ) {
			return; // First run after an update: only remember.
		}
		if ( $bad ) {
			$this->send_alert_mail(
				__( 'The record of administrator accounts was changed outside the plugin', 'helloadmin-guard' ),
				array(
					/* translators: %s: site name and address. */
					sprintf( __( 'On the site %s, the record of administrator accounts that the HelloAdmin Guard plugin keeps was changed or removed outside the plugin, for example straight in the database. The plugin keeps the e-mail addresses of the administrators and a fingerprint of their passwords to notice when an existing administrator account is taken over, and cannot tell now whether an e-mail address or a password changed before that.', 'helloadmin-guard' ), \HelloAdmin\Guard\Mail::site() ),
					__( 'If you changed the WordPress salts in **wp-config.php** or moved the database to another site, this is expected.', 'helloadmin-guard' ),
					'',
					\HelloAdmin\Guard\Mail::link( __( 'Users → Administrators', 'helloadmin-guard' ), admin_url( 'users.php?role=administrator' ) ),
				)
			);
			return;
		}
		foreach ( $now as $id => $current ) {
			if ( isset( $known[ $id ] ) && is_array( $known[ $id ] ) && $known[ $id ] !== $current ) {
				$this->send_creds_alert( (int) $id, $known[ $id ], $current );
			}
		}
	}

	/**
	 * E-mail about a changed administrator account.
	 *
	 * @param int                   $user_id User ID.
	 * @param array<string, string> $before  Stored e-mail and fingerprint.
	 * @param array<string, string> $after   Current e-mail and fingerprint.
	 * @return void
	 */
	private function send_creds_alert( $user_id, array $before, array $after ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$lines = array();
		$role  = self::role_name( (string) reset( $user->roles ) );
		$email = isset( $before['e'] ) && $before['e'] !== $after['e'];
		$pass  = isset( $before['p'] ) && $before['p'] !== $after['p'];
		if ( $email ) {
			/* translators: 1: site name and address, 2: user name, 3: old e-mail address, 4: new e-mail address, 5: role. */
			$lines[] = sprintf( __( 'On the site %1$s, the e-mail address of user %2$s, %3$s, was changed to %4$s. The user has the role %5$s.', 'helloadmin-guard' ), \HelloAdmin\Guard\Mail::site(), \HelloAdmin\Guard\Mail::b( $user->user_login ), \HelloAdmin\Guard\Mail::b( $before['e'] ), \HelloAdmin\Guard\Mail::b( $after['e'] ), \HelloAdmin\Guard\Mail::b( $role ) );
		}
		if ( $pass ) {
			/* translators: 1: site name and address, 2: user name, 3: role. */
			$lines[] = sprintf( __( 'On the site %1$s, **the password of user %2$s was changed.** The user has the role %3$s.', 'helloadmin-guard' ), \HelloAdmin\Guard\Mail::site(), str_replace( '*', '', $user->user_login ), \HelloAdmin\Guard\Mail::b( $role ) );
		}
		/* translators: %s: user name or a short explanation. */
		$lines[] = sprintf( __( '**Changed by:** %s', 'helloadmin-guard' ), $this->changed_by() );
		$ip      = $this->request_ip();
		if ( $ip ) {
			/* translators: %s: IP address. */
			$lines[] = sprintf( __( '**From IP address:** %s', 'helloadmin-guard' ), $ip );
		}
		$lines[] = '';
		$lines[] = __( 'If you or this administrator made the change, nothing to do.', 'helloadmin-guard' );
		$lines[] = __( '**If not:** someone may have taken over this account. Set a new password for it ("**Users → Profile**", or "**Lost your password?**" with an address you control), use "**If hacked**" in the settings of the HelloAdmin Guard plugin to log everyone out, and check the other administrators.', 'helloadmin-guard' );
		$lines[] = '';
		$lines[] = \HelloAdmin\Guard\Mail::link( __( 'User profile', 'helloadmin-guard' ), admin_url( 'user-edit.php?user_id=' . $user_id ) );

		$subject = $email
			/* translators: %s: user name. */
			? sprintf( __( 'Administrator e-mail changed: %s', 'helloadmin-guard' ), $user->user_login )
			/* translators: %s: user name. */
			: sprintf( __( 'Administrator password changed: %s', 'helloadmin-guard' ), $user->user_login );
		// The old address gets it too: after a takeover the new one belongs to the intruder.
		$this->send_alert_mail( $subject, $lines, $email ? array( $before['e'] ) : array() );
	}

	/**
	 * E-mail when the site e-mail address (Settings → General) changes, to the old and the new address: all alerts
	 * go to this address, so an intruder would change it first.
	 *
	 * @return void
	 */
	private function check_site_email() {
		$now  = strtolower( (string) get_option( 'admin_email' ) );
		$seen = get_option( self::SITE_EMAIL_OPTION, null );
		$seen = is_string( $seen ) ? strtolower( $seen ) : $seen; // Stored as it was by early 0.6 builds.
		if ( $now === $seen ) {
			return;
		}
		update_option( self::SITE_EMAIL_OPTION, $now, false );
		if ( ! is_string( $seen ) || '' === $seen ) {
			return;
		}
		$lines = array(
			/* translators: 1: old e-mail address, 2: new e-mail address. */
			sprintf( __( 'On the site %1$s, the site e-mail address in "**Settings → General**" changed: it was %2$s, now it is %3$s. Security alerts and password reset links for the site go to this address.', 'helloadmin-guard' ), \HelloAdmin\Guard\Mail::site(), \HelloAdmin\Guard\Mail::b( $seen ), \HelloAdmin\Guard\Mail::b( $now ) ),
			/* translators: %s: user name or a short explanation. */
			sprintf( __( '**Changed by:** %s', 'helloadmin-guard' ), $this->changed_by() ),
		);
		$ip    = $this->request_ip();
		if ( $ip ) {
			/* translators: %s: IP address. */
			$lines[] = sprintf( __( '**From IP address:** %s', 'helloadmin-guard' ), $ip );
		}
		$lines[] = '';
		$lines[] = __( 'If you did not make this change, set your address back in "**Settings → General**" and check the administrators.', 'helloadmin-guard' );
		$lines[] = '';
		$lines[] = \HelloAdmin\Guard\Mail::link( __( 'Settings → General', 'helloadmin-guard' ), admin_url( 'options-general.php' ) );
		$this->send_alert_mail( __( 'The site e-mail address changed', 'helloadmin-guard' ), $lines, array( $seen ) );
	}

	/**
	 * Checks the registration settings at the end of the request, when the options are saved.
	 *
	 * @return void
	 */
	public function queue_registration_check() {
		if ( ! $this->registration_queued ) {
			$this->registration_queued = true;
			$this->reason              = 'role';
			add_action( 'shutdown', array( $this, 'check_registration' ) );
		}
	}

	/**
	 * Sends an e-mail when registration became open, or the role for new users changed while it is open.
	 * Closing registration is not reported. The regular check calls this too, so a change written straight
	 * into the database is found as well.
	 *
	 * @return void
	 */
	public function check_registration() {
		$key  = self::registration_key();
		$seen = get_option( self::REGISTRATION_OPTION, null );
		if ( $key === $seen ) {
			return;
		}
		update_option( self::REGISTRATION_OPTION, $key, false );
		$state = self::registration_state();
		// The first run after an update only remembers the state, unless it is already dangerous.
		if ( null === $state || ! $state['open'] || ( null === $seen && ! self::risky_role( $state['role'] ) ) ) {
			return;
		}
		$this->send_registration_alert( $state['role'] );
	}

	/**
	 * Registration settings of this site, or null on multisite (there the network settings decide).
	 *
	 * @return array{open: bool, role: string}|null
	 */
	private static function registration_state() {
		if ( is_multisite() ) {
			return null;
		}
		return array(
			'open' => (bool) get_option( 'users_can_register' ),
			'role' => (string) get_option( 'default_role', 'subscriber' ),
		);
	}

	/**
	 * Registration state as a short string to compare: "closed" or "open:<role>". Empty on multisite.
	 *
	 * @return string
	 */
	private static function registration_key() {
		$state = self::registration_state();
		if ( null === $state ) {
			return '';
		}
		return $state['open'] ? 'open:' . $state['role'] : 'closed';
	}

	/**
	 * Whether a role has any right that a stranger must not get (ADMIN_CAPS or RISKY_CAPS). Read from the database
	 * like admin_roles(). A role that does not exist counts as risky: WordPress would still save the name.
	 *
	 * @param string $role Role name.
	 * @return bool
	 */
	private static function risky_role( $role ) {
		$roles = self::role_caps();
		if ( ! isset( $roles[ $role ] ) ) {
			return true;
		}
		return (bool) array_intersect( array_merge( self::ADMIN_CAPS, self::RISKY_CAPS ), $roles[ $role ] );
	}

	/**
	 * Display name of a role, or the role id when it has none.
	 *
	 * @param string $role Role name.
	 * @return string
	 */
	private static function role_name( $role ) {
		$names = wp_roles()->get_names();
		return isset( $names[ $role ] ) ? translate_user_role( $names[ $role ] ) : $role;
	}

	/**
	 * E-mail about open registration.
	 *
	 * @param string $role Role for new users.
	 * @return void
	 */
	private function send_registration_alert( $role ) {
		$risky = self::risky_role( $role );
		$lines = array();
		/* translators: 1: site name and address, 2: role name. */
		$lines[] = sprintf( __( 'Anyone can now register on the site %1$s. New users get the role: %2$s.', 'helloadmin-guard' ), \HelloAdmin\Guard\Mail::site(), \HelloAdmin\Guard\Mail::b( self::role_name( $role ) ) );
		if ( $risky ) {
			$lines[] = __( 'This role can change other people\'s content, users or the site itself. With it, a stranger who registers can take over the site.', 'helloadmin-guard' );
		}
		/* translators: %s: user name or a short explanation. */
		$lines[] = sprintf( __( '**Changed by:** %s', 'helloadmin-guard' ), $this->changed_by() );
		$ip      = $this->request_ip();
		if ( $ip ) {
			/* translators: %s: IP address. */
			$lines[] = sprintf( __( '**From IP address:** %s', 'helloadmin-guard' ), $ip );
		}
		$lines[] = '';
		$lines[] = __( 'If you opened registration on purpose (a shop, a membership site), keep the new user role at **Subscriber** or the role your shop or membership plugin uses for customers.', 'helloadmin-guard' );
		$lines[] = __( '**If not:** turn off "**Anyone can register**" and set the new user role to **Subscriber** in "**Settings → General**", then look at the users registered since this e-mail and update or remove the plugin that let the settings change.', 'helloadmin-guard' );
		$lines[] = '';
		$lines[] = \HelloAdmin\Guard\Mail::link( __( 'Settings → General', 'helloadmin-guard' ), admin_url( 'options-general.php' ) );

		$this->send_alert_mail(
			$risky ? __( 'Anyone can register, with dangerous rights', 'helloadmin-guard' ) : __( 'Anyone can now register', 'helloadmin-guard' ),
			$lines
		);
	}

	/**
	 * Who made the change, for the e-mails.
	 *
	 * @return string
	 */
	private function changed_by() {
		$actor = wp_get_current_user();
		if ( 'watch' === $this->reason ) {
			return __( 'the regular check found it – the change was made outside WordPress (for example straight in the database) while this alert was on', 'helloadmin-guard' );
		}
		if ( $actor->exists() ) {
			/* translators: %s: user name. */
			return sprintf( __( 'user %s', 'helloadmin-guard' ), \HelloAdmin\Guard\Mail::b( $actor->user_login ) );
		}
		return __( 'nobody logged in to WordPress (WP-CLI, a scheduled task or code)', 'helloadmin-guard' );
	}

	/**
	 * Visitor IP when the change came with a web request.
	 *
	 * @return string
	 */
	private function request_ip() {
		return 'role' === $this->reason && ! ( defined( 'WP_CLI' ) && WP_CLI ) ? self::visitor_ip() : '';
	}

	/**
	 * E-mail when the stored list of administrators was changed or removed outside the plugin.
	 *
	 * @param int[] $ids Administrators now.
	 * @return void
	 */
	private function send_list_alert( array $ids ) {
		$lines = array();
		/* translators: %s: site name and address. */
		$lines[] = sprintf( __( 'On the site %s, the list of administrators that the HelloAdmin Guard plugin keeps was changed or removed outside the plugin, for example straight in the database. Someone may have added an administrator and hidden it from this alert.', 'helloadmin-guard' ), \HelloAdmin\Guard\Mail::site() );
		$lines[] = __( 'If you changed the WordPress salts in **wp-config.php** or moved the database to another site, this is expected.', 'helloadmin-guard' );
		$lines[] = '';
		$lines[] = __( 'Administrators now:', 'helloadmin-guard' );
		foreach ( $ids as $id ) {
			$user = get_userdata( $id );
			if ( $user ) {
				$lines[] = array(
					'item' => $user->user_login,
					'text' => '(' . $user->user_email . ', ' . \HelloAdmin\Guard\Mail::time( (int) strtotime( $user->user_registered . ' UTC' ) ) . ')',
				);
			}
		}
		$lines[] = '';
		$lines[] = __( '**If one of them is not yours:** delete the account, change the passwords of all administrators and of the hosting, and look at **wp-content/mu-plugins** for files you do not know.', 'helloadmin-guard' );
		$lines[] = '';
		$lines[] = \HelloAdmin\Guard\Mail::link( __( 'Users → Administrators', 'helloadmin-guard' ), admin_url( 'users.php?role=administrator' ) );

		$this->send_alert_mail( __( 'The list of administrators was changed outside the plugin', 'helloadmin-guard' ), $lines );
	}

	/**
	 * Rights that make an account an administrator in practice: with any of them it can take over the site.
	 */
	const ADMIN_CAPS = array( 'manage_options', 'promote_users', 'edit_plugins', 'activate_plugins', 'install_plugins', 'unfiltered_upload' );

	/**
	 * Roles of this site that hold one of ADMIN_CAPS, read straight from the database (the roles option can be
	 * filtered too). Always includes "administrator".
	 *
	 * @return string[]
	 */
	private static function admin_roles() {
		$found = array( 'administrator' );
		foreach ( self::role_caps() as $name => $caps ) {
			if ( array_intersect( self::ADMIN_CAPS, $caps ) ) {
				$found[] = $name;
			}
		}
		return array_values( array_unique( $found ) );
	}

	/**
	 * Roles of this site with their granted rights, read straight from the database (the roles option can be
	 * filtered too).
	 *
	 * @return array<string, string[]>
	 */
	private static function role_caps() {
		global $wpdb;
		$raw   = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- on purpose, past option filters; changes with every role edit.
			$wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $wpdb->get_blog_prefix() . 'user_roles' )
		);
		$roles = is_string( $raw ) ? maybe_unserialize( $raw ) : array();
		$found = array();
		foreach ( is_array( $roles ) ? $roles : array() as $name => $role ) {
			if ( is_string( $name ) ) {
				$found[ $name ] = isset( $role['capabilities'] ) && is_array( $role['capabilities'] ) ? array_map( 'strval', array_keys( array_filter( $role['capabilities'] ) ) ) : array();
			}
		}
		return $found;
	}

	/**
	 * IDs of all administrators of this site, read past any user query filters: the Administrator role, any other
	 * role with administrator rights (ADMIN_CAPS), and accounts with such rights added to the account itself.
	 *
	 * @return int[] Sorted IDs.
	 */
	public static function admin_ids() {
		global $wpdb;

		// The API stores true (b:1), but code that writes the meta itself may store 1 or "1": WordPress treats those
		// as granted too, so all three forms are looked for.
		$likes = array();
		foreach ( array_merge( self::admin_roles(), self::ADMIN_CAPS ) as $name ) {
			foreach ( array( 'b:1;', 'i:1;', 's:1:"1";' ) as $value ) {
				$likes[] = '%' . $wpdb->esc_like( '"' . $name . '";' . $value ) . '%';
			}
		}
		$where = implode( ' OR ', array_fill( 0, count( $likes ), 'meta_value LIKE %s' ) );
		$ids   = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- on purpose: get_users() can be filtered by malware that hides accounts; the result changes with every role change, so no cache.
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND ( {$where} )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is only "meta_value LIKE %s" placeholders.
				array_merge( array( $wpdb->get_blog_prefix() . 'capabilities' ), $likes )
			)
		);
		$ids   = array_map( 'intval', $ids );
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
			$by = __( 'the regular check found it – the change was made outside WordPress (for example straight in the database) while this alert was on', 'helloadmin-guard' );
		} elseif ( $actor->exists() ) {
			/* translators: %s: user name. */
			$by = sprintf( __( 'user %s', 'helloadmin-guard' ), \HelloAdmin\Guard\Mail::b( $actor->user_login ) );
		} else {
			$by = __( 'nobody logged in to WordPress (WP-CLI, a scheduled task or code)', 'helloadmin-guard' );
		}
		$ip = $this->request_ip();

		/* translators: %s: user name. */
		$subject = sprintf( __( 'New administrator: %s', 'helloadmin-guard' ), $user->user_login );

		$lines = array();
		/* translators: %s: site name and address. */
		$lines[] = sprintf( __( 'An account on the site %s now has the "**Administrator**" role.', 'helloadmin-guard' ), \HelloAdmin\Guard\Mail::site() );
		$lines[] = '';
		/* translators: %s: user name. */
		$lines[] = sprintf( __( 'User name: %s', 'helloadmin-guard' ), \HelloAdmin\Guard\Mail::b( $user->user_login ) );
		/* translators: %s: e-mail address. */
		$lines[] = sprintf( __( 'E-mail: %s', 'helloadmin-guard' ), \HelloAdmin\Guard\Mail::b( $user->user_email ) );
		/* translators: %s: date and time. */
		$lines[] = sprintf( __( 'Account created: %s', 'helloadmin-guard' ), \HelloAdmin\Guard\Mail::b( \HelloAdmin\Guard\Mail::time( (int) strtotime( $user->user_registered . ' UTC' ) ) ) );
		/* translators: %s: user name or a short explanation. */
		$lines[] = sprintf( __( '**Changed by:** %s', 'helloadmin-guard' ), $by );
		if ( $ip ) {
			/* translators: %s: IP address. */
			$lines[] = sprintf( __( '**From IP address:** %s', 'helloadmin-guard' ), $ip );
		}
		if ( \HelloAdmin\Guard\User_Names::name_leaks( $user ) || \HelloAdmin\Guard\User_Names::slug_leaks( $user ) ) {
			$lines[] = __( 'Its public name or author page address is the login, so the login shows under its posts and comments. "**General → Hide user names**" in the settings of the HelloAdmin Guard plugin can change that.', 'helloadmin-guard' );
		}
		$lines[] = '';
		$lines[] = __( 'If you or your team added this account, nothing to do.', 'helloadmin-guard' );
		$lines[] = __( '**If not:** delete the account, change the passwords of all administrators and of the hosting, and look at **wp-content/mu-plugins** for files you do not know.', 'helloadmin-guard' );
		$lines[] = '';
		$lines[] = \HelloAdmin\Guard\Mail::link( __( 'Users → Administrators', 'helloadmin-guard' ), admin_url( 'users.php?role=administrator' ) );

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
