<?php
/**
 * Frontend admin bar shortcuts for AssetPilot.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Frontend;

defined( 'ABSPATH' ) || exit;
use AssetControl\Core\SafeMode;

/**
 * Adds analyze / create-rule links while viewing the public site.
 */
final class AdminBar {

	public function init(): void {
		add_action( 'admin_bar_menu', array( $this, 'register_menu' ), 100 );
	}

	/**
	 * @param \WP_Admin_Bar $bar Admin bar instance.
	 */
	public function register_menu( $bar ): void {
		if ( ! is_admin_bar_showing() || is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$page_url = $this->get_current_page_url();

		$bar->add_node(
			array(
				'id'    => 'assetpilot-assetpilot',
				'title' => esc_html__( 'AssetPilot', 'assetpilot' ),
				'href'  => admin_url( 'admin.php?page=assetpilot' ),
				'meta'  => array(
					'class' => 'assetpilot-admin-bar-root',
				),
			)
		);

		$bar->add_node(
			array(
				'parent' => 'assetpilot-assetpilot',
				'id'     => 'assetpilot-analyze-page',
				'title'  => esc_html__( 'Analyze this page', 'assetpilot' ),
				'href'   => add_query_arg(
					array(
						'page'        => 'assetpilot-assets',
						'assetpilot_tab'    => 'analyze',
						'analyze_url' => $page_url,
					),
					admin_url( 'admin.php' )
				),
			)
		);

		$bar->add_node(
			array(
				'parent' => 'assetpilot-assetpilot',
				'id'     => 'assetpilot-create-for-page',
				'title'  => esc_html__( 'Manage assets on this page', 'assetpilot' ),
				'href'   => add_query_arg(
					array(
						'page'     => 'assetpilot-assets',
						'scan_url' => $page_url,
					),
					admin_url( 'admin.php' )
				),
			)
		);

		$bar->add_node(
			array(
				'parent' => 'assetpilot-assetpilot',
				'id'     => 'assetpilot-rules',
				'title'  => esc_html__( 'All rules', 'assetpilot' ),
				'href'   => admin_url( 'admin.php?page=assetpilot-rules' ),
			)
		);

		if ( SafeMode::is_runtime_disabled() ) {
			$settings_url = admin_url( 'admin.php?page=assetpilot-settings' );
			$title        = SafeMode::is_active()
				? __( 'Safe Mode ON', 'assetpilot' )
				: __( 'Runtime paused (errors)', 'assetpilot' );

			$bar->add_node(
				array(
					'parent' => 'assetpilot-assetpilot',
					'id'     => 'assetpilot-safe-mode',
					'title'  => esc_html( $title ),
					'href'   => $settings_url,
					'meta'   => array(
						'class' => 'assetpilot-admin-bar-safe-mode',
					),
				)
			);
		}

		$bar->add_node(
			array(
				'parent' => 'assetpilot-assetpilot',
				'id'     => 'assetpilot-assets',
				'title'  => esc_html__( 'Assets Explorer', 'assetpilot' ),
				'href'   => add_query_arg(
					array(
						'page'     => 'assetpilot-assets',
						'scan_url' => $page_url,
					),
					admin_url( 'admin.php' )
				),
			)
		);
	}

	private function get_current_page_url(): string {
		if ( is_singular() ) {
			$permalink = get_permalink();
			if ( is_string( $permalink ) && '' !== $permalink ) {
				return $permalink;
			}
		}

		return home_url( add_query_arg( array() ) );
	}
}
