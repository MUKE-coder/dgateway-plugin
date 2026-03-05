<?php
defined('ABSPATH') || exit;

/**
 * Shortcode registrations for DGateway.
 *
 * [dgw_pay_button]     — Simple payment button
 * [dgw_donation_form]  — Donation form with preset amounts
 * [dgw_product_card]   — Product card with buy button
 */
class DGW_Shortcodes {

    public function __construct() {
        add_shortcode('dgw_pay_button', [$this, 'pay_button']);
        add_shortcode('dgw_donation_form', [$this, 'donation_form']);
        add_shortcode('dgw_product_card', [$this, 'product_card']);
        add_shortcode('dgw_subscribe_button', [$this, 'subscribe_button']);
    }

    /* ------------------------------------------------------------------ */
    /*  [dgw_pay_button]                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Render a pay button that opens the DGateway modal.
     *
     * Attributes:
     *   amount       (required) Payment amount
     *   currency     Default: plugin setting
     *   label        Button text, default "Pay Now"
     *   description  Payment description
     *   provider     "iotec", "stripe", or "" (auto)
     *   class        Extra CSS classes
     *   success_url  Redirect after success
     */
    public function pay_button($atts) {
        $atts = shortcode_atts([
            'amount'      => '0',
            'currency'    => get_option('dgw_default_currency', 'UGX'),
            'label'       => __('Pay Now', 'dgateway'),
            'description' => '',
            'provider'    => '',
            'class'       => '',
            'success_url' => '',
        ], $atts, 'dgw_pay_button');

        $this->enqueue_assets();

        ob_start();
        include DGW_PLUGIN_DIR . 'templates/shortcode-pay-button.php';
        return ob_get_clean();
    }

    /* ------------------------------------------------------------------ */
    /*  [dgw_donation_form]                                                 */
    /* ------------------------------------------------------------------ */

    /**
     * Render a donation form with preset amount buttons.
     *
     * Attributes:
     *   amounts         Comma-separated preset amounts (e.g. "5000,10000,50000")
     *   currency        Default: plugin setting
     *   allow_custom    "yes" or "no", default "yes"
     *   title           Form title
     *   description     Description text
     *   collect_name    "yes" or "no", default "no"
     *   collect_email   "yes" or "no", default "no"
     *   success_message Text shown after success
     *   success_url     Redirect URL
     */
    public function donation_form($atts) {
        $atts = shortcode_atts([
            'amounts'         => '5000,10000,25000,50000',
            'currency'        => get_option('dgw_default_currency', 'UGX'),
            'allow_custom'    => 'yes',
            'title'           => __('Make a Donation', 'dgateway'),
            'description'     => '',
            'collect_name'    => 'no',
            'collect_email'   => 'no',
            'success_message' => __('Thank you for your donation!', 'dgateway'),
            'success_url'     => '',
        ], $atts, 'dgw_donation_form');

        $atts['amounts_array'] = array_filter(array_map('trim', explode(',', $atts['amounts'])));

        $this->enqueue_assets();

        ob_start();
        include DGW_PLUGIN_DIR . 'templates/shortcode-donation-form.php';
        return ob_get_clean();
    }

    /* ------------------------------------------------------------------ */
    /*  [dgw_product_card]                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Render a product card with a buy button.
     *
     * Attributes:
     *   name          Product name
     *   price         Product price
     *   currency      Default: plugin setting
     *   image         Image URL
     *   description   Product description
     *   button_label  Button text, default "Buy Now"
     *   success_url   Redirect URL
     */
    public function product_card($atts) {
        $atts = shortcode_atts([
            'name'         => __('Product', 'dgateway'),
            'price'        => '0',
            'currency'     => get_option('dgw_default_currency', 'UGX'),
            'image'        => '',
            'description'  => '',
            'button_label' => __('Buy Now', 'dgateway'),
            'success_url'  => '',
        ], $atts, 'dgw_product_card');

        $this->enqueue_assets();

        ob_start();
        include DGW_PLUGIN_DIR . 'templates/shortcode-product-card.php';
        return ob_get_clean();
    }

    /* ------------------------------------------------------------------ */
    /*  [dgw_subscribe_button]                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Render a subscribe button that opens the subscription modal.
     *
     * Attributes:
     *   plan_id      (required) Subscription plan ID
     *   label        Button text, default "Subscribe"
     *   description  Plan description shown in modal
     *   class        Extra CSS classes
     */
    public function subscribe_button($atts) {
        $atts = shortcode_atts([
            'plan_id'     => '0',
            'label'       => __('Subscribe', 'dgateway'),
            'description' => '',
            'class'       => '',
        ], $atts, 'dgw_subscribe_button');

        $this->enqueue_assets();

        ob_start();
        include DGW_PLUGIN_DIR . 'templates/shortcode-subscribe-button.php';
        return ob_get_clean();
    }

    /* ------------------------------------------------------------------ */
    /*  Enqueue Assets                                                      */
    /* ------------------------------------------------------------------ */

    private function enqueue_assets() {
        wp_enqueue_style('dgw-checkout');
        wp_enqueue_script('dgw-checkout');
        wp_enqueue_script('dgw-modal');
    }
}
