=== HelloAdmin Guard ===
Contributors: helloadmincom
Tags: security, hardening, login, htaccess, headers
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress security hardening without upsells. Every setting explains what it does, what may break and how to undo it.

== Description ==

Most WordPress sites leave the same things open: a login page anyone can hit, public user names, an old XML-RPC endpoint, versions in the page source. HelloAdmin Guard closes them with simple on/off settings.

What makes it different:

* Every setting tells you what it does, what may break and how to turn it back, right next to the switch.
* Everything is off until you turn it on. Nothing changes on activation.
* No paid version, no locked settings, no ads, no calls to outside servers.

= Settings in this version =

The settings are listed from the biggest risk down to the cheap rules. The plugin checks what your site really answers to visitors and shows on every card whether the gap is closed by this plugin, already closed by another plugin or the server, or still open. The number of open gaps shows next to the menu item and in Site Health.

General tab

* Turn off or limit XML-RPC. Off: /xmlrpc.php answers 403. Or keep it for Jetpack and the mobile apps and only turn off pingbacks, which bots use to flood other sites.
* Turn off the file editor. Removes the theme and plugin file editors from wp-admin, without editing wp-config.php.
* Custom login URL. The login page moves to your own address; /wp-login.php and /wp-admin/ show a 404 page to visitors who are not logged in.
* Hide user names. Closes /?author=1, the REST API user list, oEmbed author links and the users sitemap for visitors who are not logged in. Lists users whose login shows on the site, with a Fix button; author pages can redirect to the blog or answer 404.
* Turn off Application Passwords. They stop working for everyone; existing passwords are kept and work again when you turn the setting off.
* Security headers. X-Content-Type-Options, X-Frame-Options, Referrer-Policy and Permissions-Policy. You tick what your site uses (quick payment buttons, visitor location, camera and microphone); the rest is turned off. Boxes for known plugins, like WooCommerce or a store locator, are ticked for you.
* Limit login attempts. Too many failed logins from one IP address lock it out for a while. A strict mode also stops attacks from many addresses: new devices wait, known browsers get in.
* Hide the WordPress version. Removes it from the generator tag, feeds and core file links, and removes the X-Powered-By header.
* Hide the REST API index. /wp-json/ no longer lists installed plugins to visitors, and the card warns about random-looking REST routes.
* Core REST API only for logged-in users. Posts, pages, media and the other core routes answer only logged-in users; plugin routes stay open.
* E-mail me about new administrators. A message when an account becomes an administrator, also when it was written straight into the database or hidden from the Users screen; also when an administrator's e-mail or password changes, the site e-mail changes, or anyone can register with a dangerous role.
* E-mail me about changed must-use plugins and site files. A message about new and changed files in wp-content/mu-plugins and its subfolders, drop-ins like db.php, wp-config.php, index.php, .user.ini and php.ini, where malware likes to hide.

The two e-mail settings do not depend on WP-Cron: they check after every login, on admin pages and at least once an hour while the site gets visits.

Advanced tab: server rules (Apache and LiteSpeed write them into .htaccess, Nginx shows them to copy)

* Block hidden files: .git, .env, .htpasswd and everything else that starts with a dot.
* Block backups and database dumps: .sql, .wpress, .bak, wp-config copies, archives in the site root, Duplicator installer leftovers, backup plugin folders.
* No PHP in the uploads folder.
* Block logs and diagnostic pages: debug.log, error_log, WooCommerce logs, phpinfo.php and other test pages.
* Turn off folder listings.
* Redirect http:// to https://, for every file, before WordPress starts. Safe behind Cloudflare; SSL certificate checks stay on http.
* HTTPS only (HSTS).
* Close internal WordPress folders: the rules from the WordPress hardening guide.
* Hide files that show versions: readme.html, plugin readme files, install.php and upgrade.php for guests.
* Block developer and build files: composer and npm files, source maps, Docker and deploy files.
* Block PHP files of inactive plugins: they answer 404 when opened by address.
* Refuse TRACE and TRACK requests, which security scanners report.

