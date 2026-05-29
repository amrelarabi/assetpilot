<?php
/**
 * Query string matching.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Rules\Conditions;

defined( 'ABSPATH' ) || exit;
final class QueryStringConditionHandler implements ConditionHandlerInterface {

	public function is_active( array $conditions ): bool {
		return '' !== trim( (string) ( $conditions['query_contains'] ?? '' ) );
	}

	public function matches( array $conditions ): bool {
		$needle = trim( (string) ( $conditions['query_contains'] ?? '' ) );
		if ( '' === $needle ) {
			return true;
		}

		$query = isset( $_SERVER['QUERY_STRING'] )
			? sanitize_text_field( wp_unslash( (string) $_SERVER['QUERY_STRING'] ) )
			: '';

		return str_contains( $query, $needle );
	}
}
