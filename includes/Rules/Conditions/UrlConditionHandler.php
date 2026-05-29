<?php
/**
 * URL path matching (contains or starts with).
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Rules\Conditions;

defined( 'ABSPATH' ) || exit;
final class UrlConditionHandler implements ConditionHandlerInterface {

	public function is_active( array $conditions ): bool {
		return '' !== $this->path_needle( $conditions );
	}

	public function matches( array $conditions ): bool {
		$needle = $this->path_needle( $conditions );
		if ( '' === $needle ) {
			return true;
		}

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		if ( '' === $path ) {
			$path = $uri;
		}

		$mode = (string) ( $conditions['url_match_type'] ?? 'contains' );
		if ( 'starts_with' === $mode ) {
			return str_starts_with( $path, $needle ) || str_starts_with( $uri, $needle );
		}

		return str_contains( $path, $needle ) || str_contains( $uri, $needle );
	}

	/**
	 * @param array<string, mixed> $conditions
	 */
	private function path_needle( array $conditions ): string {
		$path = (string) ( $conditions['url_path'] ?? $conditions['url_contains'] ?? '' );
		return trim( $path );
	}
}
