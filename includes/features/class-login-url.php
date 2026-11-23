<?php
/**
 * Custom login URL: the login page moves to /your-slug/, wp-login.php and wp-admin answer 404 to guests.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Settings;

use HelloAdmin\Guard\Probe;

defined( 'ABSPATH' ) || exit;

/**
 * The core login page is served at a custom address. Nothing is copied or renamed: the request for the
 * custom slug runs the current wp-login.php, so the page stays up to date after WordPress updates.
 */
class Login_Url extends Feature {

	/**
	 * Slugs that can never be the login address.
	 */
	const RESERVED = array( 'wp-admin', 'wp-login', 'wp-login-php', 'login', 'admin', 'dashboard', 'wp-content', 'wp-includes', 'wp-json', 'feed', 'xmlrpc', 'xmlrpc-php' );

	/**
	 * Actions of wp-login.php that keep working at the old address, because WordPress posts to them from
	 * the front end: the password form of protected posts and the personal data request confirmation.
	 */
	const OPEN_ACTIONS = array( 'postpass', 'confirmaction' );

	/**
	 * Login slug while the feature is on.
	 *
	 * @var string
	 */
	private $slug = '';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'login_url';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title() {
		return __( 'Custom login URL', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function what() {
		return __( 'Moves the login page to your own address. /wp-login.php and /wp-admin/ show a 404 page to visitors who are not logged in, so bots stop finding the login form.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function may_break() {
		return __( 'Bookmarks and saved passwords for the old login page stop working. If a plugin or app logs in through /wp-login.php directly, or another plugin also changes the login URL, it stops working. Visitors who are not logged in never get the new address: "Log in" links in the theme or widgets lead them to a 404 page. Only when anyone can register on the site does the registration link show it.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function undo() {
		return __( 'Turn this off and save. If you forgot the new address and cannot log in, rename the folder wp-content/plugins/helloadmin-guard over FTP or in the hosting file manager, then open .htaccess in the site root and delete everything between "# BEGIN HelloAdmin Guard" and "# END HelloAdmin Guard": that block also closes the login form on /wp-login.php. After that /wp-login.php works again.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function group() {
		return 'login';
	}

	/**
	 * Is the gap closed now, by anyone?
	 *
	 * @return bool|null
	 */
	public function probe() {
		$answer = Probe::request( '/wp-login.php' );
		if ( null === $answer ) {
			return null;
		}
		return ! ( 200 === $answer['code'] && false !== strpos( $answer['body'], 'user_login' ) );
	}

	/**
	 * The same at the server level, before PHP starts (also for static files).
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		// Built from the saved addresses, not site_url(): while this setting is on, site_url( 'wp-login.php' ) already
		// returns the custom address, and the rule would refuse logins there instead of on wp-login.php.
		$site = trailingslashit( (string) wp_parse_url( (string) get_option( 'siteurl' ), PHP_URL_PATH ) );
		$home = trailingslashit( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_PATH ) );
		$path = $site . 'wp-login.php';
		if ( 0 === strpos( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}
		$login = preg_quote( ltrim( $path, '/' ), '#' );
		return array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteCond %{REQUEST_METHOD} POST',
			'RewriteCond %{QUERY_STRING} !(^|&)action=(postpass|confirmaction)(&|$) [NC]',
			'RewriteRule ^' . $login . '$ - [R=404,L]',
			'</IfModule>',
		);
	}

	/**
	 * Nginx version, shown to copy.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		// Nothing to add on Nginx: the plugin answers POST to /wp-login.php with 404 in PHP.
		return array();
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
		return 30;
	}

	/**
	 * {@inheritDoc}
	 */
	public function learn_more() {
		return 'https://helloadmin.com/change-wordpress-login-url-without-plugin/';
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		$slug = $this->stored_slug();
		$slug = $slug ? $slug : 'login-' . strtolower( wp_generate_password( 6, false ) );
		return array(
			'slug' => array(
				'label'       => __( 'Login address', 'helloadmin-guard' ),
				'type'        => 'text',
				'default'     => $slug,
				'description' => sprintf(
					/* translators: %s: login page URL. */
					__( 'Letters, numbers and dashes, at least 4 characters. Your login page: %s. Bookmark it after saving.', 'helloadmin-guard' ),
					home_url( '/' . $slug . '/' )
				),
			),
		);
	}

	/**
	 * Keeps the slug safe: lowercase letters, numbers and dashes, not reserved, not an existing page.
	 * An invalid value keeps the previous one and shows an error on the settings page.
	 *
	 * @param string $field Field name.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	public function sanitize_field( $field, $value ) {
		$previous = $this->stored_slug();
		$slug     = sanitize_title( is_scalar( $value ) ? (string) $value : '' );

		$error = '';
		if ( strlen( $slug ) < 4 ) {
			$error = __( 'The login address must be at least 4 characters long.', 'helloadmin-guard' );
		} elseif ( in_array( $slug, self::RESERVED, true ) ) {
			$error = __( 'This login address is reserved by WordPress or too easy to guess. Choose another one.', 'helloadmin-guard' );
		} elseif ( get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) ) ) {
			$error = __( 'A page or post already uses this address. Choose another one.', 'helloadmin-guard' );
		}

		if ( $error ) {
			// add_settings_error() exists only in wp-admin; the option can also be saved from code or WP-CLI.
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error( Settings::OPTION, 'helloadmin_guard_login_slug', $error );
			}
			return $previous;
		}
		return $slug;
	}

	/**
	 * Adds the URL filters and the request router.
	 *
	 * @param Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( Settings $settings ) {
		$this->slug = $settings->field( $this->id(), 'slug' );
		if ( '' === $this->slug || is_multisite() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return;
		}

		foreach ( array( 'site_url', 'network_site_url', 'login_url', 'logout_url', 'lostpassword_url', 'register_url', 'wp_redirect' ) as $hook ) {
			add_filter( $hook, array( $this, 'filter_url' ) );
		}

		// WordPress sends /login, /admin and /dashboard to the login page; that would show the new address.
		remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );

		add_action( 'wp_loaded', array( $this, 'route' ), 1 );
	}

	/**
	 * Serves the login page at the custom address and closes the default ones for guests.
	 *
	 * @return void
	 */
	public function route() {
		global $pagenow;

		if ( $this->is_login_slug_request() ) {
			$this->serve_login();
		}

		if ( 'wp-login.php' === $pagenow && ! $this->is_open_action() ) {
			$this->not_found();
		}

		if ( is_admin() && ! is_user_logged_in() && ! wp_doing_ajax() && 'admin-post.php' !== $pagenow ) {
			$this->not_found();
		}
	}

	/**
	 * Replaces wp-login.php with the custom address in a URL WordPress builds.
	 *
	 * @param mixed $url URL from the filter.
	 * @return mixed
	 */
	public function filter_url( $url ) {
		if ( ! is_string( $url ) || ! $this->is_own_login_url( $url ) ) {
			return $url;
		}
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		parse_str( $query, $args );
		$action = isset( $args['action'] ) && is_string( $args['action'] ) ? $args['action'] : '';
		if ( in_array( $action, self::OPEN_ACTIONS, true ) ) {
			return $url;
		}
		if ( ! $this->may_see_slug( $action ) ) {
			return $url;
		}
		$new = trailingslashit( home_url( '/' . $this->slug ) );
		return $query ? $new . '?' . $query : $new;
	}

	/**
	 * Whether this request may be given the new address. Visitors who are not logged in get it only on the login
	 * page itself (form, lost password, redirects after a login action). Everywhere else their links keep
	 * wp-login.php, which answers 404: otherwise /wp-signup.php, /wp-register.php or a "Log in" link in the theme
	 * would redirect or link any visitor to the new address. When anyone can register, the registration link
	 * has to work, so it shows the address.
	 *
	 * @param string $action Login action of the URL.
	 * @return bool
	 */
	private function may_see_slug( $action ) {
		if ( ! function_exists( 'is_user_logged_in' ) ) {
			return $this->is_login_slug_request(); // Before pluggable.php is loaded: no user yet.
		}
		if ( is_user_logged_in() || $this->is_login_slug_request() ) {
			return true;
		}
		return 'register' === $action && (bool) get_option( 'users_can_register' );
	}

	/**
	 * Whether a URL points to this site's wp-login.php. Only the path counts: "wp-login.php" inside a query
	 * argument (a redirect_to back to the login page) or on another site stays as it is.
	 *
	 * @param string $url URL from a filter.
	 * @return bool
	 */
	private function is_own_login_url( $url ) {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( 'wp-login.php' !== wp_basename( $path ) ) {
			return false;
		}
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( null === $host || false === $host ) {
			return true; // Relative URL: always this site.
		}
		// Raw options: site_url() would run this filter again.
		$hosts = array(
			strtolower( (string) wp_parse_url( (string) get_option( 'siteurl' ), PHP_URL_HOST ) ),
			strtolower( (string) wp_parse_url( (string) get_option( 'home' ), PHP_URL_HOST ) ),
		);
		return in_array( strtolower( (string) $host ), $hosts, true );
	}

	/**
	 * Whether the current request path is the custom login address.
	 *
	 * @return bool
	 */
	private function is_login_slug_request() {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = untrailingslashit( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
		$home = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
		return $path === $home . '/' . $this->slug;
	}

	/**
	 * Whether a direct wp-login.php request is one of the actions that stay open.
	 *
	 * @return bool
	 */
	private function is_open_action() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reads which login action is requested.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		return in_array( $action, self::OPEN_ACTIONS, true );
	}

	/**
	 * Runs the core wp-login.php for the custom address and stops.
	 *
	 * @return void
	 */
	private function serve_login() {
		// wp-login.php and the functions it defines read these as globals.
		global $error, $interim_login, $action, $user_login;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- plugins check $pagenow to detect the login page.
		$GLOBALS['pagenow'] = 'wp-login.php';
		require_once ABSPATH . 'wp-login.php';
		exit;
	}

	/**
	 * Answers with a plain 404 page, like the web server does for a missing file, and stops.
	 *
	 * The theme's 404 template is not used: these requests run in the admin or login context, where
	 * block themes and some classic themes do not render correctly.
	 *
	 * @return void
	 */
	private function not_found() {
		status_header( 404 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!DOCTYPE html><html><head><meta name="robots" content="noindex"><title>404 Not Found</title></head><body><h1>Not Found</h1><p>'
			. esc_html__( 'The requested URL was not found on this server.', 'helloadmin-guard' )
			. '</p></body></html>';
		exit;
	}

	/**
	 * Slug saved in the database, read directly so the field can show it before the feature is on.
	 *
	 * @return string
	 */
	private function stored_slug() {
		$option = get_option( Settings::OPTION, array() );
		return isset( $option['fields'][ $this->id() ]['slug'] ) && is_string( $option['fields'][ $this->id() ]['slug'] ) ? $option['fields'][ $this->id() ]['slug'] : '';
	}
}
