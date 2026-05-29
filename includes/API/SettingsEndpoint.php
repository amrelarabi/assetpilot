<?php
/**
 * Settings REST endpoint.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\API;

defined( 'ABSPATH' ) || exit;
use AssetControl\Database\LogRepository;
use AssetControl\Database\ScanHistoryRepository;
use AssetControl\Helpers\Logger;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Plugin settings endpoint.
 */
final class SettingsEndpoint {

	private const OPTION = 'assetpilot_settings';

	public function register(): void {
		register_rest_route(
			RESTController::NAMESPACE,
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => static fn() => current_user_can( 'manage_options' ),
				),
			)
		);
	}

	public function get_settings(): WP_REST_Response {
		$defaults = array(
			'debug_logging' => false,
		);
		$settings = wp_parse_args( get_option( self::OPTION, array() ), $defaults );

		$settings['debug_logging'] = ! empty( $settings['debug_logging'] );
		$settings['log_count']       = Logger::is_enabled() ? ( new LogRepository() )->count() : 0;
		$settings['log_max_rows']    = LogRepository::MAX_ROWS;
		$settings['log_retention_days'] = LogRepository::RETENTION_DAYS;

		$scan_repo = new ScanHistoryRepository();
		$settings['scan_history_count']         = $scan_repo->count();
		$settings['scan_history_max_rows']      = ScanHistoryRepository::max_rows();
		$settings['scan_history_retention_days'] = ScanHistoryRepository::retention_days();

		return new WP_REST_Response( $settings, 200 );
	}

	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params() ?: array();
		$current = get_option( self::OPTION, array() );

		if ( isset( $params['debug_logging'] ) ) {
			$current['debug_logging'] = (bool) $params['debug_logging'];
		}

		update_option( self::OPTION, $current );

		return new WP_REST_Response( $current, 200 );
	}
}
