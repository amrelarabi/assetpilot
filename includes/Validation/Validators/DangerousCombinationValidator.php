<?php
/**
 * Dangerous rule combination detection.
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
 * Flags contradictory actions on the same asset.
 */
final class DangerousCombinationValidator implements RuleValidatorInterface {

	public function validate( RuleValidationContext $context ): array {
		if ( ! $context->is_enabled() ) {
			return array();
		}

		$issues   = array();
		$handle   = $context->handle();
		$action   = $context->action();
		$existing = $context->rules_for_same_asset();

		$enabled_actions = array( $action );
		foreach ( $existing as $rule ) {
			if ( ! empty( $rule['enabled'] ) ) {
				$enabled_actions[] = (string) ( $rule['action_type'] ?? '' );
			}
		}

		$enabled_actions = array_unique( array_filter( $enabled_actions ) );

		if ( $this->has_pair( $enabled_actions, 'disable', 'preload' ) ) {
			$issues[] = array(
				'code'     => 'disable_and_preload',
				'severity' => ValidationResult::SEVERITY_DANGER,
				'message'  => sprintf(
					/* translators: %s: asset handle */
					__( 'Cannot disable and preload "%s" at the same time — the asset cannot load and be preloaded.', 'assetpilot' ),
					$handle
				),
			);
		}

		if ( $this->has_pair( $enabled_actions, 'defer', 'preload' ) ) {
			$bulk_hint = $this->bulk_rule_hint( $existing, 'defer' );
			$issues[] = array(
				'code'     => 'defer_and_preload',
				'severity' => ValidationResult::SEVERITY_WARNING,
				'message'  => sprintf(
					/* translators: 1: asset handle, 2: optional bulk rule note */
					__( 'Defer and preload both apply to "%1$s"%2$s — pick one loading strategy or narrow conditions so they do not overlap.', 'assetpilot' ),
					$handle,
					$bulk_hint
				),
			);
		}

		if ( $this->has_pair( $enabled_actions, 'disable', 'defer' ) ) {
			$issues[] = array(
				'code'     => 'disable_and_defer',
				'severity' => ValidationResult::SEVERITY_WARNING,
				'message'  => sprintf(
					/* translators: %s: asset handle */
					__( 'Disable and defer both apply to "%s" — disable already prevents the script from loading.', 'assetpilot' ),
					$handle
				),
			);
		}

		if ( $this->has_pair( $enabled_actions, 'async', 'defer' ) ) {
			$issues[] = array(
				'code'     => 'async_and_defer',
				'severity' => ValidationResult::SEVERITY_DANGER,
				'message'  => sprintf(
					/* translators: %s: asset handle */
					__( 'Async and defer cannot both apply to "%s" — choose one loading strategy.', 'assetpilot' ),
					$handle
				),
			);
		}

		return $issues;
	}

	/**
	 * @param array<int, string> $actions
	 */
	private function has_pair( array $actions, string $a, string $b ): bool {
		return in_array( $a, $actions, true ) && in_array( $b, $actions, true );
	}

	/**
	 * @param array<int, array<string, mixed>> $related
	 */
	private function bulk_rule_hint( array $related, string $action_type ): string {
		foreach ( $related as $rule ) {
			if ( $action_type !== (string) ( $rule['action_type'] ?? '' ) ) {
				continue;
			}
			$config = is_array( $rule['action_config'] ?? null ) ? $rule['action_config'] : array();
			if ( empty( $config['bulk_group'] ) || empty( $config['bulk_assets'] ) ) {
				continue;
			}
			$count = count( (array) $config['bulk_assets'] );
			$rule_id = (int) ( $rule['id'] ?? 0 );
			return sprintf(
				/* translators: 1: asset count, 2: rule id */
				__( ' (including bulk rule #%2$d covering %1$d assets)', 'assetpilot' ),
				$count,
				$rule_id
			);
		}
		return '';
	}
}
