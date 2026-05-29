<?php
/**
 * Uninstall AssetPilot.
 *
 * @package AssetControl
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/Database/TableQuery.php';

global $wpdb;

$assetpilot_tables = array(
	$wpdb->prefix . 'assetpilot_rules',
	$wpdb->prefix . 'assetpilot_scan_history',
	$wpdb->prefix . 'assetpilot_logs',
);

foreach ( $assetpilot_tables as $assetpilot_table ) {
	\AssetControl\Database\TableQuery::drop_table( $assetpilot_table );
}

delete_option( 'assetpilot_db_version' );
delete_option( 'assetpilot_settings' );
delete_transient( 'assetpilot_rules_cache' );
delete_transient( 'assetpilot_rule_eval_cache' );
