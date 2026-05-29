<?php
/**
 * Recommendation provider contract.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Recommendations;

defined( 'ABSPATH' ) || exit;
/**
 * Each provider inspects scan context and returns zero or more suggestions.
 */
interface RecommendationProviderInterface {

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function recommend( RecommendationContext $context ): array;
}
