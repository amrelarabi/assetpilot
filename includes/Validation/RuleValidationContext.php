<?php
/**
 * Input for rule validation pipeline.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Validation;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\Runtime\BulkRuleTargets;

/**
 * Proposed rule plus existing rules from storage.
 */
final class RuleValidationContext {

	/**
	 * @param array<string, mixed>              $rule Proposed rule (sanitized).
	 * @param array<int, array<string, mixed>> $existing_rules All stored rules.
	 */
	public function __construct(
		public readonly array $rule,
		public readonly array $existing_rules,
		public readonly ?int $exclude_rule_id = null
	) {}

	public function handle(): string {
		return (string) ( $this->rule['asset_handle'] ?? '' );
	}

	public function type(): string {
		return (string) ( $this->rule['asset_type'] ?? 'script' );
	}

	public function action(): string {
		return (string) ( $this->rule['action_type'] ?? '' );
	}

	public function is_enabled(): bool {
		return (bool) ( $this->rule['enabled'] ?? true );
	}

	/**
	 * Rules that target the same handle/type as the proposed rule (includes bulk rule members).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function rules_for_same_asset(): array {
		$targets = self::targets_for_rule( $this->rule );

		return array_values(
			array_filter(
				$this->existing_rules,
				function ( array $row ) use ( $targets ): bool {
					if ( $this->exclude_rule_id && (int) ( $row['id'] ?? 0 ) === $this->exclude_rule_id ) {
						return false;
					}
					return self::targets_overlap( $targets, self::targets_for_rule( $row ) );
				}
			)
		);
	}

	/**
	 * @param array<string, mixed> $rule
	 * @return array<int, array{handle: string, type: string}>
	 */
	public static function targets_for_rule( array $rule ): array {
		$targets = array();
		foreach ( BulkRuleTargets::expand( $rule ) as $row ) {
			$handle = (string) ( $row['handle'] ?? '' );
			$type   = (string) ( $row['type'] ?? 'script' );
			if ( '' === $handle ) {
				continue;
			}
			$key = $type . ':' . $handle;
			if ( ! isset( $targets[ $key ] ) ) {
				$targets[ $key ] = array(
					'handle' => $handle,
					'type'   => $type,
				);
			}
		}
		return array_values( $targets );
	}

	/**
	 * @param array<int, array{handle: string, type: string}> $a
	 * @param array<int, array{handle: string, type: string}> $b
	 */
	public static function targets_overlap( array $a, array $b ): bool {
		foreach ( $a as $left ) {
			foreach ( $b as $right ) {
				if ( $left['handle'] === $right['handle'] && $left['type'] === $right['type'] ) {
					return true;
				}
			}
		}
		return false;
	}
}
