<?php
/**
 * Backward-compat wrapper around KitMobley\PluginCore\License.
 * Implementation lives in includes/vendor/kitmobley-core/src/License.php.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LSP_License extends \KitMobley\PluginCore\License {
	public function __construct() {
		parent::__construct( lsp_plugin_config() );
	}
}
