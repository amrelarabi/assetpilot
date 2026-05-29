<?php
/**
 * Parse assets from rendered page HTML (same technique as Page Analyzer).
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets;

defined( 'ABSPATH' ) || exit;
/**
 * Extracts script/style URLs from HTML and maps them to registry asset rows.
 */
final class HtmlAssetParser {

	private int $last_http_code = 0;

	private string $last_error = '';

	public function __construct(
		private readonly OriginDetector $origin_detector = new OriginDetector()
	) {}

	public function get_last_http_code(): int {
		return $this->last_http_code;
	}

	public function get_last_error(): string {
		return $this->last_error;
	}

	/**
	 * @return array{assets: array<int, array<string, mixed>>, body_length: int}
	 */
	public function fetch_url( string $url, int $timeout = 8 ): array {
		$this->last_http_code = 0;
		$this->last_error     = '';

		$clean = \esc_url_raw( $url );
		$url   = '' !== $clean ? $clean : trim( $url );

		if ( '' === $url || ! \wp_http_validate_url( $url ) ) {
			$this->last_error = 'invalid_url';
			return array(
				'assets'      => array(),
				'body_length' => 0,
			);
		}

		$timeout = max( 3, min( 30, $timeout ) );

		$response = \wp_remote_get(
			$url,
			array(
				'timeout'     => $timeout,
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter for local HTTPS fetches.
				'sslverify'   => \apply_filters( 'https_local_ssl_verify', false ),
				'redirection' => 3,
				'headers'     => array(
					'User-Agent' => 'assetpilot-Scanner/1.0',
				),
			)
		);

		if ( \is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			$response         = $this->maybe_retry_localhost( $url, $timeout, $response );
		}

		if ( \is_wp_error( $response ) ) {
			return array(
				'assets'      => array(),
				'body_length' => 0,
			);
		}

		$this->last_http_code = (int) wp_remote_retrieve_response_code( $response );
		$body                 = (string) wp_remote_retrieve_body( $response );

		if ( $this->last_http_code < 200 || $this->last_http_code >= 400 ) {
			$this->last_error = 'http_' . $this->last_http_code;
			return array(
				'assets'      => array(),
				'body_length' => strlen( $body ),
			);
		}

		return array(
			'assets'      => $this->parse_html( $body, $url ),
			'body_length' => strlen( $body ),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function parse_html( string $html, string $page_url = '' ): array {
		$assets = array();

		if ( preg_match_all( '/<script[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $script_matches ) ) {
			foreach ( $script_matches[1] as $src ) {
				$absolute = $this->resolve_absolute_url( $src, $page_url );
				if ( '' !== $absolute ) {
					$assets[] = $this->build_asset_row( $absolute, 'script' );
				}
			}
		}

		if ( preg_match_all( '/<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $style_matches ) ) {
			foreach ( $style_matches[1] as $href ) {
				$absolute = $this->resolve_absolute_url( $href, $page_url );
				if ( '' !== $absolute ) {
					$assets[] = $this->build_asset_row( $absolute, 'style' );
				}
			}
		}

		if ( preg_match_all( '/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\']stylesheet["\'][^>]*>/i', $html, $style_matches_alt ) ) {
			foreach ( $style_matches_alt[1] as $href ) {
				$absolute = $this->resolve_absolute_url( $href, $page_url );
				if ( '' !== $absolute ) {
					$assets[] = $this->build_asset_row( $absolute, 'style' );
				}
			}
		}

		return $assets;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function build_asset_row( string $src, string $type ): array {
		$handle = $this->resolve_handle_from_src( $src, $type );
		$origin = $this->origin_detector->detect( $src );

		return array(
			'handle'     => $handle,
			'type'       => $type,
			'src'        => $src,
			'deps'       => array(),
			'version'    => '',
			'media'      => 'style' === $type ? 'all' : null,
			'origin'     => $origin['origin'],
			'source'     => $origin['source'],
			'size'       => null,
			'in_footer'  => null,
			'enqueued'   => true,
			'registered' => true,
			'from_html'  => true,
		);
	}

	private function resolve_absolute_url( string $src, string $page_url ): string {
		$src = trim( $src );

		if ( '' === $src ) {
			return '';
		}

		if ( str_starts_with( $src, '//' ) ) {
			$parsed = wp_parse_url( $page_url ?: \home_url( '/' ) );
			$scheme = $parsed['scheme'] ?? 'http';
			$src    = $scheme . ':' . $src;
		}

		if ( preg_match( '#^https?://#i', $src ) ) {
			return $this->sanitize_src( $src );
		}

		if ( str_starts_with( $src, '/' ) ) {
			return $this->sanitize_src( \home_url( $src ) );
		}

		$base = trailingslashit( dirname( $page_url ?: \home_url( '/' ) ) );
		return $this->sanitize_src( $base . ltrim( $src, '/' ) );
	}

	/**
	 * Retry via 127.0.0.1 when .local / .test hosts time out (common on Local WP).
	 *
	 * @param string           $url     Original URL.
	 * @param int              $timeout Request timeout.
	 * @param \WP_Error|mixed  $error   Previous error.
	 * @return \WP_Error|array<string, mixed>
	 */
	private function maybe_retry_localhost( string $url, int $timeout, $error ) {
		if ( ! \is_wp_error( $error ) ) {
			return $error;
		}

		$parsed = \wp_parse_url( $url );
		$host   = $parsed['host'] ?? '';

		if ( '' === $host || ! preg_match( '/\.(local|test)$/i', $host ) ) {
			return $error;
		}

		$retry_url = ( $parsed['scheme'] ?? 'http' ) . '://127.0.0.1' . ( $parsed['path'] ?? '/' );
		if ( ! empty( $parsed['query'] ) ) {
			$retry_url .= '?' . $parsed['query'];
		}

		return \wp_remote_get(
			$retry_url,
			array(
				'timeout'     => $timeout,
				'sslverify'   => false,
				'redirection' => 3,
				'headers'     => array(
					'Host'       => $host,
					'User-Agent' => 'assetpilot-Scanner/1.0',
				),
			)
		);
	}

	/**
	 * esc_url_raw() can return empty for .local dev URLs — keep the resolved URL.
	 */
	private function sanitize_src( string $url ): string {
		$url   = trim( $url );
		$clean = \esc_url_raw( $url );
		return '' !== $clean ? $clean : $url;
	}

	private function resolve_handle_from_src( string $src, string $type ): string {
		$resolver = new AssetHandleResolver();
		$resolved = $resolver->resolve( '', $src, $type );

		if ( null !== $resolved ) {
			return $resolved;
		}

		$path = (string) wp_parse_url( $src, PHP_URL_PATH );
		$base = pathinfo( $path, PATHINFO_FILENAME ) ?: 'asset';
		$dir  = basename( dirname( $path ) );
		$slug = sanitize_title( $dir . '-' . $base );

		return '' !== $slug ? $slug : 'assetpilot-' . substr( md5( $src ), 0, 8 );
	}

	private function urls_match( string $registered_src, string $html_src, string $base_url ): bool {
		$reg  = $this->normalize_url( $this->make_absolute( $registered_src, $base_url ) );
		$html = $this->normalize_url( $html_src );

		return $reg === $html || str_ends_with( $html, $reg ) || str_ends_with( $reg, $html );
	}

	private function make_absolute( string $src, string $base_url ): string {
		if ( str_starts_with( $src, '//' ) ) {
			return ( is_ssl() ? 'https:' : 'http:' ) . $src;
		}
		if ( str_starts_with( $src, 'http' ) ) {
			return $src;
		}
		return $base_url . $src;
	}

	private function normalize_url( string $url ): string {
		return strtok( set_url_scheme( $url, 'relative' ), '?' ) ?: $url;
	}

	/**
	 * @param array<int, array<string, mixed>> $primary
	 * @param array<int, array<string, mixed>> $secondary
	 * @return array<int, array<string, mixed>>
	 */
	public function merge( array $primary, array $secondary ): array {
		$merged = array();
		$seen   = array();

		foreach ( array_merge( $primary, $secondary ) as $asset ) {
			if ( ! is_array( $asset ) ) {
				continue;
			}

			$src = (string) ( $asset['src'] ?? '' );
			if ( '' === $src ) {
				continue;
			}

			$key = ( $asset['type'] ?? 'unknown' ) . '|' . $this->normalize_url( $src );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$merged[]     = $asset;
		}

		return $merged;
	}
}
