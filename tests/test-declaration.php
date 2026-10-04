<?php
/**
 * Declaration and identity tests.
 *
 * @package Joshternet
 */

/**
 * Tests for RFC-JOSH-0002 declaration building.
 */
class Joshternet_Declaration_Test extends WP_UnitTestCase {
	/**
	 * Reset options between tests.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Joshternet_Endpoint::OPTION_ENABLED );
		delete_option( Joshternet_Endpoint::OPTION_IDENTITY );
	}

	/**
	 * Undeclared identity omits the josh member.
	 *
	 * @return void
	 */
	public function test_undeclared_declaration() {
		update_option( Joshternet_Endpoint::OPTION_IDENTITY, 'undeclared' );
		$this->assertSame( array( 'version' => 1 ), Joshternet_Endpoint::declaration() );
	}

	/**
	 * Affirmed identity publishes boolean true.
	 *
	 * @return void
	 */
	public function test_affirmed_declaration() {
		update_option( Joshternet_Endpoint::OPTION_IDENTITY, 'affirmed' );
		$this->assertSame(
			array(
				'version' => 1,
				'josh'    => true,
			),
			Joshternet_Endpoint::declaration()
		);
	}

	/**
	 * Declined identity publishes boolean false.
	 *
	 * @return void
	 */
	public function test_declined_declaration() {
		update_option( Joshternet_Endpoint::OPTION_IDENTITY, 'declined' );
		$this->assertSame(
			array(
				'version' => 1,
				'josh'    => false,
			),
			Joshternet_Endpoint::declaration()
		);
	}

	/**
	 * Invalid identity values fall back to undeclared.
	 *
	 * @return void
	 */
	public function test_sanitize_identity_allow_list() {
		$this->assertSame( 'undeclared', Joshternet_Admin::sanitize_identity( 'nope' ) );
		$this->assertSame( 'affirmed', Joshternet_Admin::sanitize_identity( 'affirmed' ) );
		$this->assertSame( 'declined', Joshternet_Admin::sanitize_identity( 'declined' ) );
		$this->assertSame( 'undeclared', Joshternet_Admin::sanitize_identity( array( 'x' ) ) );
	}

	/**
	 * Enabled option controls participation.
	 *
	 * @return void
	 */
	public function test_is_enabled() {
		$this->assertFalse( Joshternet_Endpoint::is_enabled() );
		update_option( Joshternet_Endpoint::OPTION_ENABLED, true );
		$this->assertTrue( Joshternet_Endpoint::is_enabled() );
	}
}
