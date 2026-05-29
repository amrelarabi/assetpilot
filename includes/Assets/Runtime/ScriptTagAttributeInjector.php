<?php
/**
 * Injects attributes into WordPress script loader tags.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets\Runtime;

defined( 'ABSPATH' ) || exit;
/**
 * WordPress outputs `<script src="...">`, not always `<script ` — use regex injection.
 */
final class ScriptTagAttributeInjector {

	/**
	 * @param string $name  Attribute name (defer, async, fetchpriority).
	 * @param string $value Attribute value; empty for boolean attributes.
	 */
	public static function inject( string $tag, string $name, string $value = '' ): string {
		if ( preg_match( '/\s' . preg_quote( $name, '/' ) . '(?:\s|=|>|$)/i', $tag ) ) {
			return $tag;
		}

		if ( '' === $value || in_array( $name, array( 'defer', 'async' ), true ) ) {
			$insert = $name;
		} else {
			$insert = $name . '="' . esc_attr( $value ) . '"';
		}

		$replaced = preg_replace( '/<script\b/i', '<script ' . $insert . ' ', $tag, 1 );

		return is_string( $replaced ) ? $replaced : $tag;
	}

	/**
	 * Set or replace an attribute on the first script tag in the loader output.
	 */
	public static function set_attribute( string $tag, string $name, string $value = '' ): string {
		if ( '' !== $value && ! in_array( $name, array( 'defer', 'async' ), true ) ) {
			$quoted = $name . '="' . esc_attr( $value ) . '"';
			if ( preg_match( '/\s' . preg_quote( $name, '/' ) . '\s*=\s*["\'][^"\']*["\']/i', $tag ) ) {
				$replaced = preg_replace(
					'/\s' . preg_quote( $name, '/' ) . '\s*=\s*["\'][^"\']*["\']/i',
					' ' . $quoted,
					$tag,
					1
				);
				return is_string( $replaced ) ? $replaced : $tag;
			}
			if ( preg_match( '/\s' . preg_quote( $name, '/' ) . '(?:\s|>|$)/i', $tag ) ) {
				return $tag;
			}
		}

		return self::inject( $tag, $name, $value );
	}

	/**
	 * Remove defer/async so fetchpriority (or async) can replace theme defaults.
	 */
	public static function strip_defer_and_async( string $tag ): string {
		$tag = preg_replace( '/\sdefer(?:\s|=|>|$)/i', ' ', $tag ) ?? $tag;
		$tag = preg_replace( '/\sasync(?:\s|=|>|$)/i', ' ', $tag ) ?? $tag;
		$tag = preg_replace( '/\sdata-wp-strategy=["\'][^"\']*["\']/i', '', $tag ) ?? $tag;

		return trim( preg_replace( '/\s+/', ' ', $tag ) ?? $tag );
	}
}
