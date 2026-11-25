<?php
/**
 * Users whose login shows on the site, and the "Fix" button of the Hide user names card.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Hiding the REST user list and ?author=N is not enough when the login is the public name: after install the
 * display name and the author page address (/author/<login>/) both are the login, and themes, feeds, comments
 * and SEO plugins print them. WordPress has a screen for the display name, but not for the author address.
 *
 * The fix sets a new display name, builds the author address from it (the old address answers 404) and puts
 * the new name on the user's old comments, where WordPress stored the name at the time of posting.
 */
class User_Names {

	/**
	 * Form action of the fix button.
	 */
	const ACTION = 'helloadmin_guard_fix_user';

	/**
	 * Most users listed on the card.
	 */
	const LIMIT = 20;

	/**
	 * Query argument with the user ID of the pressed button.
	 */
	const USER_ARG = 'hag_fix_user';

	/**
	 * Query argument with the nonce of the pressed button.
	 */
	const NONCE_ARG = 'hag_fix_nonce';

	/**
	 * Hooks the form handler.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'maybe_handle' ) );
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'ajax' ) );
	}

	/**
	 * Texts and the action for the settings page script, which fixes a user without reloading the page.
	 *
	 * @return array<string, string>
	 */
	public static function script_data() {
		return array(
			'fixAction'  => self::ACTION,
			'fixWorking' => __( 'Fixing…', 'helloadmin-guard' ),
			/* translators: 1: number of fixed users, 2: number of users in the list. */
			'fixCount'   => __( 'Fixed: %1$d of %2$d.', 'helloadmin-guard' ),
			'fixAllDone' => __( 'Done: logins no longer show for the users in this list.', 'helloadmin-guard' ),
			'fixMore'    => __( 'Reload the page to see the next users.', 'helloadmin-guard' ),
			'fixFailed'  => __( 'The request failed. Reload the page and try again.', 'helloadmin-guard' ),
		);
	}

