<?php
/**
 * Estimates rule scope from conditions and scan history.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Impact;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\Runtime\BulkRuleTargets;
use AssetControl\Assets\ScanHistoryIndex;

/**
 * Builds human-readable impact lines without crawling the site.
 */
final class RuleImpactEstimator {

	public function __construct(
		private readonly ScanHistoryIndex $scan_history = new ScanHistoryIndex()
	) {}

	/**
	 * @param array<string, mixed> $rule
	 * @return array<string, mixed>
	 */
	public function estimate( array $rule ): array {
		$conditions = is_array( $rule['condition_group'] ?? null ) ? $rule['condition_group'] : array();
		$lines      = $this->condition_summary_lines( $conditions );
		$targets    = BulkRuleTargets::expand( $rule );
		$bulk_count = count( $targets ) > 1 ? count( $targets ) : 0;

		if ( $bulk_count > 1 ) {
			array_unshift(
				$lines,
				sprintf(
					/* translators: %d: number of assets in bulk rule */
					__( 'Bulk rule — %d assets (scripts and/or styles)', 'assetpilot' ),
					$bulk_count
				)
			);
		}
		$scanned    = $this->scan_history->get_urls();
		$matched    = $this->filter_urls_for_conditions( $scanned, $conditions );

		$post_types = $this->post_type_breakdown( $conditions, $matched );
		$archives   = $this->archive_labels( $conditions );

		if ( ! empty( $matched ) && $this->scan_history->count() > 0 ) {
			$lines[] = sprintf(
				/* translators: 1: matched scan count, 2: total scans in history */
				__( 'Matches %1$d of %2$d scanned pages', 'assetpilot' ),
				count( $matched ),
				$this->scan_history->count()
			);
		} elseif ( 0 === $this->scan_history->count() ) {
			$lines[] = __( 'No scan history yet — scan pages in Assets Explorer for page-level estimates', 'assetpilot' );
		}

		$lines = array_values( array_unique( array_filter( $lines ) ) );

		return array(
			'summary_lines'         => $lines,
			'affected_url_count'    => count( $matched ) ?: $this->estimate_url_count( $conditions ),
			'scanned_pages_matched' => count( $matched ),
			'total_scanned_pages'   => $this->scan_history->count(),
			'post_types'            => $post_types,
			'archives'              => $archives,
			'uses_scan_history'     => $this->scan_history->count() > 0,
			'scope'                 => ! empty( $conditions['global'] ) ? 'global' : 'conditional',
			'bulk_asset_count'      => $bulk_count,
		);
	}

	/**
	 * @param array<string, mixed> $conditions
	 * @return array<int, string>
	 */
	private function condition_summary_lines( array $conditions ): array {
		$lines = array();

		if ( ! empty( $conditions['global'] ) || ( isset( $conditions['scope'] ) && 'global' === $conditions['scope'] ) ) {
			$lines[] = __( 'Entire site', 'assetpilot' );
			return $lines;
		}

		$include = $conditions['include_ids'] ?? $conditions['post_ids'] ?? array();
		foreach ( array_map( 'intval', (array) $include ) as $post_id ) {
			if ( $post_id <= 0 ) {
				continue;
			}
			$title = get_the_title( $post_id );
			$lines[] = $title
				? sprintf(
					/* translators: %s: post title */
					__( 'Page: %s', 'assetpilot' ),
					$title
				)
				: sprintf(
					/* translators: %d: post ID */
					__( 'Post ID %d', 'assetpilot' ),
					$post_id
				);
		}

		$exclude = array_map( 'intval', (array) ( $conditions['exclude_ids'] ?? array() ) );
		if ( ! empty( $exclude ) ) {
			$lines[] = sprintf(
				/* translators: %d: number of excluded pages */
				__( 'Excludes %d specific pages', 'assetpilot' ),
				count( $exclude )
			);
		}

		foreach ( (array) ( $conditions['singular_type'] ?? array() ) as $post_type ) {
			$lines[] = $this->post_type_count_line( (string) $post_type );
		}

		foreach ( (array) ( $conditions['post_type'] ?? array() ) as $post_type ) {
			$obj = get_post_type_object( (string) $post_type );
			$label = $obj->labels->archive_name ?? $obj->labels->name ?? (string) $post_type;
			$lines[] = sprintf(
				/* translators: %s: archive label */
				__( 'Archive: %s', 'assetpilot' ),
				$label
			);
		}

		foreach ( (array) ( $conditions['archive'] ?? array() ) as $archive ) {
			$lines[] = $this->archive_label( (string) $archive );
		}

		foreach ( (array) ( $conditions['woocommerce'] ?? array() ) as $page ) {
			$lines[] = $this->woocommerce_label( (string) $page );
		}

		$url_path = (string) ( $conditions['url_path'] ?? $conditions['url_contains'] ?? '' );
		if ( '' !== $url_path ) {
			$match_type = (string) ( $conditions['url_match_type'] ?? 'contains' );
			if ( 'starts_with' === $match_type ) {
				$lines[] = sprintf(
					/* translators: %s: URL path prefix */
					__( 'URLs starting with "%s"', 'assetpilot' ),
					$url_path
				);
			} else {
				$lines[] = sprintf(
					/* translators: %s: URL path fragment */
					__( 'URLs containing "%s"', 'assetpilot' ),
					$url_path
				);
			}
		}

		if ( ! empty( $conditions['query_contains'] ) ) {
			$lines[] = sprintf(
				/* translators: %s: query string fragment */
				__( 'Query string contains "%s"', 'assetpilot' ),
				(string) $conditions['query_contains']
			);
		}

		foreach ( (array) ( $conditions['user_roles'] ?? array() ) as $role ) {
			$lines[] = sprintf(
				/* translators: %s: role slug */
				__( 'User role: %s', 'assetpilot' ),
				(string) $role
			);
		}

		if ( ! empty( $conditions['device'] ) ) {
			$lines[] = 'mobile' === $conditions['device']
				? __( 'Mobile devices only', 'assetpilot' )
				: __( 'Desktop devices only', 'assetpilot' );
		}

		if ( isset( $conditions['logged_in'] ) ) {
			$lines[] = $conditions['logged_in']
				? __( 'Logged-in users only', 'assetpilot' )
				: __( 'Logged-out visitors only', 'assetpilot' );
		}

		if ( empty( $lines ) ) {
			$lines[] = __( 'Conditional scope (not site-wide)', 'assetpilot' );
		}

		return $lines;
	}

