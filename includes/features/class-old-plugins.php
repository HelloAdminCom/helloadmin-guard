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
 * Uses only the answer WordPress already gets when it checks for plugin updates (stored by WordPress, read here
 * after each new check), so the plugin makes no requests of its own:
 *
 * - "old": the plugin is on WordPress.org, but was not tested with the last 3 major WordPress releases (the same
 *   rule WordPress.org uses for its own warning on the plugin page);
 * - "gone": WordPress asked about the plugin, and WordPress.org did not answer for it. Either the plugin was closed
 *   there (often for a security problem), or it never came from there (premium, custom). Plugins whose Update URI
 *   points somewhere else are left out: they get their updates from that place.
 */
class Old_Plugins extends Feature {

	/**
	 * Option with the plugins found last time: plugin file => old, gone, or gone-once (missing from one update check
	 * only, not e-mailed yet; see check()). Signed (Feature::save_list()). The card does not read it: it shows
	 * found(), so a plugin shows there after the first check and is e-mailed after the second.
	 */
	const LIST_OPTION = 'helloadmin_guard_old_plugins';

	/**
	 * Major releases a plugin may lag behind before it counts as old.
	 */
	const BEHIND = 3;

	/**
	 * Option with the time of the last regular check (Feature::watch()).
	 */
	const CHECKED_OPTION = 'helloadmin_guard_old_plugins_checked';

	/**
	 * Option with the time of the WordPress update check the list was last compared with.
	 */
	const SEEN_OPTION = 'helloadmin_guard_old_plugins_seen';

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
		return __( 'A plugin nobody maintains gets no fixes when a hole is found in it. Twice a day WordPress asks WordPress.org about updates for your plugins. From that same answer this setting finds plugins that were not tested with the last 3 major WordPress releases, and plugins WordPress.org no longer answers for: they were closed there, often because of a security problem, or they came from somewhere else. They are listed on this card. When you turn this on, the administration address (Settings → General) gets one e-mail with the plugins found right now, and later an e-mail each time a new one shows up. The plugin makes no requests of its own for this.', 'helloadmin-guard' );
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
	 * Adds the regular check (after every login, on admin pages, at least once an hour while the site gets visits).
	 *
	 * @param Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( Settings $settings ) {
		$this->watch( array( $this, 'check' ), self::CHECKED_OPTION );
	}

	/**
	 * Sends one e-mail with what is found right now (also to the extra address, and it shows that the alerts
	 * arrive), then remembers it, so later e-mails name only plugins that show up after this.
	 *
	 * @return void
	 */
	public function switched_on() {
		$found = self::found();
		self::save_list( self::LIST_OPTION, null === $found ? array() : self::kinds( $found ) );
		// Also when nothing is found: the admin learns that the check works and that the e-mails arrive. Without
		// update data yet there is nothing to say; the regular check e-mails the findings later.
		if ( null !== $found ) {
			$this->send_alert( $found, true );
		}
	}

