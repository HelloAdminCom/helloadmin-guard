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
