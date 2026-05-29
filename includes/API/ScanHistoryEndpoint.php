<?php
/**
 * Scan history REST endpoints.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\API;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\ScanSnapshotService;
use AssetControl\Database\ScanHistoryRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * CRUD and compare for scan history.
 */
final class ScanHistoryEndpoint {

	public function register(): void {
		register_rest_route(
			RESTController::NAMESPACE,
			'/scans',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_scans' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			RESTController::NAMESPACE,
			'/scans/compare',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'compare_scans' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'a' => array(
						'required' => true,
						'type'     => 'integer',
					),
					'b' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			RESTController::NAMESPACE,
			'/scans/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_scan' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_scan' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function list_scans( WP_REST_Request $request ): WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ?: 20 ) );
		$search   = sanitize_text_field( (string) ( $request->get_param( 'search' ) ?? '' ) );

		$repo   = new ScanHistoryRepository();
		$result = $repo->list( $page, $per_page, $search );

		return new WP_REST_Response(
			array(
				'scans'     => $result['items'],
				'total'     => $result['total'],
				'page'      => $page,
				'per_page'  => $per_page,
				'retention' => array(
					'max_rows'       => ScanHistoryRepository::max_rows(),
					'retention_days' => ScanHistoryRepository::retention_days(),
				),
			),
			200
		);
	}

	public function get_scan( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$scan = ( new ScanSnapshotService() )->get( $id );

		if ( ! $scan ) {
			return new WP_REST_Response( array( 'message' => __( 'Scan not found.', 'assetpilot' ) ), 404 );
		}

		return new WP_REST_Response( $scan, 200 );
	}

	public function delete_scan( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$delete = ( new ScanHistoryRepository() )->delete( $id );

		if ( ! $delete ) {
			return new WP_REST_Response( array( 'message' => __( 'Scan not found.', 'assetpilot' ) ), 404 );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	public function compare_scans( WP_REST_Request $request ): WP_REST_Response {
		$a = (int) $request->get_param( 'a' );
		$b = (int) $request->get_param( 'b' );

		if ( $a === $b ) {
			return new WP_REST_Response(
				array( 'message' => __( 'Choose two different scans to compare.', 'assetpilot' ) ),
				400
			);
		}

		$diff = ( new ScanSnapshotService() )->compare( $a, $b );

		if ( ! $diff ) {
			return new WP_REST_Response( array( 'message' => __( 'One or both scans were not found.', 'assetpilot' ) ), 404 );
		}

		return new WP_REST_Response( $diff, 200 );
	}
}
