<?php
/**
 * Setting: Strict-Transport-Security header.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Settings;

use HelloAdmin\Guard\Probe;

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
	 * Is the gap closed now, by anyone?
	 *
	 * @return bool|null
	 */
	public function probe() {
		if ( 0 !== strpos( home_url(), 'https://' ) ) {
			return null;
		}
		$answer = Probe::request( '/' );
		return null === $answer ? null : isset( $answer['headers']['strict-transport-security'] );
	}

	/**
	 * The same at the server level, before PHP starts (also for static files).
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		return array(
			'<IfModule mod_headers.c>',
			'Header set Strict-Transport-Security "' . self::VALUE . '"',
			'</IfModule>',
		);
	}

	/**
	 * Nginx version, shown to copy.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		return array(
			'add_header Strict-Transport-Security "' . self::VALUE . '" always;',
		);
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
	 * Place on the tab.
	 *
	 * @return int
	 */
	public function priority() {
		return 55;
	}

	/**
	 * Article with details.
	 *
	 * @return string
	 */
	public function learn_more() {
		return \HelloAdmin\Guard\Htaccess_Feature::ARTICLE . '#hsts';
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
		if ( ! is_ssl() || headers_sent() || \HelloAdmin\Guard\Htaccess::written( $this->id() ) ) {
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
