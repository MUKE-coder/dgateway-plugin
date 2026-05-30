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

        // Polling UI on the thank-you page for pending MoMo payments.
        add_action('woocommerce_thankyou_dgateway', [$this, 'thankyou_polling_ui']);
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

            // ──────── Payment rails ──────────────────────────────────
            'enable_momo' => [
                'title'   => __('Mobile Money', 'dgateway'),
                'type'    => 'checkbox',
                'label'   => __('Accept Mobile Money payments (Iotec / Relworx)', 'dgateway'),
                'default' => 'yes',
            ],
            'enable_card' => [
                'title'       => __('Card Payments', 'dgateway'),
                'type'        => 'checkbox',
                'label'       => __('Accept Card payments (Stripe)', 'dgateway'),
                'description' => __('When enabled, buyers can pay by credit/debit card via Stripe. Requires an exchange rate below if your shop is priced in UGX (or any non-USD/EUR/GBP currency Stripe does not support).', 'dgateway'),
                'default'     => 'no',
                'desc_tip'    => false,
            ],

            // ──────── FX for Card payments ───────────────────────────
            'stripe_currency' => [
                'title'       => __('Stripe Settlement Currency', 'dgateway'),
                'type'        => 'select',
                'description' => __('Stripe will charge the buyer in this currency. Your shop price is converted using the exchange rate below.', 'dgateway'),
                'default'     => 'USD',
                'options'     => [
                    'USD' => 'USD — US Dollar',
                    'EUR' => 'EUR — Euro',
                    'GBP' => 'GBP — British Pound',
                ],
                'desc_tip'    => false,
            ],
            'usd_exchange_rate' => [
                'title'             => __('Exchange Rate', 'dgateway'),
                'type'              => 'number',
                'description'       => __('How many UGX equal 1 unit of the Stripe settlement currency above (e.g. 3700 means 3,700 UGX = 1 USD). Only used when your shop is priced in UGX and the buyer pays by Card. Update this when the market rate moves more than a few percent.', 'dgateway'),
                'default'           => '3700',
                'custom_attributes' => ['step' => '1', 'min' => '1'],
                'desc_tip'          => false,
            ],
        ];
    }

    /**
     * Validate gateway settings on save. WooCommerce calls this from
     * process_admin_options(); returning the (possibly-corrected) value
     * lets us refuse impossible combinations like "Card enabled but no
     * exchange rate" without silently breaking checkout for buyers.
     */
    public function process_admin_options() {
        $saved = parent::process_admin_options();

        // Re-read post-save values and refuse Card-on-without-rate.
        $card = $this->get_option('enable_card', 'no');
        $rate = floatval($this->get_option('usd_exchange_rate', '0'));
        $shopCurrency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'UGX';
        $stripeCurrency = $this->get_option('stripe_currency', 'USD');

        if ($card === 'yes' && strtoupper($shopCurrency) !== strtoupper($stripeCurrency) && $rate <= 0) {
            \WC_Admin_Settings::add_error(
                __('DGateway: Card payments are enabled but no Exchange Rate is set. Without a rate the plugin cannot convert your shop currency to Stripe. Set a rate (default 3700 UGX per 1 USD) or disable Card payments.', 'dgateway')
            );
            // Force the toggle back off so checkout doesn't break.
            $this->update_option('enable_card', 'no');
        }

        // Don't allow both rails to be disabled — the gateway becomes
        // meaningless. Default to MoMo-on if the admin tried to turn
        // both off in one save.
        if ($this->get_option('enable_momo', 'yes') !== 'yes' && $this->get_option('enable_card', 'no') !== 'yes') {
            \WC_Admin_Settings::add_error(
                __('DGateway: at least one payment rail (Mobile Money or Card) must be enabled — re-enabling Mobile Money.', 'dgateway')
            );
            $this->update_option('enable_momo', 'yes');
        }

        return $saved;
    }

    /**
     * True when this checkout request should offer the Card rail to
     * the buyer. Centralises the "enable_card=yes AND we have a usable
     * rate (or shop currency already matches Stripe)" check so the
     * classic field UI, the Blocks integration, and process_payment
     * stay aligned.
     */
    public function card_enabled(): bool {
        if ($this->get_option('enable_card', 'no') !== 'yes') return false;
        $shop = strtoupper(function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'UGX');
        $stripe = strtoupper($this->get_option('stripe_currency', 'USD'));
        if ($shop === $stripe) return true;
        return floatval($this->get_option('usd_exchange_rate', '0')) > 0;
    }

    public function momo_enabled(): bool {
        return $this->get_option('enable_momo', 'yes') === 'yes';
    }

    /**
     * Convert a shop-currency amount to the Stripe settlement currency
     * using the configured rate. Returns [convertedAmount, currencyCode].
     * No-ops (returns the input) when no FX is needed.
     */
    public function convert_for_stripe(float $amount, string $fromCurrency): array {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($this->get_option('stripe_currency', 'USD'));
        if ($from === $to) return [$amount, $to];
        $rate = floatval($this->get_option('usd_exchange_rate', '0'));
        if ($rate <= 0) return [$amount, $from];
        // Rate is units-of-shop-currency per 1 unit-of-Stripe-currency
        // (e.g. 3700 UGX = 1 USD), so to go shop → Stripe we divide.
        $converted = round($amount / $rate, 2);
        return [$converted, $to];
    }

    /* ------------------------------------------------------------------ */
    /*  Payment Fields (shown at checkout)                                  */
    /* ------------------------------------------------------------------ */

    public function payment_fields() {
        if ($this->description) {
            echo '<p>' . wp_kses_post($this->description) . '</p>';
        }

        $momoOn = $this->momo_enabled();
        $cardOn = $this->card_enabled();
        $defaultTab = $momoOn ? 'momo' : 'card';

        // FX preview for the Card tab — show the buyer what they'll
        // actually be charged on their statement, in the Stripe
        // settlement currency, BEFORE they place the order. Stripe
        // chargebacks spike when buyers see an unexpected amount on
        // the card statement.
        $cart = function_exists('WC') && WC()->cart ? WC()->cart : null;
        $cartTotal = $cart ? (float) $cart->get_total('edit') : 0.0;
        $shopCurrency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'UGX';
        [$cardAmount, $cardCurrency] = $this->convert_for_stripe($cartTotal, $shopCurrency);
        $rate = floatval($this->get_option('usd_exchange_rate', '3700'));
        ?>
        <div id="dgw-wc-payment-fields">
            <?php if ($momoOn && $cardOn): ?>
            <div class="dgw-wc-tabs">
                <button type="button" class="dgw-wc-tab active" data-tab="momo">
                    &#128241; Mobile Money
                </button>
                <button type="button" class="dgw-wc-tab" data-tab="card">
                    &#128179; Card
                </button>
            </div>
            <?php endif; ?>

            <?php if ($momoOn): ?>
            <div class="dgw-wc-tab-content dgw-wc-tab-momo <?php echo $defaultTab === 'momo' ? 'active' : ''; ?>">
                <p>
                    <label for="dgw_phone"><?php esc_html_e('Phone Number', 'dgateway'); ?> <span style="color:#dc2626">*</span></label>
                    <input type="tel" id="dgw_phone" name="dgw_phone"
                           class="input-text" placeholder="e.g. 256771234567" />
                    <small><?php esc_html_e('Enter with country code (e.g. 256 for Uganda). You will receive a payment prompt on this number.', 'dgateway'); ?></small>
                </p>
            </div>
            <?php endif; ?>

            <?php if ($cardOn): ?>
            <div class="dgw-wc-tab-content dgw-wc-tab-card <?php echo $defaultTab === 'card' ? 'active' : ''; ?>">
                <?php if (strtoupper($shopCurrency) !== strtoupper($cardCurrency) && $cartTotal > 0): ?>
                <div class="dgw-wc-card-fx">
                    <p><strong><?php esc_html_e('Charged on your card:', 'dgateway'); ?></strong>
                       <?php echo esc_html($cardCurrency . ' ' . number_format($cardAmount, 2)); ?>
                       <small style="display:block;margin-top:4px;color:#6b7280;">
                           &asymp; <?php echo esc_html($shopCurrency . ' ' . number_format($cartTotal, 0)); ?>
                           <?php
                           /* translators: %1$s = shop currency, %2$s = rate, %3$s = stripe currency */
                           printf(esc_html__('at %1$s %2$s per 1 %3$s', 'dgateway'),
                               esc_html($shopCurrency),
                               esc_html(number_format($rate, 0)),
                               esc_html($cardCurrency)
                           );
                           ?>
                       </small>
                    </p>
                </div>
                <?php endif; ?>
                <p><?php esc_html_e('You will be prompted to enter your card details securely after placing the order — your card data never touches this site.', 'dgateway'); ?></p>
            </div>
            <?php endif; ?>

            <input type="hidden" name="dgw_payment_method" id="dgw_payment_method" value="<?php echo esc_attr($defaultTab); ?>" />
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
     * Inline JS to intercept WC checkout response and handle DGateway
     * payment flow.
     *
     * The original implementation hooked $(document).ajaxComplete which
     * doesn't always fire on newer WC versions (Cart/Checkout Blocks
     * skip jQuery AJAX entirely). On classic checkout it usually does
     * fire — so we keep that hook AND add a defensive fallback that
     * intercepts the redirect via a setTimeout-based scan of the
     * "checkout-success" emitted state. For pure Blocks checkouts the
     * Blocks integration handles routing instead (see dgw-wc-blocks.js).
     *
     * Card vs MoMo branching uses BOTH `dgw_client_secret` AND
     * `dgw_stripe_key` together — previously a half-populated response
     * (Stripe key but empty client secret) silently fell through to the
     * MoMo flow, which is what produced JB's "Card shows mobile money
     * details" bug.
     */
    private function get_wc_checkout_js() {
        return <<<'JS'
(function($) {
    if (typeof $ === 'undefined') return;

    function handleDGatewayResponse(res) {
        if (!res || res.result !== 'success' || !res.dgw_reference) return false;

        var ref = res.dgw_reference;
        var redirectUrl = res.redirect || '';
        var isCard = !!(res.dgw_client_secret && res.dgw_stripe_key);

        if (isCard) {
            DGW_Modal.open({
                amount: parseFloat(res.dgw_amount) || 0,
                currency: res.dgw_currency || 'USD',
                shopAmount: parseFloat(res.dgw_shop_amount) || null,
                shopCurrency: res.dgw_shop_currency || null,
                exchangeRate: parseFloat(res.dgw_exchange_rate) || 0,
                provider: 'stripe',
                description: 'WooCommerce Order',
                successUrl: redirectUrl,
                onSuccess: function() { window.location.href = redirectUrl; },
            });
            DGW_Modal._reference = ref;
            DGW_Checkout.loadStripe(res.dgw_stripe_key).then(function(stripe) {
                DGW_Modal._stripe = stripe;
                DGW_Modal._clientSecret = res.dgw_client_secret;
                DGW_Modal._state = 'stripe_mount';
                DGW_Modal._render();
                DGW_Modal._show();
            });
            return true;
        }

        // MoMo flow: show the new waiting screen + smart polling.
        DGW_Modal.open({
            amount: parseFloat(res.dgw_amount) || 0,
            currency: res.dgw_currency || 'UGX',
            provider: 'iotec',
            description: 'WooCommerce Order',
            successUrl: redirectUrl,
            onSuccess: function() { window.location.href = redirectUrl; },
        });
        DGW_Modal._reference = ref;
        DGW_Modal._state = 'processing';
        DGW_Modal._render();
        DGW_Modal._show();
        DGW_Checkout.pollStatus(ref,
            function() { DGW_Modal._onSuccess(); },
            function(err) { DGW_Modal._onFailed(err); }
        );
        return true;
    }

    // Primary hook: jQuery ajaxComplete on classic WC checkout.
    $(document).ajaxComplete(function(event, xhr, settings) {
        if (!settings.url || settings.url.indexOf('wc-ajax=checkout') === -1) return;
        var res;
        try { res = JSON.parse(xhr.responseText); } catch(e) { return; }
        if (handleDGatewayResponse(res)) {
            // Prevent WC's default success-redirect behaviour.
            event.stopImmediatePropagation();
        }
    });

    // Fallback: WC fires "checkout_place_order_dgateway" via $.fn.trigger
    // on some themes that override checkout.js. Listen there too.
    $(document.body).on('checkout_place_order_dgateway', function() {
        return true; // let WC submit
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

        // Card rail must be enabled in settings and configured with a
        // valid exchange rate when the shop currency is non-Stripe-
        // supported. We re-check here in case the gateway was
        // misconfigured between the buyer hitting the page and submitting.
        if ($provider === 'stripe' && !$this->card_enabled()) {
            wc_add_notice(__('Card payments are not available right now. Please choose Mobile Money instead.', 'dgateway'), 'error');
            return ['result' => 'failure'];
        }

        // For Card: convert shop-currency total → Stripe settlement
        // currency using the configured rate. Stripe rejects unsupported
        // currencies (UGX, TZS, RWF, etc.) and even where supported, the
        // shop owner usually wants settlement in a major currency. We
        // store the original amount + rate + converted figure on the
        // order so the receipt and any reconciliation has the full
        // picture, not just "$5".
        $providerAmount = (float) $amount;
        $providerCurrency = $currency;
        if ($provider === 'stripe') {
            [$providerAmount, $providerCurrency] = $this->convert_for_stripe((float) $amount, $currency);
            if ($providerAmount <= 0) {
                wc_add_notice(__('Could not convert the order total to the Stripe settlement currency. Please contact the store.', 'dgateway'), 'error');
                return ['result' => 'failure'];
            }
        }

        $args = [
            'amount'       => $providerAmount,
            'currency'     => $providerCurrency,
            'phone_number' => $phone ?: '0000000000',
            'provider'     => $provider,
            'description'  => sprintf('WooCommerce Order #%d', $order_id),
            'metadata'     => [
                'order_id'        => $order_id,
                'source'          => 'woocommerce',
                'shop_currency'   => $currency,
                'shop_amount'     => (float) $amount,
                'exchange_rate'   => $provider === 'stripe' && $currency !== $providerCurrency
                    ? floatval($this->get_option('usd_exchange_rate', '0'))
                    : 0,
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

        // Store the reference + FX context in order meta.
        $order->update_meta_data('_dgw_reference', $reference);
        $order->update_meta_data('_dgw_provider', $provider);
        $order->update_meta_data('_dgw_method', $method);
        if ($provider === 'stripe') {
            // Persist the Stripe handoff so the thank-you page can
            // mount Elements server-side instead of depending on the
            // jQuery ajaxComplete intercept (which fires AFTER WC's
            // own redirect handler on most themes and silently
            // dropped the Stripe flow into the MoMo waiting UI). The
            // client_secret is intended to be exposed to the browser
            // by Stripe's own design; storing it on the order is
            // safe and lets us survive page reloads on the thank-you
            // step.
            $order->update_meta_data('_dgw_client_secret', $data['client_secret'] ?? '');
            $order->update_meta_data('_dgw_stripe_key', $data['stripe_publishable_key'] ?? '');
            $order->update_meta_data('_dgw_amount', $providerAmount);
            $order->update_meta_data('_dgw_currency', $providerCurrency);
            if ($currency !== $providerCurrency) {
                $order->update_meta_data('_dgw_stripe_amount', $providerAmount);
                $order->update_meta_data('_dgw_stripe_currency', $providerCurrency);
                $order->update_meta_data('_dgw_shop_amount', (float) $amount);
                $order->update_meta_data('_dgw_shop_currency', $currency);
                $order->update_meta_data('_dgw_exchange_rate', floatval($this->get_option('usd_exchange_rate', '0')));
            }
        }
        $order->save();

        // Set order on-hold with a clearer note when FX happened.
        if ($provider === 'stripe' && $currency !== $providerCurrency) {
            $order->update_status('on-hold', sprintf(
                __('Awaiting DGateway card payment of %1$s %2$s (= %3$s %4$s at %5$s/%6$s). Reference: %7$s', 'dgateway'),
                $providerCurrency,
                number_format($providerAmount, 2),
                $currency,
                number_format((float) $amount, 0),
                number_format(floatval($this->get_option('usd_exchange_rate', '0')), 0),
                $providerCurrency,
                $reference
            ));
        } else {
            $order->update_status('on-hold', sprintf(
                __('Awaiting DGateway %1$s payment. Reference: %2$s', 'dgateway'),
                $provider === 'stripe' ? 'card' : 'mobile money',
                $reference
            ));
        }

        // For Stripe, include client_secret so JS can mount Elements.
        // Pass through BOTH the converted amount + the original shop
        // amount so the modal can show "Charged: $5 (≈ UGX 18,500)".
        if ($provider === 'stripe') {
            return [
                'result'                => 'success',
                'redirect'              => $this->get_return_url($order),
                'dgw_reference'         => $reference,
                'dgw_amount'            => $providerAmount,
                'dgw_currency'          => $providerCurrency,
                'dgw_shop_amount'       => (float) $amount,
                'dgw_shop_currency'     => $currency,
                'dgw_exchange_rate'     => floatval($this->get_option('usd_exchange_rate', '0')),
                'dgw_client_secret'     => $data['client_secret'] ?? '',
                'dgw_stripe_key'        => $data['stripe_publishable_key'] ?? '',
            ];
        }

        // MoMo: redirect to thank-you page normally.
        // The polling UI is rendered via the woocommerce_thankyou_dgateway hook.
        return [
            'result'             => 'success',
            'redirect'           => $this->get_return_url($order),
            'dgw_reference'      => $reference,
            'dgw_amount'         => (float) $amount,
            'dgw_currency'       => $currency,
        ];
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

    /* ------------------------------------------------------------------ */
    /*  Thank-you Page Polling UI                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Render a polling UI on the thank-you page for pending MoMo payments.
     *
     * Mirrors the dashboard checkout waiting-screen pattern: 120s
     * budget, smart polling cadence (10/20/30/50/80/120 cumulative),
     * big live countdown + progress bar, "I've Paid — Check Now"
     * button, an in-app Cancel modal (no browser confirm()), and a
     * beforeunload guard so refreshing prompts the buyer. On terminal
     * states it offers Try Again (re-checkout) and Back (shop home).
     */
    public function thankyou_polling_ui($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || $order->is_paid()) {
            return;
        }

        $reference = $order->get_meta('_dgw_reference');
        if (empty($reference)) {
            return;
        }

        wp_enqueue_script('dgw-checkout');
        $checkoutUrl = function_exists('wc_get_checkout_url') ? wc_get_checkout_url() : home_url('/');
        $shopUrl = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');

        // Card flow needs an entirely different UI — render the Stripe
        // Elements form here instead of the mobile-money waiting card.
        // This was the bug JB reported: regardless of selected method
        // the thank-you page always showed the MoMo "we sent a prompt
        // to your phone" UI because the JS intercept that was meant
        // to open a separate Stripe modal lost the race against WC's
        // built-in redirect handler on most themes. Doing it
        // server-side from order meta is bullet-proof.
        $method = $order->get_meta('_dgw_method');
        if ($method === 'card') {
            $this->thankyou_stripe_ui($order, $reference, $shopUrl, $checkoutUrl);
            return;
        }
        ?>
        <div id="dgw-payment-polling" class="dgw-thankyou-waiter" data-ref="<?php echo esc_attr($reference); ?>">
            <div class="dgw-thankyou-card dgw-state-pending">
                <div class="dgw-thankyou-spinner-wrap">
                    <div class="dgw-thankyou-spinner"></div>
                    <span class="dgw-thankyou-ping" aria-hidden="true"></span>
                </div>
                <h3 class="dgw-thankyou-title">Waiting for Payment…</h3>
                <p class="dgw-thankyou-subtitle">
                    We sent a prompt to your phone. Open your mobile-money menu, enter your PIN, and approve the request.
                </p>

                <div class="dgw-thankyou-countdown">
                    <span class="dgw-thankyou-countdown-label">Time remaining</span>
                    <span class="dgw-thankyou-countdown-value" data-role="time">2:00</span>
                    <div class="dgw-thankyou-progress"><div class="dgw-thankyou-progress-fill" data-role="bar"></div></div>
                </div>

                <p class="dgw-thankyou-tick" data-role="tick">Next check in 10s · attempt 1 of 6</p>

                <div class="dgw-thankyou-actions">
                    <button type="button" class="dgw-thankyou-btn dgw-thankyou-btn-check" data-role="check">
                        I've Paid — Check Now
                    </button>
                    <button type="button" class="dgw-thankyou-btn dgw-thankyou-btn-cancel" data-role="cancel">
                        Cancel Payment
                    </button>
                </div>

                <p class="dgw-thankyou-ref">Ref: <?php echo esc_html($reference); ?></p>
            </div>

            <p class="dgw-thankyou-hint">
                Didn't receive the prompt? Check your phone's mobile-money menu manually, or dial *165# (MTN) / *185# (Airtel).
            </p>
        </div>

        <!-- Cancel-confirmation modal -->
        <div class="dgw-thankyou-modal" data-role="cancel-modal" hidden>
            <div class="dgw-thankyou-modal-backdrop" data-role="backdrop"></div>
            <div class="dgw-thankyou-modal-panel">
                <div class="dgw-thankyou-modal-icon">!</div>
                <h4>Cancel this payment?</h4>
                <p>
                    If you've already approved the prompt on your phone, the transaction may still complete and your receipt will arrive by email — no need to pay again.
                </p>
                <div class="dgw-thankyou-modal-actions">
                    <button type="button" class="dgw-thankyou-btn dgw-thankyou-btn-keep" data-role="keep">Keep waiting</button>
                    <button type="button" class="dgw-thankyou-btn dgw-thankyou-btn-confirm-cancel" data-role="confirm-cancel">Yes, cancel</button>
                </div>
            </div>
        </div>

        <script>
        (function() {
            // === Config ====================================================
            var ref = <?php echo wp_json_encode($reference); ?>;
            var verifyUrl = <?php echo wp_json_encode(esc_url_raw(rest_url('dgateway/v1/verify'))); ?>;
            var nonce = <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>;
            var checkoutUrl = <?php echo wp_json_encode(esc_url_raw($checkoutUrl)); ?>;
            var shopUrl = <?php echo wp_json_encode(esc_url_raw($shopUrl)); ?>;

            var POLL_INTERVALS = [10, 10, 10, 20, 30, 40]; // seconds — cumulative 10, 20, 30, 50, 80, 120
            var TOTAL_WINDOW = 120;

            // === State ====================================================
            var root = document.getElementById('dgw-payment-polling');
            if (!root) return;
            var modal = document.querySelector('.dgw-thankyou-modal');
            var stopped = false;
            var elapsed = 0;
            var pollIdx = 0;
            var nextCheckIn = POLL_INTERVALS[0];
            var pollTimer = null;
            var tickTimer = null;
            var checking = false;
            var status = 'pending';

            var el = {
                time:   root.querySelector('[data-role="time"]'),
                bar:    root.querySelector('[data-role="bar"]'),
                tick:   root.querySelector('[data-role="tick"]'),
                check:  root.querySelector('[data-role="check"]'),
                cancel: root.querySelector('[data-role="cancel"]'),
                card:   root.querySelector('.dgw-thankyou-card'),
            };

            function fmtTime(s) {
                var m = Math.floor(s / 60), r = s % 60;
                return m + ':' + (r < 10 ? '0' : '') + r;
            }

            function stopAll() {
                stopped = true;
                if (pollTimer) { clearTimeout(pollTimer); pollTimer = null; }
                if (tickTimer) { clearInterval(tickTimer); tickTimer = null; }
            }

            function setTerminal(newStatus) {
                stopAll();
                status = newStatus;
                renderTerminal();
            }

            function renderTerminal() {
                var titles = {
                    completed: 'Payment Successful!',
                    failed:    'Payment Failed',
                    expired:   'Payment Timed Out',
                    cancelled: 'Payment Cancelled',
                };
                var messages = {
                    completed: 'Your payment has been confirmed. Refreshing your order…',
                    failed:    "The provider couldn't complete the transaction. No money was charged. Please try again.",
                    expired:   "We didn't see a confirmation within 2 minutes. If you approved on your phone late, give it a moment and check your email — we'll send a receipt if it goes through. Otherwise try again.",
                    cancelled: "You cancelled the payment. If you'd already approved on your phone, the receipt will still arrive by email — no need to pay again.",
                };
                var html = ''
                    + '<div class="dgw-thankyou-spinner-wrap ' + (status === 'completed' ? 'dgw-success' : 'dgw-failed') + '">'
                    +   (status === 'completed'
                        ? '<svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>'
                        : status === 'expired'
                            ? '<svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
                            : '<svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>'
                       )
                    + '</div>'
                    + '<h3 class="dgw-thankyou-title">' + titles[status] + '</h3>'
                    + '<p class="dgw-thankyou-subtitle">' + messages[status] + '</p>'
                    + '<p class="dgw-thankyou-ref">Ref: ' + ref + '</p>';

                if (status !== 'completed') {
                    html += '<div class="dgw-thankyou-actions dgw-thankyou-actions-terminal">'
                        + '<a class="dgw-thankyou-btn dgw-thankyou-btn-retry" href="' + checkoutUrl + '">Try Again</a>'
                        + '<a class="dgw-thankyou-btn dgw-thankyou-btn-back" href="' + shopUrl + '">Back to Shop</a>'
                        + '</div>';
                }
                el.card.innerHTML = html;
                el.card.classList.remove('dgw-state-pending');
                el.card.classList.add('dgw-state-' + status);

                if (status === 'completed') {
                    setTimeout(function () { window.location.reload(); }, 1500);
                }
            }

            function checkOnce() {
                if (stopped || checking) return Promise.resolve(false);
                checking = true;
                el.card.classList.add('dgw-checking');
                return fetch(verifyUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                    body: JSON.stringify({ reference: ref }),
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var s = data && data.data && data.data.status;
                    if (s === 'completed' || s === 'failed') { setTerminal(s); return true; }
                    return false;
                })
                .catch(function () { return false; })
                .then(function (done) {
                    checking = false;
                    el.card.classList.remove('dgw-checking');
                    return done;
                });
            }

            function schedulePoll() {
                if (stopped || pollIdx >= POLL_INTERVALS.length) return;
                nextCheckIn = POLL_INTERVALS[pollIdx];
                pollTimer = setTimeout(function () {
                    checkOnce().then(function (done) {
                        if (done) return;
                        pollIdx += 1;
                        schedulePoll();
                    });
                }, nextCheckIn * 1000);
            }

            function tick() {
                if (stopped) return;
                elapsed += 1;
                nextCheckIn = Math.max(0, nextCheckIn - 1);
                var left = TOTAL_WINDOW - elapsed;
                if (el.time) el.time.textContent = fmtTime(Math.max(0, left));
                if (el.bar) el.bar.style.width = (Math.min(100, (elapsed / TOTAL_WINDOW) * 100)) + '%';
                if (el.tick) {
                    if (checking) {
                        el.tick.textContent = 'Checking your payment now…';
                    } else if (pollIdx < POLL_INTERVALS.length) {
                        el.tick.textContent = 'Next check in ' + nextCheckIn + 's · attempt ' + (pollIdx + 1) + ' of ' + POLL_INTERVALS.length;
                    } else {
                        el.tick.textContent = 'Still waiting for confirmation…';
                    }
                }
                if (left <= 0) setTerminal('expired');
            }

            // === Wire up buttons ==========================================
            if (el.check) el.check.addEventListener('click', function () { checkOnce(); });
            if (el.cancel) el.cancel.addEventListener('click', function () {
                if (!modal) return;
                modal.hidden = false;
            });
            if (modal) {
                modal.querySelector('[data-role="keep"]').addEventListener('click', function () { modal.hidden = true; });
                modal.querySelector('[data-role="backdrop"]').addEventListener('click', function () { modal.hidden = true; });
                modal.querySelector('[data-role="confirm-cancel"]').addEventListener('click', function () {
                    modal.hidden = true;
                    setTerminal('cancelled');
                });
            }

            // beforeunload guard while pending
            window.addEventListener('beforeunload', function (e) {
                if (status !== 'pending') return;
                e.preventDefault();
                e.returnValue = 'Your payment is still in progress. Leaving now will cancel it.';
            });

            // Kick off
            tickTimer = setInterval(tick, 1000);
            schedulePoll();
        })();
        </script>
        <style>
        .dgw-thankyou-waiter { max-width:480px; margin:32px auto; }
        .dgw-thankyou-card { background:#ffffff; border:1px solid #e5e7eb; border-radius:16px; padding:32px 28px; text-align:center; box-shadow:0 8px 24px rgba(0,0,0,0.06); position:relative; }
        .dgw-thankyou-spinner-wrap { display:inline-flex; width:80px; height:80px; align-items:center; justify-content:center; border-radius:50%; background:rgba(79,70,229,0.08); margin-bottom:20px; position:relative; }
        .dgw-thankyou-spinner { width:40px; height:40px; border:3px solid rgba(79,70,229,0.2); border-top-color:#4f46e5; border-radius:50%; animation:dgwSpin 0.9s linear infinite; }
        .dgw-thankyou-ping { position:absolute; top:-4px; right:-4px; width:12px; height:12px; border-radius:50%; background:#4f46e5; display:none; }
        .dgw-thankyou-card.dgw-checking .dgw-thankyou-ping { display:block; animation:dgwPing 1s ease-in-out infinite; }
        .dgw-thankyou-spinner-wrap.dgw-success { background:rgba(34,197,94,0.1); color:#22c55e; }
        .dgw-thankyou-spinner-wrap.dgw-success .dgw-thankyou-spinner,
        .dgw-thankyou-spinner-wrap.dgw-failed .dgw-thankyou-spinner { display:none; }
        .dgw-thankyou-spinner-wrap.dgw-failed { background:rgba(239,68,68,0.1); color:#ef4444; }
        .dgw-state-cancelled .dgw-thankyou-spinner-wrap.dgw-failed { background:rgba(245,158,11,0.1); color:#f59e0b; }
        .dgw-thankyou-title { margin:0 0 12px; font-size:22px; font-weight:700; color:#111827; }
        .dgw-thankyou-subtitle { margin:0 0 20px; font-size:14px; color:#4b5563; line-height:1.6; }
        .dgw-thankyou-countdown { border:1px solid #e5e7eb; border-radius:12px; padding:16px; background:#f9fafb; margin-bottom:16px; }
        .dgw-thankyou-countdown-label { display:block; font-size:11px; letter-spacing:0.08em; text-transform:uppercase; color:#6b7280; margin-bottom:4px; }
        .dgw-thankyou-countdown-value { display:block; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:32px; font-weight:700; color:#111827; }
        .dgw-thankyou-progress { margin-top:12px; height:6px; border-radius:999px; background:#e5e7eb; overflow:hidden; }
        .dgw-thankyou-progress-fill { height:100%; background:#4f46e5; width:0%; transition:width 1s linear; }
        .dgw-thankyou-tick { margin:0 0 20px; font-size:13px; color:#6b7280; }
        .dgw-thankyou-actions { display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:18px; }
        .dgw-thankyou-actions-terminal { max-width:280px; margin:24px auto 0; }
        .dgw-thankyou-btn { display:inline-flex; align-items:center; justify-content:center; padding:11px 14px; border-radius:10px; font-size:13px; font-weight:600; cursor:pointer; border:1px solid transparent; text-decoration:none; transition:background 0.15s, border 0.15s; }
        .dgw-thankyou-btn-check { background:rgba(79,70,229,0.08); color:#4f46e5; border-color:rgba(79,70,229,0.3); }
        .dgw-thankyou-btn-check:hover:not(:disabled) { background:rgba(79,70,229,0.14); }
        .dgw-thankyou-btn-cancel { background:transparent; color:#ef4444; border-color:rgba(239,68,68,0.3); }
        .dgw-thankyou-btn-cancel:hover { background:rgba(239,68,68,0.06); }
        .dgw-thankyou-btn-retry { background:#4f46e5; color:#ffffff; }
        .dgw-thankyou-btn-retry:hover { background:#4338ca; }
        .dgw-thankyou-btn-back { background:#ffffff; color:#374151; border-color:#d1d5db; }
        .dgw-thankyou-btn-back:hover { background:#f9fafb; }
        .dgw-thankyou-btn-keep { background:#f3f4f6; color:#374151; border-color:#d1d5db; }
        .dgw-thankyou-btn-confirm-cancel { background:#ef4444; color:#ffffff; }
        .dgw-thankyou-ref { margin:0; font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:11px; color:#9ca3af; }
        .dgw-thankyou-hint { margin:14px 0 0; text-align:center; font-size:12px; color:#9ca3af; }

        .dgw-thankyou-modal[hidden] { display:none; }
        .dgw-thankyou-modal { position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; padding:16px; }
        .dgw-thankyou-modal-backdrop { position:absolute; inset:0; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); }
        .dgw-thankyou-modal-panel { position:relative; background:#ffffff; border:1px solid #e5e7eb; border-radius:16px; padding:24px; max-width:360px; width:100%; text-align:center; box-shadow:0 20px 40px rgba(0,0,0,0.2); }
        .dgw-thankyou-modal-icon { width:48px; height:48px; border-radius:50%; background:rgba(245,158,11,0.12); color:#f59e0b; font-weight:700; font-size:24px; display:inline-flex; align-items:center; justify-content:center; margin-bottom:14px; }
        .dgw-thankyou-modal-panel h4 { margin:0 0 8px; font-size:17px; font-weight:600; color:#111827; }
        .dgw-thankyou-modal-panel p { margin:0 0 18px; font-size:13px; color:#4b5563; line-height:1.6; }
        .dgw-thankyou-modal-actions { display:grid; grid-template-columns:1fr 1fr; gap:10px; padding-top:14px; border-top:1px solid #e5e7eb; }

        @keyframes dgwSpin { to { transform: rotate(360deg); } }
        @keyframes dgwPing { 0% { transform:scale(1); opacity:1; } 75%, 100% { transform:scale(2); opacity:0; } }

        @media (prefers-color-scheme: dark) {
            .dgw-thankyou-card { background:#1f2937; border-color:rgba(255,255,255,0.08); }
            .dgw-thankyou-title { color:#f9fafb; }
            .dgw-thankyou-subtitle { color:#d1d5db; }
            .dgw-thankyou-countdown { background:#111827; border-color:rgba(255,255,255,0.06); }
            .dgw-thankyou-countdown-value { color:#f9fafb; }
            .dgw-thankyou-modal-panel { background:#1f2937; border-color:rgba(255,255,255,0.08); }
            .dgw-thankyou-modal-panel h4 { color:#f9fafb; }
            .dgw-thankyou-modal-panel p { color:#d1d5db; }
            .dgw-thankyou-btn-back { background:#1f2937; color:#e5e7eb; border-color:rgba(255,255,255,0.12); }
            .dgw-thankyou-btn-keep { background:#374151; color:#e5e7eb; border-color:rgba(255,255,255,0.12); }
        }
        </style>
        <?php
    }

    /**
     * Render the Stripe Elements card form on the thank-you page.
     *
     * Used when the buyer picked Card at checkout. We loaded a Stripe
     * publishable key + client_secret from the order meta — Stripe.js
     * mounts a PaymentElement, the buyer enters their card, and on
     * confirmation we poll the same /verify endpoint as MoMo to wait
     * for the webhook to land. If they leave the page before paying,
     * the on-hold order stays on-hold and can be retried by re-visiting
     * the order page (the form re-mounts cleanly because everything we
     * need is in order meta).
     */
    private function thankyou_stripe_ui(\WC_Order $order, string $reference, string $shopUrl, string $checkoutUrl) {
        $clientSecret = $order->get_meta('_dgw_client_secret');
        $stripeKey    = $order->get_meta('_dgw_stripe_key');
        $shopAmount   = (float) $order->get_meta('_dgw_shop_amount');
        $shopCurrency = $order->get_meta('_dgw_shop_currency');
        $stripeAmount = (float) ($order->get_meta('_dgw_stripe_amount') ?: $order->get_meta('_dgw_amount') ?: 0);
        $stripeCurrency = $order->get_meta('_dgw_stripe_currency') ?: $order->get_meta('_dgw_currency') ?: $order->get_currency();
        $rate         = floatval($order->get_meta('_dgw_exchange_rate'));

        // If Stripe data is missing the order was probably created
        // before the v1.1.0 storage change — fall back to a friendly
        // "contact support" message rather than rendering a broken
        // form with no client_secret.
        if (empty($clientSecret) || empty($stripeKey)) {
            echo '<div class="dgw-thankyou-waiter"><div class="dgw-thankyou-card"><h3 class="dgw-thankyou-title">Card details unavailable</h3><p class="dgw-thankyou-subtitle">We couldn\'t load the card form for this order. Please <a href="' . esc_url($checkoutUrl) . '">restart checkout</a> or contact the seller.</p></div></div>';
            return;
        }
        ?>
        <div id="dgw-stripe-pay" class="dgw-thankyou-waiter">
            <div class="dgw-thankyou-card dgw-state-pending">
                <h3 class="dgw-thankyou-title" style="margin-bottom:8px;">Complete your card payment</h3>
                <p class="dgw-thankyou-subtitle">Enter your card details below. Your data goes directly to Stripe — it never touches this site.</p>

                <div class="dgw-thankyou-countdown" style="text-align:left;">
                    <span class="dgw-thankyou-countdown-label">Charged on your card</span>
                    <span class="dgw-thankyou-countdown-value" style="font-size:26px;">
                        <?php echo esc_html(strtoupper($stripeCurrency) . ' ' . number_format($stripeAmount, 2)); ?>
                    </span>
                    <?php if (!empty($shopCurrency) && strtoupper($shopCurrency) !== strtoupper($stripeCurrency) && $shopAmount > 0): ?>
                    <p style="margin:6px 0 0;font-size:12px;color:#9ca3af;">
                        &asymp; <?php echo esc_html(strtoupper($shopCurrency) . ' ' . number_format($shopAmount, 0)); ?>
                        at <?php echo esc_html(strtoupper($shopCurrency) . ' ' . number_format($rate, 0)); ?> per 1 <?php echo esc_html(strtoupper($stripeCurrency)); ?>
                    </p>
                    <?php endif; ?>
                </div>

                <!-- Stripe Elements mounts here -->
                <div id="dgw-stripe-element" style="background:#0d0d14;border:1px solid rgba(255,255,255,0.08);border-radius:12px;padding:16px;margin-bottom:14px;min-height:80px;"></div>

                <div id="dgw-stripe-error" class="dgw-stripe-error" style="display:none;color:#ef4444;font-size:13px;margin-bottom:12px;text-align:left;"></div>

                <div class="dgw-thankyou-actions">
                    <button type="button" id="dgw-stripe-pay-btn" class="dgw-thankyou-btn dgw-thankyou-btn-retry" style="background:#635bff;border-color:#635bff;">
                        Pay <?php echo esc_html(strtoupper($stripeCurrency) . ' ' . number_format($stripeAmount, 2)); ?>
                    </button>
                    <a class="dgw-thankyou-btn dgw-thankyou-btn-back" href="<?php echo esc_url($shopUrl); ?>">Back</a>
                </div>

                <p class="dgw-thankyou-ref">Ref: <?php echo esc_html($reference); ?></p>
            </div>

            <p class="dgw-thankyou-hint">
                Secured by Stripe · 3D Secure supported · we never store your card.
            </p>
        </div>

        <script src="https://js.stripe.com/v3/"></script>
        <script>
        (function() {
            var ref = <?php echo wp_json_encode($reference); ?>;
            var stripeKey = <?php echo wp_json_encode($stripeKey); ?>;
            var clientSecret = <?php echo wp_json_encode($clientSecret); ?>;
            var verifyUrl = <?php echo wp_json_encode(esc_url_raw(rest_url('dgateway/v1/verify'))); ?>;
            var nonce = <?php echo wp_json_encode(wp_create_nonce('wp_rest')); ?>;
            var shopUrl = <?php echo wp_json_encode(esc_url_raw($shopUrl)); ?>;
            var checkoutUrl = <?php echo wp_json_encode(esc_url_raw($checkoutUrl)); ?>;

            var root = document.getElementById('dgw-stripe-pay');
            var payBtn = document.getElementById('dgw-stripe-pay-btn');
            var errBox = document.getElementById('dgw-stripe-error');
            var card = root.querySelector('.dgw-thankyou-card');

            function showError(msg) {
                if (!errBox) return;
                errBox.textContent = msg || 'Something went wrong.';
                errBox.style.display = 'block';
            }
            function clearError() {
                if (errBox) errBox.style.display = 'none';
            }

            // Initialise Stripe + mount Elements.
            var stripe, elements;
            try {
                stripe = window.Stripe(stripeKey);
                elements = stripe.elements({
                    clientSecret: clientSecret,
                    appearance: {
                        theme: 'night',
                        variables: { colorPrimary: '#635bff', colorBackground: '#0d0d14', colorText: '#e5e7eb' },
                    },
                });
                var paymentEl = elements.create('payment');
                paymentEl.mount('#dgw-stripe-element');
            } catch (e) {
                showError('Could not load the card form. Please refresh the page.');
                return;
            }

            payBtn.addEventListener('click', function() {
                clearError();
                payBtn.disabled = true;
                var originalText = payBtn.textContent;
                payBtn.textContent = 'Processing…';

                stripe.confirmPayment({
                    elements: elements,
                    redirect: 'if_required',
                }).then(function(result) {
                    if (result.error) {
                        showError(result.error.message || 'Card payment failed.');
                        payBtn.disabled = false;
                        payBtn.textContent = originalText;
                        return;
                    }
                    // Stripe accepted the payment — poll our verify endpoint until
                    // the webhook lands and the order flips to paid.
                    renderProcessing();
                    pollStatus();
                }).catch(function(err) {
                    showError(err.message || 'Card payment failed.');
                    payBtn.disabled = false;
                    payBtn.textContent = originalText;
                });
            });

            function renderProcessing() {
                card.innerHTML = ''
                    + '<div class="dgw-thankyou-spinner-wrap"><div class="dgw-thankyou-spinner"></div></div>'
                    + '<h3 class="dgw-thankyou-title">Confirming your payment…</h3>'
                    + '<p class="dgw-thankyou-subtitle">Your card was authorised. We&#39;re waiting on Stripe&#39;s final confirmation.</p>'
                    + '<p class="dgw-thankyou-ref">Ref: ' + ref + '</p>';
            }

            function renderTerminal(status) {
                var titles = { completed: 'Payment Successful!', failed: 'Payment Failed', expired: 'Payment Verification Slow' };
                var msgs = {
                    completed: 'Your card was charged and the order is confirmed. Refreshing your order…',
                    failed:    "Stripe couldn&#39;t complete the charge. No money was taken. Please try again.",
                    expired:   "We didn&#39;t see Stripe&#39;s confirmation yet — sometimes it takes another minute. If your card statement shows a charge, the order will update automatically.",
                };
                card.innerHTML = ''
                    + '<div class="dgw-thankyou-spinner-wrap ' + (status === 'completed' ? 'dgw-success' : 'dgw-failed') + '">'
                    +   (status === 'completed'
                        ? '<svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>'
                        : '<svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>'
                       )
                    + '</div>'
                    + '<h3 class="dgw-thankyou-title">' + titles[status] + '</h3>'
                    + '<p class="dgw-thankyou-subtitle">' + msgs[status] + '</p>'
                    + '<p class="dgw-thankyou-ref">Ref: ' + ref + '</p>'
                    + (status === 'completed' ? ''
                        : '<div class="dgw-thankyou-actions dgw-thankyou-actions-terminal">'
                          + '<a class="dgw-thankyou-btn dgw-thankyou-btn-retry" href="' + checkoutUrl + '">Try Again</a>'
                          + '<a class="dgw-thankyou-btn dgw-thankyou-btn-back" href="' + shopUrl + '">Back to Shop</a>'
                        + '</div>');
                if (status === 'completed') setTimeout(function () { window.location.reload(); }, 1500);
            }

            function pollStatus() {
                // Reuse the same smart cadence we use for MoMo — 6 polls
                // across 120s. Most Stripe webhooks land in under 5s
                // but card networks occasionally take a beat.
                var intervals = [5, 10, 15, 20, 30, 40];
                var idx = 0;
                var stopped = false;

                function go() {
                    if (stopped || idx >= intervals.length) {
                        if (!stopped) renderTerminal('expired');
                        return;
                    }
                    setTimeout(function() {
                        fetch(verifyUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                            body: JSON.stringify({ reference: ref })
                        })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            var s = data && data.data && data.data.status;
                            if (s === 'completed') { stopped = true; renderTerminal('completed'); return; }
                            if (s === 'failed')    { stopped = true; renderTerminal('failed'); return; }
                            idx++; go();
                        })
                        .catch(function() { idx++; go(); });
                    }, intervals[idx] * 1000);
                }
                go();
            }
        })();
        </script>
        <?php
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
