<?php
/**
 * Shared state for a single runtime pipeline run.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets\Runtime;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\AssetHandleResolver;

/**
 * Holds matched rules and disable-by-URL index for one request.
 */
final class RuntimeContext {

	/** @var array<int, array<string, mixed>> */
	private array $matched_rules = array();

	/** @var array<string, array{src: string, type: string}> */
	private array $url_disable_rules = array();

	public function __construct(
		private readonly AssetHandleResolver $handle_resolver = new AssetHandleResolver()
	) {}

	/**
	 * @param array<int, array<string, mixed>> $rules
	 */
	public function set_matched_rules( array $rules ): void {
		$this->matched_rules = $rules;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function matched_rules(): array {
		return $this->matched_rules;
	}

	/**
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function group_by_action(): array {
		$grouped = array();
		foreach ( $this->matched_rules as $rule ) {
			$action = (string) ( $rule['action_type'] ?? '' );
			if ( '' === $action ) {
				continue;
			}
			if ( ! isset( $grouped[ $action ] ) ) {
				$grouped[ $action ] = array();
			}
			$grouped[ $action ][] = $rule;
		}
		return $grouped;
	}

	/**
	 * @return array<string, array{src: string, type: string}>
	 */
	public function url_disable_rules(): array {
		return $this->url_disable_rules;
	}

	public function add_url_disable_rule( string $src, string $type ): void {
		$key = $type . '|' . $this->normalize_url_for_match( $src );
		if ( isset( $this->url_disable_rules[ $key ] ) ) {
			return;
		}
		$this->url_disable_rules[ $key ] = array(
			'src'  => $src,
			'type' => $type,
		);
	}

	/**
	 * @param array<string, mixed> $rule
	 */
	public function resolve_rule_handle( array $rule ): string {
		$handle = (string) ( $rule['asset_handle'] ?? '' );
		$type   = (string) ( $rule['asset_type'] ?? 'script' );
		$config = is_array( $rule['action_config'] ?? null ) ? $rule['action_config'] : array();
		$src    = $this->handle_resolver->sanitize_url( (string) ( $config['src'] ?? $config['href'] ?? '' ) );

		return $this->handle_resolver->resolve( $handle, $src, $type ) ?? $handle;
	}

	public function urls_match( string $rule_src, string $loaded_src ): bool {
		$rule_src   = strtok( set_url_scheme( $rule_src, 'relative' ), '?' ) ?: $rule_src;
		$loaded_src = strtok( set_url_scheme( $loaded_src, 'relative' ), '?' ) ?: $loaded_src;

		return $rule_src === $loaded_src
			|| str_ends_with( $loaded_src, $rule_src )
			|| str_ends_with( $rule_src, $loaded_src );
	}

	private function normalize_url_for_match( string $url ): string {
		return strtok( \set_url_scheme( $url, 'relative' ), '?' ) ?: $url;
	}
}
