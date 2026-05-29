<?php
/**
 * Tracks which scanned URLs reference each asset (pre–scan-history table).
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets;

defined( 'ABSPATH' ) || exit;
/**
 * Lightweight per-asset URL index (transient); full scans live in scan history table.
 */
final class AssetUsageService {

	private const TRANSIENT_KEY = 'assetpilot_asset_usage_index';
	private const MAX_URLS_PER_ASSET = 10;
	private const TTL              = WEEK_IN_SECONDS;

	/**
	 * Record assets from a completed scan.
	 *
	 * @param string                             $scan_url
	 * @param array<int, array<string, mixed>> $assets
	 */
	public function record_from_scan( string $scan_url, array $assets ): void {
		if ( '' === $scan_url || empty( $assets ) ) {
			return;
		}

		$index = get_transient( self::TRANSIENT_KEY );
		if ( ! is_array( $index ) ) {
			$index = array();
		}

		$entry = array(
			'url'  => $scan_url,
			'time' => time(),
		);

		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}
			$handle = (string) ( $asset['handle'] ?? '' );
			$type   = (string) ( $asset['type'] ?? 'script' );
			if ( '' === $handle ) {
				continue;
			}

			$key = $this->asset_key( $handle, $type );
			if ( ! isset( $index[ $key ] ) ) {
				$index[ $key ] = array();
			}

			$index[ $key ] = array_values(
				array_filter(
					$index[ $key ],
					static fn( array $row ): bool => ( $row['url'] ?? '' ) !== $scan_url
				)
			);

			array_unshift( $index[ $key ], $entry );
			$index[ $key ] = array_slice( $index[ $key ], 0, self::MAX_URLS_PER_ASSET );
		}

		set_transient( self::TRANSIENT_KEY, $index, self::TTL );
	}

	/**
	 * @return array{count: int, recent_pages: array<int, array{url: string, scanned_at: string}>}
	 */
	public function get_usage( string $handle, string $type ): array {
		$index = get_transient( self::TRANSIENT_KEY );
		$key   = $this->asset_key( $handle, $type );
		$rows  = is_array( $index ) && isset( $index[ $key ] ) && is_array( $index[ $key ] )
			? $index[ $key ]
			: array();

		$recent = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['url'] ) ) {
				continue;
			}
			$recent[] = array(
				'url'        => (string) $row['url'],
				'scanned_at' => isset( $row['time'] ) ? gmdate( 'Y-m-d H:i:s', (int) $row['time'] ) : '',
			);
		}

		$history_count = ( new \AssetControl\Database\ScanHistoryRepository() )->count();

		return array(
			'count'         => count( $recent ),
			'recent_pages'  => $recent,
			'history_ready' => $history_count > 0,
			'history_count' => $history_count,
		);
	}

	private function asset_key( string $handle, string $type ): string {
		return $type . ':' . $handle;
	}
}
