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
	 * Recorded Button State requests.
	 *
	 * @var array<int, array{url:string,args:array<string,mixed>}>
	 */
	private static $http_requests = array();

	/**
	 * HTTP status the stub should return.
	 *
	 * @var int
	 */
	private static $stub_http_code = 200;

	/**
	 * Raw HTTP body override, or null to encode $stub_http_payload.
	 *
	 * @var string|null
	 */
	private static $stub_http_body = null;

	/**
	 * Whether the stub should return a transport error.
	 *
	 * @var bool
	 */
	private static $stub_http_error = false;

	/**
	 * Expiration passed to set_transient for the button state.
	 *
	 * @var int|null
	 */
	private static $last_transient_expiration = null;

	/**
	 * Clear button cache between tests.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		Joshternet_Button::clear_cache();
		delete_option( Joshternet_Endpoint::OPTION_ENABLED );
		delete_option( Joshternet_Endpoint::OPTION_IDENTITY );
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'pre_transient_' . Joshternet_Button::TRANSIENT_KEY );
		remove_all_actions( 'set_transient_' . Joshternet_Button::TRANSIENT_KEY );
		add_action( 'set_transient_' . Joshternet_Button::TRANSIENT_KEY, array( __CLASS__, 'capture_transient_expiration' ), 10, 2 );
		self::$stub_state                = array();
		self::$stub_http_payload         = array();
		self::$http_requests             = array();
		self::$stub_http_code            = 200;
		self::$stub_http_body            = null;
		self::$stub_http_error           = false;
		self::$last_transient_expiration = null;

		$_SERVER['REQUEST_URI']    = '/';
		$_SERVER['REQUEST_METHOD'] = 'GET';
	}

	/**
	 * Restore a default request URI after tests that simulate the well-known endpoint.
	 *
	 * @return void
	 */
	public function tear_down() {
		$_SERVER['REQUEST_URI']    = '/';
		$_SERVER['REQUEST_METHOD'] = 'GET';
		parent::tear_down();
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
	 * Record the expiration used when the button transient is stored.
	 *
	 * @param mixed $value      Transient value.
	 * @param int   $expiration Lifetime in seconds.
	 * @return void
	 */
	public static function capture_transient_expiration( $value, $expiration ) {
		unset( $value );
		self::$last_transient_expiration = (int) $expiration;
	}

	/**
	 * Stub a failed registry HTTP response.
	 *
	 * External boundary: joshternet.org Button State service.
	 *
	 * @param false|array|WP_Error $preempt Short-circuit value.
	 * @param array                $args    Request args.
	 * @param string               $url     Request URL.
	 * @return WP_Error|false
	 */
	public static function stub_error_http_response( $preempt, $args, $url ) {
		unset( $preempt );
		if ( false === strpos( $url, 'joshternet.org/api/button-state' ) ) {
			return false;
		}

		self::$http_requests[] = array(
			'url'  => $url,
			'args' => $args,
		);

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
		$this->assertSame( 0, preg_match( '#https://joshternet\.org/[^"\']+\.png#', $html ) );
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- asserting the shortcode does not emit a remote script tag.
		$this->assertSame( 0, preg_match( '#<script src="https://joshternet\.org/#', $html ) );
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
	 * External boundary: joshternet.org Button State service.
	 *
	 * @param false|array|WP_Error $preempt Short-circuit value.
	 * @param array                $args    Request args.
	 * @param string               $url     Request URL.
	 * @return array|WP_Error|false
	 */
	public static function stub_ok_http_response( $preempt, $args, $url ) {
		unset( $preempt );
		if ( false === strpos( $url, 'joshternet.org/api/button-state' ) ) {
			return false;
		}

		self::$http_requests[] = array(
			'url'  => $url,
			'args' => $args,
		);

		if ( self::$stub_http_error ) {
			return new WP_Error( 'http_request_failed', 'timeout' );
		}

		$body = null !== self::$stub_http_body
			? self::$stub_http_body
			: wp_json_encode( self::$stub_http_payload );

		return array(
			'response' => array(
				'code'    => self::$stub_http_code,
				'message' => 200 === self::$stub_http_code ? 'OK' : 'Error',
			),
			'body'     => $body,
			'headers'  => array(),
			'cookies'  => array(),
		);
	}

	/**
	 * Join-state payload used by request-contract tests.
	 *
	 * @return array<string, mixed>
	 */
	private function join_payload() {
		return array(
			'ok'        => true,
			'state'     => 'join',
			'alt'       => 'Join the Joshternet',
			'linkLabel' => 'Join the Joshternet',
			'href'      => 'https://joshternet.org/implement/',
		);
	}

	/**
	 * Record Button State requests without returning a payload.
	 *
	 * External boundary: joshternet.org Button State service.
	 *
	 * @param false|array|WP_Error $preempt Short-circuit value.
	 * @param array                $args    Request args.
	 * @param string               $url     Request URL.
	 * @return WP_Error|false
	 */
	public static function record_unexpected_button_state_request( $preempt, $args, $url ) {
		unset( $preempt );
		if ( false === strpos( $url, 'joshternet.org/api/button-state' ) ) {
			return false;
		}

		self::$http_requests[] = array(
			'url'  => $url,
			'args' => $args,
		);

		return new WP_Error( 'http_request_failed', 'unexpected Button State request' );
	}

	/**
	 * Assert the captured request matches the Button State HTTP contract.
	 *
	 * @param array{url:string,args:array<string,mixed>} $request Captured request.
	 * @return void
	 */
	private function assert_button_state_request_contract( $request ) {
		$origin = Joshternet_Origin::from_url( home_url( '/' ) );
		$parts  = wp_parse_url( $request['url'] );

		$this->assertIsArray( $parts );
		$this->assertSame( 'https', $parts['scheme'] );
		$this->assertSame( 'joshternet.org', $parts['host'] );
		$this->assertSame( '/api/button-state', $parts['path'] );

		$query = array();
		parse_str( isset( $parts['query'] ) ? $parts['query'] : '', $query );
		$this->assertSame( array( 'origin' => $origin ), $query );

		$args = $request['args'];
		$this->assertSame( Joshternet_Button::API_TIMEOUT, $args['timeout'] );
		$this->assertSame( Joshternet_Button::API_REDIRECTION, $args['redirection'] );
		$this->assertSame( Joshternet_Button::API_RESPONSE_BYTES, $args['limit_response_size'] );
		$this->assertSame( 'Joshternet/' . JOSHTERNET_VERSION . '; ' . $origin, $args['user-agent'] );
		$this->assertSame( 'application/json', $args['headers']['Accept'] );
	}

	/**
	 * Outbound request sends the exact normalized origin and hardened HTTP args.
	 *
	 * @return void
	 */
	public function test_http_request_sends_normalized_origin_and_args() {
		self::$stub_http_payload = $this->join_payload();
		add_filter( 'pre_http_request', array( __CLASS__, 'stub_ok_http_response' ), 10, 3 );

		$html = Joshternet_Button::render_shortcode();

		$this->assertNotSame( '', $html );
		$this->assertCount( 1, self::$http_requests );
		$this->assert_button_state_request_contract( self::$http_requests[0] );
	}

	/**
	 * A cached success does not call the Button State service again.
	 *
	 * @return void
	 */
	public function test_successful_response_is_cached() {
		self::$stub_http_payload = $this->join_payload();
		add_filter( 'pre_http_request', array( __CLASS__, 'stub_ok_http_response' ), 10, 3 );

		$first  = Joshternet_Button::render_shortcode();
		$second = Joshternet_Button::render_shortcode();

		$this->assertNotSame( '', $first );
		$this->assertSame( $first, $second );
		$this->assertCount( 1, self::$http_requests );
		$this->assertSame( 6 * HOUR_IN_SECONDS, self::$last_transient_expiration );
		$this->assertSame( Joshternet_Button::SUCCESS_CACHE_TTL, self::$last_transient_expiration );
	}

	/**
	 * Transport failure renders nothing and is cached as a sentinel.
	 *
	 * @return void
	 */
	public function test_failure_sentinel_prevents_repeated_requests() {
		add_filter( 'pre_http_request', array( __CLASS__, 'stub_error_http_response' ), 10, 3 );

		$first  = Joshternet_Button::render_shortcode();
		$second = Joshternet_Button::render_shortcode();

		$this->assertSame( '', $first );
		$this->assertSame( '', $second );
		$this->assertCount( 1, self::$http_requests );
		$this->assertSame( 15 * MINUTE_IN_SECONDS, self::$last_transient_expiration );
		$this->assertSame( Joshternet_Button::FAILURE_CACHE_TTL, self::$last_transient_expiration );
	}

	/**
	 * Changing Joshternet options clears the cached button state.
	 *
	 * @return void
	 */
	public function test_option_update_invalidates_button_cache() {
		self::$stub_http_payload = $this->join_payload();
		add_filter( 'pre_http_request', array( __CLASS__, 'stub_ok_http_response' ), 10, 3 );

		update_option( Joshternet_Endpoint::OPTION_ENABLED, '0' );
		update_option( Joshternet_Endpoint::OPTION_IDENTITY, 'undeclared' );
		Joshternet_Button::clear_cache();
		self::$http_requests = array();

		Joshternet_Button::render_shortcode();
		$this->assertCount( 1, self::$http_requests );
		$this->assertNotFalse( has_action( 'update_option_' . Joshternet_Endpoint::OPTION_ENABLED ) );

		$this->assertTrue( update_option( Joshternet_Endpoint::OPTION_ENABLED, '1' ) );
		Joshternet_Button::render_shortcode();
		$this->assertCount( 2, self::$http_requests );

		$this->assertTrue( update_option( Joshternet_Endpoint::OPTION_IDENTITY, 'affirmed' ) );
		Joshternet_Button::render_shortcode();
		$this->assertCount( 3, self::$http_requests );
	}

	/**
	 * A 3xx Button State response fails closed.
	 *
	 * @return void
	 */
	public function test_redirect_response_fails_closed() {
		self::$stub_http_code    = 302;
		self::$stub_http_payload = $this->join_payload();
		add_filter( 'pre_http_request', array( __CLASS__, 'stub_ok_http_response' ), 10, 3 );

		$html = Joshternet_Button::render_shortcode();
		$this->assertSame( '', $html );
		$this->assertCount( 1, self::$http_requests );
		$this->assertSame( 0, self::$http_requests[0]['args']['redirection'] );
	}

	/**
	 * Malformed JSON fails closed.
	 *
	 * @return void
	 */
	public function test_malformed_json_fails_closed() {
		self::$stub_http_body = '{not-json';
		add_filter( 'pre_http_request', array( __CLASS__, 'stub_ok_http_response' ), 10, 3 );

		$this->assertSame( '', Joshternet_Button::render_shortcode() );
	}

	/**
	 * A payload with ok not true fails closed.
	 *
	 * @return void
	 */
	public function test_ok_false_fails_closed() {
		self::$stub_http_payload = array(
			'ok'        => false,
			'state'     => 'join',
			'alt'       => 'Join the Joshternet',
			'linkLabel' => 'Join the Joshternet',
			'href'      => 'https://joshternet.org/implement/',
		);
		add_filter( 'pre_http_request', array( __CLASS__, 'stub_ok_http_response' ), 10, 3 );

		$this->assertSame( '', Joshternet_Button::render_shortcode() );
	}

	/**
	 * Unknown registry states fail closed.
	 *
	 * @return void
	 */
	public function test_unknown_state_fails_closed() {
		self::$stub_http_payload = array(
			'ok'        => true,
			'state'     => 'mystery',
			'alt'       => 'x',
			'linkLabel' => 'x',
			'href'      => 'https://joshternet.org/implement/',
		);
		add_filter( 'pre_http_request', array( __CLASS__, 'stub_ok_http_response' ), 10, 3 );

		$this->assertSame( '', Joshternet_Button::render_shortcode() );
	}

	/**
	 * Non-HTTPS href values from the API fail closed.
	 *
	 * @return void
	 */
	public function test_http_href_fails_closed() {
		self::$stub_http_payload = array(
			'ok'        => true,
			'state'     => 'join',
			'alt'       => 'Join the Joshternet',
			'linkLabel' => 'Join the Joshternet',
			'href'      => 'http://joshternet.org/implement/',
		);
		add_filter( 'pre_http_request', array( __CLASS__, 'stub_ok_http_response' ), 10, 3 );

		$this->assertSame( '', Joshternet_Button::render_shortcode() );
	}

	/**
	 * JavaScript URLs from the API fail closed.
	 *
	 * @return void
	 */
	public function test_javascript_href_fails_closed() {
		self::$stub_http_payload = array(
			'ok'        => true,
			'state'     => 'join',
			'alt'       => 'Join the Joshternet',
			'linkLabel' => 'Join the Joshternet',
			'href'      => 'javascript:alert(1)',
		);
		add_filter( 'pre_http_request', array( __CLASS__, 'stub_ok_http_response' ), 10, 3 );

		$this->assertSame( '', Joshternet_Button::render_shortcode() );
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

	/**
	 * Declaration generation does not call the Button State service.
	 *
	 * The live Apache GET of /.well-known/josh remains the request-level
	 * isolation check; PHPUnit cannot continue after the handler exits.
	 *
	 * @return void
	 */
	public function test_declaration_does_not_call_button_state() {
		add_filter( 'pre_http_request', array( __CLASS__, 'record_unexpected_button_state_request' ), 10, 3 );

		$expected = array(
			'undeclared' => array( 'version' => 1 ),
			'affirmed'   => array(
				'version' => 1,
				'josh'    => true,
			),
			'declined'   => array(
				'version' => 1,
				'josh'    => false,
			),
		);

		foreach ( $expected as $identity => $declaration ) {
			update_option( Joshternet_Endpoint::OPTION_ENABLED, true );
			update_option( Joshternet_Endpoint::OPTION_IDENTITY, $identity );

			$this->assertSame( $declaration, Joshternet_Endpoint::declaration() );
		}

		$this->assertSame( array(), self::$http_requests );
	}
}
