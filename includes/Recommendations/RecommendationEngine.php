<?php
/**
 * Aggregates recommendation providers.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Recommendations;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\FrontendScanner;
use AssetControl\Assets\ScanSnapshotService;
use AssetControl\Database\RulesRepository;
use AssetControl\Database\ScanHistoryRepository;
use AssetControl\Helpers\Cache;
use AssetControl\Recommendations\Providers\DuplicateLibraryRecommendationProvider;
use AssetControl\Recommendations\Providers\LargeAssetRecommendationProvider;
use AssetControl\Recommendations\Providers\LowUsageRecommendationProvider;
use AssetControl\Recommendations\Providers\RenderBlockingRecommendationProvider;

/**
 * Builds recommendation list for a scan (never auto-applies).
 */
final class RecommendationEngine {

	/** @var array<int, RecommendationProviderInterface> */
	private array $providers;

	/**
	 * @param array<int, RecommendationProviderInterface>|null $providers
	 */
	public function __construct(
		?array $providers = null,
		private readonly RulesRepository $rules = new RulesRepository(),
		private readonly ScanHistoryRepository $scan_history = new ScanHistoryRepository(),
		private readonly ScanSnapshotService $snapshots = new ScanSnapshotService(),
		private readonly FrontendScanner $scanner = new FrontendScanner()
	) {
		$this->providers = $providers ?? array(
			new LargeAssetRecommendationProvider(),
			new RenderBlockingRecommendationProvider(),
			new DuplicateLibraryRecommendationProvider(),
			new LowUsageRecommendationProvider(),
		);
	}

	/**
	 * @return array{recommendations: array<int, array<string, mixed>>, scan_url: string, meta: array<string, mixed>}
	 */
	public function collect( string $scan_url, int $scan_id = 0 ): array {
		$scan_url = $this->normalize_url( $scan_url );
		$cache_key = Cache::recommendations_key( $scan_url, $scan_id );

		return Cache::remember(
			$cache_key,
			Cache::recommendations_ttl(),
			function () use ( $scan_url, $scan_id ): array {
				return $this->collect_uncached( $scan_url, $scan_id );
			}
		);
	}

	/**
	 * @return array{recommendations: array<int, array<string, mixed>>, scan_url: string, meta: array<string, mixed>}
	 */
	private function collect_uncached( string $scan_url, int $scan_id ): array {
		$assets = $this->load_assets( $scan_url, $scan_id );

		$usage_map = $this->build_usage_map();
		$context   = new RecommendationContext(
			$assets,
			$scan_url,
			$this->rules->all_cached(),
			$usage_map['counts'],
			$usage_map['distinct_urls']
		);

		$items = array();
		foreach ( $this->providers as $provider ) {
			$items = array_merge( $items, $provider->recommend( $context ) );
		}

		$items = $this->dedupe( $items );
		usort(
			$items,
			static function ( array $a, array $b ): int {
				$order = array( 'high' => 0, 'medium' => 1, 'low' => 2 );
				$ca    = $order[ $a['confidence'] ?? 'low' ] ?? 3;
				$cb    = $order[ $b['confidence'] ?? 'low' ] ?? 3;
				if ( $ca !== $cb ) {
					return $ca <=> $cb;
				}
				return ( (int) ( $b['size'] ?? 0 ) ) <=> ( (int) ( $a['size'] ?? 0 ) );
			}
		);

		return array(
			'recommendations' => $items,
			'scan_url'        => $scan_url,
			'meta'            => array(
				'asset_count'       => count( $assets ),
				'distinct_scans'    => $usage_map['distinct_urls'],
				'recommendation_count' => count( $items ),
			),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function load_assets( string $scan_url, int $scan_id ): array {
		if ( $scan_id > 0 ) {
			$snapshot = $this->snapshots->get( $scan_id );
			if ( is_array( $snapshot ) && ! empty( $snapshot['assets'] ) ) {
				return $snapshot['assets'];
			}
		}

		$result = $this->scanner->scan_url( $scan_url );
		return is_array( $result['assets'] ?? null ) ? $result['assets'] : array();
	}

	/**
	 * @return array{counts: array<string, int>, distinct_urls: int}
	 */
	private function build_usage_map(): array {
		$list   = $this->scan_history->list( 1, 40 );
		$items  = $list['items'] ?? array();
		$counts = array();
		$urls   = array();

		foreach ( $items as $row ) {
			$id = (int) ( $row['id'] ?? 0 );
			if ( $id <= 0 ) {
				continue;
			}
			$scan = $this->scan_history->find( $id, true );
			if ( ! is_array( $scan ) || empty( $scan['scan_url'] ) ) {
				continue;
			}
			$urls[ (string) $scan['scan_url'] ] = true;
			foreach ( (array) ( $scan['assets'] ?? array() ) as $asset ) {
				if ( ! is_array( $asset ) ) {
					continue;
				}
				$handle = (string) ( $asset['handle'] ?? '' );
				$type   = (string) ( $asset['type'] ?? '' );
				if ( '' === $handle ) {
					continue;
				}
				$key = $type . ':' . $handle;
				if ( ! isset( $counts[ $key ] ) ) {
					$counts[ $key ] = array();
				}
				$counts[ $key ][ (string) $scan['scan_url'] ] = true;
			}
		}

		$flat = array();
		foreach ( $counts as $key => $url_set ) {
			$flat[ $key ] = count( $url_set );
		}

		return array(
			'counts'        => $flat,
			'distinct_urls' => count( $urls ),
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $items
	 * @return array<int, array<string, mixed>>
	 */
	private function dedupe( array $items ): array {
		$seen = array();
		$out  = array();
		foreach ( $items as $item ) {
			$id = (string) ( $item['id'] ?? '' );
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$out[]       = $item;
		}
		return $out;
	}

	private function normalize_url( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return (string) home_url( '/' );
		}
		$clean = esc_url_raw( $url );
		return '' !== $clean ? $clean : $url;
	}
}
