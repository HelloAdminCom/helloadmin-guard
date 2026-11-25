<?php
/**
 * Setting: limit failed login attempts per IP address.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * After too many failed logins from one address, every login from it is refused for a while, even with the right
 * password, so a bot cannot keep guessing. Works for the login page, XML-RPC and REST API logins.
 */
class Limit_Login extends Feature {

	/**
	 * Plugins that already limit logins, by plugin file.
	 */
	const OTHERS = array(
		'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php',
		'loginizer/loginizer.php',
		'wp-limit-login-attempts/wp-limit-login-attempts.php',
		'wordfence/wordfence.php',
		'better-wp-security/better-wp-security.php',
		'ithemes-security-pro/ithemes-security-pro.php',
		'all-in-one-wp-security-and-firewall/wp-security.php',
		'defender-security/wp-defender.php',
		'wp-simple-firewall/icwp-wpsf.php',
	);

	/**
	 * Allowed attempts before the lockout.
	 *
	 * @var int
	 */
	private $attempts = 5;

	/**
	 * Lockout length in minutes.
	 *
	 * @var int
	 */
	private $minutes = 15;

	/**
	 * Strict mode: lock logins from new devices when many addresses fail together.
	 *
	 * @var bool
	 */
	private $strict = false;

	/**
	 * Failed logins from all addresses together that start the strict lock.
	 *
	 * @var int
	 */
	private $strict_attempts = 50;

	/**
	 * Option with the strict mode counter: array( count, window start ). Not a transient: its expiry would
	 * move with every write.
	 */
	const ALL_OPTION = 'helloadmin_guard_login_all';

	/**
	 * Option with the time until which new devices are locked out.
	 */
	const ALL_LOCK_OPTION = 'helloadmin_guard_login_all_lock';

	/**
	 * Transient set after the attack e-mail, so it goes out once a day at most.
	 */
	const ALL_MAILED = 'helloadmin_guard_login_all_mailed';

	/**
	 * Whether an application password failure was just counted; wp_login_failed follows it on XML-RPC.
	 *
	 * @var bool
	 */
	private $app_counted = false;

	/**
	 * Setting key.
	 *
	 * @return string
	 */
	public function id() {
		return 'limit_login';
	}

	/**
	 * Title next to the toggle.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Limit login attempts', 'helloadmin-guard' );
	}

	/**
	 * What it does.
	 *
	 * @return string
	 */
	public function what() {
		return __( 'After too many failed logins from one IP address, every login from that address is refused for a while, even with the right password, so a bot cannot keep guessing. Works for the login page, XML-RPC and REST API logins. A successful login resets the count.', 'helloadmin-guard' )
			. ' ' . __( 'Botnets guess from thousands of addresses, a few tries each, which a limit per address does not stop. The strict mode below counts failed logins from all addresses together; when there are too many, logins from new devices are refused for a while. Browsers that logged in to this site before carry a signed cookie and still get in, and "Lost your password?" keeps working: a password reset makes that browser known. The site address gets an e-mail about the attack.', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'You can lock yourself out for a while by mistyping the password. If the site is behind a proxy or CDN that the host does not unwrap, all visitors share one address and one bot can lock everyone out: then leave this off or ask the host to pass the real visitor IP.', 'helloadmin-guard' );
	}

	/**
	 * How to undo.
	 *
	 * @return string
	 */
	public function undo() {
		return __( 'Turn this off and save: lockouts end at once. If you are locked out yourself, wait until the time runs out, or rename the folder wp-content/plugins/helloadmin-guard over FTP or in the hosting file manager.', 'helloadmin-guard' );
	}

	/**
	 * General tab, login.
	 *
	 * @return string
	 */
	public function group() {
		return 'login';
	}

	/**
	 * Article with details.
	 *
	 * @return string
	 */
	public function learn_more() {
		return 'https://helloadmin.com/prevent-brute-force-attacks-on-wordpress-login-with-rate-limiting/';
	}

