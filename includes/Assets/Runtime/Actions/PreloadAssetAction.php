<?php
/**
 * Outputs preload link tags.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets\Runtime\Actions;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\Runtime\PreloadInjector;
use AssetControl\Assets\Runtime\RuntimeContext;

/**
 * Renders preload tags in wp_head.
 */
final class PreloadAssetAction implements RuntimeActionInterface {

	public function action_type(): string {
		return 'preload';
	}

	public function register_hooks(): void {
		// Output via RuntimeEngine::inject_preloads after pipeline run.
	}

	/**
	 * @param array<int, array<string, mixed>> $rules
	 */
	public function execute( array $rules, RuntimeContext $context ): void {
		unset( $context );
		( new PreloadInjector() )->output( $rules );
	}
}
