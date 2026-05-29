<?php
/**
 * Applies fetchpriority to scripts and images.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets\Runtime\Actions;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\Runtime\RuntimeContext;
use AssetControl\Assets\Runtime\ScriptTagAttributeInjector;

/**
 * fetchpriority on script tags and attachment images.
 */
final class FetchPriorityAction implements RuntimeActionInterface {

	/** @var array<int, array<string, mixed>> */
	private array $rules = array();

	private ?RuntimeContext $context = null;

	public function action_type(): string {
		return 'fetchpriority';
	}

	public function register_hooks(): void {
		// Run after theme/plugin defer filters (e.g. vanced-theme @ 20).
		add_filter( 'script_loader_tag', array( $this, 'modify_script_tag' ), 999, 3 );
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'modify_image_attributes' ), 10, 3 );
		// hello-theme-frontend and other footer scripts enqueue earlier but print in the footer.
		add_action( 'wp_print_footer_scripts', array( $this, 'reapply_script_fetchpriority' ), 1 );
	}

	/**
	 * @param array<int, array<string, mixed>> $rules
	 */
	public function execute( array $rules, RuntimeContext $context ): void {
		$this->rules   = $rules;
		$this->context = $context;
		$this->apply_script_fetchpriority_data();
	}

	public function reapply_script_fetchpriority(): void {
		if ( empty( $this->rules ) ) {
			return;
		}
		$this->apply_script_fetchpriority_data();
	}

	public function modify_script_tag( string $tag, string $handle, string $src ): string {
		$priority = $this->get_priority_for_script( $handle, $src );
		if ( ! $priority ) {
			return $tag;
		}

		$tag = ScriptTagAttributeInjector::strip_defer_and_async( $tag );

		return ScriptTagAttributeInjector::set_attribute( $tag, 'fetchpriority', $priority );
	}

	/**
	 * WordPress 6.9+ reads fetchpriority from script registration data when building tags.
	 */
	private function apply_script_fetchpriority_data(): void {
		$scripts = wp_scripts();
		if ( ! $scripts || ! $this->context ) {
			return;
		}

		foreach ( $this->rules as $rule ) {
			if ( 'script' !== ( $rule['asset_type'] ?? '' ) ) {
				continue;
			}

			$priority = $this->priority_from_rule( $rule );
			if ( ! $priority ) {
				continue;
			}

			$handle = $this->resolve_script_handle( $rule );
			if ( '' === $handle || ! isset( $scripts->registered[ $handle ] ) ) {
				continue;
			}

			unset( $scripts->registered[ $handle ]->extra['strategy'] );
			$scripts->add_data( $handle, 'fetchpriority', $priority );
		}
	}

	/**
	 * @param array<string, string> $attr
	 * @return array<string, string>
	 */
	public function modify_image_attributes( array $attr, \WP_Post $attachment, $size ): array {
		unset( $size );
		$priority = $this->get_priority_for_attachment( $attachment->ID );
		if ( $priority ) {
			$attr['fetchpriority'] = $priority;
		}
		return $attr;
	}

	private function get_priority_for_attachment( int $attachment_id ): ?string {
		foreach ( $this->rules as $rule ) {
			if ( 'image' !== ( $rule['asset_type'] ?? '' ) ) {
				continue;
			}
			$config = is_array( $rule['action_config'] ) ? $rule['action_config'] : array();
			$target = (int) ( $config['attachment_id'] ?? $rule['asset_handle'] );

			if ( $target !== $attachment_id && (string) $attachment_id !== $rule['asset_handle'] ) {
				continue;
			}

			$priority = $this->priority_from_rule( $rule );
			if ( $priority ) {
				return $priority;
			}
		}
		return null;
	}

	private function get_priority_for_script( string $handle, string $src ): ?string {
		if ( ! $this->context ) {
			return null;
		}
		foreach ( $this->rules as $rule ) {
			if ( ! $this->rule_matches_script( $rule, $handle, $src ) ) {
				continue;
			}
			$priority = $this->priority_from_rule( $rule );
			if ( $priority ) {
				return $priority;
			}
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $rule
	 */
	private function priority_from_rule( array $rule ): ?string {
		$config = is_array( $rule['action_config'] ) ? $rule['action_config'] : array();
		$value  = strtolower( (string) ( $config['value'] ?? $config['fetchpriority'] ?? 'high' ) );
		if ( in_array( $value, array( 'high', 'low' ), true ) ) {
			return $value;
		}
		return null;
	}

	/**
	 * @param array<string, mixed> $rule
	 */
	private function resolve_script_handle( array $rule ): string {
		if ( ! $this->context ) {
			return (string) ( $rule['asset_handle'] ?? '' );
		}

		$handle = $this->context->resolve_rule_handle( $rule );
		if ( '' !== $handle ) {
			return $handle;
		}

		return (string) ( $rule['asset_handle'] ?? '' );
	}

	/**
	 * @param array<string, mixed> $rule
	 */
	private function rule_matches_script( array $rule, string $handle, string $src ): bool {
		if ( 'script' !== ( $rule['asset_type'] ?? '' ) ) {
			return false;
		}

		$rule_handle = (string) ( $rule['asset_handle'] ?? '' );
		if ( '' !== $rule_handle && $rule_handle === $handle ) {
			return true;
		}

		if ( $this->context && $this->context->resolve_rule_handle( $rule ) === $handle ) {
			return true;
		}

		$config   = is_array( $rule['action_config'] ) ? $rule['action_config'] : array();
		$rule_src = (string) ( $config['src'] ?? $config['href'] ?? '' );
		if ( '' !== $rule_src && '' !== $src && $this->context && $this->context->urls_match( $rule_src, $src ) ) {
			return true;
		}

		return false;
	}
}

