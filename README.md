# HelloAdmin Guard

WordPress security hardening without upsells. Every setting explains what it does, what may break and how to undo it.

> Version 0.5.2. Submitted to wordpress.org, waiting for review.

## Why another security plugin

Most hardening plugins give you a list of switches and leave you to guess what each one breaks. Here every setting comes with three short answers next to the toggle:

- **What it does**
- **What may break**
- **How to undo it**, including when you can't reach wp-admin

Everything is off until you turn it on. No paid version, no locked features, no ads, no external requests (except a DNS check of your own domain that you start on the Tips tab).

## Settings

| Tab | Settings, from the biggest risk down |
| --- | --- |
| General | Turn off or limit XML-RPC · Turn off the file editor · Limit login attempts · Custom login URL · Hide user names · E-mail me about new administrators · E-mail me about changed must-use plugins and site files · E-mail me about abandoned and closed plugins · Turn off Application Passwords · Security headers · Hide the REST API index · Core REST API only for logged-in users · Hide the WordPress version |
| Advanced (server rules) | Block hidden files (.git, .env) · Block backups and database dumps · No PHP in the uploads folder · Block logs and diagnostic pages · Turn off folder listings · Redirect http:// to https:// · HTTPS only (HSTS) · Close internal WordPress folders · Hide files that show versions · Block developer and build files · Block PHP files of inactive plugins · Refuse TRACE and TRACK requests |
| Tips | Steps a plugin cannot take for you (two-factor login, backups, one hosting user per site, SPF and DMARC, DISALLOW_FILE_MODS and more), strongest first, each with what it does not protect against |
| If hacked | Log out everyone else and delete all application passwords with one button, plus a short checklist |

Server rules are written to `.htaccess` on Apache and LiteSpeed. On Nginx the plugin shows the same rules to copy into the server block.

WP-CLI: `wp helloadmin-guard status | check | enable | disable | sync`. `enable login_url --slug=<address>` sets the login address; `--server=apache|nginx` tells the plugin the web server when the admin area was never opened on that site.

Languages: English, Ukrainian, Russian.

## Requirements

- WordPress 6.5 or newer
- PHP 7.4 or newer

## Install from source

```sh
cd wp-content/plugins
git clone https://github.com/HelloAdminCom/helloadmin-guard.git
```

Then activate it in **Plugins** and open **HelloAdmin Guard** in the admin menu (below Settings).

## If something breaks

Rename `wp-content/plugins/helloadmin-guard` over FTP or SSH. WordPress deactivates the plugin and every setting stops working at once.

## Development

```sh
composer install
composer lint      # PHPCS: WordPress Coding Standards + PHP 7.4+ compatibility
composer analyse   # PHPStan
```

## Author

Made by [HelloAdmin](https://helloadmin.com/), a WordPress and WooCommerce developer. Articles behind most of the settings are on [helloadmin.com/category/security](https://helloadmin.com/category/security/).

## License

GPL-2.0-or-later, see [LICENSE](LICENSE).
