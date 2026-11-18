<?php
/**
 * Welcome message at the top of the settings page.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Shown to each admin until they close it. Not a popup and no redirect after activation: it sits in the page and
 * goes away with one click. The About tab has a link to show it again.
 */
class Welcome {

	const META   = 'helloadmin_guard_welcome_hidden';
	const ACTION = 'helloadmin-guard-welcome';

	/**
	 * Adds the hook that hides or shows the message.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'handle' ) );
	}

	/**
	 * Link that hides ('hide') or shows ('show') the message, with a nonce.
	 *
	 * @param string $state 'hide' or 'show'.
	 * @return string
	 */
	public static function url( $state ) {
		return wp_nonce_url( add_query_arg( self::ACTION, $state, admin_url( 'admin.php?page=' . Admin_Page::SLUG ) ), self::ACTION );
	}

	/**
	 * Saves the choice for the current user and goes back to the clean page URL.
	 *
	 * @return void
	 */
	public function handle() {
		if ( ! isset( $_GET[ self::ACTION ] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( self::ACTION );
		$state = sanitize_key( wp_unslash( $_GET[ self::ACTION ] ) );
		if ( 'hide' === $state ) {
			update_user_meta( get_current_user_id(), self::META, 1 );
			$tab = '';
		} else {
			delete_user_meta( get_current_user_id(), self::META );
			$tab = 'general';
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . Admin_Page::SLUG . ( $tab ? '&tab=' . $tab : '' ) ) );
		exit;
	}

	/**
	 * Has the current user closed the message?
	 *
	 * @return bool
	 */
	public static function hidden() {
		return (bool) get_user_meta( get_current_user_id(), self::META, true );
	}

	/**
	 * Prints the message, unless the user closed it.
	 *
	 * @return void
	 */
	public static function render() {
		if ( self::hidden() ) {
			return;
		}
		$hide = self::url( 'hide' );
		echo '<div class="hag-welcome" role="region" aria-labelledby="hag-welcome-title">';
		printf(
			'<a class="hag-welcome-close" href="%s"><span class="screen-reader-text">%s</span></a>',
			esc_url( $hide ),
			esc_html__( 'Hide the welcome message', 'helloadmin-guard' )
		);
		echo '<h2 id="hag-welcome-title">' . esc_html__( 'Hello, admin or developer!', 'helloadmin-guard' ) . '</h2>';
		echo '<p>' . esc_html__( 'A fresh WordPress leaves a few doors open: anyone can find your user names, your WordPress version and your login page. HelloAdmin Guard closes them, one switch at a time.', 'helloadmin-guard' ) . '</p>';
		echo '<ul>';
		echo '<li>' . esc_html__( 'Nothing is on until you turn it on.', 'helloadmin-guard' ) . '</li>';
		echo '<li>' . esc_html__( 'Every switch tells you what it does, what may stop working and how to undo it.', 'helloadmin-guard' ) . '</li>';
		echo '<li>' . esc_html__( 'Free, no Pro version, no account. The plugin sends nothing anywhere.', 'helloadmin-guard' ) . '</li>';
		echo '</ul>';
		echo '<p>' . esc_html__( 'A good place to start: the Login group below.', 'helloadmin-guard' ) . '</p>';
		printf( '<p><a class="button button-primary" href="%s">%s</a></p>', esc_url( $hide ), esc_html__( 'Got it', 'helloadmin-guard' ) );
		echo '</div>';
	}
}
