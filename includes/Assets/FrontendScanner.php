<?php
/**
 * Scans frontend pages via internal render mode (accurate WP queues).
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets;

defined( 'ABSPATH' ) || exit;
use AssetControl\Helpers\OutputBuffer;
use AssetControl\Helpers\UrlHelper;

/**
 * Primary scan: in-process capture, then optional loopback ?assetpilot_analyze=1.
 */
final class FrontendScanner {

	private static bool $scan_in_progress = false;

	private const MARKER_START = '<!--ASSETPILOT_SCAN:';
	private const MARKER_END   = ':ASSETPILOT_SCAN-->';

	/** @var array<int, array<string, mixed>>|null */
	private ?array $captured_assets = null;

	/** @var array{scripts: array<int, string>, styles: array<int, string>}|null */
	private ?array $captured_queues = null;

	private bool $analyze_response_sent = false;

	private int $analyze_buffer_level = 0;

	private bool $analyze_buffer_started = false;

	public function init(): void {
		add_action( 'init', array( $this, 'bootstrap_analyze_mode' ), 1 );
		add_action( 'template_redirect', array( $this, 'register_analyze_hooks' ), 0 );
	}

	public function bootstrap_analyze_mode(): void {
		if ( ! $this->is_analyze_request() ) {
			return;
		}

		if ( ! defined( 'ASSETPILOT_ASSET_SCAN' ) ) {
			define( 'ASSETPILOT_ASSET_SCAN', true );
		}
	}

	public function register_analyze_hooks(): void {
		if ( ! defined( 'ASSETPILOT_ASSET_SCAN' ) || ! ASSETPILOT_ASSET_SCAN ) {
			return;
		}

		add_action( 'template_redirect', array( $this, 'start_output_buffer' ), 1 );
		add_action( 'wp_footer', array( $this, 'capture_queues' ), 99998 );
		add_action( 'wp_print_footer_scripts', array( $this, 'capture_queues' ), 99998 );
		add_action( 'wp_print_footer_scripts', array( $this, 'send_analyze_response' ), 99999 );
	}

	public function start_output_buffer(): void {
		$this->analyze_buffer_level  = OutputBuffer::start();
		$this->analyze_buffer_started = true;
	}

	public function capture_queues(): void {
		if ( ! defined( 'ASSETPILOT_ASSET_SCAN' ) || ! ASSETPILOT_ASSET_SCAN ) {
			return;
		}

		$registry = new Registry();

		$this->captured_assets = array_values(
			array_filter(
				$registry->collect_enqueued(),
				static fn( array $asset ): bool => '' !== ( $asset['src'] ?? '' )
			)
		);
		$this->captured_queues = Registry::get_queue_snapshot();
	}

