<?php
/**
 * Removes the theme and plugin file editors from wp-admin.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Feature;
use HelloAdmin\Guard\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Does what DISALLOW_FILE_EDIT does, without touching wp-config.php: the capabilities to edit theme and
 * plugin files are refused to everyone, so the editor screens and their menu items disappear.
 */
class Disable_File_Editor extends Feature {

	/**
	 * Capabilities that open the file editors.
	 */
	const CAPS = array( 'edit_themes', 'edit_plugins', 'edit_files' );

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'disable_file_editor';
	}

	/**
	 * {@inheritDoc}
	 */
	public function title() {
		return __( 'Turn off the file editor', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function what() {
		return __( 'Removes Appearance > Theme File Editor and Plugins > Plugin File Editor. Anyone who gets into an admin account can no longer put their own PHP code into the site from the browser.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function may_break() {
		return __( 'Quick code edits from wp-admin: change theme and plugin files over SFTP, SSH or Git instead. The Site Editor, the Customizer and Additional CSS keep working.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function undo() {
		return __( 'Turn this off and save. The editors come back at once; nothing is changed in wp-config.php.', 'helloadmin-guard' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function group() {
		return 'legacy';
	}

	/**
	 * What closes the gap, when known.
	 *
	 * @return string
	 */
	public function covered_by() {
		return defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ? 'wp-config.php (DISALLOW_FILE_EDIT)' : '';
	}

	/**
	 * Is the gap closed now, by anyone?
	 *
	 * @return bool|null
	 */
	public function probe() {
		if ( defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT ) {
			return true;
		}
		return ! current_user_can( 'edit_plugins' ) && ! current_user_can( 'edit_themes' );
	}

	/**
	 * Risk while off.
	 *
	 * @return string
	 */
	public function risk() {
		return 'high';
	}

	/**
	 * Place on the tab.
	 *
	 * @return int
	 */
	public function priority() {
		return 20;
	}

	/**
	 * {@inheritDoc}
	 */
	public function learn_more() {
		return 'https://helloadmin.com/wordpress-security-hardening-essential-steps/';
	}

	/**
	 * Adds the capability filter.
	 *
	 * @param Settings $settings Plugin settings.
	 * @return void
	 */
	public function enable( Settings $settings ) {
		add_filter( 'map_meta_cap', array( $this, 'refuse_editor_caps' ), 10, 2 );
	}

	/**
	 * Maps the editor capabilities to do_not_allow, the same way core does when DISALLOW_FILE_EDIT is set.
	 *
	 * @param string[] $caps Primitive capabilities the user needs.
	 * @param string   $cap  Capability being checked.
	 * @return string[]
	 */
	public function refuse_editor_caps( $caps, $cap ) {
		return in_array( $cap, self::CAPS, true ) ? array( 'do_not_allow' ) : $caps;
	}
}
