<?php
/**
 * Base class for one hardening setting (one toggle on the settings page).
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * A feature is off by default. When the user turns it on, enable() adds its hooks on every request.
 *
 * Every feature must explain itself in plain words: what it does, what may break and how to undo it.
 * These texts are shown next to the toggle, so nobody has to guess.
 */
abstract class Feature {

	/**
	 * Unique key, used in the option array and in HTML ids. Lowercase, underscores.
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Short title shown next to the toggle.
	 *
	 * @return string
	 */
	abstract public function title();

	/**
	 * What the setting does, one or two sentences.
	 *
	 * @return string
	 */
	abstract public function what();

	/**
	 * What may stop working when the setting is on.
	 *
	 * @return string
	 */
	abstract public function may_break();

	/**
	 * How to undo it, including the case when the admin area is not reachable.
	 *
	 * @return string
	 */
	abstract public function undo();

	/**
	 * Adds the hooks that do the work. Called on every request while the setting is on.
	 *
	 * @param Settings $settings Plugin settings, for features with extra fields.
	 * @return void
	 */
	abstract public function enable( Settings $settings );

	/**
	 * Settings page tab: 'general' or 'advanced'.
	 *
	 * @return string
	 */
	public function tab() {
		return 'general';
	}

	/**
	 * Link to an article that explains the problem in detail, or an empty string.
	 *
	 * @return string
	 */
	public function learn_more() {
		return '';
	}

	/**
	 * Extra fields shown under the toggle, keyed by field name.
	 * Each field: array( 'label' => string, 'type' => 'text', 'default' => string, 'description' => string ).
	 *
	 * @return array<string, array<string, string>>
	 */
	public function fields() {
		return array();
	}

	/**
	 * Cleans the value of one extra field before it is saved.
	 *
	 * @param string $field Field name from fields().
	 * @param mixed  $value Raw value from the form.
	 * @return string
	 */
	public function sanitize_field( $field, $value ) {
		unset( $field );
		return sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
	}
}