	/**
	 * The fix button without a page reload: same checks as handle(), answers with JSON.
	 *
	 * @return void
	 */
	public function ajax() {
		$id = isset( $_POST['user'] ) ? absint( $_POST['user'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked right below.
		check_ajax_referer( self::ACTION . '_' . $id, 'nonce' );
		if ( ! $id || ! current_user_can( 'edit_user', $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Sorry, you are not allowed to do this.', 'helloadmin-guard' ) ), 403 );
		}
		$name   = isset( $_POST['name'] ) && is_string( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$result = self::fix( $id, $name );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				'message' => $result,
				'url'     => get_author_posts_url( $id ),
			)
		);
	}

	/**
	 * The button sends the whole settings form, which carries its own "action" and "_wpnonce" fields for
	 * options.php. Those would override the same names in the button address (POST wins in $_REQUEST), so the
	 * button posts to the plugin page with its own argument names and is handled here.
	 *
	 * @return void
	 */
	public function maybe_handle() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only routes the request, handle() checks the nonce.
		if ( isset( $_GET[ self::USER_ARG ], $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$this->handle();
		}
	}

	/**
	 * Users whose login shows: by the display name or the author page address. Only users whose name appears
	 * somewhere public (published posts, approved comments), and new users who can write posts (added in the
	 * last 7 days: their first post will show the login). Customers of a shop who never post are left out.
	 *
	 * @return array{users: array<int, array{id: int, login: string, name: bool, slug: bool, fresh: bool, proposal: string}>, total: int}
	 */
	public static function leaks() {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- small aggregate queries for the settings page only.
		$authors   = $wpdb->get_col( "SELECT DISTINCT post_author FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_author > 0" );
		$commenter = $wpdb->get_col( "SELECT DISTINCT user_id FROM {$wpdb->comments} WHERE comment_approved = '1' AND user_id > 0" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
		$public = array_map( 'intval', array_merge( $authors, $commenter ) );

		$fresh = get_users(
			array(
				'capability' => 'edit_posts',
				'fields'     => 'ID',
				'number'     => 100,
				'date_query' => array( array( 'after' => '7 days ago' ) ),
			)
		);
		$fresh = array_map( 'intval', $fresh );
		$ids   = array_values( array_unique( array_merge( $public, $fresh ) ) );

		$found = array();
		foreach ( array_chunk( $ids, 200 ) as $chunk ) {
			foreach ( get_users( array( 'include' => $chunk ) ) as $user ) {
				$name = self::name_leaks( $user );
				$slug = self::slug_leaks( $user );
				if ( $name || $slug ) {
					$found[] = array(
						'id'       => (int) $user->ID,
						'login'    => $user->user_login,
						'name'     => $name,
						'slug'     => $slug,
						'fresh'    => in_array( (int) $user->ID, $fresh, true ) && ! in_array( (int) $user->ID, $public, true ),
						'proposal' => self::proposal( $user ),
					);
				}
			}
		}
		return array(
			'users' => array_slice( $found, 0, self::LIMIT ),
			'total' => count( $found ),
		);
	}

	/**
	 * Whether the display name is the login.
	 *
	 * @param \WP_User $user User.
	 * @return bool
	 */
	public static function name_leaks( $user ) {
		return self::same( $user->display_name, $user->user_login );
	}

	/**
	 * Whether the author page address is made from the login.
	 *
	 * @param \WP_User $user User.
	 * @return bool
	 */
	public static function slug_leaks( $user ) {
		return sanitize_title( $user->user_login ) === $user->user_nicename;
	}

	/**
	 * Two names that read the same (letter case and spaces do not matter, "John.Doe" and "john-doe" are the same).
	 *
	 * @param string $a Name.
	 * @param string $b Name.
	 * @return bool
	 */
	private static function same( $a, $b ) {
		return sanitize_title( (string) $a ) === sanitize_title( (string) $b );
	}

	/**
	 * Name to offer: first and last name when filled, else the nickname, else the current display name, as long
	 * as it is not the login. Empty when there is nothing to offer: the admin types one.
	 *
	 * @param \WP_User $user User.
	 * @return string
	 */
	public static function proposal( $user ) {
		$full = trim( $user->first_name . ' ' . $user->last_name );
		foreach ( array( $full, (string) $user->nickname, (string) $user->display_name ) as $candidate ) {
			if ( '' !== $candidate && ! self::same( $candidate, $user->user_login ) ) {
				return $candidate;
			}
		}
		return '';
	}

	/**
	 * The list with a name field and a button per user, shown inside the card. The buttons send the page form
	 * to the plugin page (formaction, see maybe_handle()), so no form is nested in the settings form.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}
		$leaks = self::leaks();
		if ( ! $leaks['users'] ) {
			return;
		}
		echo '<div class="hag-fix">';
		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: %d: number of users. */
					_n( '%d user shows the login on the site. "Fix" sets the public name below, moves the author page to an address made from it (the old address answers "not found") and puts the new name on the user\'s old comments. The login and the password stay the same.', '%d users show the login on the site. "Fix" sets the public name below, moves the author page to an address made from it (the old address answers "not found") and puts the new name on the user\'s old comments. The login and the password stay the same.', $leaks['total'], 'helloadmin-guard' ),
					$leaks['total']
				)
			)
		);
		printf( '<p class="hag-fix-summary" aria-live="polite" data-more="%d"></p>', (int) ( $leaks['total'] - count( $leaks['users'] ) ) );
		echo '<table class="widefat striped hag-fix-table"><thead><tr>';
		printf( '<th scope="col">%s</th>', esc_html__( 'User', 'helloadmin-guard' ) );
		printf( '<th scope="col">%s</th>', esc_html__( 'Where the login shows', 'helloadmin-guard' ) );
		printf( '<th scope="col">%s</th><td></td></tr></thead><tbody>', esc_html__( 'New public name', 'helloadmin-guard' ) );
		foreach ( $leaks['users'] as $user ) {
			$where = array();
			if ( $user['name'] ) {
				$where[] = __( 'public name', 'helloadmin-guard' );
			}
			if ( $user['slug'] ) {
				$where[] = '/author/' . sanitize_title( $user['login'] ) . '/';
			}
			$field = 'hag-fix-name-' . $user['id'];
			$url   = add_query_arg(
				array(
					'page'          => Admin_Page::SLUG,
					self::USER_ARG  => $user['id'],
					self::NONCE_ARG => wp_create_nonce( self::ACTION . '_' . $user['id'] ),
				),
				admin_url( 'admin.php' )
			);
			printf(
				'<tr><td><a href="%1$s">%2$s</a>%3$s</td><td>%4$s</td><td><label class="screen-reader-text" for="%5$s">%6$s</label><input type="text" class="regular-text" id="%5$s" name="hag_fix_name[%7$d]" value="%8$s" maxlength="250"></td><td><button type="submit" class="button" formaction="%9$s" formmethod="post" formnovalidate data-user="%7$d" data-nonce="%11$s">%10$s</button></td></tr>',
				esc_url( get_edit_user_link( $user['id'] ) ),
				esc_html( $user['login'] ),
				$user['fresh'] ? ' <span class="hag-fix-new">' . esc_html__( '(new)', 'helloadmin-guard' ) . '</span>' : '',
				esc_html( implode( ', ', $where ) ),
				esc_attr( $field ),
				/* translators: %s: user login. */
				esc_html( sprintf( __( 'New public name for %s', 'helloadmin-guard' ), $user['login'] ) ),
				(int) $user['id'],
				esc_attr( $user['proposal'] ),
				esc_url( $url ),
				esc_html__( 'Fix', 'helloadmin-guard' ),
				esc_attr( wp_create_nonce( self::ACTION . '_' . $user['id'] ) )
			);
		}
		echo '</tbody></table>';
		if ( $leaks['total'] > count( $leaks['users'] ) ) {
			/* translators: %d: number of users not shown. */
			printf( '<p>%s</p>', esc_html( sprintf( __( 'And %d more. Fix these first, the list then shows the next ones.', 'helloadmin-guard' ), $leaks['total'] - count( $leaks['users'] ) ) ) );
		}
		echo '</div>';
	}

	/**
	 * Handles the button: checks the rights and the nonce, fixes the user, goes back with a message.
	 *
	 * @return void
	 */
	public function handle() {
		$id = isset( $_GET[ self::USER_ARG ] ) ? absint( $_GET[ self::USER_ARG ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- checked right below.
		check_admin_referer( self::ACTION . '_' . $id, self::NONCE_ARG );
		if ( ! $id || ! current_user_can( 'edit_user', $id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do this.', 'helloadmin-guard' ), '', array( 'response' => 403 ) );
		}
		$names = isset( $_POST['hag_fix_name'] ) && is_array( $_POST['hag_fix_name'] ) ? wp_unslash( $_POST['hag_fix_name'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- one value is taken and sanitized below.
		$name  = isset( $names[ $id ] ) && is_string( $names[ $id ] ) ? sanitize_text_field( $names[ $id ] ) : '';

		$result = self::fix( $id, $name );
		if ( is_wp_error( $result ) ) {
			add_settings_error( Settings::OPTION, 'helloadmin_guard_fix_user', $result->get_error_message(), 'error' );
		} else {
			add_settings_error( Settings::OPTION, 'helloadmin_guard_fix_user', $result, 'success' );
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		$back = wp_get_referer();
		$back = $back ? $back : admin_url( 'admin.php?page=' . Admin_Page::SLUG );
		// Back to the card, not to the top of the page.
		wp_safe_redirect( add_query_arg( 'settings-updated', 'true', remove_query_arg( array( 'settings-updated', self::USER_ARG, self::NONCE_ARG ), $back ) ) . '#hag-user-enumeration' );
		exit;
	}

	/**
	 * Sets a new public name, an author address made from it when the old one is the login, and the new name on
	 * the user's old comments.
	 *
	 * @param int    $id   User ID.
	 * @param string $name New public name.
	 * @return string|\WP_Error Message for the admin, or an error.
	 */
	public static function fix( $id, $name ) {
		$user = get_userdata( $id );
		if ( ! $user ) {
			return new \WP_Error( 'no_user', __( 'This user does not exist any more.', 'helloadmin-guard' ) );
		}
		$name = trim( $name );
		if ( '' === $name ) {
			/* translators: %s: user login. */
			return new \WP_Error( 'empty', sprintf( __( 'Nothing was changed for %s: type a new public name first.', 'helloadmin-guard' ), $user->user_login ) );
		}
		if ( self::same( $name, $user->user_login ) || '' === sanitize_title( $name ) ) {
			/* translators: %s: user login. */
			return new \WP_Error( 'same', sprintf( __( 'Nothing was changed for %s: the new public name must not be the login.', 'helloadmin-guard' ), $user->user_login ) );
		}

		$old  = $user->display_name;
		$data = array(
			'ID'           => $id,
			'display_name' => $name,
		);
		if ( self::slug_leaks( $user ) ) {
			// wp_insert_user() adds "-2" and so on when another user already has this address.
			$data['user_nicename'] = mb_substr( sanitize_title( $name ), 0, 50 );
		}
		$saved = wp_update_user( $data );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		global $wpdb;
		$names    = array_values( array_unique( array( $old, $user->user_login ) ) );
		$in       = implode( ', ', array_fill( 0, count( $names ), '%s' ) );
		$comments = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- one-time update of this user's comments, cache cleared below.
			$wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE user_id = %d AND comment_author IN ({$in})", array_merge( array( $id ), $names ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is only %s placeholders.
		);
		if ( $comments ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- see above.
				$wpdb->prepare( "UPDATE {$wpdb->comments} SET comment_author = %s WHERE user_id = %d AND comment_author IN ({$in})", array_merge( array( $name, $id ), $names ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $in is only %s placeholders, values passed as one array.
			);
			clean_comment_cache( array_map( 'intval', $comments ) );
		}

		$user = get_userdata( $id );
		$slug = $user && isset( $data['user_nicename'] ) ? ' ' . sprintf(
			/* translators: %s: new author page address. */
			__( 'Author page: %s', 'helloadmin-guard' ),
			get_author_posts_url( $id )
		) : '';
		return sprintf(
			/* translators: 1: user login, 2: new public name, 3: number of comments. */
			_n( '%1$s now shows as "%2$s". Comments updated: %3$d.', '%1$s now shows as "%2$s". Comments updated: %3$d.', count( $comments ), 'helloadmin-guard' ),
			$user ? $user->user_login : '',
			$name,
			count( $comments )
		) . $slug;
	}
}
