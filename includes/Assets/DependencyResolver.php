<?php
/**
 * Resolves asset dependency trees with loop protection.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets;

defined( 'ABSPATH' ) || exit;
/**
 * Builds dependency and dependent trees for the asset details drawer.
 */
final class DependencyResolver {

	private const MAX_DEPTH = 12;

	/**
	 * @return array{
	 *   dependencies: array<int, array{handle: string, depth: int, parent: string|null}>,
	 *   dependents: array<int, array{handle: string, depth: int, parent: string|null}>,
	 *   dependency_chain: array<int, string>,
	 *   direct_dependencies: array<int, string>,
	 *   direct_dependents: array<int, string>
	 * }
	 */
	public function resolve( string $handle, string $type = 'script' ): array {
		$queue = $this->get_queue( $type );
		if ( ! $queue || ! isset( $queue->registered[ $handle ] ) ) {
			return array(
				'dependencies'        => array(),
				'dependents'          => array(),
				'dependency_chain'    => array(),
				'direct_dependencies' => array(),
				'direct_dependents'   => array(),
			);
		}

		$direct_deps = array_values( array_filter( (array) ( $queue->registered[ $handle ]->deps ?? array() ) ) );
		$direct_dep_handles = $this->get_direct_dependents( $handle, $type, $queue );

		return array(
			'dependencies'        => $this->walk_dependencies( $handle, $type, $queue ),
			'dependents'          => $this->walk_dependents( $handle, $type, $queue ),
			'dependency_chain'    => $this->build_chain( $handle, $type, $queue ),
			'direct_dependencies' => $direct_deps,
			'direct_dependents'   => $direct_dep_handles,
		);
	}

	/**
	 * @return array<int, array{handle: string, depth: int, parent: string|null}>
	 */
	private function walk_dependencies( string $handle, string $type, \WP_Dependencies $queue ): array {
		$lines   = array();
		$visited = array();

		$this->collect_upstream( $handle, $type, $queue, 0, null, $lines, $visited );

		return $lines;
	}

	/**
	 * @param array<int, array{handle: string, depth: int, parent: string|null}> $lines
	 * @param array<string, bool> $visited
	 */
	private function collect_upstream(
		string $handle,
		string $type,
		\WP_Dependencies $queue,
		int $depth,
		?string $parent,
		array &$lines,
		array &$visited
	): void {
		if ( $depth > self::MAX_DEPTH ) {
			return;
		}

		$key = $type . ':' . $handle;
		if ( isset( $visited[ $key ] ) ) {
			$lines[] = array(
				'handle'  => $handle,
				'depth'   => $depth,
				'parent'  => $parent,
				'circular' => true,
			);
			return;
		}

		$visited[ $key ] = true;

		if ( ! isset( $queue->registered[ $handle ] ) ) {
			return;
		}

		$deps = (array) ( $queue->registered[ $handle ]->deps ?? array() );
		foreach ( $deps as $dep ) {
			if ( ! is_string( $dep ) || '' === $dep ) {
				continue;
			}
			$lines[] = array(
				'handle'   => $dep,
				'depth'    => $depth,
				'parent'   => $handle,
				'circular' => false,
			);
			$this->collect_upstream( $dep, $type, $queue, $depth + 1, $handle, $lines, $visited );
		}
	}

	/**
	 * @return array<int, array{handle: string, depth: int, parent: string|null}>
	 */
	private function walk_dependents( string $handle, string $type, \WP_Dependencies $queue ): array {
		$lines   = array();
		$visited = array();

		$this->collect_downstream( $handle, $type, $queue, 0, null, $lines, $visited );

		return $lines;
	}

	/**
	 * @param array<int, array{handle: string, depth: int, parent: string|null}> $lines
	 * @param array<string, bool> $visited
	 */
	private function collect_downstream(
		string $handle,
		string $type,
		\WP_Dependencies $queue,
		int $depth,
		?string $parent,
		array &$lines,
		array &$visited
	): void {
		if ( $depth > self::MAX_DEPTH ) {
			return;
		}

		$key = $type . ':' . $handle . ':down';
		if ( isset( $visited[ $key ] ) ) {
			return;
		}

		$visited[ $key ] = true;

		foreach ( $queue->registered as $reg_handle => $item ) {
			$deps = (array) ( $item->deps ?? array() );
			if ( ! in_array( $handle, $deps, true ) ) {
				continue;
			}

			if ( $depth > 0 || $reg_handle !== $handle ) {
				$lines[] = array(
					'handle'   => $reg_handle,
					'depth'    => $depth,
					'parent'   => $handle,
					'circular' => false,
				);
			}

			$this->collect_downstream( $reg_handle, $type, $queue, $depth + 1, $handle, $lines, $visited );
		}
	}

	/**
	 * @return array<int, string>
	 */
	private function get_direct_dependents( string $handle, string $type, \WP_Dependencies $queue ): array {
		$dependents = array();
		foreach ( $queue->registered as $reg_handle => $item ) {
			$deps = (array) ( $item->deps ?? array() );
			if ( in_array( $handle, $deps, true ) ) {
				$dependents[] = $reg_handle;
			}
		}
		return $dependents;
	}

	/**
	 * Flat load order chain (dependencies first).
	 *
	 * @return array<int, string>
	 */
	private function build_chain( string $handle, string $type, \WP_Dependencies $queue ): array {
		$visited = array();
		$chain   = array();
		$this->chain_visit( $handle, $type, $queue, $visited, $chain, 0 );
		return array_values( array_unique( $chain ) );
	}

	/**
	 * @param array<string, bool> $visited
	 * @param array<int, string> $chain
	 */
	private function chain_visit(
		string $handle,
		string $type,
		\WP_Dependencies $queue,
		array &$visited,
		array &$chain,
		int $depth = 0
	): void {
		if ( $depth > self::MAX_DEPTH || isset( $visited[ $handle ] ) || ! isset( $queue->registered[ $handle ] ) ) {
			return;
		}
		$visited[ $handle ] = true;
		foreach ( (array) ( $queue->registered[ $handle ]->deps ?? array() ) as $dep ) {
			if ( is_string( $dep ) && '' !== $dep ) {
				$this->chain_visit( $dep, $type, $queue, $visited, $chain, $depth + 1 );
			}
		}
		$chain[] = $handle;
	}

	private function get_queue( string $type ): ?\WP_Dependencies {
		$queue = 'script' === $type ? wp_scripts() : wp_styles();
		return $queue instanceof \WP_Dependencies ? $queue : null;
	}
}
