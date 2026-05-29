<?php
/**
 * Registry of condition handlers.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Rules\Conditions;

defined( 'ABSPATH' ) || exit;
/**
 * Provides ordered handlers for ConditionEvaluator.
 */
final class ConditionHandlerRegistry {

	/**
	 * @return array<int, ConditionHandlerInterface>
	 */
	public function handlers(): array {
		return array(
			new DeviceConditionHandler(),
			new AuthConditionHandler(),
			new UserRolesConditionHandler(),
			new ScanPageUrlConditionHandler(),
			new UrlConditionHandler(),
			new QueryStringConditionHandler(),
			new WooCommerceConditionHandler(),
			new SingularConditionHandler(),
			new PostTypeArchiveConditionHandler(),
			new ArchiveConditionHandler(),
		);
	}
}
