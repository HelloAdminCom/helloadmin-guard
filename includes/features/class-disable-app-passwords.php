<?php
/**
 * Turns off Application Passwords, the second set of passwords for the REST API and XML-RPC.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Application Passwords work without two-factor login and without the login page. If nothing uses them,
 * they are one more way in. Existing passwords are not deleted: they stop working and come back when
 * the setting is turned off.
 */
class Disable_App_Passwords extends Feature {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'disable_app_passwords';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title() {
		return __( 'Turn off Application Passwords', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function what() {
		return __( 'Application Passwords let apps log in to the REST API without the login page and without two-factor login. This turns them off for all users and hides the section in user profiles. Passwords that already exist are kept, they just stop working.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function may_break() {
		return __( 'The WordPress mobile app on sites without Jetpack, Zapier, Make and other services connected with an application password, and your own scripts that publish through the REST API.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function undo() {
		return __( 'Turn this off and save. The existing application passwords work again; nobody needs to create new ones.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function group() {
		return 'login';
	}

	/**
	 * What closes the gap, when known.
	 *
	 * @return string
	 */
	public function covered_by() {
		return wp_is_application_passwords_supported() ? '' : __( 'WordPress itself (Application Passwords need HTTPS)', 'helloadmin-guard' );
	}

	/**
	 * Is the gap closed now, by anyone?
	 *
	 * @return bool|null
	 */
	public function probe() {
		return ! wp_is_application_passwords_available();
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
		return 50;
	}

	/**
	 * {@inheritDoc}
	 */
	public function learn_more() {
		return 'https://helloadmin.com/wordpress-application-passwords-for-secure-rest-api-authentication/';
	}

	/**
	 * Adds the filter.
	 *
	 * @param Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( Settings $settings ) {
		add_filter( 'wp_is_application_passwords_available', '__return_false' );
	}
}
