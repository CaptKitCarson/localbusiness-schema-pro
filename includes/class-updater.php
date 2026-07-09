<?php
/**
 * Backward-compat wrapper around KitMobley\PluginCore\Updater.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class LSP_Updater extends \KitMobley\PluginCore\Updater {
	public function __construct() {
		parent::__construct( lsp_plugin_config(), new LSP_License() );
	}
}
