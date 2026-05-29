<?php
/**
 * Frontend runtime executor.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets\Runtime;

defined( 'ABSPATH' ) || exit;
use AssetControl\Core\SafeModeManager;

/**
 * Applies rules on the frontend via RuntimePipeline.
 */
final class RuntimeEngine {

	private RuntimePipeline $pipeline;

	private ?RuntimeContext $context = null;

	private bool $ran = false;

	/** @var array<string, array{src: string, type: string}> */
	private array $url_disable_rules = array();

	public function __construct() {
		$this->pipeline = new RuntimePipeline();
	}

	public function init(): void {
		if ( SafeModeManager::is_runtime_disabled() ) {
			return;
		}

		if ( defined( 'ASSETPILOT_ASSET_SCAN' ) && ASSETPILOT_ASSET_SCAN ) {
			return;
		}

		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$this->pipeline->register_hooks();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Must run on core enqueue.
		add_action( 'wp_enqueue_scripts', array( $this, 'run_pipeline' ), 999 );
		add_action( 'wp_print_styles', array( $this, 'run_pipeline' ), 0 );
		add_action( 'wp_print_scripts', array( $this, 'run_pipeline' ), 0 );
		add_action( 'wp_head', array( $this, 'inject_preloads' ), 1 );

		add_filter( 'script_loader_src', array( $this, 'maybe_block_script_src' ), 999, 2 );
		add_filter( 'style_loader_src', array( $this, 'maybe_block_style_src' ), 999, 2 );
	}

	public function run_pipeline(): void {
		if ( $this->ran ) {
			return;
		}
		$this->ran                 = true;
		$this->context             = $this->pipeline->run();
		$this->url_disable_rules   = $this->context->url_disable_rules();
	}

	public function inject_preloads(): void {
		if ( ! $this->context ) {
			$this->run_pipeline();
		}
		if ( $this->context ) {
			$this->pipeline->run_preload( $this->context );
		}
	}

	/**
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_grouped_rules(): array {
		if ( $this->context ) {
			return $this->context->group_by_action();
		}
		return array();
	}

	/**
	 * @param array<string, mixed> $rule
	 */
	public function resolve_rule_handle( array $rule ): string {
		$context = $this->context ?? new RuntimeContext();
		return $context->resolve_rule_handle( $rule );
	}

	public function maybe_block_script_src( string $src, string $handle ): string|false {
		unset( $handle );
		foreach ( $this->url_disable_rules as $rule ) {
			if ( 'script' !== $rule['type'] ) {
				continue;
			}
			if ( $this->urls_match( $rule['src'], $src ) ) {
				return false;
			}
		}
		return $src;
	}

	public function maybe_block_style_src( string $src, string $handle ): string|false {
		unset( $handle );
		foreach ( $this->url_disable_rules as $rule ) {
			if ( 'style' !== $rule['type'] ) {
				continue;
			}
			if ( $this->urls_match( $rule['src'], $src ) ) {
				return false;
			}
		}
		return $src;
	}

	private function urls_match( string $rule_src, string $loaded_src ): bool {
		$context = $this->context ?? new RuntimeContext();
		return $context->urls_match( $rule_src, $loaded_src );
	}
}
