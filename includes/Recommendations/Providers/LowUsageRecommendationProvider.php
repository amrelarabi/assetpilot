<?php
/**
 * High load, low usage recommendations from scan history.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Recommendations\Providers;

defined( 'ABSPATH' ) || exit;
use AssetControl\Recommendations\RecommendationContext;
use AssetControl\Recommendations\RecommendationProviderInterface;

/**
 * Suggests conditional disable when an asset appears on few scanned URLs.
 */
final class LowUsageRecommendationProvider implements RecommendationProviderInterface {

	private const MIN_SITE_SCANS = 3;

	private const MIN_SIZE = 20480;

	public function recommend( RecommendationContext $context ): array {
		if ( $context->distinct_scan_urls < self::MIN_SITE_SCANS ) {
			return array();
		}

		$out = array();

		foreach ( $context->assets as $asset ) {
			$handle = (string) ( $asset['handle'] ?? '' );
			$type   = (string) ( $asset['type'] ?? '' );
			$size   = (int) ( $asset['size'] ?? 0 );

			if ( '' === $handle || $size < self::MIN_SIZE ) {
				continue;
			}

			if ( $context->has_rule( $handle, $type ) ) {
				continue;
			}

			$url_count = $context->usage_url_count( $handle, $type );
			if ( $url_count <= 0 || $url_count > 1 ) {
				continue;
			}

			$out[] = array(
				'id'               => 'low_usage:' . $type . ':' . $handle,
				'type'             => 'low_usage',
				'handle'           => $handle,
				'asset_type'       => $type,
				'suggested_action' => 'disable',
				'confidence'       => 'medium',
				'size'             => $size,
				'title'            => __( 'Low usage asset', 'assetpilot' ),
				'reason'           => sprintf(
					/* translators: 1: handle, 2: pages seen, 3: total scans */
					__( '"%1$s" was seen on %2$d scanned page(s) across %3$d recent scans. It may be safe to disable on most of the site.', 'assetpilot' ),
					$handle,
					$url_count,
					$context->distinct_scan_urls
				),
			);
		}

		return $out;
	}
}
