<?php
/**
 * Builds dependency graph data for the admin visualization.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets;

defined( 'ABSPATH' ) || exit;
use AssetControl\Database\RulesRepository;
use AssetControl\Helpers\Cache;

/**
 * Computes nodes and edges from enqueued frontend assets only (visitor view).
 */
final class DependencyGraphBuilder {

	private const MAX_NODES = 200;

	private const MAX_UPSTREAM_ITERATIONS = 64;

	/** @var array<string, bool> */
	private static array $critical_handles = array(
		'jquery'         => true,
		'jquery-core'    => true,
		'jquery-migrate' => true,
		'wp-polyfill'    => true,
		'wp-hooks'       => true,
		'wp-i18n'        => true,
	);

	public function __construct(
		private readonly AssetCapture $capture = new AssetCapture(),
		private readonly RulesRepository $rules = new RulesRepository()
	) {}

	/**
	 * @return array<string, mixed>
	 */
	public function build( string $scan_url, string $asset_type = 'all', string $focus_handle = '', string $focus_type = 'script' ): array {
		$scan_url = $this->normalize_url( $scan_url );
		$cache_key = Cache::graph_key( $scan_url, $asset_type, $focus_handle, $focus_type );

		return Cache::remember(
			$cache_key,
			Cache::graph_ttl(),
			function () use ( $scan_url, $asset_type, $focus_handle, $focus_type ): array {
				return $this->build_uncached( $scan_url, $asset_type, $focus_handle, $focus_type );
			}
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_uncached( string $scan_url, string $asset_type, string $focus_handle, string $focus_type ): array {
		// Visitor mode: no admin bar (underscore, wp-util, etc.).
		$state = $this->capture->bootstrap_dependency_registry( $scan_url, true, true );

		try {
			$types = $this->resolve_types( $asset_type );
			$nodes = array();
			$edges = array();

			foreach ( $types as $type ) {
				$queue = 'script' === $type ? wp_scripts() : wp_styles();
				if ( ! $queue instanceof \WP_Dependencies ) {
					continue;
				}

				$enqueued = array_values( array_filter( (array) ( $queue->queue ?? array() ), 'is_string' ) );
				$built    = $this->build_for_queue( $type, $queue, $enqueued );
				$nodes    = array_merge( $nodes, $built['nodes'] );
				$edges    = array_merge( $edges, $built['edges'] );
			}

			if ( '' !== $focus_handle ) {
				$focus_id = $this->node_id( $focus_type, $focus_handle );
				list( $nodes, $edges ) = $this->filter_focus( $nodes, $edges, $focus_id );
			}

			$truncated = false;
			if ( count( $nodes ) > self::MAX_NODES ) {
				$truncated = true;
				$node_map  = array_slice( $nodes, 0, self::MAX_NODES, true );
				$allowed   = array_fill_keys( array_keys( $node_map ), true );
				$edges     = array_values(
					array_filter(
						$edges,
						static fn( array $edge ): bool => isset( $allowed[ $edge['source'] ], $allowed[ $edge['target'] ] )
					)
				);
				$nodes = $node_map;
			}

			$nodes = $this->apply_layout( $nodes, $edges );

			return array(
				'nodes'      => array_values( $nodes ),
				'edges'      => $edges,
				'scan_url'   => $scan_url,
				'asset_type' => $asset_type,
				'focus'      => '' !== $focus_handle
					? array(
						'handle' => $focus_handle,
						'type'   => $focus_type,
					)
					: null,
				'meta'       => array(
					'node_count'    => count( $nodes ),
					'edge_count'    => count( $edges ),
					'truncated'     => $truncated,
					'max_nodes'     => self::MAX_NODES,
					'visitor_mode'  => true,
				),
			);
		} finally {
			$this->capture->restore_after_bootstrap( $state );
		}
	}

	/**
	 * @param array<int, string> $enqueued_handles
	 * @return array{nodes: array<string, array<string, mixed>>, edges: array<int, array<string, mixed>>}
	 */
	private function build_for_queue( string $type, \WP_Dependencies $queue, array $enqueued_handles ): array {
		$nodes = array();
		$edges = array();

		if ( empty( $enqueued_handles ) ) {
			return array(
				'nodes' => $nodes,
				'edges' => $edges,
			);
		}

		$keep = array_fill_keys( $enqueued_handles, true );

		// Only walk upstream: dependencies required by enqueued assets (not every script that uses jQuery).
		$changed    = true;
		$iterations = 0;
		while ( $changed && $iterations < self::MAX_UPSTREAM_ITERATIONS ) {
			++$iterations;
			$changed = false;
			foreach ( array_keys( $keep ) as $handle ) {
				if ( ! isset( $queue->registered[ $handle ] ) ) {
					continue;
				}
				foreach ( (array) ( $queue->registered[ $handle ]->deps ?? array() ) as $dep ) {
					if ( ! is_string( $dep ) || '' === $dep || isset( $keep[ $dep ] ) ) {
						continue;
					}
					if ( ! isset( $queue->registered[ $dep ] ) ) {
						continue;
					}
					$keep[ $dep ] = true;
					$changed      = true;
				}
			}
		}

		$dependent_counts = $this->count_dependents_within( $queue, $keep );
		$enqueued_set     = array_flip( $enqueued_handles );

		foreach ( array_keys( $keep ) as $handle ) {
			if ( ! isset( $queue->registered[ $handle ] ) ) {
				continue;
			}

			$item    = $queue->registered[ $handle ];
			$node_id = $this->node_id( $type, $handle );

			$nodes[ $node_id ] = array(
				'id'              => $node_id,
				'handle'          => $handle,
				'type'            => $type,
				'label'           => $handle,
				'src'             => (string) ( $item->src ?? '' ),
				'enqueued'        => isset( $enqueued_set[ $handle ] ),
				'dependent_count' => $dependent_counts[ $handle ] ?? 0,
				'is_critical'     => $this->is_critical( $handle, $dependent_counts[ $handle ] ?? 0 ),
				'rules'           => $this->rules_for_handle( $handle, $type ),
			);
		}

		foreach ( array_keys( $keep ) as $handle ) {
			if ( ! isset( $queue->registered[ $handle ] ) ) {
				continue;
			}
			$target_id = $this->node_id( $type, $handle );
			foreach ( (array) ( $queue->registered[ $handle ]->deps ?? array() ) as $dep ) {
				if ( ! is_string( $dep ) || '' === $dep || ! isset( $keep[ $dep ] ) ) {
					continue;
				}
				$source_id = $this->node_id( $type, $dep );
				if ( ! isset( $nodes[ $source_id ], $nodes[ $target_id ] ) ) {
					continue;
				}
				$edges[] = array(
					'id'          => $source_id . '->' . $target_id,
					'source'      => $source_id,
					'target'      => $target_id,
					'is_critical' => ! empty( $nodes[ $source_id ]['is_critical'] ) || ! empty( $nodes[ $target_id ]['is_critical'] ),
				);
			}
		}

		return array(
			'nodes' => $nodes,
			'edges' => $edges,
		);
	}

	/**
	 * @param array<string, bool> $keep
	 * @return array<string, int>
	 */
	private function count_dependents_within( \WP_Dependencies $queue, array $keep ): array {
		$counts = array();
		foreach ( $queue->registered as $handle => $item ) {
			if ( ! isset( $keep[ $handle ] ) ) {
				continue;
			}
			foreach ( (array) ( $item->deps ?? array() ) as $dep ) {
				if ( is_string( $dep ) && '' !== $dep && isset( $keep[ $dep ] ) ) {
					$counts[ $dep ] = ( $counts[ $dep ] ?? 0 ) + 1;
				}
			}
		}
		return $counts;
	}

	/**
	 * @return array<int, string>
	 */
	private function resolve_types( string $asset_type ): array {
		return match ( $asset_type ) {
			'script', 'scripts' => array( 'script' ),
			'style', 'styles' => array( 'style' ),
			default => array( 'script', 'style' ),
		};
	}

	private function node_id( string $type, string $handle ): string {
		return $type . ':' . $handle;
	}

	private function is_critical( string $handle, int $dependent_count ): bool {
		if ( $dependent_count >= 3 ) {
			return true;
		}
		return isset( self::$critical_handles[ $handle ] );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function rules_for_handle( string $handle, string $type ): array {
		$matched = array();
		foreach ( $this->rules->find_for_asset( $handle, $type ) as $rule ) {
			$matched[] = array(
				'id'          => (int) ( $rule['id'] ?? 0 ),
				'action_type' => (string) ( $rule['action_type'] ?? '' ),
				'enabled'     => (bool) ( $rule['enabled'] ?? false ),
				'label'       => (string) ( $rule['label'] ?? '' ),
			);
		}
		return $matched;
	}

	/**
	 * @param array<string, array<string, mixed>> $nodes
	 * @param array<int, array<string, mixed>>    $edges
	 * @return array{0: array<string, array<string, mixed>>, 1: array<int, array<string, mixed>>}
	 */
	private function filter_focus( array $nodes, array $edges, string $focus_id ): array {
		if ( ! isset( $nodes[ $focus_id ] ) ) {
			return array( $nodes, $edges );
		}

		$keep    = array( $focus_id => true );
		$changed    = true;
		$iterations = 0;
		while ( $changed && $iterations < self::MAX_UPSTREAM_ITERATIONS ) {
			++$iterations;
			$changed = false;
			foreach ( $edges as $edge ) {
				if ( isset( $keep[ $edge['source'] ] ) && ! isset( $keep[ $edge['target'] ] ) ) {
					$keep[ $edge['target'] ] = true;
					$changed                 = true;
				}
				if ( isset( $keep[ $edge['target'] ] ) && ! isset( $keep[ $edge['source'] ] ) ) {
					$keep[ $edge['source'] ] = true;
					$changed                 = true;
				}
			}
		}

		$nodes = array_intersect_key( $nodes, $keep );
		$edges = array_values(
			array_filter(
				$edges,
				static fn( array $edge ): bool => isset( $keep[ $edge['source'] ], $keep[ $edge['target'] ] )
			)
		);

		return array( $nodes, $edges );
	}

	/**
	 * @param array<string, array<string, mixed>> $nodes
	 * @param array<int, array<string, mixed>>    $edges
	 * @return array<string, array<string, mixed>>
	 */
	private function apply_layout( array $nodes, array $edges ): array {
		if ( empty( $nodes ) ) {
			return $nodes;
		}

		$depth = array();
		foreach ( array_keys( $nodes ) as $id ) {
			$depth[ $id ] = 0;
		}

		for ( $i = 0; $i < 32; $i++ ) {
			$changed = false;
			foreach ( $edges as $edge ) {
				$source = $edge['source'];
				$target = $edge['target'];
				if ( ! isset( $depth[ $source ], $depth[ $target ] ) ) {
					continue;
				}
				if ( $depth[ $target ] < $depth[ $source ] + 1 ) {
					$depth[ $target ] = $depth[ $source ] + 1;
					$changed          = true;
				}
			}
			if ( ! $changed ) {
				break;
			}
		}

		$columns = array();
		foreach ( $depth as $id => $level ) {
			$columns[ $level ][] = $id;
		}
		ksort( $columns );

		$column_width = (int) apply_filters( 'assetpilot_dependency_graph_column_width', 360 );
		$row_height   = (int) apply_filters( 'assetpilot_dependency_graph_row_height', 100 );

		foreach ( $columns as $level => $ids ) {
			sort( $ids );
			foreach ( $ids as $index => $id ) {
				if ( ! isset( $nodes[ $id ] ) ) {
					continue;
				}
				$nodes[ $id ]['position'] = array(
					'x' => (int) $level * $column_width,
					'y' => (int) $index * $row_height,
				);
			}
		}

		return $nodes;
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
