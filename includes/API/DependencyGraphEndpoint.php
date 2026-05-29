<?php
/**
 * Dependency graph REST endpoint.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\API;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\DependencyGraphBuilder;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Returns graph nodes/edges computed in PHP.
 */
final class DependencyGraphEndpoint {

	public function register(): void {
		register_rest_route(
			RESTController::NAMESPACE,
			'/dependency-graph',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_graph' ),
					'permission_callback' => static fn() => current_user_can( 'manage_options' ),
					'args'                => array(
						'scan_url'      => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'esc_url_raw',
						),
						'asset_type'    => array(
							'type'    => 'string',
							'default' => 'all',
						),
						'focus_handle'  => array(
							'type' => 'string',
						),
						'focus_type'    => array(
							'type'    => 'string',
							'default' => 'script',
						),
					),
				),
			)
		);
	}

	public function get_graph( WP_REST_Request $request ): WP_REST_Response {
		$scan_url = (string) ( $request->get_param( 'scan_url' ) ?? '' );
		if ( '' === $scan_url ) {
			$scan_url = (string) home_url( '/' );
		}

		$asset_type   = sanitize_key( (string) ( $request->get_param( 'asset_type' ) ?? 'all' ) );
		$focus_handle = sanitize_text_field( (string) ( $request->get_param( 'focus_handle' ) ?? '' ) );
		$focus_type   = sanitize_key( (string) ( $request->get_param( 'focus_type' ) ?? 'script' ) );

		if ( ! in_array( $asset_type, array( 'all', 'script', 'scripts', 'style', 'styles' ), true ) ) {
			$asset_type = 'all';
		}

		if ( ! in_array( $focus_type, array( 'script', 'style' ), true ) ) {
			$focus_type = 'script';
		}

		try {
			$graph = ( new DependencyGraphBuilder() )->build( $scan_url, $asset_type, $focus_handle, $focus_type );
		} catch ( \Throwable $e ) {
			return new WP_REST_Response(
				array(
					'message' => $e->getMessage(),
					'nodes'   => array(),
					'edges'   => array(),
				),
				500
			);
		}

		return new WP_REST_Response( $graph, 200 );
	}
}
