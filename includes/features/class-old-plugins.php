<?php
/**
 * Lists plugins that look abandoned or are no longer on WordPress.org, and e-mails when a new one shows up.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Uses only the answer WordPress already gets when it checks for plugin updates (the update_plugins site
 * transient), so the plugin makes no requests of its own:
 *
 * - "old": the plugin is on WordPress.org, but was not tested with the last 3 major WordPress releases (the same
 *   rule WordPress.org uses for its own warning on the plugin page);
 * - "gone": WordPress asked about the plugin, and WordPress.org did not answer for it. Either the plugin was closed
 *   there (often for a security problem), or it never came from there (premium, custom). Plugins whose Update URI
 *   points somewhere else are left out: they get their updates from that place.
 */
class Old_Plugins extends Feature {

	/**
	 * Option with the plugins found last time: plugin file => old or gone. Signed (Feature::save_list()).
	 */
	const LIST_OPTION = 'helloadmin_guard_old_plugins';

	/**
	 * Major releases a plugin may lag behind before it counts as old.
	 */
	const BEHIND = 3;

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'old_plugins';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title() {
		return __( 'E-mail me about abandoned and closed plugins', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function what() {
		return __( 'A plugin nobody maintains gets no fixes when a hole is found in it. Twice a day WordPress asks WordPress.org about updates for your plugins. From that same answer this setting finds plugins that were not tested with the last 3 major WordPress releases, and plugins WordPress.org no longer answers for: they were closed there, often because of a security problem, or they came from somewhere else. They are listed on this card, and the administration address (Settings → General) gets an e-mail when a new one shows up. The plugin makes no requests of its own for this.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function may_break() {
		return __( 'Nothing stops working; the plugins are only listed. Premium and custom plugins that do not say where their updates come from (the Update URI header) show up in the second list too: check where you got them and whether they still get updates.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function undo() {
		return __( 'Turn this off and save. No plugins are changed.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function group() {
		return 'files';
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
	 * Place on the tab: after the must-use files alert.
	 *
	 * @return int
	 */
	public function priority() {
		return 47;
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
	 * The plugins found now, as lines on the card.
	 *
	 * @return string[]
	 */
	public function warnings() {
		$found = self::found();
		if ( null === $found ) {
			return array( __( 'WordPress has not checked for plugin updates yet, so there is nothing to compare. Open Dashboard → Updates once.', 'helloadmin-guard' ) );
		}
		$lines = array();
		foreach ( $found as $file => $item ) {
			$lines[] = self::describe( $file, $item );
		}
		return $lines;
	}

	/**
	 * Checks again each time WordPress saves new update data.
	 *
	 * @param Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( Settings $settings ) {
		add_action( 'set_site_transient_update_plugins', array( $this, 'check' ), 20 );
	}

	/**
	 * Remembers what is found now, so only plugins that show up later are e-mailed.
	 *
	 * @return void
	 */
	public function switched_on() {
		$found = self::found();
		self::save_list( self::LIST_OPTION, null === $found ? array() : self::kinds( $found ) );
	}

	/**
	 * Compares the plugins found now with the stored list and e-mails about the new ones.
	 *
	 * @return void
	 */
	public function check() {
		$found = self::found();
		if ( null === $found ) {
			return;
		}
		$now                 = self::kinds( $found );
		list( $known, $bad ) = self::load_list( self::LIST_OPTION );
		if ( $now !== $known || $bad ) {
			self::save_list( self::LIST_OPTION, $now );
		}
		if ( $bad ) {
			// A changed list only means more plugins than usual could be reported; start again from what is there.
			return;
		}
		$new = array();
		foreach ( $now as $file => $kind ) {
			if ( ! isset( $known[ $file ] ) || $known[ $file ] !== $kind ) {
				$new[ $file ] = $found[ $file ];
			}
		}
		if ( $new ) {
			$this->send_alert( $new );
		}
	}

	/**
	 * Plugins that look abandoned or are not on WordPress.org, or null when WordPress has not checked for updates.
	 *
	 * @return array<string, array{kind: string, name: string, tested: string}>|null
	 */
	public static function found() {
		$data = get_site_transient( 'update_plugins' );
		if ( ! is_object( $data ) || empty( $data->checked ) || ! is_array( $data->checked ) ) {
			return null;
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$answered = array_merge(
			isset( $data->no_update ) && is_array( $data->no_update ) ? $data->no_update : array(),
			isset( $data->response ) && is_array( $data->response ) ? $data->response : array()
		);
		$found    = array();
		foreach ( get_plugins() as $file => $info ) {
			$name = isset( $info['Name'] ) ? (string) $info['Name'] : $file;
			if ( isset( $answered[ $file ] ) ) {
				$item   = (array) $answered[ $file ];
				$tested = isset( $item['tested'] ) && is_string( $item['tested'] ) ? $item['tested'] : '';
				if ( '' !== $tested && self::releases_behind( $tested ) >= self::BEHIND ) {
					$found[ $file ] = array(
						'kind'   => 'old',
						'name'   => $name,
						'tested' => $tested,
					);
				}
				continue;
			}
			// Not asked about yet (installed after the last check), or updated from another place.
			if ( ! isset( $data->checked[ $file ] ) || ! self::from_wordpress_org( $info ) ) {
				continue;
			}
			$found[ $file ] = array(
				'kind'   => 'gone',
				'name'   => $name,
				'tested' => '',
			);
		}
		return $found;
	}

	/**
	 * Whether WordPress asks WordPress.org about this plugin: no Update URI, or one on WordPress.org.
	 *
	 * @param array<string, mixed> $info Plugin header data.
	 * @return bool
	 */
	private static function from_wordpress_org( array $info ) {
		$uri = isset( $info['UpdateURI'] ) ? trim( (string) $info['UpdateURI'] ) : '';
		if ( '' === $uri ) {
			return true;
		}
		$host = strtolower( (string) wp_parse_url( $uri, PHP_URL_HOST ) );
		return in_array( $host, array( 'wordpress.org', 'w.org' ), true );
	}

	/**
	 * How many major releases the version a plugin was tested with is behind this WordPress (6.9 -> 7.1 = 2).
	 *
	 * @param string $tested Version from the update data.
	 * @return int
	 */
	public static function releases_behind( $tested ) {
		$major = static function ( $version ) {
			$parts = array_map( 'intval', explode( '.', (string) $version ) );
			return $parts[0] * 10 + ( isset( $parts[1] ) ? $parts[1] : 0 );
		};
		return max( 0, $major( (string) get_bloginfo( 'version' ) ) - $major( $tested ) );
	}

	/**
	 * Plugin file => kind, the part of the findings that is stored.
	 *
	 * @param array<string, array{kind: string, name: string, tested: string}> $found Findings.
	 * @return array<string, string>
	 */
	private static function kinds( array $found ) {
		$kinds = array();
		foreach ( $found as $file => $item ) {
			$kinds[ $file ] = $item['kind'];
		}
		ksort( $kinds );
		return $kinds;
	}

	/**
	 * One line about a plugin, for the card and the e-mail.
	 *
	 * @param string                                            $file Plugin file.
	 * @param array{kind: string, name: string, tested: string} $item Finding.
	 * @return string
	 */
	private static function describe( $file, array $item ) {
		$active = is_plugin_active( $file ) ? '' : ' ' . __( '(inactive)', 'helloadmin-guard' );
		if ( 'old' === $item['kind'] ) {
			/* translators: 1: plugin name, 2: WordPress version. */
			return sprintf( __( '%1$s was last tested with WordPress %2$s. It may be abandoned: look for a maintained replacement.', 'helloadmin-guard' ), $item['name'], $item['tested'] ) . $active;
		}
		/* translators: %s: plugin name. */
		return sprintf( __( '%s is not on WordPress.org (closed there, or installed from somewhere else). Check where it came from and whether it still gets updates.', 'helloadmin-guard' ), $item['name'] ) . $active;
	}

	/**
	 * Sends the e-mail about plugins that showed up in the lists.
	 *
	 * @param array<string, array{kind: string, name: string, tested: string}> $added New findings.
	 * @return void
	 */
	private function send_alert( array $added ) {
		$lines = array( __( 'WordPress checked for plugin updates, and these plugins look abandoned or are no longer on WordPress.org:', 'helloadmin-guard' ), '' );
		foreach ( $added as $file => $item ) {
			$lines[] = '  ' . self::describe( $file, $item );
		}
		$lines[] = '';
		$lines[] = __( 'A plugin that gets no updates keeps every hole found in it. If you still need it, look for a maintained plugin that does the same; if not, delete it (an inactive plugin\'s files can still be opened directly).', 'helloadmin-guard' );
		$lines[] = '';
		$lines[] = admin_url( 'plugins.php' );

		$count   = count( $added );
		$first   = reset( $added );
		$subject = 1 === $count
			/* translators: %s: plugin name. */
			? sprintf( __( 'Plugin to check: %s', 'helloadmin-guard' ), $first['name'] )
			/* translators: %d: number of plugins. */
			: sprintf( _n( '%d plugin to check', '%d plugins to check', $count, 'helloadmin-guard' ), $count );
		$this->send_alert_mail( $subject, $lines );
	}
}
