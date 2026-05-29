<?php
/**
 * Rules data access layer.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Database;

defined( 'ABSPATH' ) || exit;
use AssetControl\Helpers\Cache;

/**
 * CRUD for asset control rules.
 */
final class RulesRepository {

	/**
	 * @return array{items: array<int, array<string, mixed>>, total: int}
	 */
	public function query( RulesListQuery $query ): array {
		global $wpdb;

		$table  = Schema::table_name();
		$where  = array( '1=1' );
		$args   = array();

		if ( null !== $query->enabled ) {
			$where[] = 'enabled = %d';
			$args[]  = $query->enabled ? 1 : 0;
		}

		if ( '' !== $query->asset_handle ) {
			$like    = '%' . $wpdb->esc_like( $query->asset_handle ) . '%';
			$where[] = 'asset_handle LIKE %s';
			$args[]  = $like;
		}

		if ( '' !== $query->action_type ) {
			$where[] = 'action_type = %s';
			$args[]  = $query->action_type;
		}

		if ( '' !== $query->asset_type ) {
			$where[] = 'asset_type = %s';
			$args[]  = $query->asset_type;
		}

		if ( '' !== $query->search ) {
			$like    = '%' . $wpdb->esc_like( $query->search ) . '%';
			$where[] = '( label LIKE %s OR notes LIKE %s OR asset_handle LIKE %s OR action_type LIKE %s )';
			$args[]  = $like;
			$args[]  = $like;
			$args[]  = $like;
			$args[]  = $like;
		}

		$condition_sql = $this->condition_type_where( $query->condition_type );
		if ( '' !== $condition_sql ) {
			$where[] = $condition_sql;
		}

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM %i WHERE {$where_sql}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) TableQuery::get_var( $table, $count_sql, $args );

		$orderby = $this->sanitize_orderby( $query->orderby );
		$order   = 'desc' === strtolower( $query->order ) ? 'DESC' : 'ASC';
		$offset  = ( $query->page - 1 ) * $query->per_page;

		$list_sql  = "SELECT * FROM %i WHERE {$where_sql} ORDER BY {$orderby} {$order}, id ASC LIMIT %d OFFSET %d";
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

	/**
	 * @param array<int, int> $ids
	 * @return array{updated: int, deleted: int}
	 */
	public function bulk_set_enabled( array $ids, bool $enabled ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		$table   = Schema::table_name();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql    = "UPDATE %i SET enabled = %d WHERE id IN ({$placeholders})";
		$params = array_merge( array( $enabled ? 1 : 0 ), $ids );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = TableQuery::query( $table, $sql, $params );

		if ( false !== $updated ) {
			Cache::invalidate_rules();
		}

		return false === $updated ? 0 : (int) $updated;
	}

