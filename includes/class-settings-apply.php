<?php
/**
 * Applies a set of settings: builds the server rules, writes .htaccess, checks the site, turns off what could not
 * be applied. One place for the admin page and WP-CLI.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * The core does not save the option and does not print anything. It returns:
 *
 * - settings:          the settings to save, after anything that could not be applied was turned off;
 * - rolled_back:       ids of settings that were asked to be on but stay off;
 * - kept:              ids that stay on because their rules are still in the file (see Htaccess::settle());
 * - messages:          list of array( type, code, text, data ); type is error, warning, info or success;
 * - skipped_duplicate: true when the same call came twice in one request (WordPress may run the sanitize
 *                      callback twice on the first save) and the first result was returned.
 *
 * The caller saves the settings (the admin page returns them from the sanitize callback, so WordPress writes the
 * option once; WP-CLI calls update_option()) and shows the messages its own way.
 */
class Settings_Apply {

	/**
	 * Features by id.
	 *
	 * @var Feature[]
	 */
	private $features;

	/**
	 * Results of this request by fingerprint (settings + context). A new request starts empty, so saving the same
	 * settings again, or running the same command again, always applies them.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $done = array();

	/**
	 * Constructor.
	 *
	 * @param Feature[] $features Features by id.
	 */
	public function __construct( array $features ) {
		$this->features = $features;
	}

	/**
	 * Applies the settings.
	 *
	 * @param array<string, mixed> $settings Normalized settings about to be saved (enabled, skip, fields, prefs).
	 * @param array<string, mixed> $context  source: admin, cli or api; changed: ids the caller switched;
	 *                                       on: whether they were switched on (CLI enable / disable).
	 * @return array{settings: array<string, mixed>, rolled_back: string[], kept: string[], messages: array<int, array{type: string, code: string, text: string, data: array<string, mixed>}>, skipped_duplicate: bool}
	 */
	public function apply( array $settings, array $context = array() ) {
		$context = array_merge(
			array(
				'source'  => 'admin',
				'changed' => array(),
				'on'      => true,
			),
			$context
		);
		$key     = md5( (string) wp_json_encode( array( $settings, $context ) ) );
		if ( isset( $this->done[ $key ] ) ) {
			$again                      = $this->done[ $key ];
			$again['skipped_duplicate'] = true;
			return $again;
		}

		$messages = array();
		$enabled  = isset( $settings['enabled'] ) && is_array( $settings['enabled'] ) ? $settings['enabled'] : array();
		$fields   = isset( $settings['fields'] ) && is_array( $settings['fields'] ) ? $settings['fields'] : array();

		// Rules from the values about to be saved. A copy of each feature gets the values, so the shared objects that
		// run this request keep the stored ones.
		$rules = array();
		foreach ( $this->features as $id => $feature ) {
			if ( empty( $enabled[ $id ] ) ) {
				continue;
			}
			$copy = clone $feature;
			$copy->set_values( isset( $fields[ $id ] ) && is_array( $fields[ $id ] ) ? $fields[ $id ] : array() );
			if ( $copy->apache_rules() ) {
				$rules[ $id ] = $copy;
			}
		}

		if ( 'cli' === $context['source'] ) {
			$messages = array_merge( $messages, $this->cli_notes( $rules, $context ) );
		}

		$rolled = array();
		$kept   = array();
		$result = Htaccess::sync( $rules );
		if ( is_wp_error( $result ) ) {
			$code = (string) $result->get_error_code();
			if ( 'not_checked' !== $code ) {
				$before  = $enabled;
				$enabled = Htaccess::settle( $enabled, $rules, $result );
				$rolled  = array_keys( array_diff_key( array_filter( $before ), array_filter( $enabled ) ) );
				$kept    = array_keys( array_diff_key( array_filter( $enabled ), array_filter( $before ) ) );
			}
			$messages[] = array(
				'type' => 'not_checked' === $code ? 'warning' : 'error',
				'code' => $code,
				'text' => self::text( $code, (string) $context['source'] ),
				'data' => array(
					'rolled_back' => $rolled,
					'kept'        => $kept,
				),
			);
		}

		$settings['enabled'] = $enabled;
		$out                 = array(
			'settings'          => $settings,
			'rolled_back'       => $rolled,
			'kept'              => $kept,
			'messages'          => $messages,
			'skipped_duplicate' => false,
		);
		$this->done[ $key ]  = $out;
		return $out;
	}

