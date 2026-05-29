<?php
/**
 * Disables scripts and styles by handle or URL.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets\Runtime\Actions;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\AssetHandleResolver;
use AssetControl\Assets\Runtime\BulkRuleTargets;
use AssetControl\Assets\Runtime\RuntimeContext;
use AssetControl\Helpers\Logger;

/**
 * Dequeues and deregisters matched assets.
 */
final class DisableAssetAction implements RuntimeActionInterface {

	public function __construct(
		private readonly AssetHandleResolver $handle_resolver = new AssetHandleResolver()
	) {}

	public function action_type(): string {
		return 'disable';
	}

	public function register_hooks(): void {
		// Applied synchronously in execute().
	}

	/**
	 * @param array<int, array<string, mixed>> $rules
	 */
	public function execute( array $rules, RuntimeContext $context ): void {
		foreach ( $rules as $rule ) {
			foreach ( BulkRuleTargets::expand( $rule ) as $target ) {
				$this->disable_target( $rule, $target, $context );
			}
		}
	}

	/**
	 * @param array<string, mixed> $rule
	 * @param array{handle: string, type: string, src: string} $target
	 */
	private function disable_target( array $rule, array $target, RuntimeContext $context ): void {
		$handle = (string) ( $target['handle'] ?? '' );
		$type   = (string) ( $target['type'] ?? 'script' );
		$config = is_array( $rule['action_config'] ) ? $rule['action_config'] : array();
		$src    = $this->handle_resolver->sanitize_url( (string) ( $target['src'] ?? $config['src'] ?? $config['href'] ?? '' ) );

		if ( '' === $handle ) {
			return;
		}

		if ( '' === $src && str_contains( $handle, '/' ) ) {
			$src = $handle;
		}

		$resolved = $this->handle_resolver->resolve( $handle, $src, $type );
		if ( null !== $resolved ) {
			$handle = $resolved;
		}

		if ( '' !== $src ) {
			$context->add_url_disable_rule( $src, $type );
		}

		try {
			if ( 'script' === $type ) {
				wp_dequeue_script( $handle );
				wp_deregister_script( $handle );
			} elseif ( 'style' === $type ) {
				wp_dequeue_style( $handle );
				wp_deregister_style( $handle );
			}
			Logger::log(
				'applied',
				'Disabled asset',
				array(
					'handle'   => $handle,
					'type'     => $type,
					'resolved' => $resolved,
					'src'      => $src,
				)
			);
		} catch ( \Throwable $e ) {
			Logger::log( 'error', $e->getMessage(), array( 'handle' => $handle ) );
		}
	}
}