	/**
	 * Attempts and lockout length.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function fields() {
		return array(
			'attempts'        => array(
				'label'       => __( 'Attempts', 'helloadmin-guard' ),
				'type'        => 'text',
				'default'     => '5',
				'description' => __( 'Failed logins allowed from one address, 3 to 20.', 'helloadmin-guard' ),
			),
			'minutes'         => array(
				'label'       => __( 'Lockout, minutes', 'helloadmin-guard' ),
				'type'        => 'text',
				'default'     => '15',
				'description' => __( 'How long the address stays locked, 5 to 1440 minutes (one day).', 'helloadmin-guard' ),
			),
			'strict'          => array(
				'group'       => __( 'Attacks from many addresses', 'helloadmin-guard' ),
				'label'       => __( 'Strict mode: lock new devices out during such an attack', 'helloadmin-guard' ),
				'type'        => 'checkbox',
				'default'     => '0',
				'description' => __( 'Not for sites where customers or members log in: during an attack they could not log in from a new device without a password reset.', 'helloadmin-guard' ),
			),
			'strict_attempts' => array(
				'label'       => __( 'Strict mode: failed logins from all addresses', 'helloadmin-guard' ),
				'type'        => 'text',
				'default'     => '50',
				'description' => __( 'Within the lockout time above, 20 to 1000. When reached, new devices are locked out for the same time.', 'helloadmin-guard' ),
			),
		);
	}

	/**
	 * Keeps the numbers in range.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	public function sanitize_field( $field, $value ) {
		if ( 'strict' === $field ) {
			return '1' === (string) $value ? '1' : '0';
		}
		$number = is_numeric( $value ) ? (int) $value : 0;
		if ( 'strict_attempts' === $field ) {
			return (string) ( $number ? min( 1000, max( 20, $number ) ) : 50 );
		}
		if ( 'attempts' === $field ) {
			return (string) ( $number ? min( 20, max( 3, $number ) ) : 5 );
		}
		return (string) ( $number ? min( 1440, max( 5, $number ) ) : 15 );
	}

	/**
	 * Is login limiting on, here or in another plugin?
	 *
	 * @return bool|null
	 */
	public function probe() {
		// Logins cannot be tested from outside without failing them on purpose: look at what is running instead.
		if ( false !== has_filter( 'authenticate', array( $this, 'refuse_locked' ) ) ) {
			return true;
		}
		$active = (array) get_option( 'active_plugins', array() );
		return (bool) array_intersect( self::OTHERS, $active );
	}

	/**
	 * Risk while off.
	 *
	 * @return string
	 */
	public function risk() {
		return 'high';
	}

	/**
	 * Place on the tab.
	 *
	 * @return int
	 */
	public function priority() {
		return 25;
	}

	/**
	 * Cloudflare address ranges (https://www.cloudflare.com/ips/, November 2026).
	 */
	const CLOUDFLARE = array(
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);

	/**
	 * Warns when the visitor address the site sees is a Cloudflare server: the host does not pass the real
	 * visitor IP (CF-Connecting-IP is missing), so all visitors share a few addresses and one bot can lock
	 * everyone out.
	 *
	 * @return string[]
	 */
	public function warnings() {
		// Behind Cloudflare the plugin reads CF-Connecting-IP itself; the warning is for the case it does not arrive.
		$ip = self::visitor_ip();
		if ( self::is_internal( $ip ) && ! self::is_local_site() ) {
			return array(
				sprintf(
					/* translators: %s: IP address. */
					__( 'The site sees your address as %s, an address inside the server or its network. A proxy in front of the site (for example Nginx in front of Apache) does not pass the real visitor address, so all visitors share it and one bot can lock everyone out. Ask the host to pass the real visitor IP (mod_remoteip or the real IP module), or leave this setting off until then. Developers can also set the address with the helloadmin_guard_client_ip filter.', 'helloadmin-guard' ),
					$ip
				),
			);
		}
		foreach ( self::CLOUDFLARE as $range ) {
			if ( self::in_range( $ip, $range ) ) {
				/* translators: %s: IP address. */
				return array( sprintf( __( 'The site sees your address as %s, which belongs to Cloudflare, and the CF-Connecting-IP header with the real visitor address does not reach WordPress. Then all visitors share a few Cloudflare addresses and one bot can lock everyone out. Ask the host why the header is removed, or leave this setting off until then.', 'helloadmin-guard' ), $ip ) );
			}
		}
		return array();
	}

