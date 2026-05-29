<?php
/**
 * Aggregated validation outcome.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Validation;

defined( 'ABSPATH' ) || exit;
/**
 * Result of running the validation pipeline.
 */
final class ValidationResult {

	public const SEVERITY_INFO    = 'info';
	public const SEVERITY_WARNING = 'warning';
	public const SEVERITY_DANGER  = 'danger';

	/**
	 * @param array<int, array{code: string, message: string, severity: string}> $issues
	 */
	public function __construct(
		private readonly array $issues = array()
	) {}

	/**
	 * @param array<int, array{code: string, message: string, severity: string}> $issues
	 */
	public static function from_issues( array $issues ): self {
		$normalized = array();
		foreach ( $issues as $issue ) {
			if ( empty( $issue['message'] ) ) {
				continue;
			}
			$severity = $issue['severity'] ?? self::SEVERITY_WARNING;
			if ( ! in_array( $severity, array( self::SEVERITY_INFO, self::SEVERITY_WARNING, self::SEVERITY_DANGER ), true ) ) {
				$severity = self::SEVERITY_WARNING;
			}
			$normalized[] = array(
				'code'     => (string) ( $issue['code'] ?? 'validation' ),
				'message'  => (string) $issue['message'],
				'severity' => $severity,
			);
		}

		return new self( $normalized );
	}

	public function has_danger(): bool {
		foreach ( $this->issues as $issue ) {
			if ( self::SEVERITY_DANGER === $issue['severity'] ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return array<int, array{code: string, message: string, severity: string}>
	 */
	public function issues(): array {
		return $this->issues;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'valid'                  => ! $this->has_danger(),
			'requires_confirmation'  => $this->has_danger(),
			'issues'                 => $this->issues,
		);
	}
}
