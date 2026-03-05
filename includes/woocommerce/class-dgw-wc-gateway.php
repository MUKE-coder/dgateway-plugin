<?php
defined('ABSPATH') || exit;

/**
 * WooCommerce Payment Gateway for DGateway.
 *
 * Supports Mobile Money (via Iotec) and Card (via Stripe) payments.
 */
class DGW_WC_Gateway extends \WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'dgateway';
        $this->icon               = '';
        $this->has_fields         = true;
        $this->method_title       = __('DGateway', 'dgateway');
        $this->method_description = __('Accept Mobile Money and Card payments via DGateway.', 'dgateway');
        $this->supports           = ['products'];

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option('title', __('DGateway Payment', 'dgateway'));
        $this->description = $this->get_option('description', __('Pay with Mobile Money or Card.', 'dgateway'));
        $this->enabled     = $this->get_option('enabled', 'yes');

        // Save settings.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

        // Enqueue checkout scripts.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_checkout_scripts']);
    }

    /* ------------------------------------------------------------------ */
    /*  Gateway Settings                                                    */
    /* ------------------------------------------------------------------ */

    public function init_form_fields() {
        $this->form_fields = [
            'enabled' => [
                'title'   => __('Enable/Disable', 'dgateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable DGateway Payment', 'dgateway'),
                'default' => 'yes',
            ],
            'title' => [
                'title'       => __('Title', 'dgateway'),
                'type'        => 'text',
                'description' => __('Payment method title shown at checkout.', 'dgateway'),
                'default'     => __('DGateway Payment', 'dgateway'),
                'desc_tip'    => true,
            ],
            'description' => [
                'title'       => __('Description', 'dgateway'),
                'type'        => 'textarea',
                'description' => __('Payment method description shown at checkout.', 'dgateway'),
                'default'     => __('Pay with Mobile Money or Card via DGateway.', 'dgateway'),
                'desc_tip'    => true,
            ],
        ];
    }

    /* ------------------------------------------------------------------ */
    /*  Payment Fields (shown at checkout)                                  */
    /* ------------------------------------------------------------------ */

    public function payment_fields() {
        if ($this->description) {
            echo '<p>' . wp_kses_post($this->description) . '</p>';
        }
        ?>
        <div id="dgw-wc-payment-fields">
            <div class="dgw-wc-tabs">
                <button type="button" class="dgw-wc-tab active" data-tab="momo">
                    &#128241; Mobile Money
                </button>
                <button type="button" class="dgw-wc-tab" data-tab="card">
                    &#128179; Card
                </button>
            </div>

            <div class="dgw-wc-tab-content dgw-wc-tab-momo active">
                <p>
                    <label for="dgw_phone"><?php esc_html_e('Phone Number', 'dgateway'); ?></label>
                    <input type="tel" id="dgw_phone" name="dgw_phone"
                           class="input-text" placeholder="e.g. 256771234567" />
                    <small><?php esc_html_e('Enter with country code (e.g. 256 for Uganda)', 'dgateway'); ?></small>
                </p>
            </div>

            <div class="dgw-wc-tab-content dgw-wc-tab-card">
                <p><?php esc_html_e('You will be prompted to enter your card details after placing the order.', 'dgateway'); ?></p>
            </div>

            <input type="hidden" name="dgw_payment_method" id="dgw_payment_method" value="momo" />
        </div>

        <style>
            .dgw-wc-tabs {
                display: flex;
                gap: 8px;
                margin-bottom: 16px;
            }
            .dgw-wc-tab {
                flex: 1;
                padding: 10px;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                background: #fff;
                cursor: pointer;
                font-size: 13px;
                font-weight: 600;
                text-align: center;
                transition: all 0.15s;
            }
            .dgw-wc-tab.active {
                border-color: #4f46e5;
                background: #eef2ff;
                color: #4f46e5;
            }
            .dgw-wc-tab-content {
                display: none;
            }
            .dgw-wc-tab-content.active {
                display: block;
            }
            #dgw-wc-payment-fields small {
                color: #888;
                display: block;
                margin-top: 4px;
            }
        </style>

        <script>
            (function() {
                var tabs = document.querySelectorAll('.dgw-wc-tab');
                tabs.forEach(function(tab) {
                    tab.addEventListener('click', function() {
                        tabs.forEach(function(t) { t.classList.remove('active'); });
                        tab.classList.add('active');
                        var target = tab.getAttribute('data-tab');
                        document.querySelectorAll('.dgw-wc-tab-content').forEach(function(c) { c.classList.remove('active'); });
                        document.querySelector('.dgw-wc-tab-' + target).classList.add('active');
                        document.getElementById('dgw_payment_method').value = target;
                    });
                });
            })();
        </script>
        <?php
    }

    /* ------------------------------------------------------------------ */
    /*  Enqueue Checkout Scripts                                            */
    /* ------------------------------------------------------------------ */

    public function enqueue_checkout_scripts() {
        if (!is_checkout()) {
            return;
        }

        wp_enqueue_style('dgw-checkout');
        wp_enqueue_script('dgw-checkout');
        wp_enqueue_script('dgw-modal');

        // WC checkout interception script.
        wp_add_inline_script('dgw-checkout', $this->get_wc_checkout_js(), 'after');
    }

    /**
     * Inline JS to intercept WC checkout response and handle DGateway payment flow.
     */
    private function get_wc_checkout_js() {
        return <<<'JS'
(function($) {
    if (typeof $ === 'undefined') return;

    $(document.body).on('checkout_error', function() {
        // Let WC handle errors normally.
    });

    // Override the default checkout success handler for DGateway orders.
    $(document).ajaxComplete(function(event, xhr, settings) {
        if (!settings.url || settings.url.indexOf('wc-ajax=checkout') === -1) return;

        try {
            var res = JSON.parse(xhr.responseText);
        } catch(e) { return; }

        if (res.result !== 'success' || !res.dgw_reference) return;

        // Prevent WC from redirecting.
        event.stopImmediatePropagation();

        var ref = res.dgw_reference;
        var redirectUrl = res.redirect || '';

        if (res.dgw_client_secret && res.dgw_stripe_key) {
            // Stripe flow: open modal with card form.
            DGW_Modal.open({
                amount: parseFloat(res.dgw_amount) || 0,
                currency: res.dgw_currency || 'USD',
                provider: 'stripe',
                description: 'WooCommerce Order',
                successUrl: redirectUrl,
                onSuccess: function() {
                    window.location.href = redirectUrl;
                },
            });
            // Override the modal's stripe init with pre-fetched data.
            DGW_Modal._reference = ref;
            DGW_Checkout.loadStripe(res.dgw_stripe_key).then(function(stripe) {
                DGW_Modal._stripe = stripe;
                DGW_Modal._clientSecret = res.dgw_client_secret;
                DGW_Modal._state = 'stripe_mount';
                DGW_Modal._render();
                DGW_Modal._show();
            });
        } else {
            // MoMo flow: show processing modal and poll.
            DGW_Modal.open({
                amount: parseFloat(res.dgw_amount) || 0,
                currency: res.dgw_currency || 'UGX',
                provider: 'iotec',
                description: 'WooCommerce Order',
                successUrl: redirectUrl,
                onSuccess: function() {
                    window.location.href = redirectUrl;
                },
            });
            DGW_Modal._reference = ref;
            DGW_Modal._state = 'processing';
            DGW_Modal._render();
            DGW_Modal._show();

            DGW_Checkout.pollStatus(ref,
                function() { DGW_Modal._onSuccess(); },
                function(err) { DGW_Modal._onFailed(err); }
            );
        }
    });
})(jQuery);
JS;
    }

    /* ------------------------------------------------------------------ */
    /*  Validate Fields                                                     */
    /* ------------------------------------------------------------------ */

    public function validate_fields() {
        $method = isset($_POST['dgw_payment_method']) ? sanitize_text_field($_POST['dgw_payment_method']) : '';

        if ($method === 'momo') {
            $phone = isset($_POST['dgw_phone']) ? sanitize_text_field($_POST['dgw_phone']) : '';
            if (empty($phone) || strlen($phone) < 9) {
                wc_add_notice(__('Please enter a valid phone number for Mobile Money payment.', 'dgateway'), 'error');
                return false;
            }
        }

        return true;
    }

    /* ------------------------------------------------------------------ */
    /*  Process Payment                                                     */
    /* ------------------------------------------------------------------ */

    public function process_payment($order_id) {
        $order  = wc_get_order($order_id);
        $method = isset($_POST['dgw_payment_method']) ? sanitize_text_field($_POST['dgw_payment_method']) : 'momo';
        $phone  = isset($_POST['dgw_phone']) ? sanitize_text_field($_POST['dgw_phone']) : '';

        $amount   = $order->get_total();
        $currency = $order->get_currency();

        $api = DGW_Plugin::api();

        // Check if this order contains a DGateway subscription product.
        $plan_id = $this->get_subscription_plan_id($order);
        if ($plan_id) {
            return $this->process_subscription_payment($order, $plan_id, $phone);
        }

        // Determine provider.
        $provider = $method === 'card' ? 'stripe' : 'iotec';

        $args = [
            'amount'       => (float) $amount,
            'currency'     => $currency,
            'phone_number' => $phone ?: '0000000000',
            'provider'     => $provider,
            'description'  => sprintf('WooCommerce Order #%d', $order_id),
            'metadata'     => [
                'order_id'   => $order_id,
                'source'     => 'woocommerce',
            ],
        ];

        $result = $api->collect($args);

        if (is_wp_error($result)) {
            wc_add_notice($result->get_error_message(), 'error');
            return ['result' => 'failure'];
        }

        $data = $result['data'] ?? $result;
        $reference = $data['reference'] ?? '';

        if (empty($reference)) {
            wc_add_notice(__('Failed to get payment reference from DGateway.', 'dgateway'), 'error');
            return ['result' => 'failure'];
        }

        // Store the reference in order meta.
        $order->update_meta_data('_dgw_reference', $reference);
        $order->update_meta_data('_dgw_provider', $provider);
        $order->save();

        // Set order on-hold.
        $order->update_status('on-hold', sprintf(
            __('Awaiting DGateway %s payment. Reference: %s', 'dgateway'),
            $provider === 'stripe' ? 'card' : 'mobile money',
            $reference
        ));

        // Build response for JS interception.
        $response = [
            'result'        => 'success',
            'redirect'      => $this->get_return_url($order),
            'dgw_reference' => $reference,
            'dgw_amount'    => $amount,
            'dgw_currency'  => $currency,
        ];

        // For Stripe, include client_secret so JS can mount Elements.
        if ($provider === 'stripe') {
            $response['dgw_client_secret'] = $data['client_secret'] ?? '';
            $response['dgw_stripe_key']    = $data['stripe_publishable_key'] ?? '';
        }

        return $response;
    }

    /**
     * Check if order contains a DGateway subscription product and return its plan ID.
     */
    private function get_subscription_plan_id(\WC_Order $order): int {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product && $product->get_type() === 'dgw_subscription') {
                $plan_id = (int) get_post_meta($product->get_id(), '_dgw_plan_id', true);
                if ($plan_id > 0) {
                    return $plan_id;
                }
            }
        }
        return 0;
    }

    /**
     * Process payment for a subscription product.
     */
    private function process_subscription_payment(\WC_Order $order, int $plan_id, string $phone): array {
        $api = DGW_Plugin::api();

        $result = $api->create_subscription([
            'plan_id'        => $plan_id,
            'customer_email' => $order->get_billing_email(),
            'customer_name'  => $order->get_formatted_billing_full_name(),
            'customer_phone' => $phone ?: $order->get_billing_phone(),
            'provider'       => 'iotec',
        ]);

        if (is_wp_error($result)) {
            wc_add_notice($result->get_error_message(), 'error');
            return ['result' => 'failure'];
        }

        $data = $result['data'] ?? $result;
        $reference = $data['reference'] ?? '';

        $order->update_meta_data('_dgw_reference', $reference);
        $order->update_meta_data('_dgw_subscription_ref', $reference);
        $order->update_meta_data('_dgw_provider', 'iotec');
        $order->save();

        // If there's a transaction (no trial), wait for payment.
        if (!empty($data['transaction_reference'])) {
            $order->update_meta_data('_dgw_reference', $data['transaction_reference']);
            $order->save();

            $order->update_status('on-hold', sprintf(
                __('Awaiting DGateway subscription payment. Ref: %s', 'dgateway'), $reference
            ));

            return [
                'result'        => 'success',
                'redirect'      => $this->get_return_url($order),
                'dgw_reference' => $data['transaction_reference'],
                'dgw_amount'    => $data['amount'] ?? $order->get_total(),
                'dgw_currency'  => $data['currency'] ?? $order->get_currency(),
            ];
        }

        // Trial subscription — mark as completed immediately.
        $order->payment_complete($reference);
        $order->add_order_note(sprintf(
            __('DGateway subscription created (trial). Ref: %s', 'dgateway'), $reference
        ));

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }
}
