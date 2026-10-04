<?php
/**
 * Joshternet button shortcode.
 *
 * Looks up registry state in PHP, then serves a bundled 88x31 image.
 * Page HTML does not load remote scripts or remote images.
 *
 * @package Joshternet
 * @author  Joshua Morris
 * @copyright 2026 Joshua Morris
 * @license GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry-backed Joshternet button shortcode.
 */
final class Joshternet_Button {
	const SHORTCODE        = 'joshternet_button';
	const TRANSIENT_KEY    = 'joshternet_button_state';
	const CACHE_TTL        = 900;
	const API_TIMEOUT      = 5;
	const BUTTON_STATE_API = 'https://joshternet.org/api/button-state';

	/**
	 * Register shortcode and cache invalidation.
	 *
	 * @return void
	 */
	public static function register() {
		add_shortcode( self::SHORTCODE, array( __CLASS__, 'render_shortcode' ) );
		add_action( 'update_option_' . Joshternet_Endpoint::OPTION_ENABLED, array( __CLASS__, 'clear_cache' ) );
		add_action( 'update_option_' . Joshternet_Endpoint::OPTION_IDENTITY, array( __CLASS__, 'clear_cache' ) );
	}

	/**
	 * Clear the cached registry button state.
	 *
	 * @return void
	 */
	public static function clear_cache() {
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Render the Joshternet button for the current origin.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes (unused).
	 * @return string
	 */
	public static function render_shortcode( $atts = array() ) {
		unset( $atts );

		$state = self::get_button_state();
		if ( null === $state ) {
			return '';
		}

		$filename = self::asset_for_state( $state['state'] );
		if ( '' === $filename ) {
			return '';
		}

		$image_url = plugins_url( 'assets/buttons/' . $filename, JOSHTERNET_PLUGIN_FILE );
		$href      = $state['href'];
		$alt       = $state['alt'];
		$label     = $state['linkLabel'];

		return sprintf(
			'<a href="%1$s" rel="noopener noreferrer" aria-label="%2$s"><img src="%3$s" width="88" height="31" alt="%4$s" decoding="async" loading="lazy" /></a>',
			esc_url( $href ),
			esc_attr( $label ),
			esc_url( $image_url ),
			esc_attr( $alt )
		);
	}

	/**
	 * Return allow-listed button state data, or null when unavailable.
	 *
	 * @return array{state:string,href:string,alt:string,linkLabel:string}|null
	 */
	public static function get_button_state() {
		$cached = get_transient( self::TRANSIENT_KEY );

		// Empty-string sentinel: registry was unreachable; do not hammer it.
		if ( '' === $cached ) {
			return null;
		}

		if ( is_array( $cached ) ) {
			// Cache stores the already-normalized shape (no API `ok` member).
			return self::normalized_from_cache( $cached );
		}

		$fetched = self::fetch_button_state();
		if ( null === $fetched ) {
			set_transient( self::TRANSIENT_KEY, '', self::CACHE_TTL );
			return null;
		}

		set_transient( self::TRANSIENT_KEY, $fetched, self::CACHE_TTL );
		return $fetched;
	}

	/**
	 * Accept a previously normalized cache payload.
	 *
	 * @param array<string, mixed> $payload Cached button state.
	 * @return array{state:string,href:string,alt:string,linkLabel:string}|null
	 */
	private static function normalized_from_cache( array $payload ) {
		$state = isset( $payload['state'] ) && is_string( $payload['state'] ) ? $payload['state'] : '';
		if ( '' === self::asset_for_state( $state ) ) {
			return null;
		}

		$href  = isset( $payload['href'] ) && is_string( $payload['href'] ) ? $payload['href'] : '';
		$alt   = isset( $payload['alt'] ) && is_string( $payload['alt'] ) ? $payload['alt'] : '';
		$label = isset( $payload['linkLabel'] ) && is_string( $payload['linkLabel'] ) ? $payload['linkLabel'] : '';

		if ( '' === $href || '' === $alt || '' === $label || ! self::is_allowed_href( $href ) ) {
			return null;
		}

		return array(
			'state'     => $state,
			'href'      => $href,
			'alt'       => $alt,
			'linkLabel' => $label,
		);
	}

	/**
	 * Fetch button state from the Joshternet registry API.
	 *
	 * External boundary: joshternet.org registry. Frontend assets stay local.
	 *
	 * @return array{state:string,href:string,alt:string,linkLabel:string}|null
	 */
	private static function fetch_button_state() {
		$origin = Joshternet_Origin::from_url( home_url( '/' ) );
		if ( '' === $origin ) {
			return null;
		}

		$url = add_query_arg(
			array(
				'origin' => $origin,
			),
			self::BUTTON_STATE_API
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => self::API_TIMEOUT,
				'headers' => array(
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return null;
		}

		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return null;
		}

		return self::normalize_state( $body );
	}

	/**
	 * Validate and normalize a button-state payload.
	 *
	 * @param array<string, mixed> $payload Raw API or cached payload.
	 * @return array{state:string,href:string,alt:string,linkLabel:string}|null
	 */
	private static function normalize_state( array $payload ) {
		if ( empty( $payload['ok'] ) || true !== $payload['ok'] ) {
			return null;
		}

		$state = isset( $payload['state'] ) && is_string( $payload['state'] ) ? $payload['state'] : '';
		if ( 'unavailable' === $state || '' === self::asset_for_state( $state ) ) {
			return null;
		}

		$href = isset( $payload['href'] ) && is_string( $payload['href'] ) ? $payload['href'] : '';
		$alt  = isset( $payload['alt'] ) && is_string( $payload['alt'] ) ? $payload['alt'] : '';

		if ( '' === $href || '' === $alt || ! self::is_allowed_href( $href ) ) {
			return null;
		}

		$label = isset( $payload['linkLabel'] ) && is_string( $payload['linkLabel'] ) && '' !== $payload['linkLabel']
			? $payload['linkLabel']
			: $alt;

		return array(
			'state'     => $state,
			'href'      => $href,
			'alt'       => $alt,
			'linkLabel' => $label,
		);
	}

	/**
	 * Whether a button link may point only at Joshternet HTTPS origins.
	 *
	 * @param string $href Candidate URL from the registry API.
	 * @return bool
	 */
	private static function is_allowed_href( $href ) {
		$parts = wp_parse_url( $href );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		if ( 'https' !== strtolower( (string) $parts['scheme'] ) ) {
			return false;
		}

		$host = strtolower( (string) $parts['host'] );

		return 'joshternet.org' === $host || 'www.joshternet.org' === $host;
	}

	/**
	 * Map a registry state to a bundled PNG filename.
	 *
	 * @param string $state Registry state token.
	 * @return string Filename or empty string when unknown.
	 */
	public static function asset_for_state( $state ) {
		$map = array(
			'verified-josh'     => 'verified-josh.png',
			'verified-non-josh' => 'verified-non-josh.png',
			'undeclared'        => 'undeclared.png',
			'join'              => 'join-the-joshternet.png',
		);

		return isset( $map[ $state ] ) ? $map[ $state ] : '';
	}
}
