<?php
/**
 * Recommendations REST endpoint.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\API;

defined( 'ABSPATH' ) || exit;
use AssetControl\Recommendations\RecommendationEngine;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /recommendations
 */
final class RecommendationsEndpoint {

	public function register(): void {
		register_rest_route(
			RESTController::NAMESPACE,
			'/recommendations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_recommendations' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'args'                => array(
					'scan_url' => array(
						'type' => 'string',
					),
					'scan_id'  => array(
						'type' => 'integer',
					),
				),
			)
		);
	}

	public function get_recommendations( WP_REST_Request $request ): WP_REST_Response {
		$scan_url = (string) ( $request->get_param( 'scan_url' ) ?? '' );
		if ( '' === $scan_url ) {
			$scan_url = (string) home_url( '/' );
		}

		$scan_id = (int) $request->get_param( 'scan_id' );

		$result = ( new RecommendationEngine() )->collect( $scan_url, $scan_id );

		return new WP_REST_Response( $result, 200 );
	}
}
