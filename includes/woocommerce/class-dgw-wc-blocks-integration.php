<?php
defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * WooCommerce Blocks integration for DGateway.
 *
 * IMPORTANT: initialize() is called during woocommerce_blocks_loaded, which fires
 * at plugins_loaded before our init_woocommerce() (priority 20) has had a chance
 * to register the DGW_WC_Gateway via the woocommerce_payment_gateways filter.
 * We therefore read configuration directly from the options table instead of
 * depending on the gateway object being available at that point.
 */
class DGW_WC_Blocks_Integration extends AbstractPaymentMethodType {

    protected $name = 'dgateway';

    public function initialize() {
        // Read settings directly from the DB — safe to call at any hook.
        $this->settings = get_option('woocommerce_dgateway_settings', []);
    }

    /**
     * Whether the payment method should appear at checkout.
     * Checks the gateway's enabled setting directly (no gateway object needed).
     */
    public function is_active(): bool {
        return 'yes' === ($this->settings['enabled'] ?? 'yes');
    }

    /**
     * Enqueue the blocks JS and return its handle.
     */
    public function get_payment_method_script_handles(): array {
        wp_register_script(
            'dgw-wc-blocks',
            DGW_PLUGIN_URL . 'assets/js/dgw-wc-blocks.js',
            ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities'],
            DGW_VERSION,
            true
        );
        return ['dgw-wc-blocks'];
    }

    /**
     * Data passed to the JS payment form via getSetting('dgateway_data', {}).
     */
    public function get_payment_method_data(): array {
        return [
            'title'       => $this->settings['title'] ?? __('DGateway Payment', 'dgateway'),
            'description' => $this->settings['description'] ?? __('Pay with Mobile Money or Card via DGateway.', 'dgateway'),
            'supports'    => ['products'],
        ];
    }
}
