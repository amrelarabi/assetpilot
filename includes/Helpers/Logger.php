<?php
/**
 * Debug logging.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Helpers;

defined( 'ABSPATH' ) || exit;
use AssetControl\Database\LogRepository;

/**
 * Optional debug logger with DB persistence and rotation.
 */
final class Logger {

	private const OPTION = 'assetpilot_settings';

	private static bool $shutdown_registered = false;

	/**
	 * @var array<int, array<string, mixed>>
	 */
	private static array $buffer = array();

	/**
	 * @param array<string, mixed> $context
	 */
	public static function log( string $type, string $message, array $context = array() ): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$entry = array(
			'type'          => sanitize_key( $type ),
			'severity'      => self::severity_for_type( $type, $context ),
			'message'       => $message,
			'context'         => $context,
			'timestamp'       => gmdate( 'c' ),
			'rule_id'         => (int) ( $context['rule_id'] ?? 0 ),
			'asset_handle'    => (string) ( $context['handle'] ?? $context['asset_handle'] ?? '' ),
		);

		self::$buffer[] = $entry;

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[AssetPilot] ' . wp_json_encode( $entry ) );
		}

		self::register_shutdown();
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private static function severity_for_type( string $type, array $context = array() ): string {
		if ( ! empty( $context['severity'] ) ) {
			$severity = sanitize_key( (string) $context['severity'] );
			if ( in_array( $severity, array( 'debug', 'info', 'warning', 'error' ), true ) ) {
				return $severity;
			}
			if ( 'danger' === $severity ) {
				return 'error';
			}
		}

		return match ( $type ) {
			'error' => 'error',
			'validation' => 'warning',
			'skipped' => 'debug',
			'verification' => 'warning',
			default => 'info',
		};
	}

	public static function is_enabled(): bool {
		$settings = get_option( self::OPTION, array() );
		return ! empty( $settings['debug_logging'] );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_buffer(): array {
		return self::$buffer;
	}

	public static function flush(): void {
		self::$buffer = array();
	}

	public static function persist_buffer(): void {
		if ( ! self::is_enabled() || empty( self::$buffer ) ) {
			return;
		}

		( new LogRepository() )->insert_many( self::$buffer );
		self::flush();
	}

	private static function register_shutdown(): void {
		if ( self::$shutdown_registered ) {
			return;
		}
		self::$shutdown_registered = true;
		add_action( 'shutdown', array( self::class, 'persist_buffer' ), 999 );
	}
}
