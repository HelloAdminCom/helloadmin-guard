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
		return __( 'After too many failed logins from one IP address, every login from that address is refused for a while, even with the right password, so a bot cannot keep guessing. Works for the login page, XML-RPC and REST API logins. A successful login resets the count.', 'helloadmin-guard' );
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
			'attempts' => array(
				'label'       => __( 'Attempts', 'helloadmin-guard' ),
				'type'        => 'text',
				'default'     => '5',
				'description' => __( 'Failed logins allowed from one address, 3 to 20.', 'helloadmin-guard' ),
			),
			'minutes'  => array(
				'label'       => __( 'Lockout, minutes', 'helloadmin-guard' ),
				'type'        => 'text',
				'default'     => '15',
				'description' => __( 'How long the address stays locked, 5 to 1440 minutes (one day).', 'helloadmin-guard' ),
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
		$number = is_numeric( $value ) ? (int) $value : 0;
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
	 * visitor IP, so all visitors share a few addresses and one bot can lock everyone out.
	 *
	 * @return string[]
	 */
	public function warnings() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		foreach ( self::CLOUDFLARE as $range ) {
			if ( self::in_range( $ip, $range ) ) {
				/* translators: %s: IP address. */
				return array( sprintf( __( 'The site sees your address as %s, which belongs to Cloudflare. The real visitor address does not reach WordPress, so all visitors share a few Cloudflare addresses and one bot can lock everyone out. Ask the host to turn on the real visitor IP for Cloudflare (mod_remoteip or the Cloudflare module), or leave this setting off until then.', 'helloadmin-guard' ), $ip ) );
			}
		}
		return array();
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
		add_filter( 'authenticate', array( $this, 'refuse_locked' ), 99 );
		add_action( 'wp_login_failed', array( $this, 'count_login_failure' ) );
		add_action( 'wp_login', array( $this, 'reset' ) );
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
		$locked = $this->refuse_locked( null );
		if ( $errors instanceof \WP_Error && is_wp_error( $locked ) ) {
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
		return $user;
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
		$this->count_failure();
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
	 * @return void
	 */
	public function count_failure() {
		if ( get_transient( $this->key( 'lock' ) ) ) {
			return;
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
	 * A successful login clears the failures of the address.
	 *
	 * @return void
	 */
	public function reset() {
		delete_transient( $this->key( 'count' ) );
	}

	/**
	 * Transient key for the visitor address. Only REMOTE_ADDR: X-Forwarded-For can be set by anyone.
	 *
	 * @param string $what count or lock.
	 * @return string
	 */
	private function key( $what ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		/**
		 * Visitor IP address used for login limits. Change it only if your server puts the real address elsewhere
		 * and you trust that source.
		 *
		 * @param string $ip REMOTE_ADDR.
		 */
		$ip = (string) apply_filters( 'helloadmin_guard_client_ip', $ip );
		return 'hag_login_' . $what . '_' . md5( $ip );
	}
}
