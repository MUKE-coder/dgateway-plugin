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
     *
     * Mirrors the toggles exposed by DGW_WC_Gateway.init_form_fields()
     * so the Blocks checkout can hide the Card tab when the admin has
     * disabled Card payments — and surface the FX preview ("you'll be
     * charged $5") on the front-end so buyers aren't surprised on their
     * statements.
     */
    public function get_payment_method_data(): array {
        $enableMomo = ($this->settings['enable_momo'] ?? 'yes') === 'yes';
        $enableCard = ($this->settings['enable_card'] ?? 'no') === 'yes';
        $shopCurrency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'UGX';
        $stripeCurrency = $this->settings['stripe_currency'] ?? 'USD';
        $rate = floatval($this->settings['usd_exchange_rate'] ?? 0);

        // Card only effectively available when configured. Same logic
        // as DGW_WC_Gateway::card_enabled() — keep them in sync.
        $cardEffective = $enableCard && (
            strtoupper($shopCurrency) === strtoupper($stripeCurrency) || $rate > 0
        );

        return [
            'title'           => $this->settings['title'] ?? __('DGateway Payment', 'dgateway'),
            'description'    => $this->settings['description'] ?? __('Pay with Mobile Money or Card via DGateway.', 'dgateway'),
            'supports'       => ['products'],
            'enable_momo'    => $enableMomo,
            'enable_card'    => $cardEffective,
            'shop_currency'  => $shopCurrency,
            'stripe_currency' => $stripeCurrency,
            'exchange_rate'  => $rate,
        ];
    }
}
