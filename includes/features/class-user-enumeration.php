<?php
/**
 * Stops visitors from listing user names: ?author=N, the REST users endpoint, oEmbed author links and the users sitemap.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Settings;

use HelloAdmin\Guard\Probe;

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
		return __( 'Visitors who are not logged in can no longer get user names from /?author=1, the REST API user list (/wp-json/wp/v2/users), oEmbed author links and the users sitemap. The login form answers "Wrong username or password" for both mistakes, and "Lost your password?" shows the same answer whether the user exists or not.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function may_break() {
		return __( 'Apps or scripts that read the user list through the REST API without logging in. Author pages drop out of wp-sitemap.xml; the pages themselves keep working. Someone who mistyped the user name is no longer told which part was wrong.', 'helloadmin-guard' );
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
	 * Is the gap closed now, by anyone?
	 *
	 * @return bool|null
	 */
	public function probe() {
		$author = Probe::request( '/?author=' . self::admin_id() );
		$users  = Probe::request( '/?rest_route=/wp/v2/users' );
		if ( null === $author || null === $users ) {
			return null;
		}
		$location   = isset( $author['headers']['location'] ) ? $author['headers']['location'] : '';
		$author_ok  = ! ( in_array( $author['code'], array( 301, 302 ), true ) && false !== strpos( $location, '/author/' ) );
		$list       = json_decode( $users['body'], true );
		$users_open = 200 === $users['code'] && is_array( $list ) && isset( $list[0]['slug'] );
		return $author_ok && ! $users_open;
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
		return 40;
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
		add_filter( 'authenticate', array( $this, 'same_login_error' ), 100 );
		add_action( 'lostpassword_post', array( $this, 'same_lost_password_answer' ), 10, 2 );
	}

	/**
	 * One answer for a wrong user name and a wrong password, so the login form does not confirm that a user exists.
	 * Other errors (empty fields, lockouts) stay as they are.
	 *
	 * @param mixed $user Result of the earlier authentication filters.
	 * @return mixed
	 */
	public function same_login_error( $user ) {
		if ( is_wp_error( $user ) && array_intersect( $user->get_error_codes(), array( 'invalid_username', 'invalid_email', 'incorrect_password' ) ) ) {
			return new \WP_Error( 'invalid_credentials', __( '<strong>Error:</strong> Wrong username or password.', 'helloadmin-guard' ) );
		}
		return $user;
	}

	/**
	 * "Lost your password?" with an unknown user: show the same "check your email" page as for a real one.
	 *
	 * @param \WP_Error      $errors    Errors so far.
	 * @param \WP_User|false $user_data User found by the entered name, or false.
	 * @return void
	 */
	public function same_lost_password_answer( $errors, $user_data ) {
		global $pagenow;
		if ( $user_data || 'wp-login.php' !== $pagenow || ! $errors instanceof \WP_Error ) {
			return;
		}
		// An empty field keeps its own error; only "no such user" is hidden.
		$code = $errors->get_error_code();
		if ( '' !== $code && 'invalid_email' !== $code ) {
			return;
		}
		wp_safe_redirect( add_query_arg( 'checkemail', 'confirm', wp_login_url() ) );
		exit;
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
