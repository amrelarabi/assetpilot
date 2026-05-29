<?php
/**
 * Builds and stores scan snapshots.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets;

defined( 'ABSPATH' ) || exit;
use AssetControl\Database\ScanHistoryRepository;

/**
 * Persists scan results and compares snapshots.
 */
final class ScanSnapshotService {

	public function __construct(
		private readonly ScanHistoryRepository $repository = new ScanHistoryRepository()
	) {}

	/**
	 * @param array<int, array<string, mixed>> $assets Prepared assets.
	 * @return array<string, mixed>|null
	 */
	public function save( string $scan_url, array $assets, string $source = '' ): ?array {
		if ( '' === $scan_url || empty( $assets ) ) {
			return null;
		}

		$stats = $this->compute_stats( $assets );

		return $this->repository->create(
			array_merge(
				$stats,
				array(
					'scan_url' => $scan_url,
					'assets'   => $assets,
					'source'   => $source,
				)
			)
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $assets
	 * @return array{asset_count: int, script_count: int, style_count: int, total_js_size: int, total_css_size: int}
	 */
	public function compute_stats( array $assets ): array {
		$script_count   = 0;
		$style_count    = 0;
		$total_js_size  = 0;
		$total_css_size = 0;

		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}
			$type = (string) ( $asset['type'] ?? 'script' );
			$size = isset( $asset['size'] ) ? (int) $asset['size'] : 0;

			if ( 'style' === $type ) {
				++$style_count;
				$total_css_size += max( 0, $size );
			} else {
				++$script_count;
				$total_js_size += max( 0, $size );
			}
		}

		return array(
			'asset_count'    => count( $assets ),
			'script_count'   => $script_count,
			'style_count'    => $style_count,
			'total_js_size'  => $total_js_size,
			'total_css_size' => $total_css_size,
		);
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function get( int $id ): ?array {
		return $this->repository->find( $id, true );
	}

	/**
	 * @return array{scan_a: array<string, mixed>, scan_b: array<string, mixed>, added: array, removed: array, unchanged: int}
	 */
	public function compare( int $scan_id_a, int $scan_id_b ): ?array {
		$scan_a = $this->repository->find( $scan_id_a, true );
		$scan_b = $this->repository->find( $scan_id_b, true );

		if ( ! $scan_a || ! $scan_b ) {
			return null;
		}

		$assets_a = is_array( $scan_a['assets'] ?? null ) ? $scan_a['assets'] : array();
		$assets_b = is_array( $scan_b['assets'] ?? null ) ? $scan_b['assets'] : array();

		$diff = $this->diff_assets( $assets_a, $assets_b );

		return array(
			'scan_a'    => $this->summary_for_compare( $scan_a ),
			'scan_b'    => $this->summary_for_compare( $scan_b ),
			'added'     => $diff['added'],
			'removed'   => $diff['removed'],
			'changed'   => $diff['changed'],
			'unchanged' => $diff['unchanged'],
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $assets_a
	 * @param array<int, array<string, mixed>> $assets_b
	 * @return array{added: array<int, array<string, mixed>>, removed: array<int, array<string, mixed>>, changed: array<int, array<string, mixed>>, unchanged: int}
	 */
	private function diff_assets( array $assets_a, array $assets_b ): array {
		$map_a = $this->asset_map( $assets_a );
		$map_b = $this->asset_map( $assets_b );

		$added    = array();
		$removed  = array();
		$changed  = array();
		$unchanged = 0;

		foreach ( $map_b as $key => $asset ) {
			if ( ! isset( $map_a[ $key ] ) ) {
				$added[] = $asset;
				continue;
			}
			if ( $this->asset_signature( $map_a[ $key ] ) !== $this->asset_signature( $asset ) ) {
				$changed[] = array(
					'before' => $map_a[ $key ],
					'after'  => $asset,
				);
			} else {
				++$unchanged;
			}
		}

		foreach ( $map_a as $key => $asset ) {
			if ( ! isset( $map_b[ $key ] ) ) {
				$removed[] = $asset;
			}
		}

		return array(
			'added'     => $added,
			'removed'   => $removed,
			'changed'   => $changed,
			'unchanged' => $unchanged,
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $assets
	 * @return array<string, array<string, mixed>>
	 */
	private function asset_map( array $assets ): array {
		$map = array();
		foreach ( $assets as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}
			$key = $this->asset_key( $asset );
			if ( '' !== $key ) {
				$map[ $key ] = $asset;
			}
		}
		return $map;
	}

	/**
	 * @param array<string, mixed> $asset
	 */
	private function asset_key( array $asset ): string {
		$handle = (string) ( $asset['handle'] ?? '' );
		$type   = (string) ( $asset['type'] ?? 'script' );
		if ( '' === $handle ) {
			return '';
		}
		return $type . ':' . $handle;
	}

	/**
	 * @param array<string, mixed> $asset
	 */
	private function asset_signature( array $asset ): string {
		return wp_json_encode(
			array(
				'src'     => (string) ( $asset['src'] ?? '' ),
				'version' => (string) ( $asset['version'] ?? '' ),
				'size'    => $asset['size'] ?? null,
			)
		) ?: '';
	}

	/**
	 * @param array<string, mixed> $scan
	 * @return array<string, mixed>
	 */
	private function summary_for_compare( array $scan ): array {
		return array(
			'id'           => (int) ( $scan['id'] ?? 0 ),
			'scan_url'     => (string) ( $scan['scan_url'] ?? '' ),
			'scanned_at'   => (string) ( $scan['scanned_at'] ?? '' ),
			'asset_count'  => (int) ( $scan['asset_count'] ?? 0 ),
			'script_count' => (int) ( $scan['script_count'] ?? 0 ),
			'style_count'  => (int) ( $scan['style_count'] ?? 0 ),
		);
	}
}
