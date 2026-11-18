=== HelloAdmin Guard ===
Contributors: helloadmin
Tags: security, hardening, login, htaccess, headers
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.1
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

The settings are listed from the biggest risk down to the cheap rules, and every card shows how bad the gap is while it is off.

General tab

* Turn off XML-RPC. /xmlrpc.php answers 403.
* Turn off the file editor. Removes the theme and plugin file editors from wp-admin, without editing wp-config.php.
* Custom login URL. The login page moves to your own address; /wp-login.php and /wp-admin/ show a 404 page to visitors who are not logged in.
* Hide user names. Closes /?author=1, the REST API user list, oEmbed author links and the users sitemap for visitors who are not logged in.
* Turn off Application Passwords. They stop working for everyone; existing passwords are kept and work again when you turn the setting off.
* Security headers. X-Content-Type-Options, X-Frame-Options, Referrer-Policy and Permissions-Policy.
* Hide the WordPress version. Removes it from the generator tag, feeds and core file links, and removes the X-Powered-By header.

Advanced tab: server rules (Apache and LiteSpeed write them into .htaccess, Nginx shows them to copy)

* Block hidden files: .git, .env, .htpasswd and everything else that starts with a dot.
* Block backups and database dumps: .sql, .wpress, .bak, wp-config copies, archives in the site root, Duplicator installer leftovers, backup plugin folders.
* No PHP in the uploads folder.
* Block logs and diagnostic pages: debug.log, error_log, WooCommerce logs, phpinfo.php and other test pages.
* Turn off folder listings.
* HTTPS only (HSTS).
* Close internal WordPress folders: the rules from the WordPress hardening guide.
* Hide files that show versions: readme.html, plugin readme files, install.php and upgrade.php for guests.
* Block developer and build files: composer and npm files, source maps, Docker and deploy files.

Before writing .htaccess the plugin keeps a copy of it and then opens your home page. If the page answers with a server error, the old file is put back and the settings stay off. Its rules sit between "# BEGIN HelloAdmin Guard" and "# END HelloAdmin Guard" and are removed when the plugin is deactivated.

The plugin is available in English, Ukrainian and Russian.

== Installation ==

1. Upload the plugin to wp-content/plugins/helloadmin-guard, or install it from Plugins > Add New.
2. Activate it.
3. Open HeAd Guard in the admin menu (below Settings), read each setting and turn on what you need.

== Frequently Asked Questions ==

= I turned something on and can't log in. What now? =

Rename the folder wp-content/plugins/helloadmin-guard over FTP or in your hosting file manager. WordPress turns the plugin off and every setting stops working at once. Rename it back and fix the setting in the HelloAdmin Guard menu.

= Does it work together with other security plugins? =

Yes, but do not turn on the same protection in two plugins. Two plugins that both change the login URL, for example, will get in each other's way.

= Does it work on multisite? =

Not yet. The custom login URL does nothing on multisite; the other settings are tested on single sites only.

= Does the plugin send any data? =

No. It makes no requests to outside servers. The only request it makes is to your own home page, right after writing .htaccess, to check that the site still opens. Links to helloadmin.com in the plugin carry UTM tags so the site can tell visits from the plugin; nothing is sent unless you click them.

== Changelog ==

= 0.2.1 =
* Every card on the Advanced tab links to its own section of a new article about server rules.

= 0.2.0 =
* Advanced tab with server rules in .htaccess: hidden files, backups, PHP in uploads, logs, folder listings, internal folders, files that show versions, developer files. Copy of the file before each change, automatic rollback on a server error.
* HTTPS only (HSTS) as its own setting.
* Settings ordered by risk, with a risk label on every card.
* Welcome message on the settings page that folds into one line.
* Hide the WordPress version also removes the X-Powered-By header.

= 0.1.0 =
* First public version: custom login URL, Application Passwords off, hide user names, hide the WordPress version, XML-RPC off, file editor off, security headers.
* Ukrainian and Russian translations.
