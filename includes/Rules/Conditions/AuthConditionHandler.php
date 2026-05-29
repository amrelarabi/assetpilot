<?php
/**
 * Logged-in / logged-out condition.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Rules\Conditions;

defined( 'ABSPATH' ) || exit;
final class AuthConditionHandler implements ConditionHandlerInterface {

	public function is_active( array $conditions ): bool {
		return isset( $conditions['logged_in'] );
	}

	public function matches( array $conditions ): bool {
		return (bool) $conditions['logged_in'] === is_user_logged_in();
	}
}
