<?php
/**
 * Main plugin bootstrap.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Core;

defined( 'ABSPATH' ) || exit;
use AssetControl\Admin\Admin;
use AssetControl\API\RESTController;
use AssetControl\Frontend\AdminBar;
use AssetControl\Assets\Runtime\RuntimeEngine;
use AssetControl\Compatibility\CompatibilityLoader;
use AssetControl\Core\SafeMode;

/**
 * Singleton plugin orchestrator.
 */
final class Plugin {

	private static ?self $instance = null;

	private bool $initialized = false;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		if ( $this->initialized ) {
			return;
		}

		$this->initialized = true;

		\AssetControl\Database\Schema::maybe_upgrade();

		$scanner = new \AssetControl\Assets\FrontendScanner();
		$scanner->init();

		$is_asset_scan = \function_exists( 'wp_hash' ) && $scanner->is_analyze_request();

		if ( $is_asset_scan && ! defined( 'ASSETPILOT_ASSET_SCAN' ) ) {
			define( 'ASSETPILOT_ASSET_SCAN', true );
		}

		( new SafeMode() )->init();
		( new AdminBar() )->init();

		if ( is_admin() ) {
			( new Admin() )->init();
		}

		( new RESTController() )->init();

		if ( ! SafeMode::is_runtime_disabled() && ! $is_asset_scan ) {
			( new RuntimeEngine() )->init();
			RuntimeHealthMonitor::init();
		}

		( new CompatibilityLoader() )->init();
	}
}
