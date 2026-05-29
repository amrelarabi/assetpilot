<?php
/**
 * In-process asset capture REST endpoint (no HTTP loopback).
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\API;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\AssetCapture;
use AssetControl\Assets\AssetHandleResolver;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /capture — runs frontend enqueue simulation in the current PHP process.
 */
final class CaptureAssetsEndpoint {

	public function register(): void {
		register_rest_route(
			RESTController::NAMESPACE,
			'/capture',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'capture' ),
				'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				'args'                => array(
					'url' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);
	}

	public function capture( WP_REST_Request $request ): WP_REST_Response {
		$resolver = new AssetHandleResolver();
		$url      = $resolver->sanitize_url( (string) $request->get_param( 'url' ) );

		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return new WP_REST_Response( array( 'message' => __( 'Invalid URL.', 'assetpilot' ) ), 400 );
		}

		$parsed_host = wp_parse_url( $url, PHP_URL_HOST );
		$home_host   = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( $parsed_host !== $home_host ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Only URLs from this site can be captured.', 'assetpilot' ) ),
				400
			);
		}

		$result = ( new AssetCapture() )->capture_for_url( $url );

		return new WP_REST_Response(
			array(
				'assets' => $result['assets'],
				'queues' => $result['queues'],
				'url'    => $url,
				'error'  => $result['error'],
			),
			200
		);
	}
}
