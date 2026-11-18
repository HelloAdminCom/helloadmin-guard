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
		if ( $on && in_array( 'login_url', $ids, true ) ) {
			\WP_CLI::warning( 'login_url uses the login address saved on the settings page; check it there before you log out.' );
		}

		$option = get_option( Settings::OPTION, array() );
		$option = is_array( $option ) ? $option : array();
		foreach ( $ids as $id ) {
			if ( $on ) {
				$option['enabled'][ $id ] = true;
				unset( $option['skip'][ $id ] );
			} else {
				unset( $option['enabled'][ $id ] );
			}
		}

		$rules = array();
		foreach ( $this->features as $id => $feature ) {
			if ( $feature->apache_rules() && ! empty( $option['enabled'][ $id ] ) ) {
				$rules[ $id ] = $feature;
			}
		}
		// WP-CLI has no web server to detect: an existing .htaccess file means Apache or LiteSpeed.
		global $is_apache;
		if ( ! $is_apache && file_exists( Htaccess::path() ) ) {
			$is_apache = true; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WP-CLI has no web server to detect.
		}
		$result = Htaccess::sync( $rules );
		if ( is_wp_error( $result ) && 'not_checked' !== $result->get_error_code() ) {
			foreach ( $rules as $id => $feature ) {
				if ( $feature instanceof Htaccess_Feature && in_array( $id, $ids, true ) ) {
					unset( $option['enabled'][ $id ] );
				}
			}
			\WP_CLI::warning( 'Server rules were not written (' . $result->get_error_code() . '); those settings stay off.' );
		} elseif ( is_wp_error( $result ) ) {
			\WP_CLI::warning( 'Server rules were written, but the home page could not be opened to check them. Open the site to make sure it works.' );
		}

		update_option( Settings::OPTION, $option );
		Status::forget();
		\WP_CLI::success( ( $on ? 'Enabled: ' : 'Disabled: ' ) . implode( ', ', $ids ) );
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
