<?php
/**
 * Resolves a scanned frontend URL into suggested rule conditions.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Helpers;

defined( 'ABSPATH' ) || exit;
/**
 * Maps scan URLs to condition_group shapes for the rule wizard.
 */
final class ScanPageContextResolver {

	/**
	 * @return array{
	 *   url: string,
	 *   kind: string,
	 *   label: string,
	 *   conditions: array<string, mixed>
	 * }
	 */
	public function resolve( string $url ): array {
		$url = $this->normalize_url( $url );

		if ( '' === $url ) {
			return $this->fallback( $url, __( 'Entire site', 'assetpilot' ), 'unknown' );
		}

		if ( ! $this->is_same_site( $url ) ) {
			return $this->fallback( $url, __( 'Scanned page URL', 'assetpilot' ), 'external' );
		}

		$wc = $this->match_woocommerce_page( $url );
		if ( null !== $wc ) {
			return $wc;
		}

		$post_id = url_to_postid( $url );
		if ( $post_id > 0 ) {
			return $this->singular_for_post( $url, $post_id );
		}

		$archive = $this->match_post_type_archive( $url );
		if ( null !== $archive ) {
			return $archive;
		}

		$builtin = $this->match_builtin_archive( $url );
		if ( null !== $builtin ) {
			return $builtin;
		}

		if ( UrlHelper::is_site_front_url( $url ) ) {
			return $this->match_front_page( $url );
		}

		return $this->fallback( $url, __( 'Scanned page URL', 'assetpilot' ), 'scan_page' );
	}

	/**
	 * @return array{url: string, kind: string, label: string, conditions: array<string, mixed>}
	 */
	private function singular_for_post( string $url, int $post_id ): array {
		$post_type = get_post_type( $post_id );
		if ( ! is_string( $post_type ) || '' === $post_type ) {
			return $this->fallback( $url, __( 'Scanned page URL', 'assetpilot' ), 'scan_page' );
		}

		$obj   = get_post_type_object( $post_type );
		$label = $obj->labels->singular_name ?? $obj->label ?? $post_type;

		return array(
			'url'        => $url,
			'kind'       => 'singular',
			'label'      => sprintf(
				/* translators: %s: post type singular label */
				__( 'Singular — %s', 'assetpilot' ),
				$label
			),
			'conditions' => array(
				'global'         => false,
				'singular_type'  => array( $post_type ),
				'post_type'      => array(),
				'include_ids'    => array(),
				'exclude_ids'    => array(),
				'archive'        => array(),
				'woocommerce'    => array(),
				'scan_page_url'  => '',
				'url_path'       => '',
				'url_contains'   => '',
				'query_contains' => '',
				'user_roles'     => array(),
				'device'         => '',
				'logged_in'      => null,
			),
		);
	}

	/**
	 * @return array{url: string, kind: string, label: string, conditions: array<string, mixed>}|null
	 */
	private function match_post_type_archive( string $url ): ?array {
		$types = get_post_types( array( 'public' => true ), 'names' );

		foreach ( $types as $post_type ) {
			$link = get_post_type_archive_link( (string) $post_type );
			if ( ! $link || ! $this->urls_match( $link, $url ) ) {
				continue;
			}

			$obj   = get_post_type_object( (string) $post_type );
			$label = $obj->labels->name ?? (string) $post_type;

			return array(
				'url'        => $url,
				'kind'       => 'post_type_archive',
				'label'      => sprintf(
					/* translators: %s: post type plural label */
					__( '%s archive', 'assetpilot' ),
					$label
				),
				'conditions' => array(
					'global'         => false,
					'post_type'      => array( (string) $post_type ),
					'singular_type'  => array(),
					'include_ids'    => array(),
					'exclude_ids'    => array(),
					'archive'        => array(),
					'woocommerce'    => array(),
					'scan_page_url'  => '',
					'url_path'       => '',
					'url_contains'   => '',
					'query_contains' => '',
					'user_roles'     => array(),
					'device'         => '',
					'logged_in'      => null,
				),
			);
		}

		return null;
	}

