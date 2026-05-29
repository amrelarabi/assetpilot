<?php
/**
 * Condition field options for the admin UI.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Admin;

defined( 'ABSPATH' ) || exit;
/**
 * Builds post types, archives, and WooCommerce page lists for multiselects.
 */
final class ConditionOptions {

	/**
	 * @return array{
	 *   postTypes: array<int, array{value: string, label: string}>,
	 *   archives: array<int, array{value: string, label: string}>,
	 *   wcPages: array<int, array{value: string, label: string}>,
	 *   userRoles: array<int, array{value: string, label: string}>,
	 *   urlMatchModes: array<int, array{value: string, label: string}>
	 * }
	 */
	public static function get(): array {
		return array(
			'postTypes'     => self::get_post_types(),
			'archives'      => self::get_archives(),
			'wcPages'       => self::get_wc_pages(),
			'userRoles'     => self::get_user_roles(),
			'urlMatchModes' => self::get_url_match_modes(),
		);
	}

	/**
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function get_url_match_modes(): array {
		return array(
			array(
				'value' => 'contains',
				'label' => __( 'Path contains', 'assetpilot' ),
			),
			array(
				'value' => 'starts_with',
				'label' => __( 'Path starts with', 'assetpilot' ),
			),
		);
	}

	/**
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function get_user_roles(): array {
		if ( ! function_exists( 'wp_roles' ) ) {
			return array();
		}

		$roles = wp_roles();
		if ( ! $roles ) {
			return array();
		}

		$out = array();
		foreach ( $roles->roles as $slug => $role ) {
			$out[] = array(
				'value' => (string) $slug,
				'label' => translate_user_role( $role['name'] ?? $slug ),
			);
		}

		usort(
			$out,
			static fn( array $a, array $b ): int => strcmp( $a['label'], $b['label'] )
		);

		return $out;
	}

	/**
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function get_post_types(): array {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		$out   = array();

		foreach ( $types as $type ) {
			$out[] = array(
				'value' => $type->name,
				'label' => $type->labels->singular_name ?: $type->label,
			);
		}

		usort(
			$out,
			static fn( array $a, array $b ): int => strcmp( $a['label'], $b['label'] )
		);

		return $out;
	}

	/**
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function get_archives(): array {
		$built_in = array(
			array(
				'value' => 'home',
				'label' => __( 'Blog home', 'assetpilot' ),
			),
			array(
				'value' => 'front',
				'label' => __( 'Front page', 'assetpilot' ),
			),
			array(
				'value' => 'category',
				'label' => __( 'Category archives', 'assetpilot' ),
			),
			array(
				'value' => 'tag',
				'label' => __( 'Tag archives', 'assetpilot' ),
			),
			array(
				'value' => 'author',
				'label' => __( 'Author archives', 'assetpilot' ),
			),
			array(
				'value' => 'date',
				'label' => __( 'Date archives', 'assetpilot' ),
			),
			array(
				'value' => 'search',
				'label' => __( 'Search results', 'assetpilot' ),
			),
			array(
				'value' => 'cpt',
				'label' => __( 'Custom post type archives', 'assetpilot' ),
			),
		);

		$taxonomies = array();

		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $taxonomy ) {
			$taxonomies[] = array(
				'value' => 'taxonomy:' . $taxonomy->name,
				'label' => sprintf(
					/* translators: %s: taxonomy label */
					__( 'Taxonomy: %s', 'assetpilot' ),
					$taxonomy->labels->name
				),
			);
		}

		usort(
			$taxonomies,
			static fn( array $a, array $b ): int => strcmp( $a['label'], $b['label'] )
		);

		return array_merge( $built_in, $taxonomies );
	}

	/**
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function get_wc_pages(): array {
		if ( ! function_exists( 'woocommerce' ) ) {
			return array();
		}

		return array(
			array(
				'value' => 'shop',
				'label' => __( 'Shop', 'assetpilot' ),
			),
			array(
				'value' => 'cart',
				'label' => __( 'Cart', 'assetpilot' ),
			),
			array(
				'value' => 'checkout',
				'label' => __( 'Checkout', 'assetpilot' ),
			),
			array(
				'value' => 'account',
				'label' => __( 'My account', 'assetpilot' ),
			),
			array(
				'value' => 'product',
				'label' => __( 'Single product', 'assetpilot' ),
			),
		);
	}
}
