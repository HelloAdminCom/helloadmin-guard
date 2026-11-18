<?php
/**
 * Status of every setting: closed by this plugin, closed by someone else, open, or not needed.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the checks (Feature::probe()) in the background from the settings page, keeps the results for a day and
 * counts the open gaps for the number next to the menu item.
 */
class Status {

	const CACHE = 'helloadmin_guard_status';
	const AJAX  = 'helloadmin_guard_check';
	const NONCE = 'helloadmin-guard-check';
	const TTL   = DAY_IN_SECONDS;

	/**
	 * Security plugins that can close the same gaps, by plugin file. Used only to name the likely source.
	 */
	const KNOWN = array(
		'wordfence/wordfence.php'                         => 'Wordfence',
		'better-wp-security/better-wp-security.php'       => 'Solid Security',
		'ithemes-security-pro/ithemes-security-pro.php'   => 'Solid Security Pro',
		'all-in-one-wp-security-and-firewall/wp-security.php' => 'All-In-One Security',
		'really-simple-ssl/rlrsssl-really-simple-ssl.php' => 'Really Simple Security',
		'really-simple-ssl-pro/really-simple-ssl-pro.php' => 'Really Simple Security Pro',
		'sucuri-scanner/sucuri.php'                       => 'Sucuri Security',
		'wp-simple-firewall/icwp-wpsf.php'                => 'Shield Security',
		'defender-security/wp-defender.php'               => 'Defender',
		'wps-hide-login/wps-hide-login.php'               => 'WPS Hide Login',
		'disable-xml-rpc/disable-xml-rpc.php'             => 'Disable XML-RPC',
		'disable-xml-rpc-api/disable-xml-rpc-api.php'     => 'Disable XML-RPC-API',
		'headers-security-advanced-hsts-wp/headers-security-advanced-hsts-wp.php' => 'Headers Security Advanced & HSTS WP',
		'hide-my-wp/index.php'                            => 'Hide My WP Ghost',
	);

	/**
	 * Settings, keyed by id.
	 *
	 * @var array<string, Feature>
	 */
	private $features;

	/**
	 * Stored settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Keeps what the checks need.
	 *
	 * @param Settings               $settings Stored settings.
	 * @param array<string, Feature> $features Settings, keyed by id.
	 */
	public function __construct( Settings $settings, array $features ) {
		$this->settings = $settings;
		$this->features = $features;
	}

	/**
	 * Adds the AJAX handler that runs the checks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_' . self::AJAX, array( $this, 'ajax' ) );
		// Saved settings change what the site answers: check again on the next visit of the settings page.
		add_action( 'update_option_' . Settings::OPTION, array( __CLASS__, 'forget' ) );
	}

	/**
	 * Drops the stored results.
	 *
	 * @return void
	 */
	public static function forget() {
		delete_transient( self::CACHE );
	}

	/**
	 * Stored results, or null when there are none or they are older than a day.
	 *
	 * @return array{time: int, closed: array<string, bool|null>}|null
	 */
	public static function cached() {
		$data = get_transient( self::CACHE );
		return is_array( $data ) && isset( $data['time'], $data['closed'] ) && is_array( $data['closed'] ) ? $data : null;
	}

	/**
	 * Runs every check and stores the results.
	 *
	 * @return array{time: int, closed: array<string, bool|null>}
	 */
	public function run() {
		$closed = array();
		try {
			foreach ( $this->features as $id => $feature ) {
				$closed[ $id ] = $feature->probe();
			}
		} finally {
			Probe::cleanup();
		}
		$data = array(
			'time'   => time(),
			'closed' => $closed,
		);
		set_transient( self::CACHE, $data, self::TTL );
		return $data;
	}

	/**
	 * Runs the checks for the settings page script.
	 *
	 * @return void
	 */
	public function ajax() {
		check_ajax_referer( self::NONCE );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		$this->run();
		wp_send_json_success();
	}

	/**
	 * Status of one setting.
	 *
	 * @param string $id Setting id.
	 * @return string on (closed by this plugin), broken (on here, but the check still finds the gap open),
	 *                covered (closed by someone else), open, skipped (marked as not needed) or unknown.
	 */
	public function of( $id ) {
		$on     = $this->settings->is_enabled( $id );
		$cached = self::cached();
		$closed = null !== $cached && array_key_exists( $id, $cached['closed'] ) ? $cached['closed'][ $id ] : null;

		if ( $on ) {
			return false === $closed ? 'broken' : 'on';
		}
		if ( $this->settings->is_skipped( $id ) ) {
			return 'skipped';
		}
		if ( true === $closed ) {
			return 'covered';
		}
		return false === $closed ? 'open' : 'unknown';
	}

	/**
	 * Number of gaps that need attention: open ones and settings that are on but do not work.
	 * Before the first check every setting that is off and not skipped counts as open.
	 *
	 * @return int
	 */
	public function count() {
		$count = 0;
		foreach ( array_keys( $this->features ) as $id ) {
			$status = $this->of( $id );
			if ( in_array( $status, array( 'open', 'broken' ), true ) || ( 'unknown' === $status && null === self::cached() ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Names of active security plugins that may have closed the gap.
	 *
	 * @return string[]
	 */
	public static function suspects() {
		$active = (array) get_option( 'active_plugins', array() );
		$names  = array();
		foreach ( self::KNOWN as $file => $name ) {
			if ( in_array( $file, $active, true ) ) {
				$names[] = $name;
			}
		}
		return $names;
	}

	/**
	 * Data for the settings page script.
	 *
	 * @return array<string, mixed>
	 */
	public static function script_data() {
		return array(
			'checkAction' => self::AJAX,
			'checkNonce'  => wp_create_nonce( self::NONCE ),
			'checkNeeded' => null === self::cached(),
		);
	}
}
