<?php
/**
 * Plugin Name: DGateway Payment Gateway
 * Plugin URI: https://dgatewayapi.desispay.com
 * Description: Accept Mobile Money and Card payments via DGateway. Works standalone or with WooCommerce.
 * Version: 1.0.0
 * Author: DesisPay
 * Author URI: https://desispay.com
 * License: GPL-2.0-or-later
 * Text Domain: dgateway
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 */

defined('ABSPATH') || exit;

define('DGW_VERSION', '1.0.0');
define('DGW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DGW_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DGW_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoload classes.
spl_autoload_register(function ($class) {
    $prefix = 'DGW_';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $map = [
        'DGW_Plugin'          => 'includes/class-dgw-plugin.php',
        'DGW_API_Client'      => 'includes/class-dgw-api-client.php',
        'DGW_REST_API'        => 'includes/class-dgw-rest-api.php',
        'DGW_Webhook_Handler' => 'includes/class-dgw-webhook-handler.php',
        'DGW_Shortcodes'      => 'includes/class-dgw-shortcodes.php',
        'DGW_Admin'           => 'includes/class-dgw-admin.php',
        'DGW_WC_Gateway'                 => 'includes/woocommerce/class-dgw-wc-gateway.php',
        'DGW_WC_Subscription_Product'    => 'includes/woocommerce/class-dgw-wc-subscription-product.php',
    ];

    if (isset($map[$class])) {
        $file = DGW_PLUGIN_DIR . $map[$class];
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

/**
 * Activation hook — set default options.
 */
function dgw_activate() {
    add_option('dgw_api_url', 'https://dgatewayapi.desispay.com');
    add_option('dgw_api_key', '');
    add_option('dgw_default_currency', 'UGX');
    add_option('dgw_webhook_secret', wp_generate_password(32, false));
}
register_activation_hook(__FILE__, 'dgw_activate');

/**
 * Deactivation hook.
 */
function dgw_deactivate() {
    // Nothing to clean up for now.
}
register_deactivation_hook(__FILE__, 'dgw_deactivate');

/**
 * Boot the plugin.
 */
function dgw_init() {
    if (!class_exists('DGW_Plugin')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>DGateway:</strong> Plugin files are incomplete. Please re-install the plugin.</p></div>';
        });
        return;
    }
    DGW_Plugin::instance();
}
add_action('plugins_loaded', 'dgw_init');
