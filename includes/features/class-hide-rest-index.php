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
	 * Set when this request's namespace root was answered with 404.
	 *
	 * @var bool
	 */
	private $hidden = false;

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
		return __( 'For visitors who are not logged in, /wp-json/ no longer lists the namespaces and routes of the REST API. That list names most installed plugins (yoast/v1, wc/v3, contact-form-7/v1). Namespace roots like /wp-json/yoast/v1 answer 404, the same as for a plugin that is not installed, so a bot cannot test plugin names one by one. The Link header and the <link> tag that point to the API are removed too. The API itself keeps working. A bot that knows an exact route of a plugin still gets its answer, and plugin names stay visible in the page source (/wp-content/plugins/...): this removes the easy list, not the plugins. What really matters is the plugin version, see "Hide files that show versions".', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'If you use tools that discover the API through the /wp-json/ root or the Link header without logging in, for example some headless front ends or API explorers, they stop working. Forms, shops, the block editor and the mobile app use the routes directly and keep working.', 'helloadmin-guard' );
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
		add_filter( 'rest_namespace_index', array( $this, 'hide_namespace' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'drop_allow_header' ), 20 );
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
	}

	/**
	 * /wp-json/yoast/v1 and other namespace roots answer 404 to visitors, the same as for a plugin that is not there,
	 * so a bot cannot test a list of plugin names one by one. Routes inside the namespace keep working.
	 *
	 * @param \WP_REST_Response $response Namespace index response.
	 * @return \WP_REST_Response
	 */
	public function hide_namespace( $response ) {
		if ( is_user_logged_in() || ! $response instanceof \WP_REST_Response ) {
			return $response;
		}
		// Exactly the answer WordPress gives for a namespace that does not exist, in the site language.
		$error        = new \WP_Error(
			'rest_no_route',
			translate( 'No route was found matching the URL and request method.', 'default' ), // phpcs:ignore WordPress.WP.I18n -- the core message, so the answer cannot be told apart from a missing namespace.
			array( 'status' => 404 )
		);
		$this->hidden = true;
		return rest_convert_error_to_response( $error );
	}

	/**
	 * WordPress adds "Allow: GET" for a namespace that exists; a missing one has no such header.
	 *
	 * @param mixed $response Response about to be sent.
	 * @return mixed
	 */
	public function drop_allow_header( $response ) {
		if ( $this->hidden && $response instanceof \WP_REST_Response ) {
			$headers = $response->get_headers();
			unset( $headers['Allow'] );
			$response->set_headers( $headers );
		}
		return $response;
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