	/**
	 * Forgets the results of this request (tests, or code that applies the same settings twice on purpose).
	 *
	 * @return void
	 */
	public function reset_request_cache() {
		$this->done = array();
	}

	/**
	 * Notes that only make sense on the command line.
	 *
	 * @param Feature[]            $rules   Features with rules about to be written.
	 * @param array<string, mixed> $context Call context.
	 * @return array<int, array{type: string, code: string, text: string, data: array<string, mixed>}>
	 */
	private function cli_notes( array $rules, array $context ) {
		$notes = array();
		if ( ! empty( $context['on'] ) && in_array( 'login_url', (array) $context['changed'], true ) ) {
			$notes[] = array(
				'type' => 'warning',
				'code' => 'login_url_check',
				'text' => 'login_url uses the login address saved on the settings page; check it there before you log out.',
				'data' => array(),
			);
		}
		// WP-CLI has no web server to detect: an existing .htaccess file means Apache or LiteSpeed.
		global $is_apache;
		if ( ! $is_apache && file_exists( Htaccess::path() ) ) {
			$is_apache = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WP-CLI has no web server to detect.
			if ( $rules ) {
				$notes[] = array(
					'type' => 'info',
					'code' => 'server_unknown',
					'text' => 'Server rules go to ' . Htaccess::path() . '. If this site runs on Nginx, that file is ignored: copy the Nginx rules from the Advanced tab into the server config.',
					'data' => array( 'path' => Htaccess::path() ),
				);
			}
		}
		return $notes;
	}

	/**
	 * Text of a result code. The admin page shows translated texts; WP-CLI prints English, like the rest of its
	 * output.
	 *
	 * @param string $code   Error code from Htaccess::sync().
	 * @param string $source admin, cli or api.
	 * @return string
	 */
	public static function text( $code, $source ) {
		if ( 'cli' === $source ) {
			$cli = array(
				'restore_failed' => 'The home page answered with a server error after writing the rules, and the old .htaccess could not be written back. Delete everything between "# BEGIN HelloAdmin Guard" and "# END HelloAdmin Guard" in ' . Htaccess::path() . ' by hand.',
				'not_checked'    => 'Server rules were written, but the home page could not be opened to check them. Open the site to make sure it works.',
			);
			return isset( $cli[ $code ] ) ? $cli[ $code ] : 'Server rules were not written (' . $code . '). .htaccess is unchanged: new server rule settings stay off.';
		}
		$admin = array(
			'not_apache'     => __( 'Server rules were not saved: this server is not Apache or LiteSpeed, so the plugin cannot write them. Copy the rules from each card into your server configuration instead.', 'helloadmin-guard' ),
			'not_writable'   => __( 'Server rules were not saved: WordPress cannot write the .htaccess file. Copy the rules from each card into .htaccess by hand, or ask your host to make the file writable.', 'helloadmin-guard' ),
			'write_failed'   => __( 'Server rules were not saved: writing the .htaccess file failed. Nothing was changed.', 'helloadmin-guard' ),
			'site_broken'    => __( 'Server rules were not saved: after writing them the home page answered with a server error, so the old .htaccess file was put back. The server may not support one of the rules.', 'helloadmin-guard' ),
			'restore_failed' => __( 'After writing the server rules the home page answered with a server error, and the old .htaccess file could not be written back. Open .htaccess in the site root over FTP or in the hosting file manager and delete everything between "# BEGIN HelloAdmin Guard" and "# END HelloAdmin Guard".', 'helloadmin-guard' ),
			'not_checked'    => __( 'Server rules were saved, but the plugin could not open your home page to check them (some hosts block such requests). Open the site in a new tab to make sure it works.', 'helloadmin-guard' ),
		);
		return isset( $admin[ $code ] ) ? $admin[ $code ] : $code;
	}
}
