<?php
/**
 * Match the exact URL used during an Assets Explorer scan.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Rules\Conditions;

defined( 'ABSPATH' ) || exit;
/**
 * Compares the current request URL to a stored scan URL.
 */
final class ScanPageUrlConditionHandler implements ConditionHandlerInterface {

	public function is_active( array $conditions ): bool {
		return '' !== trim( (string) ( $conditions['scan_page_url'] ?? '' ) );
	}

	public function matches( array $conditions ): bool {
		$target = trim( (string) ( $conditions['scan_page_url'] ?? '' ) );
		if ( '' === $target ) {
			return true;
		}

		return $this->normalize_url( $target ) === $this->normalize_url( $this->current_request_url() );
	}

	private function current_request_url(): string {
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$path = sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) );
			return (string) home_url( $path );
		}

		return (string) home_url( '/' );
	}

	private function normalize_url( string $url ): string {
		$clean = esc_url_raw( $url );
		if ( '' === $clean ) {
			$clean = $url;
		}

		$parts = wp_parse_url( $clean );
		if ( ! is_array( $parts ) ) {
			return untrailingslashit( strtolower( $clean ) );
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? 'http' ) );
		$host   = strtolower( (string) ( $parts['host'] ?? '' ) );
		$path   = (string) ( $parts['path'] ?? '/' );
		$query  = isset( $parts['query'] ) ? '?' . (string) $parts['query'] : '';

		return $scheme . '://' . $host . untrailingslashit( $path ) . $query;
	}
}
