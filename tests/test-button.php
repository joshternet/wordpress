<?php
/**
 * Button shortcode tests.
 *
 * @package Joshternet
 */

/**
 * Tests for registry-backed local button markup.
 */
class Joshternet_Button_Test extends WP_UnitTestCase {
	/**
	 * Fixture payload used by the pre_transient stub.
	 *
	 * @var array<string, mixed>
	 */
	private static $stub_state = array();

	/**
	 * Fixture payload used by the pre_http_request stub.
	 *
	 * @var array<string, mixed>
	 */
	private static $stub_http_payload = array();

	/**
	 * Clear button cache between tests.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		Joshternet_Button::clear_cache();
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'pre_transient_' . Joshternet_Button::TRANSIENT_KEY );
		self::$stub_state        = array();
		self::$stub_http_payload = array();
	}

	/**
	 * Known states map to bundled filenames.
	 *
	 * @return void
	 */
	public function test_asset_for_state_allow_list() {
		$this->assertSame( 'verified-josh.png', Joshternet_Button::asset_for_state( 'verified-josh' ) );
		$this->assertSame( 'verified-non-josh.png', Joshternet_Button::asset_for_state( 'verified-non-josh' ) );
		$this->assertSame( 'undeclared.png', Joshternet_Button::asset_for_state( 'undeclared' ) );
		$this->assertSame( 'join-the-joshternet.png', Joshternet_Button::asset_for_state( 'join' ) );
		$this->assertSame( '', Joshternet_Button::asset_for_state( 'unavailable' ) );
		$this->assertSame( '', Joshternet_Button::asset_for_state( 'nope' ) );
	}

	/**
	 * Return a stubbed transient payload.
	 *
	 * @param mixed $pre Pre-filter value.
	 * @return array<string, mixed>
	 */
	public static function stub_transient_state( $pre ) {
		unset( $pre );
		return self::$stub_state;
	}

	/**
	 * Stub a failed registry HTTP response.
	 *
	 * External boundary: joshternet.org registry.
	 *
	 * @param false|array|WP_Error $preempt Short-circuit value.
	 * @param array                $args    Request args.
	 * @param string               $url     Request URL.
	 * @return WP_Error|false
	 */
	public static function stub_error_http_response( $preempt, $args, $url ) {
		unset( $preempt, $args );
		if ( false === strpos( $url, 'joshternet.org/api/button-state' ) ) {
			return false;
		}

		return new WP_Error( 'http_request_failed', 'timeout' );
	}

	/**
	 * Shortcode serves a local image from cached registry state.
	 *
	 * @return void
	 */
	public function test_shortcode_uses_local_image() {
		// Cached shape is normalized (no API `ok` member).
		self::$stub_state = array(
			'state'     => 'join',
			'alt'       => 'Join the Joshternet',
			'linkLabel' => 'Join the Joshternet',
			'href'      => 'https://joshternet.org/implement/',
		);
		add_filter( 'pre_transient_' . Joshternet_Button::TRANSIENT_KEY, array( __CLASS__, 'stub_transient_state' ) );

		$html = Joshternet_Button::render_shortcode();

		$this->assertNotSame( '', $html );
		$this->assertSame( 1, preg_match( '#join-the-joshternet\.png#', $html ) );
		$this->assertSame( 0, preg_match( '#joshternet\.org/embed/#', $html ) );
		$this->assertSame( 0, preg_match( '#src="https://joshternet\.org/#', $html ) );
		$this->assertSame( 1, preg_match( '#href="https://joshternet\.org/implement/"#', $html ) );
	}

	/**
	 * Affirmed registry state maps to the verified-josh asset.
	 *
	 * @return void
	 */
	public function test_shortcode_verified_josh_asset() {
		self::$stub_state = array(
			'state'     => 'verified-josh',
			'alt'       => 'Verified Josh, Joshternet site',
			'linkLabel' => 'Verified Josh on the Joshternet',
			'href'      => 'https://joshternet.org/network/',
		);
		add_filter( 'pre_transient_' . Joshternet_Button::TRANSIENT_KEY, array( __CLASS__, 'stub_transient_state' ) );

		$html = Joshternet_Button::render_shortcode();
		$this->assertSame( 1, preg_match( '#verified-josh\.png#', $html ) );
		$this->assertSame( 1, preg_match( '#href="https://joshternet\.org/network/"#', $html ) );
	}

	/**
	 * Verified non-Josh registry state maps to its bundled asset.
	 *
	 * @return void
	 */
	public function test_shortcode_verified_non_josh_asset() {
		self::$stub_state = array(
			'state'     => 'verified-non-josh',
			'alt'       => 'Verified non-Josh, Joshternet site',
			'linkLabel' => 'Verified non-Josh on the Joshternet',
			'href'      => 'https://joshternet.org/network/',
		);
		add_filter( 'pre_transient_' . Joshternet_Button::TRANSIENT_KEY, array( __CLASS__, 'stub_transient_state' ) );

		$html = Joshternet_Button::render_shortcode();
		$this->assertSame( 1, preg_match( '#verified-non-josh\.png#', $html ) );
	}