	/**
	 * Compares the plugins found now with the stored list and e-mails about the new ones.
	 *
	 * @return void
	 */
	public function check() {
		// Only when WordPress has asked WordPress.org again since the last comparison.
		$data = get_site_transient( 'update_plugins' );
		$when = is_object( $data ) && isset( $data->last_checked ) ? (int) $data->last_checked : 0;
		if ( 0 === $when || (int) get_option( self::SEEN_OPTION, 0 ) === $when ) {
			return;
		}
		update_option( self::SEEN_OPTION, $when, false );
		$found = self::found();
		if ( null === $found ) {
			return;
		}
		$now                 = self::kinds( $found );
		list( $known, $bad ) = self::load_list( self::LIST_OPTION );
		// A plugin missing from one answer may be a hiccup (an update filter, a partial answer): it counts as gone
		// when the next check still misses it. Until then it is stored as gone-once and not e-mailed. A list that was
		// changed outside the plugin starts again from what is there, as gone, so nothing is e-mailed for it later.
		foreach ( $now as $file => $kind ) {
			if ( 'gone' === $kind && ! $bad && ( ! isset( $known[ $file ] ) || ! in_array( $known[ $file ], array( 'gone', 'gone-once' ), true ) ) ) {
				$now[ $file ] = 'gone-once';
			}
		}
		if ( $now !== $known || $bad ) {
			self::save_list( self::LIST_OPTION, $now );
		}
		if ( $bad ) {
			// A changed list only means more plugins than usual could be reported; start again from what is there.
			return;
		}
		$new = array();
		foreach ( $now as $file => $kind ) {
			$was = isset( $known[ $file ] ) ? $known[ $file ] : '';
			if ( ( 'old' === $kind && 'old' !== $was ) || ( 'gone' === $kind && 'gone-once' === $was ) ) {
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
	 * @return array<string, array{kind: string, name: string, tested: string, author: string}>|null
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
		// This plugin knows where it comes from: before its WordPress.org listing it would name itself as "gone".
		$self = plugin_basename( HELLOADMIN_GUARD_FILE );
		foreach ( get_plugins() as $file => $info ) {
			if ( $self === $file ) {
				continue;
			}
			$name   = isset( $info['Name'] ) ? (string) $info['Name'] : $file;
			$author = isset( $info['Author'] ) ? trim( wp_strip_all_tags( (string) $info['Author'] ) ) : '';
			if ( isset( $answered[ $file ] ) ) {
				$item   = (array) $answered[ $file ];
				$tested = isset( $item['tested'] ) && is_string( $item['tested'] ) ? $item['tested'] : '';
				if ( '' !== $tested && self::releases_behind( $tested ) >= self::BEHIND ) {
					$found[ $file ] = array(
						'kind'   => 'old',
						'name'   => $name,
						'tested' => $tested,
						'author' => $author,
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
				'author' => $author,
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
	 * BEHIND = 3 is the WordPress.org rule "not tested with the latest 3 major releases": on 7.1 these are 7.1, 7.0
	 * and 6.9, so 6.9 is fine (2) and 6.8 is not (3). Major versions go x.0 to x.9, so major * 10 + minor counts
	 * releases in a row.
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
	 * @param array<string, array{kind: string, name: string, tested: string, author: string}> $found Findings.
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
	 * @param string                                                            $file Plugin file.
	 * @param array{kind: string, name: string, tested: string, author: string} $item Finding.
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
	 * One numbered line of the e-mail list: name, author and why it is listed.
	 *
	 * @param int                                                               $n    Number in the list.
	 * @param string                                                            $file Plugin file.
	 * @param array{kind: string, name: string, tested: string, author: string} $item Finding.
	 * @return string
	 */
	private static function list_line( $n, $file, array $item ) {
		$why = 'old' === $item['kind']
			/* translators: %s: WordPress version. */
			? sprintf( __( 'last tested with WordPress %s', 'helloadmin-guard' ), $item['tested'] )
			: __( 'not on WordPress.org (closed there, or installed from somewhere else)', 'helloadmin-guard' );
		$who = '' !== $item['author'] ? $item['name'] . ', ' . $item['author'] : $item['name'];
		if ( ! is_plugin_active( $file ) ) {
			$who .= ' ' . __( '(inactive)', 'helloadmin-guard' );
		}
		return $n . '. ' . $who . ' – ' . $why;
	}

	/**
	 * Sends the e-mail: once with the current list when the alert is switched on (also when nothing is found), and
	 * later about plugins that showed up in the lists.
	 *
	 * @param array<string, array{kind: string, name: string, tested: string, author: string}> $items Findings.
	 * @param bool                                                                             $first The alert was just switched on.
	 * @return void
	 */
	private function send_alert( array $items, $first = false ) {
		$site = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ) . ' (' . home_url( '/' ) . ')';
		$when = self::mail_time();
		if ( $first ) {
			/* translators: 1: site name and address, 2: tab and setting name, 3: date and time. */
			$lines = array( sprintf( __( 'On the site %1$s, the setting "%2$s" in the HelloAdmin Guard plugin was turned on (%3$s).', 'helloadmin-guard' ), $site, __( 'General', 'helloadmin-guard' ) . ' → ' . $this->title(), $when ), '' );
		} else {
			/* translators: 1: site name and address, 2: date and time. */
			$lines = array( sprintf( __( 'On the site %1$s, WordPress checked for plugin updates (%2$s).', 'helloadmin-guard' ), $site, $when ), '' );
		}
		if ( $items ) {
			$lines[] = __( 'Here are the plugins that look abandoned or are no longer on WordPress.org:', 'helloadmin-guard' );
			$n       = 0;
			foreach ( $items as $file => $item ) {
				$lines[] = self::list_line( ++$n, $file, $item );
			}
		} else {
			$lines[] = __( 'No such plugins were found on this site right now.', 'helloadmin-guard' );
		}
		$lines[] = '';
		if ( $first ) {
			$lines[] = __( 'Later e-mails will name only plugins that show up on the site after this one.', 'helloadmin-guard' );
			$lines[] = '';
		}
		$lines[] = array(
			'bold' => __( 'Important!', 'helloadmin-guard' ),
			'text' => __( 'A plugin that gets no updates keeps every hole found in it. If you still need it, find a maintained plugin that does the same and switch to it; if not, delete it (an inactive plugin\'s files can still be opened directly).', 'helloadmin-guard' ),
		);
		$lines[] = '';
		/* translators: %s: address of the Plugins screen. */
		$lines[] = sprintf( __( 'To check your plugins, open %s or the Plugins menu in the WordPress admin panel.', 'helloadmin-guard' ), admin_url( 'plugins.php' ) );

		$count = count( $items );
		if ( 0 === $count ) {
			$subject = __( 'Abandoned plugins check is on: nothing found', 'helloadmin-guard' );
		} elseif ( 1 === $count ) {
			$one = reset( $items );
			/* translators: %s: plugin name. */
			$subject = sprintf( __( 'Plugin to check: %s', 'helloadmin-guard' ), $one['name'] );
		} else {
			/* translators: %d: number of plugins. */
			$subject = sprintf( _n( '%d plugin to check', '%d plugins to check', $count, 'helloadmin-guard' ), $count );
		}
		$this->send_alert_mail( $subject, $lines );
	}
}
