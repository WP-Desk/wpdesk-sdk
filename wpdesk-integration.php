<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

require_once __DIR__ . '/autoloader-vendor/autoload.php';

$product_id = ''; // UNIQUE PLUGIN NAME - should be shared with WP Desk servers.
$plugin_dir = ''; // PLUGIN DIR - will be used for activation storage hash

\WPDesk\External\ExternalIntegration::integrate( $product_id, $plugin_dir, function () {
	// here you can put tje code to run when the plugin subscription is active.
} );