	public function send_analyze_response(): void {
		if ( $this->analyze_response_sent || ! defined( 'ASSETPILOT_ASSET_SCAN' ) || ! ASSETPILOT_ASSET_SCAN ) {
			return;
		}

		$this->analyze_response_sent = true;

		if ( null === $this->captured_assets ) {
			$this->capture_queues();
		}

		if ( $this->analyze_buffer_started ) {
			OutputBuffer::end_clean( $this->analyze_buffer_level );
		}

		$payload = array(
			'assets' => $this->captured_assets ?? array(),
			'queues' => $this->captured_queues ?? Registry::get_queue_snapshot(),
			'url'    => \home_url( add_query_arg( array() ) ),
		);

		if ( ! headers_sent() ) {
			\status_header( 200 );
			header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo \wp_json_encode( $payload );
		exit;
	}

	public function get_scan_key(): string {
		return substr( \wp_hash( 'assetpilot_asset_scan' ), 0, 32 );
	}

	/**
	 * Internal analyze mode: ?assetpilot_analyze=1&assetpilot_scan_key=...
	 * Legacy alias: assetpilot_asset_scan=1
	 */
	public function is_analyze_request(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Scan token verified via assetpilot_scan_key hash_equals below.
		$analyze = ! empty( $_GET['assetpilot_analyze'] ) || ! empty( $_GET['assetpilot_asset_scan'] );
		$key     = ! empty( $_GET['assetpilot_scan_key'] )
			? sanitize_text_field( wp_unslash( $_GET['assetpilot_scan_key'] ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $analyze && '' !== $key && hash_equals( $this->get_scan_key(), $key );
	}

	/** @deprecated Use is_analyze_request() */
	public function is_scan_request(): bool {
		return $this->is_analyze_request();
	}

	/**
	 * Build analyze URL for a page (admin display / docs).
	 */
	public function get_analyze_url( string $url ): string {
		return add_query_arg(
			array(
				'assetpilot_analyze'  => '1',
				'assetpilot_scan_key' => $this->get_scan_key(),
			),
			$this->normalize_url( $url )
		);
	}

	/**
	 * @return array{assets: array<int, array<string, mixed>>, html_assets: array<int, array<string, mixed>>, debug: string, meta: array<string, mixed>}
	 */
	public static function get_cache_key( string $url ): string {
		return 'assetpilot_analyze_' . md5( $url );
	}

	public function clear_scan_cache( string $url ): void {
		$url = $this->normalize_url( $url );
		if ( '' === $url ) {
			return;
		}
		\delete_transient( self::get_cache_key( $url ) );
	}

	public function scan_url( string $url, bool $force_refresh = false ): array {
		if ( self::$scan_in_progress ) {
			return $this->empty_result( 'scan_busy' );
		}

		self::$scan_in_progress = true;
		$buffer_level = OutputBuffer::start();

		try {
			return $this->run_scan( $url, $force_refresh );
		} finally {
			self::$scan_in_progress = false;
			OutputBuffer::end_clean( $buffer_level );
		}
	}

	/**
	 * @return array{assets: array<int, array<string, mixed>>, html_assets: array<int, array<string, mixed>>, debug: string, meta: array<string, mixed>}
	 */
	private function run_scan( string $url, bool $force_refresh = false ): array {
		$url = $this->normalize_url( $url );

		if ( '' === $url || ! $this->is_valid_scan_url( $url ) ) {
			return $this->empty_result( 'invalid_url' );
		}

		if ( ! $this->is_same_site( $url, \home_url() ) && ! $this->is_same_site( $url, \site_url() ) ) {
			return $this->empty_result( 'url_not_same_site' );
		}

		$cache_key = self::get_cache_key( $url );
		if ( $force_refresh ) {
			\delete_transient( $cache_key );
		} else {
			$cached = \get_transient( $cache_key );
			if ( is_array( $cached ) && ! empty( $cached['assets'] ) ) {
				$cached['meta']['from_cache'] = true;
				return $cached;
			}
		}

		$loopback_first = UrlHelper::is_site_front_url( $url );
		$loopback_first = (bool) \apply_filters( 'assetpilot_loopback_first_for_front_url', $loopback_first, $url );

		$rest        = array(
			'queues'    => array(),
			'http_code' => 0,
			'timeout'   => 0,
		);
		$assets      = array();
		$method      = '';
		$fetch_error = '';

		if ( $loopback_first ) {
			$internal    = $this->scan_via_internal_render( $url );
			$assets      = $internal['assets'];
			$method      = 'loopback_render';
			$fetch_error = $internal['error'];
			$rest        = array_merge( $rest, $internal );
		}

		if ( empty( $assets ) ) {
			$capture     = $this->scan_via_inprocess_capture( $url );
			$assets      = $capture['assets'];
			$method      = empty( $method ) ? 'inprocess_capture' : $method . '+inprocess_capture';
			$fetch_error = $capture['error'] ?: $fetch_error;
			$rest        = array_merge( $rest, $capture );
		}

		if ( empty( $assets ) && ! $loopback_first ) {
			$internal    = $this->scan_via_internal_render( $url );
			$assets      = $internal['assets'];
			$method      = 'loopback_render';
			$fetch_error = $internal['error'];
			$rest        = array_merge( $rest, $internal );
		}

		if ( empty( $assets ) ) {
			$fallback    = ( new AssetCapture() )->capture_for_url( $url, true );
			$assets      = $fallback['assets'];
			$method      = 'registry_bootstrap';
			$rest['queues'] = $fallback['queues'];
			$fetch_error = $fallback['error'] ?: $fetch_error;
		}

		$meta = array(
			'registry_count'   => count( $assets ),
			'merged_count'     => count( $assets ),
			'http_code'        => $rest['http_code'] ?? 0,
			'fetch_error'      => $fetch_error,
			'timeout'          => $rest['timeout'] ?? 0,
			'bootstrap'        => 'registry_bootstrap' === $method,
			'queues'           => $rest['queues'] ?? array(),
			'analyze_url'      => $this->get_analyze_url( $url ),
			'method'           => $method,
			'site_front_scan'  => UrlHelper::is_site_front_url( $url ),
			'page_on_front_id' => (int) get_option( 'page_on_front' ),
		);

		if ( ! empty( $assets ) && in_array( $method, array( 'inprocess_capture', 'loopback_render' ), true ) ) {
			$debug = 'internal_render';
		} elseif ( ! empty( $assets ) ) {
			$debug = 'registry_bootstrap';
		} else {
			$debug = 'scan_empty';
		}

		$result = array(
			'assets'      => $assets,
			'html_assets' => array(),
			'debug'       => $debug,
			'meta'        => $meta,
		);

		if ( ! empty( $assets ) && 'registry_bootstrap' !== $method ) {
			\set_transient( $cache_key, $result, \AssetControl\Helpers\Cache::scan_ttl() );
		}

		return $result;
	}

	/**
	 * In-process capture (no nested REST — avoids corrupt JSON on /assets).
	 *
	 * @return array{assets: array<int, array<string, mixed>>, queues: array<string, mixed>, http_code: int, error: string, timeout: int}
	 */
	private function scan_via_inprocess_capture( string $url ): array {
		$empty = array(
			'assets'    => array(),
			'queues'    => array(),
			'http_code' => 0,
			'error'     => '',
			'timeout'   => 0,
		);

		$buffer_level = OutputBuffer::start();

		try {
			$result = ( new AssetCapture() )->capture_for_url( $url, true );
		} catch ( \Throwable $e ) {
			$empty['error'] = $e->getMessage();
			return $empty;
		} finally {
			OutputBuffer::end_clean( $buffer_level );
		}

		$assets = is_array( $result['assets'] ?? null ) ? $result['assets'] : array();
		$queues = is_array( $result['queues'] ?? null ) ? $result['queues'] : array();
		$error  = (string) ( $result['error'] ?? '' );

		if ( empty( $assets ) ) {
			$empty['queues'] = $queues;
			$empty['error']  = '' !== $error ? $error : 'empty_capture';
			return $empty;
		}

		return array(
			'assets'    => $assets,
			'queues'    => $queues,
			'http_code' => 200,
			'error'     => '',
			'timeout'   => 0,
		);
	}

	/**
	 * Loopback internal render — full WP page load, JSON-only response.
	 *
	 * @return array{assets: array<int, array<string, mixed>>, queues: array<string, mixed>, http_code: int, error: string, timeout: int}
	 */
	private function scan_via_internal_render( string $url ): array {
		$empty = array(
			'assets'    => array(),
			'queues'    => array(),
			'http_code' => 0,
			'error'     => '',
			'timeout'   => 0,
		);

		$analyze_url = $this->get_analyze_url( $url );
		$timeout     = (int) \apply_filters( 'assetpilot_analyze_timeout', 20 );

		$args = \apply_filters(
			'assetpilot_analyze_request_args',
			array(
				'timeout'     => $timeout,
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter for local HTTPS fetches.
				'sslverify'   => \apply_filters( 'https_local_ssl_verify', false ),
				'redirection' => 3,
				'headers'     => array(
					'User-Agent' => 'assetpilot-Analyze/1.0',
				),
			),
			$url
		);

		$response = \wp_remote_get( $analyze_url, $args );

		if ( \is_wp_error( $response ) ) {
			$response = $this->maybe_retry_localhost( $analyze_url, $args, $response );
		}

		if ( \is_wp_error( $response ) ) {
			$empty['error']   = $response->get_error_message();
			$empty['timeout'] = $timeout;
			return $empty;
		}

		$code = (int) \wp_remote_retrieve_response_code( $response );
		$body = (string) \wp_remote_retrieve_body( $response );

		$empty['http_code'] = $code;
		$empty['timeout']   = $timeout;

		if ( $code < 200 || $code >= 400 ) {
			$empty['error'] = 'http_' . $code;
			return $empty;
		}

		$parsed = $this->parse_analyze_body( $body );
		if ( empty( $parsed['assets'] ) ) {
			$empty['error'] = 'invalid_analyze_response';
			return $empty;
		}

		$parsed['http_code'] = $code;
		$parsed['error']     = '';
		$parsed['timeout']   = $timeout;

		return $parsed;
	}

	/**
	 * @return array{assets: array<int, array<string, mixed>>, queues: array<string, mixed>}
	 */
	private function parse_analyze_body( string $body ): array {
		$body = trim( $body );

		if ( '' === $body ) {
			return array(
				'assets' => array(),
				'queues' => array(),
			);
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) && str_contains( $body, '"assets"' ) ) {
			if ( preg_match( '/\{[\s\S]*"assets"[\s\S]*\}/', $body, $json_match ) ) {
				$data = json_decode( $json_match[0], true );
			}
		}

		if ( is_array( $data ) && isset( $data['assets'] ) && is_array( $data['assets'] ) ) {
			return array(
				'assets' => $data['assets'],
				'queues' => is_array( $data['queues'] ?? null ) ? $data['queues'] : array(),
			);
		}

		// Legacy HTML marker fallback.
		return array(
			'assets' => $this->parse_response( $body ),
			'queues' => array(),
		);
	}

	/**
	 * @return array{assets: array<int, array<string, mixed>>, html_assets: array<int, array<string, mixed>>, debug: string, meta: array<string, mixed>}
	 */
	private function empty_result( string $debug ): array {
		return array(
			'assets'      => array(),
			'html_assets' => array(),
			'debug'       => $debug,
			'meta'        => array(),
		);
	}

	private function normalize_url( string $url ): string {
		return ( new AssetHandleResolver() )->sanitize_url( trim( rawurldecode( $url ) ) );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function parse_response( string $html ): array {
		$pattern = '/' . preg_quote( self::MARKER_START, '/' ) . '(.+?)' . preg_quote( self::MARKER_END, '/' ) . '/s';

		if ( ! preg_match( $pattern, $html, $matches ) ) {
			return array();
		}

		$decoded = base64_decode( $matches[1], true );
		if ( false === $decoded ) {
			return array();
		}

		$data = json_decode( $decoded, true );
		if ( ! is_array( $data ) || empty( $data['assets'] ) || ! is_array( $data['assets'] ) ) {
			return array();
		}

		return $data['assets'];
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>|\WP_Error
	 */
	private function maybe_retry_localhost( string $url, array $args, $error ) {
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

		$retry_args = $args;
		$retry_args['headers'] = array_merge(
			(array) ( $args['headers'] ?? array() ),
			array( 'Host' => $host )
		);

		return \wp_remote_get( $retry_url, $retry_args );
	}

	private function is_valid_scan_url( string $url ): bool {
		if ( \wp_http_validate_url( $url ) ) {
			return true;
		}

		$parsed = \wp_parse_url( $url );

		return ! empty( $parsed['host'] ) && in_array( $parsed['scheme'] ?? 'http', array( 'http', 'https' ), true );
	}

	private function is_same_site( string $url_a, string $url_b ): bool {
		$a = \wp_parse_url( $url_a );
		$b = \wp_parse_url( $url_b );

		return ( $a['host'] ?? '' ) === ( $b['host'] ?? '' );
	}
}
