# Changelog

All notable changes to this project are documented here. Versions follow [Semantic Versioning](https://semver.org/).

## [0.7.0] - 2026-11-26

### Added
- Tips tab: one group and one tip open at a time, the opened title slides to the top; three fold-out groups (ten minutes in the admin area, hosting and DNS, for developers), strongest tip first, each with what it does not protect against; SPF and DMARC check of the site domain on request (cached 10 minutes); current PHP version in its tip.
- E-mail me about abandoned and closed plugins (new alert card), a plugin missing from WordPress.org is e-mailed only when two update checks in a row miss it: plugins not tested with the last 3 major WordPress releases and plugins WordPress.org no longer answers for, from the update_plugins data; plugins with an Update URI elsewhere are left out; e-mail when a new one shows up.
- Card views: full description, short description with Details (default), no description; saved per user; cut titles show in full in a hint; when cards stand in one column (phones, tablets, narrow windows) an opened card slides to the top of the screen.
- upgrade.php: a must-use file (helloadmin-guard-upgrade.php) answers 404 to visitors who are not logged in, also with a fake login cookie, unless a database update is due. Written while Hide files that show versions is on, removed on deactivation and uninstall; the must-use alert ignores it only when it is byte for byte the plugin's own.
- WP-CLI: --slug for enable login_url, --server for enable, disable and sync.

### Changed
- One core (Settings_Apply) applies settings for the admin page and WP-CLI.
- WP-CLI takes the web server type from admin requests (option helloadmin_guard_server) or --server instead of guessing Apache from an existing .htaccess.
- Narrow screens: status label under the title, view buttons on their own line, the Hide user names table as stacked rows, long code wraps.
- wp helloadmin-guard sync reports a rewrite when the rules inside the block changed.

### Fixed
- WP-CLI could enable login_url without a login address and write the login POST rule, locking everyone out; the rule is now written only with a saved address and never on multisite (the admin page could do the same on multisite).
- WP-CLI reported server rules as enabled on Nginx when an old .htaccess file was present.
- When the old .htaccess could not be written back after a broken write, the new rules stayed in the file but their settings were switched off.
- The e-mail about "Log out everyone" also goes to the "Also send to" addresses of the e-mail alerts; on multisite the card hints name security plugins activated for the whole network too.
- Self-heal no longer writes rules that broke the home page last time (for example after a failed restore the admin removed the block by hand, and every admin visit put the breaking rules back); saving the settings still tries, a successful write clears the mark.
- On Apache with a read-only .htaccess, saving the Advanced tab unchanged no longer reports that the rules were not saved (settings whose rules are in the file keep their value).
- The list of users whose login shows is cached for 10 minutes and dropped on user changes and Fix (big sites); the upgrade.php file check skips AJAX requests.
- Saving the settings from code without display preferences no longer resets them; notices keep their type (info, success) on the settings page.
- WP-CLI: settings that also work in PHP (XML-RPC, login URL) are reported as enabled when only their server rules could not be written; the upgrade.php file follows the setting at once, also from WP-CLI.
- The inactive plugins check loads plugin.php itself; the e-mail about a changed must-use list names the files as watched files.

## [0.6.1] - 2026-11-25

### Changed
- Self-heal of .htaccess skips reading the file while its time, size and the expected rules are unchanged since it last matched (option helloadmin_guard_heal_seen).
- Check results, the visitor IP, the must-use file list, the active plugin list (security headers), the version hash and the REST keep_open list are read once per request.
- The site check after writing .htaccess shares a 20 second limit; running out of time counts as not checked.
- HSTS from PHP also when X-Forwarded-Proto is https.

### Fixed
- A failed rollback of .htaccess after a broken write is reported as restore_failed (admin notice and WP-CLI) instead of looking like a restored file.
- Settings fields that are not arrays are dropped; empty extra addresses no longer go into the deactivation e-mail.
- One helper for the paths of server rules.

## [0.6.0] - 2026-11-25

### Added
- Core REST API only for logged-in users (new card): wp/v2, wp-site-health/v1, wp-block-editor/v1 and wp-abilities/v1 answer 401 to visitors who are not logged in, also inside batch calls; oEmbed and plugin routes stay open; "Keep open for visitors" field for exceptions.
- Limit login strict mode: failed logins from all addresses together lock new devices out; known browsers (signed cookie) and password resets still get in; one e-mail a day about the attack; application passwords not affected.
- Hide user names: list of users whose login shows (public name or author page address) with a Fix button per user (public name, author address, old comments), without a page reload; new users who can write posts are marked.
- Hide user names: author pages for visitors can redirect to the blog page or answer 404, the same answer for every /author/ address.
- E-mail me about new administrators: also when anyone can register with a role that has dangerous rights (card warning too), when the e-mail address or password of an administrator changes, and when the site e-mail address changes (the old address gets it too).
- E-mail me about changed must-use plugins and site files: also wp-config.php, index.php, .user.ini, php.ini and PHP files in subfolders of mu-plugins (folders managed by hosts are skipped).

### Fixed
- Alert lists without a signature now count as changed outside the plugin (before, dropping the signature in the database made a planted list trusted).
- Admin alert finds administrator rights stored as 1 or "1", not only true.
- Hide user names: a REST batch call to /wp/v2/users by a visitor caused a PHP fatal error in WordPress 7 (the refusal was a WP_Error, now a response).
- Limit login: views of the login page during a lockout no longer count as failed logins.
- Limit login strict mode: the lock is extended only when failures reach the limit again (a few bot tries no longer keep it on), known-browser cookies end with a password change, Cloudflare ranges can be updated with the helloadmin_guard_cloudflare_ranges filter.
- File alert: SHA-256 instead of MD5 (old MD5 lists move over quietly).
- Uninstall removes login limit counters too, and cleans every site of a network.
- Disable the file editor counts DISALLOW_FILE_MODS and the edit_files right.
- Menu number: settings that are not checked yet do not count while the first check after an update runs.
- .htaccess: more than one plugin block in the file counts as a mismatch, the next sync leaves one.
- Visitor IP: CF-Connecting-IP is used only when the request comes from a Cloudflare address. Limit login warns when the site sees a loopback or private address on a public site (a local proxy that does not pass the visitor IP); FAQ entry on the helloadmin_guard_client_ip filter.
- Smaller: header flag found after a double internal redirect, WP-CLI handles a damaged option and notes the Nginx case, XML-RPC limit mode filter runs last.

## [0.5.2] - 2026-11-24

### Security
- E-mail me about new administrators: the list of administrators is read from the capabilities meta for the Administrator role, for every other role that holds manage_options, promote_users, edit_plugins, activate_plugins, install_plugins or unfiltered_upload (roles read straight from the user_roles option), and for accounts with one of these rights added to the account itself. Before, a custom role like "adm1n" with full rights, or rights added to an editor, went unnoticed. Any role change and any change of the capabilities meta queues the check. New card warning for administrators without the Administrator role.
- Security headers and HSTS: PHP skipped its headers whenever the rules had been written to .htaccess, so with mod_headers off, or with the rules removed by hand, nobody sent them. The rules now also set a request header (RequestHeader, same module) and PHP skips only when the request carries it.
- Self-heal of .htaccess: the block in the file is compared with the block the settings would write, so removed or edited rules are rewritten too, not only rules of settings that are off. A 2-minute lock is taken before writing, so parallel requests to the login page do not all write and check the file. The check after writing waits 10 s per request instead of 15.
- No PHP in the uploads folder: also files with a second extension (shell.php.jpg), which run as PHP on servers set up with AddHandler.
- Hide user names: oEmbed answers lose author_name too (the login by default), comments of registered users lose the comment-author-<nicename> class.
- Turn off XML-RPC: the request is refused in enable() on plugins_loaded, not at the end of plugins_loaded.

### Changed
- Block hidden files: also Thumbs.db, desktop.ini (Windows) and __MACOSX folders from zip archives made on a Mac, in any letter case; the card explains what .DS_Store is.
- Block logs: error_log.N, php_errorlog, numbered .log copies; phpinfo.php and php_info.php in any folder (info.php and test.php stay root-only: plugins may ship files with these names).
- Block backups: .sql.bz2, .sql.xz, .dump, .jpa (Akeeba), .tar.bz2, .tbz2, .bz2, .tar.xz, .xz archives, wp-content/wpvividbackups and wp-content/backwpup-*; the wp-config rule needs a dot after the name, so /wp-config-tips (a post without the trailing slash) reaches WordPress.
- Login URL: the POST rule for wp-login.php is case-insensitive; may-break text mentions page caches that store pages of logged-in users.
- Nginx texts: ^~ locations for wp-admin/includes and wp-includes/theme-compat (win over a PHP location placed above them), a note on server_name for the http to https block.
- Deactivation e-mail goes also to the extra address of every e-mail setting that is on, not only the first one.
- Multisite: the "If hacked" button only for super admins (it ends sessions of every user of the network), e-mail to the network address.
- Limit login: card warning when REMOTE_ADDR is a Cloudflare address (the host does not pass the real visitor IP).
- Status check: leftover test folders older than ten minutes are removed (a PHP fatal error skips "finally").
- uninstall.php also deletes the status and heal transients.
- Texts and uk/ru translations updated for the cards above.

## [0.5.1] - 2026-11-24

### Security
- Hide user names: the REST API user list stayed open to visitors under another letter case. The REST server matches routes case-insensitively (/wp/v2/USERS reaches the users endpoint), the plugin compared the route case-sensitively. Now the check is case-insensitive, and the status check also requests /wp/v2/Users.
- Redirect http:// to https://: the rule builds the target from the Host header, so on a server that answers any host name it redirected to that name. Now it runs only for the host names of home and siteurl (with or without www.); other names pointed at the site are left to WordPress.
- The check that the site still opens after a .htaccess change followed any redirect. Now it follows only redirects to the site's own host; a redirect elsewhere counts as an answer.

### Changed
- uninstall.php removes the plugin's .htaccess block too (deactivation already does), for a block left behind, for example by a restored backup.
- README.md lists the current settings.

## [0.5.0] - 2026-11-23

### Security
- Login URL: the new address leaked to any visitor. /wp-signup.php and /wp-register.php redirect to the registration link, and the plugin rewrote that link to the new address. Now visitors who are not logged in get the new address only on the login page itself (form, lost password, redirects after a login action); everywhere else their links keep wp-login.php, which answers 404. Exception: when anyone can register, the registration link shows it. Sites on 0.4.x with this setting on should change their login address after the update.
- E-mail settings: the lists of administrators and must-use files are stored with an HMAC signature keyed with the WordPress salts (wp-config.php, not the database). Someone who can only write to the database (SQL injection in another plugin) can no longer add himself to the list, or delete it, to stay quiet: a list changed or missing outside the plugin sends one e-mail with all current administrators or files. The lists are created only when the setting is switched on (settings page or WP-CLI), not rebuilt when they go missing. Expected once after a salt change or a database copied to another site.
- E-mail when HelloAdmin Guard is deactivated (who, from which IP), sent when one of the e-mail settings is on: an intruder with an administrator account would turn the plugin off first.
- .htaccess: a feature whose rule lines hold a line break or another control character is left out of the file (second layer: some rules are built from the site address in the database, and PHP's parse_url() already replaces control characters there).

### Added
- Setting "E-mail me about new administrators": the role hooks report a new admin at once; a regular check compares the administrators read straight from the capabilities meta (past user query filters) with the last list, so accounts written into the database or hidden from the Users screen are reported too. The card warns about admins hidden from the Users screen, hex-like names ("a3f9c2") and admins added in the last 7 days.
- Setting "E-mail me about new must-use plugins": new and changed files in wp-content/mu-plugins (also dot files) and known drop-ins, compared by MD5; warnings for dot files and decoded code (base64/eval patterns).
- Both e-mail settings run their check without WP-Cron (Feature::watch()): after every login (login form or application password), on admin pages at most every 5 minutes, and on any request at most once an hour. WP-Cron runs only on visits and never with DISABLE_WP_CRON and no server cron.
- "If hacked" tab: one button ends every session of every user except the current one and deletes all application passwords, with a confirmation box and a short response checklist.
- XML-RPC card: choose between turning XML-RPC off and keeping it for Jetpack and apps with pingbacks off. The description says that multicall brute force is limited in core since 4.4.
- The plugin keeps .htaccess in line with its settings: when the file holds rules of settings that are off on this site (a .htaccess copied from production to staging or local, a move to new hosting), it rewrites its block on the login page or in the admin area. Before, a copied login URL rule closed the login form with a 404. Writes only when they differ; after a failed write it waits 10 minutes before the next try.
- WP-CLI: wp helloadmin-guard sync rewrites the block from the saved settings and names what it removed or added.

### Changed
- Menu number and status chips: the stored check results keep a fingerprint of the settings they were taken with. Settings changed outside the settings page (SQL, database import, code) mark those cards as not checked, and they are checked again on the next settings page load. Before, a card could show "Not working" after it had been turned on that way.
- E-mail settings that are off are not counted as open gaps and show the chip "Off, <risk>" instead of "Cannot be checked".
- Login URL card: "How to undo" also says to delete the .htaccess block after renaming the plugin folder.
- New icon: three gears in the head (tools/build-gears-icon.py; tools/build-shield-icon.py for a shield variant).
- readme FAQ: renaming the plugin folder does not remove the .htaccess block, delete it by hand; same when .htaccess is read-only on deactivation; copying a site to another server.

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
