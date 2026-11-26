<?php
/**
 * Tips tab: security steps a plugin should not take for you, in three groups.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Each group is a fold-out block like the welcome message; each tip inside folds out too. Tips are ordered from the
 * strongest to the weakest and say plainly what they protect against and what they do not. The only live check is
 * the mail DNS records, and it runs only when the admin asks for it.
 */
class Tips {

	/**
	 * Query argument that runs the mail DNS check.
	 */
	const DNS_ARG = 'hag_mail_dns';

	/**
	 * Transient prefix for the mail DNS check result.
	 */
	const DNS_CACHE = 'helloadmin_guard_mail_dns_';

	/**
	 * HTML allowed in tip texts.
	 *
	 * @var array<string, array<string, bool>>
	 */
	const KSES = array(
		'code'   => array(),
		'strong' => array(),
	);

	/**
	 * Renders the tab.
	 *
	 * @return void
	 */
	public static function render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only DNS lookup, nothing is saved.
		$check = isset( $_GET[ self::DNS_ARG ] );

		echo '<div class="hag-tips">';
		echo '<p class="hag-tips-intro">' . esc_html__( 'Some protection cannot be a switch in a plugin: it lives in your hosting, your DNS or your habits. These are the steps worth taking, strongest first in each group. Each one says what it protects against and what it does not.', 'helloadmin-guard' ) . '</p>';
		foreach ( self::groups() as $id => $group ) {
			$open = $check && 'hosting' === $id;
			printf(
				'<details class="hag-tip-group" name="hag-tip-groups" id="hag-tips-%1$s"%2$s><summary><span class="hag-tip-group-head"><span class="hag-tip-group-title">%3$s</span> <span class="hag-tip-count">%4$s</span></span><span class="hag-tip-group-text">%5$s</span><span class="hag-welcome-arrow" aria-hidden="true"></span></summary><div class="hag-tip-list">',
				esc_attr( $id ),
				$open ? ' open' : '',
				esc_html( $group['title'] ),
				/* translators: %d: number of tips in the group. */
				esc_html( sprintf( _n( '%d tip', '%d tips', count( $group['tips'] ), 'helloadmin-guard' ), count( $group['tips'] ) ) ),
				esc_html( $group['text'] )
			);
			foreach ( $group['tips'] as $key => $tip ) {
				printf(
					'<details class="hag-tip" name="hag-tips-%4$s" id="hag-tip-%1$s"%2$s><summary>%3$s</summary><div class="hag-tip-body">',
					esc_attr( $key ),
					$open && 'mail' === $key ? ' open' : '',
					esc_html( $tip['title'] ),
					esc_attr( $id )
				);
				foreach ( $tip['text'] as $paragraph ) {
					echo '<p>' . wp_kses( $paragraph, self::KSES ) . '</p>';
				}
				if ( ! empty( $tip['code'] ) ) {
					echo '<pre><code>' . esc_html( $tip['code'] ) . '</code></pre>';
				}
				if ( ! empty( $tip['limit'] ) ) {
					printf( '<p class="hag-tip-limit"><strong>%s</strong> %s</p>', esc_html__( 'Does not help against:', 'helloadmin-guard' ), wp_kses( $tip['limit'], self::KSES ) );
				}
				if ( 'mail' === $key ) {
					self::render_mail_check( $check );
				}
				echo '</div></details>';
			}
			echo '</div></details>';
		}
		echo '</div>';
	}

	/**
	 * The three groups with their tips.
	 *
	 * @return array<string, array{title: string, text: string, tips: array<string, array{title: string, text: string[], code?: string, limit?: string}>}>
	 */
	private static function groups() {
		return array(
			'admin'     => array(
				'title' => __( 'Ten minutes in the admin area', 'helloadmin-guard' ),
				'text'  => __( 'Things any admin can do today, without hosting access.', 'helloadmin-guard' ),
				'tips'  => array(
					'two-factor' => array(
						'title' => __( 'Turn on two-factor login for every admin', 'helloadmin-guard' ),
						'text'  => array(
							__( 'A second step at login, a code from an app on your phone, stops someone who guessed, bought or phished an admin password. Stolen and reused passwords are one of the most common ways into a WordPress site.', 'helloadmin-guard' ),
							__( 'Free options: the Two Factor plugin, made by WordPress core contributors, or the login security part of a security plugin you already use.', 'helloadmin-guard' ),
						),
						'limit' => __( 'holes in plugins and themes: they let an attacker in without logging in at all.', 'helloadmin-guard' ),
					),
					'updates'    => array(
						'title' => __( 'Keep plugins and themes updated, delete the ones you do not use', 'helloadmin-guard' ),
						'text'  => array(
							__( 'Most break-ins go through a known hole in an old plugin or theme. Turn on auto-updates for plugins you trust (the Auto-updates column on the Plugins screen); minor WordPress releases update themselves by default.', 'helloadmin-guard' ),
							__( 'Delete plugins and themes you have switched off: their files stay on the server and can still be opened directly. Until you delete them, the <strong>Block PHP files of inactive plugins</strong> setting on the Advanced tab keeps them shut.', 'helloadmin-guard' ),
							__( 'Never install "nulled" copies of paid plugins from unofficial sites. Many of them come with a backdoor already inside.', 'helloadmin-guard' ),
						),
						'limit' => __( 'a site that is already hacked: an update replaces the plugin files, but not the backdoors an attacker left elsewhere.', 'helloadmin-guard' ),
					),
					'roles'      => array(
						'title' => __( 'Keep the number of admins small', 'helloadmin-guard' ),
						'text'  => array(
							__( 'Every admin account is a full key to the site. Give people the lowest role that does the job: Editor for content, Shop manager for orders. Remove the accounts of people and agencies who no longer work on the site.', 'helloadmin-guard' ),
						),
						'limit' => __( 'plugin holes that run code without a login.', 'helloadmin-guard' ),
					),
					'passwords'  => array(
						'title' => __( 'A different password for each admin, kept in a password manager', 'helloadmin-guard' ),
						'text'  => array(
							__( 'Passwords leaked from other sites are tried on WordPress logins every day. A password manager makes a long unique password as easy to use as a short one.', 'helloadmin-guard' ),
						),
						'limit' => __( 'phishing pages and malware on your own computer. Two-factor login covers part of that.', 'helloadmin-guard' ),
					),
					'register'   => array(
						'title' => __( 'Keep registration closed unless you need it', 'helloadmin-guard' ),
						'text'  => array(
							__( 'In Settings → General, leave "Anyone can register" off unless the site needs accounts, and keep "New User Default Role" at Subscriber or Customer. A role with more rights there gives it to everyone who signs up.', 'helloadmin-guard' ),
							__( 'The <strong>E-mail me about new administrators</strong> setting on the General tab also e-mails you when registration opens with a risky role.', 'helloadmin-guard' ),
						),
					),
				),
			),
			'hosting'   => array(
				'title' => __( 'Hosting and DNS', 'helloadmin-guard' ),
				'text'  => __( 'Done once, in the hosting panel or at the domain registrar.', 'helloadmin-guard' ),
				'tips'  => array(
					'backups'  => array(
						'title' => __( 'Backups kept away from the server, and one test restore', 'helloadmin-guard' ),
						'text'  => array(
							__( 'A backup that lives on the same server can be deleted or encrypted together with the site. Keep copies somewhere else, for example in your host\'s backup service or in cloud storage, for at least a few weeks: an infection is often found weeks after it started.', 'helloadmin-guard' ),
							__( 'Restore a backup to a test copy once, so you know it works before you need it.', 'helloadmin-guard' ),
						),
						'limit' => __( 'the hole that let the attacker in. After a restore, close it, or the site gets hacked again.', 'helloadmin-guard' ),
					),
					'panel'    => array(
						'title' => __( 'Two-factor login for the hosting panel, SFTP or SSH instead of FTP', 'helloadmin-guard' ),
						'text'  => array(
							__( 'Whoever gets into the hosting panel or FTP gets every file and the database, and no WordPress setting can stop them. Turn on two-factor login in the hosting panel and at your domain registrar.', 'helloadmin-guard' ),
							__( 'Plain FTP sends the password unencrypted. Use SFTP or SSH, with a key instead of a password if your host allows it.', 'helloadmin-guard' ),
						),
					),
					'isolate'  => array(
						'title' => __( 'One site, one hosting user', 'helloadmin-guard' ),
						'text'  => array(
							__( 'When several sites run under one hosting user, a hole in one of them lets the attacker change the files of all the others. Cleaning one site and leaving the rest infected is a common reason a site gets hacked again a week later.', 'helloadmin-guard' ),
							__( 'Ask your host for a separate user, or at least a separate PHP pool, for each site.', 'helloadmin-guard' ),
						),
					),
					'firewall' => array(
						'title' => __( 'A firewall in front of the site', 'helloadmin-guard' ),
						'text'  => array(
							__( 'A web application firewall at your host or a service like Cloudflare stops much of the bot traffic and known attacks before they reach WordPress, and takes load off the server.', 'helloadmin-guard' ),
						),
						'limit' => __( 'an outdated plugin. New or unusual attacks still get through, so updates still matter.', 'helloadmin-guard' ),
					),
					'php'      => array(
						'title' => __( 'A PHP version that still gets security fixes', 'helloadmin-guard' ),
						'text'  => array(
							/* translators: %s: PHP version of this site. */
							sprintf( __( 'This site runs PHP %s. Versions past their end of life get no security fixes; Tools → Site Health warns about them. Your host can switch the version in the panel. Try the new one on a copy first: very old plugins can break.', 'helloadmin-guard' ), '<code>' . PHP_VERSION . '</code>' ),
						),
					),
					'mail'     => array(
						'title' => __( 'SPF, DKIM and DMARC records for your domain', 'helloadmin-guard' ),
						'text'  => array(
							__( 'These DNS records tell mail servers which servers may send mail for your domain. Without them, password reset e-mails and this plugin\'s alerts often land in spam, and anyone can send mail that looks like it comes from your domain.', 'helloadmin-guard' ),
							__( 'Your host or mail provider gives you the exact values; you add them at your DNS provider.', 'helloadmin-guard' ),
						),
						'limit' => __( 'look-alike domains. DMARC stops fake mail from your exact domain only.', 'helloadmin-guard' ),
					),
				),
			),
			'developer' => array(
				'title' => __( 'For developers', 'helloadmin-guard' ),
				'text'  => __( 'Server and code settings for sites you deploy and look after.', 'helloadmin-guard' ),
				'tips'  => array(
					'file-mods' => array(
						'title' => __( 'Lock file changes on sites deployed with Git', 'helloadmin-guard' ),
						'text'  => array(
							__( 'When code reaches the site only through your deploy, turn off installing, updating and editing from the admin area. Then a stolen admin password alone cannot upload a plugin with a backdoor. Updates then come only through your deploy too. Add this line to wp-config.php:', 'helloadmin-guard' ),
						),
						'code'  => "define( 'DISALLOW_FILE_MODS', true );",
						'limit' => __( 'holes in the plugins that are already installed.', 'helloadmin-guard' ),
					),
					'checksums' => array(
						'title' => __( 'Compare files with the originals on WordPress.org', 'helloadmin-guard' ),
						'text'  => array(
							__( 'WP-CLI checks WordPress and plugin files against the copies on WordPress.org and lists every changed or extra file. On sites deployed with Git, git status does the same for your own code. Run it from cron and send yourself the output.', 'helloadmin-guard' ),
						),
						'code'  => "wp core verify-checksums\nwp plugin verify-checksums --all",
						'limit' => __( 'premium plugins and themes: they are not on WordPress.org, so there is nothing to compare them with.', 'helloadmin-guard' ),
					),
					'config'    => array(
						'title' => __( 'Protect wp-config.php', 'helloadmin-guard' ),
						'text'  => array(
							__( 'It holds the database password and the secret keys. Set its permissions to 400 or 440, so only the owner can read it, or move it one folder above the site root: WordPress finds it there by itself.', 'helloadmin-guard' ),
						),
						'code'  => 'chmod 440 wp-config.php',
					),
					'salts'     => array(
						'title' => __( 'New secret keys after a break-in', 'helloadmin-guard' ),
						'text'  => array(
							__( 'New keys and salts in wp-config.php log everyone out, and login cookies made with the old keys stop working, even if the attacker read wp-config.php. The <strong>Log out everyone</strong> button on the If hacked tab ends all sessions and deletes application passwords, but keeps the keys. Do both, and give every admin a new password.', 'helloadmin-guard' ),
						),
						'code'  => 'wp config shuffle-salts',
					),
					'admin-ip'  => array(
						'title' => __( 'Limit the login page by IP address or a second password', 'helloadmin-guard' ),
						'text'  => array(
							__( 'An IP allow list or HTTP Basic Auth on wp-login.php and wp-admin stops password guessing before WordPress even starts. Leave wp-admin/admin-ajax.php open: forms and plugins on the public pages use it.', 'helloadmin-guard' ),
						),
						'limit' => __( 'plugin holes reached through admin-ajax.php or the REST API, which stay open to everyone.', 'helloadmin-guard' ),
					),
					'db-user'   => array(
						'title' => __( 'A database user with only the rights WordPress needs', 'helloadmin-guard' ),
						'text'  => array(
							__( 'A separate database user for each site keeps one hacked site out of the others\' databases. Taking away more rights helps less than it seems: updates and plugin activation need CREATE and ALTER, and without them updates fail quietly.', 'helloadmin-guard' ),
						),
					),
					'prefix'    => array(
						'title' => __( 'A table prefix other than wp_', 'helloadmin-guard' ),
						'text'  => array(
							__( 'Last on the list for a reason. An SQL injection that can read your data can read the table names too, so a different prefix stops only the most basic automated attacks. Choose one when you install a new site; on a live site, change it only with a fresh backup and a tested script.', 'helloadmin-guard' ),
						),
					),
				),
			),
		);
	}

	/**
	 * Domain whose mail records matter: the site host without "www.", the domain WordPress sends mail from.
	 *
	 * @return string
	 */
	private static function mail_domain() {
		$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		return 0 === strpos( $host, 'www.' ) ? substr( $host, 4 ) : $host;
	}

	/**
	 * The SPF and DMARC records of the domain, from DNS, kept for 10 minutes. DKIM cannot be looked up without the
	 * selector your mail provider uses, so it is not checked.
	 *
	 * @param string $domain Domain.
	 * @return array{spf: string, dmarc: string, error: bool}
	 */
	private static function mail_records( $domain ) {
		$key    = self::DNS_CACHE . md5( $domain );
		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['spf'], $cached['dmarc'], $cached['error'] ) ) {
			return $cached;
		}
		$result = array(
			'spf'   => '',
			'dmarc' => '',
			'error' => false,
		);
		if ( ! function_exists( 'dns_get_record' ) ) {
			$result['error'] = true;
			return $result;
		}
		foreach ( array(
			'spf'   => $domain,
			'dmarc' => '_dmarc.' . $domain,
		) as $type => $name ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a failed lookup returns false and warns; the false is handled below.
			$records = @dns_get_record( $name, DNS_TXT );
			if ( false === $records ) {
				$result['error'] = true;
				continue;
			}
			$prefix = 'spf' === $type ? 'v=spf1' : 'v=dmarc1';
			foreach ( $records as $record ) {
				$text = isset( $record['txt'] ) ? (string) $record['txt'] : '';
				if ( 0 === stripos( $text, $prefix ) ) {
					$result[ $type ] = $text;
					break;
				}
			}
		}
		set_transient( $key, $result, 10 * MINUTE_IN_SECONDS );
		return $result;
	}

	/**
	 * The mail DNS check inside its tip: a link before, the records after.
	 *
	 * @param bool $run Whether the admin asked for the check.
	 * @return void
	 */
	private static function render_mail_check( $run ) {
		$domain = self::mail_domain();
		if ( ! $run ) {
			printf(
				'<p><a class="button" href="%s">%s</a></p>',
				esc_url( add_query_arg( self::DNS_ARG, 1, admin_url( 'admin.php?page=' . Admin_Page::SLUG . '&tab=tips' ) ) . '#hag-tip-mail' ),
				/* translators: %s: domain name. */
				esc_html( sprintf( __( 'Check the records of %s', 'helloadmin-guard' ), $domain ) )
			);
			return;
		}
		$records = self::mail_records( $domain );
		/* translators: %s: domain name. */
		echo '<p class="hag-tip-check-head"><strong>' . esc_html( sprintf( __( 'DNS records of %s:', 'helloadmin-guard' ), $domain ) ) . '</strong></p><ul class="hag-tip-check">';
		foreach ( array(
			'spf'   => 'SPF',
			'dmarc' => 'DMARC',
		) as $type => $label ) {
			if ( '' !== $records[ $type ] ) {
				printf( '<li class="hag-tip-found">%s: <code>%s</code></li>', esc_html( $label ), esc_html( $records[ $type ] ) );
			} else {
				/* translators: %s: record type, SPF or DMARC. */
				printf( '<li class="hag-tip-missing">%s</li>', esc_html( sprintf( __( '%s: not found.', 'helloadmin-guard' ), $label ) ) );
			}
		}
		echo '<li class="hag-tip-unknown">' . esc_html__( 'DKIM: not checked here, because its address depends on your mail provider. Ask them, or send a test e-mail to a checker such as mail-tester.com.', 'helloadmin-guard' ) . '</li>';
		echo '</ul>';
		if ( $records['error'] ) {
			echo '<p class="description">' . esc_html__( 'The server could not ask DNS for some of the records, so "not found" may be wrong. Check them with your DNS provider.', 'helloadmin-guard' ) . '</p>';
		}
		if ( '' !== $records['dmarc'] && preg_match( '/\bp\s*=\s*none\b/i', $records['dmarc'] ) ) {
			echo '<p class="description">' . esc_html__( 'DMARC is in watch mode (p=none): it reports fake mail but does not stop it. Once the reports look clean, move to p=quarantine.', 'helloadmin-guard' ) . '</p>';
		}
	}
}
