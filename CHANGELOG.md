# Changelog

All notable changes to this project are documented here. Versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.2.0] - 2026-11-18

Advanced tab with server rules, settings ordered by risk.

### Added
- Advanced tab with server rules written into .htaccess between "# BEGIN HelloAdmin Guard" and "# END HelloAdmin Guard", at the top of the file. Before each change the plugin keeps a copy of the file (in the database, not as a downloadable file) and opens the home page; on a server error the old file is put back and the settings stay off. The rules are removed on deactivation and written again on activation.
- Server rule settings: block hidden files (.git, .env); block backups and database dumps (.sql, .sqlite, .wpress, .bak, wp-config copies, root archives, Duplicator installer.php, backup plugin folders); no PHP in the uploads folder; block logs and diagnostic pages (debug.log, error_log, wc-logs, wflogs, phpinfo.php, server-status); turn off folder listings; close internal WordPress folders (WordPress hardening guide rules); hide files that show versions (readme.html, plugin readme files, wp-links-opml.php, install.php, setup-config.php, upgrade.php for guests: WordPress loads no plugins on those pages); block developer and build files (composer, npm, source maps, Docker and deploy files).
- Each server rule card shows the exact lines; on Nginx, or when .htaccess is not writable, the switch is disabled and the rules are shown to copy.
- HTTPS only (HSTS) as its own setting on the Advanced tab: max-age one year, no subdomains, sent only over HTTPS.
- Settings on each tab are ordered by risk (biggest first) with a risk label on every card; topic groups are gone.
- Welcome message on every tab of the settings page: the arrow or "Got it" folds it into one line (remembered per user, works without JavaScript), the X removes it; "Show the welcome message again" on the About tab.
- Ukrainian and Russian translations for everything new.

### Changed
- Hide the WordPress version also removes the X-Powered-By header.
- Settings page logo: 5 px gap to the title.
- Cards: "What may break" is now "Downsides"; cards in one row have the same height; the intro, the welcome message and the About card are as wide as the first card column.
- "Learn more" links for the backups, logs and PHP in uploads cards.

## [0.1.0] - 2026-11-17

First public version: seven settings, each one off until turned on.

### Added
- Plugin skeleton: settings page in its own admin menu (after Settings) with General, Advanced and About tabs.
- Base `Feature` class: every setting describes what it does, what may break and how to undo it.
- Coding standards (PHPCS with WordPress Coding Standards, PHPCompatibilityWP), PHPStan, GitHub Actions.
- Plugin icon (head with a gear) in the admin menu and on the settings page; menu label "HeAd Guard".
- Custom login URL: the login page moves to /your-address/, /wp-login.php and /wp-admin/ answer 404 to visitors who are not logged in. Single sites only for now.
- Turn off Application Passwords: they stop working for all users and the profile section is hidden; existing passwords are kept and work again when the setting is off.
- Hide user names: ?author=N answers 404, REST /wp/v2/users needs a login, no author link in oEmbed, no users sitemap (visitors who are not logged in).
- Hide the WordPress version: no generator tag in pages and feeds, core ?ver=X.Y.Z replaced with a hash (caches still refresh after updates).
- Security headers: X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy on pages, the login page and wp-admin; headers already set are not duplicated. HSTS is left for a separate setting.
- Turn off XML-RPC: /xmlrpc.php answers 403, no X-Pingback header, no RSD link.
- Turn off the file editor: theme and plugin file editors removed from wp-admin (like DISALLOW_FILE_EDIT, without editing wp-config.php).
- Field hints under the fields; links to helloadmin.com carry UTM tags (utm_source=helloadmin-guard), nothing is sent from the site.
- Ukrainian and Russian translations (languages/), loaded with the plugin.
