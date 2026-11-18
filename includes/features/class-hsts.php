<?php
/**
 * Setting: Strict-Transport-Security header.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Tells browsers to open the site only over HTTPS for a year. Separate from the other headers because it is hard
 * to take back: browsers remember it.
 */
class Hsts extends Feature {

	const VALUE = 'max-age=31536000';

	/**
	 * Setting key.
	 *
	 * @return string
	 */
	public function id() {
		return 'hsts';
	}

	/**
	 * Title next to the toggle.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'HTTPS only (HSTS)', 'helloadmin-guard' );
	}

	/**
	 * What it does.
	 *
	 * @return string
	 */
	public function what() {
		return __( 'Adds the Strict-Transport-Security header: after the first visit, browsers open your site only over HTTPS for one year, even if someone types http:// or a public Wi-Fi tries to send them to the plain version. Sent only when the page itself is loaded over HTTPS. Subdomains are not affected.', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'If the SSL certificate expires or HTTPS stops working, visitors who have been to the site cannot open it at all, there is no "continue anyway" button. Turn this on only when HTTPS works on every page and the certificate renews by itself.', 'helloadmin-guard' );
	}

	/**
	 * How to undo.
	 *
	 * @return string
	 */
	public function undo() {
		return __( 'Turn this off and save: the header is no longer sent. Browsers that already got it keep using HTTPS until the year runs out, this cannot be taken back from the site.', 'helloadmin-guard' );
	}

	/**
	 * Risky enough for the Advanced tab.
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
		return 'headers';
	}

	/**
	 * Article with details.
	 *
	 * @return string
	 */
	public function learn_more() {
		return 'https://helloadmin.com/wordpress-site-from-the-outside-12-checks/#https';
	}

	/**
	 * Sends the header on pages, the login page and wp-admin.
	 *
	 * @param Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( Settings $settings ) {
		add_action( 'send_headers', array( $this, 'send' ) );
		add_action( 'login_init', array( $this, 'send' ) );
		add_action( 'admin_init', array( $this, 'send' ) );
	}

	/**
	 * Adds the header over HTTPS, unless the server or another plugin already set it.
	 *
	 * @return void
	 */
	public function send() {
		if ( ! is_ssl() || headers_sent() ) {
			return;
		}
		foreach ( headers_list() as $line ) {
			if ( 0 === stripos( $line, 'strict-transport-security:' ) ) {
				return;
			}
		}
		header( 'Strict-Transport-Security: ' . self::VALUE );
	}
}
