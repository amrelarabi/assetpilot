<?php
/**
 * Post type archive conditions.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Rules\Conditions;

defined( 'ABSPATH' ) || exit;
final class PostTypeArchiveConditionHandler implements ConditionHandlerInterface {

	public function is_active( array $conditions ): bool {
		return ! empty( $conditions['post_type'] );
	}

	public function matches( array $conditions ): bool {
		$types = (array) $conditions['post_type'];

		if ( is_singular() ) {
			return in_array( get_post_type(), $types, true );
		}

		return is_post_type_archive( $types );
	}
}
