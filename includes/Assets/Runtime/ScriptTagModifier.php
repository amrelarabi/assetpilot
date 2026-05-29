<?php
/**
 * Modifies script tags for defer/async.
 *
 * @deprecated Use DeferAssetAction and AsyncAssetAction via RuntimePipeline.
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets\Runtime;

defined( 'ABSPATH' ) || exit;
use AssetControl\Helpers\Logger;

/**
 * Adds defer/async attributes via script_loader_tag filter.
 */
final class ScriptTagModifier {

	private RuntimeEngine $engine;

	public function init( RuntimeEngine $engine ): void {
		$this->engine = $engine;
		add_filter( 'script_loader_tag', array( $this, 'modify_tag' ), 10, 3 );
	}

	public function modify_tag( string $tag, string $handle, string $src ): string {
		$grouped = $this->engine->get_grouped_rules();

		$apply_defer = $this->should_apply( $handle, $grouped['defer'] ?? array() );
		$apply_async = $this->should_apply( $handle, $grouped['async'] ?? array() );

		// Defer takes precedence — never apply both on the same tag.
		if ( $apply_defer ) {
			$tag = $this->add_attribute( $tag, 'defer' );
			Logger::log( 'applied', 'Deferred script', array( 'handle' => $handle ) );
		} elseif ( $apply_async ) {
			$tag = $this->add_attribute( $tag, 'async' );
			Logger::log( 'applied', 'Async script', array( 'handle' => $handle ) );
		}

		return $tag;
	}

	/**
	 * @param array<int, array<string, mixed>> $rules
	 */
	private function should_apply( string $handle, array $rules ): bool {
		foreach ( $rules as $rule ) {
			if ( 'script' !== ( $rule['asset_type'] ?? '' ) ) {
				continue;
			}
			if ( $this->engine->resolve_rule_handle( $rule ) === $handle ) {
				return true;
			}
		}
		return false;
	}

	private function add_attribute( string $tag, string $attr ): string {
		if ( str_contains( $tag, $attr ) ) {
			return $tag;
		}
		return str_replace( '<script ', '<script ' . $attr . ' ', $tag );
	}
}
