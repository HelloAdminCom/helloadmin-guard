<?php
/**
 * Plugin bootstrap: builds the feature list and turns on the enabled ones.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin object, one per request.
 */
final class Plugin {

	/**
	 * The single instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Stored settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Registered features, keyed by id.
	 *
	 * @var array<string, Feature>
	 */
	private $features = array();

	/**
	 * Returns the plugin object, creating it on the first call (on plugins_loaded).
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Loads settings, registers features and enables the ones that are on.
	 *
	 * @return void
	 */
	private function boot() {
		$this->settings = new Settings();

		/**
		 * Filters the list of feature objects. Each item must extend HelloAdmin\Guard\Feature.
		 *
		 * @param Feature[] $features Feature objects.
		 */
		$features = apply_filters( 'helloadmin_guard_features', $this->default_features() );
		foreach ( $features as $feature ) {
			if ( $feature instanceof Feature ) {
				$this->features[ $feature->id() ] = $feature;
			}
		}

		foreach ( $this->features as $id => $feature ) {
			$feature->set_values( $this->settings->fields_of( $id ) );
			if ( $this->settings->is_enabled( $id ) ) {
				$feature->enable( $this->settings );
			}
		}

		// Settings switched on (settings page or WP-CLI) start from what the site has now.
		add_action( 'add_option_' . Settings::OPTION, array( $this, 'settings_added' ), 10, 2 );
		add_action( 'update_option_' . Settings::OPTION, array( $this, 'settings_updated' ), 10, 2 );

		// A .htaccess copied from another server can hold rules this copy has off (e.g. the login POST block).
		add_action( 'login_init', array( $this, 'heal_htaccess' ) );
		add_action( 'admin_init', array( $this, 'heal_htaccess' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'helloadmin-guard', new Cli( $this->features ) );
		}

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
			$status = new Status( $this->settings, $this->features );
			$status->register();
			( new Site_Health( $status ) )->register();
			( new Admin_Page( $this->settings, $this->features, $status ) )->register();
			( new Welcome() )->register();
			( new Emergency() )->register();
		}
	}

	/**
	 * Features that ship with the plugin.
	 *
	 * @return Feature[]
	 */
	private function default_features() {
		// Order of the server rules in .htaccess: the https redirect goes first, so every other rule answers on https.
		return array(
			new Features\Https_Redirect(),
			new Features\Login_Url(),
			new Features\Limit_Login(),
			new Features\Disable_App_Passwords(),
			new Features\Admin_Alert(),
			new Features\Mu_Plugins_Watch(),
			new Features\User_Enumeration(),
			new Features\Hide_Wp_Version(),
			new Features\Hide_Rest_Index(),
			new Features\Disable_Xmlrpc(),
			new Features\Disable_File_Editor(),
			new Features\Security_Headers(),
			new Features\Block_Hidden_Files(),
			new Features\Block_Backups(),
			new Features\No_Php_In_Uploads(),
			new Features\Block_Logs(),
			new Features\Disable_Listing(),
			new Features\Block_Core_Internals(),
			new Features\Hide_Version_Files(),
			new Features\Block_Dev_Files(),
			new Features\Hsts(),
			new Features\Block_Trace(),
		);
	}

	/**
	 * Transient that pauses heal_htaccess() after a failed attempt.
	 */
	const HEAL_WAIT = 'helloadmin_guard_heal_wait';

	/**
	 * Rewrites the .htaccess block when it differs from the block the current settings would write: rules of
	 * settings that are off (a .htaccess copied from another copy of the site: staging, local, a move to new
	 * hosting; with the login URL setting off here and its rule in the file, the login form would get a 404),
	 * rules of settings that are on but were removed or edited by hand or by another plugin. Runs on the login
	 * page and in the admin area; writes only when the file and the settings differ.
	 *
	 * @return void
	 */
	public function heal_htaccess() {
		if ( 'apache' !== Htaccess::server() || get_transient( self::HEAL_WAIT ) ) {
			return;
		}
		$want = array();
		foreach ( $this->features as $id => $feature ) {
			if ( $feature->apache_rules() && $this->settings->is_enabled( $id ) ) {
				$want[] = $feature;
			}
		}
		if ( Htaccess::block_matches( $want ) ) {
			return;
		}
		// Taken before writing: parallel requests (bots on the login page) must not all write and check the file.
		set_transient( self::HEAL_WAIT, 1, 2 * MINUTE_IN_SECONDS );
		$result = $this->sync_htaccess();
		// No retry on every page view while the file cannot be fixed (each try may load the home page to check it).
		if ( is_wp_error( $result ) && 'not_checked' !== $result->get_error_code() ) {
			set_transient( self::HEAL_WAIT, 1, 10 * MINUTE_IN_SECONDS );
		}
		Status::forget();
	}

	/**
	 * Writes the server rules of the enabled settings into .htaccess, or removes them all.
	 *
	 * @param bool $remove True to remove the block (plugin deactivated).
	 * @return true|\WP_Error See Htaccess::sync().
	 */
	public function sync_htaccess( $remove = false ) {
		$on = array();
		foreach ( $this->features as $id => $feature ) {
			if ( ! $remove && $feature->apache_rules() && $this->settings->is_enabled( $id ) ) {
				$on[] = $feature;
			}
		}
		return Htaccess::sync( $on );
	}

	/**
	 * Option with the plugin version that wrote the current server rules.
	 */
	const VERSION_OPTION = 'helloadmin_guard_version';

	/**
	 * After an update, rewrites the server rules once: a new version may build them differently
	 * (0.4 builds Permissions-Policy from the site's choices), and old checks may no longer be right.
	 *
	 * @return void
	 */
	public function maybe_upgrade() {
		if ( get_option( self::VERSION_OPTION ) === HELLOADMIN_GUARD_VERSION || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		update_option( self::VERSION_OPTION, HELLOADMIN_GUARD_VERSION, true );
		if ( (array) get_option( Htaccess::WRITTEN_OPTION, array() ) ) {
			$this->sync_htaccess();
		}
		Status::forget();
	}

	/**
	 * Plugin deactivated: e-mail about it if an alert setting is on.
	 *
	 * @return void
	 */
	public function deactivated() {
		$sender = null;
		$extra  = array();
		foreach ( $this->features as $id => $feature ) {
			if ( $feature->is_alert() && $this->settings->is_enabled( $id ) ) {
				$sender  = $sender ? $sender : $feature;
				$extra[] = (string) $feature->value( 'email' );
			}
		}
		// One e-mail, also to the extra address of every e-mail setting that is on (a second mailbox may be set
		// on only one of them).
		if ( $sender ) {
			$sender->send_deactivation_mail( $extra );
		}
	}

	/**
	 * First save of the settings.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Saved value.
	 * @return void
	 */
	public function settings_added( $option, $value ) {
		unset( $option );
		$this->settings_updated( array(), $value );
	}

	/**
	 * Tells the settings that were just switched on.
	 *
	 * @param mixed $old_value Value before the save.
	 * @param mixed $new_value Value after the save.
	 * @return void
	 */
	public function settings_updated( $old_value, $new_value ) {
		$before = is_array( $old_value ) && isset( $old_value['enabled'] ) && is_array( $old_value['enabled'] ) ? $old_value['enabled'] : array();
		$after  = is_array( $new_value ) && isset( $new_value['enabled'] ) && is_array( $new_value['enabled'] ) ? $new_value['enabled'] : array();
		foreach ( $this->features as $id => $feature ) {
			if ( ! empty( $after[ $id ] ) && empty( $before[ $id ] ) ) {
				$feature->switched_on();
			}
		}
	}

	/**
	 * Registered features, keyed by id.
	 *
	 * @return array<string, Feature>
	 */
	public function features() {
		return $this->features;
	}

	/**
	 * Stored settings.
	 *
	 * @return Settings
	 */
	public function settings() {
		return $this->settings;
	}
}
