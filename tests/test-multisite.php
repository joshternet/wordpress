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
		$_SERVER['REQUEST_URI']    = '/';
		$_SERVER['REQUEST_METHOD'] = 'GET';
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
		$_SERVER['REQUEST_URI']    = '/';
		$_SERVER['REQUEST_METHOD'] = 'GET';

		$this->assertSame( '', $output );
	}

	/**
	 * Path-subsite button images must not use /{blog}/wp-content/plugin URLs.
	 *
	 * Network plugins live at origin-root wp-content. WP_PLUGIN_URL on a path
	 * child is built from that blog's siteurl, which prefixes the blog path
	 * and 404s the PNG. Simulate that plugins_url shape, then assert the
	 * shortcode rebases onto the origin.
	 *
	 * @return void
	 */
	public function test_button_image_url_uses_origin_plugin_path() {
		$blog_id = self::factory()->blog->create(
			array(
				'path' => '/site2/',
			)
		);

		$stub = array(
			'state'     => 'join',
			'alt'       => 'Join the Joshternet',
			'linkLabel' => 'Join the Joshternet',
			'href'      => 'https://joshternet.org/implement/',
		);
		add_filter(
			'pre_transient_' . Joshternet_Button::TRANSIENT_KEY,
			static function () use ( $stub ) {
				return $stub;
			}
		);
		add_filter( 'plugins_url', array( $this, 'prefix_plugins_url_with_site2_path' ), 10, 1 );

		switch_to_blog( $blog_id );
		$html = Joshternet_Button::render_shortcode();
		restore_current_blog();

		remove_filter( 'plugins_url', array( $this, 'prefix_plugins_url_with_site2_path' ), 10 );

		$this->assertNotSame( '', $html );
		$this->assertFalse( strpos( $html, '/site2/wp-content/' ) );
		$this->assertNotFalse( strpos( $html, '/wp-content/plugins/' ) );
		$this->assertNotFalse( strpos( $html, 'join-the-joshternet.png' ) );
	}

	/**
	 * Simulate WP_PLUGIN_URL built from a path-based subsite siteurl.
	 *
	 * External boundary: WordPress defines plugin constants from the current
	 * blog's siteurl for that request; this filter reproduces that URL shape.
	 *
	 * @param string $url Plugin URL from plugins_url().
	 * @return string
	 */
	public function prefix_plugins_url_with_site2_path( $url ) {
		if ( ! Joshternet_Origin::shares_origin_with_main_site() ) {
			return $url;
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return $url;
		}

		if ( 0 === strpos( (string) $parts['path'], '/site2/' ) ) {
			return $url;
		}

		return $parts['scheme'] . '://' . $parts['host'] . '/site2' . $parts['path'];
	}
}
