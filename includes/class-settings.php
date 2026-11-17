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
	 * Value of an extra field, or the default when it was never saved.
	 *
	 * @param string $id      Feature id.
	 * @param string $field   Field name.
	 * @param string $fallback Value to return when nothing is saved.
	 * @return string
	 */
	public function field( $id, $field, $fallback = '' ) {
		return isset( $this->data['fields'][ $id ][ $field ] ) ? $this->data['fields'][ $id ][ $field ] : $fallback;
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
		);
		foreach ( $features as $feature ) {
			$id = $feature->id();
			if ( ! empty( $input['enabled'][ $id ] ) ) {
				$clean['enabled'][ $id ] = true;
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
			'fields'  => isset( $value['fields'] ) && is_array( $value['fields'] ) ? $value['fields'] : array(),
		);
	}
}
