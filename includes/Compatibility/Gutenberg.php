<?php
/**
 * Gutenberg compatibility.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Compatibility;

defined( 'ABSPATH' ) || exit;
/**
 * Block editor compatibility helpers.
 */
final class Gutenberg {

	public function init(): void {
		add_filter( 'assetpilot_condition_context', array( $this, 'add_block_context' ) );
	}

	/**
	 * @param array<string, mixed> $context
	 * @return array<string, mixed>
	 */
	public function add_block_context( array $context ): array {
		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			$context['has_blocks'] = has_blocks( $post_id );
		}
		return $context;
	}
}
