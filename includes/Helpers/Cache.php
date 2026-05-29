<?php
/**
 * Caching helpers.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Helpers;

defined( 'ABSPATH' ) || exit;
/**
 * Transient cache, version busting, and per-request memoization.
 */
final class Cache {

	private const RULES_ENABLED_KEY = 'assetpilot_rules_cache';

	private const RULES_ALL_KEY = 'assetpilot_rules_all';

	private const VERSION_OPTION = 'assetpilot_cache_version';

	/** @var array<string, mixed> */
	private static array $request = array();

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_rules( callable $callback ): array {
		$cached = get_transient( self::RULES_ENABLED_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$rules = $callback();
		if ( is_array( $rules ) ) {
			set_transient( self::RULES_ENABLED_KEY, $rules, HOUR_IN_SECONDS );
		}

		return is_array( $rules ) ? $rules : array();
	}

	/**
	 * All rules (enabled + disabled) for admin / indexing.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_rules_all( callable $callback ): array {
		return self::remember( self::RULES_ALL_KEY, HOUR_IN_SECONDS, $callback );
	}

	/**
	 * @template T
	 * @param callable(): T $callback
	 * @return T
	 */
	public static function remember( string $key, int $ttl, callable $callback ) {
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			return $cached;
		}

		$value = $callback();
		set_transient( $key, $value, $ttl );
		return $value;
	}

	/**
	 * Per-request memoization (cleared when rules cache is invalidated).
	 *
	 * @template T
	 * @param callable(): T $callback
	 * @return T
	 */
	public static function request( string $key, callable $callback ) {
		if ( array_key_exists( $key, self::$request ) ) {
			return self::$request[ $key ];
		}

		self::$request[ $key ] = $callback();
		return self::$request[ $key ];
	}

	public static function invalidate_rules(): void {
		delete_transient( self::RULES_ENABLED_KEY );
		delete_transient( self::RULES_ALL_KEY );
		delete_transient( 'assetpilot_rule_eval_cache' );
		self::bump_version();
		self::$request = array();
	}

	public static function bump_version(): void {
		$version = (int) get_option( self::VERSION_OPTION, 1 );
		update_option( self::VERSION_OPTION, $version + 1, false );
	}

	public static function version(): int {
		return (int) get_option( self::VERSION_OPTION, 1 );
	}

	public static function versioned_key( string $base ): string {
		return $base . '_v' . self::version();
	}

	public static function graph_key( string $scan_url, string $asset_type, string $focus_handle, string $focus_type ): string {
		return self::versioned_key(
			'assetpilot_graph_' . md5( $scan_url . '|' . $asset_type . '|' . $focus_handle . '|' . $focus_type )
		);
	}

	public static function dependency_analysis_key( string $handle, string $type, string $action ): string {
		return self::versioned_key(
			'assetpilot_dep_' . md5( $handle . '|' . $type . '|' . $action )
		);
	}

	public static function recommendations_key( string $scan_url, int $scan_id ): string {
		return self::versioned_key(
			'assetpilot_reco_' . md5( $scan_url . '|' . (string) $scan_id )
		);
	}

	/**
	 * @param array<string, mixed> $conditions
	 */
	public static function condition_match_key( array $conditions, string $request_fingerprint ): string {
		return 'assetpilot_cond_' . md5( ( wp_json_encode( $conditions ) ?: '' ) . '|' . $request_fingerprint );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public static function get_eval_key( array $context ): string {
		return 'assetpilot_eval_' . md5( wp_json_encode( $context ) ?: '' );
	}

	public static function scan_ttl(): int {
		return (int) apply_filters( 'assetpilot_scan_cache_ttl', 15 * MINUTE_IN_SECONDS );
	}

	public static function graph_ttl(): int {
		return (int) apply_filters( 'assetpilot_dependency_graph_cache_ttl', 10 * MINUTE_IN_SECONDS );
	}

	public static function dependency_ttl(): int {
		return (int) apply_filters( 'assetpilot_dependency_analysis_cache_ttl', 5 * MINUTE_IN_SECONDS );
	}

	public static function recommendations_ttl(): int {
		return (int) apply_filters( 'assetpilot_recommendations_cache_ttl', 5 * MINUTE_IN_SECONDS );
	}
}
