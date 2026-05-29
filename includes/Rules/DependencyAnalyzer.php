<?php
/**
 * Dependency chain analysis.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Rules;

defined( 'ABSPATH' ) || exit;
use AssetControl\Helpers\Cache;

/**
 * Analyzes script/style dependencies and warns about breaking chains.
 */
final class DependencyAnalyzer {

	private const MAX_DEPTH = 12;

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_dependents( string $handle, string $type = 'script' ): array {
		$queue = 'script' === $type ? wp_scripts() : wp_styles();
		if ( ! $queue ) {
			return array();
		}

		$dependents = array();
		$visited    = array();
		$this->collect_dependents( $handle, $type, $queue, 0, $dependents, $visited );

		return $dependents;
	}

	/**
	 * @param array<int, array<string, mixed>> $dependents
	 * @param array<string, bool>            $visited
	 */
	private function collect_dependents(
		string $handle,
		string $type,
		\WP_Dependencies $queue,
		int $depth,
		array &$dependents,
		array &$visited
	): void {
		if ( $depth > self::MAX_DEPTH ) {
			return;
		}

		$key = $type . ':' . $handle;
		if ( isset( $visited[ $key ] ) ) {
			return;
		}
		$visited[ $key ] = true;

		foreach ( $queue->registered as $reg_handle => $item ) {
			$deps = $item->deps ?? array();
			if ( ! in_array( $handle, $deps, true ) ) {
				continue;
			}

			$dependents[] = array(
				'handle' => $reg_handle,
				'type'   => $type,
			);
			$this->collect_dependents( $reg_handle, $type, $queue, $depth + 1, $dependents, $visited );
		}
	}

	/**
	 * @return array{warnings: array<int, string>, dependents: array<int, array<string, mixed>>}
	 */
	public function analyze( string $handle, string $action, string $type = 'script' ): array {
		$cache_key = Cache::dependency_analysis_key( $handle, $type, $action );

		return Cache::remember(
			$cache_key,
			Cache::dependency_ttl(),
			function () use ( $handle, $action, $type ): array {
				return $this->analyze_uncached( $handle, $action, $type );
			}
		);
	}

	/**
	 * @return array{warnings: array<int, string>, dependents: array<int, array<string, mixed>>}
	 */
	private function analyze_uncached( string $handle, string $action, string $type ): array {
		$dependents = $this->get_dependents( $handle, $type );
		$warnings   = array();

		if ( empty( $dependents ) ) {
			return array(
				'warnings'   => $warnings,
				'dependents' => $dependents,
			);
		}

		$handles = array_map(
			static fn( array $d ): string => $d['handle'],
			$dependents
		);

		$warnings[] = sprintf(
			/* translators: 1: asset handle, 2: comma-separated dependent handles, 3: action */
			__( 'Asset "%1$s" is required by: %2$s. Applying "%3$s" may break dependent assets.', 'assetpilot' ),
			$handle,
			implode( ', ', $handles ),
			$action
		);

		return array(
			'warnings'   => $warnings,
			'dependents' => $dependents,
		);
	}

	/**
	 * @return array<int, string>
	 */
	public function get_chain( string $handle, string $type = 'script' ): array {
		$queue = 'script' === $type ? wp_scripts() : wp_styles();
		if ( ! $queue || ! isset( $queue->registered[ $handle ] ) ) {
			return array();
		}

		$visited = array();
		$chain   = array();
		$this->chain_visit( $handle, $type, $queue, 0, $visited, $chain );

		return array_values( array_unique( $chain ) );
	}

	/**
	 * @param array<string, bool> $visited
	 * @param array<int, string>  $chain
	 */
	private function chain_visit(
		string $handle,
		string $type,
		\WP_Dependencies $queue,
		int $depth,
		array &$visited,
		array &$chain
	): void {
		if ( $depth > self::MAX_DEPTH || isset( $visited[ $handle ] ) || ! isset( $queue->registered[ $handle ] ) ) {
			return;
		}

		$visited[ $handle ] = true;

		foreach ( (array) ( $queue->registered[ $handle ]->deps ?? array() ) as $dep ) {
			if ( is_string( $dep ) && '' !== $dep ) {
				$this->chain_visit( $dep, $type, $queue, $depth + 1, $visited, $chain );
			}
		}

		$chain[] = $handle;
	}
}
