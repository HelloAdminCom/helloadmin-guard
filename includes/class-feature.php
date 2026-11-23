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
	 * Values of the extra fields: saved ones, or the ones being saved while the server rules are written.
	 *
	 * @var array<string, string>
	 */
	private $values = array();

	/**
	 * Sets the values of the extra fields (see fields()).
	 *
	 * @param array<string, string> $values Field name => value.
	 * @return void
	 */
	public function set_values( array $values ) {
		$this->values = $values;
	}

	/**
	 * Saved value of an extra field without looking at fields(), for code that runs before translations load
	 * (fields() builds translated labels).
	 *
	 * @param string $field    Field name.
	 * @param string $fallback Value when nothing is saved.
	 * @return string
	 */
	protected function saved_value( $field, $fallback ) {
		return isset( $this->values[ $field ] ) ? (string) $this->values[ $field ] : $fallback;
	}

	/**
	 * Value of an extra field, or its default when nothing is saved.
	 *
	 * @param string $field Field name from fields().
	 * @return string
	 */
	protected function value( $field ) {
		if ( isset( $this->values[ $field ] ) ) {
			return (string) $this->values[ $field ];
		}
		$fields = $this->fields();
		return isset( $fields[ $field ]['default'] ) ? (string) $fields[ $field ]['default'] : '';
	}

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
	 * Is this an alert (it e-mails about changes) rather than a gap? An alert that is off cannot be checked from
	 * outside and is a choice, so it does not count as an open gap.
	 *
	 * @return bool
	 */
	public function is_alert() {
		return false;
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
	 * The optional "Also send to" field of the settings that send e-mail alerts.
	 *
	 * @return array<string, array<string, string>>
	 */
	protected static function email_field() {
		return array(
			'email' => array(
				'label'       => __( 'Also send to', 'helloadmin-guard' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'One more e-mail address besides the site address in Settings, General. Leave empty to use only the site address.', 'helloadmin-guard' ),
			),
		);
	}

	/**
	 * Runs a watch check (alert settings) on the moments that matter, without WP-Cron. WP-Cron runs only when the
	 * site gets visits and never when DISABLE_WP_CRON is set without a server cron, so an hourly event can be late
	 * or never run. Instead the check runs at the end of the request:
	 * - after every login, with the login form or an application password (a planted account has to log in to
	 *   be used);
	 * - on admin pages, at most every 5 minutes;
	 * - on any other request, at most once an hour.
	 *
	 * @param callable $callback Check to run.
	 * @param string   $option   Option that keeps the time of the last run (autoloaded, so reading it is free).
	 * @return void
	 */
	protected function watch( $callback, $option ) {
		$run = static function ( $every ) use ( $callback, $option ) {
			if ( time() - (int) get_option( $option, 0 ) < $every ) {
				return;
			}
			update_option( $option, time(), true );
			add_action( 'shutdown', $callback );
		};
		$now = static function () use ( $run ) {
			$run( 0 );
		};
		add_action( 'wp_login', $now );
		add_action( 'application_password_did_authenticate', $now );
		add_action(
			'admin_init',
			static function () use ( $run ) {
				$run( 5 * MINUTE_IN_SECONDS );
			}
		);
		add_action(
			'init',
			static function () use ( $run ) {
				$run( HOUR_IN_SECONDS );
			}
		);
	}

	/**
	 * Sends a plain text alert to the site e-mail address, and to the "email" field address if it is set.
	 *
	 * @param string   $subject Subject without the site name (it is added in brackets).
	 * @param string[] $lines   Lines of the message.
	 * @return bool Whether wp_mail() accepted the message.
	 */
	protected function send_alert_mail( $subject, array $lines ) {
		$to    = array( (string) get_option( 'admin_email' ) );
		$extra = $this->value( 'email' );
		if ( $extra && is_email( $extra ) && ! in_array( $extra, $to, true ) ) {
			$to[] = $extra;
		}

		$lines[] = '';
		/* translators: %s: title of the setting. */
		$lines[] = sprintf( __( 'Sent by HelloAdmin Guard. To stop these e-mails, turn off "%s" in HeAd Guard.', 'helloadmin-guard' ), $this->title() );

		$site = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
		return wp_mail( $to, '[' . $site . '] ' . $subject, implode( "\n", $lines ) );
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
	 * Each field: array( 'label' => string, 'type' => 'text', 'checkbox' or 'radio', 'default' => string, 'description' => string ).
	 * A checkbox saves '1' or '0'. Checkboxes that follow each other are shown as one group under 'group' of the first.
	 * A radio field also has 'options' => array( value => label ) and saves the chosen value.
	 *
	 * @return array<string, array<string, mixed>>
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
