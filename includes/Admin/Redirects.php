<?php
/**
 * Admin URL redirects for navigation UX.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Redirects legacy or invalid admin entry points.
 */
final class Redirects {

	public function init(): void {
		add_action( 'admin_init', array( $this, 'maybe_redirect' ), 1 );
		add_filter( 'parent_file', array( $this, 'highlight_parent_menu' ) );
		add_filter( 'submenu_file', array( $this, 'highlight_submenu' ) );
	}

	public function maybe_redirect(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Admin routing; manage_options required for plugin pages.
		if ( ! is_admin() || ! isset( $_GET['page'] ) ) {
			return;
		}

		$page = sanitize_key( (string) wp_unslash( $_GET['page'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( 'assetpilot-analyzer' === $page ) {
			$this->redirect_to_assets_analyze_tab();
			return;
		}

		if ( 'assetpilot-create' === $page ) {
			$this->maybe_redirect_empty_create();
		}
	}

	private function maybe_redirect_empty_create(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Deep-link params for create-rule screen; no mutation.
		$handle = isset( $_GET['handle'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['handle'] ) ) : '';
		$type   = isset( $_GET['type'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['type'] ) ) : '';
		$bulk   = isset( $_GET['bulk'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['bulk'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' !== $handle && '' !== $type ) {
			return;
		}

		if ( '1' === $bulk ) {
			return;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => 'assetpilot-assets',
					'assetpilot_notice' => 'select_asset',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	private function redirect_to_assets_analyze_tab(): void {
		$args = array(
			'page'      => 'assetpilot-assets',
			'assetpilot_tab'  => 'analyze',
		);

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Preserves analyze URL when redirecting legacy menu slug.
		if ( ! empty( $_GET['analyze_url'] ) ) {
			$args['analyze_url'] = esc_url_raw( wp_unslash( (string) $_GET['analyze_url'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * @param string $parent_file Parent menu file.
	 */
	public function highlight_parent_menu( string $parent_file ): string {
		global $plugin_page;

		if ( in_array( $plugin_page, array( 'assetpilot-create', 'assetpilot-analyzer' ), true ) ) {
			return 'assetpilot';
		}

		return $parent_file;
	}

	/**
	 * @param string|null $submenu_file Submenu file.
	 */
	public function highlight_submenu( $submenu_file ) {
		global $plugin_page;

		if ( in_array( $plugin_page, array( 'assetpilot-create', 'assetpilot-analyzer' ), true ) ) {
			return 'assetpilot-assets';
		}

		return $submenu_file;
	}
}
