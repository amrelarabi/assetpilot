<?php
/**
 * Suggests defer for large scripts.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Recommendations\Providers;

defined( 'ABSPATH' ) || exit;
use AssetControl\Recommendations\RecommendationContext;
use AssetControl\Recommendations\RecommendationProviderInterface;

/**
 * Flags heavy JS/CSS assets.
 */
final class LargeAssetRecommendationProvider implements RecommendationProviderInterface {

	private const SCRIPT_THRESHOLD = 150000;

	private const STYLE_THRESHOLD = 100000;

	public function recommend( RecommendationContext $context ): array {
		$out = array();

		foreach ( $context->assets as $asset ) {
			$handle = (string) ( $asset['handle'] ?? '' );
			$type   = (string) ( $asset['type'] ?? '' );
			$size   = (int) ( $asset['size'] ?? 0 );

			if ( '' === $handle || $size <= 0 ) {
				continue;
			}

			if ( 'script' === $type && $size >= self::SCRIPT_THRESHOLD ) {
				if ( $context->has_rule( $handle, $type, 'defer' ) ) {
					continue;
				}
				$out[] = $this->item(
					'large_asset',
					$handle,
					$type,
					'defer',
					'high',
					$size,
					sprintf(
						/* translators: 1: handle, 2: size in KB */
						__( 'Script "%1$s" is about %2$s KB on this page. Deferring can reduce render blocking.', 'assetpilot' ),
						$handle,
						(string) round( $size / 1024 )
					)
				);
				continue;
			}

			if ( 'style' === $type && $size >= self::STYLE_THRESHOLD && ! $context->has_rule( $handle, $type, 'disable' ) ) {
				$out[] = $this->item(
					'large_asset',
					$handle,
					$type,
					'disable',
					'medium',
					$size,
					sprintf(
						/* translators: 1: handle, 2: size in KB */
						__( 'Stylesheet "%1$s" is about %2$s KB. Consider disabling it on pages that do not need it.', 'assetpilot' ),
						$handle,
						(string) round( $size / 1024 )
					)
				);
			}
		}

		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function item(
		string $type,
		string $handle,
		string $asset_type,
		string $action,
		string $confidence,
		int $size,
		string $reason
	): array {
		return array(
			'id'               => $type . ':' . $asset_type . ':' . $handle . ':' . $action,
			'type'             => $type,
			'handle'           => $handle,
			'asset_type'       => $asset_type,
			'suggested_action' => $action,
			'confidence'       => $confidence,
			'size'             => $size,
			'title'            => 'script' === $asset_type
				? __( 'Large script', 'assetpilot' )
				: __( 'Large stylesheet', 'assetpilot' ),
			'reason'           => $reason,
		);
	}
}
