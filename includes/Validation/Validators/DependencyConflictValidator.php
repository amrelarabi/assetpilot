<?php
/**
 * Dependency-related rule warnings.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Validation\Validators;

defined( 'ABSPATH' ) || exit;
use AssetControl\Rules\DependencyAnalyzer;
use AssetControl\Validation\RuleValidationContext;
use AssetControl\Validation\RuleValidatorInterface;
use AssetControl\Validation\ValidationResult;

/**
 * Warns when actions may break dependency chains.
 */
final class DependencyConflictValidator implements RuleValidatorInterface {

	private const DEPENDENCY_ACTIONS = array( 'disable', 'defer', 'async' );

	public function validate( RuleValidationContext $context ): array {
		$issues = array();
		$handle = $context->handle();
		$type   = $context->type();
		$action = $context->action();

		if ( ! in_array( $type, array( 'script', 'style' ), true ) ) {
			return $issues;
		}

		if ( ! in_array( $action, self::DEPENDENCY_ACTIONS, true ) ) {
			return $issues;
		}

		$analyzer   = new DependencyAnalyzer();
		$dependents = $analyzer->get_dependents( $handle, $type );

		if ( ! empty( $dependents ) ) {
			$handles = array_unique(
				array_map(
					static fn( array $d ): string => (string) ( $d['handle'] ?? '' ),
					$dependents
				)
			);
			$handles = array_filter( $handles );

			$issues[] = array(
				'code'     => 'dependents_affected',
				'severity' => ValidationResult::SEVERITY_WARNING,
				'message'  => sprintf(
					/* translators: 1: handle, 2: dependent list, 3: action */
					__( 'Asset "%1$s" is required by: %2$s. "%3$s" may break those assets.', 'assetpilot' ),
					$handle,
					implode( ', ', $handles ),
					$action
				),
			);
		}

		if ( 'defer' === $action && ! empty( $dependents ) ) {
			$issues[] = array(
				'code'     => 'defer_dependency_not_child',
				'severity' => ValidationResult::SEVERITY_WARNING,
				'message'  => sprintf(
					/* translators: %s: asset handle */
					__( 'Deferring "%s" without deferring its dependents can change load order unexpectedly.', 'assetpilot' ),
					$handle
				),
			);
		}

		if ( 'async' === $action ) {
			$queue = 'script' === $type ? wp_scripts() : wp_styles();
			$deps  = array();
			if ( $queue && isset( $queue->registered[ $handle ] ) ) {
				$deps = array_filter( (array) ( $queue->registered[ $handle ]->deps ?? array() ) );
			}

			if ( ! empty( $deps ) || ! empty( $dependents ) ) {
				$issues[] = array(
					'code'     => 'async_dependency_mismatch',
					'severity' => ValidationResult::SEVERITY_WARNING,
					'message'  => sprintf(
						/* translators: %s: asset handle */
						__( 'Async on "%s" with dependencies or dependents can cause race conditions or undefined load order.', 'assetpilot' ),
						$handle
					),
				);
			}
		}

		if ( 'disable' === $action ) {
			$chain = $analyzer->get_chain( $handle, $type );
			if ( count( $chain ) > 1 ) {
				$parents = array_slice( $chain, 0, -1 );
				$issues[] = array(
					'code'     => 'disable_parent_dependency',
					'severity' => ValidationResult::SEVERITY_WARNING,
					'message'  => sprintf(
						/* translators: 1: handle, 2: dependency chain */
						__( 'Disabling "%1$s" affects its dependency chain: %2$s.', 'assetpilot' ),
						$handle,
						implode( ' → ', $chain )
					),
				);
			}
		}

		return $issues;
	}
}
