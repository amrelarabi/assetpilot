<?php
/**
 * Scan URL registry for impact preview (backed by scan history table).
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets;

defined( 'ABSPATH' ) || exit;
use AssetControl\Database\ScanHistoryRepository;

/**
 * Lightweight facade over persisted scan history.
 */
final class ScanHistoryIndex {

	public function __construct(
		private readonly ScanHistoryRepository $repository = new ScanHistoryRepository()
	) {}

	public function record( string $url ): void {
		// Persistence happens in ScanSnapshotService during full scans.
		unset( $url );
	}

	/**
	 * @return array<int, array{url: string, scanned_at: string}>
	 */
	public function get_entries(): array {
		$list = $this->repository->list( 1, 50 );
		$out  = array();

		foreach ( $list['items'] as $row ) {
			$out[] = array(
				'url'        => (string) ( $row['scan_url'] ?? '' ),
				'scanned_at' => (string) ( $row['scanned_at'] ?? '' ),
			);
		}

		return $out;
	}

	/**
	 * @return array<int, string>
	 */
	public function get_urls(): array {
		return $this->repository->get_recent_urls( 50 );
	}

	public function count(): int {
		return $this->repository->count();
	}
}