If hacked tab

* One button ends every session of every user except yours and deletes all application passwords. Changing a password alone does not log out someone who is already in.
* A short checklist of what to do next.

Before writing .htaccess the plugin keeps a copy of it and then opens your home page. If the page answers with a server error or goes round in redirects, the old file is put back and the settings stay off. Its rules sit between "# BEGIN HelloAdmin Guard" and "# END HelloAdmin Guard" and are removed when the plugin is deactivated.

The plugin is available in English, Ukrainian and Russian.

== Installation ==

1. Upload the plugin to wp-content/plugins/helloadmin-guard, or install it from Plugins > Add New.
2. Activate it.
3. Open HeAd Guard in the admin menu (below Settings), read each setting and turn on what you need.

== Frequently Asked Questions ==

= I turned something on and can't log in. What now? =

Rename the folder wp-content/plugins/helloadmin-guard over FTP or in your hosting file manager. WordPress turns the plugin off and every setting stops working at once. A renamed plugin cannot clean up after itself, so also open .htaccess in the site root and delete everything between "# BEGIN HelloAdmin Guard" and "# END HelloAdmin Guard" (with the login URL setting, that block closes the login form on wp-login.php). Rename the folder back and fix the setting in the HelloAdmin Guard menu.

= Does it work together with other security plugins? =

Yes. The plugin checks what the site answers, so when Wordfence, Solid Security or your host already closes a gap, the card says so and you can leave that switch off. Do not turn on the same login URL change in two plugins: they will get in each other's way.

= Is there a command line? =

Yes: wp helloadmin-guard status, check, enable, disable and sync.

= Does it work on multisite? =

Not yet. The custom login URL does nothing on multisite; the other settings are tested on single sites only.

= What happens when I deactivate or delete the plugin? =

Deactivating turns every setting off at once and removes the plugin's block from .htaccess; your choices are kept and come back when you activate it again. Deleting the plugin also removes its settings from the database. If WordPress cannot write .htaccess at that moment (for example the file is read-only), the block stays: delete everything between "# BEGIN HelloAdmin Guard" and "# END HelloAdmin Guard" by hand.

= I copied the site or its .htaccess to another server. Anything to watch? =

The .htaccess file carries the plugin's server rules, the settings live in the database. When the copied file has rules for settings that are off on the new copy, the plugin rewrites its block on the next visit to the login page or the admin area. Without that, a login URL rule from the old server could close the login form. You can also run wp helloadmin-guard sync.

= How does the strict mode of Limit login work? =

The normal limit counts failed logins per IP address. Botnets guess from thousands of addresses, a few tries each, so the strict mode also counts failed logins from all addresses together.

* Threshold: when the failed logins from all addresses reach the number you set (50 by default) within the lockout time, logins from new devices are refused for that time. While the attempts go on, the lock is extended.
* Same answer: during the lock a new device gets the same message with a right or a wrong password, so the answer never confirms a guessed password.
* Known browsers: after a successful login the browser gets a cookie for one year, signed with the salts of your wp-config.php and tied to the user. With it the owner still logs in during an attack.
* Lost your password: keeps working. The link from the reset e-mail makes that browser known, so you can log in from a new device.
* E-mail: the administration e-mail (Settings → General) gets "Login attack from many addresses", once a day at most.
* Application passwords (Jetpack, the mobile app, REST API clients): the strict mode leaves them alone, they are long and random and guessing them is pointless. The limit per IP address still applies to them.
* Not for sites where customers or members log in: during an attack they could not log in from a new device without a password reset.

= Limit login says all visitors share one address. What now? =

PHP sees the address of whatever connected to it. Behind a proxy that does not pass the visitor address (Nginx in front of Apache on the same server, a load balancer, a CDN other than Cloudflare) every visitor looks the same, and one bot could lock everyone out, so the card warns about it. The best fix is on the server: ask the host to turn on mod_remoteip or the Nginx real IP module. Behind Cloudflare the plugin reads CF-Connecting-IP itself, but only from Cloudflare's own addresses. Developers who know where their server puts the real address can return it from the helloadmin_guard_client_ip filter.

