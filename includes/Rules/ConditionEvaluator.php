<?php
/**
 * Conditions engine.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Rules;

defined( 'ABSPATH' ) || exit;
use AssetControl\Helpers\Cache;
use AssetControl\Rules\Conditions\ConditionHandlerRegistry;

/**
 * Evaluates rule condition groups against the current request context.
 */
final class ConditionEvaluator {

	public function __construct(
		private readonly ConditionHandlerRegistry $registry = new ConditionHandlerRegistry()
	) {}

	/**
	 * @param array<string, mixed> $conditions
	 */
	public function matches( array $conditions ): bool {
		$key = Cache::condition_match_key( $conditions, ConditionContext::fingerprint() );

		return Cache::request(
			$key,
			function () use ( $conditions ): bool {
				return $this->evaluate( $conditions );
			}
		);
	}

	/**
	 * @param array<string, mixed> $conditions
	 */
	private function evaluate( array $conditions ): bool {
		if ( empty( $conditions ) || ! empty( $conditions['global'] ) ) {
			return true;
		}

		if ( isset( $conditions['scope'] ) && 'global' === $conditions['scope'] ) {
			return true;
		}

		foreach ( $this->registry->handlers() as $handler ) {
			if ( $handler->is_active( $conditions ) && ! $handler->matches( $conditions ) ) {
				return false;
			}
		}

		return true;
	}
}
