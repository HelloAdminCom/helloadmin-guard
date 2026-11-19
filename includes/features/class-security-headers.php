<?php
/**
 * Sends the basic security headers on pages, the login page and wp-admin.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Settings;

use HelloAdmin\Guard\Probe;

defined( 'ABSPATH' ) || exit;

/**
 * Four headers that are safe on almost every site. A header that is already set (by another plugin or
 * PHP code) is left alone. HSTS is not here on purpose: browsers remember it for months, so it gets its
 * own setting with its own warning.
 *
 * Permissions-Policy depends on the site: payments, location and camera with microphone are turned off only when
 * the site does not use them. The admin ticks what the site uses; plugins known to need it tick the box by default.
 */
class Security_Headers extends Feature {

	/**
	 * Browser features the site may use: field name => policy directives turned off while the box is not ticked.
	 */
	const USES = array(
		'uses_payments' => array( 'payment' ),
		'uses_location' => array( 'geolocation' ),
		'uses_camera'   => array( 'camera', 'microphone' ),
	);

	/**
	 * Directives that are always off: motion sensors and USB devices, which websites do not need.
	 */
	const ALWAYS_OFF = array( 'accelerometer', 'gyroscope', 'magnetometer', 'usb' );

	/**
	 * Active plugins that use a browser feature: field name => plugin folder => plugin name.
	 * Payment plugins can show Apple Pay or Google Pay buttons; store locators and maps ask for the visitor's
	 * location; video call and recording plugins need the camera and microphone.
	 */
	const KNOWN = array(
		'uses_payments' => array(
			'woocommerce'                => 'WooCommerce',
			'easy-digital-downloads'     => 'Easy Digital Downloads',
			'easy-digital-downloads-pro' => 'Easy Digital Downloads',
			'give'                       => 'GiveWP',
			'stripe'                     => 'WP Simple Pay',
			'wp-simple-pay-pro-3'        => 'WP Simple Pay',
			'memberpress'                => 'MemberPress',
			'paid-memberships-pro'       => 'Paid Memberships Pro',
			'gravityformsstripe'         => 'Gravity Forms Stripe',
			'gravityformsppcp'           => 'Gravity Forms PayPal',
			'wpforms-stripe'             => 'WPForms Stripe',
			'charitable'                 => 'Charitable',
			'restrict-content-pro'       => 'Restrict Content Pro',
			'surecart'                   => 'SureCart',
		),
		'uses_location' => array(
			'wp-store-locator'    => 'WP Store Locator',
			'agile-store-locator' => 'Agile Store Locator',
			'store-locator-le'    => 'Store Locator Plus',
			'wp-google-maps'      => 'WP Go Maps',
			'geodirectory'        => 'GeoDirectory',
			'geo-my-wp'           => 'GEO my WP',
			'directorist'         => 'Directorist',
		),
		'uses_camera'   => array(
			'bigbluebutton'                                => 'BigBlueButton',
			'video-conferencing-with-zoom-api'             => 'Video Conferencing with Zoom',
			'webinar-and-video-conference-with-jitsi-meet' => 'Jitsi Meet',
			'jitsi-meet-wp'                                => 'Jitsi Meet',
			'videowhisper-video-conference-integration'    => 'VideoWhisper',
			'live-streaming'                               => 'VideoWhisper Live Streaming',
		),
	);

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'security_headers';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title() {
		return __( 'Security headers', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function what() {
		return __( 'Adds four headers: X-Content-Type-Options: nosniff (browsers do not guess file types), X-Frame-Options: SAMEORIGIN (other sites cannot show your pages in a frame), Referrer-Policy: strict-origin-when-cross-origin (other sites see only your domain, not full URLs) and Permissions-Policy (your pages and anything embedded in them cannot use motion sensors, USB devices, or the browser features you do not tick below).', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function may_break() {
		return __( 'If your pages are shown inside a frame on another domain, for example in a landing page builder or on a partner site, they stop opening there. If you leave a box below unticked but the site uses it, that part stops working: Apple Pay and Google Pay buttons, "find stores near me", video calls. Tilting the phone to look around 360° videos stops working. The editor and the Customizer are not affected.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function undo() {
		return __( 'Turn this off and save. Browsers drop these headers on the next page load. If the server or another plugin also sends them, those copies stay.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
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
		$answer = Probe::request( '/' );
		if ( null === $answer ) {
			return null;
		}
		foreach ( array_keys( $this->headers() ) as $name ) {
			if ( ! isset( $answer['headers'][ strtolower( $name ) ] ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * The same at the server level, before PHP starts (also for static files).
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		$lines = array( '<IfModule mod_headers.c>' );
		foreach ( $this->headers() as $name => $value ) {
			$lines[] = 'Header set ' . $name . ' "' . $value . '"';
		}
		$lines[] = '</IfModule>';
		return $lines;
	}

	/**
	 * Nginx version, shown to copy.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		$lines = array();
		foreach ( $this->headers() as $name => $value ) {
			$lines[] = 'add_header ' . $name . ' "' . $value . '" always;';
		}
		return $lines;
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
	 * Place on the tab.
	 *
	 * @return int
	 */
	public function priority() {
		return 60;
	}

	/**
	 * {@inheritDoc}
	 */
	public function learn_more() {
		return 'https://helloadmin.com/wordpress-site-from-the-outside-12-checks/#headers';
	}

	/**
	 * Three boxes: what the site uses. Ticked by default when a plugin that needs it is active.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function fields() {
		$labels = array(
			'uses_payments' => __( 'Quick payment buttons (Apple Pay, Google Pay, Link)', 'helloadmin-guard' ),
			'uses_location' => __( 'Visitor location (store locator, "near me" search, map with "my location")', 'helloadmin-guard' ),
			'uses_camera'   => __( 'Camera and microphone (video calls, recording in the browser)', 'helloadmin-guard' ),
		);
		$fields = array();
		foreach ( $labels as $name => $label ) {
			$found = self::detected( $name );
			$hint  = '';
			if ( $found ) {
				/* translators: %s: plugin names. */
				$hint = sprintf( __( 'Found: %s.', 'helloadmin-guard' ), implode( ', ', $found ) );
			}
			$fields[ $name ] = array(
				'label'       => $label,
				'type'        => 'checkbox',
				'default'     => $found ? '1' : '0',
				'description' => $hint,
			);
		}
		$fields['uses_payments']['group']      = __( 'What does your site use?', 'helloadmin-guard' );
		$fields['uses_payments']['group_hint'] = __( 'Permissions-Policy turns off what is not ticked. Boxes for plugins found on this site are ticked by default.', 'helloadmin-guard' );
		return $fields;
	}

	/**
	 * A plugin needs a feature the admin left unticked.
	 *
	 * @return string[]
	 */
	public function warnings() {
		$warnings = array();
		foreach ( array_keys( self::USES ) as $field ) {
			$found = self::detected( $field );
			if ( $found && '1' !== $this->value( $field ) ) {
				/* translators: %s: plugin names. */
				$warnings[] = sprintf( __( '%s may need a feature you left unticked below. Tick it if that part of the site stops working.', 'helloadmin-guard' ), implode( ', ', $found ) );
			}
		}
		return $warnings;
	}

	/**
	 * A box is either ticked or not.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	public function sanitize_field( $field, $value ) {
		unset( $field );
		return empty( $value ) ? '0' : '1';
	}

	/**
	 * Names of active plugins that need a browser feature.
	 *
	 * @param string $field Field name from USES.
	 * @return string[]
	 */
	public static function detected( $field ) {
		if ( ! isset( self::KNOWN[ $field ] ) ) {
			return array();
		}
		$active = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$found = array();
		foreach ( $active as $file ) {
			$folder = strtok( (string) $file, '/' );
			if ( isset( self::KNOWN[ $field ][ $folder ] ) ) {
				$found[] = self::KNOWN[ $field ][ $folder ];
			}
		}
		return array_values( array_unique( $found ) );
	}

	/**
	 * Permissions-Policy value: what the site does not use is turned off. What it uses keeps the browser default.
	 *
	 * @return string
	 */
	public function permissions_policy() {
		$off = self::ALWAYS_OFF;
		foreach ( self::USES as $field => $directives ) {
			if ( '1' !== $this->value( $field ) ) {
				$off = array_merge( $off, $directives );
			}
		}
		sort( $off );
		return implode(
			', ',
			array_map(
				static function ( $directive ) {
					return $directive . '=()';
				},
				$off
			)
		);
	}

	/**
	 * Headers to send: name => value.
	 *
	 * @return array<string, string>
	 */
	public function headers() {
		return array(
			'X-Content-Type-Options' => 'nosniff',
			'X-Frame-Options'        => 'SAMEORIGIN',
			'Referrer-Policy'        => 'strict-origin-when-cross-origin',
			'Permissions-Policy'     => $this->permissions_policy(),
		);
	}

	/**
	 * Hooks the headers into pages, the login page and wp-admin.
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
	 * Sends each header unless it is already in the response.
	 *
	 * @return void
	 */
	public function send() {
		if ( headers_sent() ) {
			return;
		}
		$present = array();
		foreach ( headers_list() as $line ) {
			$present[] = strtolower( trim( strstr( $line, ':', true ) ) );
		}
		// The same headers in .htaccess: Apache with PHP-FPM would send both copies.
		if ( \HelloAdmin\Guard\Htaccess::written( $this->id() ) ) {
			return;
		}
		foreach ( $this->headers() as $name => $value ) {
			if ( ! in_array( strtolower( $name ), $present, true ) ) {
				header( $name . ': ' . $value );
			}
		}
	}
}
