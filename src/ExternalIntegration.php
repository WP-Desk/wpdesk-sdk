<?php

namespace WPDesk\External;

use WPDesk\License\PluginRegistrator;
use WPDesk_Plugin_Info;

class ExternalIntegration {
	/** @var WPDesk_Plugin_Info */
	private $plugin_info;

	public function __construct( WPDesk_Plugin_Info $plugin_info ) {
		$this->plugin_info = $plugin_info;
	}

	/**
	 * @param string $product_id
	 * @param string $plugin_dir
	 */
	public static function integrate( $product_id, $plugin_dir, $filename, $plugin_version ) {
		$external_plugin_info = new WPDesk_Plugin_Info();
		$external_plugin_info->set_product_id( $product_id );
		$external_plugin_info->set_plugin_dir( $plugin_dir );
		$external_plugin_info->set_plugin_file_name( $filename );
		$external_plugin_info->set_version( $plugin_version );

		( new self( $external_plugin_info ) )->run_init();
	}

	private function run_init(): void {
		if ( apply_filters( 'wpdesk_can_register_plugin', true, $this->plugin_info ) ) {
			$registrator = new PluginRegistrator( $this->plugin_info );
			$registrator->initialize_license_manager();
		}
	}
}