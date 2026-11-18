# Changelog

All notable changes to this project are documented here. Versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Advanced tab, "Files the server should not give out": hide files that show versions (readme, changelog, license, wp-config-sample.php; install.php, setup-config.php and upgrade.php for guests, where WordPress loads no plugins), block hidden files (.git, .env), backups (.sql, .bak, .wpress, root archives), logs (debug.log, error_log) and leftover test pages (phpinfo.php), no PHP in the uploads folder. The rules go into .htaccess between "# BEGIN HelloAdmin Guard" and "# END HelloAdmin Guard", at the top of the file. Before each change the plugin keeps a copy of the file (in the database) and opens the home page; on a server error the old file is put back and the settings stay off.
- Each server rule card shows the exact lines it writes; on Nginx, or when .htaccess is not writable, the cards show the rules to copy and the switch is disabled.
- HTTPS only (HSTS) as its own setting on the Advanced tab: max-age one year, no subdomains, sent only over HTTPS.
- Server rules are removed when the plugin is deactivated and written again when it is activated.
- Welcome message on the settings page for each admin until closed; "Show the welcome message again" on the About tab.

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
