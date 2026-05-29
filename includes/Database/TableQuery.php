<?php
/**
 * Prepared SQL helpers for plugin-owned tables.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps $wpdb->prepare() for plugin-owned table names.
 */
final class TableQuery {

	/**
	 * @param array<int, mixed> $values Placeholder values after the table name.
	 */
	public static function prepare( string $table, string $sql, array $values = [] ): string {
		global $wpdb;

		$table = self::validate_table( $table );
		if ( '' === $table ) {
			return '';
		}

		if ( empty( $values ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is caller-built with placeholders; table is allow-listed.
			return $wpdb->prepare( $sql, $table );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is caller-built with placeholders; table is allow-listed.
		return $wpdb->prepare( $sql, $table, ...$values );
	}

	/**
	 * @param array<int, mixed> $values
	 */
	public static function get_var( string $table, string $sql, array $values = [] ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table allow-listed in validate_table().
		return $wpdb->get_var( self::prepare( $table, $sql, $values ) );
	}

	/**
	 * @param array<int, mixed> $values
	 * @return array<int, object>|array<int, array<string, mixed>>|null
	 */
	public static function get_results( string $table, string $sql, array $values = [], string $output = OBJECT ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $wpdb->get_results( self::prepare( $table, $sql, $values ), $output );
	}

	/**
	 * @param array<int, mixed> $values
	 * @return array<string, mixed>|object|null
	 */
	public static function get_row( string $table, string $sql, array $values = [], string $output = OBJECT, int $y = 0 ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $wpdb->get_row( self::prepare( $table, $sql, $values ), $output, $y );
	}

	/**
	 * @param array<int, mixed> $values
	 * @return int|false
	 */
	public static function query( string $table, string $sql, array $values = [] ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		return $wpdb->query( self::prepare( $table, $sql, $values ) );
	}

	/**
	 * @param array<int, mixed> $values
	 * @return array<int, string>|null
	 */
	public static function get_col( string $table, string $sql, array $values = [] ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_col( self::prepare( $table, $sql, $values ) );

		return is_array( $rows ) ? $rows : null;
	}

	/**
	 * Drops a plugin table on uninstall (allow-listed names only).
	 */
	public static function drop_table( string $table ): void {
		global $wpdb;

		$table = self::validate_table( $table );
		if ( '' === $table ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name validated against fixed allow-list.
		$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
	}

	/**
	 * @return string Empty string when not a known plugin table.
	 */
	private static function validate_table( string $table ): string {
		global $wpdb;

		$allowed = array(
			$wpdb->prefix . 'assetpilot_rules',
			$wpdb->prefix . 'assetpilot_scan_history',
			$wpdb->prefix . 'assetpilot_logs',
		);

		return in_array( $table, $allowed, true ) ? $table : '';
	}
}
