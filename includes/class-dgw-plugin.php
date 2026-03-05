<?php
defined('ABSPATH') || exit;

/**
 * Main plugin class (singleton).
 */
class DGW_Plugin {

    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init_hooks();
    }

    private function init_hooks() {
        // Admin.
        if (is_admin()) {
            new DGW_Admin();
        }

        // REST API.
        add_action('rest_api_init', [$this, 'register_rest_routes']);

        // Shortcodes.
        new DGW_Shortcodes();

        // Webhook handler.
        new DGW_Webhook_Handler();

        // WooCommerce gateway (only if WC active).
        add_action('plugins_loaded', [$this, 'init_woocommerce'], 20);

        // Frontend scripts.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Register WooCommerce gateway if WooCommerce is active.
     */
    public function init_woocommerce() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_filter('woocommerce_payment_gateways', function ($gateways) {
            $gateways[] = 'DGW_WC_Gateway';
            return $gateways;
        });

        // Register subscription product type.
        new DGW_WC_Subscription_Product();
    }

    /**
     * Register WP REST API routes.
     */
    public function register_rest_routes() {
        $rest = new DGW_REST_API();
        $rest->register_routes();
    }

    /**
     * Enqueue frontend scripts and styles.
     */
    public function enqueue_scripts() {
        // Always register — only enqueued when needed.
        wp_register_style(
            'dgw-checkout',
            DGW_PLUGIN_URL . 'assets/css/dgw-checkout.css',
            [],
            DGW_VERSION
        );

        wp_register_script(
            'dgw-checkout',
            DGW_PLUGIN_URL . 'assets/js/dgw-checkout.js',
            [],
            DGW_VERSION,
            true
        );

        wp_register_script(
            'dgw-modal',
            DGW_PLUGIN_URL . 'assets/js/dgw-modal.js',
            ['dgw-checkout'],
            DGW_VERSION,
            true
        );

        // Localize with REST URL, nonce, and logo.
        wp_localize_script('dgw-checkout', 'dgwCheckout', [
            'restUrl' => esc_url_raw(rest_url('dgateway/v1/')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'logoUrl' => esc_url(DGW_PLUGIN_URL . 'assets/images/logo.png'),
        ]);
    }

    /**
     * Get the API client instance.
     */
    public static function api() {
        static $client = null;
        if (null === $client) {
            $client = new DGW_API_Client(
                get_option('dgw_api_url', 'https://dgatewayapi.desispay.com'),
                get_option('dgw_api_key', '')
            );
        }
        return $client;
    }
}
