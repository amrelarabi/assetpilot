<?php
/**
 * Orchestrates matched rules through action handlers.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets\Runtime;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\Runtime\Actions\AsyncAssetAction;
use AssetControl\Assets\Runtime\Actions\DeferAssetAction;
use AssetControl\Assets\Runtime\Actions\DisableAssetAction;
use AssetControl\Assets\Runtime\Actions\FetchPriorityAction;
use AssetControl\Assets\Runtime\Actions\PreloadAssetAction;
use AssetControl\Assets\Runtime\Actions\RuntimeActionInterface;
use AssetControl\Core\SafeModeManager;
use AssetControl\Helpers\Logger;
use AssetControl\Rules\RuleEngine;

/**
 * Collect → validate → sort → execute runtime actions.
 */
final class RuntimePipeline {

	/** @var array<int, RuntimeActionInterface> */
	private array $handlers;

	private bool $hooks_registered = false;

	/**
	 * @param array<int, RuntimeActionInterface>|null $handlers
	 */
	public function __construct(
		private readonly RuleEngine $rule_engine = new RuleEngine(),
		?array $handlers = null
	) {
		$this->handlers = $handlers ?? array(
			new DisableAssetAction(),
			new DeferAssetAction(),
			new AsyncAssetAction(),
			new PreloadAssetAction(),
			new FetchPriorityAction(),
		);
	}

	public function register_hooks(): void {
		if ( $this->hooks_registered ) {
			return;
		}
		foreach ( $this->handlers as $handler ) {
			$handler->register_hooks();
		}
		$this->hooks_registered = true;
	}

	public function run(): RuntimeContext {
		$context = new RuntimeContext();

		if ( SafeModeManager::is_runtime_disabled() ) {
			return $context;
		}

		$rules   = $this->collect_matched_rules();
		$rules   = $this->validate_rules( $rules );
		$rules   = $this->sort_by_priority( $rules );

		$context->set_matched_rules( $rules );

		$grouped = $context->group_by_action();
		$order   = array( 'disable', 'defer', 'async', 'fetchpriority' );

		foreach ( $this->handlers as $handler ) {
			$type = $handler->action_type();
			if ( ! in_array( $type, $order, true ) ) {
				continue;
			}
			$action_rules = $grouped[ $type ] ?? array();
			if ( empty( $action_rules ) ) {
				continue;
			}
			$handler->execute( $action_rules, $context );
		}

		/**
		 * Fires after runtime handlers run (verification hooks in Phase 3).
		 *
		 * @param array<int, array<string, mixed>> $rules
		 * @param RuntimeContext                   $context
		 */
		do_action( 'assetpilot_after_runtime_pipeline', $rules, $context );

		Logger::log(
			'runtime',
			'Pipeline executed',
			array(
				'matched' => count( $rules ),
				'actions' => array_keys( $grouped ),
			)
		);

		return $context;
	}

	/**
	 * Preload runs in wp_head after queues are built.
	 */
	public function run_preload( RuntimeContext $context ): void {
		$rules = $context->group_by_action()['preload'] ?? array();
		if ( empty( $rules ) ) {
			return;
		}
		foreach ( $this->handlers as $handler ) {
			if ( 'preload' === $handler->action_type() ) {
				$handler->execute( $rules, $context );
				break;
			}
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function collect_matched_rules(): array {
		return $this->rule_engine->get_applicable_rules();
	}

	/**
	 * @param array<int, array<string, mixed>> $rules
	 * @return array<int, array<string, mixed>>
	 */
	private function validate_rules( array $rules ): array {
		$allowed_actions = array( 'disable', 'defer', 'async', 'preload', 'fetchpriority' );
		$allowed_types     = array( 'script', 'style', 'image', 'font' );

		return array_values(
			array_filter(
				$rules,
				static function ( array $rule ) use ( $allowed_actions, $allowed_types ): bool {
					if ( empty( $rule['enabled'] ) ) {
						Logger::log( 'skipped', 'Rule disabled at runtime', array( 'rule_id' => $rule['id'] ?? 0 ) );
						return false;
					}
					if ( empty( $rule['asset_handle'] ) || empty( $rule['action_type'] ) ) {
						Logger::log( 'skipped', 'Rule missing handle or action', array( 'rule_id' => $rule['id'] ?? 0 ) );
						return false;
					}
					if ( ! in_array( $rule['action_type'], $allowed_actions, true ) ) {
						return false;
					}
					if ( ! in_array( $rule['asset_type'] ?? '', $allowed_types, true ) ) {
						return false;
					}
					return true;
				}
			)
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $rules
	 * @return array<int, array<string, mixed>>
	 */
	private function sort_by_priority( array $rules ): array {
		usort(
			$rules,
			static fn( array $a, array $b ): int => ( (int) ( $a['priority'] ?? 10 ) ) <=> ( (int) ( $b['priority'] ?? 10 ) )
		);
		return $rules;
	}
}
