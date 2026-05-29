<?php
/**
 * Runs all rule validators.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Validation;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\AssetCapture;
use AssetControl\Database\RulesRepository;
use AssetControl\Validation\Validators\CoreAssetProtectionValidator;
use AssetControl\Validation\Validators\DangerousCombinationValidator;
use AssetControl\Validation\Validators\DependencyConflictValidator;
use AssetControl\Helpers\Logger;
use AssetControl\Validation\Validators\DuplicateActionValidator;

/**
 * Validation pipeline for proposed rules.
 */
final class ValidationPipeline {

	/** @var array<int, RuleValidatorInterface> */
	private array $validators;

	/**
	 * @param array<int, RuleValidatorInterface>|null $validators
	 */
	public function __construct( ?array $validators = null ) {
		$this->validators = $validators ?? array(
			new DependencyConflictValidator(),
			new CoreAssetProtectionValidator(),
			new DuplicateActionValidator(),
			new DangerousCombinationValidator(),
		);
	}

	/**
	 * @param array<string, mixed> $rule Sanitized proposed rule.
	 */
	public function validate( array $rule, ?int $exclude_rule_id = null, string $scan_url = '' ): ValidationResult {
		$url     = $this->normalize_scan_url( $scan_url );
		$capture = new AssetCapture();
		// Skip footer hooks — they print HTML and break REST JSON responses.
		$state   = $capture->bootstrap_dependency_registry( $url, false );

		try {
			$context = new RuleValidationContext(
				$rule,
				( new RulesRepository() )->all_cached(),
				$exclude_rule_id
			);

			$issues = array();
			foreach ( $this->validators as $validator ) {
				$issues = array_merge( $issues, $validator->validate( $context ) );
			}

			$result = ValidationResult::from_issues( $issues );
			$this->log_validation_result( $rule, $result );

			return $result;
		} finally {
			$capture->restore_after_bootstrap( $state );
		}
	}

	/**
	 * @param array<string, mixed> $rule
	 */
	private function log_validation_result( array $rule, ValidationResult $result ): void {
		if ( ! Logger::is_enabled() ) {
			return;
		}

		$rule_id = (int) ( $rule['id'] ?? 0 );
		$handle  = (string) ( $rule['asset_handle'] ?? '' );

		foreach ( $result->issues() as $issue ) {
			Logger::log(
				'validation',
				$issue['message'],
				array(
					'rule_id'      => $rule_id,
					'asset_handle' => $handle,
					'code'         => $issue['code'],
					'severity'     => $issue['severity'],
				)
			);
		}

		if ( empty( $result->issues() ) ) {
			Logger::log(
				'validation',
				__( 'Rule passed validation.', 'assetpilot' ),
				array(
					'rule_id'      => $rule_id,
					'asset_handle' => $handle,
					'severity'     => 'info',
				)
			);
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
