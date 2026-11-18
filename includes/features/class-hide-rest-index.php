<?php
/**
 * Setting: the REST API index does not list routes and namespaces to visitors who are not logged in.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Probe;
use HelloAdmin\Guard\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * /wp-json/ lists every namespace (yoast/v1, wc/v3, contact-form-7/v1...), which is a ready list of installed plugins.
 * The routes themselves keep working: forms, shops and the block editor are not affected.
 */
class Hide_Rest_Index extends Feature {

	/**
	 * Setting key.
	 *
	 * @return string
	 */
	public function id() {
		return 'hide_rest_index';
	}

	/**
	 * Title next to the toggle.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Hide the REST API index', 'helloadmin-guard' );
	}

	/**
	 * What it does.
	 *
	 * @return string
	 */
	public function what() {
		return __( 'For visitors who are not logged in, /wp-json/ no longer lists the namespaces and routes of the REST API. That list names most installed plugins (yoast/v1, wc/v3, contact-form-7/v1). The Link header and the <link> tag that point to the API are removed too. The API itself keeps working.', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'Tools that discover the API by reading the index or the Link header without logging in, for example some headless front ends or API explorers. Forms, shops, the block editor and the mobile app use the routes directly and keep working. Plugin names stay visible in the page source anyway (/wp-content/plugins/...), so this only removes the easy list.', 'helloadmin-guard' );
	}

	/**
	 * How to undo.
	 *
	 * @return string
	 */
	public function undo() {
		return __( 'Turn this off and save. The index and the links come back at once.', 'helloadmin-guard' );
	}

	/**
	 * Topic.
	 *
	 * @return string
	 */
	public function group() {
		return 'leaks';
	}

	/**
	 * Article with details.
	 *
	 * @return string
	 */
	public function learn_more() {
		return 'https://helloadmin.com/wordpress-site-from-the-outside-12-checks/#plugins';
	}

	/**
	 * Is the index hidden from visitors?
	 *
	 * @return bool|null
	 */
	public function probe() {
		$answer = Probe::request( '/?rest_route=/' );
		if ( null === $answer ) {
			return null;
		}
		if ( 200 !== $answer['code'] ) {
			return true;
		}
		$index = json_decode( $answer['body'], true );
		return ! ( is_array( $index ) && ! empty( $index['namespaces'] ) && count( (array) $index['namespaces'] ) > 1 );
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
		return 65;
	}

	/**
	 * Adds the hooks.
	 *
	 * @param Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( Settings $settings ) {
		add_filter( 'rest_index', array( $this, 'trim_index' ) );
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
	}

	/**
	 * Keeps only the site name, URL and description in the index for visitors.
	 *
	 * @param \WP_REST_Response $response Index response.
	 * @return \WP_REST_Response
	 */
	public function trim_index( $response ) {
		if ( is_user_logged_in() || ! $response instanceof \WP_REST_Response ) {
			return $response;
		}
		$data = (array) $response->get_data();
		$response->set_data( array_intersect_key( $data, array_flip( array( 'name', 'description', 'url', 'home' ) ) ) );
		return $response;
	}
}