	/**
	 * Loopback and private addresses: what PHP sees behind a local proxy that does not pass the visitor address.
	 */
	const INTERNAL = array( '127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '::1/128', 'fc00::/7' );

	/**
	 * Whether an address is loopback or private.
	 *
	 * @param string $ip Address.
	 * @return bool
	 */
	private static function is_internal( $ip ) {
		foreach ( self::INTERNAL as $range ) {
			if ( self::in_range( $ip, $range ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether the site itself is a local copy (localhost, .test, .local, .localhost, DDEV, Lando, an IP address):
	 * there the admin's own address is local too, and the warning would only scare.
	 *
	 * @return bool
	 */
	private static function is_local_site() {
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		return '' === $host || 'localhost' === $host || false !== filter_var( $host, FILTER_VALIDATE_IP )
			|| (bool) preg_match( '/\.(test|local|localhost|ddev\.site|lndo\.site|example|invalid)$/', $host );
	}

	/**
	 * Whether an IPv4 or IPv6 address is inside a CIDR range.
	 *
	 * @param string $ip    Address.
	 * @param string $range Range like 104.16.0.0/13.
	 * @return bool
	 */
	public static function in_range( $ip, $range ) {
		list( $net, $bits ) = explode( '/', $range );
		$ip_bin             = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- an invalid address only means "not in range".
		$net_bin            = @inet_pton( $net ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- same.
		if ( false === $ip_bin || false === $net_bin || strlen( $ip_bin ) !== strlen( $net_bin ) ) {
			return false;
		}
		$bits  = (int) $bits;
		$bytes = intdiv( $bits, 8 );
		if ( substr( $ip_bin, 0, $bytes ) !== substr( $net_bin, 0, $bytes ) ) {
			return false;
		}
		$rest = $bits % 8;
		if ( 0 === $rest ) {
			return true;
		}
		$mask = chr( ( 0xff << ( 8 - $rest ) ) & 0xff );
		return ( $ip_bin[ $bytes ] & $mask ) === ( $net_bin[ $bytes ] & $mask );
	}

	/**
	 * Adds the hooks.
	 *
	 * @param Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( Settings $settings ) {
		$this->attempts = (int) $settings->field( $this->id(), 'attempts', '5' );
		$this->minutes  = (int) $settings->field( $this->id(), 'minutes', '15' );
		$this->strict   = '1' === (string) $settings->field( $this->id(), 'strict', '0' );
		// A value saved before 0.6 or by hand may be out of range: the same limits as on the settings page.
		$this->strict_attempts = (int) $this->sanitize_field( 'strict_attempts', $settings->field( $this->id(), 'strict_attempts', '50' ) );
		add_filter( 'authenticate', array( $this, 'refuse_locked' ), 99 );
		add_action( 'wp_login_failed', array( $this, 'count_login_failure' ) );
		add_action( 'wp_login', array( $this, 'reset' ) );
		// Known browsers: set on every login and after a password reset, used only by the strict mode.
		add_action( 'wp_login', array( $this, 'remember_browser' ), 10, 2 );
		add_action( 'after_password_reset', array( $this, 'remember_browser_after_reset' ) );
		// REST API and XML-RPC logins with an application password skip the filter and the action above.
		add_action( 'wp_authenticate_application_password_errors', array( $this, 'refuse_locked_app' ) );
		add_action( 'application_password_failed_authentication', array( $this, 'count_app_failure' ) );
	}

	/**
	 * Refuses a valid application password from a locked address.
	 *
	 * @param \WP_Error $errors Errors collected for this application password check.
	 * @return void
	 */
	public function refuse_locked_app( $errors ) {
		// No strict mode here: application passwords are long and random, guessing them is pointless, and apps
		// and Jetpack must keep working during an attack. The lock of the address still applies.
		$locked = $this->lock_error( null, false );
		if ( $errors instanceof \WP_Error && $locked ) {
			$errors->add( $locked->get_error_code(), $locked->get_error_message() );
		}
	}

	/**
	 * Refuses any login from a locked address, also with the right password.
	 *
	 * @param mixed $user Result of the earlier authentication filters.
	 * @return mixed
	 */
	public function refuse_locked( $user ) {
		// wp-login.php runs the login check on every view of the page, with nothing entered. That is no attempt:
		// a lock error here would be counted as a failed login (and shown before anything was typed).
		if ( null === $user || ( is_wp_error( $user ) && ! array_diff( $user->get_error_codes(), array( 'empty_username', 'empty_password' ) ) ) ) {
			return $user;
		}
		$locked = $this->lock_error( $user, $this->strict );
		return $locked ? $locked : $user;
	}

	/**
	 * The lock that applies to this login, if any. In the strict lock every attempt from a new device gets the same
	 * answer, right password or not, so the answer never confirms a guessed password.
	 *
	 * @param mixed $user   Result of the earlier authentication filters.
	 * @param bool  $strict Whether the strict lock applies to this kind of login.
	 * @return \WP_Error|null
	 */
	private function lock_error( $user, $strict ) {
		if ( $strict && (int) get_option( self::ALL_LOCK_OPTION, 0 ) > time() && ! $this->known_browser( $user ) ) {
			return new \WP_Error(
				'helloadmin_guard_locked',
				__( 'Logins from new devices are paused for a while because of many failed attempts. If this is your site, use "Lost your password?": the reset link lets this browser in.', 'helloadmin-guard' )
			);
		}
		$until = (int) get_transient( $this->key( 'lock' ) );
		if ( $until > time() ) {
			$minutes = (int) ceil( ( $until - time() ) / MINUTE_IN_SECONDS );
			return new \WP_Error(
				'helloadmin_guard_locked',
				sprintf(
					/* translators: %d: minutes until the next login attempt is allowed. */
					_n( 'Too many failed login attempts. Try again in %d minute.', 'Too many failed login attempts. Try again in %d minutes.', $minutes, 'helloadmin-guard' ),
					$minutes
				)
			);
		}
		return null;
	}

	/**
	 * Counts a failed application password, but not the answer "application passwords are off":
	 * that is no password guess.
	 *
	 * @param mixed $error Authentication error.
	 * @return void
	 */
	public function count_app_failure( $error ) {
		if ( is_wp_error( $error ) && 0 === strpos( (string) $error->get_error_code(), 'application_passwords_disabled' ) ) {
			return;
		}
		$this->count_failure( false );
		$this->app_counted = true;
	}

	/**
	 * Counts a failed login, unless it is the same application password failure counted a moment ago.
	 *
	 * @return void
	 */
	public function count_login_failure() {
		if ( $this->app_counted ) {
			$this->app_counted = false;
			return;
		}
		$this->count_failure();
	}

	/**
	 * Counts a failed login and locks the address when the limit is reached.
	 *
	 * @param bool $site_wide Whether it counts toward the strict mode (false for application passwords).
	 * @return void
	 */
	public function count_failure( $site_wide = true ) {
		if ( get_transient( $this->key( 'lock' ) ) ) {
			return; // A locked address adds nothing: one bot alone must not start the strict lock.
		}
		// Application passwords are left out of the strict mode, so their failures do not start it either.
		if ( $this->strict && $site_wide ) {
			$this->count_global_failure();
		}
		$window = $this->minutes * MINUTE_IN_SECONDS;
		$count  = (int) get_transient( $this->key( 'count' ) ) + 1;
		if ( $count >= $this->attempts ) {
			set_transient( $this->key( 'lock' ), time() + $window, $window );
			delete_transient( $this->key( 'count' ) );
			return;
		}
		set_transient( $this->key( 'count' ), $count, $window );
	}

	/**
	 * Strict mode: counts a failure from any address; at the limit, locks new devices out and e-mails the site
	 * address (once a day at most). Failures during the lock extend it, so a lasting attack keeps it on.
	 *
	 * @return void
	 */
	private function count_global_failure() {
		$window = $this->minutes * MINUTE_IN_SECONDS;
		$now    = time();
		if ( (int) get_option( self::ALL_LOCK_OPTION, 0 ) > $now ) {
			update_option( self::ALL_LOCK_OPTION, $now + $window, false );
			return;
		}
		$state = get_option( self::ALL_OPTION, array() );
		$count = is_array( $state ) && isset( $state[0], $state[1] ) && (int) $state[1] > $now - $window ? (int) $state[0] : 0;
		$start = $count ? (int) $state[1] : $now;
		++$count;
		if ( $count < $this->strict_attempts ) {
			update_option( self::ALL_OPTION, array( $count, $start ), false );
			return;
		}
		delete_option( self::ALL_OPTION );
		update_option( self::ALL_LOCK_OPTION, $now + $window, false );
		if ( ! get_transient( self::ALL_MAILED ) ) {
			set_transient( self::ALL_MAILED, 1, DAY_IN_SECONDS );
			$this->send_attack_mail( $count, (int) ceil( ( $now - $start ) / MINUTE_IN_SECONDS ) );
		}
	}

	/**
	 * E-mail about a login attack from many addresses.
	 *
	 * @param int $count   Failed logins counted.
	 * @param int $minutes Minutes they took.
	 * @return void
	 */
	private function send_attack_mail( $count, $minutes ) {
		$lines = array(
			/* translators: 1: number of failed logins, 2: minutes. */
			sprintf( __( '%1$d failed logins from different addresses in %2$d minutes: a bot network is guessing passwords on this site.', 'helloadmin-guard' ), $count, max( 1, $minutes ) ),
			/* translators: %d: minutes. */
			sprintf( __( 'For the next %d minutes, and longer while the attempts go on, logins from new devices are refused. Browsers that logged in to this site before still get in.', 'helloadmin-guard' ), $this->minutes ),
			__( 'If you need to log in from a new device, use "Lost your password?" on the login page: the reset link lets that browser in.', 'helloadmin-guard' ),
			'',
			__( 'Worth doing now: check that every administrator has a strong unique password, and turn on two-factor login if you have not yet.', 'helloadmin-guard' ),
		);
		$this->send_alert_mail( __( 'Login attack from many addresses', 'helloadmin-guard' ), $lines );
	}

	/**
	 * Name of the known-browser cookie.
	 *
	 * @return string
	 */
	private static function cookie_name() {
		return 'hag_known_' . ( defined( 'COOKIEHASH' ) ? COOKIEHASH : md5( (string) get_option( 'siteurl' ) ) );
	}

	/**
	 * Signature of a known-browser cookie: user ID and expiry, signed with the site's salts, so it cannot be made
	 * up or moved to another user.
	 *
	 * @param int $user_id User ID.
	 * @param int $expires Expiry time.
	 * @return string
	 */
	private static function cookie_sig( $user_id, $expires ) {
		return hash_hmac( 'sha256', 'hag-known|' . $user_id . '|' . $expires, wp_salt( 'auth' ) );
	}

	/**
	 * Marks this browser as known for the user who just logged in.
	 *
	 * @param string        $login User name.
	 * @param \WP_User|null $user  User.
	 * @return void
	 */
	public function remember_browser( $login, $user = null ) {
		unset( $login );
		if ( $user instanceof \WP_User ) {
			self::set_known_cookie( (int) $user->ID );
		}
	}

	/**
	 * After a password reset by the link from the e-mail: this browser proved access to the mailbox.
	 *
	 * @param \WP_User $user User.
	 * @return void
	 */
	public function remember_browser_after_reset( $user ) {
		if ( $user instanceof \WP_User ) {
			self::set_known_cookie( (int) $user->ID );
		}
	}

	/**
	 * Sets the known-browser cookie for one year.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private static function set_known_cookie( $user_id ) {
		if ( headers_sent() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}
		$expires = time() + YEAR_IN_SECONDS;
		setcookie(
			self::cookie_name(),
			$user_id . '|' . $expires . '|' . self::cookie_sig( $user_id, $expires ),
			array(
				'expires'  => $expires,
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? (string) COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? (string) COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Whether this browser is known for the user that just gave the right password.
	 *
	 * @param mixed $user Result of the earlier authentication filters.
	 * @return bool
	 */
	private function known_browser( $user ) {
		if ( ! $user instanceof \WP_User ) {
			return false; // Wrong password or no user: nothing to let in.
		}
		$name  = self::cookie_name();
		$value = isset( $_COOKIE[ $name ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) ) : '';
		$parts = explode( '|', $value );
		if ( 3 !== count( $parts ) || (int) $parts[0] !== (int) $user->ID || (int) $parts[1] < time() ) {
			return false;
		}
		return hash_equals( self::cookie_sig( (int) $parts[0], (int) $parts[1] ), $parts[2] );
	}

	/**
	 * A successful login clears the failures of the address.
	 *
	 * @return void
	 */
	public function reset() {
		delete_transient( $this->key( 'count' ) );
	}

	/**
	 * Transient key for the visitor address (see Feature::visitor_ip()).
	 *
	 * @param string $what count or lock.
	 * @return string
	 */
	private function key( $what ) {
		return 'hag_login_' . $what . '_' . md5( self::visitor_ip() );
	}
}
