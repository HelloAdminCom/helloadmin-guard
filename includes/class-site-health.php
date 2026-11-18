<?php
/**
 * One item in Tools > Site Health.
 *
 * @package HelloAdmin\Guard
 */

namespace HelloAdmin\Guard;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the number of open gaps where many admins look first, with a link to the settings page.
 * Uses the results of the last check (Status), so Site Health does not get slower.
 */
class Site_Health {

	/**
	 * Check results.
	 *
	 * @var Status
	 */
	private $status;

	/**
	 * Keeps the check results.
	 *
	 * @param Status $status Check results.
	 */
	public function __construct( Status $status ) {
		$this->status = $status;
	}

	/**
	 * Adds the test.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'site_status_tests', array( $this, 'add_test' ) );
	}

	/**
	 * Registers the test as a direct (fast) one.
	 *
	 * @param array<string, mixed> $tests Site Health tests.
	 * @return array<string, mixed>
	 */
	public function add_test( $tests ) {
		$tests['direct']['helloadmin_guard'] = array(
			'label' => __( 'HelloAdmin Guard', 'helloadmin-guard' ),
			'test'  => array( $this, 'test' ),
		);
		return $tests;
	}

	/**
	 * Result for Site Health.
	 *
	 * @return array<string, mixed>
	 */
	public function test() {
		$count  = $this->status->count();
		$link   = sprintf(
			'<p><a href="%s">%s</a></p>',
			esc_url( admin_url( 'admin.php?page=' . Admin_Page::SLUG ) ),
			esc_html__( 'Open HelloAdmin Guard', 'helloadmin-guard' )
		);
		$result = array(
			'label'       => __( 'HelloAdmin Guard: no open security gaps', 'helloadmin-guard' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Security', 'helloadmin-guard' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html__( 'Every gap HelloAdmin Guard checks is closed on this site, by the plugin, by the server or by another plugin, or marked as not needed.', 'helloadmin-guard' ) . '</p>',
			'actions'     => '',
			'test'        => 'helloadmin_guard',
		);

		if ( null === Status::cached() ) {
			$result['label']       = __( 'HelloAdmin Guard has not checked this site yet', 'helloadmin-guard' );
			$result['status']      = 'recommended';
			$result['description'] = '<p>' . esc_html__( 'Open the plugin settings page once: it checks what the site answers to visitors and shows which gaps are open.', 'helloadmin-guard' ) . '</p>';
			$result['actions']     = $link;
		} elseif ( $count > 0 ) {
			/* translators: %d: number of open security gaps. */
			$result['label']       = sprintf( _n( 'HelloAdmin Guard: %d security gap is open', 'HelloAdmin Guard: %d security gaps are open', $count, 'helloadmin-guard' ), $count );
			$result['status']      = 'recommended';
			$result['description'] = '<p>' . esc_html__( 'Some things bots look for are still open on this site. Every switch on the settings page explains what it closes, what may break and how to undo it.', 'helloadmin-guard' ) . '</p>';
			$result['actions']     = $link;
		}
		return $result;
	}
}
