<?php
/**
 * Admin disclosure and Privacy Policy Guide tests.
 *
 * @package Joshternet
 */

/**
 * Tests for administrator-facing Button State disclosure.
 */
class Joshternet_Admin_Test extends WP_UnitTestCase {
	/**
	 * Grant an administrator and an admin screen.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		set_current_screen( 'options-general' );
	}

	/**
	 * Settings copy discloses the optional Button State request.
	 *
	 * @return void
	 */
	public function test_settings_page_discloses_button_state() {
		ob_start();
		Joshternet_Admin::render_settings_page();
		$html = ob_get_clean();

		$this->assertNotFalse( strpos( $html, 'Joshternet Button State' ) );
		$this->assertNotFalse( strpos( $html, 'normalized website origin' ) );
		$this->assertNotFalse( strpos( $html, '6 hours' ) );
		$this->assertNotFalse( strpos( $html, '15 minutes' ) );
		$this->assertNotFalse( strpos( $html, 'bundled with the plugin' ) );
		$this->assertNotFalse( strpos( $html, '/.well-known/josh does not make this request' ) );
		$this->assertNotFalse( strpos( $html, 'https://joshternet.org/privacy/' ) );
		$this->assertNotFalse( strpos( $html, 'https://joshternet.org/terms/' ) );
	}

	/**
	 * Privacy Policy Guide receives suggested Button State language.
	 *
	 * @return void
	 */
	public function test_privacy_policy_guide_content() {
		$this->assertTrue( is_admin() );

		global $wp_current_filter;
		if ( ! is_array( $wp_current_filter ) ) {
			$wp_current_filter = array();
		}
		$wp_current_filter[] = 'admin_init';
		Joshternet_Admin::register_privacy_policy_content();
		array_pop( $wp_current_filter );

		$sections = WP_Privacy_Policy_Content::get_suggested_policy_text();
		$found    = '';

		foreach ( $sections as $section ) {
			if ( isset( $section['plugin_name'] ) && 'Joshternet' === $section['plugin_name'] ) {
				$found = (string) $section['policy_text'];
				break;
			}
		}

		$this->assertNotSame( '', $found );
		$this->assertNotFalse( strpos( $found, 'Button State' ) );
		$this->assertNotFalse( strpos( $found, 'normalized public origin' ) );
		$this->assertNotFalse( strpos( $found, '[joshternet_button]' ) );
		$this->assertNotFalse( strpos( $found, '6 hours' ) );
		$this->assertNotFalse( strpos( $found, '15 minutes' ) );
		$this->assertNotFalse( strpos( $found, 'https://joshternet.org/privacy/' ) );
		$this->assertNotFalse( strpos( $found, 'not legal advice' ) );
	}
}
