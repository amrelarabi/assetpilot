<?php
/**
 * WooCommerce page conditions.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Rules\Conditions;

defined( 'ABSPATH' ) || exit;
final class WooCommerceConditionHandler implements ConditionHandlerInterface {

	public function is_active( array $conditions ): bool {
		return ! empty( $conditions['woocommerce'] );
	}

	public function matches( array $conditions ): bool {
		if ( empty( $conditions['woocommerce'] ) || ! function_exists( 'is_woocommerce' ) ) {
			return false;
		}

		$wc = $conditions['woocommerce'];
		if ( ! is_array( $wc ) ) {
			$wc = array( $wc );
		}

		$map = array(
			'shop'     => 'is_shop',
			'cart'     => 'is_cart',
			'checkout' => 'is_checkout',
			'account'  => 'is_account_page',
			'product'  => 'is_product',
		);

		foreach ( $wc as $page ) {
			$fn = $map[ (string) $page ] ?? null;
			if ( $fn && function_exists( $fn ) && $fn() ) {
				return true;
			}
		}

		return false;
	}
}
