<?php
/**
 * Parses frontend HTML for runtime verification checks.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Verification;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\AssetHandleResolver;
use AssetControl\Assets\HtmlAssetParser;

/**
 * Extracts script, style, and preload tags with attributes from rendered HTML.
 */
final class HTMLVerificationParser {

	public function __construct(
		private readonly AssetHandleResolver $handle_resolver = new AssetHandleResolver(),
		private readonly HtmlAssetParser $html_parser = new HtmlAssetParser()
	) {}

	/**
	 * @return array{
	 *   scripts: array<int, array{src: string, handle: string, defer: bool, async: bool, fetchpriority: string}>,
	 *   styles: array<int, array{href: string, handle: string}>,
	 *   preloads: array<int, array{href: string, as: string, fetchpriority: string}>,
	 *   images: array<int, array{fetchpriority: string, src: string}>
	 * }
	 */
	public function parse( string $html, string $page_url = '' ): array {
		$page_url = $page_url ?: home_url( '/' );

		return array(
			'scripts'  => $this->parse_scripts( $html, $page_url ),
			'styles'   => $this->parse_styles( $html, $page_url ),
			'preloads' => $this->parse_preloads( $html, $page_url ),
			'images'   => $this->parse_images( $html ),
		);
	}

	/**
	 * @return array{html: string, parsed: array<string, mixed>, error: string}
	 */
	public function fetch_and_parse( string $url, int $timeout = 0 ): array {
		if ( $timeout <= 0 ) {
			$timeout = (int) apply_filters( 'assetpilot_verification_timeout', 15 );
		}

		$response = $this->fetch_body( $url, $timeout );
		if ( '' === $response['body'] ) {
			return array(
				'html'   => '',
				'parsed' => $this->empty_parsed(),
				'error'  => $response['error'],
			);
		}

		return array(
			'html'   => $response['body'],
			'parsed' => $this->parse( $response['body'], $url ),
			'error'  => '',
		);
	}

