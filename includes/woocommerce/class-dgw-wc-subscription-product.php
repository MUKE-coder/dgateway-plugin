<?php
defined('ABSPATH') || exit;

/**
 * WooCommerce "DGateway Subscription" product type.
 *
 * Extends WC_Product_Simple so merchants can sell recurring subscription
 * products through WooCommerce with DGateway handling the billing.
 */
class DGW_WC_Subscription_Product {

    public function __construct() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Register product type.
        add_filter('product_type_selector', [$this, 'add_product_type']);
        add_filter('woocommerce_product_class', [$this, 'product_class'], 10, 2);

        // Custom product data tab.
        add_filter('woocommerce_product_data_tabs', [$this, 'product_data_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'product_data_panel']);
        add_action('woocommerce_process_product_meta', [$this, 'save_product_meta']);

        // Show price fields for our product type.
        add_action('woocommerce_product_options_pricing', [$this, 'show_price_fields']);
    }

    /**
     * Add "DGateway Subscription" to product type selector.
     */
    public function add_product_type(array $types): array {
        $types['dgw_subscription'] = __('DGateway Subscription', 'dgateway');
        return $types;
    }

    /**
     * Map product type to WC_Product_Simple so it works without a custom class.
     */
    public function product_class(string $classname, string $product_type): string {
        if ($product_type === 'dgw_subscription') {
            return 'WC_Product_Simple';
        }
        return $classname;
    }

    /**
     * Add "Subscription" data tab in product editor.
     */
    public function product_data_tab(array $tabs): array {
        $tabs['dgw_subscription'] = [
            'label'    => __('Subscription', 'dgateway'),
            'target'   => 'dgw_subscription_data',
            'class'    => ['show_if_dgw_subscription'],
            'priority' => 21,
        ];
        return $tabs;
    }

    /**
     * Render subscription settings panel.
     */
    public function product_data_panel() {
        global $post;
        $product_id = $post->ID;
        ?>
        <div id="dgw_subscription_data" class="panel woocommerce_options_panel hidden">
            <div class="options_group">
                <?php
                woocommerce_wp_text_input([
                    'id'          => '_dgw_plan_id',
                    'label'       => __('DGateway Plan ID', 'dgateway'),
                    'description' => __('The subscription plan ID from your DGateway dashboard.', 'dgateway'),
                    'desc_tip'    => true,
                    'type'        => 'number',
                    'value'       => get_post_meta($product_id, '_dgw_plan_id', true),
                ]);

                woocommerce_wp_select([
                    'id'      => '_dgw_interval',
                    'label'   => __('Billing Interval', 'dgateway'),
                    'options' => [
                        'monthly' => __('Monthly', 'dgateway'),
                        'weekly'  => __('Weekly', 'dgateway'),
                        'daily'   => __('Daily', 'dgateway'),
                        'yearly'  => __('Yearly', 'dgateway'),
                        'custom'  => __('Custom', 'dgateway'),
                    ],
                    'value' => get_post_meta($product_id, '_dgw_interval', true) ?: 'monthly',
                ]);

                woocommerce_wp_text_input([
                    'id'          => '_dgw_trial_days',
                    'label'       => __('Trial Days', 'dgateway'),
                    'description' => __('Number of free trial days. Leave 0 for no trial.', 'dgateway'),
                    'desc_tip'    => true,
                    'type'        => 'number',
                    'value'       => get_post_meta($product_id, '_dgw_trial_days', true) ?: '0',
                ]);
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Save subscription meta when product is saved.
     */
    public function save_product_meta(int $product_id) {
        $product = wc_get_product($product_id);
        if (!$product || $product->get_type() !== 'dgw_subscription') {
            return;
        }

        $fields = ['_dgw_plan_id', '_dgw_interval', '_dgw_trial_days'];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($product_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }

    /**
     * Show price fields for subscription products (same as simple products).
     */
    public function show_price_fields() {
        // WooCommerce JS hides price fields for unknown product types.
        // This inline script shows them for dgw_subscription.
        ?>
        <script>
            jQuery(function($) {
                $(document.body).on('woocommerce-product-type-change', function(e, type) {
                    if (type === 'dgw_subscription') {
                        $('.show_if_simple').show();
                        $('.options_group.pricing').show();
                    }
                });
                // Trigger on page load if already selected.
                if ($('#product-type').val() === 'dgw_subscription') {
                    $('.show_if_simple').show();
                    $('.options_group.pricing').show();
                }
            });
        </script>
        <?php
    }
}
