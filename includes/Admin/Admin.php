<?php
/**
 * Admin bootstrap.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Admin;

defined( 'ABSPATH' ) || exit;
use AssetControl\Database\LogRepository;
use AssetControl\Core\RuntimeHealthMonitor;
use AssetControl\Core\SafeMode;
use AssetControl\Core\SafeModeManager;
use AssetControl\Database\Schema;

/**
 * Admin area initialization.
 */
final class Admin {

	public function init(): void {
		Schema::maybe_upgrade();

		( new Menu() )->init();
		( new Redirects() )->init();
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! str_contains( $hook, 'assetpilot' ) ) {
			return;
		}

		$asset_file = ASSETPILOT_PLUGIN_DIR . 'assets/build/admin.asset.php';
		$deps       = array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' );
		$version    = ASSETPILOT_VERSION;

		if ( file_exists( $asset_file ) ) {
			$asset   = require $asset_file;
			$deps    = $this->normalize_script_dependencies( $asset['dependencies'] ?? $deps );
			$version = $asset['version'] ?? $version;
		}

		wp_enqueue_script(
			'assetpilot-admin',
			ASSETPILOT_PLUGIN_URL . 'assets/build/admin.js',
			$deps,
			$version,
			true
		);

		$style_path = ASSETPILOT_PLUGIN_DIR . 'assets/build/style-admin.css';
		if ( file_exists( $style_path ) ) {
			wp_enqueue_style(
				'assetpilot-admin',
				ASSETPILOT_PLUGIN_URL . 'assets/build/style-admin.css',
				array( 'wp-components' ),
				$version
			);
		}

		$settings_page_url = admin_url( 'admin.php?page=assetpilot-settings' );

		wp_localize_script(
			'assetpilot-admin',
			'assetpilotAdmin',
			array(
				'apiUrl'    => rest_url( 'assetpilot/v1' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'pluginUrl' => ASSETPILOT_PLUGIN_URL,
				'homeUrl'   => home_url( '/' ),
				'adminUrl'    => admin_url(),
				'rulesPageUrl'  => admin_url( 'admin.php?page=assetpilot-rules' ),
				'createPageUrl' => admin_url( 'admin.php?page=assetpilot-create' ),
				'assetsPageUrl' => admin_url( 'admin.php?page=assetpilot-assets' ),
				'scanHistoryPageUrl' => admin_url( 'admin.php?page=assetpilot-scan-history' ),
				'logsPageUrl'        => admin_url( 'admin.php?page=assetpilot-logs' ),
				'graphPageUrl'              => admin_url( 'admin.php?page=assetpilot-graph' ),
				'recommendationsPageUrl'    => admin_url( 'admin.php?page=assetpilot-recommendations' ),
				'settingsPageUrl'    => $settings_page_url,
				'logMaxRows'         => LogRepository::MAX_ROWS,
				'safeModeActive'       => SafeMode::is_active(),
				'runtimeDisabled'      => SafeMode::is_runtime_disabled(),
				'runtimeAutoSuspended' => SafeModeManager::is_auto_suspended(),
				'runtimeSuspendInfo'   => SafeModeManager::get_suspend_info(),
				'runtimeHealth'        => RuntimeHealthMonitor::get_status(),
				'safeModeRecoveryUrl'  => SafeModeManager::recovery_url(),
				'resumeRuntimeUrl'     => SafeModeManager::resume_runtime_url(),
				'safeModeEnableUrl'    => add_query_arg( 'assetpilot-safe-mode', '1', $settings_page_url ),
				'safeModeDisableUrl'   => add_query_arg( 'assetpilot-safe-mode', '0', $settings_page_url ),
				'safeModeUrl'          => add_query_arg( 'assetpilot-safe-mode', SafeMode::is_active() ? '0' : '1', $settings_page_url ),
				'postId'           => 0,
				'postType'         => '',
				'conditionOptions' => ConditionOptions::get(),
			)
		);

		wp_set_script_translations( 'assetpilot-admin', 'assetpilot', ASSETPILOT_PLUGIN_DIR . 'languages' );
	}

	public function enqueue_editor_assets(): void {
		$asset_file = ASSETPILOT_PLUGIN_DIR . 'assets/build/editor.asset.php';
		$deps       = array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' );
		$version    = ASSETPILOT_VERSION;

		if ( file_exists( $asset_file ) ) {
			$asset   = require $asset_file;
			$deps    = $this->normalize_script_dependencies( $asset['dependencies'] ?? $deps );
			$version = $asset['version'] ?? $version;
		}

		wp_enqueue_script(
			'assetpilot-editor',
			ASSETPILOT_PLUGIN_URL . 'assets/build/editor.js',
			$deps,
			$version,
			true
		);

		$screen = get_current_screen();
		$post_id = 0;
		$post_type = '';

		if ( $screen && 'post' === $screen->base ) {
			global $post;
			$post_id   = $post->ID ?? 0;
			$post_type = $post->post_type ?? '';
		}

		wp_localize_script(
			'assetpilot-editor',
			'assetpilotAdmin',
			array(
				'apiUrl'   => rest_url( 'assetpilot/v1' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'postId'   => $post_id,
				'postType' => $post_type,
			)
		);
	}

	/**
	 * wp-scripts may list react/react-dom handles that are not registered on older WordPress versions.
	 *
	 * @param array<int, string> $deps Script handles from *.asset.php.
	 * @return array<int, string>
	 */
	private function normalize_script_dependencies( array $deps ): array {
		$wp_scripts = wp_scripts();

		foreach ( array( 'react', 'react-dom', 'react-jsx-runtime' ) as $handle ) {
			if ( ! isset( $wp_scripts->registered[ $handle ] ) ) {
				$deps = array_values( array_diff( $deps, array( $handle ) ) );
			}
		}

		if ( ! in_array( 'wp-element', $deps, true ) ) {
			$deps[] = 'wp-element';
		}

		return array_values( array_unique( $deps ) );
	}
}
