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
			if ( $this->settings->is_enabled( $id ) ) {
				$feature->enable( $this->settings );
			}
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'helloadmin-guard', new Cli( $this->features ) );
		}

		if ( is_admin() ) {
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
		return array(
			new Features\Login_Url(),
			new Features\Limit_Login(),
			new Features\Disable_App_Passwords(),
			new Features\User_Enumeration(),
			new Features\Hide_Wp_Version(),
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
			if ( ! $remove && $feature instanceof Htaccess_Feature && $this->settings->is_enabled( $id ) ) {
				$on[] = $feature;
			}
		}
		return Htaccess::sync( $on );
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
