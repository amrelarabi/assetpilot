<?php
/**
 * Verifies runtime rules against rendered frontend HTML.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Verification;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\AssetHandleResolver;
use AssetControl\Assets\AssetUrlResolver;
use AssetControl\Assets\Runtime\RuntimeContext;
use AssetControl\Database\RulesRepository;

/**
 * Compares expected rule effects to parsed HTML output.
 */
final class RuntimeVerificationService {

	public const STATUS_VERIFIED  = 'verified';
	public const STATUS_PARTIAL   = 'partial';
	public const STATUS_FAILED    = 'failed';
	public const STATUS_SKIPPED   = 'skipped';
	public const STATUS_UNAVAILABLE = 'unavailable';

	public function __construct(
		private readonly HTMLVerificationParser $parser = new HTMLVerificationParser(),
		private readonly RulesRepository $rules_repository = new RulesRepository(),
		private readonly AssetHandleResolver $handle_resolver = new AssetHandleResolver(),
		private readonly AssetUrlResolver $url_resolver = new AssetUrlResolver()
	) {}

	/**
	 * @param array<string, mixed> $args Optional rule_id, asset_handle, asset_type filters.
	 * @return array{url: string, error: string, results: array<int, array<string, mixed>>}
	 */
	public function verify_url( string $url, array $args = array() ): array {
		$url = esc_url_raw( $url ) ?: trim( $url );
		if ( '' === $url ) {
			$url = home_url( '/' );
		}

		$fetch = $this->parser->fetch_and_parse( $url );
		if ( '' !== $fetch['error'] ) {
			return array(
				'url'     => $url,
				'error'   => $fetch['error'],
				'results' => array(),
			);
		}

		$rules = $this->filter_rules( $args );
		$results = array();

		foreach ( $rules as $rule ) {
			$results[] = $this->verify_rule( $rule, $fetch['parsed'], $url );
		}

		return array(
			'url'     => $url,
			'error'   => '',
			'results' => $results,
		);
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<int, array<string, mixed>>
	 */
	private function filter_rules( array $args ): array {
		$rules = $this->rules_repository->all_cached();

		if ( ! empty( $args['rule_id'] ) ) {
			$id = (int) $args['rule_id'];
			$rules = array_filter( $rules, static fn( array $r ): bool => (int) ( $r['id'] ?? 0 ) === $id );
		}

		if ( ! empty( $args['asset_handle'] ) && ! empty( $args['asset_type'] ) ) {
			$handle = (string) $args['asset_handle'];
			$type   = (string) $args['asset_type'];
			$rules  = array_filter(
				$rules,
				static fn( array $r ): bool => ( $r['asset_handle'] ?? '' ) === $handle
					&& ( $r['asset_type'] ?? '' ) === $type
			);
		}

		return array_values( $rules );
	}

	/**
	 * @param array<string, mixed>              $rule
	 * @param array<string, mixed>              $parsed
	 * @return array<string, mixed>
	 */
	public function verify_rule( array $rule, array $parsed, string $page_url ): array {
		$rule_id = (int) ( $rule['id'] ?? 0 );
		$action  = (string) ( $rule['action_type'] ?? '' );

		if ( empty( $rule['enabled'] ) ) {
			return $this->result(
				$rule_id,
				self::STATUS_SKIPPED,
				__( 'Rule is disabled.', 'assetpilot' ),
				'',
				''
			);
		}

		$context = new RuntimeContext();
		$handle  = $context->resolve_rule_handle( $rule );
		$type    = (string) ( $rule['asset_type'] ?? 'script' );
		$src     = $this->rule_src( $rule, $handle, $type );

		return match ( $action ) {
			'disable'        => $this->verify_disable( $rule_id, $handle, $type, $src, $parsed ),
			'defer'          => $this->verify_defer( $rule_id, $handle, $src, $parsed ),
			'async'          => $this->verify_async( $rule_id, $handle, $src, $parsed ),
			'preload'        => $this->verify_preload( $rule_id, $rule, $parsed, $page_url ),
			'fetchpriority'  => $this->verify_fetchpriority( $rule_id, $rule, $parsed ),
			default          => $this->result(
				$rule_id,
				self::STATUS_SKIPPED,
				__( 'Unknown action type.', 'assetpilot' ),
				'',
				''
			),
		};
	}

	/**
	 * @param array<string, mixed> $parsed
	 * @return array<string, mixed>
	 */
	private function verify_disable( int $rule_id, string $handle, string $type, string $src, array $parsed ): array {
		$found_handle = false;
		$found_src    = false;

		if ( 'script' === $type ) {
			foreach ( $parsed['scripts'] ?? array() as $script ) {
				if ( $this->handles_match( $handle, (string) ( $script['handle'] ?? '' ) ) ) {
					$found_handle = true;
				}
				if ( '' !== $src && $this->urls_match( $src, (string) ( $script['src'] ?? '' ) ) ) {
					$found_src = true;
				}
			}
		} elseif ( 'style' === $type ) {
			foreach ( $parsed['styles'] ?? array() as $style ) {
				if ( $this->handles_match( $handle, (string) ( $style['handle'] ?? '' ) ) ) {
					$found_handle = true;
				}
				if ( '' !== $src && $this->urls_match( $src, (string) ( $style['href'] ?? '' ) ) ) {
					$found_src = true;
				}
			}
		} else {
			if ( '' !== $src ) {
				foreach ( array_merge( $parsed['scripts'] ?? array(), $parsed['styles'] ?? array() ) as $row ) {
					$url = (string) ( $row['src'] ?? $row['href'] ?? '' );
					if ( $this->urls_match( $src, $url ) ) {
						$found_src = true;
					}
				}
			}
		}

		if ( $found_handle && $found_src ) {
			return $this->result(
				$rule_id,
				self::STATUS_FAILED,
				__( 'Asset still appears in page HTML.', 'assetpilot' ),
				__( 'Not present in HTML', 'assetpilot' ),
				$handle . ' / ' . $src
			);
		}

		if ( $found_handle || $found_src ) {
			return $this->result(
				$rule_id,
				self::STATUS_PARTIAL,
				__( 'Asset partially removed (handle or URL still detected).', 'assetpilot' ),
				__( 'Fully removed', 'assetpilot' ),
				$found_handle ? $handle : $src
			);
		}

		return $this->result(
			$rule_id,
			self::STATUS_VERIFIED,
			__( 'Disable verified — asset is not present in page HTML.', 'assetpilot' ),
			__( 'Removed from output', 'assetpilot' ),
			__( 'Not in HTML', 'assetpilot' )
		);
	}

	/**
	 * @param array<string, mixed> $parsed
	 * @return array<string, mixed>
	 */
	private function verify_defer( int $rule_id, string $handle, string $src, array $parsed ): array {
		$script = $this->find_script( $handle, $src, $parsed );
		if ( null === $script ) {
			return $this->result(
				$rule_id,
				self::STATUS_PARTIAL,
				__( 'Script not found in HTML (may be disabled or not loaded on this URL).', 'assetpilot' ),
				'defer',
				__( 'missing', 'assetpilot' )
			);
		}
		if ( ! empty( $script['defer'] ) ) {
			return $this->result( $rule_id, self::STATUS_VERIFIED, __( 'defer attribute present.', 'assetpilot' ), 'defer', 'defer' );
		}
		return $this->result( $rule_id, self::STATUS_FAILED, __( 'defer attribute missing on script tag.', 'assetpilot' ), 'defer', __( 'not set', 'assetpilot' ) );
	}

	/**
	 * @param array<string, mixed> $parsed
	 * @return array<string, mixed>
	 */
	private function verify_async( int $rule_id, string $handle, string $src, array $parsed ): array {
		$script = $this->find_script( $handle, $src, $parsed );
		if ( null === $script ) {
			return $this->result(
				$rule_id,
				self::STATUS_PARTIAL,
				__( 'Script not found in HTML.', 'assetpilot' ),
				'async',
				__( 'missing', 'assetpilot' )
			);
		}
		if ( ! empty( $script['async'] ) ) {
			return $this->result( $rule_id, self::STATUS_VERIFIED, __( 'async attribute present.', 'assetpilot' ), 'async', 'async' );
		}
		return $this->result( $rule_id, self::STATUS_FAILED, __( 'async attribute missing on script tag.', 'assetpilot' ), 'async', __( 'not set', 'assetpilot' ) );
	}

	/**
	 * @param array<string, mixed> $rule
	 * @param array<string, mixed> $parsed
	 * @return array<string, mixed>
	 */
	private function verify_preload( int $rule_id, array $rule, array $parsed, string $page_url ): array {
		unset( $page_url );
		$config = is_array( $rule['action_config'] ) ? $rule['action_config'] : array();
		$href   = (string) ( $config['href'] ?? $config['src'] ?? '' );
		if ( '' === $href ) {
			$href = $this->url_resolver->resolve_handle( (string) $rule['asset_handle'], (string) $rule['asset_type'] );
		}

		foreach ( $parsed['preloads'] ?? array() as $preload ) {
			if ( $this->urls_match( $href, (string) ( $preload['href'] ?? '' ) ) ) {
				return $this->result(
					$rule_id,
					self::STATUS_VERIFIED,
					__( 'Preload link found in HTML.', 'assetpilot' ),
					'rel=preload',
					$href
				);
			}
		}

		return $this->result(
			$rule_id,
			self::STATUS_FAILED,
			__( 'Preload link not found in page HTML.', 'assetpilot' ),
			'rel=preload ' . $href,
			__( 'not found', 'assetpilot' )
		);
	}

	/**
	 * @param array<string, mixed> $rule
	 * @param array<string, mixed> $parsed
	 * @return array<string, mixed>
	 */
	private function verify_fetchpriority( int $rule_id, array $rule, array $parsed ): array {
		$config   = is_array( $rule['action_config'] ) ? $rule['action_config'] : array();
		$expected = strtolower( (string) ( $config['value'] ?? $config['fetchpriority'] ?? 'high' ) );
		$type     = (string) ( $rule['asset_type'] ?? 'script' );
		$handle   = (string) ( $rule['asset_handle'] ?? '' );
		$src      = $this->rule_src( $rule, $handle, $type );

		if ( 'script' === $type ) {
			$script = $this->find_script( $handle, $src, $parsed );
			if ( $script && $expected === ( $script['fetchpriority'] ?? '' ) ) {
				return $this->result( $rule_id, self::STATUS_VERIFIED, __( 'fetchpriority on script matches.', 'assetpilot' ), $expected, $expected );
			}
			if ( $script ) {
				return $this->result(
					$rule_id,
					self::STATUS_FAILED,
					__( 'fetchpriority on script does not match.', 'assetpilot' ),
					$expected,
					(string) ( $script['fetchpriority'] ?? '' )
				);
			}
		}

		foreach ( $parsed['images'] ?? array() as $image ) {
			if ( $expected === ( $image['fetchpriority'] ?? '' ) ) {
				return $this->result( $rule_id, self::STATUS_VERIFIED, __( 'fetchpriority on image found.', 'assetpilot' ), $expected, $expected );
			}
		}

		return $this->result(
			$rule_id,
			self::STATUS_PARTIAL,
			__( 'fetchpriority not detected in HTML for this rule.', 'assetpilot' ),
			$expected,
			__( 'not found', 'assetpilot' )
		);
	}

	/**
	 * @param array<string, mixed> $parsed
	 * @return array<string, mixed>|null
	 */
	private function find_script( string $handle, string $src, array $parsed ): ?array {
		foreach ( $parsed['scripts'] ?? array() as $script ) {
			if ( $this->handles_match( $handle, (string) ( $script['handle'] ?? '' ) ) ) {
				return $script;
			}
			if ( '' !== $src && $this->urls_match( $src, (string) ( $script['src'] ?? '' ) ) ) {
				return $script;
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $rule
	 */
	private function rule_src( array $rule, string $handle, string $type ): string {
		$config = is_array( $rule['action_config'] ) ? $rule['action_config'] : array();
		$src    = $this->handle_resolver->sanitize_url( (string) ( $config['src'] ?? $config['href'] ?? '' ) );
		if ( '' === $src ) {
			$src = $this->url_resolver->resolve_handle( $handle, $type ) ?: '';
		}
		return $src;
	}

	private function handles_match( string $a, string $b ): bool {
		return '' !== $a && '' !== $b && $a === $b;
	}

	private function urls_match( string $a, string $b ): bool {
		if ( '' === $a || '' === $b ) {
			return false;
		}
		$na = strtok( set_url_scheme( $a, 'relative' ), '?' ) ?: $a;
		$nb = strtok( set_url_scheme( $b, 'relative' ), '?' ) ?: $b;
		return $na === $nb || str_ends_with( $nb, $na ) || str_ends_with( $na, $nb );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function result( int $rule_id, string $status, string $message, string $expected, string $actual ): array {
		return array(
			'rule_id'  => $rule_id,
			'status'   => $status,
			'message'  => $message,
			'expected' => $expected,
			'actual'   => $actual,
		);
	}
}
