<?php
/**
 * Applies fetchpriority to images and preload tags.
 *
 * @deprecated Use FetchPriorityAction via RuntimePipeline.
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets\Runtime;

defined( 'ABSPATH' ) || exit;
/**
 * Modifies fetchpriority on images and scripts.
 */
final class FetchPriorityModifier {

	private RuntimeEngine $engine;

	public function init( RuntimeEngine $engine ): void {
		$this->engine = $engine;

		add_filter( 'script_loader_tag', array( $this, 'modify_script_priority' ), 11, 3 );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'modify_image_attributes' ), 10, 3 );
	}

	public function modify_script_priority( string $tag, string $handle, string $src ): string {
		$priority = $this->get_priority_for_handle( $handle, 'script' );
		if ( ! $priority ) {
			return $tag;
		}
		if ( str_contains( $tag, 'fetchpriority' ) ) {
			return $tag;
		}
		return str_replace( '<script ', '<script fetchpriority="' . esc_attr( $priority ) . '" ', $tag );
	}

	/**
	 * @param array<string, string> $attr
	 * @return array<string, string>
	 */
	public function modify_image_attributes( array $attr, \WP_Post $attachment, $size ): array {
		$priority = $this->get_priority_for_attachment( $attachment->ID );

		if ( $priority ) {
			$attr['fetchpriority'] = $priority;
		}

		return $attr;
	}

	private function get_priority_for_attachment( int $attachment_id ): ?string {
		$rules = $this->engine->get_grouped_rules()['fetchpriority'] ?? array();

		foreach ( $rules as $rule ) {
			if ( 'image' !== $rule['asset_type'] ) {
				continue;
			}

			$config = is_array( $rule['action_config'] ) ? $rule['action_config'] : array();
			$target = (int) ( $config['attachment_id'] ?? $rule['asset_handle'] );

			if ( $target !== $attachment_id && (string) $attachment_id !== $rule['asset_handle'] ) {
				continue;
			}

			$value = $config['value'] ?? $config['fetchpriority'] ?? '';
			if ( in_array( $value, array( 'high', 'low' ), true ) ) {
				return $value;
			}
		}

		return null;
	}

	private function get_priority_for_handle( string $handle, string $type ): ?string {
		$rules = $this->engine->get_grouped_rules()['fetchpriority'] ?? array();

		foreach ( $rules as $rule ) {
			if ( $rule['asset_handle'] !== $handle ) {
				continue;
			}
			if ( $rule['asset_type'] !== $type ) {
				continue;
			}
			$config = is_array( $rule['action_config'] ) ? $rule['action_config'] : array();
			$value  = $config['value'] ?? $config['fetchpriority'] ?? '';
			if ( in_array( $value, array( 'high', 'low' ), true ) ) {
				return $value;
			}
		}

		return null;
	}
}
