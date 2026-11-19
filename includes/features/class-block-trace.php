<?php
/**
 * Setting: the TRACE and TRACK request methods are refused.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Htaccess_Feature;

use HelloAdmin\Guard\Probe;

defined( 'ABSPATH' ) || exit;

/**
 * TRACE sends the request back to the sender. Browsers no longer allow scripts to send it, so the old cross-site
 * tracing trick is dead, but security scanners still report "TRACE enabled" and clients ask about it.
 */
class Block_Trace extends Htaccess_Feature {

	/**
	 * Setting key.
	 *
	 * @return string
	 */
	public function id() {
		return 'block_trace';
	}

	/**
	 * Title next to the toggle.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Refuse TRACE and TRACK requests', 'helloadmin-guard' );
	}

	/**
	 * What it does.
	 *
	 * @return string
	 */
	public function what() {
		return __( 'The server answers 403 to the TRACE and TRACK request methods. They only echo a request back and are used for debugging; security scanners report them as "TRACE enabled". Browsers do not send them, so visitors see no difference.', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'Nothing on a normal site.', 'helloadmin-guard' );
	}

	/**
	 * Section of the server rules article.
	 *
	 * @return string
	 */
	public function anchor() {
		return 'trace';
	}

	/**
	 * Does the site refuse TRACE, by anyone? A 2xx answer means it is open.
	 *
	 * @return bool|null
	 */
	public function probe() {
		$answer = Probe::request( '/', 'TRACE' );
		if ( null === $answer || 0 === $answer['code'] ) {
			return null;
		}
		return $answer['code'] < 200 || $answer['code'] >= 300;
	}

	/**
	 * Risk while off.
	 *
	 * @return string
	 */
	public function risk() {
		return 'low';
	}

	/**
	 * Place on the tab: last, it is cheap and the risk is small.
	 *
	 * @return int
	 */
	public function priority() {
		return 200;
	}

	/**
	 * Apache rules.
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		return array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteCond %{REQUEST_METHOD} ^(TRACE|TRACK)$ [NC]',
			'RewriteRule ^ - [F]',
			'</IfModule>',
		);
	}

	/**
	 * Nginx refuses TRACE by itself (405); this covers TRACK passed on to PHP.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		return array(
			'if ($request_method ~ ^(TRACE|TRACK)$) { return 405; }',
		);
	}
}
