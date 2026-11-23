# Changelog

All notable changes to this project are documented here. Versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.4.2] - 2026-11-23

Fixes from the first wordpress.org review.

### Fixed
- Limit login attempts: logins with an application password (REST API, XML-RPC) skip the `authenticate` filter and `wp_login_failed`, so they were neither counted nor locked. Now they are counted (`application_password_failed_authentication`, except "application passwords are off") and refused while locked (`wp_authenticate_application_password_errors`). An XML-RPC failure that fires both actions counts once.
- Nginx rules to copy used paths relative to the site, so they missed files when WordPress lives in a subfolder. They are built from the full path now. The .htaccess rule for readme.html and other core files takes the WordPress folder into account when WordPress has its own folder.
- When .htaccess could not be written, turning a setting off still saved it as off while its rules stayed in the file (for example the POST block of the login URL setting). Settings page and WP-CLI now keep the switches in line with the file: rules still in the file = setting stays on, new server rules not written = setting stays off. WP-CLI names only the settings that really changed and exits with an error when nothing did.
- Login URL: only links whose path is this site's wp-login.php are changed. Before, any link with "wp-login.php" anywhere (a query argument, another site in a redirect) was rewritten.

### Changed
- Translations are no longer bundled and `load_plugin_textdomain()` is gone: wordpress.org delivers them from translate.wordpress.org. The languages/ folder stays in the repository as the source for that.

## [0.4.1] - 2026-11-19

### Changed
- "Already closed" note: when the plugin cannot tell what closes a gap, it names the theme too (helloadmin.com closes the REST API for guests in its theme, and the note blamed Sucuri).
- readme: FAQ on deactivation and Nginx, Screenshots section, Contributors = helloadmincom.

## [0.4.0] - 2026-11-19

Permissions-Policy that fits the site, and two more server rules.

### Added
- Security headers: three boxes "What does your site use?": quick payment buttons (Apple Pay, Google Pay, Link), visitor location, camera and microphone. Permissions-Policy turns off what is not ticked, and always turns off motion sensors and USB. A box is ticked by default when a plugin that needs it is active (WooCommerce and other payment plugins, store locators and map plugins, video call plugins); the card warns when such a plugin is found and its box is unticked. What is ticked keeps the browser default, so embedded payment and map frames keep working.
- Redirect http:// to https:// (Advanced tab): permanent redirect for every request before WordPress starts, the first rule in the block. Proxies that send X-Forwarded-Proto (Cloudflare) do not loop; /.well-known/ and cPanel .cpaneldcv files stay on http for SSL checks; no rule while the site address is http. The check asks the plain http home page.
- Refuse TRACE and TRACK requests (Advanced tab): 403 before WordPress starts. The check sends a TRACE request; a 2xx answer counts as open.
- After an update to a new version the plugin rewrites its .htaccess block once and checks the site again.

### Changed
- Permissions-Policy without any box ticked: geolocation=() instead of geolocation=(self), and payment=(), accelerometer=(), gyroscope=(), magnetometer=(), usb=() added.
- After writing .htaccess the plugin follows redirects itself, on the https and the plain http home page. More than 5 redirects count as a broken site and the old file is put back (before, a redirect loop was treated as "could not check" and the rules stayed).

## [0.3.0] - 2026-11-18

The plugin now checks what the site really answers and shows it on every card.

### Added
- Every gap is checked from outside, as a visitor who is not logged in (requests from the site to itself; for server rules a few harmless test files in a temporary folder in uploads, removed right after). The result shows on each card: closed by this plugin, already closed by another plugin or the server (with the likely plugin named), open (with the risk), not working, or not needed. Results are kept for a day; "Check again" runs it at once; after a save only the changed settings are checked again, on the same page load.
- "I do not need this on this site": a gap marked like that stops counting as open.
- The number of open gaps next to HeAd Guard in the admin menu (like pending comments), with a setting to turn it off.
- Site Health item: the number of open gaps with a link to the settings page.
- Limit login attempts: after too many failed logins from one IP address (3 to 20, default 5) every login from it is refused for a while (5 to 1440 minutes, default 15), also on XML-RPC and REST API logins.
- Hide the REST API index: /wp-json/ lists no namespaces and routes to visitors; namespace roots answer exactly like a missing namespace; no Link header and <link> tag.
- WP-CLI: wp helloadmin-guard status, check, enable, disable.
- Warnings on cards: public names and author addresses that show the login; display_errors turned on in php.ini.
- General settings also write their server rules into .htaccess (Apache, LiteSpeed): XML-RPC refused before PHP, POST to /wp-login.php refused, security headers and HSTS also on static files, X-Powered-By and the server signature removed. PHP does not send headers the server already sends.
- Welcome message: lines about gaps closed by other plugins and the menu number.

### Changed
- Hide user names: the login form gives one answer for a wrong user name and a wrong password; "Lost your password?" shows the same page whether the user exists or not.
- Card head in one line: switch, title and one status label; the risk shows while the gap is open.
- Close internal WordPress folders also turns off PHP errors for visitors where PHP runs inside the web server.
- "Show the welcome message again" stays on the About tab; the short intro line shows only when the welcome message is removed.
- Card texts: "Description", "Risks", "How to undo"; every risk says what stops working, and starts with "If you use…" where it depends on the site.

### Fixed
- The settings page shows "Settings saved" and the messages about server rules after a save (they were not shown on a page with its own menu item).
- The menu number is red in every admin color scheme and no longer jumps for a moment after a save.
- The menu icon no longer blinks on page load.
- With the custom login address on, the server rule refused POST requests to the custom address instead of /wp-login.php, so nobody could log in until the rule was removed.
- "Check again" shows a spinner and "Checking…" while it runs.

## [0.2.1] - 2026-11-18

### Changed
- Every card on the Advanced tab ("Learn more") links to its own section of the article "Files bots look for on a WordPress site, and the server rules that close them" (helloadmin.com), with UTM tags.
- About tab: the author text is written in the first person, without repeating the name.

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
