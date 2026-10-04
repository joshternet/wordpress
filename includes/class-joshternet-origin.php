<?php
/**
 * Origin helpers for the Joshternet declaration.
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
 * Origin helpers for Joshternet declarations.
 */
final class Joshternet_Origin {
	/**
	 * Return a normalized origin for a URL.
	 *
	 * @param string $url URL to inspect.
	 * @return string
	 */
	public static function from_url( $url ) {
		$parts = wp_parse_url( $url );

		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		$host   = strtolower( (string) $parts['host'] );
		$port   = isset( $parts['port'] ) ? (int) $parts['port'] : null;

		$is_default_port = ( 'https' === $scheme && 443 === $port ) || ( 'http' === $scheme && 80 === $port );

		if ( $port && ! $is_default_port ) {
			return sprintf( '%s://%s:%d', $scheme, $host, $port );
		}

		return sprintf( '%s://%s', $scheme, $host );
	}

	/**
	 * Return the public Joshternet endpoint for the current site's origin.
	 *
	 * @return string
	 */
	public static function endpoint_url() {
		$origin = self::from_url( home_url( '/' ) );

		return $origin ? $origin . '/.well-known/josh' : '';
	}

	/**
	 * Whether this multisite blog shares its origin with the network main site.
	 *
	 * Path-based subsites can be separate WordPress sites while still sharing
	 * one HTTP origin. RFC-JOSH-0002 gives that origin one declaration, so a
	 * child path cannot publish a second declaration at the same root URI.
	 *
	 * @return bool
	 */
	public static function shares_origin_with_main_site() {
		if ( ! is_multisite() ) {
			return false;
		}

		$main_site_id = (int) get_main_site_id();
		$current_id   = (int) get_current_blog_id();

		if ( $main_site_id === $current_id ) {
			return false;
		}

		$current_origin = self::from_url( home_url( '/' ) );
		$main_origin    = self::from_url( get_home_url( $main_site_id, '/' ) );

		return $current_origin && $main_origin && hash_equals( $main_origin, $current_origin );
	}

	/**
	 * Whether the public site URL lives below the origin root.
	 *
	 * A site can still publish /.well-known/josh if its host deliberately routes
	 * that root request into WordPress. A portable plugin cannot guarantee that
	 * routing, so the admin UI calls it out for verification.
	 *
	 * @return bool
	 */
	public static function home_is_subdirectory() {
		$path = wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		if ( ! is_string( $path ) ) {
			return false;
		}

		return '/' !== $path && '' !== trim( $path, '/' );
	}
}
