<?php
/**
 * User role condition.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Rules\Conditions;

defined( 'ABSPATH' ) || exit;
final class UserRolesConditionHandler implements ConditionHandlerInterface {

	public function is_active( array $conditions ): bool {
		return ! empty( $conditions['user_roles'] );
	}

	public function matches( array $conditions ): bool {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();
		if ( ! $user || empty( $user->roles ) ) {
			return false;
		}

		$required = array_map( 'sanitize_key', (array) $conditions['user_roles'] );

		return ! empty( array_intersect( $required, $user->roles ) );
	}
}
