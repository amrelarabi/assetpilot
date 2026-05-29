<?php
/**
 * Query parameters for debug logs list.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Database;

defined( 'ABSPATH' ) || exit;
/**
 * Filters for log repository queries.
 */
final class LogsListQuery {

	public function __construct(
		public int $page = 1,
		public int $per_page = 50,
		public string $severity = '',
		public string $type = '',
		public int $rule_id = 0,
		public string $asset_handle = '',
		public string $search = '',
		public string $date_from = '',
		public string $date_to = ''
	) {}

	/**
	 * @param array<string, mixed> $params
	 */
	public static function from_request( array $params ): self {
		return new self(
			max( 1, (int) ( $params['page'] ?? 1 ) ),
			max( 1, min( 200, (int) ( $params['per_page'] ?? 50 ) ) ),
			sanitize_key( (string) ( $params['severity'] ?? '' ) ),
			sanitize_key( (string) ( $params['type'] ?? '' ) ),
			max( 0, (int) ( $params['rule_id'] ?? 0 ) ),
			sanitize_text_field( (string) ( $params['asset_handle'] ?? '' ) ),
			sanitize_text_field( (string) ( $params['search'] ?? '' ) ),
			sanitize_text_field( (string) ( $params['date_from'] ?? '' ) ),
			sanitize_text_field( (string) ( $params['date_to'] ?? '' ) )
		);
	}
}
