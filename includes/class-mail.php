<?php
/**
 * The e-mails the plugin sends: one look for all of them.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Builds simple HTML (clickable addresses, bold notes, lists, the plugin name in bold, a small footer apart) and the
 * same text as a plain-text part for mail programs that do not show HTML.
 *
 * A line is a string, or an array:
 * - array( 'bold' => lead words, 'text' => the rest ): a line that starts in bold, for example "Important!";
 * - array( 'item' => name, 'text' => details ): a list item with a bullet, the name in bold (a file, a user).
 * An empty string starts a new paragraph.
 */
class Mail {

	/**
	 * Sends an e-mail.
	 *
	 * @param string[]                                 $to      Addresses.
	 * @param string                                   $subject Subject without the site name (it is added in brackets).
	 * @param array<int, string|array<string, string>> $lines   Lines, see the class comment.
	 * @param string                                   $footer  Small text at the end.
	 * @return bool Whether wp_mail() accepted the message.
	 */
	public static function send( array $to, $subject, array $lines, $footer ) {
		$text = implode( "\n", array_map( array( __CLASS__, 'plain' ), $lines ) ) . "\n\n\n" . $footer;
		$alt  = static function ( $mailer ) use ( $text ) {
			$mailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
		};
		add_action( 'phpmailer_init', $alt );
		$site = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		$sent = wp_mail( $to, '[' . $site . '] ' . $subject, self::html( $lines, $footer ), array( 'Content-Type: text/html; charset=UTF-8' ) );
		remove_action( 'phpmailer_init', $alt );
		return $sent;
	}

	/**
	 * Date and time for an e-mail in the site's format. A site that still has WordPress's English defaults
	 * ("F j, Y" and "g:i a") but another language gets the usual format of that language from the translation,
	 * for example a 24-hour clock.
	 *
	 * @param int|null $timestamp Unix time, now when null.
	 * @return string
	 */
	public static function time( $timestamp = null ) {
		$date = (string) get_option( 'date_format' );
		$time = (string) get_option( 'time_format' );
		if ( 'F j, Y' === $date && 'g:i a' === $time && 0 !== strpos( get_locale(), 'en' ) ) {
			/* translators: PHP date format for dates and times in e-mails, see https://www.php.net/manual/datetime.format.php */
			return (string) wp_date( __( 'F j, Y g:i a', 'helloadmin-guard' ), $timestamp );
		}
		return (string) wp_date( $date . ' ' . $time, $timestamp );
	}

	/**
	 * A line as plain text.
	 *
	 * @param string|array<string, string> $line Line.
	 * @return string
	 */
	public static function plain( $line ) {
		if ( ! is_array( $line ) ) {
			return (string) $line;
		}
		$text = isset( $line['text'] ) ? $line['text'] : '';
		if ( isset( $line['item'] ) ) {
			return '  • ' . trim( $line['item'] . ' ' . $text );
		}
		return trim( ( isset( $line['bold'] ) ? $line['bold'] . ' ' : '' ) . $text );
	}

	/**
	 * The e-mail as HTML: lines between empty lines make a paragraph.
	 *
	 * @param array<int, string|array<string, string>> $lines  Lines.
	 * @param string                                   $footer Footer.
	 * @return string
	 */
	public static function html( array $lines, $footer ) {
		$blocks = array();
		$block  = array();
		// An empty line at the end closes the last paragraph too.
		foreach ( array_merge( $lines, array( '' ) ) as $line ) {
			if ( '' === trim( self::plain( $line ) ) ) {
				if ( $block ) {
					$blocks[] = '<div style="margin:0 0 14px">' . implode( '', $block ) . '</div>';
				}
				$block = array();
				continue;
			}
			$block[] = self::line_html( $line );
		}
		return '<!DOCTYPE html><html><body style="font-family:-apple-system,Segoe UI,Arial,sans-serif;font-size:14px;line-height:1.5;color:#1d2327">'
			. implode( '', $blocks )
			. '<p style="margin:32px 0 0;font-size:12px;color:#646970">' . self::bold_name( esc_html( $footer ) ) . '</p></body></html>';
	}

	/**
	 * One line as HTML.
	 *
	 * @param string|array<string, string> $line Line.
	 * @return string
	 */
	private static function line_html( $line ) {
		$text = is_array( $line ) && isset( $line['text'] ) ? $line['text'] : '';
		if ( is_array( $line ) && isset( $line['item'] ) ) {
			return '<div style="padding-left:18px;margin:2px 0">&bull;&nbsp;<strong>' . esc_html( $line['item'] ) . '</strong> ' . make_clickable( esc_html( $text ) ) . '</div>';
		}
		if ( is_array( $line ) && isset( $line['bold'] ) ) {
			$html = '<strong>' . esc_html( $line['bold'] ) . '</strong> ' . make_clickable( esc_html( $text ) );
		} else {
			$html = make_clickable( esc_html( trim( (string) $line ) ) );
		}
		// Plain lines indented in the text version keep an indent.
		$indent = is_string( $line ) && 0 === strpos( $line, ' ' ) ? 'padding-left:18px;' : '';
		return '<div style="' . $indent . 'margin:0 0 2px">' . self::bold_name( $html ) . '</div>';
	}

	/**
	 * The plugin name in bold.
	 *
	 * @param string $html Escaped HTML.
	 * @return string
	 */
	private static function bold_name( $html ) {
		return str_replace( 'HelloAdmin Guard', '<strong>HelloAdmin Guard</strong>', $html );
	}
}
