<?php
/**
 * Setting: the core REST API routes answer only logged-in users.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Probe;
use HelloAdmin\Guard\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * By default anyone can read /wp-json/wp/v2/: posts, pages, categories, search, and /wp/v2/media with the address
 * of every uploaded file, also files that no page links to. A normal site needs none of this for its visitors:
 * the block editor and the admin area are used logged in.
 *
 * Only the core namespaces are closed. oEmbed (embedding your posts on other sites) and the routes of plugins
 * (forms, shops, statistics) stay open, because their visitors are often not logged in. Each request of a batch
 * call (/batch/v1) is checked on its own. Requests that a plugin runs from PHP on a normal page (rest_do_request)
 * are not touched.
 */
class Rest_For_Users extends Feature {

	/**
	 * Core namespaces closed for visitors who are not logged in.
	 */
	const CORE = array( 'wp/v2', 'wp-site-health/v1', 'wp-block-editor/v1', 'wp-abilities/v1' );

	/**
	 * Setting key.
	 *
	 * @return string
	 */
	public function id() {
		return 'rest_for_users';
	}

	/**
	 * Title next to the toggle.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Core REST API only for logged-in users', 'helloadmin-guard' );
	}

	/**
	 * What it does.
	 *
	 * @return string
	 */
	public function what() {
		return __( 'Visitors who are not logged in get "You are not currently logged in" from the core REST API routes (/wp-json/wp/v2/...). Without this anyone can read the list of posts, pages and categories through the API, and /wp-json/wp/v2/media lists the address of every uploaded file, also files that no page links to, like a PDF you uploaded but never published. Fewer open routes also means fewer places where the next core bug can be used. oEmbed and the routes of plugins stay open: forms, shops and statistics keep working. Logged-in users, the block editor and application passwords are not affected.', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'A headless front end (Next.js, Gatsby, a mobile app) that reads posts through the API without logging in stops getting them, and so does a theme or plugin that loads posts in the visitor\'s browser through /wp/v2/ ("load more" buttons, live search, post sliders). If something on the site stops loading for visitors, add its route below. Plugin routes are not closed, so a plugin with its own security hole is not covered by this.', 'helloadmin-guard' );
	}

	/**
	 * How to undo.
	 *
	 * @return string
	 */
	public function undo() {
		return __( 'Turn this off and save. The routes answer visitors again at once.', 'helloadmin-guard' );
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
	 * Risk while off.
	 *
	 * @return string
	 */
	public function risk() {
		return 'low';
	}

	/**
	 * Place on the tab: right after the REST index.
	 *
	 * @return int
	 */
	public function priority() {
		return 66;
	}

	/**
	 * Routes to keep open.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function fields() {
		return array(
			'keep_open' => array(
				'label'       => __( 'Keep open for visitors', 'helloadmin-guard' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'Core routes your visitors need, separated by commas, for example: /wp/v2/posts, /wp/v2/search. A route also opens everything under it (/wp/v2/posts opens /wp/v2/posts/123). Usually empty.', 'helloadmin-guard' ),
			),
		);
	}

	/**
	 * Keeps a clean list of routes: lowercase, each starting with a slash, letters, numbers and / _ - . only.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	public function sanitize_field( $field, $value ) {
		unset( $field );
		return implode( ', ', self::routes( is_scalar( $value ) ? (string) $value : '' ) );
	}

	/**
	 * Parses the list of routes.
	 *
	 * @param string $value Comma or line separated routes.
	 * @return string[]
	 */
	private static function routes( $value ) {
		$routes = array();
		foreach ( preg_split( '/[\s,]+/', strtolower( $value ) ) as $route ) {
			$route = '/' . trim( (string) $route, '/' );
			if ( '/' !== $route && preg_match( '#^/[a-z0-9/_.-]+$#', $route ) ) {
				$routes[] = $route;
			}
		}
		return array_values( array_unique( $routes ) );
	}

	/**
	 * Is the list of posts closed for visitors?
	 *
	 * @return bool|null
	 */
	public function probe() {
		$answer = Probe::request( '/?rest_route=/wp/v2/media' );
		if ( null === $answer ) {
			return null;
		}
		return in_array( $answer['code'], array( 401, 403, 404 ), true );
	}

	/**
	 * Adds the filter.
	 *
	 * @param Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( Settings $settings ) {
		add_filter( 'rest_pre_dispatch', array( $this, 'refuse' ), 10, 3 );
	}

	/**
	 * Refuses a core route to a visitor who is not logged in. Only requests that came over HTTP: the request
	 * itself or one item of a batch call. Internal calls (rest_do_request from PHP) pass.
	 *
	 * @param mixed            $result  Response to send instead of the real one, or null.
	 * @param \WP_REST_Server  $server  REST server.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function refuse( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result || ! $request instanceof \WP_REST_Request || is_user_logged_in() || ! self::from_http( $request ) ) {
			return $result;
		}
		// Letter case does not matter to the REST server: /WP/V2/posts reaches the same route.
		$route = strtolower( '/' . ltrim( $request->get_route(), '/' ) );
		foreach ( self::routes( $this->saved_value( 'keep_open', '' ) ) as $open ) {
			if ( $route === $open || 0 === strpos( $route, $open . '/' ) ) {
				return $result;
			}
		}
		foreach ( self::CORE as $namespace ) {
			if ( '/' . $namespace === $route || 0 === strpos( $route, '/' . $namespace . '/' ) ) {
				// A response, not a WP_Error: see User_Enumeration::block_rest_users() (batch calls in WordPress 7).
				return rest_convert_error_to_response(
					new \WP_Error(
						'rest_not_logged_in',
						translate( 'You are not currently logged in.', 'default' ), // phpcs:ignore WordPress.WP.I18n -- the core message in the site language.
						array( 'status' => rest_authorization_required_code() )
					)
				);
			}
		}
		return $result;
	}

	/**
	 * Whether the request came over HTTP: the route of this REST request, or any item of a batch call.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool
	 */
	private static function from_http( $request ) {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return false;
		}
		global $wp;
		$outer = isset( $wp->query_vars['rest_route'] ) ? strtolower( '/' . ltrim( (string) $wp->query_vars['rest_route'], '/' ) ) : '';
		$outer = untrailingslashit( $outer );
		if ( '/batch/v1' === $outer ) {
			return true;
		}
		return untrailingslashit( strtolower( '/' . ltrim( $request->get_route(), '/' ) ) ) === $outer;
	}
}
