<?php
/**
 * Detects repeated frontend fatals and auto-suspends runtime rules.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Core;

defined( 'ABSPATH' ) || exit;
/**
 * Monitors public frontend requests while runtime modifications are active.
 */
final class RuntimeHealthMonitor {

	private const OPTION_HEALTH = 'assetpilot_runtime_health';

	private static bool $tracking = false;

	public static function init(): void {
		if ( ! self::should_track() || SafeModeManager::is_runtime_disabled() ) {
			return;
		}

		self::$tracking = true;
		register_shutdown_function( array( self::class, 'on_shutdown' ) );
	}

	public static function should_track(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( defined( 'ASSETPILOT_ASSET_SCAN' ) && ASSETPILOT_ASSET_SCAN ) {
			return false;
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return false;
		}

		return true;
	}

	public static function on_shutdown(): void {
		if ( ! self::$tracking ) {
			return;
		}

		$error = error_get_last();
		if ( null !== $error && self::is_fatal_error( $error ) ) {
			self::record_failure();
			return;
		}

		self::record_success();
	}

	/**
	 * @param array{type: int, message: string, file: string, line: int} $error
	 */
	private static function is_fatal_error( array $error ): bool {
		$fatals = array(
			E_ERROR,
			E_PARSE,
			E_CORE_ERROR,
			E_COMPILE_ERROR,
			E_USER_ERROR,
		);

		return in_array( $error['type'], $fatals, true );
	}

	public static function record_failure(): void {
		$window    = (int) apply_filters( 'assetpilot_runtime_failure_window_seconds', 5 * MINUTE_IN_SECONDS );
		$threshold = (int) apply_filters( 'assetpilot_runtime_failure_threshold', 3 );
		$threshold = max( 1, $threshold );

		$data     = self::get_health_data();
		$now      = time();
		$failures = array_values(
			array_filter(
				(array) ( $data['failures'] ?? array() ),
				static fn( $ts ): bool => is_int( $ts ) && $ts > ( $now - $window )
			)
		);
		$failures[] = $now;

		$data['failures']     = $failures;
		$data['last_failure'] = $now;
		update_option( self::OPTION_HEALTH, $data, false );

		if ( count( $failures ) >= $threshold ) {
			SafeModeManager::set_auto_suspend( count( $failures ) );
		}
	}

	public static function record_success(): void {
		$data = self::get_health_data();
		if ( empty( $data['failures'] ) ) {
			return;
		}

		$data['failures']      = array();
		$data['last_success']  = time();
		update_option( self::OPTION_HEALTH, $data, false );
	}

	public static function reset_failures(): void {
		delete_option( self::OPTION_HEALTH );
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get_health_data(): array {
		$data = get_option( self::OPTION_HEALTH, array() );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * @return array{failures: int, last_failure: int|null, last_success: int|null}
	 */
	public static function get_status(): array {
		$data     = self::get_health_data();
		$failures = (array) ( $data['failures'] ?? array() );

		return array(
			'failures'      => count( $failures ),
			'last_failure'  => isset( $data['last_failure'] ) ? (int) $data['last_failure'] : null,
			'last_success'  => isset( $data['last_success'] ) ? (int) $data['last_success'] : null,
		);
	}
}