	/**
	 * @return array{body: string, error: string}
	 */
	private function fetch_body( string $url, int $timeout ): array {
		$url = $this->sanitize_url( $url );
		if ( '' === $url || ! $this->is_valid_url( $url ) ) {
			return array(
				'body'  => '',
				'error' => 'invalid_url',
			);
		}

		$timeout = max( 3, min( 30, $timeout ) );
		$args    = (array) apply_filters(
			'assetpilot_verification_request_args',
			array(
				'timeout'     => $timeout,
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter for local HTTPS fetches.
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
				'redirection' => 3,
				'headers'     => array(
					'User-Agent' => 'assetpilot-Verify/1.0',
				),
			),
			$url
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			$response = $this->maybe_retry_localhost( $url, $args, $response );
		}

		if ( is_wp_error( $response ) ) {
			return array(
				'body'  => '',
				'error' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 400 ) {
			return array(
				'body'  => '',
				'error' => 'http_' . $code,
			);
		}

		return array(
			'body'  => (string) wp_remote_retrieve_body( $response ),
			'error' => '',
		);
	}

	private function sanitize_url( string $url ): string {
		$url   = trim( rawurldecode( $url ) );
		$clean = esc_url_raw( $url );
		return '' !== $clean ? $clean : $url;
	}

	private function is_valid_url( string $url ): bool {
		if ( wp_http_validate_url( $url ) ) {
			return true;
		}

		$parsed = wp_parse_url( $url );

		return ! empty( $parsed['host'] )
			&& in_array( $parsed['scheme'] ?? 'http', array( 'http', 'https' ), true );
	}

	/**
	 * Retry via 127.0.0.1 when .local / .test hosts time out (common on Local WP).
	 *
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>|\WP_Error
	 */
	private function maybe_retry_localhost( string $url, array $args, $error ) {
		if ( ! is_wp_error( $error ) ) {
			return $error;
		}

		$parsed = wp_parse_url( $url );
		$host   = $parsed['host'] ?? '';

		if ( '' === $host || ! preg_match( '/\.(local|test)$/i', $host ) ) {
			return $error;
		}

		$retry_url = ( $parsed['scheme'] ?? 'http' ) . '://127.0.0.1' . ( $parsed['path'] ?? '/' );
		if ( ! empty( $parsed['query'] ) ) {
			$retry_url .= '?' . $parsed['query'];
		}

		$retry_args            = $args;
		$retry_args['headers'] = array_merge(
			(array) ( $args['headers'] ?? array() ),
			array( 'Host' => $host )
		);

		return wp_remote_get( $retry_url, $retry_args );
	}

	/**
	 * @return array{scripts: array, styles: array, preloads: array, images: array}
	 */
	private function empty_parsed(): array {
		return array(
			'scripts'  => array(),
			'styles'   => array(),
			'preloads' => array(),
			'images'   => array(),
		);
	}

	/**
	 * @return array<int, array{src: string, handle: string, defer: bool, async: bool, fetchpriority: string}>
	 */
	private function parse_scripts( string $html, string $page_url ): array {
		$scripts = array();

		if ( ! preg_match_all( '/<script\b([^>]*?)>/i', $html, $matches, PREG_SET_ORDER ) ) {
			return $scripts;
		}

		foreach ( $matches as $match ) {
			$attrs = $match[1];
			if ( ! preg_match( '/\ssrc=["\']([^"\']+)["\']/i', $attrs, $src_match ) ) {
				continue;
			}
			$src      = $this->resolve_absolute( $src_match[1], $page_url );
			$handle   = $this->handle_resolver->resolve( '', $src, 'script' ) ?? $this->guess_handle( $src );
			$scripts[] = array(
				'src'            => $src,
				'handle'         => $handle,
				'defer'          => $this->has_attr( $attrs, 'defer' ),
				'async'          => $this->has_attr( $attrs, 'async' ),
				'fetchpriority'  => $this->get_attr_value( $attrs, 'fetchpriority' ),
			);
		}

		return $scripts;
	}

	/**
	 * @return array<int, array{href: string, handle: string}>
	 */
	private function parse_styles( string $html, string $page_url ): array {
		$styles = array();

		if ( ! preg_match_all( '/<link\b([^>]*?)>/i', $html, $matches, PREG_SET_ORDER ) ) {
			return $styles;
		}

		foreach ( $matches as $match ) {
			$attrs = $match[1];
			if ( ! preg_match( '/\srel=["\']stylesheet["\']/i', $attrs ) ) {
				continue;
			}
			if ( ! preg_match( '/\shref=["\']([^"\']+)["\']/i', $attrs, $href_match ) ) {
				continue;
			}
			$href   = $this->resolve_absolute( $href_match[1], $page_url );
			$handle = $this->handle_resolver->resolve( '', $href, 'style' ) ?? $this->guess_handle( $href );
			$styles[] = array(
				'href'   => $href,
				'handle' => $handle,
			);
		}

		return $styles;
	}

	/**
	 * @return array<int, array{href: string, as: string, fetchpriority: string}>
	 */
	private function parse_preloads( string $html, string $page_url ): array {
		$preloads = array();

		if ( ! preg_match_all( '/<link\b([^>]*?)>/i', $html, $matches, PREG_SET_ORDER ) ) {
			return $preloads;
		}

		foreach ( $matches as $match ) {
			$attrs = $match[1];
			if ( ! preg_match( '/\srel=["\']preload["\']/i', $attrs ) ) {
				continue;
			}
			if ( ! preg_match( '/\shref=["\']([^"\']+)["\']/i', $attrs, $href_match ) ) {
				continue;
			}
			$preloads[] = array(
				'href'          => $this->resolve_absolute( $href_match[1], $page_url ),
				'as'            => $this->get_attr_value( $attrs, 'as' ),
				'fetchpriority' => $this->get_attr_value( $attrs, 'fetchpriority' ),
			);
		}

		return $preloads;
	}

	/**
	 * @return array<int, array{fetchpriority: string, src: string}>
	 */
	private function parse_images( string $html ): array {
		$images = array();

		if ( ! preg_match_all( '/<img\b([^>]*?)>/i', $html, $matches, PREG_SET_ORDER ) ) {
			return $images;
		}

		foreach ( $matches as $match ) {
			$attrs = $match[1];
			$prio  = $this->get_attr_value( $attrs, 'fetchpriority' );
			if ( '' === $prio ) {
				continue;
			}
			$src = '';
			if ( preg_match( '/\ssrc=["\']([^"\']+)["\']/i', $attrs, $src_match ) ) {
				$src = $src_match[1];
			}
			$images[] = array(
				'fetchpriority' => $prio,
				'src'           => $src,
			);
		}

		return $images;
	}

	private function has_attr( string $attrs, string $name ): bool {
		return (bool) preg_match( '/\s' . preg_quote( $name, '/' ) . '(?:\s|>|=|$)/i', $attrs );
	}

	private function get_attr_value( string $attrs, string $name ): string {
		if ( preg_match( '/\s' . preg_quote( $name, '/' ) . '=["\']([^"\']*)["\']/i', $attrs, $m ) ) {
			return strtolower( trim( $m[1] ) );
		}
		if ( $this->has_attr( $attrs, $name ) ) {
			return $name;
		}
		return '';
	}

	private function resolve_absolute( string $src, string $page_url ): string {
		$src = trim( $src );
		if ( str_starts_with( $src, '//' ) ) {
			$parsed = wp_parse_url( $page_url );
			$src    = ( $parsed['scheme'] ?? 'http' ) . ':' . $src;
		}
		if ( preg_match( '#^https?://#i', $src ) ) {
			return $src;
		}
		if ( str_starts_with( $src, '/' ) ) {
			return home_url( $src );
		}
		return trailingslashit( dirname( $page_url ) ) . ltrim( $src, '/' );
	}

	private function guess_handle( string $src ): string {
		$path = (string) wp_parse_url( $src, PHP_URL_PATH );
		$base = pathinfo( $path, PATHINFO_FILENAME ) ?: 'asset';
		return sanitize_title( basename( dirname( $path ) ) . '-' . $base ) ?: 'assetpilot-asset';
	}
}
