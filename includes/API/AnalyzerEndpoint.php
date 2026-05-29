<?php
/**
 * Page analyzer REST endpoint.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\API;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\AssetHandleResolver;
use AssetControl\Assets\FrontendScanner;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * POST /analyze — uses internal render mode (same as Assets Explorer).
 */
final class AnalyzerEndpoint {

	public function register(): void {
		register_rest_route(
			RESTController::NAMESPACE,
			'/analyze',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'analyze' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'args'                => array(
					'url' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);
	}

	public function analyze( WP_REST_Request $request ): WP_REST_Response {
		$resolver = new AssetHandleResolver();
		$url      = $resolver->sanitize_url( (string) $request->get_param( 'url' ) );

		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return new WP_REST_Response( array( 'message' => __( 'Invalid URL.', 'assetpilot' ) ), 400 );
		}

		$parsed_host = wp_parse_url( $url, PHP_URL_HOST );
		$home_host   = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( $parsed_host !== $home_host ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Only URLs from this site can be analyzed.', 'assetpilot' ) ),
				400
			);
		}

		$scan   = ( new FrontendScanner() )->scan_url( $url );
		$assets = is_array( $scan['assets'] ?? null ) ? $scan['assets'] : array();

		$scripts = array();
		$styles  = array();

		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}
			if ( 'script' === ( $asset['type'] ?? '' ) ) {
				$scripts[] = array(
					'src'      => $asset['src'],
					'handle'   => $asset['handle'],
					'blocking' => true,
				);
			} elseif ( 'style' === ( $asset['type'] ?? '' ) ) {
				$styles[] = array(
					'href'     => $asset['src'],
					'handle'   => $asset['handle'],
					'blocking' => true,
				);
			}
		}

		$parsed     = array(
			'scripts' => $scripts,
			'styles'  => $styles,
		);
		$duplicates = $this->find_duplicates( $parsed );
		$blocking   = array_values( $parsed['styles'] );

		return new WP_REST_Response(
			array(
				'url'             => $url,
				'scripts'         => $parsed['scripts'],
				'styles'          => $parsed['styles'],
				'duplicates'      => $duplicates,
				'render_blocking' => $blocking,
				'total_scripts'   => count( $parsed['scripts'] ),
				'total_styles'    => count( $parsed['styles'] ),
				'source'          => $scan['debug'] ?? '',
				'queues'          => $scan['meta']['queues'] ?? array(),
			),
			200
		);
	}

	/**
	 * @param array{scripts: array<int, array<string, mixed>>, styles: array<int, array<string, mixed>>} $parsed
	 * @return array<int, array<string, mixed>>
	 */
	private function find_duplicates( array $parsed ): array {
		$libs    = array( 'jquery', 'react', 'lodash', 'moment', 'swiper', 'elementor' );
		$found   = array();
		$all_src = array_merge(
			array_column( $parsed['scripts'], 'src' ),
			array_column( $parsed['styles'], 'href' )
		);

		foreach ( $libs as $lib ) {
			$matches = array_filter(
				$all_src,
				static fn( $src ) => str_contains( strtolower( (string) $src ), $lib )
			);
			if ( count( $matches ) > 1 ) {
				$found[] = array(
					'library' => $lib,
					'count'   => count( $matches ),
					'urls'    => array_values( $matches ),
				);
			}
		}

		return $found;
	}
}
