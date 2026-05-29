<?php
/**
 * Elementor compatibility.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Compatibility;

defined( 'ABSPATH' ) || exit;
/**
 * Elementor frontend asset compatibility.
 */
final class Elementor {

	public function init(): void {
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return;
		}

		add_action( 'elementor/frontend/after_enqueue_scripts', array( $this, 'mark_elementor_assets' ) );
	}

	public function mark_elementor_assets(): void {
		// Elementor assets are available in $wp_scripts after this hook.
		do_action( 'assetpilot_elementor_assets_registered' );
	}
}
