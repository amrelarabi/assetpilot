<?php
/**
 * Asset registry — collects registered scripts and styles.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets;

defined( 'ABSPATH' ) || exit;
use AssetControl\Helpers\UrlFilesystemResolver;
/**
 * Builds a registry from $wp_scripts and $wp_styles.
 */
final class Registry {

	public function __construct(
		private readonly OriginDetector $origin_detector = new OriginDetector(),
		private readonly AssetUrlResolver $url_resolver = new AssetUrlResolver()
	) {}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function collect( bool $frontend_only = true ): array {
		$assets = array();

		$scripts = $this->collect_from_queue( 'script', $frontend_only );
		$styles  = $this->collect_from_queue( 'style', $frontend_only );

		return array_merge( $scripts, $styles );
	}

	/**
	 * Only handles present in $wp_scripts->queue / $wp_styles->queue (accurate for analyze mode).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function collect_enqueued(): array {
		return array_values(
			array_filter(
				$this->collect(),
				static fn( array $asset ): bool => ! empty( $asset['enqueued'] )
			)
		);
	}

	/**
	 * Snapshot of WordPress dependency queues during render.
	 *
	 * @return array{scripts: array<int, string>, styles: array<int, string>}
	 */
	public static function get_queue_snapshot(): array {
		$scripts = wp_scripts();
		$styles  = wp_styles();

		return array(
			'scripts' => $scripts ? array_values( $scripts->queue ) : array(),
			'styles'  => $styles ? array_values( $styles->queue ) : array(),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_from_queue( string $type, bool $frontend_only ): array {
		$queue = 'script' === $type ? wp_scripts() : wp_styles();
		if ( ! $queue ) {
			return array();
		}

		$items = array();

		foreach ( $queue->registered as $handle => $item ) {
			$src = $item->src ?? '';
			$origin = $this->origin_detector->detect( (string) $src );

			$items[] = array(
				'handle'       => $handle,
				'type'         => $type,
				'src'          => $this->url_resolver->resolve_handle( $handle, $type ) ?: $this->resolve_src( $queue, $handle, (string) $src ),
				'deps'         => $item->deps ?? array(),
				'version'      => $item->ver ?? '',
				'media'        => 'style' === $type ? ( $item->args ?? 'all' ) : null,
				'origin'       => $origin['origin'],
				'source'       => $origin['source'],
				'size'         => $this->get_local_file_size( (string) $src ),
				'in_footer'    => 'script' === $type ? (bool) ( $item->extra['group'] ?? false ) : null,
				'enqueued'     => in_array( $handle, $queue->queue, true ),
				'registered'   => true,
			);
		}

		return $items;
	}

	private function resolve_src( \WP_Dependencies $queue, string $handle, string $src ): string {
		if ( '' === $src ) {
			return '';
		}
		if ( str_starts_with( $src, '//' ) || str_starts_with( $src, 'http' ) ) {
			return $src;
		}
		return $queue->base_url . $src;
	}

	private function get_local_file_size( string $src ): ?int {
		if ( '' === $src || str_starts_with( $src, '//' ) || str_starts_with( $src, 'http' ) ) {
			$path = $this->url_to_path( $src );
		} else {
			$path = $this->url_to_path( $src );
		}

		if ( ! $path || ! file_exists( $path ) ) {
			return null;
		}

		return (int) filesize( $path );
	}

	private function url_to_path( string $url ): ?string {
		$path = UrlFilesystemResolver::resolve( $url );

		return '' !== $path ? $path : null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function merge_custom_assets( array $assets, array $custom ): array {
		foreach ( $custom as $item ) {
			if ( empty( $item['handle'] ) || empty( $item['src'] ) ) {
				continue;
			}
			$assets[] = array(
				'handle'     => sanitize_text_field( (string) $item['handle'] ),
				'type'       => sanitize_key( (string) ( $item['type'] ?? 'image' ) ),
				'src'        => esc_url_raw( (string) $item['src'] ),
				'deps'       => array(),
				'version'    => '',
				'media'      => null,
				'origin'     => 'custom',
				'source'     => 'custom',
				'size'       => null,
				'in_footer'  => null,
				'enqueued'   => true,
				'registered' => true,
			);
		}
		return $assets;
	}

	public function find_by_handle( string $handle, string $type = 'script' ): ?array {
		foreach ( $this->collect() as $asset ) {
			if ( $asset['handle'] === $handle && $asset['type'] === $type ) {
				return $asset;
			}
		}
		return null;
	}
}
