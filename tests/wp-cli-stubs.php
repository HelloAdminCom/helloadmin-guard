<?php
/**
 * The few WP-CLI symbols the plugin uses, for PHPStan only (php-stubs/wp-cli-stubs does not support WordPress 7 stubs).
 *
 * @package HelloAdmin\Guard
 */

// phpcs:ignoreFile -- declarations for static analysis.

namespace {
	class WP_CLI {
		/**
		 * @param string $name
		 * @param object $callable
		 * @return bool
		 */
		public static function add_command( $name, $callable ) {}
		/** @param string $message */
		public static function log( $message ) {}
		/** @param string $message */
		public static function success( $message ) {}
		/** @param string $message */
		public static function warning( $message ) {}
		/**
		 * @param string $message
		 * @return never
		 */
		public static function error( $message ) {}
	}
}

namespace WP_CLI\Utils {
	/**
	 * @param string                           $format
	 * @param array<int, array<string, mixed>> $items
	 * @param string[]                         $fields
	 * @return void
	 */
	function format_items( $format, $items, $fields ) {}
}