	private function post_type_count_line( string $post_type ): string {
		$obj = get_post_type_object( $post_type );
		if ( ! $obj ) {
			return $post_type;
		}

		$counts = wp_count_posts( $post_type );
		$count  = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$label  = $obj->labels->name ?? $post_type;

		return sprintf(
			/* translators: 1: number of posts, 2: post type plural label */
			_n( '%1$d %2$s', '%1$d %2$s', $count, 'assetpilot' ),
			$count,
			$label
		);
	}

	private function archive_label( string $archive ): string {
		$labels = array(
			'front'    => __( 'Front page', 'assetpilot' ),
			'home'     => __( 'Blog home', 'assetpilot' ),
			'category' => __( 'Category archives', 'assetpilot' ),
			'tag'      => __( 'Tag archives', 'assetpilot' ),
			'author'   => __( 'Author archives', 'assetpilot' ),
			'date'     => __( 'Date archives', 'assetpilot' ),
			'search'   => __( 'Search results', 'assetpilot' ),
			'cpt'      => __( 'Custom post type archives', 'assetpilot' ),
		);

		if ( isset( $labels[ $archive ] ) ) {
			return $labels[ $archive ];
		}

		if ( str_starts_with( $archive, 'taxonomy:' ) ) {
			$tax = substr( $archive, 9 );
			$obj = get_taxonomy( $tax );
			return $obj
				? sprintf(
					/* translators: %s: taxonomy label */
					__( '%s archives', 'assetpilot' ),
					$obj->labels->name
				)
				: $archive;
		}

		return $archive;
	}

	private function woocommerce_label( string $page ): string {
		$labels = array(
			'shop'     => __( 'WooCommerce shop', 'assetpilot' ),
			'cart'     => __( 'WooCommerce cart', 'assetpilot' ),
			'checkout' => __( 'WooCommerce checkout', 'assetpilot' ),
			'account'  => __( 'WooCommerce account', 'assetpilot' ),
			'product'  => __( 'WooCommerce products', 'assetpilot' ),
		);

		return $labels[ $page ] ?? $page;
	}

	/**
	 * @param array<int, string>   $urls
	 * @param array<string, mixed> $conditions
	 * @return array<int, string>
	 */
	private function filter_urls_for_conditions( array $urls, array $conditions ): array {
		if ( empty( $urls ) ) {
			return array();
		}

		if ( ! empty( $conditions['global'] ) ) {
			return $urls;
		}

		$exclude = array_map( 'intval', (array) ( $conditions['exclude_ids'] ?? array() ) );
		$matched = array();

		foreach ( $urls as $url ) {
			if ( ! $this->url_matches_conditions( $url, $conditions, $exclude ) ) {
				continue;
			}
			$matched[] = $url;
		}

		return array_values( array_unique( $matched ) );
	}

