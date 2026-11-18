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
	 * Topic of the setting (login, leaks, legacy, headers, files). Not shown since 0.2: cards are ordered by priority().
	 *
	 * @return string
	 */
	public function group() {
		return 'other';
	}

	/**
	 * How bad the gap is while the setting is off: 'high', 'medium' or 'low'. Shown on the card.
	 *
	 * @return string
	 */
	public function risk() {
		return 'low';
	}

	/**
	 * Checks whether the gap is closed right now, by anyone: this plugin, another plugin or the server.
	 * Most checks ask the site itself as a visitor (see Probe), so they see what a bot sees.
	 *
	 * @return bool|null True if closed, false if open, null if it cannot be checked here.
	 */
	public function probe() {
		return null;
	}

	/**
	 * What closes the gap when the check finds it closed while this setting is off, if the plugin can tell exactly.
	 *
	 * @return string Empty when unknown.
	 */
	public function covered_by() {
		return '';
	}

	/**
	 * Optional Apache rules (written into .htaccess) that do the same before PHP starts, also for static files.
	 * Settings on the General tab keep working without them; on the Advanced tab the rules are the setting.
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		return array();
	}

	/**
	 * The same rules for Nginx, shown to copy.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		return array();
	}

	/**
	 * Things on this site the setting cannot fix by itself, shown on the card as warnings.
	 *
	 * @return string[]
	 */
	public function warnings() {
		return array();
	}

	/**
	 * The logged-in admin, or the first administrator when there is none (WP-CLI, cron).
	 *
	 * @return int User ID, 0 if the site has no administrator.
	 */
	protected static function admin_id() {
		$id = get_current_user_id();
		if ( $id ) {
			return $id;
		}
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'fields'  => 'ID',
				'orderby' => 'ID',
			)
		);
		return $admins ? (int) $admins[0] : 0;
	}

	/**
	 * Place on the tab: lower numbers first. The list goes from the biggest risk down to the cheap rules.
	 *
	 * @return int
	 */
	public function priority() {
		return 100;
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
