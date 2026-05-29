<?php
/**
 * Expands a rule into one or more asset targets (bulk group support).
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets\Runtime;

defined( 'ABSPATH' ) || exit;
/**
 * Reads action_config.bulk_assets for multi-asset rules.
 */
final class BulkRuleTargets {

	/**
	 * @param array<string, mixed> $rule
	 * @return array<int, array{handle: string, type: string, src: string}>
	 */
	public static function expand( array $rule ): array {
		$config = is_array( $rule['action_config'] ?? null ) ? $rule['action_config'] : array();

		if ( ! empty( $config['bulk_assets'] ) && is_array( $config['bulk_assets'] ) ) {
			$targets = array();
			foreach ( $config['bulk_assets'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$handle = sanitize_text_field( (string) ( $row['handle'] ?? '' ) );
				$type   = sanitize_key( (string) ( $row['type'] ?? 'script' ) );
				if ( '' === $handle ) {
					continue;
				}
				$targets[] = array(
					'handle' => $handle,
					'type'   => $type,
					'src'    => sanitize_text_field( (string) ( $row['src'] ?? '' ) ),
				);
			}
			if ( ! empty( $targets ) ) {
				return $targets;
			}
		}

		return array(
			array(
				'handle' => (string) ( $rule['asset_handle'] ?? '' ),
				'type'   => (string) ( $rule['asset_type'] ?? 'script' ),
				'src'    => '',
			),
		);
	}

	public static function is_group( array $rule ): bool {
		$config = is_array( $rule['action_config'] ?? null ) ? $rule['action_config'] : array();

		return ! empty( $config['bulk_group'] ) && ! empty( $config['bulk_assets'] );
	}

	public static function count( array $rule ): int {
		return count( self::expand( $rule ) );
	}

	/**
	 * @param array<string, mixed> $rule
	 */
	public static function matches_handle( array $rule, string $handle, string $type, RuntimeContext $context ): bool {
		if ( '' === $handle ) {
			return false;
		}

		foreach ( self::expand( $rule ) as $target ) {
			if ( $type !== ( $target['type'] ?? '' ) ) {
				continue;
			}

			$target_handle = (string) ( $target['handle'] ?? '' );
			if ( '' !== $target_handle && $target_handle === $handle ) {
				return true;
			}

			$src = (string) ( $target['src'] ?? '' );
			if ( '' !== $src && $context->resolve_rule_handle(
				array_merge(
					$rule,
					array(
						'asset_handle'  => $target_handle,
						'asset_type'    => $target['type'],
						'action_config' => array_merge(
							is_array( $rule['action_config'] ?? null ) ? $rule['action_config'] : array(),
							array( 'src' => $src )
						),
					)
				)
			) === $handle ) {
				return true;
			}
		}

		return false;
	}
}
