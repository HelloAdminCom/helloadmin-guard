<?php
/**
 * Welcome message at the top of the settings page.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Shown on every tab of the settings page. Not a popup and no redirect after activation: it sits in the page.
 * The arrow or "Got it" folds it into one line (remembered per user), the X removes it; the About tab brings it back.
 * Built on <details>, so folding works without JavaScript and with the keyboard; the script only saves the state.
 */
class Welcome {

	const META      = 'helloadmin_guard_welcome_hidden';
	const META_FOLD = 'helloadmin_guard_welcome_folded';
	const ACTION    = 'helloadmin-guard-welcome';
	const AJAX      = 'helloadmin_guard_welcome';

	/**
	 * Adds the hooks that save the state.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'handle' ) );
		add_action( 'wp_ajax_' . self::AJAX, array( $this, 'ajax' ) );
	}

	/**
	 * Link that changes the state: 'hide' (remove), 'show' (bring back), 'fold' or 'unfold'. Works without JavaScript.
	 *
	 * @param string $state New state.
	 * @param string $tab   Tab to come back to.
	 * @return string
	 */
	public static function url( $state, $tab = '' ) {
		$args = array( self::ACTION => $state );
		if ( '' !== $tab ) {
			$args['tab'] = $tab;
		}
		return wp_nonce_url( add_query_arg( $args, admin_url( 'admin.php?page=' . Admin_Page::SLUG ) ), self::ACTION );
	}

	/**
	 * Saves the state from a link and goes back to the clean page URL.
	 *
	 * @return void
	 */
	public function handle() {
		if ( ! isset( $_GET[ self::ACTION ] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( self::ACTION );
		self::save( sanitize_key( wp_unslash( $_GET[ self::ACTION ] ) ) );
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		wp_safe_redirect( admin_url( 'admin.php?page=' . Admin_Page::SLUG . ( $tab ? '&tab=' . $tab : '' ) ) );
		exit;
	}

	/**
	 * Saves the folded state sent by the script when the arrow is clicked.
	 *
	 * @return void
	 */
	public function ajax() {
		check_ajax_referer( self::ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		$state = isset( $_POST['state'] ) ? sanitize_key( wp_unslash( $_POST['state'] ) ) : '';
		if ( in_array( $state, array( 'fold', 'unfold' ), true ) ) {
			self::save( $state );
		}
		wp_send_json_success();
	}

	/**
	 * Stores a state for the current user.
	 *
	 * @param string $state hide, show, fold or unfold.
	 * @return void
	 */
	private static function save( $state ) {
		$user = get_current_user_id();
		switch ( $state ) {
			case 'hide':
				update_user_meta( $user, self::META, 1 );
				break;
			case 'show':
				delete_user_meta( $user, self::META );
				delete_user_meta( $user, self::META_FOLD );
				break;
			case 'fold':
				update_user_meta( $user, self::META_FOLD, 1 );
				break;
			case 'unfold':
				delete_user_meta( $user, self::META_FOLD );
				break;
		}
	}

	/**
	 * Has the current user removed the message?
	 *
	 * @return bool
	 */
	public static function hidden() {
		return (bool) get_user_meta( get_current_user_id(), self::META, true );
	}

	/**
	 * Script data: where and how to save the folded state.
	 *
	 * @return array<string, string>
	 */
	public static function script_data() {
		return array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'action'  => self::AJAX,
			'nonce'   => wp_create_nonce( self::ACTION ),
		);
	}

	/**
	 * Prints the message, unless the user removed it.
	 *
	 * @param string $tab Current tab, for the links that work without JavaScript.
	 * @return void
	 */
	public static function render( $tab ) {
		if ( self::hidden() ) {
			return;
		}
		$folded = (bool) get_user_meta( get_current_user_id(), self::META_FOLD, true );

		echo '<div class="hag-welcome-wrap">';
		printf(
			'<details class="hag-welcome"%s><summary><h2>%s</h2><span class="hag-welcome-arrow" aria-hidden="true"></span></summary>',
			$folded ? '' : ' open',
			esc_html__( 'Hello, admin or developer!', 'helloadmin-guard' )
		);
		echo '<div class="hag-welcome-body">';
		echo '<p>' . esc_html__( 'Out of the box, WordPress leaves a few gaps open: anyone can find your user names, your WordPress version and the address of your login page. No, the WordPress team is not being careless 🙂 some sites need them to work. On most sites, though, they can and should be closed. That is what HelloAdmin Guard does. Every gap has its own switch, so you decide what to close and what to leave open.', 'helloadmin-guard' ) . '</p>';
		echo '<h3>' . esc_html__( 'Good to know', 'helloadmin-guard' ) . '</h3>';
		echo '<ul class="hag-checks">';
		echo '<li>' . esc_html__( 'Nothing is on until you turn it on.', 'helloadmin-guard' ) . '</li>';
		echo '<li>' . esc_html__( 'Every switch tells you what it does, what may stop working and how to undo it.', 'helloadmin-guard' ) . '</li>';
		echo '<li>' . esc_html__( 'The plugin is free and will stay free: no Pro version, no account, no data sent anywhere.', 'helloadmin-guard' ) . '</li>';
		echo '<li>' . esc_html__( 'Every new version closes more gaps, so keep the plugin updated.', 'helloadmin-guard' ) . '</li>';
		echo '</ul>';
		echo '<p>' . esc_html__( 'Not sure where to start? Turn switches on one at a time and check your site after each one.', 'helloadmin-guard' ) . '</p>';
		printf(
			'<p><a class="button button-primary hag-welcome-fold" href="%s">%s</a></p>',
			esc_url( self::url( 'fold', $tab ) ),
			esc_html__( 'Got it', 'helloadmin-guard' )
		);
		echo '</div></details>';
		printf(
			'<a class="hag-welcome-close" href="%s"><span class="screen-reader-text">%s</span></a>',
			esc_url( self::url( 'hide', $tab ) ),
			esc_html__( 'Hide the welcome message', 'helloadmin-guard' )
		);
		echo '</div>';
	}
}
