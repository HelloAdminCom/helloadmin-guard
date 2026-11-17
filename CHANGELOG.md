# Changelog

All notable changes to this project are documented here. Versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Plugin skeleton: settings page in its own admin menu (after Settings) with General, Advanced and About tabs.
- Base `Feature` class: every setting describes what it does, what may break and how to undo it.
- Coding standards (PHPCS with WordPress Coding Standards, PHPCompatibilityWP), PHPStan, GitHub Actions.
- Plugin icon (head with a gear) in the admin menu and on the settings page; menu label "HeAd Guard".
- Custom login URL: the login page moves to /your-address/, /wp-login.php and /wp-admin/ answer 404 to visitors who are not logged in. Single sites only for now.
- Hide user names: ?author=N answers 404, REST /wp/v2/users needs a login, no author link in oEmbed, no users sitemap (visitors who are not logged in).
- Turn off XML-RPC: /xmlrpc.php answers 403, no X-Pingback header, no RSD link.
- Turn off the file editor: theme and plugin file editors removed from wp-admin (like DISALLOW_FILE_EDIT, without editing wp-config.php).
- Field hints under the fields; links to helloadmin.com carry UTM tags (utm_source=helloadmin-guard), nothing is sent from the site.
