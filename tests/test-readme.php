<?php
/**
 * WordPress.org readme disclosure tests.
 *
 * @package Joshternet
 */

/**
 * Guard the External services section required by plugin review.
 */
class Joshternet_Readme_Test extends WP_UnitTestCase {
	/**
	 * The plugin readme.txt documents the Button State service, origin, and policies.
	 *
	 * @return void
	 */
	public function test_external_services_section() {
		$readme = file_get_contents( dirname( __DIR__ ) . '/readme.txt' );

		$this->assertNotFalse( $readme );
		$this->assertNotFalse( strpos( $readme, '== External services ==' ) );
		$this->assertNotFalse( strpos( $readme, 'https://joshternet.org/api/button-state' ) );
		$this->assertNotFalse( strpos( $readme, 'https://joshternet.org/terms/' ) );
		$this->assertNotFalse( strpos( $readme, 'https://joshternet.org/privacy/' ) );
		$this->assertNotFalse( strpos( $readme, '/.well-known/josh' ) );
		$this->assertNotFalse( strpos( $readme, 'does not contact Joshternet.org' ) );
		$this->assertNotFalse( strpos( $readme, 'normalized website origin' ) );
		$this->assertNotFalse( strpos( $readme, '6 hours' ) );
		$this->assertNotFalse( strpos( $readme, '15 minutes' ) );
	}
}
