<?php
/**
 * Rules REST endpoint.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\API;

defined( 'ABSPATH' ) || exit;
use AssetControl\Database\RulesListQuery;
use AssetControl\Database\RulesRepository;
use AssetControl\Validation\ValidationPipeline;
use AssetControl\Impact\ImpactPreviewService;
use AssetControl\Helpers\OutputBuffer;
use AssetControl\Verification\RuleVerificationService;
use AssetControl\Verification\RuntimeVerificationService;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * CRUD /rules
 */
final class RulesEndpoint {

	private RulesRepository $repository;

	private ValidationPipeline $validation;

	public function __construct() {
		$this->repository  = new RulesRepository();
		$this->validation  = new ValidationPipeline();
	}

	public function register(): void {
		register_rest_route(
			RESTController::NAMESPACE,
			'/rules',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_rules' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_rule' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			RESTController::NAMESPACE,
			'/rules/bulk',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulk_action' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			RESTController::NAMESPACE,
			'/rules/bulk-create',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'bulk_create_rules' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			RESTController::NAMESPACE,
			'/rules/validate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'validate_rule' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			RESTController::NAMESPACE,
			'/rules/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_rule' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_rule' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_rule' ),
					'permission_callback' => array( $this, 'can_manage' ),
				),
			)
		);

		register_rest_route(
			RESTController::NAMESPACE,
			'/rules/(?P<id>\d+)/duplicate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'duplicate_rule' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);

		register_rest_route(
			RESTController::NAMESPACE,
			'/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_dashboard' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'args'                => array(
					'summary_only' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function get_rules( WP_REST_Request $request ): WP_REST_Response {
		$query  = RulesListQuery::from_request( $request->get_params() );
		$result = $this->repository->query( $query );

		return new WP_REST_Response(
			array(
				'rules'    => $result['items'],
				'total'    => $result['total'],
				'page'     => $query->page,
				'per_page' => $query->per_page,
			),
			200
		);
	}

	public function bulk_create_rules( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params() ?: array();
		$assets = (array) ( $params['assets'] ?? array() );

		if ( empty( $assets ) ) {
			return new WP_REST_Response( array( 'message' => __( 'No assets selected.', 'assetpilot' ) ), 400 );
		}

		if ( count( $assets ) > 50 ) {
			return new WP_REST_Response(
				array( 'message' => __( 'You can select at most 50 assets per bulk rule.', 'assetpilot' ) ),
				400
			);
		}

		$mode = sanitize_key( (string) ( $params['mode'] ?? 'grouped' ) );
		if ( 'per_asset' === $mode ) {
			return $this->bulk_create_per_asset( $request, $assets, $params );
		}

		return $this->bulk_create_grouped( $request, $assets, $params );
	}

	/**
	 * One rule that applies to every selected asset (fast save, single verification).
	 *
	 * @param array<int, mixed>           $assets
	 * @param array<string, mixed>        $params
	 */
	private function bulk_create_grouped( WP_REST_Request $request, array $assets, array $params ): WP_REST_Response {
		$targets = $this->normalize_bulk_targets( $assets, (string) ( $params['action_type'] ?? 'disable' ) );
		if ( empty( $targets['items'] ) ) {
			return new WP_REST_Response(
				array(
					'message' => __( 'No valid assets for this action.', 'assetpilot' ),
					'errors'  => $targets['errors'],
				),
				400
			);
		}

		$first   = $targets['items'][0];
		$shared  = $this->sanitize_rule_data(
			array(
				'action_type'     => $params['action_type'] ?? 'disable',
				'condition_group' => $params['condition_group'] ?? array(),
				'action_config'   => $params['action_config'] ?? array(),
				'priority'        => $params['priority'] ?? 10,
				'enabled'         => $params['enabled'] ?? true,
				'label'           => $params['label'] ?? '',
				'notes'           => $params['notes'] ?? '',
			)
		);
		$count   = count( $targets['items'] );
		$label   = (string) ( $shared['label'] ?? '' );
		if ( '' === $label ) {
			$label = sprintf(
				/* translators: %d: asset count */
				__( 'Bulk rule (%d assets)', 'assetpilot' ),
				$count
			);
		}

		$action_config = is_array( $shared['action_config'] ?? null ) ? $shared['action_config'] : array();
		$action_config['bulk_group']  = true;
		$action_config['bulk_assets'] = $targets['items'];

		$rule_data = array_merge(
			$shared,
			array(
				'asset_handle'  => $first['handle'],
				'asset_type'    => $first['type'],
				'label'         => $label,
				'action_config' => $action_config,
			)
		);

		$blocked = $this->guard_validation( $rule_data, null, $request );
		if ( $blocked instanceof WP_REST_Response ) {
			return $blocked;
		}

		$rule = $this->repository->create( $rule_data );
		if ( ! $rule ) {
			return new WP_REST_Response( array( 'message' => __( 'Failed to create bulk rule.', 'assetpilot' ) ), 500 );
		}

		$rule = $this->attach_verification( $rule, false );

		return new WP_REST_Response(
			array(
				'mode'    => 'grouped',
				'created' => 1,
				'assets'  => $count,
				'rules'   => array( $rule ),
				'errors'  => $targets['errors'],
			),
			empty( $targets['errors'] ) ? 201 : 207
		);
	}

	/**
	 * Legacy: one database row per asset (no per-row verification for speed).
	 *
	 * @param array<int, mixed>    $assets
	 * @param array<string, mixed> $params
	 */
	private function bulk_create_per_asset( WP_REST_Request $request, array $assets, array $params ): WP_REST_Response {
		$shared = $this->sanitize_rule_data(
			array(
				'action_type'     => $params['action_type'] ?? 'disable',
				'condition_group' => $params['condition_group'] ?? array(),
				'action_config'   => $params['action_config'] ?? array(),
				'priority'        => $params['priority'] ?? 10,
				'enabled'         => $params['enabled'] ?? true,
				'label'           => $params['label'] ?? '',
				'notes'           => $params['notes'] ?? '',
			)
		);

		if ( empty( $shared['action_type'] ) ) {
			return new WP_REST_Response( array( 'message' => __( 'Action type is required.', 'assetpilot' ) ), 400 );
		}

		$group_label = (string) ( $shared['label'] ?? '' );
		$created     = array();
		$errors      = array();
		$validated   = false;

		foreach ( $assets as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$handle = sanitize_text_field( (string) ( $row['handle'] ?? '' ) );
			$type   = sanitize_key( (string) ( $row['type'] ?? 'script' ) );

			if ( '' === $handle ) {
				$errors[] = array(
					'index'   => $index,
					'message' => __( 'Missing asset handle.', 'assetpilot' ),
				);
				continue;
			}

			if ( ! $this->is_action_allowed_for_type( (string) $shared['action_type'], $type ) ) {
				$errors[] = array(
					'handle'  => $handle,
					'type'    => $type,
					'message' => __( 'Action is not allowed for this asset type.', 'assetpilot' ),
				);
				continue;
			}

			$rule_data = array_merge(
				$shared,
				array(
					'asset_handle' => $handle,
					'asset_type'   => $type,
					'label'        => $this->bulk_rule_label( $group_label, $handle ),
				)
			);

			if ( ! $validated ) {
				$blocked = $this->guard_validation( $rule_data, null, $request );
				if ( $blocked instanceof WP_REST_Response ) {
					$payload = $blocked->get_data();
					if ( is_array( $payload ) && 'assetpilot_validation_requires_confirm' === ( $payload['code'] ?? '' ) ) {
						return $blocked;
					}
				}
				$validated = true;
			}

			$rule = $this->repository->create( $rule_data );
			if ( ! $rule ) {
				$errors[] = array(
					'handle'  => $handle,
					'type'    => $type,
					'message' => __( 'Failed to create rule.', 'assetpilot' ),
				);
				continue;
			}

			$created[] = $this->attach_verification( $rule, false );
		}

		$status = empty( $created ) ? 400 : ( empty( $errors ) ? 201 : 207 );

		return new WP_REST_Response(
			array(
				'mode'    => 'per_asset',
				'created' => count( $created ),
				'rules'   => $created,
				'errors'  => $errors,
			),
			$status
		);
	}

	/**
	 * @param array<int, mixed> $assets
	 * @return array{items: array<int, array{handle: string, type: string, src: string}>, errors: array<int, array<string, mixed>>}
	 */
	private function normalize_bulk_targets( array $assets, string $action_type ): array {
		$items  = array();
		$errors = array();

		foreach ( $assets as $index => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$handle = sanitize_text_field( (string) ( $row['handle'] ?? '' ) );
			$type   = sanitize_key( (string) ( $row['type'] ?? 'script' ) );
			$src    = isset( $row['src'] ) ? sanitize_text_field( (string) $row['src'] ) : '';

			if ( '' === $handle ) {
				$errors[] = array(
					'index'   => $index,
					'message' => __( 'Missing asset handle.', 'assetpilot' ),
				);
				continue;
			}

			if ( ! $this->is_action_allowed_for_type( $action_type, $type ) ) {
				$errors[] = array(
					'handle'  => $handle,
					'type'    => $type,
					'message' => __( 'Action is not allowed for this asset type.', 'assetpilot' ),
				);
				continue;
			}

			$items[] = array(
				'handle' => $handle,
				'type'   => $type,
				'src'    => $src,
			);
		}

		return array(
			'items'  => $items,
			'errors' => $errors,
		);
	}

	public function bulk_action( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params() ?: array();
		$action = sanitize_key( (string) ( $params['action'] ?? '' ) );
		$ids    = array_map( 'intval', (array) ( $params['ids'] ?? array() ) );
		$ids    = array_values( array_filter( $ids ) );

		if ( empty( $ids ) ) {
			return new WP_REST_Response( array( 'message' => __( 'No rules selected.', 'assetpilot' ) ), 400 );
		}

		$affected = 0;
		switch ( $action ) {
			case 'enable':
				$affected = $this->repository->bulk_set_enabled( $ids, true );
				break;
			case 'disable':
				$affected = $this->repository->bulk_set_enabled( $ids, false );
				break;
			case 'delete':
				$affected = $this->repository->bulk_delete( $ids );
				break;
			default:
				return new WP_REST_Response( array( 'message' => __( 'Invalid bulk action.', 'assetpilot' ) ), 400 );
		}

		return new WP_REST_Response(
			array(
				'action'   => $action,
				'affected' => $affected,
			),
			200
		);
	}

	public function get_rule( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$rule = $this->repository->find( $id );

		if ( ! $rule ) {
			return new WP_REST_Response( array( 'message' => __( 'Rule not found.', 'assetpilot' ) ), 404 );
		}

		return new WP_REST_Response( $rule, 200 );
	}

	public function validate_rule( WP_REST_Request $request ): WP_REST_Response {
		$buffer_level = OutputBuffer::start();

		try {
			$params = $request->get_json_params() ?: array();
			$data   = $this->sanitize_rule_data( is_array( $params['rule'] ?? null ) ? $params['rule'] : $params );

			$basic = $this->validate_rule_data( $data );
			if ( is_wp_error( $basic ) ) {
				return new WP_REST_Response( array( 'message' => $basic->get_error_message() ), 400 );
			}

			$exclude_id = isset( $params['rule_id'] ) ? (int) $params['rule_id'] : null;
			$scan_url   = $this->scan_url_from_request( $params );
			$result     = $this->validation->validate( $data, $exclude_id ?: null, $scan_url );
			$payload    = $result->to_array();
			$payload['impact_preview'] = ( new ImpactPreviewService() )->preview( $data, $scan_url );

			return new WP_REST_Response( $payload, 200 );
		} catch ( \Throwable $e ) {
			return new WP_REST_Response(
				array(
					'message' => $e->getMessage(),
					'valid'   => false,
					'issues'  => array(),
				),
				500
			);
		} finally {
			OutputBuffer::end_clean( $buffer_level );
		}
	}

	public function create_rule( WP_REST_Request $request ): WP_REST_Response {
		$data = $this->sanitize_rule_data( $request->get_json_params() ?: array() );

		$blocked = $this->guard_validation( $data, null, $request );
		if ( $blocked instanceof WP_REST_Response ) {
			return $blocked;
		}

		$rule = $this->repository->create( $data );

		if ( ! $rule ) {
			return new WP_REST_Response( array( 'message' => __( 'Failed to create rule.', 'assetpilot' ) ), 500 );
		}

		$rule = $this->attach_verification( $rule );

		return new WP_REST_Response(
			array(
				'rule'       => $rule,
				'validation' => $blocked,
			),
			201
		);
	}

	public function update_rule( WP_REST_Request $request ): WP_REST_Response {
		$id       = (int) $request->get_param( 'id' );
		$existing = $this->repository->find( $id );

		if ( ! $existing ) {
			return new WP_REST_Response( array( 'message' => __( 'Rule not found.', 'assetpilot' ) ), 404 );
		}

		$partial = $this->sanitize_rule_data( $request->get_json_params() ?: array() );

		if ( empty( $partial ) ) {
			return new WP_REST_Response( array( 'message' => __( 'No valid fields to update.', 'assetpilot' ) ), 400 );
		}

		$enabled_only = 1 === count( $partial ) && array_key_exists( 'enabled', $partial );

		if ( ! $enabled_only ) {
			$merged  = $this->merge_rule_data( $existing, $partial );
			$blocked = $this->guard_validation( $merged, $id, $request );
			if ( $blocked instanceof WP_REST_Response ) {
				return $blocked;
			}
		} else {
			$blocked = array();
		}

		$rule = $this->repository->update( $id, $partial );

		if ( ! $rule ) {
			return new WP_REST_Response( array( 'message' => __( 'Rule not found or update failed.', 'assetpilot' ) ), 404 );
		}

		$rule = $this->attach_verification( $rule );

		return new WP_REST_Response(
			array(
				'rule'       => $rule,
				'validation' => $blocked,
			),
			200
		);
	}

	public function delete_rule( WP_REST_Request $request ): WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$delete = $this->repository->delete( $id );

		if ( ! $delete ) {
			return new WP_REST_Response( array( 'message' => __( 'Rule not found.', 'assetpilot' ) ), 404 );
		}

		return new WP_REST_Response( array( 'deleted' => true ), 200 );
	}

	public function duplicate_rule( WP_REST_Request $request ): WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$rule = $this->repository->find( $id );

		if ( ! $rule ) {
			return new WP_REST_Response( array( 'message' => __( 'Rule not found.', 'assetpilot' ) ), 404 );
		}

		unset( $rule['id'], $rule['created_at'], $rule['updated_at'], $rule['verification'] );
		$rule['enabled'] = false;
		if ( ! empty( $rule['label'] ) ) {
			$rule['label'] = sprintf(
				/* translators: %s: original rule label */
				__( '%s (copy)', 'assetpilot' ),
				$rule['label']
			);
		}
		$new             = $this->repository->create( $rule );

		return new WP_REST_Response( $new, 201 );
	}

	public function get_dashboard( WP_REST_Request $request ): WP_REST_Response {
		$rules   = $this->repository->all_cached();
		$payload = $this->dashboard_rules_payload( $rules );

		if ( filter_var( $request->get_param( 'summary_only' ), FILTER_VALIDATE_BOOLEAN ) ) {
			return new WP_REST_Response( $payload, 200 );
		}

		$assets = $this->dashboard_collect_assets( home_url( '/' ) );
		$payload = array_merge( $payload, $this->dashboard_assets_payload( $assets ) );

		return new WP_REST_Response( $payload, 200 );
	}

	/**
	 * @param array<int, array<string, mixed>> $rules
	 * @return array<string, mixed>
	 */
	private function dashboard_rules_payload( array $rules ): array {
		return array(
			'scan_url'      => home_url( '/' ),
			'total_assets'  => 0,
			'largest_assets' => array(),
			'total_rules'   => count( $rules ),
			'enabled_rules' => count( array_filter( $rules, static fn( $r ) => $r['enabled'] ) ),
			'recent_rules'  => array_slice( array_reverse( $rules ), 0, 5 ),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $assets
	 * @return array<string, mixed>
	 */
	private function dashboard_assets_payload( array $assets ): array {
		usort(
			$assets,
			static fn( $a, $b ) => ( $b['size'] ?? 0 ) <=> ( $a['size'] ?? 0 )
		);

		return array(
			'total_assets'   => count( $assets ),
			'largest_assets' => array_slice( $assets, 0, 5 ),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function dashboard_collect_assets( string $url ): array {
		$scanner = new \AssetControl\Assets\FrontendScanner();
		$scan    = $scanner->scan_url( $url );
		$assets  = $scan['assets'];

		if ( empty( $assets ) ) {
			$assets = ( new \AssetControl\Assets\FrontendContext() )->collect_for_url( $url );
		}

		if ( empty( $assets ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook required for dependency validation.
			do_action( 'wp_enqueue_scripts' );
			$assets = ( new \AssetControl\Assets\Registry() )->collect();
		}

		return is_array( $assets ) ? $assets : array();
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function sanitize_rule_data( array $data ): array {
		$allowed_actions = array( 'disable', 'defer', 'async', 'preload', 'fetchpriority' );
		$allowed_types   = array( 'script', 'style', 'image', 'font' );

		$out = array();

		if ( isset( $data['asset_handle'] ) ) {
			$out['asset_handle'] = sanitize_text_field( (string) $data['asset_handle'] );
		}
		if ( isset( $data['asset_type'] ) && in_array( $data['asset_type'], $allowed_types, true ) ) {
			$out['asset_type'] = $data['asset_type'];
		}
		if ( isset( $data['action_type'] ) && in_array( $data['action_type'], $allowed_actions, true ) ) {
			$out['action_type'] = $data['action_type'];
		}
		if ( isset( $data['condition_group'] ) && is_array( $data['condition_group'] ) ) {
			$out['condition_group'] = $this->sanitize_conditions( $data['condition_group'] );
		}
		if ( isset( $data['action_config'] ) && is_array( $data['action_config'] ) ) {
			$out['action_config'] = $this->sanitize_action_config( $data['action_config'] );
		}
		if ( isset( $data['priority'] ) ) {
			$out['priority'] = (int) $data['priority'];
		}
		if ( isset( $data['enabled'] ) ) {
			$out['enabled'] = (bool) $data['enabled'];
		}
		if ( array_key_exists( 'label', $data ) ) {
			$out['label'] = sanitize_text_field( (string) $data['label'] );
		}
		if ( array_key_exists( 'notes', $data ) ) {
			$out['notes'] = sanitize_textarea_field( (string) $data['notes'] );
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $conditions
	 * @return array<string, mixed>
	 */
	private function sanitize_conditions( array $conditions ): array {
		$clean = array();

		$scalar_keys = array( 'scope', 'device', 'url_contains', 'url_path', 'scan_page_url', 'query_contains', 'logged_in', 'global' );
		foreach ( $scalar_keys as $key ) {
			if ( isset( $conditions[ $key ] ) ) {
				if ( 'scan_page_url' === $key ) {
					$url           = trim( (string) $conditions[ $key ] );
					$clean[ $key ] = '' !== $url ? esc_url_raw( $url ) : '';
					continue;
				}
				$clean[ $key ] = is_bool( $conditions[ $key ] )
					? $conditions[ $key ]
					: sanitize_text_field( (string) $conditions[ $key ] );
			}
		}

		if ( isset( $conditions['url_match_type'] ) ) {
			$mode = sanitize_key( (string) $conditions['url_match_type'] );
			if ( in_array( $mode, array( 'contains', 'starts_with' ), true ) ) {
				$clean['url_match_type'] = $mode;
			}
		}

		if ( ! empty( $clean['url_path'] ) && empty( $clean['url_contains'] ) ) {
			$clean['url_contains'] = $clean['url_path'];
		}

		$string_array_keys = array( 'post_type', 'archive', 'woocommerce', 'singular_type', 'user_roles' );
		foreach ( $string_array_keys as $key ) {
			if ( ! empty( $conditions[ $key ] ) ) {
				$clean[ $key ] = array_map( 'sanitize_text_field', (array) $conditions[ $key ] );
			}
		}

		$int_array_keys = array( 'include_ids', 'exclude_ids', 'post_ids' );
		foreach ( $int_array_keys as $key ) {
			if ( ! empty( $conditions[ $key ] ) ) {
				$clean[ $key ] = array_map( 'intval', (array) $conditions[ $key ] );
			}
		}

		return $clean;
	}

	/**
	 * @param array<string, mixed> $config
	 * @return array<string, mixed>
	 */
	private function sanitize_action_config( array $config ): array {
		$clean = array();

		$string_keys = array( 'href', 'src', 'as', 'value', 'fetchpriority', 'scan_url' );
		foreach ( $string_keys as $key ) {
			if ( ! isset( $config[ $key ] ) ) {
				continue;
			}
			if ( in_array( $key, array( 'href', 'src', 'scan_url' ), true ) ) {
				$url           = trim( (string) $config[ $key ] );
				$clean[ $key ] = ( new \AssetControl\Assets\AssetHandleResolver() )->sanitize_url( $url );
				continue;
			}
			$clean[ $key ] = sanitize_text_field( (string) $config[ $key ] );
		}

		if ( isset( $config['crossorigin'] ) ) {
			$clean['crossorigin'] = (bool) $config['crossorigin'];
		}

		if ( isset( $config['attachment_id'] ) ) {
			$clean['attachment_id'] = (int) $config['attachment_id'];
		}

		if ( ! empty( $config['bulk_group'] ) ) {
			$clean['bulk_group'] = true;
		}

		if ( ! empty( $config['bulk_assets'] ) && is_array( $config['bulk_assets'] ) ) {
			$clean['bulk_assets'] = array();
			foreach ( $config['bulk_assets'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$handle = sanitize_text_field( (string) ( $row['handle'] ?? '' ) );
				$type   = sanitize_key( (string) ( $row['type'] ?? 'script' ) );
				if ( '' === $handle ) {
					continue;
				}
				$entry = array(
					'handle' => $handle,
					'type'   => $type,
				);
				if ( isset( $row['src'] ) ) {
					$entry['src'] = sanitize_text_field( (string) $row['src'] );
				}
				$clean['bulk_assets'][] = $entry;
			}
		}

		return $clean;
	}

	/**
	 * @param array<string, mixed> $existing
	 * @param array<string, mixed> $partial
	 * @return array<string, mixed>
	 */
	private function merge_rule_data( array $existing, array $partial ): array {
		return array_merge(
			array(
				'asset_handle'    => (string) ( $existing['asset_handle'] ?? '' ),
				'asset_type'      => (string) ( $existing['asset_type'] ?? 'script' ),
				'action_type'     => (string) ( $existing['action_type'] ?? 'disable' ),
				'condition_group' => is_array( $existing['condition_group'] ?? null )
					? $existing['condition_group']
					: array(),
				'action_config'   => is_array( $existing['action_config'] ?? null )
					? $existing['action_config']
					: array(),
				'priority'        => (int) ( $existing['priority'] ?? 10 ),
				'enabled'         => (bool) ( $existing['enabled'] ?? true ),
				'label'           => (string) ( $existing['label'] ?? '' ),
				'notes'           => (string) ( $existing['notes'] ?? '' ),
			),
			$partial
		);
	}

	/**
	 * @param array<string, mixed> $data
	 * @return true|\WP_Error
	 */
	private function is_action_allowed_for_type( string $action, string $asset_type ): bool {
		$map = array(
			'disable'       => array( 'script', 'style', 'image', 'font' ),
			'defer'         => array( 'script' ),
			'async'         => array( 'script' ),
			'preload'       => array( 'script', 'style', 'image', 'font' ),
			'fetchpriority' => array( 'script', 'image' ),
		);

		return isset( $map[ $action ] ) && in_array( $asset_type, $map[ $action ], true );
	}

	private function bulk_rule_label( string $group_label, string $handle ): string {
		if ( '' === $group_label ) {
			return '';
		}

		return $group_label . ' — ' . $handle;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return true|\WP_Error
	 */
	private function validate_rule_data( array $data ) {
		if ( empty( $data['asset_handle'] ) ) {
			return new \WP_Error( 'assetpilot_invalid', __( 'Asset handle is required.', 'assetpilot' ) );
		}
		if ( empty( $data['action_type'] ) ) {
			return new \WP_Error( 'assetpilot_invalid', __( 'Action type is required.', 'assetpilot' ) );
		}
		if ( empty( $data['asset_type'] ) ) {
			return new \WP_Error( 'assetpilot_invalid', __( 'Asset type is required.', 'assetpilot' ) );
		}
		return true;
	}

	/**
	 * @return array<string, mixed>|WP_REST_Response Validation array, or error response when blocked.
	 */
	private function guard_validation( array $data, ?int $rule_id, WP_REST_Request $request ) {
		$buffer_level = OutputBuffer::start();

		try {
			$basic = $this->validate_rule_data( $data );
			if ( is_wp_error( $basic ) ) {
				return new WP_REST_Response( array( 'message' => $basic->get_error_message() ), 400 );
			}

			$params   = $request->get_json_params();
			$scan_url = $this->scan_url_from_request( is_array( $params ) ? $params : array() );
			$result  = $this->validation->validate( $data, $rule_id, $scan_url );
			$confirm = $this->confirm_danger_requested( $request );

			if ( $result->has_danger() && ! $confirm ) {
				return new WP_REST_Response(
					array(
						'code'       => 'assetpilot_validation_requires_confirm',
						'message'    => __( 'This rule has dangerous conflicts. Confirm to save anyway.', 'assetpilot' ),
						'validation' => $result->to_array(),
					),
					409
				);
			}

			return $result->to_array();
		} finally {
			OutputBuffer::end_clean( $buffer_level );
		}
	}

	/**
	 * @param array<string, mixed> $params
	 */
	private function scan_url_from_request( array $params ): string {
		$raw = $params['scan_url'] ?? '';
		if ( ! is_string( $raw ) || '' === $raw ) {
			return '';
		}

		$clean = esc_url_raw( rawurldecode( $raw ) );
		return '' !== $clean ? $clean : $raw;
	}

	private function confirm_danger_requested( WP_REST_Request $request ): bool {
		$params = $request->get_json_params();
		if ( is_array( $params ) && array_key_exists( 'confirm_danger', $params ) ) {
			return rest_sanitize_boolean( $params['confirm_danger'] );
		}
		return rest_sanitize_boolean( $request->get_param( 'confirm_danger' ) );
	}

	/**
	 * @param array<string, mixed> $rule
	 * @return array<string, mixed>
	 */
	private function attach_verification( array $rule, bool $run = true ): array {
		if ( ! $run ) {
			$rule['verification'] = array(
				'rule_id'     => (int) ( $rule['id'] ?? 0 ),
				'status'      => RuntimeVerificationService::STATUS_SKIPPED,
				'message'     => __( 'Verification skipped during bulk save. Use Re-verify all on the Rules page.', 'assetpilot' ),
				'expected'    => '',
				'actual'      => '',
				'url'         => '',
				'verified_at' => gmdate( 'c' ),
			);
			return $rule;
		}

		try {
			$rule['verification'] = ( new RuleVerificationService() )->verify_and_store( $rule );
		} catch ( \Throwable $e ) {
			$rule['verification'] = array(
				'rule_id'     => (int) ( $rule['id'] ?? 0 ),
				'status'      => RuntimeVerificationService::STATUS_UNAVAILABLE,
				'message'     => $e->getMessage(),
				'expected'    => '',
				'actual'      => '',
				'url'         => '',
				'verified_at' => gmdate( 'c' ),
			);
		}
		return $rule;
	}
}
