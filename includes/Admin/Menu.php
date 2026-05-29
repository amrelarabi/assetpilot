<?php
/**
 * Admin menu registration.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Admin;

defined( 'ABSPATH' ) || exit;
/**
 * Registers admin menu pages.
 */
final class Menu {

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_menu', array( $this, 'register_hidden_page_titles' ), 999 );
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'AssetPilot', 'assetpilot' ),
			__( 'AssetPilot', 'assetpilot' ),
			'manage_options',
			'assetpilot',
			array( $this, 'render_app' ),
			'dashicons-performance',
			58
		);

		// Overview.
		add_submenu_page(
			'assetpilot',
			__( 'Dashboard', 'assetpilot' ),
			__( 'Dashboard', 'assetpilot' ),
			'manage_options',
			'assetpilot',
			array( $this, 'render_app' )
		);

		// Optimize.
		add_submenu_page(
			'assetpilot',
			__( 'Assets', 'assetpilot' ),
			__( 'Assets', 'assetpilot' ),
			'manage_options',
			'assetpilot-assets',
			array( $this, 'render_app' )
		);

		add_submenu_page(
			'assetpilot',
			__( 'Rules', 'assetpilot' ),
			__( 'Rules', 'assetpilot' ),
			'manage_options',
			'assetpilot-rules',
			array( $this, 'render_app' )
		);

		add_submenu_page(
			'assetpilot',
			__( 'Recommendations', 'assetpilot' ),
			__( 'Recommendations', 'assetpilot' ),
			'manage_options',
			'assetpilot-recommendations',
			array( $this, 'render_app' )
		);

		// Tools.
		add_submenu_page(
			'assetpilot',
			__( 'Dependency Graph', 'assetpilot' ),
			__( 'Dependency Graph', 'assetpilot' ),
			'manage_options',
			'assetpilot-graph',
			array( $this, 'render_app' )
		);

		add_submenu_page(
			'assetpilot',
			__( 'Scan History', 'assetpilot' ),
			__( 'Scan History', 'assetpilot' ),
			'manage_options',
			'assetpilot-scan-history',
			array( $this, 'render_app' )
		);

		// System.
		add_submenu_page(
			'assetpilot',
			__( 'Settings', 'assetpilot' ),
			__( 'Settings', 'assetpilot' ),
			'manage_options',
			'assetpilot-settings',
			array( $this, 'render_app' )
		);

		add_submenu_page(
			'assetpilot',
			__( 'Debug Logs', 'assetpilot' ),
			__( 'Debug Logs', 'assetpilot' ),
			'manage_options',
			'assetpilot-logs',
			array( $this, 'render_app' )
		);

		// Hidden pages (deep links only).
		add_submenu_page(
			null,
			__( 'Create Rule', 'assetpilot' ),
			__( 'Create Rule', 'assetpilot' ),
			'manage_options',
			'assetpilot-create',
			array( $this, 'render_app' )
		);

		add_submenu_page(
			null,
			__( 'Page Analyzer', 'assetpilot' ),
			__( 'Page Analyzer', 'assetpilot' ),
			'manage_options',
			'assetpilot-analyzer',
			array( $this, 'render_app' )
		);
	}

	public function render_app(): void {
		echo '<div id="assetpilot-admin-root" class="wrap">';
		echo '<p class="assetpilot-boot-fallback">' . esc_html__( 'Loading AssetPilot…', 'assetpilot' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Hidden submenu pages (parent null) do not populate the global $title,
	 * which triggers strip_tags( null ) deprecation in admin-header.php on PHP 8.1+.
	 */
	public function register_hidden_page_titles(): void {
		$pages = array(
			'assetpilot-create'   => array( $this, 'set_create_page_title' ),
			'assetpilot-analyzer' => array( $this, 'set_analyzer_page_title' ),
		);

		foreach ( $pages as $slug => $callback ) {
			$hook = get_plugin_page_hookname( $slug, '' );
			if ( '' !== $hook ) {
				add_action( "load-{$hook}", $callback );
			}
		}
	}

	public function set_create_page_title(): void {
		global $title;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen title only; capability checked by menu registration.
		$bulk = isset( $_GET['bulk'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['bulk'] ) ) : '';
		if ( '1' === $bulk ) {
			$title = __( 'Bulk Rule', 'assetpilot' );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin screen title only.
		$rule_id = isset( $_GET['rule_id'] ) ? absint( $_GET['rule_id'] ) : 0;
		if ( $rule_id > 0 ) {
			$title = __( 'Edit Rule', 'assetpilot' );
			return;
		}

		$title = __( 'Create Rule', 'assetpilot' );
	}

	public function set_analyzer_page_title(): void {
		global $title;

		$title = __( 'Page Analyzer', 'assetpilot' );
	}
}
