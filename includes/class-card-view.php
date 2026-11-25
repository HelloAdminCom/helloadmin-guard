<?php
/**
 * How the setting cards look: full texts, a short line with details on demand, or only the switches.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Each admin picks a view with the icon buttons above the cards; it is saved for that user only. All texts are always
 * in the page, the view only hides or shows them, so switching is instant and works without reloading.
 */
class Card_View {

	/**
	 * User meta key.
	 */
	const META = 'helloadmin_guard_card_view';

	/**
	 * Nonce action.
	 */
	const ACTION = 'helloadmin-guard-card-view';

	/**
	 * AJAX action.
	 */
	const AJAX = 'helloadmin_guard_card_view';

	/**
	 * The view of a new user: everything in sight, as before the views existed.
	 */
	const DEFAULT_VIEW = 'full';

	/**
	 * Adds the hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_ajax_' . self::AJAX, array( $this, 'ajax' ) );
	}

	/**
	 * Views: slug => array( label, dashicon ).
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function views() {
		return array(
			'full'    => array( __( 'Full texts', 'helloadmin-guard' ), 'dashicons-text-page' ),
			'brief'   => array( __( 'Short texts, details on click', 'helloadmin-guard' ), 'dashicons-excerpt-view' ),
			'compact' => array( __( 'Only the switches', 'helloadmin-guard' ), 'dashicons-list-view' ),
		);
	}

	/**
	 * View of the current user.
	 *
	 * @return string
	 */
	public static function current() {
		$view = (string) get_user_meta( get_current_user_id(), self::META, true );
		return isset( self::views()[ $view ] ) ? $view : self::DEFAULT_VIEW;
	}

	/**
	 * Saves the view picked with the buttons.
	 *
	 * @return void
	 */
	public function ajax() {
		check_ajax_referer( self::ACTION );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}
		$view = isset( $_POST['view'] ) ? sanitize_key( wp_unslash( $_POST['view'] ) ) : '';
		if ( ! isset( self::views()[ $view ] ) ) {
			wp_send_json_error( null, 400 );
		}
		if ( self::DEFAULT_VIEW === $view ) {
			delete_user_meta( get_current_user_id(), self::META );
		} else {
			update_user_meta( get_current_user_id(), self::META, $view );
		}
		wp_send_json_success();
	}

	/**
	 * Data for the script.
	 *
	 * @return array<string, string>
	 */
	public static function script_data() {
		return array(
			'viewAction' => self::AJAX,
			'viewNonce'  => wp_create_nonce( self::ACTION ),
			'showText'   => __( 'Details', 'helloadmin-guard' ),
			'hideText'   => __( 'Hide details', 'helloadmin-guard' ),
		);
	}

	/**
	 * The icon buttons. Without JavaScript they are not shown: the cards then stay in the saved view.
	 *
	 * @return void
	 */
	public static function render_switch() {
		$current = self::current();
		printf( '<div class="hag-views hide-if-no-js" role="group" aria-label="%s">', esc_attr__( 'Card view', 'helloadmin-guard' ) );
		foreach ( self::views() as $slug => $view ) {
			printf(
				'<button type="button" class="button hag-view" data-view="%1$s" aria-pressed="%2$s" title="%3$s"><span class="dashicons %4$s" aria-hidden="true"></span><span class="screen-reader-text">%3$s</span></button>',
				esc_attr( $slug ),
				$slug === $current ? 'true' : 'false',
				esc_attr( $view[0] ),
				esc_attr( $view[1] )
			);
		}
		echo '</div>';
	}
}
