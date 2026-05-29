<?php
/**
 * Duplicate library detection.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Recommendations\Providers;

defined( 'ABSPATH' ) || exit;
use AssetControl\Recommendations\RecommendationContext;
use AssetControl\Recommendations\RecommendationProviderInterface;

/**
 * Detects multiple slider/icon/library stacks on one page.
 */
final class DuplicateLibraryRecommendationProvider implements RecommendationProviderInterface {

	/** @var array<string, array<int, string>> */
	private const GROUPS = array(
		'slider' => array( 'swiper', 'slick', 'owl', 'flexslider', 'bxslider', 'glide' ),
		'icons'  => array( 'font-awesome', 'fontawesome', 'fa-', 'eicons', 'elementor-icons', 'dashicons' ),
		'moment' => array( 'moment', 'moment-js' ),
	);

	public function recommend( RecommendationContext $context ): array {
		$out    = array();
		$groups = array();

		foreach ( $context->assets as $asset ) {
			$handle = strtolower( (string) ( $asset['handle'] ?? '' ) );
			$src    = strtolower( (string) ( $asset['src'] ?? '' ) );
			if ( '' === $handle ) {
				continue;
			}

			foreach ( self::GROUPS as $group => $needles ) {
				foreach ( $needles as $needle ) {
					if ( str_contains( $handle, $needle ) || ( '' !== $src && str_contains( $src, $needle ) ) ) {
						$groups[ $group ][] = (string) ( $asset['handle'] ?? '' );
						break;
					}
				}
			}
		}

		foreach ( $groups as $group => $handles ) {
			$handles = array_values( array_unique( $handles ) );
			if ( count( $handles ) < 2 ) {
				continue;
			}

			$out[] = array(
				'id'               => 'duplicate:' . $group,
				'type'             => 'duplicate_library',
				'handle'           => $handles[0],
				'asset_type'       => 'script',
				'suggested_action' => 'disable',
				'confidence'       => 'medium',
				'size'             => 0,
				'title'            => __( 'Duplicate library stack', 'assetpilot' ),
				'reason'           => sprintf(
					/* translators: 1: library group, 2: comma-separated handles */
					__( 'Multiple %1$s libraries detected: %2$s. Consider keeping one and disabling the rest.', 'assetpilot' ),
					$group,
					implode( ', ', $handles )
				),
				'related_handles'  => $handles,
			);
		}

		return $out;
	}
}
