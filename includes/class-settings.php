<?php
/**
 * Stored settings: one option with the on/off state of every feature and its extra fields.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Option layout:
 * array(
 *     'enabled' => array( 'feature_id' => true, ... ),
 *     'fields'  => array( 'feature_id' => array( 'field' => 'value' ), ... ),
 * )
 */
class Settings {

	const OPTION = 'helloadmin_guard_settings';

	/**
	 * Cached option value.
	 *
	 * @var array{enabled: array<string, bool>, fields: array<string, array<string, string>>}
	 */
	private $data;

	/**
	 * Reads the option once per request.
	 */
	public function __construct() {
		$this->data = self::normalize( get_option( self::OPTION, array() ) );
	}

	/**
	 * Whether a feature is turned on.
	 *
	 * @param string $id Feature id.
	 * @return bool
	 */
	public function is_enabled( $id ) {
		return ! empty( $this->data['enabled'][ $id ] );
	}

	/**
	 * Did the admin mark this setting as not needed on this site?
	 *
	 * @param string $id Feature id.
	 * @return bool
	 */
	public function is_skipped( $id ) {
		return ! empty( $this->data['skip'][ $id ] );
	}

	/**
	 * Show the number of open gaps next to the menu item? On unless the admin turned it off.
	 *
	 * @return bool
	 */
	public function menu_count() {
		return ! isset( $this->data['prefs']['menu_count'] ) || (bool) $this->data['prefs']['menu_count'];
	}

	/**
	 * Value of an extra field, or the default when it was never saved.
	 *
	 * @param string $id       Feature id.
	 * @param string $field    Field name.
	 * @param string $fallback Value to return when nothing is saved.
	 * @return string
	 */
	public function field( $id, $field, $fallback = '' ) {
		$value = isset( $this->data['fields'][ $id ][ $field ] ) ? $this->data['fields'][ $id ][ $field ] : null;
		// A value changed by hand or imported may be an array; only text and numbers count.
		return is_scalar( $value ) ? (string) $value : $fallback;
	}

	/**
	 * Saved values of all extra fields of a feature.
	 *
	 * @param string $id Feature id.
	 * @return array<string, string>
	 */
	public function fields_of( $id ) {
		$fields = isset( $this->data['fields'][ $id ] ) && is_array( $this->data['fields'][ $id ] ) ? $this->data['fields'][ $id ] : array();
		return array_map( 'strval', array_filter( $fields, 'is_scalar' ) );
	}

	/**
	 * Short hash of what a feature is set to (on or off and its extra fields), so stored check results can tell
	 * whether they were taken with the same settings.
	 *
	 * @param string $id Feature id.
	 * @return string
	 */
	public function fingerprint( $id ) {
		return md5( (string) wp_json_encode( array( $this->is_enabled( $id ), $this->fields_of( $id ) ) ) );
	}

	/**
	 * Builds a clean option value from the submitted form. Unknown keys are dropped.
	 *
	 * @param mixed     $input    Raw $_POST data for the option.
	 * @param Feature[] $features Registered features.
	 * @return array{enabled: array<string, bool>, fields: array<string, array<string, string>>}
	 */
	public static function sanitize( $input, array $features ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array(
			'enabled' => array(),
			'fields'  => array(),
			'skip'    => array(),
		);
		foreach ( $features as $feature ) {
			$id = $feature->id();
			if ( ! empty( $input['enabled'][ $id ] ) ) {
				$clean['enabled'][ $id ] = true;
			}
			if ( ! empty( $input['skip'][ $id ] ) ) {
				$clean['skip'][ $id ] = true;
			}
			foreach ( $feature->fields() as $name => $field ) {
				$value = isset( $input['fields'][ $id ][ $name ] ) ? $input['fields'][ $id ][ $name ] : $field['default'];

				$clean['fields'][ $id ][ $name ] = $feature->sanitize_field( $name, $value );
			}
		}
		return $clean;
	}

	/**
	 * Makes sure the stored value has the expected shape, whatever is in the database.
	 *
	 * @param mixed $value Option value.
	 * @return array{enabled: array<string, bool>, fields: array<string, array<string, string>>}
	 */
	private static function normalize( $value ) {
		$value = is_array( $value ) ? $value : array();
		return array(
			'enabled' => isset( $value['enabled'] ) && is_array( $value['enabled'] ) ? array_map( 'boolval', $value['enabled'] ) : array(),
			'fields'  => isset( $value['fields'] ) && is_array( $value['fields'] ) ? array_filter( $value['fields'], 'is_array' ) : array(),
			'skip'    => isset( $value['skip'] ) && is_array( $value['skip'] ) ? array_map( 'boolval', $value['skip'] ) : array(),
			'prefs'   => isset( $value['prefs'] ) && is_array( $value['prefs'] ) ? $value['prefs'] : array(),
		);
	}
}
