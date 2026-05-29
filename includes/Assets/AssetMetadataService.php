<?php
/**
 * Aggregates asset metadata for the details drawer.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets;

defined( 'ABSPATH' ) || exit;
use AssetControl\Helpers\UrlFilesystemResolver;
use AssetControl\Database\RulesRepository;
use AssetControl\Verification\RuntimeVerificationService;

/**
 * Builds drawer payload from queues, rules, and scan context.
 */
final class AssetMetadataService {

	public function __construct(
		private readonly DependencyResolver $dependency_resolver = new DependencyResolver(),
		private readonly AssetUsageService $usage_service = new AssetUsageService(),
		private readonly OriginDetector $origin_detector = new OriginDetector(),
		private readonly AssetUrlResolver $url_resolver = new AssetUrlResolver()
	) {}

	/**
	 * @param array<string, mixed>|null $snapshot Optional asset row from last scan response.
	 * @return array<string, mixed>
	 */
	public function get_details(
		string $handle,
		string $type,
		string $scan_url = '',
		?array $snapshot = null
	): array {
		$type   = in_array( $type, array( 'script', 'style' ), true ) ? $type : 'script';
		$queue  = 'script' === $type ? wp_scripts() : wp_styles();
		$item   = ( $queue && isset( $queue->registered[ $handle ] ) ) ? $queue->registered[ $handle ] : null;
		$src    = $snapshot['src'] ?? ( $item ? (string) ( $item->src ?? '' ) : '' );
		$src    = $this->url_resolver->resolve_handle( $handle, $type ) ?: $src;

		if ( '' === $src && $item ) {
			$src = (string) ( $item->src ?? '' );
		}

		$origin = $this->origin_detector->detect( $src );
		if ( ! empty( $snapshot['origin'] ) ) {
			$origin = array(
				'origin' => (string) $snapshot['origin'],
				'source' => (string) ( $snapshot['source'] ?? '' ),
			);
		}

		$deps_data = $this->dependency_resolver->resolve( $handle, $type );
		$rules     = $this->get_rules_for_asset( $handle, $type );
		$usage     = $this->usage_service->get_usage( $handle, $type );
		$runtime   = $this->build_runtime_metadata( $handle, $type, $queue, $rules, $snapshot, $scan_url );

		$size = $snapshot['size'] ?? null;
		if ( null === $size && '' !== $src ) {
			$size = $this->estimate_size( $src );
		}

		if ( $scan_url && ! empty( $rules ) ) {
			$rules = $this->attach_rule_verification( $rules, $scan_url, $handle, $type );
		}

		return array(
			'asset'        => array(
				'handle'     => $handle,
				'type'       => $type,
				'src'        => $src,
				'source'     => $origin['source'],
				'origin'     => $origin['origin'],
				'version'    => (string) ( $snapshot['version'] ?? ( $item?->ver ?? '' ) ),
				'size'       => $size,
				'media'      => $snapshot['media'] ?? ( 'style' === $type && $item ? ( $item->args ?? 'all' ) : null ),
				'in_footer'  => $snapshot['in_footer'] ?? ( $item && 'script' === $type ? (bool) ( $item->extra['group'] ?? false ) : null ),
				'enqueued'   => (bool) ( $snapshot['enqueued'] ?? ( $queue && $item && in_array( $handle, $queue->queue, true ) ) ),
				'registered' => (bool) ( $snapshot['registered'] ?? ( null !== $item ) ),
			),
			'dependencies' => $deps_data,
			'runtime'      => $runtime,
			'usage'        => $usage,
			'rules'        => $rules,
			'scan_url'     => $scan_url,
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function get_rules_for_asset( string $handle, string $type ): array {
		$repo  = new RulesRepository();
		$rules = array();

		foreach ( $repo->find_for_asset( $handle, $type ) as $rule ) {
			$rules[] = array(
				'id'                => (int) $rule['id'],
				'action_type'       => (string) ( $rule['action_type'] ?? '' ),
				'enabled'           => (bool) ( $rule['enabled'] ?? false ),
				'priority'          => (int) ( $rule['priority'] ?? 10 ),
				'condition_summary' => $this->summarize_conditions( $rule['condition_group'] ?? array() ),
				'verification'      => $rule['verification'] ?? null,
			);
		}

		usort(
			$rules,
			static fn( array $a, array $b ): int => ( $a['priority'] ?? 10 ) <=> ( $b['priority'] ?? 10 )
		);

		return $rules;
	}

	/**
	 * @param array<int, array<string, mixed>> $rules
	 * @param array<string, mixed>|null        $snapshot
	 * @return array<string, mixed>
	 */
	private function build_runtime_metadata(
		string $handle,
		string $type,
		?\WP_Dependencies $queue,
		array $rules,
		?array $snapshot,
		string $scan_url
	): array {
		$enqueue_order = null;
		if ( $queue ) {
			$position = array_search( $handle, $queue->queue, true );
			if ( false !== $position ) {
				$enqueue_order = (int) $position + 1;
			}
		}

		$actions = array_column( $rules, 'action_type' );
		$enabled_rules = array_filter( $rules, static fn( array $r ): bool => ! empty( $r['enabled'] ) );

		$loaded_on_scan = false;
		if ( ! empty( $snapshot['enqueued'] ) ) {
			$loaded_on_scan = true;
		} elseif ( $scan_url && $queue && in_array( $handle, $queue->queue, true ) ) {
			$loaded_on_scan = true;
		}

		return array(
			'loaded_on_scan'      => $loaded_on_scan,
			'enqueue_order'       => $enqueue_order,
			'queue_length'        => $queue ? count( $queue->queue ) : 0,
			'active_rules_count'  => count( $enabled_rules ),
			'total_rules_count'   => count( $rules ),
			'has_disable_rule'    => in_array( 'disable', $actions, true ),
			'has_defer_rule'      => in_array( 'defer', $actions, true ),
			'has_async_rule'      => in_array( 'async', $actions, true ),
			'has_preload_rule'    => in_array( 'preload', $actions, true ),
			'has_fetchpriority_rule' => in_array( 'fetchpriority', $actions, true ),
		);
	}

	/**
	 * @param array<string, mixed> $conditions
	 */
	/**
	 * @param array<int, array<string, mixed>> $rules
	 * @return array<int, array<string, mixed>>
	 */
	private function attach_rule_verification( array $rules, string $scan_url, string $handle, string $type ): array {
		unset( $handle, $type );
		$repo     = new RulesRepository();
		$resolver = new \AssetControl\Verification\RuleVerificationUrlResolver();
		$runtime  = new RuntimeVerificationService();
		$parser   = new \AssetControl\Verification\HTMLVerificationParser();

		foreach ( $rules as $index => $rule ) {
			if ( ! empty( $rule['verification']['status'] ) ) {
				continue;
			}

			$full = $repo->find( (int) ( $rule['id'] ?? 0 ) );
			if ( ! $full ) {
				continue;
			}

			if ( ! empty( $full['verification']['status'] ) ) {
				$rules[ $index ]['verification'] = $full['verification'];
				continue;
			}

			$url   = '' !== $scan_url ? $scan_url : $resolver->resolve( $full );
			$fetch = $parser->fetch_and_parse( $url );
			if ( '' !== $fetch['error'] ) {
				$rules[ $index ]['verification'] = array(
					'rule_id'  => (int) $full['id'],
					'status'   => RuntimeVerificationService::STATUS_UNAVAILABLE,
					'message'  => $fetch['error'],
					'expected' => '',
					'actual'   => '',
					'url'      => $url,
				);
				continue;
			}

			$result = $runtime->verify_rule( $full, $fetch['parsed'], $url );
			$rules[ $index ]['verification'] = array_merge(
				$result,
				array(
					'url'         => $url,
					'verified_at' => gmdate( 'c' ),
				)
			);
		}

		return $rules;
	}

	/**
	 * @param array<string, mixed> $conditions
	 */
	private function summarize_conditions( array $conditions ): string {
		if ( ! empty( $conditions['global'] ) ) {
			return __( 'Entire site', 'assetpilot' );
		}
		if ( ! empty( $conditions['url_contains'] ) ) {
			return sprintf(
				/* translators: %s: URL fragment */
				__( 'URL contains: %s', 'assetpilot' ),
				$conditions['url_contains']
			);
		}
		return __( 'Conditional', 'assetpilot' );
	}

	private function estimate_size( string $src ): ?int {
		$path = $this->url_to_path( $src );
		if ( '' === $path || ! is_readable( $path ) ) {
			return null;
		}
		$size = filesize( $path );
		return false !== $size ? (int) $size : null;
	}

	private function url_to_path( string $url ): string {
		return UrlFilesystemResolver::resolve( $url );
	}
}
