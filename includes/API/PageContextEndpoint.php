<?php
/**
 * Page context REST endpoint for intelligent rule conditions.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\API;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\AssetHandleResolver;
use AssetControl\Helpers\ScanPageContextResolver;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /page-context — suggest condition_group from a scan URL.
 */
final class PageContextEndpoint {

	public function register(): void {
		register_rest_route(
			RESTController::NAMESPACE,
			'/page-context',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_context' ),
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

	public function get_context( WP_REST_Request $request ): WP_REST_Response {
		$resolver = new AssetHandleResolver();
		$url      = $resolver->sanitize_url( (string) $request->get_param( 'url' ) );

		if ( '' === $url ) {
			return new WP_REST_Response( array( 'message' => __( 'URL is required.', 'assetpilot' ) ), 400 );
		}

		$context = ( new ScanPageContextResolver() )->resolve( $url );

		return new WP_REST_Response( $context );
	}
}
