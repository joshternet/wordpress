<?php
/**
 * Multisite origin ownership tests.
 *
 * @package Joshternet
 */

/**
 * Path-based Multisite: one origin, one declaration.
 *
 * @group ms-required
 */
class Joshternet_Multisite_Test extends WP_UnitTestCase {
	/**
	 * Skip when the suite is not running under Multisite.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only.' );
		}
	}

	/**
	 * Main site does not share origin with itself.
	 *
	 * @return void
	 */
	public function test_main_site_does_not_share_origin() {
		$this->assertFalse( Joshternet_Origin::shares_origin_with_main_site() );
	}

	/**
	 * Path-based child shares the main site origin and must not own a second declaration.
	 *
	 * @return void
	 */
	public function test_path_subsite_shares_origin_with_main() {
		$blog_id = self::factory()->blog->create(
			array(
				'path' => '/site2/',
			)
		);

		switch_to_blog( $blog_id );
		$shares = Joshternet_Origin::shares_origin_with_main_site();
		restore_current_blog();

		$this->assertTrue( $shares );
	}

	/**
	 * Endpoint does not publish when the current blog is a path-based child.
	 *
	 * Early return (no exit): child context must not emit declaration JSON.
	 *
	 * @return void
	 */
	public function test_endpoint_skips_path_subsite() {
		$blog_id = self::factory()->blog->create(
			array(
				'path' => '/site3/',
			)
		);

		switch_to_blog( $blog_id );
		update_option( Joshternet_Endpoint::OPTION_ENABLED, true );
		update_option( Joshternet_Endpoint::OPTION_IDENTITY, 'affirmed' );

		$_SERVER['REQUEST_URI']    = '/.well-known/josh';
		$_SERVER['REQUEST_METHOD'] = 'GET';

		ob_start();
		Joshternet_Endpoint::maybe_serve();
		$output = ob_get_clean();

		restore_current_blog();
		unset( $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'] );

		$this->assertSame( '', $output );
	}
}
