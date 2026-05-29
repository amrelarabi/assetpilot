<?php
/**
 * Assets REST endpoint.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\API;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\AssetMetadataService;
use AssetControl\Assets\AssetUsageService;
use AssetControl\Assets\FrontendScanner;
use AssetControl\Assets\ScanSnapshotService;
use AssetControl\Database\ScanHistoryRepository;
use AssetControl\Assets\Registry;
use AssetControl\Helpers\Cache;
use AssetControl\Rules\DependencyAnalyzer;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * GET /assets
 */
final class AssetsEndpoint {

	public function register(): void {
		register_rest_route(
			RESTController::NAMESPACE,
			'/assets',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_assets' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'search'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'type'     => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'origin'   => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					),
					'scan_url' => array(
						'type' => 'string',
					),
					'refresh'  => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'scan_id'  => array(
						'type' => 'integer',
					),
				),
			)
		);

		register_rest_route(
			RESTController::NAMESPACE,
			'/assets/(?P<handle>[^/]+)/dependencies',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_dependencies' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'handle' => array( 'required' => true ),
					'type'   => array(
						'default'           => 'script',
						'sanitize_callback' => 'sanitize_key',
					),
					'action' => array(
						'default'           => 'disable',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			RESTController::NAMESPACE,
			'/assets/(?P<handle>[^/]+)/details',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_details' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'handle'   => array( 'required' => true ),
					'type'     => array(
						'default'           => 'script',
						'sanitize_callback' => 'sanitize_key',
					),
					'scan_url' => array( 'type' => 'string' ),
					'enqueued' => array( 'type' => 'boolean' ),
					'size'     => array( 'type' => 'integer' ),
					'src'      => array( 'type' => 'string' ),
					'origin'   => array( 'type' => 'string' ),
					'source'   => array( 'type' => 'string' ),
					'version'  => array( 'type' => 'string' ),
				),
			)
		);
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_assets( WP_REST_Request $request ): WP_REST_Response {
		$scan_id = (int) $request->get_param( 'scan_id' );
		if ( $scan_id > 0 ) {
			return $this->get_assets_from_history( $request, $scan_id );
		}

		$scan_url = $this->normalize_scan_url( $request->get_param( 'scan_url' ) );
		$target   = $scan_url ?: home_url( '/' );

		$scanner = new FrontendScanner();
		$refresh = rest_sanitize_boolean( $request->get_param( 'refresh' ) );
		$result  = $scanner->scan_url( $target, $refresh );
		$meta   = $result['meta'] ?? array();
		$assets = $this->prepare_assets( $result['assets'] ?? array() );
		$source = (string) ( $result['debug'] ?? 'scan_empty' );

		if ( empty( $assets ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook required for asset inventory.
			\do_action( 'wp_enqueue_scripts' );
			$assets = $this->prepare_assets( ( new Registry() )->collect() );
			$source = empty( $assets ) ? $source : 'admin_fallback';
		}

		$search   = $request->get_param( 'search' );
		$type     = $request->get_param( 'type' );
		$origin   = $request->get_param( 'origin' );
		$scan_record = null;
		if ( ! empty( $assets ) ) {
			( new AssetUsageService() )->record_from_scan( $target, $assets );
			$scan_record = ( new ScanSnapshotService() )->save( $target, $assets, $source );
		}

		$filtered = $this->filter_assets( $assets, $search, $type, $origin );

		// Never drop every prepared asset because of a filter mismatch.
		if ( empty( $filtered ) && ! empty( $assets ) ) {
			$filtered = $assets;
		}

		return new WP_REST_Response(
			array(
				'assets'   => $filtered,
				'total'    => count( $filtered ),
				'source'   => $source,
				'scan_url' => $target,
				'scan_id'  => $scan_record['id'] ?? null,
				'meta'     => array_merge(
					$meta,
					array(
						'returned_count' => count( $filtered ),
						'prepared_count' => count( $assets ),
					)
				),
			),
			200
		);
	}

	public function get_assets_from_history( WP_REST_Request $request, int $scan_id ): WP_REST_Response {
		$scan = ( new ScanHistoryRepository() )->find( $scan_id, true );

		if ( ! $scan ) {
			return new WP_REST_Response( array( 'message' => __( 'Scan not found.', 'assetpilot' ) ), 404 );
		}

		$assets   = is_array( $scan['assets'] ?? null ) ? $scan['assets'] : array();
		$search   = $request->get_param( 'search' );
		$type     = $request->get_param( 'type' );
		$origin   = $request->get_param( 'origin' );
		$filtered = $this->filter_assets( $assets, $search, $type, $origin );

		if ( empty( $filtered ) && ! empty( $assets ) ) {
			$filtered = $assets;
		}

		return new WP_REST_Response(
			array(
				'assets'     => $filtered,
				'total'      => count( $filtered ),
				'source'     => 'scan_history',
				'scan_url'   => (string) ( $scan['scan_url'] ?? '' ),
				'scan_id'    => $scan_id,
				'scanned_at' => (string) ( $scan['scanned_at'] ?? '' ),
				'meta'       => array(
					'returned_count' => count( $filtered ),
					'prepared_count' => count( $assets ),
					'from_history'   => true,
				),
			),
			200
		);
	}

	/**
	 * @param mixed $assets
	 * @return array<int, array<string, mixed>>
	 */
	private function prepare_assets( $assets ): array {
		if ( ! is_array( $assets ) ) {
			return array();
		}

		$prepared = array();

		foreach ( array_values( $assets ) as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}

			$src = trim( (string) ( $asset['src'] ?? $asset['href'] ?? '' ) );
			if ( '' === $src ) {
				continue;
			}

			$handle = (string) ( $asset['handle'] ?? '' );
			if ( '' === $handle ) {
				$path   = (string) wp_parse_url( $src, PHP_URL_PATH );
				$base   = pathinfo( $path, PATHINFO_FILENAME ) ?: 'asset';
				$dir    = basename( dirname( $path ) );
				$handle = sanitize_title( $dir . '-' . $base ) ?: 'assetpilot-asset';
			}

			$prepared[] = array(
				'handle'     => $handle,
				'type'       => (string) ( $asset['type'] ?? 'script' ),
				'src'        => $src,
				'deps'       => is_array( $asset['deps'] ?? null ) ? $asset['deps'] : array(),
				'version'    => (string) ( $asset['version'] ?? '' ),
				'media'      => $asset['media'] ?? null,
				'origin'     => (string) ( $asset['origin'] ?? 'unknown' ),
				'source'     => (string) ( $asset['source'] ?? '' ),
				'size'       => $asset['size'] ?? null,
				'in_footer'  => $asset['in_footer'] ?? null,
				'enqueued'   => (bool) ( $asset['enqueued'] ?? true ),
				'registered' => (bool) ( $asset['registered'] ?? true ),
				'from_html'  => (bool) ( $asset['from_html'] ?? false ),
			);
		}

		return $prepared;
	}

	/**
	 * @param array<int, array<string, mixed>> $assets
	 * @return array<int, array<string, mixed>>
	 */
	private function filter_assets( array $assets, $search, $type, $origin ): array {
		return array_values(
			array_filter(
				$assets,
				static function ( array $asset ) use ( $search, $type, $origin ): bool {
					if ( $type && ( $asset['type'] ?? '' ) !== $type ) {
						return false;
					}
					if ( $origin && ( $asset['origin'] ?? '' ) !== $origin ) {
						return false;
					}
					if ( $search ) {
						$haystack = strtolower(
							( $asset['handle'] ?? '' ) . ' ' . ( $asset['src'] ?? '' ) . ' ' . ( $asset['source'] ?? '' )
						);
						if ( ! str_contains( $haystack, strtolower( (string) $search ) ) ) {
							return false;
						}
					}
					return true;
				}
			)
		);
	}

	private function normalize_scan_url( $url ): string {
		if ( ! is_string( $url ) || '' === $url ) {
			return '';
		}

		$url = rawurldecode( $url );
		$clean = esc_url_raw( $url );

		return '' !== $clean ? $clean : $url;
	}

	public function get_details( WP_REST_Request $request ): WP_REST_Response {
		$handle = sanitize_text_field( $request->get_param( 'handle' ) );
		$type   = sanitize_key( $request->get_param( 'type' ) ?: 'script' );
		$scan_url = $this->normalize_scan_url( $request->get_param( 'scan_url' ) );

		$snapshot = $this->snapshot_from_request( $request );

		$service = new AssetMetadataService();
		$data    = $service->get_details( $handle, $type, $scan_url, $snapshot );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private function snapshot_from_request( WP_REST_Request $request ): ?array {
		$snapshot = array();

		if ( null !== $request->get_param( 'enqueued' ) ) {
			$snapshot['enqueued'] = rest_sanitize_boolean( $request->get_param( 'enqueued' ) );
		}
		if ( null !== $request->get_param( 'size' ) ) {
			$snapshot['size'] = (int) $request->get_param( 'size' );
		}
		if ( is_string( $request->get_param( 'src' ) ) && '' !== $request->get_param( 'src' ) ) {
			$snapshot['src'] = esc_url_raw( (string) $request->get_param( 'src' ) );
		}
		if ( is_string( $request->get_param( 'origin' ) ) && '' !== $request->get_param( 'origin' ) ) {
			$snapshot['origin'] = sanitize_key( (string) $request->get_param( 'origin' ) );
		}
		if ( is_string( $request->get_param( 'source' ) ) ) {
			$snapshot['source'] = sanitize_text_field( (string) $request->get_param( 'source' ) );
		}
		if ( is_string( $request->get_param( 'version' ) ) ) {
			$snapshot['version'] = sanitize_text_field( (string) $request->get_param( 'version' ) );
		}

		return empty( $snapshot ) ? null : $snapshot;
	}

	public function get_dependencies( WP_REST_Request $request ): WP_REST_Response {
		$handle = sanitize_text_field( $request->get_param( 'handle' ) );
		$type   = sanitize_key( $request->get_param( 'type' ) ?: 'script' );
		$action = sanitize_key( $request->get_param( 'action' ) ?: 'disable' );

		$payload = Cache::request(
			Cache::dependency_analysis_key( $handle, $type, $action ) . '_api',
			function () use ( $handle, $type, $action ): array {
				$analyzer = new DependencyAnalyzer();
				$result   = $analyzer->analyze( $handle, $action, $type );

				return array(
					'handle'     => $handle,
					'chain'      => $analyzer->get_chain( $handle, $type ),
					'dependents' => $result['dependents'],
					'warnings'   => $result['warnings'],
				);
			}
		);

		return new WP_REST_Response( $payload, 200 );
	}
}
