<?php
/**
 * Assigns risk level to proposed rules.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Impact;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\Runtime\BulkRuleTargets;

/**
 * Risk from dependents, asset type, core handles, and action severity.
 */
final class RiskAssessmentService {

	public const LEVEL_LOW    = 'low';
	public const LEVEL_MEDIUM = 'medium';
	public const LEVEL_HIGH   = 'high';

	/**
	 * @var array<string, true>
	 */
	private const CORE_HANDLES = array(
		'jquery'      => true,
		'jquery-core' => true,
		'wp-hooks'    => true,
		'react'       => true,
		'react-dom'   => true,
		'wp-element'  => true,
	);

	/**
	 * @param array<string, mixed> $rule
	 * @return array{level: string, label: string, reasons: array<int, string>}
	 */
	public function assess( array $rule, int $dependent_count = 0 ): array {
		$score   = 0;
		$reasons = array();

		$action     = (string) ( $rule['action_type'] ?? '' );
		$handle     = strtolower( (string) ( $rule['asset_handle'] ?? '' ) );
		$type       = (string) ( $rule['asset_type'] ?? 'script' );
		$conditions = is_array( $rule['condition_group'] ?? null ) ? $rule['condition_group'] : array();
		$bulk_targets = BulkRuleTargets::expand( $rule );
		$bulk_count   = count( $bulk_targets );

		if ( $bulk_count > 1 ) {
			$score += min( 20, (int) floor( $bulk_count / 2 ) );
			$reasons[] = sprintf(
				/* translators: %d: asset count */
				__( 'Bulk rule affects %d assets at once.', 'assetpilot' ),
				$bulk_count
			);
			foreach ( $bulk_targets as $target ) {
				$target_handle = strtolower( (string) ( $target['handle'] ?? '' ) );
				if ( isset( self::CORE_HANDLES[ $target_handle ] ) ) {
					$score += 15;
					$reasons[] = sprintf(
						/* translators: %s: script handle */
						__( 'Includes core bundle "%s".', 'assetpilot' ),
						$target['handle'] ?? $target_handle
					);
					break;
				}
			}
		}

		if ( 'disable' === $action ) {
			$score += 35;
			$reasons[] = __( 'Disabling assets can break dependent scripts or styles.', 'assetpilot' );
		} elseif ( in_array( $action, array( 'defer', 'async' ), true ) ) {
			$score += 12;
			$reasons[] = __( 'Load-order changes can affect scripts that expect this asset early.', 'assetpilot' );
		}

		if ( ! empty( $conditions['global'] ) ) {
			$score += 25;
			$reasons[] = __( 'Rule applies site-wide.', 'assetpilot' );
		}

		if ( $dependent_count >= 5 ) {
			$score += 40;
			$reasons[] = sprintf(
				/* translators: %d: dependent count */
				__( '%d other assets depend on this one.', 'assetpilot' ),
				$dependent_count
			);
		} elseif ( $dependent_count >= 2 ) {
			$score += 22;
			$reasons[] = sprintf(
				/* translators: %d: dependent count */
				__( '%d dependents may be affected.', 'assetpilot' ),
				$dependent_count
			);
		} elseif ( $dependent_count > 0 ) {
			$score += 10;
		}

		if ( isset( self::CORE_HANDLES[ $handle ] ) ) {
			$score += 30;
			$reasons[] = __( 'This is a core WordPress or editor bundle.', 'assetpilot' );
		}

		if ( 'script' === $type && 'disable' !== $action && $dependent_count >= 3 ) {
			$score += 8;
		}

		$level = self::LEVEL_LOW;
		if ( $score >= 55 ) {
			$level = self::LEVEL_HIGH;
		} elseif ( $score >= 28 ) {
			$level = self::LEVEL_MEDIUM;
		}

		return array(
			'level'   => $level,
			'label'   => $this->level_label( $level ),
			'reasons' => array_values( array_unique( $reasons ) ),
			'score'   => $score,
		);
	}

	private function level_label( string $level ): string {
		return match ( $level ) {
			self::LEVEL_HIGH   => __( 'High risk', 'assetpilot' ),
			self::LEVEL_MEDIUM => __( 'Medium risk', 'assetpilot' ),
			default            => __( 'Low risk', 'assetpilot' ),
		};
	}
}
