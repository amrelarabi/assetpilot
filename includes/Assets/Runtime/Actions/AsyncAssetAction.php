<?php
/**
 * Adds async to script tags.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets\Runtime\Actions;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\Runtime\BulkRuleTargets;
use AssetControl\Assets\Runtime\RuntimeContext;
use AssetControl\Assets\Runtime\ScriptTagAttributeInjector;
use AssetControl\Helpers\Logger;

/**
 * Async attribute via script_loader_tag (after defer handler).
 */
final class AsyncAssetAction implements RuntimeActionInterface {

	/** @var array<int, array<string, mixed>> */
	private array $rules = array();

	private ?RuntimeContext $context = null;

	public function action_type(): string {
		return 'async';
	}

	public function register_hooks(): void {
		add_filter( 'script_loader_tag', array( $this, 'modify_tag' ), 11, 3 );
	}

	/**
	 * @param array<int, array<string, mixed>> $rules
	 */
	public function execute( array $rules, RuntimeContext $context ): void {
		$this->rules   = $rules;
		$this->context = $context;
	}

	public function modify_tag( string $tag, string $handle, string $src ): string {
		if ( str_contains( $tag, 'defer' ) || str_contains( $tag, 'async' ) ) {
			return $tag;
		}
		if ( ! $this->should_apply( $handle, $src ) ) {
			return $tag;
		}
		Logger::log( 'applied', 'Async script', array( 'handle' => $handle ) );
		return ScriptTagAttributeInjector::inject( $tag, 'async' );
	}

	private function should_apply( string $handle, string $src ): bool {
		if ( ! $this->context ) {
			return false;
		}
		foreach ( $this->rules as $rule ) {
			if ( BulkRuleTargets::matches_handle( $rule, $handle, 'script', $this->context ) ) {
				return true;
			}
			$config   = is_array( $rule['action_config'] ) ? $rule['action_config'] : array();
			$rule_src = (string) ( $config['src'] ?? $config['href'] ?? '' );
			if ( '' !== $rule_src && '' !== $src && $this->context->urls_match( $rule_src, $src ) ) {
				return true;
			}
		}
		return false;
	}
}
