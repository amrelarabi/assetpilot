<?php
/**
 * Back-compat facade for safe mode (see SafeModeManager).
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Core;

defined( 'ABSPATH' ) || exit;
/**
 * @deprecated Use SafeModeManager for new code.
 */
final class SafeMode {

	public function init(): void {
		( new SafeModeManager() )->init();
	}

	/**
	 * Manual safe mode (cookie / ASSETPILOT_SAFE_MODE constant).
	 */
	public static function is_active(): bool {
		return SafeModeManager::is_manual_enabled();
	}

	/**
	 * Manual safe mode or automatic runtime suspension.
	 */
	public static function is_runtime_disabled(): bool {
		return SafeModeManager::is_runtime_disabled();
	}
}
