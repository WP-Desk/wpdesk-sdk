<?php

namespace WPDesk\External;

use WPDesk\Helper\HelperRemover;
use WPDesk\Helper\PrefixedHelperAsLibrary;
use WPDesk\License\PluginRegistrator;
use WPDesk\Plugin\Flow\Initialization\PluginDisablerByFileTrait;
use WPDesk\Plugin\Flow\Initialization\Simple\HelperInstanceAsFilterTrait;

class ExternalIntegration {
	use HelperInstanceAsFilterTrait;

	/** @var WPDesk_Plugin_Info */
	private $plugin_info;

	public function __construct( WPDesk_Plugin_Info $plugin_info ) {
		$this->plugin_info = $plugin_info;
	}

	/**
	 * @param string $product_id
	 * @param string $plugin_dir
	 * @param callback $activate_callback
	 */
	public static function integrate( $product_id, $plugin_dir, $activate_callback ) {
		$external_plugin_info = new WPDesk_Plugin_Info();
		$external_plugin_info->set_product_id( $product_id );
		$external_plugin_info->set_plugin_dir( $plugin_dir );

		( new self( $external_plugin_info ) )->run_init( $activate_callback );
	}

	/**
	 * Run task that integrates plugin with other dependencies. Can be run in plugins_loaded.
	 */
	private function run_init( $activate_callback ) {
		$this->init_translation();;
		$registrator = $this->register_plugin();
		$this->init_helper();

		$is_plugin_subscription_active = $registrator instanceof PluginRegistrator && $registrator->is_active();
		if ( $is_plugin_subscription_active && is_callable( $activate_callback ) ) {
			$activate_callback();
		}
	}

	public function init_translation() {
		//
	}

	/**
	 * Register plugin for subscriptions and updates
	 *
	 * @return PluginRegistrator|void
	 *
	 * @see init_helper note
	 *
	 */
	private function register_plugin() {
		if ( apply_filters( 'wpdesk_can_register_plugin', true, $this->plugin_info ) ) {
			$registrator = new PluginRegistrator( $this->plugin_info );
			$registrator->add_plugin_to_installed_plugins();

			return $registrator;
		}
	}

	/**
	 * Helper is a component that gives:
	 * - activation interface
	 * - automatic updates
	 * - logs
	 * - some other feats
	 *
	 * NOTE:
	 *
	 * It's possible for this method to not found classes embedded here.
	 * OTHER plugin in unlikely scenario that THIS plugin is disabled
	 * can use this class and do not have this library dependencies as
	 * these are loaded using composer.
	 *
	 * @return PrefixedHelperAsLibrary|null
	 */
	private function init_helper() {
		$this->prevent_older_helpers();
		$this->prepare_helper_action();

		return $this->get_helper_instance();
	}

	/**
	 * Try to disable all other types of helpers
	 */
	private function prevent_older_helpers() {
		if ( apply_filters( 'wpdesk_can_hack_shared_helper', true, $this->plugin_info ) ) {
			// hack to ensure that the class is loaded so other helpers are disabled
			class_exists( \WPDesk\Helper\HelperAsLibrary::class, true );
		}

		if ( apply_filters( 'wpdesk_can_supress_original_helper', true, $this->plugin_info ) ) {
			$this->try_suppress_original_helper_load();

			// start supression only once. Prevent doing it again
			add_filter( 'wpdesk_can_supress_original_helper',
				function () {
					return false;
				} );
		}

		if ( apply_filters( 'wpdesk_can_remove_old_helper_hooks', true, $this->plugin_info ) ) {
			( new HelperRemover() )->hooks();
		}
	}

	/**
	 * Tries to prevent original Helper from loading
	 */
	private function try_suppress_original_helper_load() {
		( new PluginDisablerByFileTrait( 'wpdesk-helper/wpdesk-helper.php' ) )->disable();
	}
}