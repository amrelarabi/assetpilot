<?php
/**
 * Runtime action handler contract.
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl\Assets\Runtime\Actions;

defined( 'ABSPATH' ) || exit;
use AssetControl\Assets\Runtime\RuntimeContext;

/**
 * Applies one action type to already-matched rules (no condition logic).
 */
interface RuntimeActionInterface {

	public function action_type(): string;

	/**
	 * Register WordPress hooks needed for this action (once per request bootstrap).
	 */
	public function register_hooks(): void;

	/**
	 * @param array<int, array<string, mixed>> $rules Matched rules for this action only.
	 */
	public function execute( array $rules, RuntimeContext $context ): void;
}