	/**
	 * @return array{url: string, kind: string, label: string, conditions: array<string, mixed>}|null
	 */
	private function match_woocommerce_page( string $url ): ?array {
		if ( ! function_exists( 'wc_get_page_id' ) ) {
			return null;
		}

		$pages = array(
			'shop'     => __( 'Shop', 'assetpilot' ),
			'cart'     => __( 'Cart', 'assetpilot' ),
			'checkout' => __( 'Checkout', 'assetpilot' ),
			'account'  => __( 'My account', 'assetpilot' ),
		);

		foreach ( $pages as $slug => $page_label ) {
			$page_id = (int) wc_get_page_id( $slug );
			if ( $page_id <= 0 ) {
				continue;
			}
			$permalink = get_permalink( $page_id );
			if ( $permalink && $this->urls_match( $permalink, $url ) ) {
				return array(
					'url'        => $url,
					'kind'       => 'woocommerce',
					'label'      => sprintf(
						/* translators: %s: WooCommerce page name */
						__( 'WooCommerce — %s', 'assetpilot' ),
						$page_label
					),
					'conditions' => array(
						'global'         => false,
						'woocommerce'    => array( $slug ),
						'singular_type'  => array(),
						'post_type'      => array(),
						'include_ids'    => array(),
						'exclude_ids'    => array(),
						'archive'        => array(),
						'scan_page_url'  => '',
						'url_path'       => '',
						'url_contains'   => '',
						'query_contains' => '',
						'user_roles'     => array(),
						'device'         => '',
						'logged_in'      => null,
					),
				);
			}
		}

		return null;
	}

	/**
	 * @return array{url: string, kind: string, label: string, conditions: array<string, mixed>}
	 */
	private function match_front_page( string $url ): array {
		$page_id = (int) get_option( 'page_on_front' );
		if ( $page_id > 0 ) {
			return $this->singular_for_post( $url, $page_id );
		}

		return array(
			'url'        => $url,
			'kind'       => 'archive',
			'label'      => __( 'Front page', 'assetpilot' ),
			'conditions' => array(
				'global'         => false,
				'archive'        => array( 'front' ),
				'singular_type'  => array(),
				'post_type'      => array(),
				'include_ids'    => array(),
				'exclude_ids'    => array(),
				'woocommerce'    => array(),
				'scan_page_url'  => '',
				'url_path'       => '',
				'url_contains'   => '',
				'query_contains' => '',
				'user_roles'     => array(),
				'device'         => '',
				'logged_in'      => null,
			),
		);
	}

	/**
	 * @return array{url: string, kind: string, label: string, conditions: array<string, mixed>}|null
	 */
	private function match_builtin_archive( string $url ): ?array {
		$posts_page = (int) get_option( 'page_for_posts' );
		if ( $posts_page > 0 ) {
			$posts_url = get_permalink( $posts_page );
			if ( $posts_url && $this->urls_match( $posts_url, $url ) ) {
				return array(
					'url'        => $url,
					'kind'       => 'archive',
					'label'      => __( 'Blog home', 'assetpilot' ),
					'conditions' => array(
						'global'         => false,
						'archive'        => array( 'home' ),
						'singular_type'  => array(),
						'post_type'      => array(),
						'include_ids'    => array(),
						'exclude_ids'    => array(),
						'woocommerce'    => array(),
						'scan_page_url'  => '',
						'url_path'       => '',
						'url_contains'   => '',
						'query_contains' => '',
						'user_roles'     => array(),
						'device'         => '',
						'logged_in'      => null,
					),
				);
			}
		}

		return null;
	}

	/**
	 * @return array{url: string, kind: string, label: string, conditions: array<string, mixed>}
	 */
	private function fallback( string $url, string $label, string $kind ): array {
		return array(
			'url'        => $url,
			'kind'       => $kind,
			'label'      => $label,
			'conditions' => array(
				'global'         => false,
				'scan_page_url'  => $url,
				'singular_type'  => array(),
				'post_type'      => array(),
				'include_ids'    => array(),
				'exclude_ids'    => array(),
				'archive'        => array(),
				'woocommerce'    => array(),
				'url_path'       => '',
				'url_contains'   => '',
				'query_contains' => '',
				'user_roles'     => array(),
				'device'         => '',
				'logged_in'      => null,
			),
		);
	}

	private function normalize_url( string $url ): string {
		$url   = trim( rawurldecode( $url ) );
		$clean = esc_url_raw( $url );
		return '' !== $clean ? $clean : $url;
	}

	private function is_same_site( string $url ): bool {
		$parsed = wp_parse_url( $url );
		$host   = $parsed['host'] ?? '';
		if ( '' === $host ) {
			return true;
		}

		$site_hosts = array();
		foreach ( array( home_url( '/' ), site_url( '/' ) ) as $site_url ) {
			$site_host = wp_parse_url( $site_url, PHP_URL_HOST );
			if ( is_string( $site_host ) && '' !== $site_host ) {
				$site_hosts[] = strtolower( $site_host );
			}
		}

		return in_array( strtolower( $host ), $site_hosts, true );
	}

	private function urls_match( string $a, string $b ): bool {
		return UrlHelper::normalize_for_compare( $a ) === UrlHelper::normalize_for_compare( $b );
	}
}
