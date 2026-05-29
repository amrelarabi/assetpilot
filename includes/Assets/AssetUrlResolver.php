<?php
/**
 * Resolves full URLs for registered script/style handles.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets;

defined( 'ABSPATH' ) || exit;
/**
 * Shared URL resolution for registry and runtime.
 */
final class AssetUrlResolver {

	public function resolve_handle( string $handle, string $type ): string {
		$queue = 'script' === $type ? wp_scripts() : wp_styles();

		if ( ! $queue || ! isset( $queue->registered[ $handle ] ) ) {
			return '';
		}

		$item = $queue->registered[ $handle ];
		$src  = (string) ( $item->src ?? '' );

		if ( '' === $src ) {
			return '';
		}

		if ( str_starts_with( $src, '//' ) ) {
			$scheme = is_ssl() ? 'https:' : 'http:';
			return $scheme . $src;
		}

		if ( str_starts_with( $src, 'http' ) ) {
			return $src;
		}

		return $queue->base_url . $src;
	}

	public function resolve_custom_url( string $url ): string {
		$url = esc_url_raw( $url );
		if ( str_starts_with( $url, '//' ) ) {
			$scheme = is_ssl() ? 'https:' : 'http:';
			return $scheme . $url;
		}
		return $url;
	}
}
