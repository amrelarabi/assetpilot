<?php
/**
 * Contract for rule validation checks.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Validation;

defined( 'ABSPATH' ) || exit;
/**
 * Validates a proposed rule against one aspect of risk.
 */
interface RuleValidatorInterface {

	/**
	 * @return array<int, array{code: string, message: string, severity: string}>
	 */
	public function validate( RuleValidationContext $context ): array;
}