	/**
	 * @param array<int, int> $ids
	 */
	public function bulk_delete( array $ids ): int {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( empty( $ids ) ) {
			return 0;
		}

		$table        = Schema::table_name();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql = "DELETE FROM %i WHERE id IN ({$placeholders})";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$deleted = TableQuery::query( $table, $sql, $ids );

		if ( $deleted ) {
			Cache::invalidate_rules();
		}

		return false === $deleted ? 0 : (int) $deleted;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function all( bool $enabled_only = false ): array {
		global $wpdb;

		$table = Schema::table_name();
		$sql   = $enabled_only
			? 'SELECT * FROM %i WHERE enabled = 1 ORDER BY priority ASC, id ASC'
			: 'SELECT * FROM %i ORDER BY priority ASC, id ASC';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = TableQuery::get_results( $table, $sql, array(), ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( $this, 'format_row' ), $rows );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		$table = Schema::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = TableQuery::get_row( $table, 'SELECT * FROM %i WHERE id = %d', array( $id ), ARRAY_A );

		if ( ! is_array( $row ) ) {
			return null;
		}

		return $this->format_row( $row );
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>|null
	 */
	public function create( array $data ): ?array {
		global $wpdb;

		$table   = Schema::table_name();
		$insert  = $this->prepare_write_data( $data );
		$formats = $this->write_formats( $insert );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table, $insert, $formats );

		if ( false === $result ) {
			return null;
		}

		Cache::invalidate_rules();
		return $this->find( (int) $wpdb->insert_id );
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>|null
	 */
	public function update( int $id, array $data ): ?array {
		global $wpdb;

		$table   = Schema::table_name();
		$update  = $this->prepare_write_data( $data, false );
		$formats = $this->write_formats( $update );

		if ( empty( $update ) ) {
			return $this->find( $id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update( $table, $update, array( 'id' => $id ), $formats, array( '%d' ) );

		if ( false === $result ) {
			return null;
		}

		Cache::invalidate_rules();
		return $this->find( $id );
	}

	/**
	 * @param array<string, mixed> $verification
	 */
	public function update_verification( int $id, array $verification ): ?array {
		global $wpdb;

		$table = Schema::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table,
			array( 'verification_result' => wp_json_encode( $verification ) ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( false === $result ) {
			return null;
		}

		Cache::invalidate_rules();
		return $this->find( $id );
	}

	public function delete( int $id ): bool {
		global $wpdb;

		$table = Schema::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );

		if ( $result ) {
			Cache::invalidate_rules();
		}

		return (bool) $result;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_enabled_cached(): array {
		return Cache::get_rules(
			function (): array {
				return $this->all( true );
			}
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function all_cached(): array {
		return Cache::get_rules_all(
			function (): array {
				return $this->all( false );
			}
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function find_for_asset( string $handle, string $type, bool $enabled_only = false ): array {
		$key = 'assetpilot_rules_for_' . $type . ':' . $handle . ( $enabled_only ? ':enabled' : ':all' );

		return Cache::request(
			$key,
			function () use ( $handle, $type, $enabled_only ): array {
				$source = $enabled_only ? $this->get_enabled_cached() : $this->all_cached();
				$rules  = array();

				foreach ( $source as $rule ) {
					if ( (string) ( $rule['asset_handle'] ?? '' ) !== $handle ) {
						continue;
					}
					if ( (string) ( $rule['asset_type'] ?? '' ) !== $type ) {
						continue;
					}
					$rules[] = $rule;
				}

				return $rules;
			}
		);
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function format_row( array $row ): array {
		$row['id']              = (int) $row['id'];
		$row['priority']        = (int) $row['priority'];
		$row['enabled']         = (bool) (int) $row['enabled'];
		$row['condition_group'] = json_decode( $row['condition_group'] ?? '{}', true ) ?: array();
		$row['action_config']   = ! empty( $row['action_config'] )
			? ( json_decode( $row['action_config'], true ) ?: array() )
			: array();
		$row['verification']    = ! empty( $row['verification_result'] )
			? ( json_decode( $row['verification_result'], true ) ?: null )
			: null;
		unset( $row['verification_result'] );
		$row['label']             = (string) ( $row['label'] ?? '' );
		$row['notes']             = (string) ( $row['notes'] ?? '' );
		$row['condition_scope']   = $this->detect_condition_scope( $row['condition_group'] );
		return $row;
	}

	private function detect_condition_scope( array $conditions ): string {
		if ( ! empty( $conditions['global'] ) || ( isset( $conditions['scope'] ) && 'global' === $conditions['scope'] ) ) {
			return 'global';
		}
		if ( ! empty( $conditions['scan_page_url'] ) ) {
			return 'url';
		}
		if ( ! empty( $conditions['url_contains'] ) || ! empty( $conditions['url_path'] ) ) {
			return 'url';
		}
		if ( ! empty( $conditions['query_contains'] ) ) {
			return 'query';
		}
		if ( ! empty( $conditions['user_roles'] ) ) {
			return 'role';
		}
		if ( ! empty( $conditions['woocommerce'] ) ) {
			return 'woocommerce';
		}
		if ( ! empty( $conditions['archive'] ) ) {
			return 'archive';
		}
		if ( ! empty( $conditions['post_type'] ) ) {
			return 'post_type_archive';
		}
		if ( ! empty( $conditions['include_ids'] ) || ! empty( $conditions['post_ids'] ) || ! empty( $conditions['singular_type'] ) ) {
			return 'singular';
		}
		if ( ! empty( $conditions['device'] ) ) {
			return 'device';
		}
		if ( isset( $conditions['logged_in'] ) ) {
			return 'auth';
		}
		return 'conditional';
	}

	private function condition_type_where( string $condition_type ): string {
		if ( '' === $condition_type ) {
			return '';
		}

		return match ( $condition_type ) {
			'global' => "( condition_group LIKE '%\"global\":true%' OR condition_group LIKE '%\"scope\":\"global\"%' )",
			'url' => "( condition_group LIKE '%url_contains%' OR condition_group LIKE '%url_path%' )",
			'scan_page' => "condition_group LIKE '%scan_page_url%'",
			'query' => "condition_group LIKE '%query_contains%'",
			'role' => "condition_group LIKE '%user_roles%'",
			'device' => "condition_group LIKE '%\"device\"%'",
			'auth' => "condition_group LIKE '%logged_in%'",
			'singular' => "( condition_group LIKE '%singular_type%' OR condition_group LIKE '%include_ids%' OR condition_group LIKE '%post_ids%' )",
			'archive' => "condition_group LIKE '%\"archive\"%'",
			'woocommerce' => "condition_group LIKE '%woocommerce%'",
			'post_type_archive' => "condition_group LIKE '%\"post_type\"%'",
			default => '',
		};
	}

	private function sanitize_orderby( string $orderby ): string {
		$allowed = array(
			'priority'      => 'priority',
			'id'            => 'id',
			'asset_handle'  => 'asset_handle',
			'action_type'   => 'action_type',
			'created_at'    => 'created_at',
			'label'         => 'label',
		);
		return $allowed[ $orderby ] ?? 'priority';
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function prepare_write_data( array $data, bool $is_create = true ): array {
		$out = array();

		if ( isset( $data['asset_handle'] ) ) {
			$out['asset_handle'] = sanitize_text_field( (string) $data['asset_handle'] );
		}
		if ( isset( $data['asset_type'] ) ) {
			$out['asset_type'] = sanitize_key( (string) $data['asset_type'] );
		}
		if ( isset( $data['action_type'] ) ) {
			$out['action_type'] = sanitize_key( (string) $data['action_type'] );
		}
		if ( isset( $data['condition_group'] ) ) {
			$out['condition_group'] = wp_json_encode( is_array( $data['condition_group'] ) ? $data['condition_group'] : array() );
		}
		if ( array_key_exists( 'action_config', $data ) ) {
			$config = is_array( $data['action_config'] ) ? $data['action_config'] : array();
			$out['action_config'] = wp_json_encode( $config );
		}
		if ( isset( $data['priority'] ) ) {
			$out['priority'] = (int) $data['priority'];
		}
		if ( isset( $data['enabled'] ) ) {
			$out['enabled'] = $data['enabled'] ? 1 : 0;
		}
		if ( array_key_exists( 'label', $data ) ) {
			$out['label'] = sanitize_text_field( (string) $data['label'] );
		}
		if ( array_key_exists( 'notes', $data ) ) {
			$out['notes'] = sanitize_textarea_field( (string) $data['notes'] );
		}

		if ( $is_create && ! isset( $out['condition_group'] ) ) {
			$out['condition_group'] = '{}';
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string>
	 */
	private function write_formats( array $data ): array {
		$map = array(
			'asset_handle'    => '%s',
			'asset_type'      => '%s',
			'action_type'     => '%s',
			'condition_group' => '%s',
			'action_config'   => '%s',
			'priority'        => '%d',
			'enabled'         => '%d',
			'label'           => '%s',
			'notes'           => '%s',
		);

		$formats = array();
		foreach ( array_keys( $data ) as $key ) {
			if ( isset( $map[ $key ] ) ) {
				$formats[] = $map[ $key ];
			}
		}
		return $formats;
	}
}
