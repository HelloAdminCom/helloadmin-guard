<?php
/**
 * Settings page: HelloAdmin Guard menu after Settings.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the tabs with one card per feature and saves the form through the Settings API.
 */
class Admin_Page {

	const SLUG  = 'helloadmin-guard';
	const GROUP = 'helloadmin_guard';

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
	private $features;

	/**
	 * Sets up the page.
	 *
	 * @param Settings               $settings Stored settings.
	 * @param array<string, Feature> $features Registered features.
	 */
	public function __construct( Settings $settings, array $features ) {
		$this->settings = $settings;
		$this->features = $features;
	}

	/**
	 * Adds the WordPress hooks for the page.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( HELLOADMIN_GUARD_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Tabs of the page: slug => label.
	 *
	 * @return array<string, string>
	 */
	private function tabs() {
		return array(
			'general'  => __( 'General', 'helloadmin-guard' ),
			'advanced' => __( 'Advanced', 'helloadmin-guard' ),
			'about'    => __( 'About', 'helloadmin-guard' ),
		);
	}

	/**
	 * Tab from the URL, or 'general'.
	 *
	 * @return string
	 */
	private function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab switch, nothing is saved.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		return array_key_exists( $tab, $this->tabs() ) ? $tab : 'general';
	}

	/**
	 * Adds the HelloAdmin Guard menu right after Settings.
	 *
	 * @return void
	 */
	public function add_menu() {
		add_menu_page(
			__( 'HelloAdmin Guard', 'helloadmin-guard' ),
			$this->menu_title(),
			'manage_options',
			self::SLUG,
			array( $this, 'render' ),
			$this->svg_icon(),
			81
		);
	}

	/**
	 * Menu label: the short name, fits on one line.
	 *
	 * @return string
	 */
	private function menu_title() {
		/* translators: Short plugin name in the admin menu. HeAd = He(llo) Ad(min), reads as "head guard". */
		return esc_html__( 'HeAd Guard', 'helloadmin-guard' );
	}

	/**
	 * Menu icon (head with a gear) as a data URI; WordPress paints it in the admin color scheme.
	 *
	 * @return string
	 */
	private function svg_icon() {
		$svg = (string) file_get_contents( HELLOADMIN_GUARD_DIR . 'assets/img/icon.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- data URI for the menu icon.
	}

	/**
	 * URL of the settings page, optionally on a tab.
	 *
	 * @param string $tab Tab slug or empty.
	 * @return string
	 */
	private function page_url( $tab = '' ) {
		$url = admin_url( 'admin.php?page=' . self::SLUG );
		return $tab ? add_query_arg( 'tab', $tab, $url ) : $url;
	}

	/**
	 * Registers the option so options.php can save it.
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Saves only the features of the submitted tab; the other tabs keep their stored values.
	 *
	 * Input without a tab is treated as the full option: that is how update_option() calls this
	 * a second time (through add_option()) when the option does not exist yet, with the already
	 * cleaned value, and how code outside the form saves it.
	 *
	 * @param mixed $input Raw form data.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$tab   = isset( $input['tab'] ) ? sanitize_key( $input['tab'] ) : '';
		if ( '' === $tab ) {
			return Settings::sanitize( $input, $this->features );
		}

		$on_tab = array();
		foreach ( $this->features as $id => $feature ) {
			if ( $feature->tab() === $tab ) {
				$on_tab[ $id ] = $feature;
			}
		}

		$clean  = Settings::sanitize( $input, $on_tab );
		$stored = get_option( Settings::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		foreach ( array( 'enabled', 'fields' ) as $key ) {
			$keep = isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ? array_diff_key( $stored[ $key ], $on_tab ) : array();

			$clean[ $key ] = array_merge( $keep, $clean[ $key ] );
		}
		return $clean;
	}

	/**
	 * Loads the page CSS on this page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, array( 'toplevel_page_' . self::SLUG ), true ) ) {
			return;
		}
		wp_enqueue_style( 'helloadmin-guard-admin', HELLOADMIN_GUARD_URL . 'assets/css/admin.css', array(), HELLOADMIN_GUARD_VERSION );
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$url = $this->page_url();
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'helloadmin-guard' ) . '</a>' );
		return $links;
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$tab = $this->current_tab();
		echo '<div class="wrap hag">';
		printf(
			'<h1 class="hag-heading"><img class="hag-logo" src="%s" width="32" height="32" alt="">%s</h1>',
			esc_url( add_query_arg( 'ver', (string) filemtime( HELLOADMIN_GUARD_DIR . 'assets/img/icon.svg' ), HELLOADMIN_GUARD_URL . 'assets/img/icon.svg' ) ),
			esc_html__( 'HelloAdmin Guard', 'helloadmin-guard' )
		);
		echo '<p class="hag-intro">' . esc_html__( 'Each setting is off until you turn it on. Read what it does and what may break before you save.', 'helloadmin-guard' ) . '</p>';

		echo '<nav class="nav-tab-wrapper">';
		foreach ( $this->tabs() as $slug => $label ) {
			$url   = $this->page_url( $slug );
			$class = 'nav-tab' . ( $slug === $tab ? ' nav-tab-active' : '' );
			printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $class ), esc_html( $label ) );
		}
		echo '</nav>';

		if ( 'about' === $tab ) {
			$this->render_about();
		} else {
			$this->render_features( $tab );
		}
		echo '</div>';
	}

	/**
	 * Renders the form with the feature cards of one tab.
	 *
	 * @param string $tab Tab slug.
	 * @return void
	 */
	private function render_features( $tab ) {
		$features = array_filter(
			$this->features,
			static function ( Feature $feature ) use ( $tab ) {
				return $feature->tab() === $tab;
			}
		);

		if ( ! $features ) {
			echo '<p>' . esc_html__( 'No settings on this tab yet.', 'helloadmin-guard' ) . '</p>';
			return;
		}

		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP );
		printf( '<input type="hidden" name="%s[tab]" value="%s">', esc_attr( Settings::OPTION ), esc_attr( $tab ) );
		foreach ( $features as $feature ) {
			$this->render_card( $feature );
		}
		submit_button();
		echo '</form>';
	}

	/**
	 * Renders one feature: toggle, explanation, extra fields.
	 *
	 * @param Feature $feature Feature to render.
	 * @return void
	 */
	private function render_card( Feature $feature ) {
		$id   = $feature->id();
		$name = Settings::OPTION . '[enabled][' . $id . ']';
		$html = 'hag-' . str_replace( '_', '-', $id );

		echo '<div class="hag-card">';
		printf(
			'<label class="hag-toggle" for="%1$s"><input type="checkbox" role="switch" id="%1$s" name="%2$s" value="1" %3$s><span class="hag-switch" aria-hidden="true"></span><span class="hag-title">%4$s</span></label>',
			esc_attr( $html ),
			esc_attr( $name ),
			checked( $this->settings->is_enabled( $id ), true, false ),
			esc_html( $feature->title() )
		);

		echo '<dl class="hag-explain">';
		printf( '<dt>%s</dt><dd>%s</dd>', esc_html__( 'What it does', 'helloadmin-guard' ), esc_html( $feature->what() ) );
		printf( '<dt>%s</dt><dd>%s</dd>', esc_html__( 'What may break', 'helloadmin-guard' ), esc_html( $feature->may_break() ) );
		printf( '<dt>%s</dt><dd>%s</dd>', esc_html__( 'How to undo', 'helloadmin-guard' ), esc_html( $feature->undo() ) );
		echo '</dl>';

		foreach ( $feature->fields() as $field => $args ) {
			$field_id = $html . '-' . str_replace( '_', '-', $field );
			printf(
				'<p class="hag-field"><label for="%1$s">%2$s</label> <input type="text" class="regular-text" id="%1$s" name="%3$s" value="%4$s">',
				esc_attr( $field_id ),
				esc_html( $args['label'] ),
				esc_attr( Settings::OPTION . '[fields][' . $id . '][' . $field . ']' ),
				esc_attr( $this->settings->field( $id, $field, $args['default'] ) )
			);
			if ( ! empty( $args['description'] ) ) {
				echo '<span class="description">' . esc_html( $args['description'] ) . '</span>';
			}
			echo '</p>';
		}

		if ( $feature->learn_more() ) {
			printf(
				'<p class="hag-more"><a href="%s" target="_blank" rel="noopener">%s</a></p>',
				esc_url( $feature->learn_more() ),
				esc_html__( 'Learn more', 'helloadmin-guard' )
			);
		}
		echo '</div>';
	}

	/**
	 * Renders the About tab.
	 *
	 * @return void
	 */
	private function render_about() {
		echo '<div class="hag-card">';
		echo '<p><strong>' . esc_html__( 'HelloAdmin Guard is free and will stay free.', 'helloadmin-guard' ) . '</strong> '
			. esc_html__( 'No paid version, no locked settings, no ads, no calls to outside servers.', 'helloadmin-guard' ) . '</p>';
		echo '<p>' . esc_html__( 'Every setting tells you what it changes, what may break and how to turn it back, because a security setting you do not understand is a risk too.', 'helloadmin-guard' ) . '</p>';
		echo '<h2>' . esc_html__( 'Need help with your site?', 'helloadmin-guard' ) . '</h2>';
		echo '<p>' . esc_html__( 'The plugin is made by HelloAdmin, a WordPress and WooCommerce developer. If your site is slow, broken or was hacked, I can look at it and tell you in writing what I see.', 'helloadmin-guard' ) . '</p>';
		printf(
			'<p><a class="button button-primary" href="%s" target="_blank" rel="noopener">%s</a> <a class="button" href="%s" target="_blank" rel="noopener">%s</a></p>',
			esc_url( 'https://helloadmin.com/#contact' ),
			esc_html__( 'Contact HelloAdmin', 'helloadmin-guard' ),
			esc_url( 'https://github.com/HelloAdminCom/helloadmin-guard/issues' ),
			esc_html__( 'Report a problem', 'helloadmin-guard' )
		);
		echo '</div>';
	}
}
