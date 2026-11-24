<?php
/**
 * Setting: build, deploy and package files answer 404.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard\Features;

use HelloAdmin\Guard\Htaccess_Feature;

use HelloAdmin\Guard\Probe;

defined( 'ABSPATH' ) || exit;

/**
 * Build and package files list exact versions of libraries; deploy files describe the server.
 */
class Block_Dev_Files extends Htaccess_Feature {

	/**
	 * Setting key.
	 *
	 * @return string
	 */
	public function id() {
		return 'block_dev_files';
	}

	/**
	 * Title next to the toggle.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Block developer and build files', 'helloadmin-guard' );
	}

	/**
	 * What it does.
	 *
	 * @return string
	 */
	public function what() {
		return __( 'The server answers 404 for package and build files (composer.json, composer.lock, package.json, package-lock.json, yarn.lock, gulpfile.js, webpack and Vite configs), deploy files (Dockerfile, docker-compose.yml, wp-cli.yml, deploy.php, Makefile), php.ini copies, source maps (.map) and text files of plugins, themes and translations (.md, .txt, .po, .mo, .pot, .yml, .lock) inside the content folder, plus .md, .yml and .sh files in the site root. They list exact versions of libraries with known holes and describe the server.', 'helloadmin-guard' );
	}

	/**
	 * What may break.
	 *
	 * @return string
	 */
	public function may_break() {
		return __( 'Browser developer tools no longer show the source code of minified scripts. Uploads are not affected. robots.txt, ads.txt and other .txt files in the site root stay open. The .txt and .md files inside plugin and theme folders answer 404 too: a plugin that opens its own text file in the browser, for example a changelog in a pop-up, no longer shows it.', 'helloadmin-guard' );
	}

	/**
	 * Uploads folder relative to the site root, as a regex fragment.
	 *
	 * @return string
	 */
	private function uploads() {
		$uploads = wp_upload_dir( null, false );
		return self::rel( $uploads['baseurl'] );
	}

	/**
	 * Uploads folder relative to the content folder, for the Nginx rule.
	 *
	 * @return string
	 */
	private function uploads_inside() {
		$content = self::rel( content_url() );
		$uploads = $this->uploads();
		return 0 === strpos( $uploads, $content . '/' ) ? substr( $uploads, strlen( $content ) + 1 ) : 'uploads';
	}

	/**
	 * Section of the server rules article.
	 *
	 * @return string
	 */
	public function anchor() {
		return 'dev-files';
	}

	/**
	 * Is the gap closed now, by anyone?
	 *
	 * @return bool|null
	 */
	public function probe() {
		$path = Probe::file( 'hag-check.map' );
		return '' === $path ? null : Probe::closed( $path );
	}

	/**
	 * Risk while off.
	 *
	 * @return string
	 */
	public function risk() {
		return 'low';
	}

	/**
	 * Place on the tab.
	 *
	 * @return int
	 */
	public function priority() {
		return 80;
	}

	/**
	 * Apache rules.
	 *
	 * @return string[]
	 */
	public function apache_rules() {
		$content = self::rel( content_url() );
		$uploads = $this->uploads();
		return array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteRule (^|/)(composer\\.(json|lock)|package(-lock)?\\.json|yarn\\.lock|pnpm-lock\\.yaml|gulpfile\\.js|gruntfile\\.js|webpack\\.config\\.js|vite\\.config\\.[jt]s|Dockerfile|docker-compose[^/]*\\.ya?ml|wp-cli\\.yml|deploy\\.php|Makefile|php5?\\.ini|php\\.info)$ - [NC,R=404,L]',
			'RewriteRule \\.map$ - [NC,R=404,L]',
			'RewriteRule ^[^/]+\\.(md|ya?ml|sh|neon|dist)$ - [NC,R=404,L]',
			'RewriteCond %{REQUEST_URI} !/' . $uploads . '/',
			'RewriteRule ^' . $content . '/.+\\.(md|txt|pot|po|mo|ya?ml|lock|dist|neon|sh)$ - [NC,R=404,L]',
			'</IfModule>',
		);
	}

	/**
	 * Nginx rules.
	 *
	 * @return string[]
	 */
	public function nginx_rules() {
		$content = self::abs( content_url() );
		$root    = self::prefix( home_url( '/' ), true );
		return array(
			'location ~* /(composer\\.(json|lock)|package(-lock)?\\.json|yarn\\.lock|pnpm-lock\\.yaml|gulpfile\\.js|gruntfile\\.js|webpack\\.config\\.js|vite\\.config\\.[jt]s|Dockerfile|docker-compose[^/]*\\.ya?ml|wp-cli\\.yml|deploy\\.php|Makefile|php5?\\.ini|php\\.info)$ { return 404; }',
			'location ~* \\.map$ { return 404; }',
			'location ~* ^/' . $root . '[^/]+\\.(md|ya?ml|sh|neon|dist)$ { return 404; }',
			'location ~* ^/' . $content . '/(?!' . $this->uploads_inside() . '/).+\\.(md|txt|pot|po|mo|ya?ml|lock|dist|neon|sh)$ { return 404; }',
		);
	}
}
