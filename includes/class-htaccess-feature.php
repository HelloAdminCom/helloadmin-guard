<?php
/**
 * Base class for a setting that works through server rules (.htaccess on Apache and LiteSpeed).
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Nothing runs on page load: the rules are written into .htaccess when the settings are saved (see Htaccess::sync()).
 * The server then refuses the request before WordPress starts, which is cheaper and covers files PHP never sees.
 */
abstract class Htaccess_Feature extends Feature {

	/**
	 * Apache rules, one line per item, without the BEGIN/END markers.
	 *
	 * @return string[]
	 */
	abstract public function apache_rules();

	/**
	 * The same rules for Nginx, shown to copy into the server block.
	 *
	 * @return string[]
	 */
	abstract public function nginx_rules();

	/**
	 * Server rules live on the Advanced tab.
	 *
	 * @return string
	 */
	public function tab() {
		return 'advanced';
	}

	/**
	 * Group on the tab.
	 *
	 * @return string
	 */
	public function group() {
		return 'files';
	}

	/**
	 * Post on helloadmin.com that explains every server rule, one section per card.
	 */
	const ARTICLE = 'https://helloadmin.com/wordpress-files-bots-look-for-server-rules/';

	/**
	 * Section of the article for this card, without "#".
	 *
	 * @return string
	 */
	abstract public function anchor();

	/**
	 * Every server rule card links to its own section of the article.
	 *
	 * @return string
	 */
	public function learn_more() {
		return self::ARTICLE . '#' . $this->anchor();
	}

	/**
	 * Common undo text for all server rules.
	 *
	 * @return string
	 */
	public function undo() {
		return __( 'Turn this off and save: the plugin removes its lines from .htaccess. If the site does not open at all, delete everything between "# BEGIN HelloAdmin Guard" and "# END HelloAdmin Guard" in the .htaccess file over FTP or in the hosting file manager.', 'helloadmin-guard' );
	}

	/**
	 * Nothing to hook: the server applies the rules.
	 *
	 * @param Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( Settings $settings ) {
		unset( $settings );
	}

	/**
	 * Path of a URL relative to the site root, plain, with a trailing slash, for example "wp-includes/".
	 *
	 * @param string $url Full URL inside the site.
	 * @return string
	 */
	protected static function rel_path( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '' !== $home && 0 === strpos( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}
		return trailingslashit( trim( $path, '/' ) );
	}

	/**
	 * Path of a URL relative to the site root, as a regex fragment, for example "wp-content/uploads".
	 *
	 * @param string $url Full URL inside the site.
	 * @return string
	 */
	protected static function rel( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '' !== $home && 0 === strpos( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}
		// preg_quote() also escapes dashes; servers do not need that and the rules read better without.
		return str_replace( '\\-', '-', preg_quote( trim( $path, '/' ), '#' ) );
	}
}
