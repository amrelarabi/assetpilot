<?php
/**
 * Input data for recommendation providers.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Recommendations;

defined( 'ABSPATH' ) || exit;
/**
 * Immutable context for a single scan analysis pass.
 */
final class RecommendationContext {

	/**
	 * @param array<int, array<string, mixed>> $assets
	 * @param array<int, array<string, mixed>> $rules
	 * @param array<string, int>              $usage_url_counts asset key => distinct URL count
	 */
	public function __construct(
		public readonly array $assets,
		public readonly string $scan_url,
		public readonly array $rules,
		public readonly array $usage_url_counts = array(),
		public readonly int $distinct_scan_urls = 0
	) {}

	public function asset_key( string $handle, string $type ): string {
		return $type . ':' . $handle;
	}

	public function has_rule( string $handle, string $type, ?string $action = null ): bool {
		foreach ( $this->rules as $rule ) {
			if ( (string) ( $rule['asset_handle'] ?? '' ) !== $handle ) {
				continue;
			}
			if ( (string) ( $rule['asset_type'] ?? '' ) !== $type ) {
				continue;
			}
			if ( null !== $action && (string) ( $rule['action_type'] ?? '' ) !== $action ) {
				continue;
			}
			return true;
		}

		return false;
	}

	public function usage_url_count( string $handle, string $type ): int {
		return (int) ( $this->usage_url_counts[ $this->asset_key( $handle, $type ) ] ?? 0 );
	}
}
