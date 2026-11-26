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
 * An empty string starts a new paragraph. Inside any text **words** are bold (b() wraps a value that way); the plain
 * text part shows them without the stars.
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
	 * Date and time for an e-mail as numbers in the order the site language uses: 26.11.2026 11:43 for most
	 * languages (24-hour clock), 11/26/2026 11:43 AM in the US, 26/11/2026 11:43 in the UK, 26/11/2026 11:43 am in
	 * Australia and New Zealand, 2026-11-26 11:43 a.m. in Canada.
	 *
	 * @param int|null $timestamp Unix time, now when null.
	 * @return string
	 */
	public static function time( $timestamp = null ) {
		$formats = array(
			'en_US' => 'm/d/Y g:i A',
			'en_GB' => 'd/m/Y H:i',
			'en_AU' => 'd/m/Y g:i a',
			'en_NZ' => 'd/m/Y g:i a',
			'en_CA' => 'Y-m-d g:i a',
			'en_ZA' => 'Y/m/d H:i',
			'fr_FR' => 'd/m/Y H:i',
			'fr_CA' => 'Y-m-d H:i',
			'es_ES' => 'd/m/Y H:i',
			'it_IT' => 'd/m/Y H:i',
			'pt_PT' => 'd/m/Y H:i',
			'pt_BR' => 'd/m/Y H:i',
		);
		$locale  = get_locale();
		return (string) wp_date( isset( $formats[ $locale ] ) ? $formats[ $locale ] : 'd.m.Y H:i', $timestamp );
	}

	/**
	 * A value in bold (any stars inside are dropped, so the value cannot break the markup).
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function b( $value ) {
		return '**' . str_replace( '*', '', (string) $value ) . '**';
	}

	/**
	 * A closing line with a link: a bold label on the left, the address after it.
	 *
	 * @param string $label Where the link leads, for example "Settings → General".
	 * @param string $url   Address.
	 * @return array<string, string>
	 */
	public static function link( $label, $url ) {
		return array(
			'bold' => $label . ':',
			'text' => $url,
		);
	}

	/**
	 * A line as plain text.
	 *
	 * @param string|array<string, string> $line Line.
	 * @return string
	 */
	public static function plain( $line ) {
		if ( ! is_array( $line ) ) {
			return str_replace( '**', '', (string) $line );
		}
		$text = isset( $line['text'] ) ? $line['text'] : '';
		if ( isset( $line['item'] ) ) {
			return str_replace( '**', '', '  • ' . trim( $line['item'] . ' ' . $text ) );
		}
		return str_replace( '**', '', trim( ( isset( $line['bold'] ) ? $line['bold'] . ' ' : '' ) . $text ) );
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
			return '<div style="padding-left:18px;margin:2px 0">&bull;&nbsp;<strong>' . esc_html( str_replace( '**', '', $line['item'] ) ) . '</strong> ' . self::fmt( $text ) . '</div>';
		}
		if ( is_array( $line ) && isset( $line['bold'] ) ) {
			$html = '<strong>' . esc_html( str_replace( '**', '', $line['bold'] ) ) . '</strong> ' . self::fmt( $text );
		} else {
			$html = self::fmt( trim( (string) $line ) );
		}
		// Plain lines indented in the text version keep an indent.
		$indent = is_string( $line ) && 0 === strpos( $line, ' ' ) ? 'padding-left:18px;' : '';
		return '<div style="' . $indent . 'margin:0 0 2px">' . $html . '</div>';
	}

	/**
	 * Text to HTML: escaped, addresses as links, **words** in bold, the plugin name in bold.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function fmt( $text ) {
		$html = make_clickable( esc_html( $text ) );
		$html = (string) preg_replace( '/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $html );
		return self::bold_name( $html );
	}

	/**
	 * The plugin name in bold.
	 *
	 * @param string $html Escaped HTML.
	 * @return string
	 */
	private static function bold_name( $html ) {
		return str_replace( array( '<strong>HelloAdmin Guard</strong>', 'HelloAdmin Guard' ), array( 'HelloAdmin Guard', '<strong>HelloAdmin Guard</strong>' ), $html );
	}
}
