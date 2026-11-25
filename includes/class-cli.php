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
	 * ## EXAMPLES
	 *
	 *     wp helloadmin-guard enable disable_xmlrpc block_backups
	 *
	 * @param string[] $args Setting ids.
	 * @return void
	 */
	public function enable( $args ) {
		$this->change( $args, true );
	}

	/**
	 * Turns settings off.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : One or more setting ids.
	 *
	 * ## EXAMPLES
	 *
	 *     wp helloadmin-guard disable login_url
	 *
	 * @param string[] $args Setting ids.
	 * @return void
	 */
	public function disable( $args ) {
		$this->change( $args, false );
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
	 * @param string[] $ids Setting ids.
	 * @param bool     $on  Turn on or off.
	 * @return void
	 */
	private function change( array $ids, $on ) {
		$unknown = array_diff( $ids, array_keys( $this->features ) );
		if ( $unknown ) {
			\WP_CLI::error( 'Unknown setting: ' . implode( ', ', $unknown ) . '. See wp helloadmin-guard status.' );
		}
		$option = get_option( Settings::OPTION, array() );
		$option = is_array( $option ) ? $option : array();
		// A damaged option (a string instead of a list) would break unset() and the loops below.
		foreach ( array( 'enabled', 'skip' ) as $key ) {
			if ( isset( $option[ $key ] ) && ! is_array( $option[ $key ] ) ) {
				$option[ $key ] = array();
			}
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
		$done = array();
		foreach ( $ids as $id ) {
			if ( ! empty( $option['enabled'][ $id ] ) === $on ) {
				$done[] = $id;
			}
		}
		if ( ! $done ) {
			\WP_CLI::error( 'Nothing changed.' );
		}
		\WP_CLI::success( ( $on ? 'Enabled: ' : 'Disabled: ' ) . implode( ', ', $done ) );
	}

	/**
	 * Rewrites the plugin block in .htaccess from the saved settings, for example after the site or its .htaccess
	 * was copied from another server.
	 *
	 * ## EXAMPLES
	 *
	 *     wp helloadmin-guard sync
	 *
	 * @return void
	 */
	public function sync() {
		// WP-CLI has no web server to detect: an existing .htaccess file means Apache or LiteSpeed, for this write only.
		$assumed = 'apache' !== Htaccess::server() && file_exists( Htaccess::path() );
		if ( $assumed ) {
			Htaccess::assume_server( 'apache' );
		}
		$before = Htaccess::ids_in_file();
		try {
			$result = Plugin::instance()->sync_htaccess();
		} finally {
			if ( $assumed ) {
				Htaccess::assume_server( null );
			}
		}
		Status::forget();
		if ( is_wp_error( $result ) && 'not_checked' !== $result->get_error_code() ) {
			$code = (string) $result->get_error_code();
			\WP_CLI::error( 'restore_failed' === $code ? Settings_Apply::text( $code, 'cli' ) : 'Server rules were not written (' . $code . '). .htaccess is unchanged.' );
		}
		if ( is_wp_error( $result ) ) {
			\WP_CLI::warning( Settings_Apply::text( 'not_checked', 'cli' ) );
		}
		$after   = Htaccess::ids_in_file();
		$removed = array_diff( $before, $after );
		$added   = array_diff( $after, $before );
		\WP_CLI::success(
			( $removed || $added ? '.htaccess now matches the settings.' : '.htaccess already matched the settings.' )
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