	/**
	 * Undeclared member state maps to its bundled asset.
	 *
	 * @return void
	 */
	public function test_shortcode_undeclared_asset() {
		self::$stub_state = array(
			'state'     => 'undeclared',
			'alt'       => 'Joshternet site, undeclared Josh identity',
			'linkLabel' => 'Joshternet site',
			'href'      => 'https://www.joshternet.org/network/',
		);
		add_filter( 'pre_transient_' . Joshternet_Button::TRANSIENT_KEY, array( __CLASS__, 'stub_transient_state' ) );

		$html = Joshternet_Button::render_shortcode();
		$this->assertSame( 1, preg_match( '#undeclared\.png#', $html ) );
		$this->assertSame( 1, preg_match( '#href="https://www\.joshternet\.org/network/"#', $html ) );
	}

	/**
	 * Fresh registry HTTP payloads for every displayable state render the local face.
	 *
	 * External boundary: joshternet.org registry.
	 *
	 * @dataProvider provider_registry_http_states
	 * @param string $state    Registry state token.
	 * @param string $filename Expected bundled PNG filename.
	 * @return void
	 */
	public function test_shortcode_http_states_render_local_face( $state, $filename ) {
		self::$stub_http_payload = array(
			'ok'        => true,
			'state'     => $state,
			'alt'       => 'Button ' . $state,
			'linkLabel' => 'Button ' . $state,
			'href'      => 'https://joshternet.org/implement/',
		);
		add_filter( 'pre_http_request', array( __CLASS__, 'stub_ok_http_response' ), 10, 3 );

		$html = Joshternet_Button::render_shortcode();
		$this->assertNotSame( '', $html );
		$this->assertSame( 1, preg_match( '#' . preg_quote( $filename, '#' ) . '#', $html ) );
		$this->assertSame( 0, preg_match( '#src="https://joshternet\.org/#', $html ) );
	}

	/**
	 * Displayable registry states and their bundled filenames.
	 *
	 * @return array<string, array{0:string,1:string}>
	 */
	public function provider_registry_http_states() {
		return array(
			'join'              => array( 'join', 'join-the-joshternet.png' ),
			'undeclared'        => array( 'undeclared', 'undeclared.png' ),
			'verified-josh'     => array( 'verified-josh', 'verified-josh.png' ),
			'verified-non-josh' => array( 'verified-non-josh', 'verified-non-josh.png' ),
		);
	}

	/**
	 * Stub a successful registry HTTP response.
	 *
	 * External boundary: joshternet.org registry.
	 *
	 * @param false|array|WP_Error $preempt Short-circuit value.
	 * @param array                $args    Request args.
	 * @param string               $url     Request URL.
	 * @return array|false
	 */
	public static function stub_ok_http_response( $preempt, $args, $url ) {
		unset( $preempt, $args );
		if ( false === strpos( $url, 'joshternet.org/api/button-state' ) ) {
			return false;
		}

		return array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => wp_json_encode( self::$stub_http_payload ),
			'headers'  => array(),
			'cookies'  => array(),
		);
	}

	/**
	 * Registry failure renders nothing.
	 *
	 * @return void
	 */
	public function test_shortcode_fail_closed() {
		add_filter( 'pre_http_request', array( __CLASS__, 'stub_error_http_response' ), 10, 3 );
		$html = Joshternet_Button::render_shortcode();
		$this->assertSame( '', $html );
	}

	/**
	 * Unavailable cached state renders nothing.
	 *
	 * @return void
	 */
	public function test_shortcode_unavailable_fail_closed() {
		self::$stub_state = array(
			'state'     => 'unavailable',
			'alt'       => 'x',
			'linkLabel' => 'x',
			'href'      => 'https://joshternet.org/',
		);
		add_filter( 'pre_transient_' . Joshternet_Button::TRANSIENT_KEY, array( __CLASS__, 'stub_transient_state' ) );
		$html = Joshternet_Button::render_shortcode();
		$this->assertSame( '', $html );
	}

	/**
	 * Non-Joshternet href values from cache are rejected.
	 *
	 * @return void
	 */
	public function test_shortcode_rejects_foreign_href() {
		self::$stub_state = array(
			'state'     => 'join',
			'alt'       => 'Join the Joshternet',
			'linkLabel' => 'Join the Joshternet',
			'href'      => 'https://evil.example/phish',
		);
		add_filter( 'pre_transient_' . Joshternet_Button::TRANSIENT_KEY, array( __CLASS__, 'stub_transient_state' ) );
		$html = Joshternet_Button::render_shortcode();
		$this->assertSame( '', $html );
	}
}
