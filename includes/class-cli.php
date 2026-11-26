<?php
/**
 * WP-CLI commands: wp helloadmin-guard status | check | enable | disable.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Manage HelloAdmin Guard settings from the command line.
 *
 * Server rules (Advanced tab) are written into .htaccess by enable and disable as well, with the same backup and
 * the same check of the home page as on the settings page.
 */
class Cli {

	/**
	 * Settings, keyed by id.
	 *
	 * @var array<string, Feature>
	 */
	private $features;

	/**
	 * Keeps the settings list.
	 *
	 * @param array<string, Feature> $features Settings, keyed by id.
	 */
	public function __construct( array $features ) {
		$this->features = $features;
	}

	/**
	 * Lists every setting with its state and the result of the last check.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, csv, json or yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp helloadmin-guard status
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function status( $args, $assoc_args ) {
		unset( $args );
		$settings = new Settings();
		$status   = new Status( $settings, $this->features );
		$rows     = array();
		foreach ( $this->sorted() as $feature ) {
			$rows[] = array(
				'id'     => $feature->id(),
				'tab'    => $feature->tab(),
				'risk'   => $feature->risk(),
				'on'     => $settings->is_enabled( $feature->id() ) ? 'yes' : 'no',
				'status' => $status->of( $feature->id() ),
			);
		}
		\WP_CLI\Utils\format_items( isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table', $rows, array( 'id', 'tab', 'risk', 'on', 'status' ) );
		$cached = Status::cached();
		\WP_CLI::log( null === $cached ? 'Not checked yet: run wp helloadmin-guard check.' : 'Last check: ' . wp_date( 'Y-m-d H:i', $cached['time'] ) . '. Open gaps: ' . $status->count() . '.' );
	}

	/**
	 * Checks every gap from outside, as a visitor who is not logged in, and stores the result.
	 *
	 * Needs the site URL to be reachable from the server itself. Run it with --url=https://your-site when WP-CLI does
	 * not know the address.
	 *
	 * ## EXAMPLES
	 *
	 *     wp helloadmin-guard check
	 *
	 * @return void
	 */
	public function check() {
		$settings = new Settings();
		$status   = new Status( $settings, $this->features );
		$status->run();
		$this->status( array(), array() );
	}

	/**
	 * Turns settings on.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more setting ids, see wp helloadmin-guard status.
	 *
	 * [--slug=<slug>]
	 * : New login address for login_url (at least 4 characters: letters, numbers, dashes). Needed when no
	 * address was saved on the settings page yet.
	 *
	 * [--server=<server>]
	 * : Web server of this site, when the settings page was never opened in the browser (WP-CLI cannot detect
	 * it). Remembered for later commands.
	 * ---
	 * options:
	 *   - apache
	 *   - nginx
	 *   - other
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp helloadmin-guard enable disable_xmlrpc block_backups
	 *     wp helloadmin-guard enable login_url --slug=my-door-7k
	 *
	 * @param string[]              $args       Setting ids.
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function enable( $args, $assoc_args = array() ) {
		$this->change( $args, true, $assoc_args );
	}

	/**
	 * Turns settings off.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more setting ids.
	 *
	 * [--server=<server>]
	 * : Web server of this site, see enable.
	 * ---
	 * options:
	 *   - apache
	 *   - nginx
	 *   - other
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp helloadmin-guard disable login_url
	 *
	 * @param string[]              $args       Setting ids.
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function disable( $args, $assoc_args = array() ) {
		$this->change( $args, false, $assoc_args );
	}

	/**
	 * Stores --server when it was given.
	 *
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	private static function take_server( array $assoc_args ) {
		if ( isset( $assoc_args['server'] ) && ! Htaccess::set_server( (string) $assoc_args['server'] ) ) {
			\WP_CLI::error( 'Unknown --server value. Use apache (also LiteSpeed), nginx or other.' );
		}
	}

	/**
	 * Login address for enable login_url: --slug, or the one saved on the settings page. Stops the command when
	 * there is none or it cannot be used, because the login rule without a working login page locks everyone out.
	 *
	 * @param array<string, mixed>  $option     Settings about to be saved.
	 * @param array<string, string> $assoc_args Options.
	 * @return string
	 */
	private static function login_slug( array $option, array $assoc_args ) {
		if ( is_multisite() ) {
			\WP_CLI::error( 'login_url does not work on multisite networks.' );
		}
		$saved = isset( $option['fields']['login_url']['slug'] ) && is_string( $option['fields']['login_url']['slug'] ) ? $option['fields']['login_url']['slug'] : '';
		$slug  = isset( $assoc_args['slug'] ) ? sanitize_title( (string) $assoc_args['slug'] ) : $saved;
		if ( '' === $slug ) {
			\WP_CLI::error( 'login_url needs a login address. Run: wp helloadmin-guard enable login_url --slug=<address> (at least 4 characters: letters, numbers, dashes).' );
		}
		// The rest of the command output is English; the shared check returns the site's language.
		$switched = switch_to_locale( 'en_US' );
		$problem  = Features\Login_Url::slug_problem( $slug );
		if ( $switched ) {
			restore_previous_locale();
		}
		if ( $problem ) {
			\WP_CLI::error( $problem . ' Nothing changed.' );
		}
		return $slug;
	}

