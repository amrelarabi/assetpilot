<?php
/**
 * Single aspect of rule condition evaluation.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Rules\Conditions;

defined( 'ABSPATH' ) || exit;
/**
 * Handlers are AND-combined when active for a rule.
 */
interface ConditionHandlerInterface {

	/**
	 * Whether this handler participates in evaluation for the given group.
	 *
	 * @param array<string, mixed> $conditions
	 */
	public function is_active( array $conditions ): bool;

	/**
	 * @param array<string, mixed> $conditions
	 */
	public function matches( array $conditions ): bool;
}
