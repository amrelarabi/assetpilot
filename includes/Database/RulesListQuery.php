<?php
/**
 * Query parameters for paginated rules list.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Database;

defined( 'ABSPATH' ) || exit;
/**
 * Filters and pagination for rules listing.
 */
final class RulesListQuery {

	public function __construct(
		public readonly int $page = 1,
		public readonly int $per_page = 20,
		public readonly string $search = '',
		public readonly string $asset_handle = '',
		public readonly string $action_type = '',
		public readonly string $asset_type = '',
		public readonly string $condition_type = '',
		public readonly ?bool $enabled = null,
		public readonly string $orderby = 'priority',
		public readonly string $order = 'asc'
	) {}

	/**
	 * @param array<string, mixed> $params
	 */
	public static function from_request( array $params ): self {
		$page     = max( 1, (int) ( $params['page'] ?? 1 ) );
		$per_page = max( 1, min( 100, (int) ( $params['per_page'] ?? 20 ) ) );

		$enabled = null;
		if ( isset( $params['enabled'] ) && '' !== (string) $params['enabled'] ) {
			$enabled = rest_sanitize_boolean( $params['enabled'] );
		}

		$orderby = sanitize_key( (string) ( $params['orderby'] ?? 'priority' ) );
		$allowed_orderby = array( 'priority', 'id', 'asset_handle', 'action_type', 'created_at', 'label' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'priority';
		}

		$order = strtolower( (string) ( $params['order'] ?? 'asc' ) );
		if ( ! in_array( $order, array( 'asc', 'desc' ), true ) ) {
			$order = 'asc';
		}

		return new self(
			$page,
			$per_page,
			sanitize_text_field( (string) ( $params['search'] ?? '' ) ),
			sanitize_text_field( (string) ( $params['asset_handle'] ?? '' ) ),
			sanitize_key( (string) ( $params['action_type'] ?? '' ) ),
			sanitize_key( (string) ( $params['asset_type'] ?? '' ) ),
			sanitize_key( (string) ( $params['condition_type'] ?? '' ) ),
			$enabled,
			$orderby,
			$order
		);
	}
}
