<?php
/**
 * Plugin deactivation.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Core;

defined( 'ABSPATH' ) || exit;
use AssetControl\Helpers\Cache;

/**
 * Runs on plugin deactivation.
 */
final class Deactivator {

	public static function deactivate(): void {
		Cache::invalidate_rules();
		flush_rewrite_rules();
	}
}
