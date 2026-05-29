<?php
/**
 * Plugin activation.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Core;

defined( 'ABSPATH' ) || exit;
use AssetControl\Database\Schema;

/**
 * Runs on plugin activation.
 */
final class Activator {

	public static function activate(): void {
		Schema::create_tables();
		flush_rewrite_rules();
	}
}
