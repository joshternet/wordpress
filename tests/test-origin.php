<?php
/**
 * Origin helper tests.
 *
 * @package Joshternet
 */

/**
 * Tests for origin normalization helpers.
 */
class Joshternet_Origin_Test extends WP_UnitTestCase {
	/**
	 * Origin helper strips default ports and lowercases the host.
	 *
	 * @return void
	 */
	public function test_from_url_normalization() {
		$this->assertSame( 'https://example.com', Joshternet_Origin::from_url( 'https://Example.com/' ) );
		$this->assertSame( 'https://example.com', Joshternet_Origin::from_url( 'https://example.com:443/path' ) );
		$this->assertSame( 'http://example.com', Joshternet_Origin::from_url( 'http://example.com:80/' ) );
		$this->assertSame( 'https://example.com:8443', Joshternet_Origin::from_url( 'https://example.com:8443/' ) );
		$this->assertSame( '', Joshternet_Origin::from_url( '/relative' ) );
	}

	/**
	 * Endpoint URL is origin root plus /.well-known/josh.
	 *
	 * @return void
	 */
	public function test_endpoint_url() {
		$this->assertStringEndsWith( '/.well-known/josh', Joshternet_Origin::endpoint_url() );
	}
}
