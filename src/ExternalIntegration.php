<?php

namespace WPDesk\External;

use WPDesk\License\LicenseServer\PluginRegistrator;
use WPDesk\License\LicenseServer\PluginVersionInfo;

final class ExternalIntegration {

	private PluginVersionInfo $plugin_info;

	private function __construct( PluginVersionInfo $plugin_info ) {
		$this->plugin_info = $plugin_info;
	}

	public static function integrate( string $product_id, string $plugin_dir, string $filename, string $plugin_version ): void {
		$version_info = new PluginVersionInfo($product_id, $plugin_version, $product_id, basename($plugin_dir), $filename);

		( new self( $version_info ) )->run_init();
	}

	private function run_init(): void {
		add_action('plugins_loaded', function() {
			$registrator = new PluginRegistrator( $this->plugin_info );
			$registrator->initialize_license_manager();
		}, 9999);
	}
}