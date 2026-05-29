<?php
/**
 * Safe output-buffer helpers (paired ob_start / ob_end_clean).
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Pairs ob_start() with ob_end_clean() for buffers opened here.
 */
final class OutputBuffer {

	/**
	 * Start a buffer and return the level to restore in end_clean().
	 */
	public static function start(): int {
		$level = ob_get_level();
		ob_start();

		return $level;
	}

	/**
	 * Close buffers opened after start() using ob_end_clean().
	 *
	 * @param int $level_before_start Value returned by start().
	 */
	public static function end_clean( int $level_before_start ): void {
		while ( ob_get_level() > $level_before_start ) {
			ob_end_clean();
		}
	}
}
