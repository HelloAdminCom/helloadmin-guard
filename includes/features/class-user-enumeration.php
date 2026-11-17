<?php
/**
 * Stops visitors from listing user names: ?author=N, the REST users endpoint, oEmbed author links and the users sitemap.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * A bot that tries passwords needs a user name first. WordPress gives it away in several places;
 * this closes them for visitors who are not logged in. Logged-in users keep everything.
 */
class User_Enumeration extends Feature {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'user_enumeration';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title() {
		return __( 'Hide user names', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function what() {
		return __( 'Visitors who are not logged in can no longer get user names from /?author=1, the REST API user list (/wp-json/wp/v2/users), oEmbed author links and the users sitemap.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function may_break() {
		return __( 'Apps or scripts that read the user list through the REST API without logging in. Author pages drop out of wp-sitemap.xml; the pages themselves keep working.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function undo() {
		return __( 'Turn this off and save. Nothing is deleted or changed in the database.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function group() {
		return 'leaks';
	}

	/**
	 * {@inheritDoc}
	 */
	public function learn_more() {
		return 'https://helloadmin.com/prevent-wordpress-username-enumeration-attacks/';
	}

	/**
	 * Adds the filters.
	 *
	 * @param Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( Settings $settings ) {
		add_action( 'parse_request', array( $this, 'block_author_query' ), 1 );
		add_filter( 'rest_pre_dispatch', array( $this, 'block_rest_users' ), 10, 3 );
		add_filter( 'oembed_response_data', array( $this, 'strip_oembed_author' ) );
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'drop_users_sitemap' ), 10, 2 );
	}

	/**
	 * Answers ?author=N with a 404 for guests, before WordPress redirects it to /author/user-name/.
	 *
	 * @param \WP $wp Current request.
	 * @return void
	 */
	public function block_author_query( $wp ) {
		if ( is_admin() || is_user_logged_in() || empty( $wp->query_vars['author'] ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only check of the public query string.
		if ( ! isset( $_GET['author'] ) ) {
			return; // Pretty /author/name/ URLs are not touched.
		}
		$wp->query_vars = array( 'error' => '404' );
	}

	/**
	 * Refuses the users endpoints of the REST API to visitors who are not logged in.
	 *
	 * @param mixed            $result  Response to send instead of the real one, or null.
	 * @param \WP_REST_Server  $server  REST server.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function block_rest_users( $result, $server, $request ) {
		unset( $server );
		if ( null !== $result || is_user_logged_in() ) {
			return $result;
		}
		if ( 0 === strpos( $request->get_route(), '/wp/v2/users' ) ) {
			return new \WP_Error( 'rest_user_cannot_view', __( 'Sorry, you are not allowed to list users.', 'helloadmin-guard' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return $result;
	}

	/**
	 * Removes the author link (it contains the user name) from oEmbed data; the display name stays.
	 *
	 * @param array<string, mixed> $data oEmbed response data.
	 * @return array<string, mixed>
	 */
	public function strip_oembed_author( $data ) {
		unset( $data['author_url'] );
		return $data;
	}

	/**
	 * Leaves the users list out of wp-sitemap.xml.
	 *
	 * @param mixed  $provider Sitemap provider.
	 * @param string $name     Provider name.
	 * @return mixed
	 */
	public function drop_users_sitemap( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}
}
