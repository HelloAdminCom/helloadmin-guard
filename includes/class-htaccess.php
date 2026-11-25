<?php
/**
 * Writes the plugin's block of rules into the .htaccess file in the site root.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Every change goes the same way: save a copy of the file, write the block, open the home page. If the home page
 * answers with a server error, the old file is put back. The block sits at the top of the file, before the
 * WordPress rules, between "# BEGIN HelloAdmin Guard" and "# END HelloAdmin Guard".
 */
class Htaccess {

	const MARKER = 'HelloAdmin Guard';

	/**
	 * Option with the file as it was before the last change: array( 'time' => int, 'content' => string ).
	 * Kept in the database, not as a file, so nobody can download it from the site.
	 */
	const BACKUP_OPTION = 'helloadmin_guard_htaccess_backup';

	/**
	 * Option with the ids of the settings whose rules are in .htaccess now. PHP skips headers the server already sends.
	 */
	const WRITTEN_OPTION = 'helloadmin_guard_rules_written';

	/**
	 * Are this setting's rules in .htaccess right now?
	 *
	 * @param string $id Setting id.
	 * @return bool
	 */
	public static function written( $id ) {
		return in_array( $id, (array) get_option( self::WRITTEN_OPTION, array() ), true );
	}

	/**
	 * Request header the server adds when it applies the header rules of a setting. Set with mod_headers, the same
	 * module that sends the response headers: when the module is off or the rules were removed, the request
	 * arrives without it and PHP sends the headers itself. Dashes only: Apache drops header names with
	 * underscores before PHP sees them.
	 *
	 * @param string $id Feature id.
	 * @return string
	 */
	public static function flag_header( $id ) {
		return 'X-Helloadmin-Guard-' . str_replace( '_', '-', $id );
	}

	/**
	 * Whether the server applied the header rules of a setting to this request (see flag_header()). A visitor
	 * can send the header himself, but that only matters where mod_headers is off, and only for his own page.
	 *
	 * @param string $id Feature id.
	 * @return bool
	 */
	public static function served( $id ) {
		$key = 'HTTP_' . strtoupper( str_replace( '-', '_', self::flag_header( $id ) ) );
		// Each internal redirect (for example index.php, then an ErrorDocument) adds one more REDIRECT_ prefix.
		return ! empty( $_SERVER[ $key ] ) || ! empty( $_SERVER[ 'REDIRECT_' . $key ] ) || ! empty( $_SERVER[ 'REDIRECT_REDIRECT_' . $key ] );
	}

	/**
	 * Ids of the settings whose rules are in the plugin block of .htaccess now, read from the file itself
	 * (each part of the block starts with "# <id>"). The file may come from another copy of the site. When the file
	 * holds more than one block (pasted twice by hand), the ids of all blocks count: their rules all work.
	 *
	 * @return string[]
	 */
	public static function ids_in_file() {
		$fs   = self::fs();
		$path = self::path();
		if ( ! $fs->exists( $path ) ) {
			return array();
		}
		$pattern = '/' . preg_quote( '# BEGIN ' . self::MARKER, '/' ) . '(.*?)' . preg_quote( '# END ' . self::MARKER, '/' ) . '/s';
		if ( ! preg_match_all( $pattern, (string) $fs->get_contents( $path ), $blocks ) ) {
			return array();
		}
		preg_match_all( '/^# ([a-z0-9_]+)\s*$/m', implode( "\n", $blocks[1] ), $ids );
		return array_values( array_unique( $ids[1] ) );
	}

	/**
	 * Settings to save after a failed sync. The file did not change, so the switches must match it: a setting
	 * whose rules are still in the file stays on (turning off login_url while its POST block stays would
	 * lock the admin out), and a server rule setting that could not be written stays off. General settings
	 * work in PHP without their rules, so they may stay on.
	 *
	 * @param array<string, bool>   $enabled Settings about to be saved as on.
	 * @param array<string, object> $tried   Features whose rules were to be written, by id.
	 * @param \WP_Error             $error   Result of sync().
	 * @return array<string, bool>
	 */
	public static function settle( array $enabled, array $tried, \WP_Error $error ) {
		if ( 'not_checked' === $error->get_error_code() ) {
			return $enabled;
		}
		$written = (array) get_option( self::WRITTEN_OPTION, array() );
		foreach ( $tried as $id => $feature ) {
			if ( $feature instanceof Htaccess_Feature && ! in_array( $id, $written, true ) ) {
				unset( $enabled[ $id ] );
			}
		}
		foreach ( $written as $id ) {
			$enabled[ (string) $id ] = true;
		}
		return $enabled;
	}

