<?php
/**
 * Rule evaluation and matching.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Rules;

defined( 'ABSPATH' ) || exit;
use AssetControl\Database\RulesRepository;
use AssetControl\Helpers\Cache;
use AssetControl\Helpers\Logger;

/**
 * Loads rules and returns applicable actions for the current request.
 */
final class RuleEngine {

	public function __construct(
		private readonly RulesRepository $repository = new RulesRepository(),
		private readonly ConditionEvaluator $evaluator = new ConditionEvaluator()
	) {}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_applicable_rules(): array {
		return Cache::request(
			'assetpilot_applicable_rules',
			function (): array {
				$rules      = $this->repository->get_enabled_cached();
				$applicable = array();

				foreach ( $rules as $rule ) {
					$conditions = is_array( $rule['condition_group'] ) ? $rule['condition_group'] : array();

					if ( $this->evaluator->matches( $conditions ) ) {
						$applicable[] = $rule;
						Logger::log( 'applied', 'Rule matched', array( 'rule_id' => $rule['id'] ) );
					} else {
						Logger::log( 'skipped', 'Rule skipped (conditions)', array( 'rule_id' => $rule['id'] ) );
					}
				}

				return $applicable;
			}
		);
	}

	/**
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function group_by_action(): array {
		$grouped = array();
		foreach ( $this->get_applicable_rules() as $rule ) {
			$action = $rule['action_type'];
			if ( ! isset( $grouped[ $action ] ) ) {
				$grouped[ $action ] = array();
			}
			$grouped[ $action ][] = $rule;
		}
		return $grouped;
	}
}
