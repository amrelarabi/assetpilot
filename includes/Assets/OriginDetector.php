<?php
/**
 * Detect asset origin (core, plugin, theme).
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets;

defined( 'ABSPATH' ) || exit;
/**
 * Determines whether an asset URL belongs to core, a plugin, or the active theme.
 */
final class OriginDetector {

	/**
	 * @return array{origin: string, source: string}
	 */
	public function detect( string $src ): array {
		if ( '' === $src ) {
			return array(
				'origin' => 'unknown',
				'source' => '',
			);
		}

		$src = $this->normalize_url( $src );

		$includes_url = $this->normalize_url( includes_url() );
		if ( ( '' !== $includes_url && str_contains( $src, $includes_url ) ) || $this->matches_path_marker( $src, 'wp-includes' ) ) {
			return array(
				'origin' => 'core',
				'source' => 'WordPress',
			);
		}

		$plugin_url = $this->normalize_url( plugins_url() );
		if ( ( '' !== $plugin_url && str_contains( $src, $plugin_url ) ) || $this->matches_path_marker( $src, 'plugins' ) ) {
			$slug = $this->extract_slug_from_url( $src, $plugin_url, 'plugins' );
			return array(
				'origin' => 'plugin',
				'source' => $slug ?: 'plugin',
			);
		}

		$theme_root = $this->normalize_url( get_theme_root_uri() );
		if ( ( '' !== $theme_root && str_contains( $src, $theme_root ) ) || $this->matches_path_marker( $src, 'themes' ) ) {
			$slug = $this->extract_slug_from_url( $src, $theme_root, 'themes' );
			return array(
				'origin' => 'theme',
				'source' => $slug ?: wp_get_theme()->get_stylesheet(),
			);
		}

		$uploads = wp_upload_dir();
		$uploads_url = is_array( $uploads ) ? $this->normalize_url( (string) ( $uploads['baseurl'] ?? '' ) ) : '';
		if ( ( '' !== $uploads_url && str_contains( $src, $uploads_url ) ) || $this->matches_path_marker( $src, 'uploads' ) ) {
			return array(
				'origin' => 'upload',
				'source' => 'uploads',
			);
		}

		return array(
			'origin' => 'external',
			'source' => wp_parse_url( $src, PHP_URL_HOST ) ?: 'external',
		);
	}

	private function normalize_url( string $url ): string {
		return set_url_scheme( $url, 'relative' );
	}

	private function matches_path_marker( string $src, string $segment ): bool {
		$segment = trim( $segment, '/' );
		if ( '' === $segment ) {
			return false;
		}

		$content_path = wp_parse_url( content_url(), PHP_URL_PATH );
		if ( ! is_string( $content_path ) || '' === $content_path ) {
			return false;
		}

		$marker = trailingslashit( untrailingslashit( $content_path ) ) . $segment . '/';

		return str_contains( $src, $marker );
	}

	private function extract_slug_from_url( string $src, string $base_url, string $segment ): string {
		if ( '' !== $base_url ) {
			$pos = strpos( $src, $base_url );
			if ( false !== $pos ) {
				$rest  = substr( $src, $pos + strlen( $base_url ) );
				$parts = explode( '/', ltrim( $rest, '/' ) );
				return $parts[0] ?? '';
			}
		}

		$content_path = wp_parse_url( content_url(), PHP_URL_PATH );
		if ( ! is_string( $content_path ) || '' === $content_path ) {
			return '';
		}

		$marker = trailingslashit( untrailingslashit( $content_path ) ) . $segment . '/';
		$pos    = strpos( $src, $marker );
		if ( false === $pos ) {
			return '';
		}

		$rest  = substr( $src, $pos + strlen( $marker ) );
		$parts = explode( '/', $rest );

		return $parts[0] ?? '';
	}
}
