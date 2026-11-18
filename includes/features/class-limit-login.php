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
	 * Adds the hooks.
	 *
	 * @param Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( Settings $settings ) {
		$this->attempts = (int) $settings->field( $this->id(), 'attempts', '5' );
		$this->minutes  = (int) $settings->field( $this->id(), 'minutes', '15' );
		add_filter( 'authenticate', array( $this, 'refuse_locked' ), 99 );
		add_action( 'wp_login_failed', array( $this, 'count_failure' ) );
		add_action( 'wp_login', array( $this, 'reset' ) );
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
