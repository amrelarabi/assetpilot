<?php
/**
 * Render-blocking script recommendations.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Recommendations\Providers;

defined( 'ABSPATH' ) || exit;
use AssetControl\Recommendations\RecommendationContext;
use AssetControl\Recommendations\RecommendationProviderInterface;

/**
 * Suggests defer for head-loaded scripts.
 */
final class RenderBlockingRecommendationProvider implements RecommendationProviderInterface {

	/** @var array<string, bool> */
	private static array $skip_handles = array(
		'jquery'         => true,
		'jquery-core'    => true,
		'jquery-migrate' => true,
		'wp-polyfill'    => true,
	);

	public function recommend( RecommendationContext $context ): array {
		$out = array();

		foreach ( $context->assets as $asset ) {
			if ( 'script' !== ( $asset['type'] ?? '' ) ) {
				continue;
			}

			$handle = (string) ( $asset['handle'] ?? '' );
			if ( '' === $handle || isset( self::$skip_handles[ $handle ] ) ) {
				continue;
			}

			if ( empty( $asset['enqueued'] ) ) {
				continue;
			}

			$in_footer = ! empty( $asset['in_footer'] );
			if ( $in_footer ) {
				continue;
			}

			if ( $context->has_rule( $handle, 'script', 'defer' ) || $context->has_rule( $handle, 'script', 'async' ) ) {
				continue;
			}

			$out[] = array(
				'id'               => 'render_blocking:script:' . $handle . ':defer',
				'type'             => 'render_blocking',
				'handle'           => $handle,
				'asset_type'       => 'script',
				'suggested_action' => 'defer',
				'confidence'       => 'medium',
				'size'             => (int) ( $asset['size'] ?? 0 ),
				'title'            => __( 'Render-blocking script', 'assetpilot' ),
				'reason'           => sprintf(
					/* translators: %s: script handle */
					__( '"%s" loads in the document head without defer/async.', 'assetpilot' ),
					$handle
				),
			);
		}

		return $out;
	}
}
