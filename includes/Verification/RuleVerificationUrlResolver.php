<?php
/**
 * Resolves a frontend URL to verify a rule against.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Verification;

defined( 'ABSPATH' ) || exit;
/**
 * Picks a page where the rule's conditions are expected to apply.
 */
final class RuleVerificationUrlResolver {

	/**
	 * @param array<string, mixed> $rule
	 */
	public function resolve( array $rule ): string {
		$conditions = is_array( $rule['condition_group'] ?? null ) ? $rule['condition_group'] : array();
		$config     = is_array( $rule['action_config'] ?? null ) ? $rule['action_config'] : array();

		if ( ! empty( $conditions['global'] ) || ( isset( $conditions['scope'] ) && 'global' === $conditions['scope'] ) ) {
			return home_url( '/' );
		}

		$scan_url = isset( $config['scan_url'] ) ? esc_url_raw( (string) $config['scan_url'] ) : '';
		if ( '' !== $scan_url ) {
			return $scan_url;
		}

		if ( ! empty( $conditions['url_contains'] ) ) {
			return $this->url_from_path( (string) $conditions['url_contains'] );
		}

		$include_ids = $conditions['include_ids'] ?? $conditions['post_ids'] ?? array();
		if ( ! empty( $include_ids ) ) {
			$permalink = get_permalink( (int) $include_ids[0] );
			if ( $permalink ) {
				return $permalink;
			}
		}

		if ( ! empty( $conditions['singular_type'] ) ) {
			$url = $this->first_singular_url( (array) $conditions['singular_type'] );
			if ( $url ) {
				return $url;
			}
		}

		if ( ! empty( $conditions['post_type'] ) ) {
			$archive = get_post_type_archive_link( (string) ( (array) $conditions['post_type'] )[0] );
			if ( $archive ) {
				return $archive;
			}
		}

		if ( ! empty( $conditions['archive'] ) ) {
			$url = $this->resolve_archive_url( (array) $conditions['archive'] );
			if ( $url ) {
				return $url;
			}
		}

		if ( ! empty( $conditions['woocommerce'] ) && function_exists( 'wc_get_page_id' ) ) {
			$url = $this->resolve_woocommerce_url( (array) $conditions['woocommerce'] );
			if ( $url ) {
				return $url;
			}
		}

		return home_url( '/' );
	}

	private function url_from_path( string $path ): string {
		$path = trim( $path );
		if ( '' === $path ) {
			return home_url( '/' );
		}
		if ( preg_match( '#^https?://#i', $path ) ) {
			return esc_url_raw( $path ) ?: home_url( '/' );
		}
		if ( ! str_starts_with( $path, '/' ) ) {
			$path = '/' . $path;
		}
		return home_url( $path );
	}

	/**
	 * @param array<int, string> $types
	 */
	private function first_singular_url( array $types ): string {
		foreach ( $types as $post_type ) {
			$post_type = sanitize_key( (string) $post_type );
			if ( '' === $post_type ) {
				continue;
			}
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'no_found_rows'  => true,
				)
			);
			if ( ! empty( $posts[0] ) ) {
				$link = get_permalink( $posts[0] );
				if ( $link ) {
					return $link;
				}
			}
		}
		return '';
	}

	/**
	 * @param array<int, string> $archives
	 */
	private function resolve_archive_url( array $archives ): string {
		foreach ( $archives as $archive ) {
			$archive = (string) $archive;
			switch ( $archive ) {
				case 'front':
					if ( 'page' === get_option( 'show_on_front' ) ) {
						$page_id = (int) get_option( 'page_on_front' );
						if ( $page_id > 0 ) {
							$link = get_permalink( $page_id );
							if ( $link ) {
								return $link;
							}
						}
					}
					return home_url( '/' );
				case 'home':
					return (string) ( get_home_url() ?: home_url( '/' ) );
				case 'search':
					return home_url( '/?s=assetpilot-verify' );
				case 'category':
					$term = get_terms( array( 'taxonomy' => 'category', 'number' => 1, 'hide_empty' => false ) );
					if ( ! is_wp_error( $term ) && ! empty( $term[0] ) ) {
						$link = get_term_link( $term[0] );
						if ( ! is_wp_error( $link ) ) {
							return $link;
						}
					}
					break;
				case 'tag':
					$term = get_terms( array( 'taxonomy' => 'post_tag', 'number' => 1, 'hide_empty' => false ) );
					if ( ! is_wp_error( $term ) && ! empty( $term[0] ) ) {
						$link = get_term_link( $term[0] );
						if ( ! is_wp_error( $link ) ) {
							return $link;
						}
					}
					break;
				case 'author':
					$users = get_users( array( 'number' => 1, 'who' => 'authors' ) );
					if ( ! empty( $users[0] ) ) {
						return get_author_posts_url( (int) $users[0]->ID );
					}
					break;
				case 'date':
					return home_url( '/' );
				case 'cpt':
					$types = get_post_types( array( 'public' => true, '_builtin' => false ), 'names' );
					foreach ( $types as $type ) {
						$link = get_post_type_archive_link( $type );
						if ( $link ) {
							return $link;
						}
					}
					break;
				default:
					if ( str_starts_with( $archive, 'taxonomy:' ) ) {
						$tax = substr( $archive, 9 );
						$term = get_terms( array( 'taxonomy' => $tax, 'number' => 1, 'hide_empty' => false ) );
						if ( ! is_wp_error( $term ) && ! empty( $term[0] ) ) {
							$link = get_term_link( $term[0] );
							if ( ! is_wp_error( $link ) ) {
								return $link;
							}
						}
					}
					break;
			}
		}
		return '';
	}

	/**
	 * @param array<int, string> $pages
	 */
	private function resolve_woocommerce_url( array $pages ): string {
		$map = array(
			'shop'     => 'shop',
			'cart'     => 'cart',
			'checkout' => 'checkout',
			'account'  => 'myaccount',
			'product'  => 'product',
		);

		foreach ( $pages as $page ) {
			$slug = $map[ (string) $page ] ?? null;
			if ( ! $slug ) {
				continue;
			}
			if ( 'product' === $slug ) {
				$products = get_posts(
					array(
						'post_type'      => 'product',
						'post_status'    => 'publish',
						'posts_per_page' => 1,
						'no_found_rows'  => true,
					)
				);
				if ( ! empty( $products[0] ) ) {
					$link = get_permalink( $products[0] );
					if ( $link ) {
						return $link;
					}
				}
				continue;
			}
			$page_id = wc_get_page_id( $slug );
			if ( $page_id > 0 ) {
				$link = get_permalink( $page_id );
				if ( $link ) {
					return $link;
				}
			}
		}
		return '';
	}
}
