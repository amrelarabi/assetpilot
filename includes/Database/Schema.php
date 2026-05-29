<?php
/**
 * Database schema.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Database;

defined( 'ABSPATH' ) || exit;
/**
 * Creates and migrates custom tables.
 */
final class Schema {

	public const DB_VERSION = '1.4.1';

	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'assetpilot_rules';
	}

	public static function scan_history_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'assetpilot_scan_history';
	}

	public static function logs_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'assetpilot_logs';
	}

	public static function create_tables(): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		self::create_rules_table();
		self::create_scan_history_table();
		self::create_logs_table();

		update_option( 'assetpilot_db_version', self::DB_VERSION );
	}

	public static function maybe_upgrade(): void {
		$installed = get_option( 'assetpilot_db_version', '' );
		if ( self::DB_VERSION !== $installed ) {
			self::create_tables();
			( new ScanHistoryRepository() )->rotate();
		}
	}

	private static function create_rules_table(): void {
		global $wpdb;

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			asset_handle varchar(255) NOT NULL DEFAULT '',
			asset_type varchar(50) NOT NULL DEFAULT 'script',
			action_type varchar(50) NOT NULL DEFAULT 'disable',
			condition_group longtext NOT NULL,
			action_config longtext DEFAULT NULL,
			priority int(11) NOT NULL DEFAULT 10,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			verification_result longtext DEFAULT NULL,
			label varchar(255) NOT NULL DEFAULT '',
			notes text DEFAULT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY asset_handle (asset_handle),
			KEY enabled (enabled),
			KEY priority (priority),
			KEY action_type (action_type),
			KEY label (label(191))
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private static function create_scan_history_table(): void {
		global $wpdb;

		$table           = self::scan_history_table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			scan_url varchar(2048) NOT NULL DEFAULT '',
			scanned_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			assets_json longtext NOT NULL,
			asset_count int(11) NOT NULL DEFAULT 0,
			script_count int(11) NOT NULL DEFAULT 0,
			style_count int(11) NOT NULL DEFAULT 0,
			total_js_size bigint(20) NOT NULL DEFAULT 0,
			total_css_size bigint(20) NOT NULL DEFAULT 0,
			source varchar(50) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY scanned_at (scanned_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	private static function create_logs_table(): void {
		global $wpdb;

		$table           = self::logs_table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			logged_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			severity varchar(20) NOT NULL DEFAULT 'info',
			type varchar(50) NOT NULL DEFAULT 'info',
			message text NOT NULL,
			rule_id bigint(20) unsigned NOT NULL DEFAULT 0,
			asset_handle varchar(255) NOT NULL DEFAULT '',
			context_json longtext DEFAULT NULL,
			request_uri varchar(2048) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY logged_at (logged_at),
			KEY severity (severity),
			KEY type (type),
			KEY rule_id (rule_id),
			KEY asset_handle (asset_handle(191))
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
