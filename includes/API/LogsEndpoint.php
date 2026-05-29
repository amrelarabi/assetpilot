<?php
/**
 * Debug logs REST endpoints.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\API;

defined( 'ABSPATH' ) || exit;
use AssetControl\Database\LogRepository;
use AssetControl\Database\LogsListQuery;
use AssetControl\Helpers\Logger;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * List and manage persisted debug logs.
 */
final class LogsEndpoint {

	public function register(): void {
		register_rest_route(
			RESTController::NAMESPACE,
			'/logs',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_logs' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_logs' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function list_logs( WP_REST_Request $request ): WP_REST_Response {
		if ( ! Logger::is_enabled() ) {
			return new WP_REST_Response(
				array(
					'logs'            => array(),
					'total'           => 0,
					'page'            => 1,
					'per_page'        => 50,
					'debug_enabled'   => false,
					'types'           => array(),
				),
				200
			);
		}

		$query  = LogsListQuery::from_request( $request->get_params() );
		$result = ( new LogRepository() )->query( $query );

		return new WP_REST_Response(
			array(
				'logs'          => $result['items'],
				'total'         => $result['total'],
				'page'          => $query->page,
				'per_page'      => $query->per_page,
				'debug_enabled' => true,
				'types'         => ( new LogRepository() )->distinct_types(),
				'log_count'     => ( new LogRepository() )->count(),
			),
			200
		);
	}

	public function clear_logs( WP_REST_Request $request ): WP_REST_Response {
		$repo = new LogRepository();
		$repo->clear_all();
		Logger::flush();

		return new WP_REST_Response(
			array(
				'cleared'   => true,
				'log_count' => $repo->count(),
			),
			200
		);
	}
}
