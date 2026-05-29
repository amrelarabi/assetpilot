<?php
/**
 * Maps scanned / guessed handles to real WP script/style handles.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets;

defined( 'ABSPATH' ) || exit;
/**
 * Resolves asset handles on the frontend where the registry is fully loaded.
 */
final class AssetHandleResolver {

	public function __construct(
		private readonly AssetUrlResolver $url_resolver = new AssetUrlResolver()
	) {}

	/**
	 * @param string $handle Guessed or canonical handle.
	 * @param string $src    Full or partial asset URL from rule config / scan.
	 * @param string $type   script|style
	 */
	public function resolve( string $handle, string $src, string $type ): ?string {
		$queue = 'script' === $type ? wp_scripts() : wp_styles();

		if ( ! $queue ) {
			return null;
		}

		if ( isset( $queue->registered[ $handle ] ) ) {
			return $handle;
		}

		if ( '' !== $src ) {
			foreach ( $queue->registered as $reg_handle => $item ) {
				$item_src = (string) ( $item->src ?? '' );
				if ( '' === $item_src ) {
					continue;
				}
				$absolute = $this->absolute_src( $item_src, $queue->base_url );
				if ( $this->urls_match( $src, $absolute ) ) {
					return (string) $reg_handle;
				}
			}
		}

		$fragment = $this->path_fragment_from_guessed_handle( $handle, $type );
		if ( '' === $fragment ) {
			return null;
		}

		foreach ( $queue->registered as $reg_handle => $item ) {
			$item_src = (string) ( $item->src ?? '' );
			if ( '' === $item_src ) {
				continue;
			}
			$absolute = $this->normalize_url( $this->absolute_src( $item_src, $queue->base_url ) );
			if ( str_contains( $absolute, $fragment ) ) {
				return (string) $reg_handle;
			}
		}

		return null;
	}

	/**
	 * Preserve .local URLs that esc_url_raw() may reject.
	 */
	public function sanitize_url( string $url ): string {
		$url   = trim( $url );
		$clean = \esc_url_raw( $url );

		return '' !== $clean ? $clean : $url;
	}

	private function absolute_src( string $src, string $base_url ): string {
		if ( str_starts_with( $src, '//' ) ) {
			return ( is_ssl() ? 'https:' : 'http:' ) . $src;
		}
		if ( str_starts_with( $src, 'http' ) ) {
			return $src;
		}

		return $base_url . $src;
	}

	private function urls_match( string $a, string $b ): bool {
		$a = $this->normalize_url( $a );
		$b = $this->normalize_url( $b );

		return $a === $b || str_ends_with( $a, $b ) || str_ends_with( $b, $a );
	}

	private function normalize_url( string $url ): string {
		return strtok( \set_url_scheme( $url, 'relative' ), '?' ) ?: $url;
	}

	/**
	 * css-main-min (from .../css/main.min.css) -> main.min.css
	 */
	private function path_fragment_from_guessed_handle( string $handle, string $type ): string {
		if ( ! preg_match( '/^(?:css|js|style|script)-(.+)$/i', $handle, $matches ) ) {
			return '';
		}

		$slug = $matches[1];
		if ( preg_match( '/^(.+)-min$/', $slug, $parts ) ) {
			$base = str_replace( '-', '.', $parts[1] );
			$ext  = 'script' === $type ? '.min.js' : '.min.css';

			return $base . $ext;
		}

		$ext = 'script' === $type ? '.js' : '.css';

		return str_replace( '-', '.', $slug ) . $ext;
	}
}
