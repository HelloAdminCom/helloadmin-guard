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
	 * Check results and the count of open gaps.
	 *
	 * @var Status
	 */
	private $status;

	/**
	 * Sets up the page.
	 *
	 * @param Settings               $settings Stored settings.
	 * @param array<string, Feature> $features Registered features.
	 * @param Status                 $status   Check results.
	 */
	public function __construct( Settings $settings, array $features, Status $status ) {
		$this->settings = $settings;
		$this->features = $features;
		$this->status   = $status;
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
		global $plugin_page;
		// On the settings page, check what the last save changed before the menu number is counted.
		if ( self::SLUG === $plugin_page ) {
			$this->status->run_pending();
		}
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
		$title = esc_html__( 'HeAd Guard', 'helloadmin-guard' );
		$count = $this->settings->menu_count() ? $this->status->count() : 0;
		if ( $count > 0 ) {
			// Same markup as the number of pending comments, so it looks like the rest of the menu.
			$title .= sprintf(
				' <span class="awaiting-mod count-%1$d"><span class="pending-count" aria-hidden="true">%1$d</span><span class="screen-reader-text">%2$s</span></span>',
				$count,
				/* translators: %d: number of open security gaps. */
				esc_html( sprintf( _n( '%d open gap', '%d open gaps', $count, 'helloadmin-guard' ), $count ) )
			);
		}
		return $title;
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
			$clean          = Settings::sanitize( $input, $this->features );
			$clean['prefs'] = isset( $input['prefs'] ) && is_array( $input['prefs'] ) ? array( 'menu_count' => ! empty( $input['prefs']['menu_count'] ) ) : array();
			return $this->apply_htaccess( $clean );
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

		foreach ( array( 'enabled', 'fields', 'skip' ) as $key ) {
			$keep = isset( $stored[ $key ] ) && is_array( $stored[ $key ] ) ? array_diff_key( $stored[ $key ], $on_tab ) : array();

			$clean[ $key ] = array_merge( $keep, $clean[ $key ] );
		}
		// Display preferences live on the General tab.
		if ( 'general' === $tab ) {
			$clean['prefs'] = array( 'menu_count' => ! empty( $input['prefs']['menu_count'] ) );
		} else {
			$clean['prefs'] = isset( $stored['prefs'] ) && is_array( $stored['prefs'] ) ? $stored['prefs'] : array();
		}
		return $this->apply_htaccess( $clean );
	}

	/**
	 * Writes the server rules for the settings about to be saved. If that fails, those settings are saved as off and
	 * the admin sees why.
	 *
	 * @param array<string, mixed> $clean Sanitized option value.
	 * @return array<string, mixed>
	 */
	private function apply_htaccess( array $clean ) {
		static $done = null;
		// WordPress may run the sanitize callback twice on the first save; write the file once.
		if ( null !== $done ) {
			return $done;
		}

		$on = array();
		foreach ( $this->features as $id => $feature ) {
			if ( $feature->apache_rules() && ! empty( $clean['enabled'][ $id ] ) ) {
				$on[ $id ] = $feature;
			}
		}

		$result = Htaccess::sync( $on );
		if ( is_wp_error( $result ) ) {
			$messages = array(
				'not_apache'   => __( 'Server rules were not saved: this server is not Apache or LiteSpeed, so the plugin cannot write them. Copy the rules from each card into your server configuration instead.', 'helloadmin-guard' ),
				'not_writable' => __( 'Server rules were not saved: WordPress cannot write the .htaccess file. Copy the rules from each card into .htaccess by hand, or ask your host to make the file writable.', 'helloadmin-guard' ),
				'write_failed' => __( 'Server rules were not saved: writing the .htaccess file failed. Nothing was changed.', 'helloadmin-guard' ),
				'site_broken'  => __( 'Server rules were not saved: after writing them the home page answered with a server error, so the old .htaccess file was put back. The server may not support one of the rules.', 'helloadmin-guard' ),
				'not_checked'  => __( 'Server rules were saved, but the plugin could not open your home page to check them (some hosts block such requests). Open the site in a new tab to make sure it works.', 'helloadmin-guard' ),
			);
			$code     = (string) $result->get_error_code();
			// Server rule settings (Advanced tab) stay off; General settings keep working in PHP without their rules.
			if ( 'not_checked' !== $code ) {
				foreach ( $on as $id => $feature ) {
					if ( $feature instanceof Htaccess_Feature ) {
						unset( $clean['enabled'][ $id ] );
					}
				}
			}
			if ( function_exists( 'add_settings_error' ) && isset( $messages[ $code ] ) ) {
				add_settings_error( Settings::OPTION, 'helloadmin_guard_htaccess', $messages[ $code ], 'not_checked' === $code ? 'warning' : 'error' );
			}
		}
		$done = $clean;
		return $clean;
	}

	/**
	 * Menu number colour on every admin page; the page CSS and JS on this page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue( $hook_suffix ) {
		// The open gaps number in the menu stays red in every admin color scheme, also on the plugin's own page.
		wp_add_inline_style( 'admin-menu', '#adminmenu #toplevel_page_' . self::SLUG . ' .awaiting-mod{background:#d63638;color:#fff}' );
		if ( ! in_array( $hook_suffix, array( 'toplevel_page_' . self::SLUG ), true ) ) {
			return;
		}
		wp_enqueue_style( 'helloadmin-guard-admin', HELLOADMIN_GUARD_URL . 'assets/css/admin.css', array(), HELLOADMIN_GUARD_VERSION );
		wp_enqueue_script( 'helloadmin-guard-admin', HELLOADMIN_GUARD_URL . 'assets/js/admin.js', array(), HELLOADMIN_GUARD_VERSION, array( 'in_footer' => true ) );
		wp_localize_script( 'helloadmin-guard-admin', 'helloadminGuard', array_merge( Welcome::script_data(), Status::script_data() ) );
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
		echo '<div class="wrap hag hag-tab-' . esc_attr( $tab ) . '">';
		printf(
			'<h1 class="hag-heading"><img class="hag-logo" src="%s" width="32" height="32" alt="">%s</h1>',
			esc_url( add_query_arg( 'ver', (string) filemtime( HELLOADMIN_GUARD_DIR . 'assets/img/icon.svg' ), HELLOADMIN_GUARD_URL . 'assets/img/icon.svg' ) ),
			esc_html__( 'HelloAdmin Guard', 'helloadmin-guard' )
		);
		// The welcome message says the same in more detail; the short line is for admins who removed it.
		if ( Welcome::hidden() ) {
			echo '<p class="hag-intro">' . esc_html__( 'Each setting is off until you turn it on. Read what it does and what may break before you save.', 'helloadmin-guard' ) . '</p>';
		}

		Welcome::render( $tab );

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

		if ( 'advanced' === $tab ) {
			$this->render_server_note();
		}

		$this->render_check_line();

		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP );
		printf( '<input type="hidden" name="%s[tab]" value="%s">', esc_attr( Settings::OPTION ), esc_attr( $tab ) );
		// One list from the biggest risk down to the cheap rules (Feature::priority()), not grouped by topic.
		usort(
			$features,
			static function ( Feature $a, Feature $b ) {
				return $a->priority() <=> $b->priority();
			}
		);
		echo '<div class="hag-cards">';
		foreach ( $features as $feature ) {
			$this->render_card( $feature );
		}
		echo '</div>';
		if ( 'general' === $tab ) {
			printf(
				'<p class="hag-pref"><label><input type="checkbox" name="%s[prefs][menu_count]" value="1"%s> %s</label></p>',
				esc_attr( Settings::OPTION ),
				checked( $this->settings->menu_count(), true, false ),
				esc_html__( 'Show the number of open gaps next to HeAd Guard in the admin menu', 'helloadmin-guard' )
			);
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

		// Server rules can be switched on only where the plugin can write them; elsewhere the card shows them to copy.
		$manual = $feature instanceof Htaccess_Feature && ( 'apache' !== Htaccess::server() || ! Htaccess::writable() );

		echo '<div class="hag-card"><div class="hag-head">';
		printf(
			'<label class="hag-toggle" for="%1$s"><input type="checkbox" role="switch" id="%1$s" name="%2$s" value="1" %3$s%5$s><span class="hag-switch" aria-hidden="true"></span><span class="hag-title">%4$s</span></label>',
			esc_attr( $html ),
			esc_attr( $name ),
			checked( $this->settings->is_enabled( $id ), true, false ),
			esc_html( $feature->title() ),
			$manual ? ' disabled' : ''
		);
		list( $class, $chip, $note ) = $this->status_parts( $feature );
		printf( '<span class="hag-chip hag-chip-%s">%s</span></div>', esc_attr( $class ), esc_html( $chip ) );
		if ( '' !== $note ) {
			printf( '<p class="hag-note">%s</p>', esc_html( $note ) );
		}
		foreach ( $feature->warnings() as $warning ) {
			printf( '<p class="hag-warning">%s</p>', esc_html( $warning ) );
		}

		// "Learn more" closes the last text of the card: the last field hint, or "How to undo".
		$more = '';
		if ( $feature->learn_more() ) {
			$more = sprintf(
				' <a class="hag-more" href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( self::site_link( $feature->learn_more(), 'learn-more', $id ) ),
				esc_html__( 'Learn more', 'helloadmin-guard' )
			);
		}
		$fields    = $feature->fields();
		$last_hint = '';
		foreach ( $fields as $field => $args ) {
			if ( ! empty( $args['description'] ) ) {
				$last_hint = $field;
			}
		}

		echo '<dl class="hag-explain">';
		printf( '<dt>%s</dt><dd>%s</dd>', esc_html__( 'Description', 'helloadmin-guard' ), esc_html( $feature->what() ) );
		printf( '<dt>%s</dt><dd>%s</dd>', esc_html__( 'Downsides', 'helloadmin-guard' ), esc_html( $feature->may_break() ) );
		printf(
			'<dt>%s</dt><dd>%s%s</dd>',
			esc_html__( 'How to undo', 'helloadmin-guard' ),
			esc_html( $feature->undo() ),
			'' === $last_hint ? $more : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
		);

		foreach ( $fields as $field => $args ) {
			$field_id = $html . '-' . str_replace( '_', '-', $field );
			$has_hint = ! empty( $args['description'] );
			printf(
				'<dt class="hag-field-label"><label for="%1$s">%2$s</label></dt><dd><input type="text" class="hag-input" id="%1$s" name="%3$s" value="%4$s"%5$s>',
				esc_attr( $field_id ),
				esc_html( $args['label'] ),
				esc_attr( Settings::OPTION . '[fields][' . $id . '][' . $field . ']' ),
				esc_attr( $this->settings->field( $id, $field, $args['default'] ) ),
				$has_hint ? ' aria-describedby="' . esc_attr( $field_id . '-help' ) . '"' : ''
			);
			if ( $has_hint ) {
				printf(
					'<p class="description" id="%s">%s%s</p>',
					esc_attr( $field_id . '-help' ),
					esc_html( $args['description'] ),
					$field === $last_hint ? $more : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts above.
				);
			}
			echo '</dd>';
		}
		echo '</dl>';
		echo '<div class="hag-foot">';
		if ( ! $this->settings->is_enabled( $id ) ) {
			printf(
				'<p class="hag-skip"><label><input type="checkbox" name="%s" value="1"%s> %s</label></p>',
				esc_attr( Settings::OPTION . '[skip][' . $id . ']' ),
				checked( $this->settings->is_skipped( $id ), true, false ),
				esc_html__( 'I do not need this on this site (it stops counting as an open gap)', 'helloadmin-guard' )
			);
		}
		if ( $feature->apache_rules() ) {
			$this->render_rules( $feature, $manual );
		}
		echo '</div></div>';
	}

	/**
	 * One label for the card head, from what the last check found, and an optional line under it.
	 * While the gap is open the label carries the risk; once it is closed the risk no longer matters.
	 *
	 * @param Feature $feature Feature.
	 * @return array{0: string, 1: string, 2: string} CSS class suffix, label, note.
	 */
	private function status_parts( Feature $feature ) {
		$risk  = in_array( $feature->risk(), array( 'high', 'medium', 'low' ), true ) ? $feature->risk() : 'low';
		$risks = array(
			'high'   => __( 'high risk', 'helloadmin-guard' ),
			'medium' => __( 'medium risk', 'helloadmin-guard' ),
			'low'    => __( 'low risk', 'helloadmin-guard' ),
		);
		switch ( $this->status->of( $feature->id() ) ) {
			case 'on':
				return array( 'on', __( 'Closed by this plugin', 'helloadmin-guard' ), '' );
			case 'broken':
				return array( 'broken', __( 'Not working', 'helloadmin-guard' ), __( 'The setting is on, but the last check still found this gap open. Server rules may not work on this server, or another plugin undoes the setting.', 'helloadmin-guard' ) );
			case 'covered':
				$by       = $feature->covered_by();
				$suspects = Status::suspects();
				if ( '' !== $by ) {
					/* translators: %s: what closes the gap, for example "wp-config.php (DISALLOW_FILE_EDIT)". */
					$note = sprintf( __( 'Closed by %s. You can leave this off.', 'helloadmin-guard' ), $by );
				} elseif ( $suspects ) {
					/* translators: %s: names of active security plugins. */
					$note = sprintf( __( 'Probably by %s or the server settings. You can leave this off.', 'helloadmin-guard' ), implode( ', ', $suspects ) );
				} else {
					$note = __( 'By the server settings or another plugin. You can leave this off.', 'helloadmin-guard' );
				}
				return array( 'covered', __( 'Already closed', 'helloadmin-guard' ), $note );
			case 'open':
				/* translators: %s: "high risk", "medium risk" or "low risk". */
				return array( 'open-' . $risk, sprintf( __( 'Open, %s', 'helloadmin-guard' ), $risks[ $risk ] ), '' );
			case 'skipped':
				return array( 'skipped', __( 'Not needed', 'helloadmin-guard' ), '' );
			default:
				/* translators: %s: "high risk", "medium risk" or "low risk". */
				$label = null === Status::cached() ? __( 'Not checked yet, %s', 'helloadmin-guard' ) : __( 'Cannot be checked, %s', 'helloadmin-guard' );
				return array( 'unknown', sprintf( $label, $risks[ $risk ] ), '' );
		}
	}

	/**
	 * When the site was last checked, with a button to check again.
	 *
	 * @return void
	 */
	private function render_check_line() {
		$cached = Status::cached();
		if ( null === $cached ) {
			$text = __( 'Checking what your site answers to visitors...', 'helloadmin-guard' );
		} else {
			/* translators: %s: time since the last check, for example "5 minutes". */
			$text = sprintf( __( 'Last check: %s ago.', 'helloadmin-guard' ), human_time_diff( $cached['time'] ) );
		}
		printf(
			'<p class="hag-check"><span class="hag-check-text">%s</span> <button type="button" class="button button-small hag-check-now">%s</button></p>',
			esc_html( $text ),
			esc_html__( 'Check again', 'helloadmin-guard' )
		);
	}

	/**
	 * The exact server rules of a card: folded for Apache (the plugin writes them), open on other servers (copy them).
	 *
	 * @param Feature $feature Feature.
	 * @param bool    $manual  True if the admin has to add the rules by hand.
	 * @return void
	 */
	private function render_rules( Feature $feature, $manual ) {
		// General tab settings work in PHP; their server rules only do the same earlier (and for static files).
		$extra  = ! $feature instanceof Htaccess_Feature;
		$server = Htaccess::server();
		if ( 'nginx' === $server ) {
			$label = $extra ? __( 'Optional: the same for Nginx, to add to the server block', 'helloadmin-guard' ) : __( 'Rules to add to the server block, above the PHP location', 'helloadmin-guard' );
			$lines = $feature->nginx_rules();
		} elseif ( 'apache' === $server ) {
			if ( $extra ) {
				$label = Htaccess::writable() ? __( 'Also added to .htaccess, so the server answers before PHP starts', 'helloadmin-guard' ) : __( 'Optional: the same in .htaccess, to add by hand', 'helloadmin-guard' );
			} else {
				$label = $manual ? __( 'Rules to add to .htaccess by hand', 'helloadmin-guard' ) : __( 'Rules the plugin adds to .htaccess', 'helloadmin-guard' );
			}
			$lines = $feature->apache_rules();
		} else {
			if ( ! $extra ) {
				echo '<p class="description">' . esc_html__( 'This setting needs Apache, LiteSpeed or Nginx.', 'helloadmin-guard' ) . '</p>';
			}
			return;
		}
		if ( ! $lines ) {
			return;
		}
		printf(
			'<details class="hag-rules"%s><summary>%s</summary><pre><code>%s</code></pre></details>',
			$manual && ! $extra ? ' open' : '',
			esc_html( $label ),
			esc_html( implode( "\n", $lines ) )
		);
	}

	/**
	 * Server type and .htaccess state above the Advanced tab settings.
	 *
	 * @return void
	 */
	private function render_server_note() {
		$server = Htaccess::server();
		if ( 'apache' === $server && Htaccess::writable() ) {
			$text = __( 'Some settings on this tab write rules into the .htaccess file in the site root. Before every change the plugin keeps a copy of the file and then opens your home page; if the page answers with a server error, the old file is put back.', 'helloadmin-guard' );
		} elseif ( 'apache' === $server ) {
			$text = __( 'WordPress cannot write the .htaccess file on this site, so the rules below cannot be switched on here. Copy them into .htaccess by hand, or ask your host to make the file writable.', 'helloadmin-guard' );
		} elseif ( 'nginx' === $server ) {
			$text = __( 'This site runs on Nginx, which does not read .htaccess files. Copy the rules from each card into the server block of your site, or send them to your host.', 'helloadmin-guard' );
		} else {
			$text = __( 'The server rules below need Apache, LiteSpeed or Nginx.', 'helloadmin-guard' );
		}
		echo '<p class="hag-intro">' . esc_html( $text ) . '</p>';
	}

	/**
	 * Adds UTM tags to links that lead to helloadmin.com, so the site's own analytics can tell visits from
	 * the plugin apart. Nothing is sent from the user's site; the tags are only part of the link.
	 * Links to other sites are returned unchanged.
	 *
	 * @param string $url      Link.
	 * @param string $campaign Where in the plugin the link is (about, learn-more).
	 * @param string $content  Which link exactly (feature id, contact).
	 * @return string
	 */
	public static function site_link( $url, $campaign, $content = '' ) {
		if ( 'helloadmin.com' !== wp_parse_url( $url, PHP_URL_HOST ) ) {
			return $url;
		}
		$args = array(
			'utm_source'   => 'helloadmin-guard',
			'utm_medium'   => 'plugin',
			'utm_campaign' => $campaign,
		);
		if ( '' !== $content ) {
			$args['utm_content'] = $content;
		}
		return add_query_arg( $args, $url );
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
		echo '<p>' . esc_html__( 'This plugin was built and is maintained by a developer with more than 10 years of experience with WordPress and WooCommerce. Is your site slow, showing errors, broken after an update or hacked? Need someone to look after it? Write to me.', 'helloadmin-guard' ) . '</p>';
		printf(
			'<p><a class="button button-primary" href="%s" target="_blank" rel="noopener">%s</a> <a class="button" href="%s" target="_blank" rel="noopener">%s</a></p>',
			esc_url( self::site_link( 'https://helloadmin.com/#contact', 'about', 'contact' ) ),
			esc_html__( 'Contact HelloAdmin', 'helloadmin-guard' ),
			esc_url( 'https://github.com/HelloAdminCom/helloadmin-guard/issues' ),
			esc_html__( 'Report a problem', 'helloadmin-guard' )
		);
		printf( '<p><a href="%s">%s</a></p>', esc_url( Welcome::url( 'show', 'about' ) ), esc_html__( 'Show the welcome message again', 'helloadmin-guard' ) );
		echo '</div>';
	}
}
