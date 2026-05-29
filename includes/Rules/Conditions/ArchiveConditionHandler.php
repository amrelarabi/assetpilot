<?php
/**
 * Built-in and taxonomy archive conditions.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Rules\Conditions;

defined( 'ABSPATH' ) || exit;
final class ArchiveConditionHandler implements ConditionHandlerInterface {

	public function is_active( array $conditions ): bool {
		return ! empty( $conditions['archive'] );
	}

	public function matches( array $conditions ): bool {
		$archives = (array) $conditions['archive'];

		$checks = array(
			'category' => is_category(),
			'tag'      => is_tag(),
			'author'   => is_author(),
			'date'     => is_date(),
			'search'   => is_search(),
			'home'     => is_home(),
			'front'    => is_front_page(),
		);

		foreach ( $archives as $archive ) {
			if ( 'cpt' === $archive && is_post_type_archive() ) {
				return true;
			}
			if ( isset( $checks[ $archive ] ) && $checks[ $archive ] ) {
				return true;
			}
			if ( is_tax() && str_starts_with( (string) $archive, 'taxonomy:' ) ) {
				$tax     = substr( (string) $archive, 9 );
				$queried = get_queried_object();
				if ( $queried && isset( $queried->taxonomy ) && $queried->taxonomy === $tax ) {
					return true;
				}
			}
		}

		return false;
	}
}