	/**
	 * @param array<string, mixed> $conditions
	 * @param array<int, int>      $exclude_ids
	 */
	private function url_matches_conditions( string $url, array $conditions, array $exclude_ids ): bool {
		$post_id = url_to_postid( $url );

		if ( $post_id > 0 && in_array( $post_id, $exclude_ids, true ) ) {
			return false;
		}

		$include = array_map( 'intval', (array) ( $conditions['include_ids'] ?? $conditions['post_ids'] ?? array() ) );
		if ( ! empty( $include ) ) {
			return $post_id > 0 && in_array( $post_id, $include, true );
		}

		$url_path = (string) ( $conditions['url_path'] ?? $conditions['url_contains'] ?? '' );
		if ( '' !== $url_path ) {
			$path = (string) ( wp_parse_url( $url, PHP_URL_PATH ) ?? '' );
			$starts_with = 'starts_with' === (string) ( $conditions['url_match_type'] ?? 'contains' );
			if ( $starts_with ) {
				if ( ! str_starts_with( $path, $url_path ) && ! str_starts_with( $url, $url_path ) ) {
					return false;
				}
			} elseif ( ! str_contains( $path, $url_path ) && ! str_contains( $url, $url_path ) ) {
				return false;
			}
		}

		if ( ! empty( $conditions['query_contains'] ) ) {
			$query = (string) ( wp_parse_url( $url, PHP_URL_QUERY ) ?? '' );
			if ( ! str_contains( $query, (string) $conditions['query_contains'] ) ) {
				return false;
			}
		}

		if ( ! empty( $conditions['singular_type'] ) && $post_id > 0 ) {
			return in_array( get_post_type( $post_id ), (array) $conditions['singular_type'], true );
		}

		if ( ! empty( $conditions['post_type'] ) && $post_id > 0 ) {
			return in_array( get_post_type( $post_id ), (array) $conditions['post_type'], true );
		}

		if ( '' === $url_path && empty( $conditions['query_contains'] ) && empty( $conditions['singular_type'] )
			&& empty( $conditions['post_type'] ) && empty( $conditions['archive'] ) && empty( $conditions['woocommerce'] ) ) {
			return true;
		}

		return ( '' === $url_path && empty( $conditions['query_contains'] ) ) || $post_id > 0
			|| ( '' !== $url_path && empty( $conditions['singular_type'] ) );
	}

	/**
	 * @param array<string, mixed> $conditions
	 * @param array<int, string>   $matched_urls
	 * @return array<int, array{type: string, label: string, count: int}>
	 */
	private function post_type_breakdown( array $conditions, array $matched_urls ): array {
		$types = array();

		foreach ( (array) ( $conditions['singular_type'] ?? array() ) as $post_type ) {
			$types[ (string) $post_type ] = true;
		}

		foreach ( $matched_urls as $url ) {
			$post_id = url_to_postid( $url );
			if ( $post_id > 0 ) {
				$types[ get_post_type( $post_id ) ] = true;
			}
		}

		$out = array();
		foreach ( array_keys( $types ) as $post_type ) {
			if ( ! is_string( $post_type ) || '' === $post_type ) {
				continue;
			}
			$obj = get_post_type_object( $post_type );
			if ( ! $obj ) {
				continue;
			}
			$out[] = array(
				'type'  => $post_type,
				'label' => $obj->labels->name ?? $post_type,
				'count' => $this->count_posts_of_type( $post_type, $conditions, $matched_urls ),
			);
		}

		return $out;
	}

	/**
	 * @param array<string, mixed> $conditions
	 * @param array<int, string>   $matched_urls
	 */
	private function count_posts_of_type( string $post_type, array $conditions, array $matched_urls ): int {
		if ( ! empty( $matched_urls ) ) {
			$count = 0;
			foreach ( $matched_urls as $url ) {
				$post_id = url_to_postid( $url );
				if ( $post_id > 0 && get_post_type( $post_id ) === $post_type ) {
					++$count;
				}
			}
			if ( $count > 0 ) {
				return $count;
			}
		}

		$counts = wp_count_posts( $post_type );
		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	/**
	 * @param array<string, mixed> $conditions
	 * @return array<int, string>
	 */
	private function archive_labels( array $conditions ): array {
		$out = array();
		foreach ( (array) ( $conditions['archive'] ?? array() ) as $archive ) {
			$out[] = $this->archive_label( (string) $archive );
		}
		return $out;
	}

	/**
	 * @param array<string, mixed> $conditions
	 */
	private function estimate_url_count( array $conditions ): int {
		if ( ! empty( $conditions['global'] ) ) {
			return max( 1, $this->scan_history->count() );
		}

		$include = array_map( 'intval', (array) ( $conditions['include_ids'] ?? $conditions['post_ids'] ?? array() ) );
		if ( ! empty( $include ) ) {
			return count( $include );
		}

		$total = 0;
		foreach ( (array) ( $conditions['singular_type'] ?? array() ) as $post_type ) {
			$counts = wp_count_posts( (string) $post_type );
			$total += isset( $counts->publish ) ? (int) $counts->publish : 0;
		}

		return $total > 0 ? $total : 1;
	}
}
