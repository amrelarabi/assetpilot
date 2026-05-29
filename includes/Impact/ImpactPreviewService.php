<?php
/**
 * Combines impact estimation and risk assessment for the review step.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Impact;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\AssetCapture;
use AssetControl\Assets\Runtime\BulkRuleTargets;
use AssetControl\Rules\DependencyAnalyzer;

/**
 * Preview payload for Create Rule step 4 and validate endpoint.
 */
final class ImpactPreviewService {

	public function __construct(
		private readonly RuleImpactEstimator $estimator = new RuleImpactEstimator(),
		private readonly RiskAssessmentService $risk = new RiskAssessmentService(),
		private readonly DependencyAnalyzer $dependencies = new DependencyAnalyzer()
	) {}

	/**
	 * @param array<string, mixed> $rule Sanitized proposed rule.
	 * @return array<string, mixed>
	 */
	public function preview( array $rule, string $scan_url = '' ): array {
		$impact           = $this->estimator->estimate( $rule );
		$dependent_count  = $this->count_dependents( $rule, $scan_url );
		$risk             = $this->risk->assess( $rule, $dependent_count );

		return array(
			'impact' => $impact,
			'risk'   => $risk,
		);
	}

	/**
	 * @param array<string, mixed> $rule
	 */
	private function count_dependents( array $rule, string $scan_url ): int {
		$action  = (string) ( $rule['action_type'] ?? '' );
		$targets = BulkRuleTargets::expand( $rule );

		if ( empty( $targets ) ) {
			return 0;
		}

		$capture = new AssetCapture();
		$state   = $capture->bootstrap_dependency_registry( $this->normalize_scan_url( $scan_url ), false );

		try {
			$seen = array();
			foreach ( $targets as $target ) {
				$handle = (string) ( $target['handle'] ?? '' );
				$type   = (string) ( $target['type'] ?? 'script' );
				if ( '' === $handle || ! in_array( $type, array( 'script', 'style' ), true ) ) {
					continue;
				}
				$analysis = $this->dependencies->analyze( $handle, $action, $type );
				foreach ( $analysis['dependents'] ?? array() as $dependent ) {
					$dep_handle = (string) ( $dependent['handle'] ?? '' );
					if ( '' !== $dep_handle ) {
						$seen[ $type . ':' . $dep_handle ] = true;
					}
				}
			}
			return count( $seen );
		} finally {
			$capture->restore_after_bootstrap( $state );
		}
	}

	private function normalize_scan_url( string $scan_url ): string {
		$scan_url = trim( $scan_url );
		if ( '' === $scan_url ) {
			return (string) home_url( '/' );
		}
		$clean = esc_url_raw( $scan_url );
		return '' !== $clean ? $clean : $scan_url;
	}
}