	/**
	 * Prints the messages of Settings_Apply::apply(). Errors do not stop the command: what could be applied is
	 * still saved and reported.
	 *
	 * @param array<string, mixed> $result Result of apply().
	 * @return void
	 */
	private static function print_messages( array $result ) {
		foreach ( (array) $result['messages'] as $message ) {
			$text = (string) $message['text'];
			if ( ! empty( $message['data']['kept'] ) ) {
				$text .= ' These stay on because their rules are still in the file: ' . implode( ', ', (array) $message['data']['kept'] ) . '.';
			}
			if ( 'info' === $message['type'] ) {
				\WP_CLI::log( $text );
			} else {
				\WP_CLI::warning( $text );
			}
		}
	}

	/**
	 * Saves the new state and syncs the server rules.
	 *
	 * @param string[]              $ids        Setting ids.
	 * @param bool                  $on         Turn on or off.
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	private function change( array $ids, $on, array $assoc_args = array() ) {
		$unknown = array_diff( $ids, array_keys( $this->features ) );
		if ( $unknown ) {
			\WP_CLI::error( 'Unknown setting: ' . implode( ', ', $unknown ) . '. See wp helloadmin-guard status.' );
		}
		if ( isset( $assoc_args['slug'] ) && ! ( $on && in_array( 'login_url', $ids, true ) ) ) {
			\WP_CLI::error( '--slug works only with enable login_url.' );
		}
		self::take_server( $assoc_args );
		$option = get_option( Settings::OPTION, array() );
		$option = is_array( $option ) ? $option : array();
		// A damaged option (a string instead of a list) would break unset() and the loops below.
		foreach ( array( 'enabled', 'skip' ) as $key ) {
			if ( isset( $option[ $key ] ) && ! is_array( $option[ $key ] ) ) {
				$option[ $key ] = array();
			}
		}
		if ( $on && in_array( 'login_url', $ids, true ) ) {
			$option['fields']['login_url']['slug'] = self::login_slug( $option, $assoc_args );
		}
		foreach ( $ids as $id ) {
			if ( $on ) {
				$option['enabled'][ $id ] = true;
				unset( $option['skip'][ $id ] );
			} else {
				unset( $option['enabled'][ $id ] );
			}
		}

		$result = ( new Settings_Apply( $this->features ) )->apply(
			$option,
			array(
				'source'  => 'cli',
				'changed' => $ids,
				'on'      => $on,
			)
		);
		self::print_messages( $result );
		$option = $result['settings'];

		update_option( Settings::OPTION, $option );
		Status::forget();
		// When the rules could not be written, a server rule setting is not enabled by this command, even when it is
		// "on" only because its old rules are still in the file.
		$failed = false;
		foreach ( (array) $result['messages'] as $message ) {
			$failed = $failed || 'error' === $message['type'];
		}
		$done = array();
		foreach ( $ids as $id ) {
			// Settings that also work in PHP (XML-RPC, login URL) stay on, as Htaccess::settle() keeps them.
			if ( $on && $failed && $this->features[ $id ] instanceof Htaccess_Feature ) {
				continue;
			}
			if ( ! empty( $option['enabled'][ $id ] ) === $on ) {
				$done[] = $id;
			}
		}
		if ( ! $done ) {
			\WP_CLI::error( 'Nothing changed.' );
		}
		\WP_CLI::success( ( $on ? 'Enabled: ' : 'Disabled: ' ) . implode( ', ', $done ) );
		if ( $on && in_array( 'login_url', $done, true ) ) {
			\WP_CLI::log( 'Log in at ' . home_url( '/' . $option['fields']['login_url']['slug'] . '/' ) . ' from now on. wp-login.php answers 404 to visitors.' );
		}
	}

	/**
	 * Rewrites the plugin block in .htaccess from the saved settings, for example after the site or its .htaccess
	 * was copied from another server.
	 *
	 * ## OPTIONS
	 *
	 * [--server=<server>]
	 * : Web server of this site, see enable.
	 * ---
	 * options:
	 *   - apache
	 *   - nginx
	 *   - other
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp helloadmin-guard sync
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function sync( $args = array(), $assoc_args = array() ) {
		unset( $args );
		self::take_server( $assoc_args );
		$before = Htaccess::ids_in_file();
		$was    = is_readable( Htaccess::path() ) ? md5_file( Htaccess::path() ) : '';
		$result = Plugin::instance()->sync_htaccess();
		Status::forget();
		if ( is_wp_error( $result ) && 'not_checked' !== $result->get_error_code() ) {
			$code = (string) $result->get_error_code();
			if ( 'restore_failed' === $code || 'not_apache' === $code ) {
				\WP_CLI::error( Settings_Apply::text( $code, 'cli' ) );
			}
			\WP_CLI::error( 'Server rules were not written (' . $code . '). .htaccess is unchanged.' );
		}
		if ( is_wp_error( $result ) ) {
			\WP_CLI::warning( Settings_Apply::text( 'not_checked', 'cli' ) );
		}
		$after   = Htaccess::ids_in_file();
		$now     = is_readable( Htaccess::path() ) ? md5_file( Htaccess::path() ) : '';
		$removed = array_diff( $before, $after );
		$added   = array_diff( $after, $before );
		\WP_CLI::success(
			( $removed || $added || $was !== $now ? '.htaccess now matches the settings.' : '.htaccess already matched the settings.' )
			. ( $removed ? ' Removed: ' . implode( ', ', $removed ) . '.' : '' )
			. ( $added ? ' Added: ' . implode( ', ', $added ) . '.' : '' )
		);
	}

	/**
	 * Settings in the order of the settings page.
	 *
	 * @return Feature[]
	 */
	private function sorted() {
		$features = array_values( $this->features );
		usort(
			$features,
			static function ( Feature $a, Feature $b ) {
				return array( $a->tab(), $a->priority() ) <=> array( $b->tab(), $b->priority() );
			}
		);
		return $features;
	}
}
