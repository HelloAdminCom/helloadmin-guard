=== HelloAdmin Guard ===
Contributors: helloadmin
Tags: security, hardening, login, xmlrpc, htaccess
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WordPress security hardening without upsells. Every setting explains what it does, what may break and how to undo it.

== Description ==

Most WordPress sites leave the same things open: a login page anyone can hit, public user names, an old XML-RPC endpoint, versions in the page source. HelloAdmin Guard closes them with simple on/off settings.

What makes it different:

* Every setting tells you what it does, what may break and how to turn it back, right next to the toggle.
* Everything is off until you turn it on. Nothing changes on activation.
* No paid version, no locked settings, no ads, no calls to outside servers.

The plugin is in early development. The list of settings grows with each version, see the changelog.

== Frequently Asked Questions ==

= I turned something on and can't log in. What now? =

Rename the folder wp-content/plugins/helloadmin-guard over FTP or in your hosting file manager. WordPress turns the plugin off and every setting stops working at once. Rename it back and fix the setting in the HelloAdmin Guard menu.

= Does it work together with other security plugins? =

Yes, but do not turn on the same protection in two plugins. Two plugins that both change the login URL, for example, will get in each other's way.

== Changelog ==

= 0.1.0 =
* In development.
