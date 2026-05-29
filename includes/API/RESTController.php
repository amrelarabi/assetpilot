<?php
/**
 * REST API bootstrap.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\API;

defined( 'ABSPATH' ) || exit;
/**
 * Registers REST routes.
 */
final class RESTController {

	public const NAMESPACE = 'assetpilot/v1';

	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		( new AssetsEndpoint() )->register();
		( new CaptureAssetsEndpoint() )->register();
		( new RulesEndpoint() )->register();
		( new AnalyzerEndpoint() )->register();
		( new PageContextEndpoint() )->register();
		( new VerifyEndpoint() )->register();
		( new ScanHistoryEndpoint() )->register();
		( new SettingsEndpoint() )->register();
		( new LogsEndpoint() )->register();
		( new DependencyGraphEndpoint() )->register();
		( new RecommendationsEndpoint() )->register();
	}
}