= Which routes does "Core REST API only for logged-in users" close? =

The core namespaces wp/v2 (posts, pages, media, categories, search, users), wp-site-health/v1, wp-block-editor/v1 and wp-abilities/v1, for visitors who are not logged in, also inside batch calls. oEmbed and the routes of plugins (forms, shops, statistics) stay open. If a theme or a headless front end reads posts through the API without logging in, add its route to the "Keep open for visitors" field, for example /wp/v2/posts.

= What does the Fix button in "Hide user names" change? =

For one user: the public name ("Display name publicly as") becomes the name you type, the author page address is made from that name when it was the login (the old address answers "not found"), and the user's old comments get the new name. The login and the password stay the same. After a Fix, clear the cache of your page cache and SEO plugins: they may still show the old name or author address until then.

= Does it work on Nginx? =

The General tab works on any server. Nginx does not read .htaccess, so on the Advanced tab every card shows its rules to copy into the server block of your site.

= Does the plugin send any data? =

No. It makes no requests to outside servers. The e-mail settings send their messages through your site's own mail to the addresses you set. Otherwise the plugin only sends requests to your own site, as a visitor who is not logged in: to check which gaps are open and, right after writing .htaccess, to check that the site still opens. Links to helloadmin.com in the plugin carry UTM tags so the site can tell visits from the plugin; nothing is sent unless you click them.

== Screenshots ==

1. General tab: every card says what it does, what may break and how to undo it, and shows whether the gap is closed by this plugin, already closed by something else, or open. The number of open gaps is next to the menu item.
2. Advanced tab: server rules written into .htaccess, with the exact lines under each card.
3. Security headers: tick what your site uses, the rest is turned off in Permissions-Policy.
4. The welcome message on the first visit to the settings page.

== Changelog ==

= 0.6.0 =
* New: "Core REST API only for logged-in users" closes wp/v2 and the other core routes to visitors who are not logged in; forms, shops and other plugin routes stay open.
* New: "Block PHP files of inactive plugins": PHP files of plugins that are off answer 404 when opened by address.
* Hide user names: a list of users whose login shows on the site, with a Fix button (public name, author page address, old comments); author pages can redirect to the blog or answer 404.
* Admin alerts: e-mail when anyone can register with a dangerous role, when an administrator's e-mail or password changes, and when the site e-mail changes.
* File alerts also watch wp-config.php, index.php, .user.ini, php.ini and subfolders of mu-plugins.
* Limit login: strict mode against attacks from many addresses (known browsers and password resets still get in); real visitor IP behind Cloudflare; warnings for proxies that hide the visitor IP.
* Hide the REST API index warns about random-looking REST routes and routes that run code from uploads.
* Fixes: a REST batch call could cause a PHP fatal error with Hide user names on, login page views counted as failed logins during a lockout, a stray "1" next to the menu during the first check, and smaller fixes from two rounds of code review.

= 0.5.2 =
* Security: "E-mail me about new administrators" also reports accounts with administrator rights through another role or rights added to the account itself, not only the Administrator role.
* Security: security headers and HSTS are sent from PHP whenever the server does not send them (mod_headers off, or the rules removed from .htaccess).
* Security: the plugin rewrites its .htaccess block when rules in it were removed or edited, not only when rules of settings that are off are there.
* "No PHP in the uploads folder" also closes files with a second extension (shell.php.jpg).
* "Block hidden files" also closes Thumbs.db, desktop.ini and __MACOSX folders left by Windows and macOS.
* More log, backup and dump names closed: error_log.1, php_errorlog, phpinfo.php in any folder, .sql.bz2, .sql.xz, .dump, Akeeba .jpa, WPvivid and older BackWPup folders.
* Hide user names: oEmbed no longer gives the author name, and comments lose the comment-author-<name> class.
* XML-RPC turned off: the request is refused earlier, before other plugins see it.
* Smaller fixes: the deactivation e-mail goes to the extra address of every e-mail setting, the "If hacked" button is for super admins only on multisite, a warning on the Limit login card when the site sees Cloudflare addresses instead of visitors, leftover check folders are removed, wp-config rule no longer catches post addresses like /wp-config-tips.