	/**
	 * Remembers which settings have their rules in the file.
	 *
	 * @param Feature[] $features Features whose rules were written.
	 * @return void
	 */
	private static function remember( array $features ) {
		$ids = array();
		foreach ( $features as $feature ) {
			$ids[] = $feature->id();
		}
		update_option( self::WRITTEN_OPTION, $ids, true );
	}

	/**
	 * Web server type: apache (also LiteSpeed), nginx or other.
	 *
	 * @return string
	 */
	public static function server() {
		global $is_apache, $is_nginx;
		if ( $is_apache ) {
			return 'apache';
		}
		return $is_nginx ? 'nginx' : 'other';
	}

	/**
	 * Full path of the .htaccess file in the site root.
	 *
	 * @return string
	 */
	public static function path() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		return get_home_path() . '.htaccess';
	}

	/**
	 * Direct file access. The file is in the site root and PHP already writes it for permalinks, like core does.
	 *
	 * @return \WP_Filesystem_Direct
	 */
	private static function fs() {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		return new \WP_Filesystem_Direct( null );
	}

	/**
	 * Can the plugin write the file (or create it)?
	 *
	 * @return bool
	 */
	public static function writable() {
		$fs   = self::fs();
		$path = self::path();
		return $fs->exists( $path ) ? $fs->is_writable( $path ) : $fs->is_writable( dirname( $path ) );
	}

	/**
	 * Whether every rule line of a feature is one plain line. Some rules are built from values in the database
	 * (the site address in siteurl and home). A line break or another control character there would add a
	 * directive of someone else's choice to .htaccess, for example php_value auto_prepend_file. Such a feature
	 * is left out of the file, so it stays off.
	 *
	 * @param Feature $feature Feature.
	 * @return bool
	 */
	public static function safe_rules( $feature ) {
		foreach ( $feature->apache_rules() as $line ) {
			if ( ! is_string( $line ) || preg_match( '/[\x00-\x08\x0A-\x1F\x7F]/', $line ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether the plugin block in the file is exactly the block these features would write. False when rules were
	 * removed, edited or added by hand, by another plugin or with a file copied from another site. A second block
	 * also counts as a mismatch: the next sync writes one block and removes the rest.
	 *
	 * @param Feature[] $features Enabled features with server rules.
	 * @return bool
	 */
	public static function block_matches( array $features ) {
		$fs      = self::fs();
		$path    = self::path();
		$content = $fs->exists( $path ) ? str_replace( "\r\n", "\n", (string) $fs->get_contents( $path ) ) : '';
		$lines   = self::lines( array_filter( $features, array( __CLASS__, 'safe_rules' ) ) );
		$pattern = '/' . preg_quote( '# BEGIN ' . self::MARKER, '/' ) . '.*?' . preg_quote( '# END ' . self::MARKER, '/' ) . '/s';
		$count   = preg_match_all( $pattern, $content, $blocks );
		if ( $count > 1 ) {
			return false;
		}
		$found = $count ? $blocks[0][0] : '';
		if ( ! $lines ) {
			return '' === $found;
		}
		return trim( self::build( '', $lines ) ) === $found;
	}

	/**
	 * Lines of the block for the given features, each part signed with the feature id.
	 *
	 * @param Feature[] $features Enabled features.
	 * @return string[]
	 */
	public static function lines( array $features ) {
		$lines = array();
		foreach ( $features as $feature ) {
			$lines[] = '# ' . $feature->id();
			foreach ( $feature->apache_rules() as $line ) {
				$lines[] = $line;
			}
		}
		return $lines;
	}

	/**
	 * Writes the block for the given features (an empty list removes it) and checks that the site still opens.
	 *
	 * @param Feature[] $features Enabled features.
	 * @return true|\WP_Error Error codes: not_apache, not_writable, write_failed, site_broken (file restored),
	 *                        not_checked (rules kept, but the home page could not be loaded to check them).
	 */
	public static function sync( array $features ) {
		$features = array_filter( $features, array( __CLASS__, 'safe_rules' ) );
		$lines    = self::lines( $features );
		// Removing always works, also from WP-CLI where the server type is unknown (plugin deactivated there).
		if ( $lines && 'apache' !== self::server() ) {
			return new \WP_Error( 'not_apache' );
		}

		$fs     = self::fs();
		$path   = self::path();
		$before = $fs->exists( $path ) ? (string) $fs->get_contents( $path ) : '';
		$after  = self::build( $before, $lines );
		if ( $after === $before ) {
			self::remember( $features );
			return true;
		}
		if ( ! self::writable() ) {
			return new \WP_Error( 'not_writable' );
		}

		update_option(
			self::BACKUP_OPTION,
			array(
				'time'    => time(),
				'content' => $before,
			),
			false
		);
		// Keep the file's own permissions. Passing them also avoids FS_CHMOD_FILE, defined only by WP_Filesystem().
		$mode = $fs->exists( $path ) ? ( fileperms( $path ) & 0777 ) : 0644;
		if ( ! $fs->put_contents( $path, $after, $mode ) ) {
			return new \WP_Error( 'write_failed' );
		}

		if ( ! $lines ) {
			self::remember( array() );
			return true;
		}
		$check = self::site_responds();
		if ( false === $check ) {
			// If even the old file cannot be written back, say so: the site may be down until it is fixed by hand.
			return $fs->put_contents( $path, $before, $mode ) ? new \WP_Error( 'site_broken' ) : new \WP_Error( 'restore_failed' );
		}
		self::remember( $features );
		return null === $check ? new \WP_Error( 'not_checked' ) : true;
	}

	/**
	 * The file content with our block replaced, added at the top, or removed when there are no lines.
	 *
	 * @param string   $content Current file content.
	 * @param string[] $lines   Block lines.
	 * @return string
	 */
	public static function build( $content, array $lines ) {
		$begin   = '# BEGIN ' . self::MARKER;
		$end     = '# END ' . self::MARKER;
		$pattern = '/' . preg_quote( $begin, '/' ) . '.*?' . preg_quote( $end, '/' ) . '\R*/s';
		$content = (string) preg_replace( $pattern, '', $content );
		if ( ! $lines ) {
			return $content;
		}

		$block = array_merge(
			array(
				$begin,
				// Not translated: the file must not change when the admin language changes.
				'# Written by the HelloAdmin Guard plugin (Advanced tab). Changes here are overwritten when its settings are saved.',
			),
			$lines,
			array( $end )
		);
		return implode( "\n", $block ) . "\n\n" . ltrim( $content );
	}

	/**
	 * Opens the home page from the server itself, over https and over plain http, following redirects by hand.
	 *
	 * @return bool|null True if it answers without a server error, false on a 5xx answer or a redirect loop, null if
	 *                   it cannot be reached (some hosts block requests from a site to itself).
	 */
	public static function site_responds() {
		$home   = add_query_arg( 'helloadmin-guard-check', (string) time(), home_url( '/' ) );
		$result = self::follow( $home );
		if ( true !== $result ) {
			return $result;
		}
		// A redirect rule may loop only for plain http visitors (a proxy that talks to the server over http).
		if ( 0 === strpos( $home, 'https://' ) && false === self::follow( set_url_scheme( $home, 'http' ) ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Requests a URL and follows up to 5 redirects.
	 *
	 * @param string $url URL to open.
	 * @return bool|null True on an answer below 500 (a redirect to another host counts as one), false on 5xx or more than
	 *                   5 redirects, null if unreachable.
	 */
	private static function follow( $url ) {
		for ( $hop = 0; $hop <= 5; $hop++ ) {
			$response = wp_remote_get(
				$url,
				array(
					'timeout'     => 10,
					'redirection' => 0,
					/** This filter is documented in wp-includes/class-wp-http-streams.php */
					'sslverify'   => apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.
				)
			);
			if ( is_wp_error( $response ) ) {
				return null;
			}
			$code     = (int) wp_remote_retrieve_response_code( $response );
			$location = (string) wp_remote_retrieve_header( $response, 'location' );
			if ( $code < 300 || $code >= 400 || '' === $location ) {
				return $code < 500;
			}
			$url = \WP_Http::make_absolute_url( $location, $url );
			// Only this site's own addresses are followed: the check must not become a request to another host.
			if ( ! self::own_host( $url ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a URL is on the site's own host (home or siteurl, with or without www.).
	 *
	 * @param string $url Absolute URL.
	 * @return bool
	 */
	private static function own_host( $url ) {
		$strip = static function ( $address ) {
			return (string) preg_replace( '/^www\\./', '', strtolower( (string) wp_parse_url( (string) $address, PHP_URL_HOST ) ) );
		};
		$host  = $strip( $url );
		return '' !== $host && in_array( $host, array( $strip( get_option( 'home' ) ), $strip( get_option( 'siteurl' ) ) ), true );
	}
}
