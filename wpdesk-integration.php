<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

require_once __DIR__ . '/autoloader-vendor/autoload.php';

$product_id = 'Woo MailerLite Groups'; // UNIQUE PLUGIN NAME - should be shared with WP Desk servers.
$plugin_dir = 'woo-mailerlite-groups'; // PLUGIN DIR - will be used for activation storage hash
$plugin_filename = 'woo-mailerlite-groups.php'; // Plugin main file - the one with the WordPress Docs
// todo: we need full filename with dir
$plugin_version  = get_file_data( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $plugin_dir . DIRECTORY_SEPARATOR . $plugin_filename, array( 'Version' => 'Version' ), false )['Version'];


\WPDesk\External\ExternalIntegration::integrate( $product_id, $plugin_dir, $plugin_filename, $plugin_version );