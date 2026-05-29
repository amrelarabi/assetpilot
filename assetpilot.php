<?php
/**
 * Plugin Name:       AssetPilot - Granular control over frontend assets
 * Description:       Granular control over frontend assets — disable, defer, async, preload, and conditional rules.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.1
 * Author:            Amr Abdelkarem
 * Author URI:        https://amrabdelkarem.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       assetpilot
 * Domain Path:       /languages
 *
 * @package AssetControl
 */

declare(strict_types=1);

namespace AssetControl;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ASSETPILOT_VERSION', '1.0.0' );
define( 'ASSETPILOT_PLUGIN_FILE', __FILE__ );
define( 'ASSETPILOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASSETPILOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ASSETPILOT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

$assetpilot_autoloader = ASSETPILOT_PLUGIN_DIR . 'vendor/autoload.php';

// Load Composer autoloader when available, but still register our fallback loader
// because older/stale vendor autoload mappings may not match the current namespaces.
if ( file_exists( $assetpilot_autoloader ) ) {
	require_once $assetpilot_autoloader;
}

// Fallback PSR-4 autoloader (used whenever Composer can't resolve a class).
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'AssetControl\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = ASSETPILOT_PLUGIN_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Returns the main plugin instance.
 */
function assetpilot(): Core\Plugin {
	return Core\Plugin::instance();
}

register_activation_hook( __FILE__, array( Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( Core\Deactivator::class, 'deactivate' ) );

assetpilot()->init();
