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

		if ( is_admin() ) {
			( new Admin_Page( $this->settings, $this->features ) )->register();
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
			new Features\User_Enumeration(),
			new Features\Disable_Xmlrpc(),
		);
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
