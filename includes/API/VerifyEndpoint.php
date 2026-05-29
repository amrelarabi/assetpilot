<?php
/**
 * Runtime verification REST endpoint.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\API;

defined( 'ABSPATH' ) || exit;
use AssetControl\Verification\RuleVerificationService;
use AssetControl\Verification\RuntimeVerificationService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * POST /verify — check rules against live frontend HTML.
 */
final class VerifyEndpoint {

	public function register(): void {
		register_rest_route(
			RESTController::NAMESPACE,
			'/verify',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'verify' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'url' => array( 'type' => 'string' ),
				),
			)
		);
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function verify( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params() ?: array();
		$url    = isset( $params['url'] ) ? (string) $params['url'] : (string) $request->get_param( 'url' );

		$verifier = new RuleVerificationService();

		if ( ! empty( $params['rule_id'] ) ) {
			$by_id = $verifier->verify_many( array( (int) $params['rule_id'] ) );
			return $this->response_from_snapshots( $by_id );
		}

		if ( ! empty( $params['rule_ids'] ) && is_array( $params['rule_ids'] ) ) {
			$ids   = array_map( 'intval', $params['rule_ids'] );
			$by_id = $verifier->verify_many( $ids );
			return $this->response_from_snapshots( $by_id );
		}

		if ( '' !== trim( $url ) ) {
			$args = array();
			if ( ! empty( $params['asset_handle'] ) ) {
				$args['asset_handle'] = sanitize_text_field( (string) $params['asset_handle'] );
			}
			if ( ! empty( $params['asset_type'] ) ) {
				$args['asset_type'] = sanitize_key( (string) $params['asset_type'] );
			}

			$service = new RuntimeVerificationService();
			$data    = $service->verify_url( $url, $args );

			$by_id = array();
			foreach ( $data['results'] as $row ) {
				$by_id[ (int) ( $row['rule_id'] ?? 0 ) ] = $row;
			}

			return new WP_REST_Response(
				array(
					'url'     => $data['url'],
					'error'   => $data['error'],
					'results' => $data['results'],
					'by_id'   => $by_id,
				),
				200
			);
		}

		$by_id = $verifier->verify_many();
		return $this->response_from_snapshots( $by_id );
	}

	/**
	 * @param array<int, array<string, mixed>> $by_id
	 */
	private function response_from_snapshots( array $by_id ): WP_REST_Response {
		$results = array_values( $by_id );
		$urls    = array_unique(
			array_filter(
				array_map(
					static fn( array $row ): string => (string) ( $row['url'] ?? '' ),
					$results
				)
			)
		);

		return new WP_REST_Response(
			array(
				'url'     => 1 === count( $urls ) ? (string) $urls[0] : '',
				'error'   => '',
				'results' => $results,
				'by_id'   => $by_id,
			),
			200
		);
	}
}

