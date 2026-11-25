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
use HelloAdmin\Guard\User_Names;

defined( 'ABSPATH' ) || exit;

/**
 * A bot that tries passwords needs a user name first. WordPress gives it away in several places;
 * this closes them for visitors who are not logged in. Logged-in users keep everything.
 */
class User_Enumeration extends Feature {

	/**
	 * Author pages stay as they are.
	 */
	const AUTHORS_KEEP = 'keep';

	/**
	 * Author pages redirect to the blog page.
	 */
	const AUTHORS_BLOG = 'blog';

	/**
	 * Author pages answer 404.
	 */
	const AUTHORS_404 = '404';

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
		return __( 'Visitors who are not logged in can no longer get user names from /?author=1, the REST API user list (/wp-json/wp/v2/users), oEmbed author names and links, the users sitemap and the author class on comments (comment-author-admin). The login form answers "Wrong username or password" for both mistakes, and "Lost your password?" shows the same answer whether the user exists or not.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function may_break() {
		return __( 'If apps or scripts read the user list through the REST API without logging in, they no longer get it. For example, a headless site on Next.js or Gatsby that takes author names from /wp-json/wp/v2/users, or an "Our authors" block that loads the list right in the visitor\'s browser. Author pages drop out of wp-sitemap.xml but keep working. Someone who mistyped the user name is not told what exactly was wrong.', 'helloadmin-guard' );
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
	 * What visitors get on author pages.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function fields() {
		return array(
			'author_pages' => array(
				'label'       => __( 'Author pages for visitors', 'helloadmin-guard' ),
				'type'        => 'radio',
				'default'     => self::AUTHORS_KEEP,
				'options'     => array(
					self::AUTHORS_KEEP => __( 'Keep them (sites with several authors)', 'helloadmin-guard' ),
					self::AUTHORS_BLOG => __( 'Redirect to the blog page (blogs with one author)', 'helloadmin-guard' ),
					self::AUTHORS_404  => __( 'Answer "not found"', 'helloadmin-guard' ),
				),
				'description' => __( 'With one author, /author/name/ only repeats the blog and shows the author address. With the last two options every /author/.../ address answers the same, whether such an author exists or not, and author links in the theme lead to the blog page. Logged-in users still see author pages.', 'helloadmin-guard' ),
			),
		);
	}

	/**
	 * Keeps only a known choice.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	public function sanitize_field( $field, $value ) {
		unset( $field );
		return in_array( $value, array( self::AUTHORS_BLOG, self::AUTHORS_404 ), true ) ? (string) $value : self::AUTHORS_KEEP;
	}

	/**
	 * Users whose public name or author address shows their login, with a fix button each.
	 *
	 * @return void
	 */
	public function render_extra() {
		User_Names::render();
	}

	/**
	 * Is the gap closed now, by anyone?
	 *
	 * @return bool|null
	 */
	public function probe() {
		$author = Probe::request( '/?author=' . self::admin_id() );
		$users  = Probe::request( '/?rest_route=/wp/v2/users' );
		// WordPress matches REST routes in any letter case, so the same list must be closed as /wp/v2/Users too.
		$mixed = Probe::request( '/?rest_route=/wp/v2/Users' );
		if ( null === $author || null === $users || null === $mixed ) {
			return null;
		}
		$location  = isset( $author['headers']['location'] ) ? $author['headers']['location'] : '';
		$author_ok = ! ( in_array( $author['code'], array( 301, 302 ), true ) && false !== strpos( $location, '/author/' ) );
		return $author_ok && ! self::lists_users( $users ) && ! self::lists_users( $mixed );
	}

	/**
	 * Whether a REST answer is a readable user list.
	 *
	 * @param array{code: int, body: string} $answer Probe answer.
	 * @return bool
	 */
	private static function lists_users( array $answer ) {
		$list = json_decode( $answer['body'], true );
		return 200 === $answer['code'] && is_array( $list ) && isset( $list[0]['slug'] );
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
		add_filter( 'comment_class', array( $this, 'strip_comment_author_class' ), 10, 4 );
		add_filter( 'wp_sitemaps_add_provider', array( $this, 'drop_users_sitemap' ), 10, 2 );
		add_filter( 'authenticate', array( $this, 'same_login_error' ), 100 );
		add_action( 'lostpassword_post', array( $this, 'same_lost_password_answer' ), 10, 2 );
		if ( self::AUTHORS_KEEP !== $this->saved_value( 'author_pages', self::AUTHORS_KEEP ) ) {
			add_action( 'template_redirect', array( $this, 'author_pages' ), 1 );
			add_filter( 'author_link', array( $this, 'blog_url' ) );
		}
	}

	/**
	 * Author pages for visitors: a redirect to the blog page or 404. Every /author/.../ address gets the same
	 * answer, also for an author that does not exist: if only real authors were redirected, the difference
	 * between the redirect and a 404 would tell which author addresses exist.
	 *
	 * @return void
	 */
	public function author_pages() {
		global $wp, $wp_query;
		if ( is_user_logged_in() || ! ( is_author() || ! empty( $wp->query_vars['author_name'] ) ) ) {
			return;
		}
		if ( self::AUTHORS_BLOG === $this->saved_value( 'author_pages', self::AUTHORS_KEEP ) ) {
			wp_safe_redirect( $this->blog_url(), 301 );
			exit;
		}
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * The blog page: the posts page when the front page is a static page, else the home page. As the author_link
	 * filter it replaces author links for visitors only.
	 *
	 * @param string $link Author link when used as a filter.
	 * @return string
	 */
	public function blog_url( $link = '' ) {
		// In the admin area and for logged-in users author links stay real: they still see author pages.
		if ( '' !== $link && ( is_admin() || is_user_logged_in() ) ) {
			return $link;
		}
		$posts = (int) get_option( 'page_for_posts' );
		if ( 'page' === get_option( 'show_on_front' ) && $posts ) {
			$url = get_permalink( $posts );
			if ( $url ) {
				return $url;
			}
		}
		return home_url( '/' );
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
		// Case-insensitive like the REST server itself: /wp/v2/USERS reaches the same endpoint.
		if ( preg_match( '#^/wp/v2/users(/|$)#i', $request->get_route() ) ) {
			// A response, not a WP_Error: in a batch call (/batch/v1) core passes this value on to rest_post_dispatch
			// filters, and one of them in WordPress 7 accepts only WP_REST_Response (a WP_Error there is a fatal error).
			return rest_convert_error_to_response( new \WP_Error( 'rest_user_cannot_view', __( 'Sorry, you are not allowed to list users.', 'helloadmin-guard' ), array( 'status' => rest_authorization_required_code() ) ) );
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
		// The name too: by default it is the login (display name = user name after install).
		unset( $data['author_url'], $data['author_name'] );
		return $data;
	}

	/**
	 * WordPress adds "comment-author-<author page slug>" to comments of registered users, and that slug is the
	 * login by default. The class is removed; "byuser" and "bypostauthor" stay for theme styles.
	 *
	 * @param string[]         $classes    Comment classes.
	 * @param string|string[]  $css_class  Extra classes passed to comment_class().
	 * @param int|string       $comment_id Comment ID.
	 * @param \WP_Comment|null $comment    Comment.
	 * @return string[]
	 */
	public function strip_comment_author_class( $classes, $css_class, $comment_id, $comment ) {
		unset( $css_class, $comment_id );
		if ( ! $comment instanceof \WP_Comment || ! $comment->user_id ) {
			return $classes;
		}
		$user = get_userdata( (int) $comment->user_id );
		if ( ! $user ) {
			return $classes;
		}
		$own = 'comment-author-' . sanitize_html_class( $user->user_nicename, (string) $comment->user_id );
		return array_values( array_diff( (array) $classes, array( $own ) ) );
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
