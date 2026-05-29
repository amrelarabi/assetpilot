<?php
/**
 * Singular post / page conditions.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Rules\Conditions;

defined( 'ABSPATH' ) || exit;
final class SingularConditionHandler implements ConditionHandlerInterface {

	public function is_active( array $conditions ): bool {
		$has_ids = ! empty( $conditions['include_ids'] ) || ! empty( $conditions['post_ids'] );
		return $has_ids || ! empty( $conditions['singular_type'] );
	}

	public function matches( array $conditions ): bool {
		if ( ! is_singular() ) {
			return false;
		}

		$post_id = get_queried_object_id();
		$exclude = array_map( 'intval', (array) ( $conditions['exclude_ids'] ?? array() ) );
		if ( in_array( $post_id, $exclude, true ) ) {
			return false;
		}

		$include = $conditions['include_ids'] ?? $conditions['post_ids'] ?? array();
		if ( ! empty( $include ) ) {
			$include = array_map( 'intval', (array) $include );
			return in_array( $post_id, $include, true );
		}

		if ( ! empty( $conditions['singular_type'] ) ) {
			$types = (array) $conditions['singular_type'];
			return in_array( get_post_type( $post_id ), $types, true );
		}

		return true;
	}
}
