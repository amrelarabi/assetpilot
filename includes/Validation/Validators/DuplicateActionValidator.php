<?php
/**
 * Duplicate or conflicting rule detection.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Validation\Validators;

defined( 'ABSPATH' ) || exit;
use AssetControl\Validation\RuleValidationContext;
use AssetControl\Validation\RuleValidatorInterface;
use AssetControl\Validation\ValidationResult;

/**
 * Warns when similar rules already exist.
 */
final class DuplicateActionValidator implements RuleValidatorInterface {

	public function validate( RuleValidationContext $context ): array {
		if ( ! $context->is_enabled() ) {
			return array();
		}

		$issues  = array();
		$handle  = $context->handle();
		$action  = $context->action();
		$related = $context->rules_for_same_asset();

		foreach ( $related as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}

			$existing_action = (string) ( $rule['action_type'] ?? '' );
			$rule_id         = (int) ( $rule['id'] ?? 0 );

			if ( $existing_action === $action ) {
				$issues[] = array(
					'code'     => 'duplicate_rule',
					'severity' => ValidationResult::SEVERITY_WARNING,
					'message'  => sprintf(
						/* translators: 1: action, 2: handle, 3: rule id */
						__( 'An enabled "%1$s" rule already exists for "%2$s" (rule #%3$d).', 'assetpilot' ),
						$action,
						$handle,
						$rule_id
					),
				);
			}

			if ( 'preload' === $action && 'preload' === $existing_action ) {
				$issues[] = array(
					'code'     => 'preload_already_exists',
					'severity' => ValidationResult::SEVERITY_WARNING,
					'message'  => sprintf(
						/* translators: 1: handle, 2: rule id */
						__( 'A preload rule already exists for "%1$s" (rule #%2$d).', 'assetpilot' ),
						$handle,
						$rule_id
					),
				);
			}

			if ( 'fetchpriority' === $action && 'fetchpriority' === $existing_action ) {
				$issues[] = array(
					'code'     => 'fetchpriority_conflict',
					'severity' => ValidationResult::SEVERITY_WARNING,
					'message'  => sprintf(
						/* translators: 1: handle, 2: rule id */
						__( 'Another fetchpriority rule exists for "%1$s" (rule #%2$d). Only one priority should apply.', 'assetpilot' ),
						$handle,
						$rule_id
					),
				);
			}
		}

		return $this->dedupe_by_code( $issues );
	}

	/**
	 * @param array<int, array{code: string, message: string, severity: string}> $issues
	 * @return array<int, array{code: string, message: string, severity: string}>
	 */
	private function dedupe_by_code( array $issues ): array {
		$seen = array();
		$out  = array();
		foreach ( $issues as $issue ) {
			$code = $issue['code'] ?? '';
			if ( isset( $seen[ $code ] ) ) {
				continue;
			}
			$seen[ $code ] = true;
			$out[]         = $issue;
		}
		return $out;
	}
}
