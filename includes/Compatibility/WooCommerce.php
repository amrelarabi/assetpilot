<?php
/**
 * WooCommerce compatibility.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Compatibility;

defined( 'ABSPATH' ) || exit;
/**
 * Ensures WooCommerce assets are registered before registry collection.
 */
final class WooCommerce {

	public function init(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		add_action( 'wp_enqueue_scripts', array( $this, 'ensure_wc_scripts' ), 5 );
	}

	public function ensure_wc_scripts(): void {
		if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
			// WooCommerce registers assets on this hook; no-op placeholder for future hooks.
		}
	}
}
