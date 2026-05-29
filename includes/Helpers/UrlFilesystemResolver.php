<?php
/**
 * Map public asset URLs to local filesystem paths.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves enqueued asset URLs to readable files without assuming site URL maps to ABSPATH.
 */
final class UrlFilesystemResolver {

	/**
	 * Resolve a URL or root-relative path to a readable filesystem path, or empty string.
	 */
	public static function resolve( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}

		if ( str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' ) ) {
			$path = self::relative_url_path_to_filesystem( $url );
			return ( '' !== $path && is_readable( $path ) ) ? $path : '';
		}

		foreach ( self::url_prefix_mappings() as $mapping ) {
			if ( ! str_starts_with( $url, $mapping['url'] ) ) {
				continue;
			}

			$relative = substr( $url, strlen( $mapping['url'] ) );
			$path     = wp_normalize_path( $mapping['dir'] . ltrim( $relative, '/' ) );
			if ( is_readable( $path ) ) {
				return $path;
			}
		}

		return '';
	}

	/**
	 * @return array<int, array{url: string, dir: string}>
	 */
	private static function url_prefix_mappings(): array {
		$mappings = array();

		$content_url = content_url();
		if ( is_string( $content_url ) && '' !== $content_url ) {
			$mappings[] = array(
				'url' => trailingslashit( $content_url ),
				'dir' => trailingslashit( WP_CONTENT_DIR ),
			);
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['baseurl'] ) && ! empty( $uploads['basedir'] ) ) {
			$mappings[] = array(
				'url' => trailingslashit( (string) $uploads['baseurl'] ),
				'dir' => trailingslashit( (string) $uploads['basedir'] ),
			);
		}

		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$plugins_url = plugins_url();
			if ( is_string( $plugins_url ) && '' !== $plugins_url ) {
				$mappings[] = array(
					'url' => trailingslashit( $plugins_url ),
					'dir' => trailingslashit( WP_PLUGIN_DIR ),
				);
			}
		}

		if ( function_exists( 'get_theme_root' ) ) {
			$theme_root_uri = get_theme_root_uri();
			if ( is_string( $theme_root_uri ) && '' !== $theme_root_uri && function_exists( 'get_theme_root' ) ) {
				$mappings[] = array(
					'url' => trailingslashit( $theme_root_uri ),
					'dir' => trailingslashit( get_theme_root() ),
				);
			}
		}

		$site_url = site_url();
		if ( is_string( $site_url ) && '' !== $site_url ) {
			$mappings[] = array(
				'url' => trailingslashit( $site_url ),
				'dir' => self::site_root_directory(),
			);
		}

		$home = home_url();
		if ( is_string( $home ) && '' !== $home && $home !== $site_url ) {
			$mappings[] = array(
				'url' => trailingslashit( $home ),
				'dir' => self::site_root_directory(),
			);
		}

		return $mappings;
	}

	/**
	 * Filesystem path to the WordPress site root (not URL concatenation).
	 */
	private static function site_root_directory(): string {
		if ( ! function_exists( 'get_home_path' ) ) {
			$core_file = ABSPATH . 'wp-admin/includes/file.php';
			if ( is_readable( $core_file ) ) {
				require_once $core_file;
			}
		}

		if ( function_exists( 'get_home_path' ) ) {
			return trailingslashit( wp_normalize_path( get_home_path() ) );
		}

		return trailingslashit( wp_normalize_path( ABSPATH ) );
	}

	private static function relative_url_path_to_filesystem( string $url_path ): string {
		$path = wp_parse_url( $url_path, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}

		foreach ( self::url_prefix_mappings() as $mapping ) {
			$base_path = wp_parse_url( $mapping['url'], PHP_URL_PATH );
			if ( ! is_string( $base_path ) || '' === $base_path ) {
				continue;
			}

			$base_path = trailingslashit( $base_path );
			if ( str_starts_with( $path, $base_path ) ) {
				$relative = substr( $path, strlen( $base_path ) );
				$candidate = wp_normalize_path( $mapping['dir'] . ltrim( $relative, '/' ) );
				if ( is_readable( $candidate ) ) {
					return $candidate;
				}
			}
		}

		return '';
	}
}
