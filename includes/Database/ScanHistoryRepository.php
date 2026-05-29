<?php
/**
 * Scan history persistence.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Database;

defined( 'ABSPATH' ) || exit;
/**
 * CRUD for persisted asset scans.
 */
final class ScanHistoryRepository {

	public const MAX_ROWS = 200;

	public const RETENTION_DAYS = 90;

	public static function max_rows(): int {
		return max( 10, (int) apply_filters( 'assetpilot_scan_history_max_rows', self::MAX_ROWS ) );
	}

	public static function retention_days(): int {
		return max( 1, (int) apply_filters( 'assetpilot_scan_history_retention_days', self::RETENTION_DAYS ) );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function create( array $data ): ?array {
		global $wpdb;

		$table = Schema::scan_history_table();

		$insert = array(
			'scan_url'       => (string) ( $data['scan_url'] ?? '' ),
			'assets_json'    => wp_json_encode( $data['assets'] ?? array() ),
			'asset_count'    => (int) ( $data['asset_count'] ?? 0 ),
			'script_count'   => (int) ( $data['script_count'] ?? 0 ),
			'style_count'    => (int) ( $data['style_count'] ?? 0 ),
			'total_js_size'  => (int) ( $data['total_js_size'] ?? 0 ),
			'total_css_size' => (int) ( $data['total_css_size'] ?? 0 ),
			'source'         => sanitize_key( (string) ( $data['source'] ?? '' ) ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$table,
			$insert,
			array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%s' )
		);

		if ( false === $result ) {
			return null;
		}

		$this->rotate();

		return $this->find( (int) $wpdb->insert_id, true );
	}

	public function find( int $id, bool $include_assets = false ): ?array {
		global $wpdb;

		$table = Schema::scan_history_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = TableQuery::get_row( $table, 'SELECT * FROM %i WHERE id = %d', array( $id ), ARRAY_A );

		if ( ! is_array( $row ) ) {
			return null;
		}

		return $this->format_row( $row, $include_assets );
	}

	/**
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function list( int $page = 1, int $per_page = 20, string $search = '' ): array {
		$this->maybe_rotate_scheduled();

		global $wpdb;

		$table   = Schema::scan_history_table();
		$page    = max( 1, $page );
		$per_page = min( 100, max( 1, $per_page ) );
		$offset  = ( $page - 1 ) * $per_page;

		$where = '1=1';
		$args  = array();

		if ( '' !== $search ) {
			$where  .= ' AND scan_url LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $search ) . '%';
		}

		$count_sql = "SELECT COUNT(*) FROM %i WHERE {$where}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) TableQuery::get_var( $table, $count_sql, $args );

		$list_sql  = "SELECT id, scan_url, scanned_at, asset_count, script_count, style_count, total_js_size, total_css_size, source FROM %i WHERE {$where} ORDER BY scanned_at DESC LIMIT %d OFFSET %d";
		$list_args = array_merge( $args, array( $per_page, $offset ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = TableQuery::get_results( $table, $list_sql, $list_args, ARRAY_A );

		$items = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$items[] = $this->format_row( $row, false );
			}
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	public function delete( int $id ): bool {
		global $wpdb;

		$table = Schema::scan_history_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		return (bool) $result;
	}

	public function count(): int {
		$this->maybe_rotate_scheduled();

		global $wpdb;

		$table = Schema::scan_history_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) TableQuery::get_var( $table, 'SELECT COUNT(*) FROM %i' );
	}

	/**
	 * Drop scans older than retention and trim excess rows (newest kept).
	 */
	public function rotate(): void {
		global $wpdb;

		$table  = Schema::scan_history_table();
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::retention_days() * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		TableQuery::query( $table, 'DELETE FROM %i WHERE scanned_at < %s', array( $cutoff ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) TableQuery::get_var( $table, 'SELECT COUNT(*) FROM %i' );
		$max   = self::max_rows();

		if ( $count <= $max ) {
			return;
		}

		$trim = $count - (int) ( $max * 0.9 );
		if ( $trim <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		TableQuery::query( $table, 'DELETE FROM %i ORDER BY id ASC LIMIT %d', array( $trim ) );
	}

	/**
	 * Run rotation periodically so existing sites prune without waiting for a new scan.
	 */
	public function maybe_rotate_scheduled(): void {
		if ( get_transient( 'assetpilot_scan_history_rotation' ) ) {
			return;
		}

		$this->rotate();
		set_transient( 'assetpilot_scan_history_rotation', 1, 12 * HOUR_IN_SECONDS );
	}

	/**
	 * @return array<int, string>
	 */
	public function get_recent_urls( int $limit = 50 ): array {
		global $wpdb;

		$table = Schema::scan_history_table();
		$limit = max( 1, min( 100, $limit ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = TableQuery::get_col( $table, 'SELECT scan_url FROM %i ORDER BY scanned_at DESC LIMIT %d', array( $limit ) );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_values( array_unique( array_map( 'strval', $rows ) ) );
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function format_row( array $row, bool $include_assets ): array {
		$formatted = array(
			'id'             => (int) ( $row['id'] ?? 0 ),
			'scan_url'       => (string) ( $row['scan_url'] ?? '' ),
			'scanned_at'     => (string) ( $row['scanned_at'] ?? '' ),
			'asset_count'    => (int) ( $row['asset_count'] ?? 0 ),
			'script_count'   => (int) ( $row['script_count'] ?? 0 ),
			'style_count'    => (int) ( $row['style_count'] ?? 0 ),
			'total_js_size'  => (int) ( $row['total_js_size'] ?? 0 ),
			'total_css_size' => (int) ( $row['total_css_size'] ?? 0 ),
			'total_size'     => (int) ( $row['total_js_size'] ?? 0 ) + (int) ( $row['total_css_size'] ?? 0 ),
			'source'         => (string) ( $row['source'] ?? '' ),
		);

		if ( $include_assets && ! empty( $row['assets_json'] ) ) {
			$assets = json_decode( (string) $row['assets_json'], true );
			$formatted['assets'] = is_array( $assets ) ? $assets : array();
		}

		return $formatted;
	}
}
