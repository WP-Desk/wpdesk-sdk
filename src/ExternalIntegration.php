<?php

declare( strict_types=1 );

namespace WPDesk\External;

use WPDesk\License\LicenseServer\PluginRegistrator;
use WPDesk\License\LicenseServer\PluginVersionInfo;

/**
 * Connects a plugin runtime to the WP Desk license system.
 */
final class ExternalIntegration {

	private PluginVersionInfo $plugin_info;

	private function __construct( PluginVersionInfo $plugin_info ) {
		$this->plugin_info = $plugin_info;
	}

	public static function integrate( string $product_id, string $plugin_file ): void {
		$plugin_headers = \get_file_data(
			$plugin_file,
			[
				'Name'    => 'Plugin Name',
				'Version' => 'Version',
			],
			false
		);
		$plugin_basename = \plugin_basename( $plugin_file );
		$plugin_slug     = \dirname( $plugin_basename );

		if ( $plugin_slug === '.' || $plugin_slug === '' ) {
			$plugin_slug = \basename( $plugin_file, '.php' );
		}

		$version_info = new PluginVersionInfo(
			$plugin_headers['Name'] ?: $product_id,
			$plugin_headers['Version'] ?: '',
			$product_id,
			$plugin_slug,
			$plugin_basename
		);

		( new self( $version_info ) )->run_init();
	}

	private function run_init(): void {
		\add_action( 'plugins_loaded', function (): void {
			$registrator = new PluginRegistrator( $this->plugin_info );
			$registrator->initialize_license_manager();
		}, 9999 );
	}
}