= 0.5.1 =
* Security: "Hide user names" now also closes the REST API user list when the address is typed in another letter case (/wp-json/wp/v2/Users). The status check tests this too.
* The http to https redirect works only for the site's own address and its www version.
* The check that the site still opens after a .htaccess change follows redirects only within the site.
* Deleting the plugin also removes its .htaccess block, if one was left behind.

= 0.5.0 =
* Security: the custom login address no longer leaks to visitors through /wp-signup.php, /wp-register.php or "Log in" links. If you use it, change the address after updating.
* Security: the e-mail settings notice when their lists are changed or removed straight in the database.
* An e-mail when the plugin is deactivated, if one of the e-mail settings is on.
* New: e-mail about new administrators, also ones written straight into the database or hidden from the Users screen.
* New: e-mail about new and changed must-use plugins and drop-ins, with warnings for files whose names start with a dot and for encoded code.
* New "If hacked" tab: log out everyone else and delete all application passwords with one button, plus a short checklist.
* XML-RPC: turn it off, or keep it for Jetpack and apps without pingbacks.
* The plugin keeps .htaccess in line with its settings after the site or the file was copied to another server. New command: wp helloadmin-guard sync.
* The number of open gaps stays right when settings change outside the settings page. E-mail settings that are off are not counted as gaps.
* New icon: three gears in the head.

= 0.4.2 =
* Limit login attempts also counts and locks REST API and XML-RPC logins with application passwords.
* Nginx rules work when WordPress lives in a subfolder; the .htaccess rule for core files works when WordPress has its own folder.
* If .htaccess cannot be written, settings whose rules are still in the file stay on, so the switches always match the file (settings page and WP-CLI).
* The login URL setting changes only links to this site's wp-login.php, not other links that mention it.
* Translations come from translate.wordpress.org instead of the plugin folder.

= 0.4.1 =
* When a gap is closed by something the plugin cannot name, the card also mentions the theme.
* readme: FAQ on deactivation and Nginx, screenshots list.

= 0.4.0 =
* Security headers: choose what your site uses (quick payment buttons, visitor location, camera and microphone); Permissions-Policy turns off the rest, plus motion sensors and USB. Known plugins tick their box for you.
* New server rules: redirect http:// to https://, refuse TRACE and TRACK.
* A redirect loop after writing .htaccess counts as a broken site: the old file is put back.
* After an update the plugin rewrites its .htaccess block once.

= 0.3.0 =
* Checks every gap from outside and shows on each card whether it is closed by this plugin, by another plugin or the server, or still open.
* Number of open gaps in the admin menu and in Site Health.
* Limit login attempts. Hide the REST API index. WP-CLI commands.
* General settings also write their server rules into .htaccess.
* The login form no longer tells which part of a login was wrong.
* Clearer card texts: what stops working and when. "Settings saved" message after a save.

= 0.2.1 =
* Every card on the Advanced tab links to its own section of a new article about server rules.
* About tab: shorter text about the author.

= 0.2.0 =
* Advanced tab with server rules in .htaccess: hidden files, backups, PHP in uploads, logs, folder listings, internal folders, files that show versions, developer files. Copy of the file before each change, automatic rollback on a server error.
* HTTPS only (HSTS) as its own setting.
* Settings ordered by risk, with a risk label on every card.
* Welcome message on the settings page that folds into one line.
* Hide the WordPress version also removes the X-Powered-By header.

= 0.1.0 =
* First public version: custom login URL, Application Passwords off, hide user names, hide the WordPress version, XML-RPC off, file editor off, security headers.
* Ukrainian and Russian translations.
