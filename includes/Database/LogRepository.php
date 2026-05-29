<?php
/**
 * Debug log persistence and rotation.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Database;

defined( 'ABSPATH' ) || exit;
/**
 * Stores plugin debug logs in a custom table with automatic rotation.
 */
final class LogRepository {

	public const MAX_ROWS = 5000;

	public const RETENTION_DAYS = 14;

	/**
	 * @param array<int, array<string, mixed>> $entries
	 */
	public function insert_many( array $entries ): int {
		if ( empty( $entries ) ) {
			return 0;
		}

		global $wpdb;

		$table   = Schema::logs_table();
		$inserted = 0;

		foreach ( $entries as $entry ) {
			$row = $this->normalize_entry( $entry );
			if ( null === $row ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->insert( $table, $row, $this->insert_formats() );
			if ( false !== $result ) {
				++$inserted;
			}
		}

		if ( $inserted > 0 ) {
			$this->rotate();
		}

		return $inserted;
	}

	/**
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function query( LogsListQuery $query ): array {
		global $wpdb;

		$table  = Schema::logs_table();
		$where  = array( '1=1' );
		$args   = array();

		if ( '' !== $query->severity ) {
			$where[] = 'severity = %s';
			$args[]  = $query->severity;
		}

		if ( '' !== $query->type ) {
			$where[] = 'type = %s';
			$args[]  = $query->type;
		}

		if ( $query->rule_id > 0 ) {
			$where[] = 'rule_id = %d';
			$args[]  = $query->rule_id;
		}

		if ( '' !== $query->asset_handle ) {
			$where[] = 'asset_handle = %s';
			$args[]  = $query->asset_handle;
		}

		if ( '' !== $query->date_from ) {
			$where[] = 'logged_at >= %s';
			$args[]  = $query->date_from . ' 00:00:00';
		}

		if ( '' !== $query->date_to ) {
			$where[] = 'logged_at <= %s';
			$args[]  = $query->date_to . ' 23:59:59';
		}

		if ( '' !== $query->search ) {
			$like    = '%' . $wpdb->esc_like( $query->search ) . '%';
			$where[] = '( message LIKE %s OR asset_handle LIKE %s OR context_json LIKE %s )';
			$args[]  = $like;
			$args[]  = $like;
			$args[]  = $like;
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM %i WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) TableQuery::get_var( $table, $count_sql, $args );

		$offset    = ( $query->page - 1 ) * $query->per_page;
		$list_sql  = "SELECT * FROM %i WHERE {$where_sql} ORDER BY logged_at DESC, id DESC LIMIT %d OFFSET %d";
		$list_args = array_merge( $args, array( $query->per_page, $offset ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = TableQuery::get_results( $table, $list_sql, $list_args, ARRAY_A );

		$items = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$items[] = $this->format_row( $row );
			}
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	public function count(): int {
		global $wpdb;
		$table = Schema::logs_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) TableQuery::get_var( $table, 'SELECT COUNT(*) FROM %i' );
	}

	public function clear_all(): int {
		global $wpdb;
		$table = Schema::logs_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = TableQuery::query( $table, 'TRUNCATE TABLE %i' );
		return false === $deleted ? 0 : (int) $deleted;
	}

	public function rotate(): void {
		global $wpdb;

		$table = Schema::logs_table();

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( self::RETENTION_DAYS * DAY_IN_SECONDS ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		TableQuery::query( $table, 'DELETE FROM %i WHERE logged_at < %s', array( $cutoff ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = (int) TableQuery::get_var( $table, 'SELECT COUNT(*) FROM %i' );
		if ( $count <= self::MAX_ROWS ) {
			return;
		}

		$trim = $count - (int) ( self::MAX_ROWS * 0.9 );
		if ( $trim <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		TableQuery::query( $table, 'DELETE FROM %i ORDER BY id ASC LIMIT %d', array( $trim ) );
	}

	/**
	 * @return array<int, string>
	 */
	public function distinct_types(): array {
		global $wpdb;
		$table = Schema::logs_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = TableQuery::get_col( $table, 'SELECT DISTINCT type FROM %i ORDER BY type ASC' );
		return is_array( $rows ) ? array_values( array_filter( array_map( 'strval', $rows ) ) ) : array();
	}

	/**
	 * @param array<string, mixed> $entry
	 * @return array<string, mixed>|null
	 */
	private function normalize_entry( array $entry ): ?array {
		$message = trim( (string) ( $entry['message'] ?? '' ) );
		if ( '' === $message ) {
			return null;
		}

		$type     = sanitize_key( (string) ( $entry['type'] ?? 'info' ) );
		$severity = sanitize_key( (string) ( $entry['severity'] ?? 'info' ) );
		$context  = is_array( $entry['context'] ?? null ) ? $entry['context'] : array();

		$rule_id = (int) ( $entry['rule_id'] ?? $context['rule_id'] ?? 0 );
		$handle  = sanitize_text_field( (string) ( $entry['asset_handle'] ?? $context['handle'] ?? $context['asset_handle'] ?? '' ) );

		unset( $context['rule_id'], $context['handle'], $context['asset_handle'] );

		$request_uri = '';
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) );
			if ( strlen( $request_uri ) > 2048 ) {
				$request_uri = substr( $request_uri, 0, 2048 );
			}
		}

		$logged_at = (string) ( $entry['timestamp'] ?? '' );
		if ( '' === $logged_at ) {
			$logged_at = gmdate( 'Y-m-d H:i:s' );
		} else {
			$logged_at = gmdate( 'Y-m-d H:i:s', strtotime( $logged_at ) ?: time() );
		}

		return array(
			'logged_at'     => $logged_at,
			'severity'      => $severity,
			'type'          => $type,
			'message'       => $message,
			'rule_id'       => max( 0, $rule_id ),
			'asset_handle'  => $handle,
			'context_json'  => empty( $context ) ? '' : (string) wp_json_encode( $context ),
			'request_uri'   => $request_uri,
		);
	}

	/**
	 * @return array<string, string|int|null>
	 */
	private function insert_formats(): array {
		return array(
			'logged_at'    => '%s',
			'severity'     => '%s',
			'type'         => '%s',
			'message'      => '%s',
			'rule_id'      => '%d',
			'asset_handle' => '%s',
			'context_json' => '%s',
			'request_uri'  => '%s',
		);
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function format_row( array $row ): array {
		$row['id']           = (int) $row['id'];
		$row['rule_id']      = isset( $row['rule_id'] ) && (int) $row['rule_id'] > 0 ? (int) $row['rule_id'] : null;
		$row['asset_handle'] = (string) ( $row['asset_handle'] ?? '' );
		$row['context']      = ! empty( $row['context_json'] )
			? ( json_decode( (string) $row['context_json'], true ) ?: array() )
			: array();
		unset( $row['context_json'] );
		return $row;
	}
}
