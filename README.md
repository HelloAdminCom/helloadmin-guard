# HelloAdmin Guard

WordPress security hardening without upsells. Every setting explains what it does, what may break and how to undo it.

> Version 0.5.1. Submitted to wordpress.org, waiting for review.

## Why another security plugin

Most hardening plugins give you a list of switches and leave you to guess what each one breaks. Here every setting comes with three short answers next to the toggle:

- **What it does**
- **What may break**
- **How to undo it**, including when you can't reach wp-admin

Everything is off until you turn it on. No paid version, no locked features, no ads, no external requests.

## Settings

| Tab | Settings, from the biggest risk down |
| --- | --- |
| General | Turn off or limit XML-RPC · Turn off the file editor · Limit login attempts · Custom login URL · Hide user names · E-mail me about new administrators · E-mail me about new must-use plugins · Turn off Application Passwords · HTTPS only (HSTS) · Security headers · Hide the REST API index · Hide the WordPress version |
| Advanced (server rules) | Block hidden files (.git, .env) · Block backups and database dumps · No PHP in the uploads folder · Block logs and diagnostic pages · Turn off folder listings · Redirect http:// to https:// · Close internal WordPress folders · Hide files that show versions · Block developer and build files · Refuse TRACE and TRACK requests |
| If hacked | Log out everyone else and delete all application passwords with one button, plus a short checklist |

Server rules are written to `.htaccess` on Apache and LiteSpeed. On Nginx the plugin shows the same rules to copy into the server block. WP-CLI: `wp helloadmin-guard sync` rewrites the `.htaccess` block from the saved settings.

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
